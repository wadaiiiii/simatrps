from pathlib import Path

SERVICE = Path('app/Services/Rps/RpsAssessmentSyncService.php')
CONTROLLER = Path('app/Http/Controllers/RpsController.php')
ASSESSMENT = Path('app/Http/Controllers/RpsAssessmentController.php')
SHOW = Path('resources/js/pages/rps/show.tsx')
CSS = Path('resources/css/app.css')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly 1 match, found {count}')
    return text.replace(old, new, 1)


# ---------------------------------------------------------------------------
# 1. Assessment/RTM provenance and conservative legacy repair
# ---------------------------------------------------------------------------
service = SERVICE.read_text(encoding='utf-8')

service = replace_once(
    service,
    """        if ($sourceType === 'assessment_sync') return true;\n        if ($sourceType === 'manual') return false;\n        if ($sourceType !== '' && $sourceType !== 'legacy') return false;\n""",
    """        if (in_array($sourceType, ['assessment_sync', 'ai_accepted', 'ai_generated', 'automation'], true)) return true;\n        if ($sourceType === 'manual') return false;\n        if ($sourceType !== '' && $sourceType !== 'legacy') return false;\n""",
    'generated RTM source types',
)

scope_helper = r'''    private function syncGeneratedAssessmentScopes(string $versionId): int
    {
        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code']);

        if ($subs->isEmpty()) return 0;

        $subIdByNumber = [];
        foreach ($subs as $sub) {
            if (preg_match('/(\d+)$/', (string) $sub->code, $match) === 1) {
                $subIdByNumber[(int) $match[1]] = (string) $sub->id;
            }
        }

        if ($subIdByNumber === []) return 0;

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('source_type', ['ai_accepted', 'ai_generated', 'automation'])
            ->whereNotIn('type', ['uts', 'uas'])
            ->get(['id', 'name', 'description']);

        $fixed = 0;

        DB::transaction(function () use ($assessments, $subIdByNumber, &$fixed): void {
            foreach ($assessments as $assessment) {
                $text = trim((string) ($assessment->name ?? '').' '.(string) ($assessment->description ?? ''));
                preg_match_all('/Sub[\s\-‐‑‒–—]*CPMK[\s\-‐‑‒–—]*(\d+)/iu', $text, $matches);

                $numbers = collect($matches[1] ?? [])
                    ->map(fn ($value) => (int) $value)
                    ->filter(fn ($number) => isset($subIdByNumber[$number]))
                    ->unique()
                    ->values();

                // Hanya perbaiki asesmen AI bila teksnya menyebut tepat satu
                // Sub-CPMK secara eksplisit. Ini konservatif dan tidak menebak
                // cakupan asesmen hanya dari judul/topik.
                if ($numbers->count() !== 1) continue;

                $targetId = $subIdByNumber[(int) $numbers->first()];
                $current = DB::table('assessment_subcpmks')
                    ->where('assessment_id', $assessment->id)
                    ->pluck('rps_sub_cpmk_id')
                    ->map('strval')
                    ->unique()
                    ->values();

                if ($current->count() === 1 && $current->first() === $targetId) continue;

                DB::table('assessment_subcpmks')
                    ->where('assessment_id', $assessment->id)
                    ->delete();

                DB::table('assessment_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'assessment_id' => $assessment->id,
                    'rps_sub_cpmk_id' => $targetId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $fixed++;
            }
        });

        return $fixed;
    }

    public function repairGeneratedArtifacts(string $versionId): array
    {
        $scopeFixes = $this->syncGeneratedAssessmentScopes($versionId);
        $linkedTasks = $this->syncTaskMappings($versionId);

        return [
            'assessment_scope_fixes' => $scopeFixes,
            'linked_generated_tasks' => $linkedTasks,
        ];
    }

'''

service = replace_once(
    service,
    "    private function isGeneratedTask(object $task): bool\n    {",
    scope_helper + "    private function isGeneratedTask(object $task): bool\n    {",
    'generated assessment scope helper insertion',
)

service = replace_once(
    service,
    """        $indicatorFixes = $this->syncWeeklyIndicators($versionId);\n        $narrativeFixes = $this->syncWeeklySubCpmkNarratives($versionId);\n\n        // Petakan RTM lama terlebih dahulu agar asesmen yang sebenarnya sudah\n""",
    """        $indicatorFixes = $this->syncWeeklyIndicators($versionId);\n        $narrativeFixes = $this->syncWeeklySubCpmkNarratives($versionId);\n        $assessmentScopeFixes = $this->syncGeneratedAssessmentScopes($versionId);\n\n        // Petakan RTM lama terlebih dahulu agar asesmen yang sebenarnya sudah\n""",
    'sync generated assessment scope before RTM mapping',
)

service = replace_once(
    service,
    'RTM wajib dibuat otomatis dari Detail Asesmen; {$indicatorFixes} indikator dan {$narrativeFixes} narasi pekan yang salah Sub-CPMK diperbaiki.',
    'RTM wajib dibuat otomatis dari Detail Asesmen; {$assessmentScopeFixes} tag asesmen AI yang eksplisit diperbaiki; {$indicatorFixes} indikator dan {$narrativeFixes} narasi pekan yang salah Sub-CPMK diperbaiki.',
    'sync message assessment scope count',
)

SERVICE.write_text(service, encoding='utf-8')


# ---------------------------------------------------------------------------
# 2. Read path performs only SAFE generated-artifact repair: no task creation.
#    This makes stale AI RTM disappear after refresh without resurrecting RTM
#    intentionally deleted by the lecturer.
# ---------------------------------------------------------------------------
controller = CONTROLLER.read_text(encoding='utf-8')

controller = replace_once(
    controller,
    """        abort_unless($version, 404);\n\n        $allCpls = DB::table('cpls')\n""",
    """        abort_unless($version, 404);\n\n        // Safe, idempotent repair only: normalize explicit AI assessment scope\n        // and remove/remap stale generated RTM. It never creates a replacement\n        // RTM while the page is merely being opened.\n        $assessmentSync->repairGeneratedArtifacts($version->id);\n\n        $allCpls = DB::table('cpls')\n""",
    'safe generated artifact repair on show',
)

controller = replace_once(
    controller,
    """        if ($sourceType === 'assessment_sync') return true;\n        if ($sourceType === 'manual') return false;\n        if ($sourceType !== '' && $sourceType !== 'legacy') return false;\n""",
    """        if (in_array($sourceType, ['assessment_sync', 'ai_accepted', 'ai_generated', 'automation'], true)) return true;\n        if ($sourceType === 'manual') return false;\n        if ($sourceType !== '' && $sourceType !== 'legacy') return false;\n""",
    'controller generated RTM source types',
)

CONTROLLER.write_text(controller, encoding='utf-8')


# ---------------------------------------------------------------------------
# 3. Any lecturer edit becomes manual provenance from now on.
# ---------------------------------------------------------------------------
assessment = ASSESSMENT.read_text(encoding='utf-8')

assessment = replace_once(
    assessment,
    """                'weight' => $validated['weight'] === null || $validated['weight'] === ''\n                    ? null\n                    : $validated['weight'],\n                'updated_at' => now(),\n""",
    """                'weight' => $validated['weight'] === null || $validated['weight'] === ''\n                    ? null\n                    : $validated['weight'],\n                'source_type' => 'manual',\n                'updated_at' => now(),\n""",
    'full assessment edit provenance',
)

assessment = replace_once(
    assessment,
    """        $updates = [];\n\n        if (array_key_exists('name', $validated) && filled($validated['name'])) {\n""",
    """        // Perubahan dari tabel matriks adalah keputusan dosen dan harus\n        // dilindungi dari normalisasi otomatis AI berikutnya.\n        $updates = ['source_type' => 'manual'];\n\n        if (array_key_exists('name', $validated) && filled($validated['name'])) {\n""",
    'matrix assessment edit provenance',
)

ASSESSMENT.write_text(assessment, encoding='utf-8')


# ---------------------------------------------------------------------------
# 4. Print layout: exact two-line gap after prerequisite, visible institution
#    hierarchy, RTM sheets with no forced blank trailing page.
# ---------------------------------------------------------------------------
show = SHOW.read_text(encoding='utf-8')

show = replace_once(
    show,
    '<table className="min-w-[1080px] w-full border-collapse text-[11px] leading-[1.45] text-slate-800">',
    '<table className="rps-print-main-table min-w-[1080px] w-full border-collapse text-[11px] leading-[1.45] text-slate-800">',
    'main print table class',
)

show = replace_once(
    show,
    '<div className="grid min-h-[108px] grid-cols-[110px_1fr_110px] items-center px-4 py-3">',
    '<div className="rps-print-institution-grid grid min-h-[108px] grid-cols-[110px_1fr_110px] items-center px-4 py-3">',
    'main institution grid class',
)

show = replace_once(
    show,
    '<div aria-hidden="true" className="w-full shrink-0" style={{ height: \'32px\' }} />',
    '<div aria-hidden="true" className="rps-print-week-gap w-full shrink-0" style={{ height: \'20px\' }} />',
    'two-line weekly table gap',
)

show = replace_once(
    show,
    '<div className="border-x border-b border-slate-300 bg-white px-3 pb-5">',
    '<div className="rps-print-rtm border-x border-b border-slate-300 bg-white px-3 pb-5">',
    'RTM print section class',
)

show = replace_once(
    show,
    'className="break-inside-avoid overflow-hidden rounded-lg border border-slate-300 bg-white"',
    'className="rps-print-rtm-sheet break-inside-avoid overflow-hidden rounded-lg border border-slate-300 bg-white"',
    'RTM sheet class',
)

show = replace_once(
    show,
    '<table className="w-full border-collapse font-sans text-[11px] leading-[1.45]">',
    '<table className="rps-print-rtm-table w-full border-collapse font-sans text-[11px] leading-[1.45]">',
    'RTM table class',
)

show = replace_once(
    show,
    '<div className="grid min-h-[92px] grid-cols-[95px_1fr_95px] items-center px-3 py-2">',
    '<div className="rps-print-institution-grid grid min-h-[92px] grid-cols-[95px_1fr_95px] items-center px-3 py-2">',
    'RTM institution grid class',
)

if show.count('JURUSAN MATEMATIKA') < 2:
    raise SystemExit('JURUSAN MATEMATIKA must exist in both RPS and RTM headers')

SHOW.write_text(show, encoding='utf-8')


# ---------------------------------------------------------------------------
# 5. Print CSS final overrides.
# ---------------------------------------------------------------------------
css = CSS.read_text(encoding='utf-8-sig')

css_patch = r'''

/* Patch: RPS generated-artifact cleanup + compact two-line weekly gap */
@media print {
    html.rps-print-mode .rps-print-week-gap {
        display: block !important;
        height: 20px !important;
        min-height: 20px !important;
        max-height: 20px !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    html.rps-print-mode .rps-print-institution-grid > div:nth-child(2) > div {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    html.rps-print-mode .rps-print-rtm {
        page: rpsPortrait;
        break-before: page !important;
        page-break-before: always !important;
        break-after: auto !important;
        page-break-after: auto !important;
        padding-bottom: 0 !important;
    }

    html.rps-print-mode .rps-print-rtm-sheet {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        break-after: auto !important;
        page-break-after: auto !important;
    }

    html.rps-print-mode .rps-print-rtm-sheet + .rps-print-rtm-sheet {
        break-before: page !important;
        page-break-before: always !important;
    }

    html.rps-print-mode .rps-print-rtm-sheet:last-child,
    html.rps-print-mode .rps-print-rtm-sheet:last-child .rps-print-rtm-table {
        break-after: auto !important;
        page-break-after: auto !important;
        margin-bottom: 0 !important;
    }
}
'''

if 'Patch: RPS generated-artifact cleanup + compact two-line weekly gap' not in css:
    css = css.rstrip() + css_patch + '\n'

CSS.write_text(css, encoding='utf-8')

print('Patch RPS sync + spacing v2 applied successfully.')
