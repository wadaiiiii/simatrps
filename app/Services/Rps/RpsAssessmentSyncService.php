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
            ->get(['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight', 'assessment_method']);

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
                }
            }
        }

        // Distribusi manual dosen disimpan sebagai override per pekan pada
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

        // Simulasi harus menampilkan SATU bukti penilaian utama yang benar-benar
        // relevan pada setiap pekan, bukan seluruh asesmen agregat yang men-tag
        // Sub-CPMK yang sama. Prioritas:
        // 1) RTM yang jatuh tepat pada pekan dan sesuai Sub-CPMK pekan;
        // 2) asesmen agregat yang week_number-nya tepat pada pekan dan men-tag
        //    Sub-CPMK pekan;
        // 3) bentuk penilaian pada tabel RPS sebagai fallback formatif.
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('due_week')
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'due_week', 'title', 'assessment_id']);

        $taskIds = $tasks->pluck('id')->all();
        $taskLinks = $taskIds === []
            ? collect()
            : DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $taskIds)
                ->get(['rps_task_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_task_id');

        $tasksByWeek = $tasks->groupBy(fn ($task) => (int) $task->due_week);
        $evidenceSourceByWeek = array_fill_keys(range(1, 16), null);
        $ambiguousEvidenceWeeks = [];
        $missingEvidenceWeeks = [];

        foreach ($weeks as $week) {
            $weekNumber = (int) $week->week_number;

            if (in_array($weekNumber, [8, 16], true)) {
                $evidenceSourceByWeek[$weekNumber] = 'system_exam';
                continue;
            }

            if (! in_array($weekNumber, self::TEACHING_WEEKS, true)) {
                continue;
            }

            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;

            $taskCandidates = collect($tasksByWeek->get($weekNumber, []))
                ->filter(function ($task) use ($taskLinks, $subId): bool {
                    if (! $subId) {
                        return false;
                    }

                    return collect($taskLinks->get($task->id, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->contains($subId);
                })
                ->values();

            if ($taskCandidates->isNotEmpty()) {
                $chosen = $taskCandidates->first();
                $namesByWeek[$weekNumber] = [trim((string) $chosen->title)];
                $evidenceSourceByWeek[$weekNumber] = 'rtm';

                if ($taskCandidates->count() > 1) {
                    $ambiguousEvidenceWeeks[] = [
                        'week' => $weekNumber,
                        'source' => 'rtm',
                        'candidates' => $taskCandidates
                            ->pluck('title')
                            ->map(fn ($title) => trim((string) $title))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                }

                continue;
            }

            $assessmentCandidates = $assessments
                ->filter(function ($assessment) use ($weekNumber, $subId, $links): bool {
                    if (! $subId || (int) ($assessment->week_number ?? 0) !== $weekNumber) {
                        return false;
                    }

                    $type = strtolower((string) ($assessment->type ?? ''));
                    if (in_array($type, ['uts', 'uas'], true) || (float) ($assessment->weight ?? 0) <= 0) {
                        return false;
                    }

                    return collect($links->get($assessment->id, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->contains($subId);
                })
                ->values();

            if ($assessmentCandidates->isNotEmpty()) {
                $chosen = $assessmentCandidates->first();
                $namesByWeek[$weekNumber] = [trim((string) $chosen->name)];
                $evidenceSourceByWeek[$weekNumber] = 'assessment_week';

                if ($assessmentCandidates->count() > 1) {
                    $ambiguousEvidenceWeeks[] = [
                        'week' => $weekNumber,
                        'source' => 'assessment_week',
                        'candidates' => $assessmentCandidates
                            ->pluck('name')
                            ->map(fn ($name) => trim((string) $name))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                }

                continue;
            }

            $fallback = trim((string) ($week->assessment_method ?? ''));
            if ($fallback !== '') {
                $namesByWeek[$weekNumber] = [$fallback];
                $evidenceSourceByWeek[$weekNumber] = 'weekly_method';
                continue;
            }

            if ((float) ($week->assessment_weight ?? 0) > 0) {
                $missingEvidenceWeeks[] = $weekNumber;
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
                ->map(fn ($names) => collect($names)->filter()->unique()->values()->first() ?: '')
                ->all(),
            'assessment_evidence_source_by_week' => $evidenceSourceByWeek,
            'ambiguous_evidence_weeks' => $ambiguousEvidenceWeeks,
            'missing_evidence_weeks' => array_values(array_unique($missingEvidenceWeeks)),
            'aggregate_sub_budgets' => collect($aggregateSubCents)
                ->map(fn ($cents) => round($cents / 100, 2))
                ->all(),
            'actual_sub_budgets' => $actualSubBudgets,
            'unmapped_assessments' => $unmappedAssessments,
            'orphan_sub_links' => $orphanSubLinks,
            'weight_overrides' => $weightOverrides,
            'invalid_weight_override_sub_ids' => array_values(array_unique($invalidWeightOverrideSubIds)),
            'aggregate_total' => round((float) $assessments->sum(
                fn ($assessment) => (float) ($assessment->weight ?? 0)
            ), 2),
        ];
    }

    public function syncVersion(string $versionId): array
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

