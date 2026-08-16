<?php

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
