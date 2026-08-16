from pathlib import Path
import re

root = Path('.')

# 1) Central synchronization service
service_path = root / 'app/Services/Rps/RpsAssessmentSyncService.php'
service_path.write_text(r'''<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RpsAssessmentSyncService
{
    private const TEACHING_WEEKS = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

    public function snapshot(string $versionId): array
    {
        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->orderBy('week_number')
            ->get(['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight']);

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'weight']);

        $assessmentIds = $assessments->pluck('id')->all();
        $links = $assessmentIds === []
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy('assessment_id');

        $weeksBySub = $weeks
            ->filter(fn ($week) =>
                in_array((int) $week->week_number, self::TEACHING_WEEKS, true)
                && filled($week->rps_sub_cpmk_id ?? null)
            )
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id);

        $expectedCents = array_fill_keys(range(1, 16), 0);
        $namesByWeek = array_fill_keys(range(1, 16), []);
        $aggregateSubCents = [];
        $unmappedAssessments = [];
        $orphanSubLinks = [];

        foreach ($assessments as $assessment) {
            $type = strtolower((string) ($assessment->type ?? ''));
            $weightCents = (int) round(max(0, (float) ($assessment->weight ?? 0)) * 100);
            $linkedSubIds = collect($links->get($assessment->id, []))
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if (in_array($type, ['uts', 'uas'], true)) {
                $weekNumber = $type === 'uts' ? 8 : 16;
                $expectedCents[$weekNumber] += $weightCents;
                if ($weightCents > 0) {
                    $namesByWeek[$weekNumber][] = (string) $assessment->name;
                }
                continue;
            }

            if ($weightCents > 0 && $linkedSubIds->isEmpty()) {
                $unmappedAssessments[] = [
                    'id' => (string) $assessment->id,
                    'code' => (string) $assessment->code,
                    'name' => (string) $assessment->name,
                ];
                continue;
            }

            if ($linkedSubIds->isEmpty()) {
                continue;
            }

            $baseSub = intdiv($weightCents, $linkedSubIds->count());
            $subRemainder = $weightCents % $linkedSubIds->count();

            foreach ($linkedSubIds as $subIndex => $subId) {
                $subShare = $baseSub + ($subIndex < $subRemainder ? 1 : 0);
                $aggregateSubCents[$subId] = ($aggregateSubCents[$subId] ?? 0) + $subShare;

                $targetWeeks = collect($weeksBySub->get($subId, []))
                    ->sortBy('week_number')
                    ->values();

                if ($targetWeeks->isEmpty()) {
                    if ($subShare > 0) {
                        $orphanSubLinks[] = [
                            'assessment_id' => (string) $assessment->id,
                            'assessment_name' => (string) $assessment->name,
                            'rps_sub_cpmk_id' => $subId,
                        ];
                    }
                    continue;
                }

                $baseWeek = intdiv($subShare, $targetWeeks->count());
                $weekRemainder = $subShare % $targetWeeks->count();

                foreach ($targetWeeks as $weekIndex => $week) {
                    $weekNumber = (int) $week->week_number;
                    $expectedCents[$weekNumber] += $baseWeek + ($weekIndex < $weekRemainder ? 1 : 0);
                    if ($subShare > 0) {
                        $namesByWeek[$weekNumber][] = (string) $assessment->name;
                    }
                }
            }
        }

        $actualSubBudgets = $weeks
            ->filter(fn ($week) =>
                in_array((int) $week->week_number, self::TEACHING_WEEKS, true)
                && filled($week->rps_sub_cpmk_id ?? null)
            )
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)
            ->map(fn ($items) => round((float) $items->sum(
                fn ($week) => (float) ($week->assessment_weight ?? 0)
            ), 2))
            ->all();

        return [
            'expected_weekly_weights' => collect($expectedCents)
                ->map(fn ($cents) => round($cents / 100, 2))
                ->all(),
            'assessment_names_by_week' => collect($namesByWeek)
                ->map(fn ($names) => collect($names)->filter()->unique()->values()->implode('; '))
                ->all(),
            'aggregate_sub_budgets' => collect($aggregateSubCents)
                ->map(fn ($cents) => round($cents / 100, 2))
                ->all(),
            'actual_sub_budgets' => $actualSubBudgets,
            'unmapped_assessments' => $unmappedAssessments,
            'orphan_sub_links' => $orphanSubLinks,
            'aggregate_total' => round((float) $assessments->sum(
                fn ($assessment) => (float) ($assessment->weight ?? 0)
            ), 2),
        ];
    }

    public function syncVersion(string $versionId): array
    {
        $this->syncTaskMappings($versionId);
        $snapshot = $this->snapshot($versionId);

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

        return [
            ...$refreshed,
            'message' => "Sinkronisasi asesmen diterapkan: {$weightedTeachingWeeks}/14 pekan pembelajaran memiliki bobot berdasarkan tag Sub-CPMK asesmen; RTM terkait mengikuti tag asesmennya.",
        ];
    }

    public function syncTaskMappings(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('assessment_id')
            ->get(['id', 'assessment_id']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $assessmentLinks = DB::table('assessment_subcpmks')
            ->whereIn('assessment_id', $tasks->pluck('assessment_id')->filter()->unique()->all())
            ->get(['assessment_id', 'rps_sub_cpmk_id'])
            ->groupBy('assessment_id');

        DB::transaction(function () use ($tasks, $assessmentLinks): void {
            foreach ($tasks as $task) {
                $subIds = collect($assessmentLinks->get($task->assessment_id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->unique()
                    ->values();

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
            }
        });

        return $tasks->count();
    }

    public function taskAlignment(string $versionId): array
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'assessment_id']);

        $linkedTasks = $tasks->filter(fn ($task) => filled($task->assessment_id ?? null));
        $assessmentIds = $linkedTasks->pluck('assessment_id')->filter()->unique()->values();
        $assessmentLinks = $assessmentIds->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds->all())
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy('assessment_id');

        $taskLinks = $tasks->isEmpty()
            ? collect()
            : DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $tasks->pluck('id')->all())
                ->get(['rps_task_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_task_id');

        $mismatchCount = 0;
        foreach ($linkedTasks as $task) {
            $expected = collect($assessmentLinks->get($task->assessment_id, []))
                ->pluck('rps_sub_cpmk_id')->map('strval')->unique()->sort()->values()->all();
            $actual = collect($taskLinks->get($task->id, []))
                ->pluck('rps_sub_cpmk_id')->map('strval')->unique()->sort()->values()->all();

            if ($expected !== $actual) {
                $mismatchCount++;
            }
        }

        $requiredAssessmentIds = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->pluck('id')
            ->map('strval')
            ->unique()
            ->values();

        $coveredAssessmentIds = $linkedTasks->pluck('assessment_id')
            ->filter()->map('strval')->unique()->values();

        $missingRequired = $requiredAssessmentIds->diff($coveredAssessmentIds)->values();

        return [
            'task_total' => $tasks->count(),
            'linked_task_total' => $linkedTasks->count(),
            'required_assessment_total' => $requiredAssessmentIds->count(),
            'missing_required_assessment_count' => $missingRequired->count(),
            'mapping_mismatch_count' => $mismatchCount,
            'is_aligned' => $missingRequired->isEmpty() && $mismatchCount === 0,
        ];
    }

    public function rebalanceTeachingWeek(string $versionId, int $weekNumber, float $newWeight): array
    {
        if (! in_array($weekNumber, self::TEACHING_WEEKS, true)) {
            throw ValidationException::withMessages([
                'weight' => 'Bobot UTS/UAS mengikuti asesmen sistem dan tidak diatur dari bobot pekan.',
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
                'weight' => 'Sub-CPMK pekan ini belum memiliki anggaran dari asesmen. Tag Sub-CPMK pada Detail Asesmen terlebih dahulu.',
            ]);
        }

        $group = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('rps_sub_cpmk_id', $subId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->orderBy('week_number')
            ->get(['id', 'week_number']);

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

        $siblings = $group->reject(fn ($item) => (int) $item->week_number === $weekNumber)->values();
        $remaining = $targetCents - $newCents;

        if ($siblings->isEmpty() && $remaining !== 0) {
            throw ValidationException::withMessages([
                'weight' => "Sub-CPMK ini hanya digunakan satu pekan sehingga bobotnya harus tepat {$target}%.",
            ]);
        }

        if ($siblings->isNotEmpty() && $remaining < $siblings->count()) {
            throw ValidationException::withMessages([
                'weight' => 'Bobot yang dipilih menyisakan kurang dari 0,01% untuk salah satu pekan Sub-CPMK yang sama.',
            ]);
        }

        DB::transaction(function () use ($week, $newCents, $siblings, $remaining): void {
            DB::table('rps_weekly_plans')->where('id', $week->id)->update([
                'assessment_weight' => round($newCents / 100, 2),
                'updated_at' => now(),
            ]);

            if ($siblings->isEmpty()) {
                return;
            }

            $base = intdiv($remaining, $siblings->count());
            $remainder = $remaining % $siblings->count();

            foreach ($siblings as $index => $sibling) {
                DB::table('rps_weekly_plans')->where('id', $sibling->id)->update([
                    'assessment_weight' => round(($base + ($index < $remainder ? 1 : 0)) / 100, 2),
                    'updated_at' => now(),
                ]);
            }
        });

        return [
            'sub_budget' => $target,
            'week_count' => $group->count(),
        ];
    }
}
''', encoding='utf-8')

# 2) Assessment controller: every change synchronizes weights and linked RTM
p = root / 'app/Http/Controllers/RpsAssessmentController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('namespace App\\Http\\Controllers;\n\n', 'namespace App\\Http\\Controllers;\n\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
s = s.replace('public function store(Request $request, string $rps): RedirectResponse', 'public function store(Request $request, string $rps, RpsAssessmentSyncService $sync): RedirectResponse')
s = s.replace("        return back()->with('success', 'Asesmen berhasil ditambahkan.');", "        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'Asesmen berhasil ditambahkan; tag Sub-CPMK, bobot pekan, RTM, matriks, dan simulasi tersinkron.');")
s = s.replace("        string $assessment\n    ): RedirectResponse {", "        string $assessment,\n        RpsAssessmentSyncService $sync\n    ): RedirectResponse {", 1)
s = s.replace("        return back()->with(\n            'success',\n            in_array($row->code, ['UTS', 'UAS'], true)", "        $sync->syncVersion($version->id);\n\n        return back()->with(\n            'success',\n            in_array($row->code, ['UTS', 'UAS'], true)", 1)
# updateMatrix signature is the next occurrence
marker = "    public function updateMatrix(\n        Request $request,\n        string $rps,\n        string $assessment\n    ): RedirectResponse {"
repl = "    public function updateMatrix(\n        Request $request,\n        string $rps,\n        string $assessment,\n        RpsAssessmentSyncService $sync\n    ): RedirectResponse {"
if marker not in s: raise SystemExit('updateMatrix signature marker missing')
s = s.replace(marker, repl, 1)
s = s.replace("        return back()->with(\n            'success',\n            array_key_exists('weight', $validated)\n                ? 'Bobot asesmen agregat diperbarui. Jalankan Isi Bagian Kosong bila distribusi bobot pekan perlu dilengkapi.'\n                : 'Pemetaan Sub-CPMK pada Tabel Penilaian dan Evaluasi CPL berhasil diperbarui.'\n        );", "        $sync->syncVersion($version->id);\n\n        return back()->with(\n            'success',\n            'Asesmen diperbarui; Detail Asesmen, tabel RPS, RTM, Tabel Penilaian, dan Simulasi langsung tersinkron.'\n        );", 1)
s = s.replace('public function destroy(Request $request, string $rps, string $assessment): RedirectResponse', 'public function destroy(Request $request, string $rps, string $assessment, RpsAssessmentSyncService $sync): RedirectResponse')
s = s.replace("        // Asesmen non-UTS/UAS adalah rekap/instrumen agregat dan tidak lagi\n        // menjadi sumber langsung bobot pekan. Karena UTS/UAS tidak dapat\n        // dihapus, penghapusan asesmen biasa tidak perlu menyentuh bobot pekan.\n\n        return back()->with('success', 'Asesmen dihapus. Distribusi bobot pekan tidak diubah.');", "        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'Asesmen dihapus dan distribusi bobot pekan serta RTM terkait disinkronkan ulang.');")
p.write_text(s, encoding='utf-8')

# 3) Task controller: linked RTM inherits assessment Sub-CPMK
p = root / 'app/Http/Controllers/RpsTaskController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('namespace App\\Http\\Controllers;\n\n', 'namespace App\\Http\\Controllers;\n\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
s = s.replace('public function store(Request $request, string $rps): RedirectResponse', 'public function store(Request $request, string $rps, RpsAssessmentSyncService $sync): RedirectResponse')
s = s.replace("        return back()->with('success', 'RTM berhasil ditambahkan.');", "        $sync->syncTaskMappings($version->id);\n\n        return back()->with('success', 'RTM berhasil ditambahkan. Jika terhubung ke asesmen, tag Sub-CPMK otomatis mengikuti asesmen.');")
marker = "    public function update(Request $request, string $rps, string $task): RedirectResponse"
if marker not in s: raise SystemExit('task update signature missing')
s = s.replace(marker, "    public function update(Request $request, string $rps, string $task, RpsAssessmentSyncService $sync): RedirectResponse", 1)
s = s.replace("        return back()->with('success', 'RTM berhasil diperbarui.');", "        $sync->syncTaskMappings($version->id);\n\n        return back()->with('success', 'RTM berhasil diperbarui dan tag Sub-CPMK disinkronkan dengan asesmen terkait.');")
p.write_text(s, encoding='utf-8')

# 4) RpsController: simulation names derive from the same assessment↔Sub-CPMK mapping
p = root / 'app/Http/Controllers/RpsController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('use App\\Services\\Rps\\RpsDraftService;\n', 'use App\\Services\\Rps\\RpsDraftService;\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
s = s.replace('        ObeWorkspaceService $workspace,\n        AiRpsProviderService $aiProvider\n    ): Response {', '        ObeWorkspaceService $workspace,\n        AiRpsProviderService $aiProvider,\n        RpsAssessmentSyncService $assessmentSync\n    ): Response {')
old = re.compile(r"        \$assessmentNamesByWeek = \$assessments\n            ->filter\(fn \(\$assessment\) => filled\(\$assessment->week_number\)\)\n            ->groupBy\(fn \(\$assessment\) => \(int\) \$assessment->week_number\)\n            ->map\(fn \(\$items\) => \$items->pluck\('name'\)->filter\(\)->implode\('; '\)\);", re.M)
if not old.search(s): raise SystemExit('assessmentNamesByWeek block missing')
s = old.sub("        $assessmentSyncSnapshot = $assessmentSync->snapshot($version->id);\n        $assessmentNamesByWeek = collect(\n            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []\n        );", s, count=1)
p.write_text(s, encoding='utf-8')

# 5) Smart draft: weight completion is now canonical synchronization
p = root / 'app/Services/Rps/RpsSmartDraftService.php'
s = p.read_text(encoding='utf-8')
pattern = re.compile(r"    private function fillEmptyTeachingWeights\(string \$versionId\): string\n    \{.*?\n    \}\n\n    private function ensureExamAssessments", re.S)
if not pattern.search(s): raise SystemExit('fillEmptyTeachingWeights method missing')
replacement = '''    private function fillEmptyTeachingWeights(string $versionId): string
    {
        $result = app(RpsAssessmentSyncService::class)->syncVersion($versionId);

        return (string) ($result['message']
            ?? 'Bobot pekan disinkronkan dari asesmen dan tag Sub-CPMK.');
    }

    private function ensureExamAssessments'''
s = pattern.sub(replacement, s, count=1)
p.write_text(s, encoding='utf-8')

# 6) Manual weekly weight editing keeps the Sub-CPMK aggregate budget synchronized
p = root / 'app/Http/Controllers/RpsDocumentController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('namespace App\\Http\\Controllers;\n\n', 'namespace App\\Http\\Controllers;\n\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
pattern = re.compile(r"    public function updateWeekWeight\(\n        Request \$request,\n        string \$rps,\n        int \$week\n    \): RedirectResponse \{.*?\n    \}\n\n    public function updateSimulationScore", re.S)
if not pattern.search(s): raise SystemExit('updateWeekWeight method missing')
replacement = '''    public function updateWeekWeight(
        Request $request,
        string $rps,
        int $week,
        RpsAssessmentSyncService $sync
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        abort_unless($week >= 1 && $week <= 16, 422);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $newWeight = round((float) $data['weight'], 2);
        $result = $sync->rebalanceTeachingWeek(
            $version->id,
            $week,
            $newWeight
        );

        return back()->with(
            'success',
            "Bobot pengukuran minggu {$week} disimpan {$newWeight}%. "
                ."Pekan lain pada Sub-CPMK yang sama otomatis diseimbangkan "
                ."agar total tetap {$result['sub_budget']}%."
        );
    }

    public function updateSimulationScore'''
s = pattern.sub(replacement, s, count=1)
p.write_text(s, encoding='utf-8')

# 7) AI assessment/RTM application synchronizes immediately and must not auto-attach unrelated Sub-CPMK to RTM
p = root / 'app/Http/Controllers/RpsAiController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('namespace App\\Http\\Controllers;\n\n', 'namespace App\\Http\\Controllers;\n\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
old = '''        $autoCoveredTaskSubs = 0;

        if ($changedTasks > 0) {
            $autoCoveredTaskSubs = $this->ensureAllSubCpmksCoveredByTasks(
                $version->id
            );
        }
'''
if old not in s: raise SystemExit('AI auto RTM coverage block missing')
s = s.replace(old, "        // RTM yang terhubung ke asesmen harus mengikuti cakupan asesmennya;\n        // jangan menambahkan Sub-CPMK lain hanya demi mengejar cakupan global.\n        $autoCoveredTaskSubs = 0;\n", 1)
needle = '''        $totalWeight = round(
            (float) DB::table('assessments')'''
if needle not in s: raise SystemExit('AI total weight marker missing')
s = s.replace(needle, "        app(RpsAssessmentSyncService::class)->syncVersion($version->id);\n\n" + needle, 1)
s = s.replace("            $message .= ' Total bobot asesmen agregat 100%. Gunakan Isi Bagian Kosong untuk membagi anggaran non-UTS/UAS ke pekan yang belum memiliki bobot.';", "            $message .= ' Total bobot asesmen agregat 100%. Distribusi bobot pekan, RTM, matriks, dan simulasi langsung disinkronkan.';")
p.write_text(s, encoding='utf-8')

# 8) Validator: add end-to-end assessment chain check and make RTM validation relational, not global coverage
p = root / 'app/Services/Rps/ObeWorkspaceService.php'
s = p.read_text(encoding='utf-8')
old = '''        $taskAssessments = $assessments
            ->whereIn('type', ['assignment', 'project', 'practicum']);

        $taskRows = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id']);

        $tasks = $taskRows->count();

        $taskCoveredSubCount = ($tasks > 0 && $subCpmkIds->isNotEmpty())
            ? DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $taskRows->pluck('id'))
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('rps_sub_cpmk_id')
            : 0;
'''
if old not in s: raise SystemExit('validator old task block missing')
new = '''        $taskAssessments = $assessments
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation']);

        $assessmentSync = app(RpsAssessmentSyncService::class);
        $taskAlignment = $assessmentSync->taskAlignment($versionId);
        $tasks = (int) $taskAlignment['task_total'];

        $positiveNonExamAssessments = $nonExamAssessments->filter(
            fn ($assessment) => (float) ($assessment->weight ?? 0) > 0
        );
        $positiveNonExamMappedCount = $positiveNonExamAssessments->filter(
            fn ($assessment) => DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->exists()
        )->count();
        $allPositiveNonExamMapped = $positiveNonExamAssessments->isNotEmpty()
            && $positiveNonExamMappedCount === $positiveNonExamAssessments->count();
        $assessmentChainAligned = $allPositiveNonExamMapped
            && $weightedTeachingWeeks->count() === 14
            && $weightedWeeklySubCount === $subCpmks->count()
            && $subBudgetAligned
            && (bool) $taskAlignment['is_aligned'];
'''
s = s.replace(old, new, 1)
old_check = '''            [
                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty()
                    || (
                        $tasks > 0
                        && $subCpmks->isNotEmpty()
                        && $taskCoveredSubCount === $subCpmks->count()
                    ),
                'message' => $taskAssessments->isEmpty()
                    ? 'Belum ada asesmen tugas/proyek yang mewajibkan RTM.'
                    : "{$tasks} RTM tersedia; {$taskCoveredSubCount}/{$subCpmks->count()} Sub-CPMK terakomodir dalam Rencana Tugas Mahasiswa.",
            ],
'''
if old_check not in s: raise SystemExit('validator RTM check missing')
new_check = '''            [
                'key' => 'assessment_chain_sync',
                'label' => 'Sinkronisasi Rantai Asesmen',
                'done' => $assessmentChainAligned,
                'message' => "{$positiveNonExamMappedCount}/{$positiveNonExamAssessments->count()} asesmen non-UTS/UAS berbobot memiliki tag Sub-CPMK; "
                    ."{$weightedTeachingWeeks->count()}/14 pekan berbobot; "
                    ."kecocokan anggaran per Sub-CPMK ".($subBudgetAligned ? 'sesuai' : 'belum sesuai')."; "
                    ."RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']} dan asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.",
                'details' => [
                    'positive_non_exam_assessments' => $positiveNonExamAssessments->count(),
                    'mapped_positive_non_exam_assessments' => $positiveNonExamMappedCount,
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'sub_budget_aligned' => $subBudgetAligned,
                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
                    'rtm_required_missing' => $taskAlignment['missing_required_assessment_count'],
                ],
            ],
            [
                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'message' => $taskAssessments->isEmpty()
                    ? 'Belum ada asesmen tugas/proyek/presentasi yang mewajibkan RTM.'
                    : "{$tasks} RTM tersedia; {$taskAlignment['missing_required_assessment_count']} asesmen tugas belum memiliki RTM; {$taskAlignment['mapping_mismatch_count']} RTM memiliki tag Sub-CPMK yang berbeda dari asesmennya.",
                'details' => $taskAlignment,
            ],
'''
s = s.replace(old_check, new_check, 1)
p.write_text(s, encoding='utf-8')

# 9) UI: linked RTM follows assessment tags; grade explains why it is not yet available
p = root / 'resources/js/pages/rps/show.tsx'
s = p.read_text(encoding='utf-8')
old = "onChange={(e) => form.setData('assessment_id', e.target.value)}"
new = '''onChange={(e) => {
                                const assessmentId = e.target.value;
                                form.setData('assessment_id', assessmentId);
                                const selectedAssessment = assessments.find(
                                    (item: any) => item.id === assessmentId,
                                );
                                if (selectedAssessment) {
                                    form.setData(
                                        'sub_cpmk_ids',
                                        safeList(selectedAssessment.sub_cpmk_ids),
                                    );
                                }
                            }}'''
count = s.count(old)
if count < 2: raise SystemExit(f'expected >=2 task assessment select handlers, found {count}')
s = s.replace(old, new)
old_grade = '''                                    {Math.abs(totalWeeklyWeight - 100) < 0.01
                                        ? gradeLetter(totalSimulationScore)
                                        : '—'}'''
new_grade = '''                                    {Math.abs(totalWeeklyWeight - 100) < 0.01
                                        ? gradeLetter(totalSimulationScore)
                                        : `Menunggu 100% (${Number(totalWeeklyWeight.toFixed(2))}%)`}'''
if old_grade not in s: raise SystemExit('grade display marker missing')
s = s.replace(old_grade, new_grade, 1)
s = s.replace('Bobot non-UTS/UAS merupakan distribusi dari anggaran asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut.', 'Bobot non-UTS/UAS merupakan distribusi dari tag Sub-CPMK pada asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut. Nama asesmen pada simulasi mengikuti tag yang sama.')
p.write_text(s, encoding='utf-8')

# Basic marker validation
checks = {
    'app/Services/Rps/RpsAssessmentSyncService.php': ['syncVersion', 'rebalanceTeachingWeek', 'taskAlignment'],
    'app/Services/Rps/ObeWorkspaceService.php': ['assessment_chain_sync', 'Sinkronisasi Rantai Asesmen'],
    'resources/js/pages/rps/show.tsx': ['Menunggu 100%', 'selectedAssessment.sub_cpmk_ids'],
    'app/Http/Controllers/RpsController.php': ['assessment_names_by_week'],
}
for name, markers in checks.items():
    text = (root / name).read_text(encoding='utf-8')
    for marker in markers:
        if marker not in text:
            raise SystemExit(f'missing marker {marker} in {name}')

print('Assessment chain synchronization patch applied.')
