from pathlib import Path

SERVICE = Path('app/Services/Rps/RpsAssessmentSyncService.php')
CONTROLLER = Path('app/Http/Controllers/RpsController.php')
SHOW = Path('resources/js/pages/rps/show.tsx')
CSS = Path('resources/css/app.css')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly 1 match, found {count}')
    return text.replace(old, new, 1)


# ---------------------------------------------------------------------------
# 1. Legacy RTM provenance repair
# ---------------------------------------------------------------------------
service = SERVICE.read_text(encoding='utf-8')

service = replace_once(
    service,
    "->get(['id', 'code', 'due_week', 'title', 'assessment_id', 'type', 'source_type']);",
    "->get(['id', 'code', 'due_week', 'title', 'assessment_id', 'type', 'source_type', 'purpose', 'instructions', 'expected_output']);",
    'snapshot task fields',
)

old_snapshot_filter = """        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);\n        $tasks = $tasks->filter(function ($task) use ($assessmentById): bool {\n            if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                return true;\n            }\n\n            $assessmentId = filled($task->assessment_id ?? null)\n                ? (string) $task->assessment_id\n                : null;\n            if (! $assessmentId) return false;\n\n            $assessment = $assessmentById->get($assessmentId);\n            if (! $assessment) return false;\n\n            return $this->normalizeLabel((string) $assessment->name)\n                === $this->normalizeLabel((string) $task->title);\n        })->values();\n"""
new_snapshot_filter = """        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);\n        $tasks = $tasks->filter(function ($task) use ($assessmentById): bool {\n            // RTM manual dosen selalu dipertahankan. RTM hasil sinkronisasi baru\n            // maupun RTM legacy yang memiliki sidik teks generator harus tunduk\n            // pada pemeriksaan induk asesmen yang ketat.\n            if (! $this->isGeneratedTask($task)) {\n                return true;\n            }\n\n            $assessmentId = filled($task->assessment_id ?? null)\n                ? (string) $task->assessment_id\n                : null;\n            if (! $assessmentId) return false;\n\n            $assessment = $assessmentById->get($assessmentId);\n            if (! $assessment) return false;\n\n            return $this->normalizeLabel((string) $assessment->name)\n                === $this->normalizeLabel((string) $task->title);\n        })->values();\n"""
service = replace_once(service, old_snapshot_filter, new_snapshot_filter, 'snapshot generated-task filter')

service = replace_once(
    service,
    "->get(['id', 'assessment_id', 'title', 'type', 'due_week', 'source_type']);",
    "->get(['id', 'assessment_id', 'title', 'type', 'due_week', 'source_type', 'purpose', 'instructions', 'expected_output']);",
    'syncTaskMappings task fields',
)

service = replace_once(
    service,
    "                $sourceType = strtolower((string) ($task->source_type ?? 'manual'));\n                $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);",
    "                $isGenerated = $this->isGeneratedTask($task);\n                $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);",
    'syncTaskMappings generated flag',
)

service = replace_once(
    service,
    """                // Keputusan dosen tidak boleh ditimpa oleh sinkronisasi.\n                // RTM manual tetap dipertahankan apa adanya; validator yang\n                // memberi rekomendasi bila judul/Sub-CPMK/pekannya tidak selaras.\n                if ($sourceType !== 'assessment_sync') {\n                    if ($currentAssessment) $linkedCount++;\n                    continue;\n                }\n""",
    """                // Keputusan dosen tidak boleh ditimpa oleh sinkronisasi.\n                // RTM manual tetap dipertahankan apa adanya; validator yang\n                // memberi rekomendasi bila judul/Sub-CPMK/pekannya tidak selaras.\n                // RTM legacy yang jelas memiliki sidik generator diperlakukan\n                // sebagai assessment_sync agar data lama yang salah induk dapat dibersihkan.\n                if (! $isGenerated) {\n                    if ($currentAssessment) $linkedCount++;\n                    continue;\n                }\n""",
    'syncTaskMappings manual guard',
)

service = replace_once(
    service,
    """                if (! $isCurrentValid) {\n                    $exact = $assessments->first(function ($assessment) use (\n""",
    """                if ($isCurrentValid && strtolower(trim((string) ($task->source_type ?? ''))) !== 'assessment_sync') {\n                    DB::table('rps_tasks')->where('id', $task->id)->update([\n                        'source_type' => 'assessment_sync',\n                        'updated_at' => now(),\n                    ]);\n                }\n\n                if (! $isCurrentValid) {\n                    $exact = $assessments->first(function ($assessment) use (\n""",
    'legacy provenance normalization',
)

service = replace_once(
    service,
    """                    DB::table('rps_tasks')->where('id', $task->id)->update([\n                        'assessment_id' => $assessmentId,\n                        'updated_at' => now(),\n                    ]);\n""",
    """                    DB::table('rps_tasks')->where('id', $task->id)->update([\n                        'assessment_id' => $assessmentId,\n                        'source_type' => 'assessment_sync',\n                        'updated_at' => now(),\n                    ]);\n""",
    'legacy exact remap provenance',
)

helper = r'''    private function isGeneratedTask(object $task): bool
    {
        $sourceType = strtolower(trim((string) ($task->source_type ?? '')));

        if ($sourceType === 'assessment_sync') return true;
        if ($sourceType === 'manual') return false;
        if ($sourceType !== '' && $sourceType !== 'legacy') return false;

        $purpose = mb_strtolower(trim((string) ($task->purpose ?? '')));
        $instructions = mb_strtolower(trim((string) ($task->instructions ?? '')));
        $output = mb_strtolower(trim((string) ($task->expected_output ?? '')));
        $signals = 0;

        if (str_starts_with($purpose, 'mengukur ketercapaian sub-cpmk melalui')) {
            $signals++;
        }
        if (str_starts_with($instructions, 'kerjakan ')
            && str_contains($instructions, 'sesuai arahan dosen')) {
            $signals++;
        }
        if (str_starts_with($output, 'luaran ')
            && str_contains($output, 'sesuai ketentuan asesmen')) {
            $signals++;
        }

        return $signals >= 2;
    }

'''
service = replace_once(
    service,
    "    private function normalizeLabel(string $value): string\n    {",
    helper + "    private function normalizeLabel(string $value): string\n    {",
    'generated-task helper insertion',
)

old_alignment = """        $tasks = DB::table('rps_tasks')\n            ->where('rps_version_id', $versionId)\n            ->get(['id', 'assessment_id', 'due_week', 'title', 'source_type'])\n            ->filter(function ($task) use ($assessmentNamesById): bool {\n                if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                    return true;\n                }\n\n                $assessmentId = filled($task->assessment_id ?? null)\n                    ? (string) $task->assessment_id\n                    : null;\n                if (! $assessmentId || ! $assessmentNamesById->has($assessmentId)) return false;\n\n                return $this->normalizeLabel((string) $task->title)\n                    === $this->normalizeLabel((string) $assessmentNamesById->get($assessmentId));\n            })\n            ->values();\n"""
new_alignment = """        $tasks = DB::table('rps_tasks')\n            ->where('rps_version_id', $versionId)\n            ->get(['id', 'assessment_id', 'due_week', 'title', 'source_type', 'purpose', 'instructions', 'expected_output'])\n            ->filter(function ($task) use ($assessmentNamesById): bool {\n                if (! $this->isGeneratedTask($task)) {\n                    return true;\n                }\n\n                $assessmentId = filled($task->assessment_id ?? null)\n                    ? (string) $task->assessment_id\n                    : null;\n                if (! $assessmentId || ! $assessmentNamesById->has($assessmentId)) return false;\n\n                return $this->normalizeLabel((string) $task->title)\n                    === $this->normalizeLabel((string) $assessmentNamesById->get($assessmentId));\n            })\n            ->values();\n"""
service = replace_once(service, old_alignment, new_alignment, 'taskAlignment legacy filter')
SERVICE.write_text(service, encoding='utf-8')


# ---------------------------------------------------------------------------
# 2. Controller must hide stale legacy-generated RTM immediately on refresh.
#    This remains read-only: the actual database cleanup still happens during
#    an explicit assessment/RTM synchronization action.
# ---------------------------------------------------------------------------
controller = CONTROLLER.read_text(encoding='utf-8')

old_controller_filter = """                ->filter(function ($task) use ($assessmentById, $normalizeAssessmentLabel): bool {\n                    if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                        return true;\n                    }\n\n                    $assessmentId = filled($task->assessment_id ?? null)\n                        ? (string) $task->assessment_id\n                        : null;\n                    if (! $assessmentId) return false;\n\n                    $assessment = $assessmentById->get($assessmentId);\n                    if (! $assessment) return false;\n\n                    return $normalizeAssessmentLabel((string) $assessment->name)\n                        === $normalizeAssessmentLabel((string) $task->title);\n                })\n"""
new_controller_filter = """                ->filter(function ($task) use ($assessmentById, $normalizeAssessmentLabel): bool {\n                    if (! $this->isGeneratedRtm($task)) {\n                        return true;\n                    }\n\n                    $assessmentId = filled($task->assessment_id ?? null)\n                        ? (string) $task->assessment_id\n                        : null;\n                    if (! $assessmentId) return false;\n\n                    $assessment = $assessmentById->get($assessmentId);\n                    if (! $assessment) return false;\n\n                    return $normalizeAssessmentLabel((string) $assessment->name)\n                        === $normalizeAssessmentLabel((string) $task->title);\n                })\n"""
controller = replace_once(controller, old_controller_filter, new_controller_filter, 'controller legacy task filter')

controller_helper = r'''    private function isGeneratedRtm(object $task): bool
    {
        $sourceType = strtolower(trim((string) ($task->source_type ?? '')));

        if ($sourceType === 'assessment_sync') return true;
        if ($sourceType === 'manual') return false;
        if ($sourceType !== '' && $sourceType !== 'legacy') return false;

        $purpose = mb_strtolower(trim((string) ($task->purpose ?? '')));
        $instructions = mb_strtolower(trim((string) ($task->instructions ?? '')));
        $output = mb_strtolower(trim((string) ($task->expected_output ?? '')));
        $signals = 0;

        if (str_starts_with($purpose, 'mengukur ketercapaian sub-cpmk melalui')) $signals++;
        if (str_starts_with($instructions, 'kerjakan ')
            && str_contains($instructions, 'sesuai arahan dosen')) $signals++;
        if (str_starts_with($output, 'luaran ')
            && str_contains($output, 'sesuai ketentuan asesmen')) $signals++;

        return $signals >= 2;
    }

'''
controller = replace_once(
    controller,
    "    private function normalizeWeekSubCpmkNarrative(mixed $value, mixed $currentCode): mixed\n    {",
    controller_helper + "    private function normalizeWeekSubCpmkNarrative(mixed $value, mixed $currentCode): mixed\n    {",
    'controller generated RTM helper insertion',
)
CONTROLLER.write_text(controller, encoding='utf-8')


# ---------------------------------------------------------------------------
# 3. Chromium print: one logical Pekan becomes one tbody print block.
# ---------------------------------------------------------------------------
show = SHOW.read_text(encoding='utf-8')
show = replace_once(
    show,
    '<table className="min-w-[1180px] w-full border-separate border-spacing-0 text-[11px] leading-[1.45]">',
    '<table className="rps-print-weekly min-w-[1180px] w-full border-separate border-spacing-0 text-[11px] leading-[1.45]">',
    'weekly print class',
)

old_week_body = """                            <tbody>\n                                {weeks.map((week: any) => (\n                                    <DocumentWeekRow\n                                        key={week.week_number}\n                                        rpsId={rps.id}\n                                        week={week}\n                                        subCpmks={subCpmks}\n                                        credits={rps.credits}\n                                        bibliography={bibliography}\n                                        aiConfigured={ai.configured}\n                                        aiBusy={aiBusyWeek === week.week_number}\n                                        onGenerateAi={(overwrite: boolean) => generateWeekAi(week.week_number, overwrite)}\n                                    />\n                                ))}\n                            </tbody>\n"""
new_week_body = """                            {weeks.map((week: any) => (\n                                <tbody key={week.week_number} className=\"rps-print-week-block\">\n                                    <DocumentWeekRow\n                                        rpsId={rps.id}\n                                        week={week}\n                                        subCpmks={subCpmks}\n                                        credits={rps.credits}\n                                        bibliography={bibliography}\n                                        aiConfigured={ai.configured}\n                                        aiBusy={aiBusyWeek === week.week_number}\n                                        onGenerateAi={(overwrite: boolean) => generateWeekAi(week.week_number, overwrite)}\n                                    />\n                                </tbody>\n                            ))}\n"""
show = replace_once(show, old_week_body, new_week_body, 'weekly tbody print blocks')

# Current main already contains Jurusan Matematika in both official headers.
# Fail loudly if a future base revision accidentally drops it before this patch runs.
if show.count('JURUSAN MATEMATIKA') < 2:
    raise SystemExit('institution headers: JURUSAN MATEMATIKA missing from RPS/RTM')
SHOW.write_text(show, encoding='utf-8')


# ---------------------------------------------------------------------------
# 4. Print CSS: prevent Chromium from splitting a Pekan across pages.
# ---------------------------------------------------------------------------
css = CSS.read_text(encoding='utf-8-sig')
old_break = """    html.rps-print-mode .rps-print-weekly tbody tr {\n        break-inside: auto !important;\n        page-break-inside: auto !important;\n    }\n\n    html.rps-print-mode .rps-print-weekly tbody td {\n        break-inside: auto !important;\n        page-break-inside: auto !important;\n    }\n"""
new_break = """    html.rps-print-mode .rps-print-weekly .rps-print-week-block,\n    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr,\n    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr > td {\n        break-inside: avoid !important;\n        page-break-inside: avoid !important;\n    }\n"""
css = replace_once(css, old_break, new_break, 'weekly print break rule')

# Keep the generic long-table rule for main/RTM tables, but do not undo the
# stricter weekly row rule later in the stylesheet.
old_long = """    html.rps-print-mode .rps-print-main-table tr,\n    html.rps-print-mode .rps-print-main-table td,\n    html.rps-print-mode .rps-print-weekly tr,\n    html.rps-print-mode .rps-print-weekly td,\n    html.rps-print-mode .rps-print-rtm-table tr,\n    html.rps-print-mode .rps-print-rtm-table td {\n        break-inside: auto !important;\n        page-break-inside: auto !important;\n    }\n"""
new_long = """    html.rps-print-mode .rps-print-main-table tr,\n    html.rps-print-mode .rps-print-main-table td,\n    html.rps-print-mode .rps-print-rtm-table tr,\n    html.rps-print-mode .rps-print-rtm-table td {\n        break-inside: auto !important;\n        page-break-inside: auto !important;\n    }\n\n    html.rps-print-mode .rps-print-weekly .rps-print-week-block,\n    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr,\n    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr > td {\n        break-inside: avoid !important;\n        page-break-inside: avoid !important;\n    }\n"""
css = replace_once(css, old_long, new_long, 'final weekly override protection')
CSS.write_text(css, encoding='utf-8')

print('Legacy RTM provenance + print pagination patch applied successfully.')
