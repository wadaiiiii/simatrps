from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'Missing marker: {label}')
    return text.replace(old, new, 1)

# -----------------------------------------------------------------------------
# Routes
# -----------------------------------------------------------------------------
routes_path = Path('routes/web.php')
routes = routes_path.read_text(encoding='utf-8')
routes = replace_once(
    routes,
    'use App\\Http\\Controllers\\RpsTaskController;\n',
    'use App\\Http\\Controllers\\RpsTaskController;\nuse App\\Http\\Controllers\\RpsValidatorDecisionController;\n',
    'validator controller import',
)
routes = replace_once(
    routes,
    "        Route::post('{rps}/validate-obe', [RpsAutomationController::class, 'validateObe'])->name('validate-obe');\n",
    "        Route::post('{rps}/validate-obe', [RpsAutomationController::class, 'validateObe'])->name('validate-obe');\n        Route::post('{rps}/validator-decisions', RpsValidatorDecisionController::class)->name('validator-decisions.store');\n",
    'validator decision route',
)
routes_path.write_text(routes, encoding='utf-8')

# -----------------------------------------------------------------------------
# Persistent lecturer decisions
# -----------------------------------------------------------------------------
controller = r'''<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpsValidatorDecisionController extends Controller
{
    public function __invoke(Request $request, string $rps): RedirectResponse
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);
        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')->where('id', $record->current_version_id)->first();
        abort_unless($version, 404);

        abort_unless(Schema::hasTable('rps_validator_decisions'), 503, 'Penyimpanan keputusan validator belum siap.');

        $validated = $request->validate([
            'check_key' => ['required', Rule::in(['assessment_semantics', 'rtm_semantics'])],
            'subject_key' => ['required', 'string', 'max:500'],
        ]);

        DB::table('rps_validator_decisions')->updateOrInsert(
            [
                'rps_version_id' => $version->id,
                'check_key' => $validated['check_key'],
                'subject_key' => $validated['subject_key'],
            ],
            [
                'id' => (string) Str::uuid(),
                'decision' => 'keep',
                'decided_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Keputusan dosen disimpan. Rekomendasi ini tidak lagi dianggap sebagai masalah.');
    }
}
'''
Path('app/Http/Controllers/RpsValidatorDecisionController.php').write_text(controller, encoding='utf-8')

migration = r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rps_validator_decisions')) {
            return;
        }

        Schema::create('rps_validator_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('check_key', 80);
            $table->string('subject_key', 500);
            $table->string('decision', 30)->default('keep');
            $table->uuid('decided_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['rps_version_id', 'check_key', 'subject_key'],
                'rps_validator_decision_unique'
            );
            $table->index(['rps_version_id', 'check_key']);
            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_validator_decisions');
    }
};
'''
Path('database/migrations/2026_08_17_130500_create_rps_validator_decisions.php').write_text(migration, encoding='utf-8')

# -----------------------------------------------------------------------------
# OBE progress / semantic validator messages
# -----------------------------------------------------------------------------
service_path = Path('app/Services/Rps/ObeWorkspaceService.php')
service = service_path.read_text(encoding='utf-8')

service = replace_once(
    service,
    "        $assessmentSnapshot = $assessmentSync->snapshot($versionId);\n        $assessmentBudgetMismatches = collect($assessmentSnapshot['assessment_budget_mismatches'] ?? []);\n",
    "        $assessmentSnapshot = $assessmentSync->snapshot($versionId);\n        $validatorDecisions = Schema::hasTable('rps_validator_decisions')\n            ? DB::table('rps_validator_decisions')\n                ->where('rps_version_id', $versionId)\n                ->where('decision', 'keep')\n                ->get(['check_key', 'subject_key'])\n            : collect();\n        $keptDecisionKeys = $validatorDecisions\n            ->pluck('subject_key')\n            ->mapWithKeys(fn ($key) => [(string) $key => true]);\n        $assessmentBudgetMismatches = collect($assessmentSnapshot['assessment_budget_mismatches'] ?? []);\n",
    'load validator decisions',
)

service = replace_once(
    service,
    "        $weightedTeachingWeekNumbers = $weightedTeachingWeeks\n            ->pluck('week_number')\n            ->map(fn ($week) => (int) $week)\n            ->values();\n",
    "        $weightedTeachingWeekNumbers = $weightedTeachingWeeks\n            ->pluck('week_number')\n            ->map(fn ($week) => (int) $week)\n            ->values();\n        $unweightedTeachingWeekNumbers = $teachingWeeks\n            ->filter(fn ($week) => (float) ($week->assessment_weight ?? 0) <= 0)\n            ->pluck('week_number')\n            ->map(fn ($week) => (int) $week)\n            ->values();\n",
    'unweighted teaching weeks',
)

service = replace_once(
    service,
    "        $assessmentSemanticIssues = collect();\n        foreach ($nonExamAssessments as $assessment) {\n",
    "        $assessmentSemanticIssues = collect();\n        $confirmedAssessmentSemanticCount = 0;\n        foreach ($nonExamAssessments as $assessment) {\n",
    'assessment confirmed counter',
)

service = replace_once(
    service,
    "                if ($explicitMismatch || $clearlyCloserElsewhere) {\n                    $bestSub = $subById->get($bestSubId);\n                    $assessmentSemanticIssues->push([\n",
    "                if ($explicitMismatch || $clearlyCloserElsewhere) {\n                    $bestSub = $subById->get($bestSubId);\n                    $decisionKey = 'assessment:'.(string) $assessment->id\n                        .':sub:'.(string) $linkedSub->id\n                        .':'.sha1($this->semanticNormalized($text).'|'.$this->semanticNormalized((string) $linkedSub->description));\n                    if ($keptDecisionKeys->has($decisionKey)) {\n                        $confirmedAssessmentSemanticCount++;\n                        continue;\n                    }\n                    $assessmentSemanticIssues->push([\n                        'decision_key' => $decisionKey,\n",
    'assessment decision key',
)

service = replace_once(
    service,
    "        $rtmSemanticIssues = collect();\n        foreach ($taskRows as $task) {\n",
    "        $rtmSemanticIssues = collect();\n        $confirmedRtmSemanticCount = 0;\n        foreach ($taskRows as $task) {\n",
    'rtm confirmed counter',
)

service = replace_once(
    service,
    "            if ($similarity < 0.34) {\n                $rtmSemanticIssues->push([\n",
    "            if ($similarity < 0.34) {\n                $decisionKey = 'rtm:'.(string) $task->id\n                    .':assessment:'.(string) $assessment->id\n                    .':'.sha1($this->semanticNormalized((string) $task->title).'|'.$this->semanticNormalized((string) $assessment->name));\n                if ($keptDecisionKeys->has($decisionKey)) {\n                    $confirmedRtmSemanticCount++;\n                    continue;\n                }\n                $rtmSemanticIssues->push([\n                    'decision_key' => $decisionKey,\n",
    'rtm decision key',
)

# Detailed ambiguity message after semantic checks, when task rows are already available.
service = replace_once(
    service,
    "        $weeklyMaterialSemanticsAligned = $weeklyMaterialIssues->isEmpty();\n\n        $cplMessage = $scopeCplCount === 0\n",
    "        $weeklyMaterialSemanticsAligned = $weeklyMaterialIssues->isEmpty();\n\n        $ambiguousEvidenceMessage = null;\n        if ($ambiguousWeightedWeeks->isNotEmpty()) {\n            $firstAmbiguous = $ambiguousWeightedWeeks->first();\n            $ambiguousWeek = (int) ($firstAmbiguous['week'] ?? 0);\n            $candidateTitles = collect($firstAmbiguous['candidates'] ?? [])\n                ->map(fn ($title) => trim((string) $title))\n                ->filter();\n            $candidateLabels = $taskRows\n                ->filter(fn ($task) => (int) ($task->due_week ?? 0) === $ambiguousWeek)\n                ->filter(fn ($task) => $candidateTitles->isEmpty() || $candidateTitles->contains(trim((string) $task->title)))\n                ->map(fn ($task) => trim((string) $task->code).' '.trim((string) $task->title))\n                ->filter()\n                ->unique()\n                ->values();\n            if ($candidateLabels->isEmpty()) {\n                $candidateLabels = $candidateTitles->values();\n            }\n            $ambiguousEvidenceMessage = 'Pekan '.$ambiguousWeek.' memiliki lebih dari satu bukti penilaian'\n                .($candidateLabels->isNotEmpty() ? ': '.$candidateLabels->implode(' dan ') : '')\n                .'.';\n        }\n\n        $cplMessage = $scopeCplCount === 0\n",
    'detailed ambiguous evidence message',
)

# Advisory severity markers.
service = replace_once(
    service,
    "                'key' => 'material_quality',\n                'label' => 'Kualitas Bahan Kajian',\n",
    "                'key' => 'material_quality',\n                'label' => 'Kualitas Bahan Kajian',\n                'severity' => 'advisory',\n",
    'material quality severity',
)
service = replace_once(
    service,
    "                'key' => 'weekly_material_semantics',\n                'label' => 'Kesesuaian Materi per Pekan',\n",
    "                'key' => 'weekly_material_semantics',\n                'label' => 'Kesesuaian Materi per Pekan',\n                'severity' => 'advisory',\n",
    'weekly semantic severity',
)
service = replace_once(
    service,
    "                'key' => 'assessment_semantics',\n                'label' => 'Kesesuaian Asesmen',\n",
    "                'key' => 'assessment_semantics',\n                'label' => 'Kesesuaian Asesmen',\n                'severity' => 'advisory',\n",
    'assessment semantic severity',
)
service = replace_once(
    service,
    "                'key' => 'rtm_semantics',\n                'label' => 'Kesesuaian RTM',\n",
    "                'key' => 'rtm_semantics',\n                'label' => 'Kesesuaian RTM',\n                'severity' => 'advisory',\n",
    'rtm semantic severity',
)

service = replace_once(
    service,
    "                'message' => $assessmentSemanticsAligned\n                    ? 'Rumusan asesmen selaras dengan Sub-CPMK.'\n                    : (($issue = $assessmentSemanticIssues->first())\n                        ? $issue['assessment_name'].': tag '.$issue['linked_sub_code'].' perlu ditelaah'.($issue['suggested_sub_code'] ? ' (lebih dekat ke '.$issue['suggested_sub_code'].').' : '.')\n                        : 'Ada tag asesmen yang perlu ditelaah.'),\n                'details' => [\n                    'issues' => $assessmentSemanticIssues->all(),\n                ],\n",
    "                'message' => $assessmentSemanticsAligned\n                    ? ($confirmedAssessmentSemanticCount > 0\n                        ? 'Rumusan asesmen diterima · '.$confirmedAssessmentSemanticCount.' keputusan dosen dipertahankan.'\n                        : 'Rumusan asesmen selaras dengan Sub-CPMK.')\n                    : (($issue = $assessmentSemanticIssues->first())\n                        ? $issue['assessment_name'].': sistem menyarankan meninjau tag '.$issue['linked_sub_code'].($issue['suggested_sub_code'] ? ' (lebih dekat ke '.$issue['suggested_sub_code'].').' : '.').' Dosen boleh mempertahankan tag.'\n                        : 'Ada tag asesmen yang disarankan untuk ditelaah.'),\n                'details' => [\n                    'issues' => $assessmentSemanticIssues->all(),\n                    'confirmed_count' => $confirmedAssessmentSemanticCount,\n                ],\n",
    'assessment semantic message',
)

# Chain message: weight completeness before evidence, detailed ambiguity.
old_chain = """                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : (! $assessmentBudgetAligned
                        ? $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.'
                        : ($ambiguousWeekNumbers->isNotEmpty()
                        ? 'Pekan '.$ambiguousWeekNumbers->implode(', ').' memiliki lebih dari satu bukti penilaian.'
                        : ($missingWeekNumbers->isNotEmpty()
                            ? 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'
                            : ($taskAlignment['missing_required_assessment_count'] > 0
                                ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                : 'Masih ada data penilaian yang belum konsisten.')))),
"""
new_chain = """                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : (! $assessmentBudgetAligned
                        ? $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.'
                        : ($weightedTeachingWeeks->count() < 14
                            ? $unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
                            : ($ambiguousWeekNumbers->isNotEmpty()
                                ? ($ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.')
                                : ($missingWeekNumbers->isNotEmpty()
                                    ? 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'
                                    : ($taskAlignment['missing_required_assessment_count'] > 0
                                        ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                        : 'Masih ada data penilaian yang belum konsisten.'))))),
"""
service = replace_once(service, old_chain, new_chain, 'assessment chain message')

old_evidence = """                'message' => $weeklyEvidenceAligned
                    ? '14/14 pekan memiliki satu bukti penilaian.'
                    : ($ambiguousWeekNumbers->isNotEmpty()
                        ? 'Pekan '.$ambiguousWeekNumbers->implode(', ').' memiliki lebih dari satu bukti.'
                        : 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'),
                'details' => [
                    'covered_weeks' => $coveredEvidenceWeeks->all(),
                    'missing_weeks' => $missingEvidenceWeeks->all(),
                    'ambiguous_weeks' => $ambiguousWeightedWeeks->all(),
                    'source_by_week' => $evidenceSourcesByWeek->all(),
                ],
"""
new_evidence = """                'message' => $weeklyEvidenceAligned
                    ? '14/14 pekan memiliki satu bukti penilaian.'
                    : ($weightedTeachingWeeks->count() < 14
                        ? 'Belum dapat diperiksa: '.$unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
                        : ($ambiguousWeekNumbers->isNotEmpty()
                            ? ($ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.')
                            : 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.')),
                'details' => [
                    'covered_weeks' => $coveredEvidenceWeeks->all(),
                    'missing_weeks' => $missingEvidenceWeeks->all(),
                    'ambiguous_weeks' => $ambiguousWeightedWeeks->all(),
                    'source_by_week' => $evidenceSourcesByWeek->all(),
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'unweighted_weeks' => $unweightedTeachingWeekNumbers->all(),
                ],
"""
service = replace_once(service, old_evidence, new_evidence, 'weekly evidence message')

old_rtm = """                'message' => $rtmSemanticsAligned
                    ? 'Judul RTM selaras dengan asesmen induk.'
                    : (($issue = $rtmSemanticIssues->first())
                        ? $issue['task_code'].' tidak selaras dengan asesmen induknya.'
                        : 'Ada RTM yang perlu ditelaah.'),
                'details' => [
                    'issues' => $rtmSemanticIssues->all(),
                ],
"""
new_rtm = """                'message' => $rtmSemanticsAligned
                    ? ($confirmedRtmSemanticCount > 0
                        ? 'Hubungan RTM diterima · '.$confirmedRtmSemanticCount.' keputusan dosen dipertahankan.'
                        : 'Judul RTM selaras dengan asesmen induk.')
                    : (($issue = $rtmSemanticIssues->first())
                        ? $issue['task_code'].' “'.$issue['task_title'].'” terhubung ke asesmen “'.$issue['assessment_name'].'”. Periksa asesmen terkait atau pertahankan hubungan jika memang disengaja.'
                        : 'Ada hubungan RTM dan asesmen yang disarankan untuk ditelaah.'),
                'details' => [
                    'issues' => $rtmSemanticIssues->all(),
                    'confirmed_count' => $confirmedRtmSemanticCount,
                ],
"""
service = replace_once(service, old_rtm, new_rtm, 'rtm semantic message')

# Advisory checks no longer reduce OBE validity/percentage.
service = replace_once(
    service,
    "        $done = collect($checks)->where('done', true)->count();\n        $percent = (int) round(($done / count($checks)) * 100);\n\n        return [\n            'checks' => $checks,\n            'percent' => $percent,\n            'is_valid' => $done === count($checks),\n",
    "        $blockingChecks = collect($checks)->reject(fn ($check) => ($check['severity'] ?? 'required') === 'advisory');\n        $done = $blockingChecks->where('done', true)->count();\n        $percent = $blockingChecks->isEmpty()\n            ? 100\n            : (int) round(($done / $blockingChecks->count()) * 100);\n\n        return [\n            'checks' => $checks,\n            'percent' => $percent,\n            'is_valid' => $done === $blockingChecks->count(),\n",
    'advisory progress calculation',
)
service_path.write_text(service, encoding='utf-8')

# -----------------------------------------------------------------------------
# UI: explicit fix location + lecturer keep decision + reliable RTM deletion
# -----------------------------------------------------------------------------
show_path = Path('resources/js/pages/rps/show.tsx')
show = show_path.read_text(encoding='utf-8')

show = replace_once(
    show,
    "function validatorFixLabel(check: any) {\n    const meta = VALIDATOR_FIX_META[check?.key];\n    if (!meta) return 'Perbaiki';\n\n    const week = validatorProblemWeek(check);\n",
    "function validatorFixLabel(check: any) {\n    const meta = VALIDATOR_FIX_META[check?.key];\n    if (!meta) return 'Perbaiki';\n\n    if (check?.key === 'weekly_assessment_evidence' && Number(check?.details?.weighted_teaching_weeks ?? 14) < 14) {\n        return 'Perbaiki Bobot';\n    }\n\n    if (check?.key === 'rtm_semantics') {\n        const issue = safeList(check?.details?.issues)[0];\n        if (issue?.task_code) return `Edit ${issue.task_code}`;\n    }\n\n    const week = validatorProblemWeek(check);\n",
    'dynamic validator fix label',
)

show = replace_once(
    show,
    "function goToValidatorFix(check: any) {\n    const meta = VALIDATOR_FIX_META[check?.key];\n    if (!meta) return;\n\n    const week = validatorProblemWeek(check);\n    let targets: HTMLElement[] = [];\n\n    if (week && meta.target === 'validator-target-rtm') {\n",
    "function goToValidatorFix(check: any) {\n    const meta = VALIDATOR_FIX_META[check?.key];\n    if (!meta) return;\n\n    const week = validatorProblemWeek(check);\n    let targets: HTMLElement[] = [];\n    let targetId = meta.target;\n\n    if (check?.key === 'weekly_assessment_evidence' && Number(check?.details?.weighted_teaching_weeks ?? 14) < 14) {\n        targetId = 'validator-target-assessment';\n    }\n\n    const semanticIssue = safeList(check?.details?.issues)[0];\n    if (check?.key === 'rtm_semantics' && semanticIssue?.task_id) {\n        targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-rtm-id=\"${semanticIssue.task_id}\"]`));\n    } else if (check?.key === 'assessment_semantics' && semanticIssue?.assessment_id) {\n        targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-assessment-id=\"${semanticIssue.assessment_id}\"]`));\n    }\n\n    if (targets.length === 0 && week && targetId === 'validator-target-rtm') {\n",
    'exact semantic fix target',
)
show = show.replace("        const section = document.getElementById(meta.target);", "        const section = document.getElementById(targetId);", 1)

# Assessment card data attribute.
show = replace_once(
    show,
    "            className=\"rounded-xl border border-slate-100 bg-white/60 p-4\"\n        >\n",
    "            data-assessment-id={assessment.id}\n            className=\"rounded-xl border border-slate-100 bg-white/60 p-4\"\n        >\n",
    'assessment data id',
)

# RTM card exact id, both view and edit forms.
show = replace_once(
    show,
    "                data-rtm-week={task.due_week ?? ''}\n                className=\"rounded-xl border border-slate-100 bg-white/60 p-4 transition-shadow\"\n",
    "                data-rtm-week={task.due_week ?? ''}\n                data-rtm-id={task.id}\n                className=\"rounded-xl border border-slate-100 bg-white/60 p-4 transition-shadow\"\n",
    'rtm view data id',
)
show = replace_once(
    show,
    "            className=\"rounded-xl border border-teal-200 bg-teal-50/40 p-4 md:col-span-2\"\n        >\n",
    "            data-rtm-week={form.data.due_week || task.due_week || ''}\n            data-rtm-id={task.id}\n            className=\"rounded-xl border border-teal-200 bg-teal-50/40 p-4 md:col-span-2\"\n        >\n",
    'rtm edit data id',
)

# Reliable deletion feedback and refresh.
old_delete = """                                if (confirm(`Hapus ${task.code} - ${task.title}?`)) {
                                    router.delete(`/rps/${rpsId}/tasks/${task.id}`, actionOptions('RTM berhasil dihapus.'));
                                }
"""
new_delete = """                                if (confirm(`Hapus ${task.code} - ${task.title}?`)) {
                                    router.delete(`/rps/${rpsId}/tasks/${task.id}`, {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            notify('success', `${task.code} berhasil dihapus.`);
                                            router.reload({
                                                only: ['tasks', 'progress', 'weeks'],
                                                preserveScroll: true,
                                                preserveState: true,
                                            });
                                        },
                                        onError: (errors: Record<string, any>) => {
                                            notify('error', `RTM tidak dihapus. ${firstError(errors)}`);
                                        },
                                    });
                                }
"""
show = replace_once(show, old_delete, new_delete, 'reliable RTM delete')

# Validator card: advisory badge + keep lecturer decision.
old_title = """                                            <div className=\"font-bold text-slate-800\">{check.label}</div>
                                        </div>
                                        <p className=\"mt-2 text-xs leading-5 text-slate-600\">{check.message}</p>
                                        {!check.done && VALIDATOR_FIX_META[check.key] && (
                                            <button
                                                type=\"button\"
                                                onClick={() => goToValidatorFix(check)}
                                                className=\"mt-3 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-[10px] font-bold text-amber-800 shadow-sm hover:bg-amber-100\"
                                            >
                                                <Pencil className=\"size-3\" />
                                                {validatorFixLabel(check)}
                                            </button>
                                        )}
"""
new_title = """                                            <div className=\"font-bold text-slate-800\">{check.label}</div>
                                            {check.severity === 'advisory' && !check.done && (
                                                <span className=\"rounded-full border border-amber-200 bg-white px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-amber-700\">Rekomendasi</span>
                                            )}
                                        </div>
                                        <p className=\"mt-2 text-xs leading-5 text-slate-600\">{check.message}</p>
                                        {!check.done && (
                                            <div className=\"mt-3 flex flex-wrap gap-2\">
                                                {VALIDATOR_FIX_META[check.key] && (
                                                    <button
                                                        type=\"button\"
                                                        onClick={() => goToValidatorFix(check)}
                                                        className=\"inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-[10px] font-bold text-amber-800 shadow-sm hover:bg-amber-100\"
                                                    >
                                                        <Pencil className=\"size-3\" />
                                                        {validatorFixLabel(check)}
                                                    </button>
                                                )}
                                                {['assessment_semantics', 'rtm_semantics'].includes(check.key) && safeList(check?.details?.issues)[0]?.decision_key && (
                                                    <button
                                                        type=\"button\"
                                                        onClick={() => {
                                                            const issue = safeList(check?.details?.issues)[0];
                                                            router.post(
                                                                `/rps/${rps.id}/validator-decisions`,
                                                                { check_key: check.key, subject_key: issue.decision_key },
                                                                actionOptions(
                                                                    check.key === 'assessment_semantics'
                                                                        ? 'Tag Sub-CPMK dipertahankan sebagai keputusan dosen.'
                                                                        : 'Hubungan RTM dipertahankan sebagai keputusan dosen.',
                                                                ),
                                                            );
                                                        }}
                                                        className=\"inline-flex items-center gap-1.5 rounded-lg border border-teal-300 bg-teal-50 px-2.5 py-1.5 text-[10px] font-bold text-teal-800 shadow-sm hover:bg-teal-100\"
                                                    >
                                                        <CheckCircle2 className=\"size-3\" />
                                                        {check.key === 'assessment_semantics' ? 'Pertahankan Tag' : 'Pertahankan Hubungan'}
                                                    </button>
                                                )}
                                            </div>
                                        )}
"""
show = replace_once(show, old_title, new_title, 'validator advisory actions')

show_path.write_text(show, encoding='utf-8')

# -----------------------------------------------------------------------------
# Sanity checks
# -----------------------------------------------------------------------------
checks = {
    'routes/web.php': ['validator-decisions', 'RpsValidatorDecisionController'],
    'app/Services/Rps/ObeWorkspaceService.php': [
        "'severity' => 'advisory'",
        'decision_key',
        'Belum dapat diperiksa:',
        'ambiguousEvidenceMessage',
        'blockingChecks',
    ],
    'resources/js/pages/rps/show.tsx': [
        'Pertahankan Tag',
        'Pertahankan Hubungan',
        'data-rtm-id',
        'RTM tidak dihapus.',
        "targetId = 'validator-target-assessment'",
    ],
}
for filename, markers in checks.items():
    content = Path(filename).read_text(encoding='utf-8')
    for marker in markers:
        if marker not in content:
            raise SystemExit(f'Missing final marker {marker!r} in {filename}')

print('Validator decision UX patch applied')
