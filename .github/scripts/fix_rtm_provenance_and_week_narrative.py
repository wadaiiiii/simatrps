from pathlib import Path
import re

service_path = Path('app/Services/Rps/RpsAssessmentSyncService.php')
controller_path = Path('app/Http/Controllers/RpsController.php')

service = service_path.read_text(encoding='utf-8')
controller = controller_path.read_text(encoding='utf-8')

# 1) Snapshot must actually select source_type so stale assessment_sync RTM can be filtered.
old = "->get(['id', 'code', 'due_week', 'title', 'assessment_id', 'type']);"
new = "->get(['id', 'code', 'due_week', 'title', 'assessment_id', 'type', 'source_type']);"
if old not in service:
    raise SystemExit('snapshot task select marker not found')
service = service.replace(old, new, 1)

# 2) Replace syncTaskMappings: automatic RTM may be exact-rematched/deleted;
# lecturer-owned RTM is never silently reassigned or rewritten.
pattern = re.compile(
    r"    public function syncTaskMappings\(string \$versionId\): int\n    \{.*?\n    \}\n\n(?=    public function syncWeeklyIndicators)",
    re.S,
)
replacement = r'''    public function syncTaskMappings(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(due_week, 99)')
            ->orderBy('code')
            ->get(['id', 'assessment_id', 'title', 'type', 'due_week', 'source_type']);

        if ($tasks->isEmpty()) return 0;

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

        DB::transaction(function () use ($tasks, $assessments, $assessmentLinks, $weekSubs, &$linkedCount): void {
            foreach ($tasks as $task) {
                $dueWeek = (int) ($task->due_week ?? 0);
                $weekSubId = filled($weekSubs->get($dueWeek))
                    ? (string) $weekSubs->get($dueWeek)
                    : null;
                $sourceType = strtolower((string) ($task->source_type ?? 'manual'));
                $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);

                $currentAssessment = filled($task->assessment_id ?? null)
                    ? $assessments->first(
                        fn ($assessment) => (string) $assessment->id === (string) $task->assessment_id
                    )
                    : null;

                // Keputusan dosen tidak boleh ditimpa oleh sinkronisasi.
                // RTM manual tetap dipertahankan apa adanya; validator yang
                // memberi rekomendasi bila judul/Sub-CPMK/pekannya tidak selaras.
                if ($sourceType !== 'assessment_sync') {
                    if ($currentAssessment) $linkedCount++;
                    continue;
                }

                // RTM hasil sinkronisasi hanya valid bila nama asesmennya exact
                // dan asesmen tersebut memang mengukur Sub-CPMK pada pekan ini.
                $assessmentId = $currentAssessment ? (string) $currentAssessment->id : null;
                $isCurrentValid = false;

                if ($currentAssessment) {
                    $type = strtolower((string) $currentAssessment->type);
                    $titleMatches = $this->normalizeLabel((string) $currentAssessment->name)
                        === $normalizedTaskTitle;

                    if ($dueWeek === 8) {
                        $subMatches = $type === 'uts';
                    } elseif ($dueWeek === 16) {
                        $subMatches = $type === 'uas';
                    } elseif ($weekSubId && ! in_array($type, ['uts', 'uas'], true)) {
                        $subMatches = collect($assessmentLinks->get($currentAssessment->id, []))
                            ->pluck('rps_sub_cpmk_id')
                            ->map(fn ($id) => (string) $id)
                            ->contains($weekSubId);
                    } else {
                        $subMatches = false;
                    }

                    $isCurrentValid = $titleMatches && $subMatches;
                }

                if (! $isCurrentValid) {
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
                        if (! $weekSubId || in_array($type, ['uts', 'uas'], true)) return false;

                        return collect($assessmentLinks->get($assessment->id, []))
                            ->pluck('rps_sub_cpmk_id')
                            ->map(fn ($id) => (string) $id)
                            ->contains($weekSubId);
                    });

                    if (! $exact) {
                        DB::table('rps_task_subcpmks')
                            ->where('rps_task_id', $task->id)
                            ->delete();
                        DB::table('rps_tasks')
                            ->where('id', $task->id)
                            ->delete();
                        continue;
                    }

                    $assessmentId = (string) $exact->id;
                    DB::table('rps_tasks')->where('id', $task->id)->update([
                        'assessment_id' => $assessmentId,
                        'updated_at' => now(),
                    ]);
                }

                $assessmentSubIds = collect($assessmentLinks->get($assessmentId, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();

                if (in_array($dueWeek, self::TEACHING_WEEKS, true) && $weekSubId) {
                    $subIds = $assessmentSubIds->contains($weekSubId)
                        ? collect([$weekSubId])
                        : collect();
                } else {
                    $subIds = $assessmentSubIds;
                }

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

                $linkedCount++;
            }
        });

        return $linkedCount;
    }

'''
service, count = pattern.subn(replacement, service, count=1)
if count != 1:
    raise SystemExit(f'syncTaskMappings replacement count={count}')

# 3) Persist mechanical Sub-CPMK code drift in generated weekly narrative.
old = """        $indicatorFixes = $this->syncWeeklyIndicators($versionId);\n\n        // Petakan RTM lama terlebih dahulu"""
new = """        $indicatorFixes = $this->syncWeeklyIndicators($versionId);\n        $narrativeFixes = $this->syncWeeklySubCpmkNarratives($versionId);\n\n        // Petakan RTM lama terlebih dahulu"""
if old not in service:
    raise SystemExit('syncVersion narrative hook marker not found')
service = service.replace(old, new, 1)

old_msg = "{$createdTasks} RTM wajib dibuat otomatis dari Detail Asesmen; {$indicatorFixes} indikator pekan yang salah Sub-CPMK diperbaiki."
new_msg = "{$createdTasks} RTM wajib dibuat otomatis dari Detail Asesmen; {$indicatorFixes} indikator dan {$narrativeFixes} narasi pekan yang salah Sub-CPMK diperbaiki."
if old_msg not in service:
    raise SystemExit('syncVersion message marker not found')
service = service.replace(old_msg, new_msg, 1)

insert_marker = """    private function normalizeLabel(string $value): string\n    {"""
helper = r'''    public function syncWeeklySubCpmkNarratives(string $versionId): int
    {
        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code'])
            ->keyBy(fn ($sub) => (string) $sub->id);

        if ($subs->isEmpty()) return 0;

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->whereNotNull('rps_sub_cpmk_id')
            ->get([
                'id', 'rps_sub_cpmk_id', 'assessment_criteria',
                'learning_activity', 'student_assignment', 'online_activity',
            ]);

        $fixed = 0;
        $pattern = '/Sub[\s\-‐‑‒–—]*CPMK[\s\-‐‑‒–—]*\d+/iu';

        foreach ($weeks as $week) {
            $sub = $subs->get((string) $week->rps_sub_cpmk_id);
            if (! $sub) continue;

            $currentCode = trim((string) $sub->code);
            $updates = [];

            foreach ([
                'assessment_criteria',
                'learning_activity',
                'student_assignment',
                'online_activity',
            ] as $field) {
                $value = trim((string) ($week->{$field} ?? ''));
                if ($value === '') continue;

                preg_match_all($pattern, $value, $matches);
                $codes = collect($matches[0] ?? [])
                    ->map(fn ($match) => $this->normalizeLabel((string) $match))
                    ->filter()
                    ->unique()
                    ->values();

                // Jangan mengubah narasi yang sengaja menyebut beberapa
                // Sub-CPMK sekaligus; hanya betulkan satu referensi mekanis
                // yang tertinggal setelah alokasi pertemuan berubah.
                if ($codes->count() !== 1) continue;

                $currentNormalized = $this->normalizeLabel($currentCode);
                if ($codes->first() === $currentNormalized) continue;

                $updated = preg_replace($pattern, $currentCode, $value) ?? $value;
                if ($updated !== $value) $updates[$field] = $updated;
            }

            if ($updates === []) continue;

            DB::table('rps_weekly_plans')
                ->where('id', $week->id)
                ->update([
                    ...$updates,
                    'updated_at' => now(),
                ]);
            $fixed++;
        }

        return $fixed;
    }

'''
if insert_marker not in service:
    raise SystemExit('normalizeLabel marker not found')
service = service.replace(insert_marker, helper + insert_marker, 1)

# 4) taskAlignment must ignore stale auto RTM even before an explicit write/sync.
old = """        $tasks = DB::table('rps_tasks')\n            ->where('rps_version_id', $versionId)\n            ->get(['id', 'assessment_id', 'due_week']);\n\n        $linkedTasks = $tasks->filter(fn ($task) => filled($task->assessment_id ?? null));"""
new = """        $assessmentNamesById = DB::table('assessments')\n            ->where('rps_version_id', $versionId)\n            ->pluck('name', 'id');\n\n        $tasks = DB::table('rps_tasks')\n            ->where('rps_version_id', $versionId)\n            ->get(['id', 'assessment_id', 'due_week', 'title', 'source_type'])\n            ->filter(function ($task) use ($assessmentNamesById): bool {\n                if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                    return true;\n                }\n\n                $assessmentId = filled($task->assessment_id ?? null)\n                    ? (string) $task->assessment_id\n                    : null;\n                if (! $assessmentId || ! $assessmentNamesById->has($assessmentId)) return false;\n\n                return $this->normalizeLabel((string) $task->title)\n                    === $this->normalizeLabel((string) $assessmentNamesById->get($assessmentId));\n            })\n            ->values();\n\n        $linkedTasks = $tasks->filter(fn ($task) => filled($task->assessment_id ?? null));"""
if old not in service:
    raise SystemExit('taskAlignment task query marker not found')
service = service.replace(old, new, 1)

# 5) RpsController presentation: normalize stale Sub-CPMK code references immediately.
old = """            $week->sub_cpmk_code = $sub?->code;\n            $week->sub_cpmk_description = $sub?->description;\n            $storedWeight = $week->assessment_weight ?? null;"""
new = """            $week->sub_cpmk_code = $sub?->code;\n            $week->sub_cpmk_description = $sub?->description;\n\n            foreach ([\n                'assessment_criteria',\n                'learning_activity',\n                'student_assignment',\n                'online_activity',\n            ] as $field) {\n                $week->{$field} = $this->normalizeWeekSubCpmkNarrative(\n                    $week->{$field} ?? null,\n                    $sub?->code\n                );\n            }\n\n            $storedWeight = $week->assessment_weight ?? null;"""
if old not in controller:
    raise SystemExit('RpsController week normalization marker not found')
controller = controller.replace(old, new, 1)

# 6) Hide stale auto RTM from UI/PDF immediately; manual RTM remains visible/advisory.
old = """        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->map(function ($task) use ($weekSubByNumber): object {"""
new = """        $assessmentNamesById = $assessments\n            ->mapWithKeys(fn ($assessment) => [(string) $assessment->id => (string) $assessment->name]);\n\n        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->filter(function ($task) use ($assessmentNamesById): bool {\n                    if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                        return true;\n                    }\n\n                    $assessmentId = filled($task->assessment_id ?? null)\n                        ? (string) $task->assessment_id\n                        : null;\n                    if (! $assessmentId || ! $assessmentNamesById->has($assessmentId)) return false;\n\n                    return $this->normalizeRtmLabel((string) $task->title)\n                        === $this->normalizeRtmLabel((string) $assessmentNamesById->get($assessmentId));\n                })\n                ->map(function ($task) use ($weekSubByNumber): object {"""
if old not in controller:
    raise SystemExit('RpsController task filter marker not found')
controller = controller.replace(old, new, 1)

# Ensure collection indices are normalized after filter/map.
old = """                    return $task;\n                })\n            : collect();\n\n        $simulationScores"""
new = """                    return $task;\n                })\n                ->values()\n            : collect();\n\n        $simulationScores"""
if old not in controller:
    raise SystemExit('RpsController task values marker not found')
controller = controller.replace(old, new, 1)

# Private presentation helpers before splitReferenceGroups.
marker = """    private function splitReferenceGroups(string $text): array\n    {"""
helpers = r'''    private function normalizeWeekSubCpmkNarrative(mixed $value, mixed $currentCode): mixed
    {
        if (! is_string($value) || trim($value) === '' || ! is_string($currentCode) || trim($currentCode) === '') {
            return $value;
        }

        $pattern = '/Sub[\s\-‐‑‒–—]*CPMK[\s\-‐‑‒–—]*\d+/iu';
        preg_match_all($pattern, $value, $matches);

        $codes = collect($matches[0] ?? [])
            ->map(fn ($match) => $this->normalizeRtmLabel((string) $match))
            ->filter()
            ->unique()
            ->values();

        if ($codes->count() !== 1) return $value;
        if ($codes->first() === $this->normalizeRtmLabel($currentCode)) return $value;

        return preg_replace($pattern, trim($currentCode), $value) ?? $value;
    }

    private function normalizeRtmLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

'''
if marker not in controller:
    raise SystemExit('splitReferenceGroups marker not found')
controller = controller.replace(marker, helpers + marker, 1)

service_path.write_text(service, encoding='utf-8')
controller_path.write_text(controller, encoding='utf-8')
print('patched RTM provenance, stale auto filtering, and weekly Sub-CPMK narrative')
