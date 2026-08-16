from pathlib import Path
import re

root = Path('.')

# --- RpsAssessmentSyncService -------------------------------------------------
p = root / 'app/Services/Rps/RpsAssessmentSyncService.php'
s = p.read_text(encoding='utf-8')

needle = """        $actualSubBudgets = $weeks
            ->filter(fn ($week) =>
"""
insert = """        // Simulasi menampilkan bukti/penugasan yang benar-benar jatuh
        // pada pekan tersebut. Asesmen agregat tetap menjadi sumber anggaran
        // bobot pada matriks, sedangkan judul RTM menjadi nama bukti per pekan.
        $taskEvidenceByWeek = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('due_week')
            ->orderBy('code')
            ->get(['due_week', 'title'])
            ->groupBy(fn ($task) => (int) $task->due_week);

        foreach ($taskEvidenceByWeek as $weekNumber => $taskItems) {
            $titles = $taskItems
                ->pluck('title')
                ->map(fn ($title) => trim((string) $title))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($titles !== [] && isset($namesByWeek[(int) $weekNumber])) {
                $namesByWeek[(int) $weekNumber] = $titles;
            }
        }

        $actualSubBudgets = $weeks
            ->filter(fn ($week) =>
"""
if needle not in s:
    raise SystemExit('snapshot insertion marker missing')
s = s.replace(needle, insert, 1)

old = """    public function syncVersion(string $versionId): array
    {
        $this->syncTaskMappings($versionId);
        $snapshot = $this->snapshot($versionId);
"""
new = """    public function syncVersion(string $versionId): array
    {
        $indicatorFixes = $this->syncWeeklyIndicators($versionId);
        $linkedTasks = $this->syncTaskMappings($versionId);
        $snapshot = $this->snapshot($versionId);
"""
if old not in s:
    raise SystemExit('syncVersion start marker missing')
s = s.replace(old, new, 1)

old = """            'message' => \"Sinkronisasi asesmen diterapkan: {$weightedTeachingWeeks}/14 pekan pembelajaran memiliki bobot berdasarkan tag Sub-CPMK asesmen; RTM terkait mengikuti tag asesmennya.\",
"""
new = """            'message' => \"Sinkronisasi asesmen diterapkan: {$weightedTeachingWeeks}/14 pekan pembelajaran memiliki bobot berdasarkan tag Sub-CPMK asesmen; {$linkedTasks} RTM terhubung ke asesmen; {$indicatorFixes} indikator pekan yang salah Sub-CPMK diperbaiki.\",
"""
if old not in s:
    raise SystemExit('syncVersion message marker missing')
s = s.replace(old, new, 1)

pattern = re.compile(r"    public function syncTaskMappings\(string \$versionId\): int\n    \{.*?\n    \}\n\n    public function taskAlignment", re.S)
replacement = r'''    public function syncTaskMappings(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(due_week, 99)')
            ->orderBy('code')
            ->get(['id', 'assessment_id', 'title', 'type', 'due_week']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'name', 'type', 'week_number']);

        $assessmentLinks = $assessments->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessments->pluck('id')->all())
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy('assessment_id');

        $weekSubs = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))
            ->pluck('rps_sub_cpmk_id', 'week_number');

        $linkedCount = 0;

        DB::transaction(function () use (
            $tasks,
            $assessments,
            $assessmentLinks,
            $weekSubs,
            &$linkedCount
        ): void {
            foreach ($tasks as $task) {
                $dueWeek = (int) ($task->due_week ?? 0);
                $weekSubId = filled($weekSubs->get($dueWeek))
                    ? (string) $weekSubs->get($dueWeek)
                    : null;

                $assessmentId = filled($task->assessment_id ?? null)
                    && $assessments->contains(fn ($assessment) => (string) $assessment->id === (string) $task->assessment_id)
                        ? (string) $task->assessment_id
                        : null;

                if ($assessmentId && $weekSubId && in_array($dueWeek, self::TEACHING_WEEKS, true)) {
                    $currentLinks = collect($assessmentLinks->get($assessmentId, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->unique();

                    if (! $currentLinks->contains($weekSubId)) {
                        $assessmentId = null;
                    }
                }

                if (! $assessmentId) {
                    $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);

                    $exact = $assessments->first(function ($assessment) use (
                        $normalizedTaskTitle,
                        $weekSubId,
                        $dueWeek,
                        $assessmentLinks
                    ): bool {
                        if ($this->normalizeLabel((string) $assessment->name) !== $normalizedTaskTitle) {
                            return false;
                        }

                        $type = strtolower((string) $assessment->type);
                        if ($dueWeek === 8) return $type === 'uts';
                        if ($dueWeek === 16) return $type === 'uas';
                        if (! $weekSubId) return true;

                        return collect($assessmentLinks->get($assessment->id, []))
                            ->pluck('rps_sub_cpmk_id')
                            ->map('strval')
                            ->contains($weekSubId);
                    });

                    if ($exact) {
                        $assessmentId = (string) $exact->id;
                    } else {
                        $taskType = strtolower((string) ($task->type ?? 'other'));

                        $candidates = $assessments
                            ->filter(function ($assessment) use ($weekSubId, $dueWeek, $assessmentLinks): bool {
                                $type = strtolower((string) $assessment->type);
                                if ($dueWeek === 8) return $type === 'uts';
                                if ($dueWeek === 16) return $type === 'uas';
                                if (! $weekSubId || in_array($type, ['uts', 'uas'], true)) return false;

                                return collect($assessmentLinks->get($assessment->id, []))
                                    ->pluck('rps_sub_cpmk_id')
                                    ->map('strval')
                                    ->contains($weekSubId);
                            })
                            ->sort(function ($a, $b) use ($taskType, $dueWeek): int {
                                $aTypePenalty = strtolower((string) $a->type) === $taskType ? 0 : 1;
                                $bTypePenalty = strtolower((string) $b->type) === $taskType ? 0 : 1;
                                if ($aTypePenalty !== $bTypePenalty) return $aTypePenalty <=> $bTypePenalty;

                                $aDistance = abs(((int) ($a->week_number ?? 99)) - $dueWeek);
                                $bDistance = abs(((int) ($b->week_number ?? 99)) - $dueWeek);
                                if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;

                                return ((int) ($a->week_number ?? 99)) <=> ((int) ($b->week_number ?? 99));
                            })
                            ->values();

                        if ($candidates->isNotEmpty()) {
                            $assessmentId = (string) $candidates->first()->id;
                        }
                    }

                    if ($assessmentId) {
                        DB::table('rps_tasks')
                            ->where('id', $task->id)
                            ->update([
                                'assessment_id' => $assessmentId,
                                'updated_at' => now(),
                            ]);
                    }
                }

                $subIds = $assessmentId
                    ? collect($assessmentLinks->get($assessmentId, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->unique()
                        ->values()
                    : collect($weekSubId ? [$weekSubId] : []);

                DB::table('rps_task_subcpmks')
                    ->where('rps_task_id', $task->id)
                    ->delete();

                foreach ($subIds as $subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $task->id,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($assessmentId) {
                    $linkedCount++;
                }
            }
        });

        return $linkedCount;
    }

    public function syncWeeklyIndicators(string $versionId): int
    {
        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'description'])
            ->keyBy(fn ($sub) => (string) $sub->id);

        if ($subs->isEmpty()) return 0;

        $descriptionOwner = [];
        foreach ($subs as $sub) {
            $normalized = $this->normalizeLabel((string) $sub->description);
            if ($normalized !== '') $descriptionOwner[$normalized] = (string) $sub->id;
        }

        $fixed = 0;
        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->whereNotNull('rps_sub_cpmk_id')
            ->whereNotNull('assessment_indicator')
            ->get(['id', 'rps_sub_cpmk_id', 'assessment_indicator']);

        foreach ($weeks as $week) {
            $currentSubId = (string) $week->rps_sub_cpmk_id;
            $currentSub = $subs->get($currentSubId);
            if (! $currentSub) continue;

            $ownerId = $descriptionOwner[$this->normalizeLabel((string) $week->assessment_indicator)] ?? null;
            if ($ownerId && $ownerId !== $currentSubId) {
                DB::table('rps_weekly_plans')
                    ->where('id', $week->id)
                    ->update([
                        'assessment_indicator' => trim((string) $currentSub->description),
                        'updated_at' => now(),
                    ]);
                $fixed++;
            }
        }

        return $fixed;
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
    }

    public function taskAlignment'''
new_s, count = pattern.subn(replacement, s, count=1)
if count != 1:
    raise SystemExit(f'syncTaskMappings replacement count {count}')
s = new_s

s = s.replace(
    "->get(['id', 'assessment_id']);\n\n        $linkedTasks = $tasks->filter",
    "->get(['id', 'assessment_id', 'due_week']);\n\n        $linkedTasks = $tasks->filter",
    1,
)

needle = """        $missingRequired = $requiredAssessmentIds->diff($coveredAssessmentIds)->values();

        return [
"""
insert = """        $missingRequired = $requiredAssessmentIds->diff($coveredAssessmentIds)->values();

        $weekRows = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->get(['week_number', 'rps_sub_cpmk_id', 'assessment_weight'])
            ->keyBy('week_number');

        $unlinkedWeightedTaskCount = 0;
        $dueWeekMismatchCount = 0;
        foreach ($tasks as $task) {
            $dueWeek = (int) ($task->due_week ?? 0);
            $week = $weekRows->get($dueWeek);
            if (! $week || (float) ($week->assessment_weight ?? 0) <= 0) continue;

            if (! filled($task->assessment_id ?? null)) {
                $unlinkedWeightedTaskCount++;
                continue;
            }

            if (filled($week->rps_sub_cpmk_id ?? null)) {
                $expected = collect($assessmentLinks->get($task->assessment_id, []))
                    ->pluck('rps_sub_cpmk_id')->map('strval')->unique();
                if (! $expected->contains((string) $week->rps_sub_cpmk_id)) $dueWeekMismatchCount++;
            }
        }

        return [
"""
if needle not in s:
    raise SystemExit('taskAlignment return marker missing')
s = s.replace(needle, insert, 1)

old = """            'mapping_mismatch_count' => $mismatchCount,
            'is_aligned' => $missingRequired->isEmpty() && $mismatchCount === 0,
"""
new = """            'mapping_mismatch_count' => $mismatchCount,
            'unlinked_weighted_task_count' => $unlinkedWeightedTaskCount,
            'due_week_subcpmk_mismatch_count' => $dueWeekMismatchCount,
            'is_aligned' => $missingRequired->isEmpty()
                && $mismatchCount === 0
                && $unlinkedWeightedTaskCount === 0
                && $dueWeekMismatchCount === 0,
"""
if old not in s:
    raise SystemExit('taskAlignment aligned marker missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# --- Validator ---------------------------------------------------------------
p = root / 'app/Services/Rps/ObeWorkspaceService.php'
s = p.read_text(encoding='utf-8')
old = ".\"RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']} dan asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.\"," 
new = ".\"RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']}; RTM berbobot tanpa asesmen {$taskAlignment['unlinked_weighted_task_count']}; ketidaksesuaian Sub-CPMK RTM dengan pekan {$taskAlignment['due_week_subcpmk_mismatch_count']}; asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.\"," 
if old not in s:
    raise SystemExit('assessment chain message marker missing')
s = s.replace(old, new, 1)
old = """                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
                    'rtm_required_missing' => $taskAlignment['missing_required_assessment_count'],
"""
new = """                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
                    'rtm_unlinked_weighted' => $taskAlignment['unlinked_weighted_task_count'],
                    'rtm_due_week_subcpmk_mismatch' => $taskAlignment['due_week_subcpmk_mismatch_count'],
                    'rtm_required_missing' => $taskAlignment['missing_required_assessment_count'],
"""
if old not in s:
    raise SystemExit('validator details marker missing')
s = s.replace(old, new, 1)
old = ": \"{$tasks} RTM tersedia; {$taskAlignment['missing_required_assessment_count']} asesmen tugas belum memiliki RTM; {$taskAlignment['mapping_mismatch_count']} RTM memiliki tag Sub-CPMK yang berbeda dari asesmennya.\"," 
new = ": \"{$tasks} RTM tersedia; {$taskAlignment['missing_required_assessment_count']} asesmen tugas belum memiliki RTM; {$taskAlignment['unlinked_weighted_task_count']} RTM berbobot belum terhubung asesmen; {$taskAlignment['due_week_subcpmk_mismatch_count']} RTM tidak cocok dengan Sub-CPMK pekannya; {$taskAlignment['mapping_mismatch_count']} RTM memiliki tag berbeda dari asesmennya.\"," 
if old not in s:
    raise SystemExit('RTM message marker missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# --- Isi Bagian Kosong ends with canonical assessment sync ------------------
p = root / 'app/Http/Controllers/RpsAutomationController.php'
s = p.read_text(encoding='utf-8')
old = """        RpsSmartDraftService $service
    ): RedirectResponse {"""
new = """        RpsSmartDraftService $service,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {"""
if old not in s:
    raise SystemExit('smartDraft signature marker missing')
s = s.replace(old, new, 1)
needle = """        $weightMessage = trim((string) ($result['weight_message'] ?? ''));

        return back()->with(
"""
insert = """        $weightMessage = trim((string) ($result['weight_message'] ?? ''));
        $syncResult = $assessmentSync->syncVersion($version->id);
        $syncMessage = trim((string) ($syncResult['message'] ?? ''));

        return back()->with(
"""
if needle not in s:
    raise SystemExit('smartDraft sync marker missing')
s = s.replace(needle, insert, 1)
old = """            \"Bagian kosong berhasil diisi. {$result['updated_weeks']} pertemuan diperbarui.\"
                .($weightMessage !== '' ? ' '.$weightMessage : '')
"""
new = """            \"Bagian kosong berhasil diisi. {$result['updated_weeks']} pertemuan diperbarui.\"
                .($weightMessage !== '' ? ' '.$weightMessage : '')
                .($syncMessage !== '' ? ' '.$syncMessage : '')
"""
if old not in s:
    raise SystemExit('smartDraft message marker missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# --- RTM display --------------------------------------------------------------
p = root / 'resources/js/pages/rps/show.tsx'
s = p.read_text(encoding='utf-8')
old = """                                                    <div><strong>Asesmen:</strong> {assessment?.name || '-'}</div>
                                                    <div><strong>Kriteria:</strong> {assessment?.description || '-'}</div>
"""
new = """                                                    <div><strong>Bentuk penilaian pekan:</strong> {task.title}</div>
                                                    <div><strong>Kriteria:</strong> {assessment?.description || '-'}</div>
"""
if old not in s:
    raise SystemExit('RTM display marker missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

checks = {
    'app/Services/Rps/RpsAssessmentSyncService.php': ['unlinked_weighted_task_count', 'syncWeeklyIndicators', 'taskEvidenceByWeek'],
    'app/Services/Rps/ObeWorkspaceService.php': ['RTM berbobot tanpa asesmen'],
    'app/Http/Controllers/RpsAutomationController.php': ['$syncResult = $assessmentSync->syncVersion($version->id);'],
    'resources/js/pages/rps/show.tsx': ['Bentuk penilaian pekan:'],
}
for path, markers in checks.items():
    text = (root / path).read_text(encoding='utf-8')
    for marker in markers:
        if marker not in text:
            raise SystemExit(f'missing marker {marker!r} in {path}')

print('Weekly evidence synchronization patch applied.')
