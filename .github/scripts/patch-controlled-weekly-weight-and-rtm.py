from pathlib import Path
import re

root = Path('.')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'missing marker: {label}')
    return text.replace(old, new, 1)


def regex_once(text: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f'{label}: replacement count {count}')
    return updated

# -----------------------------------------------------------------------------
# RpsAssessmentSyncService: preserve lecturer weekly overrides and auto-complete
# required RTM records from existing assessment data.
# -----------------------------------------------------------------------------
p = root / 'app/Services/Rps/RpsAssessmentSyncService.php'
s = p.read_text(encoding='utf-8')

marker = """        // Simulasi menampilkan bukti/penugasan yang benar-benar jatuh
"""
insert = """        // Distribusi manual dosen disimpan sebagai override per pekan pada
        // metadata versi RPS. Anggaran tetap berasal dari asesmen agregat.
        // Override hanya mengatur pembagian di dalam Sub-CPMK yang sama.
        $weightOverrides = $this->weightOverrides($versionId);
        $invalidWeightOverrideSubIds = [];

        foreach ($weeksBySub as $subId => $targetWeeks) {
            $targetCents = (int) ($aggregateSubCents[(string) $subId] ?? 0);
            $orderedWeeks = collect($targetWeeks)
                ->sortBy('week_number')
                ->values();

            $manual = $orderedWeeks
                ->filter(fn ($week) => array_key_exists((int) $week->week_number, $weightOverrides))
                ->mapWithKeys(fn ($week) => [
                    (int) $week->week_number => (int) round(
                        (float) $weightOverrides[(int) $week->week_number] * 100
                    ),
                ]);

            if ($manual->isEmpty()) {
                continue;
            }

            $autoWeeks = $orderedWeeks
                ->reject(fn ($week) => $manual->has((int) $week->week_number))
                ->values();
            $manualTotal = (int) $manual->sum();
            $remaining = $targetCents - $manualTotal;

            $valid = $targetCents > 0
                && $manual->every(fn ($cents) => (int) $cents >= 1)
                && $remaining >= $autoWeeks->count()
                && ($autoWeeks->isNotEmpty() || $remaining === 0);

            if (! $valid) {
                $invalidWeightOverrideSubIds[] = (string) $subId;
                continue;
            }

            foreach ($manual as $weekNumber => $cents) {
                $expectedCents[(int) $weekNumber] = (int) $cents;
            }

            if ($autoWeeks->isNotEmpty()) {
                $base = intdiv($remaining, $autoWeeks->count());
                $remainder = $remaining % $autoWeeks->count();

                foreach ($autoWeeks as $index => $week) {
                    $expectedCents[(int) $week->week_number] = $base
                        + ($index < $remainder ? 1 : 0);
                }
            }
        }

        // Simulasi menampilkan bukti/penugasan yang benar-benar jatuh
"""
s = replace_once(s, marker, insert, 'snapshot weight override insertion')

old = """            'orphan_sub_links' => $orphanSubLinks,
            'aggregate_total' => round((float) $assessments->sum(
"""
new = """            'orphan_sub_links' => $orphanSubLinks,
            'weight_overrides' => $weightOverrides,
            'invalid_weight_override_sub_ids' => array_values(array_unique($invalidWeightOverrideSubIds)),
            'aggregate_total' => round((float) $assessments->sum(
"""
s = replace_once(s, old, new, 'snapshot return override fields')

s = regex_once(
    s,
    r"    public function syncVersion\(string \$versionId\): array\n    \{.*?\n    \}\n\n    public function syncTaskMappings",
    r'''    public function syncVersion(string $versionId): array
    {
        $indicatorFixes = $this->syncWeeklyIndicators($versionId);

        // Petakan RTM lama terlebih dahulu agar asesmen yang sebenarnya sudah
        // mempunyai bukti tidak dibuatkan RTM duplikat.
        $this->syncTaskMappings($versionId);
        $createdTasks = $this->ensureRequiredTasks($versionId);
        $linkedTasks = $this->syncTaskMappings($versionId);

        $snapshot = $this->snapshot($versionId);

        // Bila anggaran asesmen berubah sehingga override manual lama tidak
        // lagi mungkin dipenuhi, hanya override pada Sub-CPMK tersebut yang
        // dilepas. Ini menjaga total 100% tetap konsisten dan tidak membiarkan
        // distribusi invalid tersembunyi.
        $invalidSubIds = $snapshot['invalid_weight_override_sub_ids'] ?? [];
        if ($invalidSubIds !== []) {
            $this->dropWeightOverridesForSubCpmks($versionId, $invalidSubIds);
            $snapshot = $this->snapshot($versionId);
        }

        DB::transaction(function () use ($versionId, $snapshot): void {
            foreach ($snapshot['expected_weekly_weights'] as $week => $weight) {
                DB::table('rps_weekly_plans')
                    ->where('rps_version_id', $versionId)
                    ->where('week_number', (int) $week)
                    ->update([
                        'assessment_weight' => (float) $weight,
                        'updated_at' => now(),
                    ]);
            }
        });

        $refreshed = $this->snapshot($versionId);
        $weightedTeachingWeeks = collect($refreshed['expected_weekly_weights'])
            ->filter(fn ($weight, $week) =>
                in_array((int) $week, self::TEACHING_WEEKS, true)
                && (float) $weight > 0
            )
            ->count();
        $manualWeightCount = count($refreshed['weight_overrides'] ?? []);

        return [
            ...$refreshed,
            'created_required_tasks' => $createdTasks,
            'message' => "Sinkronisasi asesmen diterapkan: {$weightedTeachingWeeks}/14 pekan pembelajaran memiliki bobot; {$manualWeightCount} pembagian bobot pekan ditetapkan manual; {$linkedTasks} RTM terhubung ke asesmen; {$createdTasks} RTM wajib dibuat otomatis dari Detail Asesmen; {$indicatorFixes} indikator pekan yang salah Sub-CPMK diperbaiki.",
        ];
    }

    /**
     * Membuat RTM minimum untuk asesmen tugas/proyek/praktikum/presentasi
     * berbobot yang belum memiliki RTM. Isi berasal dari data asesmen yang
     * sudah diputuskan dosen; tidak membuat bobot atau tag Sub-CPMK baru.
     */
    public function ensureRequiredTasks(string $versionId): int
    {
        $required = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'name', 'type', 'week_number', 'description']);

        if ($required->isEmpty()) {
            return 0;
        }

        $coveredIds = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('assessment_id')
            ->pluck('assessment_id')
            ->map('strval')
            ->unique();

        $missing = $required
            ->reject(fn ($assessment) => $coveredIds->contains((string) $assessment->id))
            ->values();

        if ($missing->isEmpty()) {
            return 0;
        }

        $assessmentLinks = DB::table('assessment_subcpmks')
            ->whereIn('assessment_id', $missing->pluck('id')->all())
            ->get(['assessment_id', 'rps_sub_cpmk_id'])
            ->groupBy('assessment_id');

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->whereNotNull('rps_sub_cpmk_id')
            ->get(['week_number', 'rps_sub_cpmk_id']);

        $usedWeeks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('due_week')
            ->pluck('due_week')
            ->map(fn ($week) => (int) $week)
            ->all();

        $existingCodes = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->all();
        $next = 1;
        while (in_array('RTM-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT), $existingCodes, true)) {
            $next++;
        }

        $created = 0;

        DB::transaction(function () use (
            $missing,
            $assessmentLinks,
            $weeks,
            &$usedWeeks,
            &$existingCodes,
            &$next,
            &$created,
            $versionId
        ): void {
            foreach ($missing as $assessment) {
                $subIds = collect($assessmentLinks->get($assessment->id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map('strval')
                    ->unique()
                    ->values();

                if ($subIds->isEmpty()) {
                    continue;
                }

                $preferred = (int) ($assessment->week_number ?? 0);
                $candidates = $weeks
                    ->filter(fn ($week) => $subIds->contains((string) $week->rps_sub_cpmk_id))
                    ->sort(function ($a, $b) use ($preferred, $usedWeeks): int {
                        $aWeek = (int) $a->week_number;
                        $bWeek = (int) $b->week_number;
                        $aDistance = $preferred > 0 ? abs($aWeek - $preferred) : $aWeek;
                        $bDistance = $preferred > 0 ? abs($bWeek - $preferred) : $bWeek;

                        if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;

                        $aUsed = in_array($aWeek, $usedWeeks, true) ? 1 : 0;
                        $bUsed = in_array($bWeek, $usedWeeks, true) ? 1 : 0;
                        if ($aUsed !== $bUsed) return $aUsed <=> $bUsed;

                        return $aWeek <=> $bWeek;
                    })
                    ->values();

                $dueWeek = $candidates->isNotEmpty()
                    ? (int) $candidates->first()->week_number
                    : ($preferred > 0 ? $preferred : null);

                while (in_array('RTM-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT), $existingCodes, true)) {
                    $next++;
                }

                $code = 'RTM-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
                $next++;
                $existingCodes[] = $code;
                if ($dueWeek) $usedWeeks[] = $dueWeek;

                $taskId = (string) Str::uuid();
                $name = trim((string) $assessment->name);
                $criteria = trim((string) ($assessment->description ?? ''));

                DB::table('rps_tasks')->insert([
                    'id' => $taskId,
                    'rps_version_id' => $versionId,
                    'assessment_id' => $assessment->id,
                    'code' => $code,
                    'title' => $name,
                    'type' => $assessment->type,
                    'purpose' => 'Mengukur ketercapaian Sub-CPMK melalui '.$name.'.',
                    'instructions' => $criteria !== ''
                        ? 'Kerjakan '.$name.' sesuai arahan dosen dengan memperhatikan kriteria penilaian: '.$criteria
                        : 'Kerjakan '.$name.' sesuai arahan dosen dan kriteria penilaian yang ditetapkan.',
                    'expected_output' => 'Luaran '.$name.' sesuai ketentuan asesmen.',
                    'due_week' => $dueWeek,
                    'source_type' => 'assessment_sync',
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($subIds as $subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $taskId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $created++;
            }
        });

        return $created;
    }

    public function syncTaskMappings''',
    'syncVersion + ensureRequiredTasks'
)

# Replace controlled rebalance and append metadata helpers before class end.
s = regex_once(
    s,
    r"    public function rebalanceTeachingWeek\(string \$versionId, int \$weekNumber, float \$newWeight\): array\n    \{.*?\n    \}\n\}",
    r'''    public function rebalanceTeachingWeek(string $versionId, int $weekNumber, float $newWeight): array
    {
        if (! in_array($weekNumber, self::TEACHING_WEEKS, true)) {
            throw ValidationException::withMessages([
                'weight' => 'Bobot UTS/UAS mengikuti asesmen sistem dan tidak diatur dari tabel RPS.',
            ]);
        }

        $week = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $weekNumber)
            ->first(['id', 'week_number', 'rps_sub_cpmk_id']);

        if (! $week || ! filled($week->rps_sub_cpmk_id ?? null)) {
            throw ValidationException::withMessages([
                'weight' => 'Pekan ini belum memiliki Sub-CPMK sehingga bobot belum dapat diatur.',
            ]);
        }

        $snapshot = $this->snapshot($versionId);
        $subId = (string) $week->rps_sub_cpmk_id;
        $target = (float) ($snapshot['aggregate_sub_budgets'][$subId] ?? 0);
        $targetCents = (int) round($target * 100);
        $newCents = (int) round(max(0, $newWeight) * 100);

        if ($targetCents <= 0) {
            throw ValidationException::withMessages([
                'weight' => 'Sub-CPMK pekan ini belum memiliki anggaran dari Asesmen Detail & RTM. Tag Sub-CPMK pada asesmen terlebih dahulu.',
            ]);
        }

        if ($newCents < 1) {
            throw ValidationException::withMessages([
                'weight' => 'Setiap pekan pembelajaran yang mengukur Sub-CPMK harus memiliki bobot positif minimal 0,01%.',
            ]);
        }

        if ($newCents > $targetCents) {
            throw ValidationException::withMessages([
                'weight' => "Bobot pekan tidak boleh melebihi anggaran Sub-CPMK {$target}%.",
            ]);
        }

        $group = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('rps_sub_cpmk_id', $subId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->orderBy('week_number')
            ->get(['id', 'week_number']);

        $overrides = $this->weightOverrides($versionId);
        $overrides[$weekNumber] = round($newCents / 100, 2);

        $groupWeekNumbers = $group->pluck('week_number')->map(fn ($value) => (int) $value);
        $manual = collect($overrides)
            ->filter(fn ($value, $key) => $groupWeekNumbers->contains((int) $key))
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) round((float) $value * 100)]);

        $manualTotal = (int) $manual->sum();
        $autoWeeks = $group
            ->reject(fn ($item) => $manual->has((int) $item->week_number))
            ->values();
        $remaining = $targetCents - $manualTotal;

        if ($manualTotal > $targetCents) {
            throw ValidationException::withMessages([
                'weight' => "Jumlah pembagian manual pada {$this->subCpmkCode($subId)} melebihi anggaran {$target}%. Kurangi salah satu bobot pekan.",
            ]);
        }

        if ($autoWeeks->isEmpty() && $remaining !== 0) {
            throw ValidationException::withMessages([
                'weight' => "Seluruh pekan {$this->subCpmkCode($subId)} sudah diatur manual. Totalnya harus tepat {$target}%.",
            ]);
        }

        if ($autoWeeks->isNotEmpty() && $remaining < $autoWeeks->count()) {
            throw ValidationException::withMessages([
                'weight' => 'Perubahan ini menyisakan kurang dari 0,01% untuk salah satu pekan lain pada Sub-CPMK yang sama.',
            ]);
        }

        $distribution = [];
        foreach ($manual as $number => $cents) {
            $distribution[(int) $number] = round($cents / 100, 2);
        }

        if ($autoWeeks->isNotEmpty()) {
            $base = intdiv($remaining, $autoWeeks->count());
            $remainder = $remaining % $autoWeeks->count();
            foreach ($autoWeeks as $index => $autoWeek) {
                $distribution[(int) $autoWeek->week_number] = round(
                    ($base + ($index < $remainder ? 1 : 0)) / 100,
                    2
                );
            }
        }

        ksort($distribution);

        DB::transaction(function () use ($group, $distribution, $versionId, $overrides): void {
            foreach ($group as $item) {
                $number = (int) $item->week_number;
                DB::table('rps_weekly_plans')
                    ->where('id', $item->id)
                    ->update([
                        'assessment_weight' => (float) ($distribution[$number] ?? 0),
                        'updated_at' => now(),
                    ]);
            }

            $this->saveWeightOverrides($versionId, $overrides);
        });

        return [
            'sub_budget' => $target,
            'sub_code' => $this->subCpmkCode($subId),
            'week_count' => $group->count(),
            'manual_week_count' => $manual->count(),
            'distribution' => $distribution,
        ];
    }

    private function weightOverrides(string $versionId): array
    {
        $raw = DB::table('rps_versions')
            ->where('id', $versionId)
            ->value('ai_generation_meta');

        if (is_string($raw)) {
            $meta = json_decode($raw, true);
        } elseif (is_object($raw)) {
            $meta = (array) $raw;
        } elseif (is_array($raw)) {
            $meta = $raw;
        } else {
            $meta = [];
        }

        $overrides = is_array($meta['weekly_weight_overrides'] ?? null)
            ? $meta['weekly_weight_overrides']
            : [];

        $clean = [];
        foreach ($overrides as $week => $weight) {
            $number = (int) $week;
            $value = round((float) $weight, 2);
            if (in_array($number, self::TEACHING_WEEKS, true) && $value > 0) {
                $clean[$number] = $value;
            }
        }

        ksort($clean);
        return $clean;
    }

    private function saveWeightOverrides(string $versionId, array $overrides): void
    {
        $raw = DB::table('rps_versions')
            ->where('id', $versionId)
            ->value('ai_generation_meta');

        if (is_string($raw)) {
            $meta = json_decode($raw, true);
        } elseif (is_object($raw)) {
            $meta = (array) $raw;
        } elseif (is_array($raw)) {
            $meta = $raw;
        } else {
            $meta = [];
        }

        if (! is_array($meta)) $meta = [];

        $clean = [];
        foreach ($overrides as $week => $weight) {
            $number = (int) $week;
            $value = round((float) $weight, 2);
            if (in_array($number, self::TEACHING_WEEKS, true) && $value > 0) {
                $clean[(string) $number] = $value;
            }
        }

        $meta['weekly_weight_overrides'] = $clean;

        DB::table('rps_versions')
            ->where('id', $versionId)
            ->update([
                'ai_generation_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function dropWeightOverridesForSubCpmks(string $versionId, array $subIds): void
    {
        $weekNumbers = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('rps_sub_cpmk_id', $subIds)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->pluck('week_number')
            ->map(fn ($value) => (int) $value)
            ->all();

        $overrides = $this->weightOverrides($versionId);
        foreach ($weekNumbers as $weekNumber) {
            unset($overrides[$weekNumber]);
        }
        $this->saveWeightOverrides($versionId, $overrides);
    }

    private function subCpmkCode(string $subId): string
    {
        return (string) (
            DB::table('rps_sub_cpmks')->where('id', $subId)->value('code')
            ?? 'Sub-CPMK'
        );
    }
}
''',
    'rebalanceTeachingWeek + metadata helpers'
)

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# RpsController: self-heal legacy RTM once, expose controlled weight metadata.
# -----------------------------------------------------------------------------
p = root / 'app/Http/Controllers/RpsController.php'
s = p.read_text(encoding='utf-8')

old = """        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
"""
new = """        // RPS lama dapat memiliki asesmen wajib yang belum mempunyai RTM.
        // Heal hanya ketika rantainya memang belum selaras; RPS yang sudah
        // valid tidak melakukan write pada setiap GET.
        $initialTaskAlignment = $assessmentSync->taskAlignment($version->id);
        if (! (bool) ($initialTaskAlignment['is_aligned'] ?? false)) {
            $assessmentSync->syncVersion($version->id);
        }

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
"""
s = replace_once(s, old, new, 'RpsController legacy RTM heal')

old = """        $assessmentNamesByWeek = collect(
            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []
        );

        $weeks = $weeks->map(function ($week) use (
            $subById,
            $assessmentWeightsByWeek,
            $assessmentNamesByWeek
        ): object {
"""
new = """        $assessmentNamesByWeek = collect(
            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []
        );
        $assessmentSubBudgets = collect(
            $assessmentSyncSnapshot['aggregate_sub_budgets'] ?? []
        );
        $weightOverrides = collect(
            $assessmentSyncSnapshot['weight_overrides'] ?? []
        );
        $teachingWeekCountsBySub = $weeks
            ->filter(fn ($item) =>
                in_array((int) $item->week_number, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true)
                && filled($item->rps_sub_cpmk_id ?? null)
            )
            ->groupBy(fn ($item) => (string) $item->rps_sub_cpmk_id)
            ->map(fn ($items) => $items->count());

        $weeks = $weeks->map(function ($week) use (
            $subById,
            $assessmentWeightsByWeek,
            $assessmentNamesByWeek,
            $assessmentSubBudgets,
            $weightOverrides,
            $teachingWeekCountsBySub
        ): object {
"""
s = replace_once(s, old, new, 'RpsController weight metadata setup')

old = """            $week->assessment_names = $assessmentNamesByWeek->get($weekNumber, '');

            return $week;
"""
new = """            $week->assessment_names = $assessmentNamesByWeek->get($weekNumber, '');
            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $subBudget = $subId
                ? (float) $assessmentSubBudgets->get($subId, 0)
                : 0.0;
            $isTeachingWeek = ! in_array($weekNumber, [8, 16], true);

            $week->assessment_sub_budget = $subBudget;
            $week->assessment_sub_week_count = $subId
                ? (int) $teachingWeekCountsBySub->get($subId, 0)
                : 0;
            $week->assessment_weight_editable = $isTeachingWeek
                && $subId !== null
                && $subBudget > 0;
            $week->assessment_weight_manual = $isTeachingWeek
                && $weightOverrides->has($weekNumber);

            return $week;
"""
s = replace_once(s, old, new, 'RpsController week metadata fields')

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# RpsDocumentController: richer validation/sync notification.
# -----------------------------------------------------------------------------
p = root / 'app/Http/Controllers/RpsDocumentController.php'
s = p.read_text(encoding='utf-8')
old = """        return back()->with(
            'success',
            \"Bobot pengukuran pekan {$week} disimpan {$newWeight}%. \"
                .\"Pekan lain pada Sub-CPMK yang sama otomatis diseimbangkan \"
                .\"agar total tetap {$result['sub_budget']}%.\"
        );
"""
new = """        $distribution = collect($result['distribution'] ?? [])
            ->map(fn ($weight, $number) => 'P'.$number.'='.$weight.'%')
            ->implode(', ');

        return back()->with(
            'success',
            \"Bobot Pekan {$week} disimpan {$newWeight}%. Anggaran {$result['sub_code']} tetap {$result['sub_budget']}%; distribusi: {$distribution}. Bobot asesmen agregat tidak berubah dan RTM/validator mengikuti bobot pekan terbaru.\"
        );
"""
s = replace_once(s, old, new, 'RpsDocumentController success message')
p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# show.tsx: editable-controlled only in RPS table, simulation read-only.
# -----------------------------------------------------------------------------
p = root / 'resources/js/pages/rps/show.tsx'
s = p.read_text(encoding='utf-8')

s = regex_once(
    s,
    r"function InlineWeightInput\(\{ rpsId, week \}: any\) \{.*?\n\}\n\nfunction SubCpmkMeetingPlanner",
    r'''function InlineWeightInput({ rpsId, week }: any) {
    const numeric = Number(week.assessment_weight || 0);
    const original = String(numeric);
    const isExam = Boolean(week.is_exam) || [8, 16].includes(Number(week.week_number));
    const editable = Boolean(week.assessment_weight_editable) && !isExam;
    const budget = Number(week.assessment_sub_budget || 0);
    const groupCount = Number(week.assessment_sub_week_count || 0);
    const isManual = Boolean(week.assessment_weight_manual);

    const form = useForm({
        weight: original,
    });

    useEffect(() => {
        form.setData('weight', original);
        form.clearErrors();
    }, [original]);

    if (isExam) {
        return (
            <span
                className="font-bold text-slate-700"
                title="Bobot UTS/UAS mengikuti Asesmen Detail & RTM."
            >
                {numeric || '—'}
            </span>
        );
    }

    if (!editable) {
        return (
            <span
                className="inline-flex min-w-12 items-center justify-center rounded border border-slate-200 bg-slate-50 px-1 py-1 text-xs font-bold text-slate-400"
                title="Belum dapat diedit. Tetapkan bobot dan tag Sub-CPMK pada Asesmen Detail & RTM terlebih dahulu."
            >
                {numeric > 0 ? numeric : '—'}
            </span>
        );
    }

    const save = () => {
        if (String(form.data.weight) === original || form.processing) return;

        const next = Number(form.data.weight);
        if (!Number.isFinite(next)) {
            form.setData('weight', original);
            return;
        }

        const confirmed = confirm(
            `Ubah bobot Pekan ${week.week_number} menjadi ${next}%?\n\n`
            + `Anggaran ${week.sub_cpmk_code || 'Sub-CPMK'} tetap ${budget}% untuk ${groupCount} pekan. `
            + 'Sistem akan menyeimbangkan sisa anggaran pada pekan lain dalam Sub-CPMK yang sama, sambil mempertahankan pembagian manual yang sudah ada. '
            + 'Bobot asesmen agregat tidak berubah; RTM dan Validator OBE akan mengikuti distribusi terbaru.',
        );

        if (!confirmed) {
            form.setData('weight', original);
            return;
        }

        form.put(
            `/rps/${rpsId}/weeks/${week.week_number}/weight`,
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: (page: any) => {
                    notify(
                        'success',
                        safeText(
                            page?.props?.flash?.success,
                            `Bobot Pekan ${week.week_number} disimpan dan seluruh representasi penilaian disinkronkan.`,
                        ),
                    );
                },
                onError: (errors) => {
                    form.setData('weight', original);
                    notify('error', firstError(errors));
                },
            },
        );
    };

    return (
        <>
            <input
                type="number"
                min="0.01"
                max={Math.max(0.01, budget)}
                step="0.01"
                value={form.data.weight}
                disabled={form.processing}
                onChange={(e) => form.setData('weight', e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        (e.currentTarget as HTMLInputElement).blur();
                    }
                }}
                className={`w-full rounded border px-1 py-1 text-center text-xs font-bold print:hidden ${
                    isManual
                        ? 'border-amber-300 bg-amber-50 text-amber-800'
                        : 'border-sky-200 bg-sky-50/40 text-sky-800'
                } disabled:opacity-50`}
                title={`Editable karena ${week.sub_cpmk_code || 'Sub-CPMK'} memiliki anggaran asesmen ${budget}%. Perubahan hanya mengatur distribusi pekan.`}
            />
            {isManual && (
                <span className="mt-0.5 block text-[8px] font-bold text-amber-600 print:hidden">manual</span>
            )}
            <span className="hidden font-bold print:inline">
                {numeric || '-'}
            </span>
        </>
    );
}

function SubCpmkMeetingPlanner''',
    'InlineWeightInput controlled'
)

s = regex_once(
    s,
    r"function SimulationWeightInput\(\{ rpsId, week, value \}: any\) \{.*?\n\}\n\nfunction AssessmentEvaluationSection",
    r'''function SimulationWeightInput({ value }: any) {
    return (
        <span
            className="font-bold text-slate-700"
            title="Bobot simulasi mengikuti distribusi Bobot Penilaian pada tabel RPS. Edit bobot dari tabel RPS."
        >
            {Number(value || 0) > 0 ? Number(value) : '—'}
        </span>
    );
}

function AssessmentEvaluationSection''',
    'SimulationWeightInput read only'
)

s = s.replace(
    'Jumlah yang ditetapkan dosen menjadi acuan utama. <strong>Lengkapi RPS Otomatis</strong> dan <strong>Susun AI</strong> akan mengikuti alokasi ini, bukan membagi ulang Sub-CPMK.',
    'Jumlah yang ditetapkan dosen menjadi acuan utama. <strong>Isi Bagian Kosong</strong> dan <strong>Susun AI</strong> akan mengikuti alokasi ini, bukan membagi ulang Sub-CPMK.',
)

old = """                                    Asesmen menyimpan bobot agregat/anggaran penilaian (total 100%). Bobot non-UTS/UAS kemudian didistribusikan ke 14 pekan pada tabel RPS; keduanya adalah dua representasi yang sama dan tidak dijumlahkan dua kali.
"""
new = """                                    Asesmen menyimpan bobot agregat/anggaran penilaian (total 100%). Bobot non-UTS/UAS kemudian didistribusikan ke 14 pekan pada tabel RPS. Bobot pekan boleh dikoreksi langsung dari tabel RPS setelah asesmen/tag Sub-CPMK tersedia; perubahan hanya mengatur distribusi pekan, tidak mengubah bobot agregat, dan RTM serta Validator OBE ikut tersinkron.
"""
s = replace_once(s, old, new, 'assessment panel explanatory text')

old = 'title="Mengisi bagian RPS yang masih kosong. Jika total asesmen agregat sudah 100%, bobot non-UTS/UAS juga dibagi ke pekan kosong berdasarkan Sub-CPMK dan jumlah pertemuannya, tanpa menimpa bobot yang sudah diisi dosen."'
new = 'title="Mengisi bagian RPS yang masih kosong dan menyinkronkan distribusi bobot dari Asesmen Detail. Pembagian bobot pekan yang sudah ditetapkan manual oleh dosen dipertahankan selama tetap sesuai anggaran Sub-CPMK."'
s = s.replace(old, new)

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# Validator wording: Pekan consistently.
# -----------------------------------------------------------------------------
p = root / 'app/Services/Rps/ObeWorkspaceService.php'
s = p.read_text(encoding='utf-8')
s = s.replace('UTS minggu 8 dan UAS minggu 16.', 'UTS pekan 8 dan UAS pekan 16.')
p.write_text(s, encoding='utf-8')

print('controlled weekly weights + RTM auto-completion patched')
