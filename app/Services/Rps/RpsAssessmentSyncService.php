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

        // Simulasi menampilkan bukti/penugasan yang benar-benar jatuh
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
        $indicatorFixes = $this->syncWeeklyIndicators($versionId);
        $linkedTasks = $this->syncTaskMappings($versionId);
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
            'message' => "Sinkronisasi asesmen diterapkan: {$weightedTeachingWeeks}/14 pekan pembelajaran memiliki bobot berdasarkan tag Sub-CPMK asesmen; {$linkedTasks} RTM terhubung ke asesmen; {$indicatorFixes} indikator pekan yang salah Sub-CPMK diperbaiki.",
        ];
    }

    public function syncTaskMappings(string $versionId): int
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
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function taskAlignment(string $versionId): array
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'assessment_id', 'due_week']);

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
            'task_total' => $tasks->count(),
            'linked_task_total' => $linkedTasks->count(),
            'required_assessment_total' => $requiredAssessmentIds->count(),
            'missing_required_assessment_count' => $missingRequired->count(),
            'mapping_mismatch_count' => $mismatchCount,
            'unlinked_weighted_task_count' => $unlinkedWeightedTaskCount,
            'due_week_subcpmk_mismatch_count' => $dueWeekMismatchCount,
            'is_aligned' => $missingRequired->isEmpty()
                && $mismatchCount === 0
                && $unlinkedWeightedTaskCount === 0
                && $dueWeekMismatchCount === 0,
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
