from pathlib import Path

ROOT = Path('.')


def replace_between(text, start_marker, end_marker, replacement):
    start = text.index(start_marker)
    end = text.index(end_marker, start)
    return text[:start] + replacement.rstrip() + "\n\n" + text[end:]

service_path = ROOT / 'app/Services/Rps/RpsAssessmentSyncService.php'
service = service_path.read_text()

snapshot = r'''    public function snapshot(string $versionId): array
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

        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('due_week')
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'due_week', 'title', 'assessment_id', 'type']);

        $taskIds = $tasks->pluck('id')->all();
        $taskLinks = $taskIds === []
            ? collect()
            : DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $taskIds)
                ->get(['rps_task_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_task_id');

        $weekByNumber = $weeks->keyBy(fn ($week) => (int) $week->week_number);
        $teachingWeeks = $weeks
            ->filter(fn ($week) => in_array((int) $week->week_number, self::TEACHING_WEEKS, true))
            ->values();

        $expectedCents = array_fill_keys(range(1, 16), 0);
        $namesByWeek = array_fill_keys(range(1, 16), []);
        $evidenceSourceByWeek = array_fill_keys(range(1, 16), null);
        $ownerByWeek = array_fill_keys(range(1, 16), null);
        $ownerNameByWeek = array_fill_keys(range(1, 16), '');
        $groupBudgetByWeek = array_fill_keys(range(1, 16), 0.0);
        $groupWeekCountByWeek = array_fill_keys(range(1, 16), 0);
        $assessmentTotalBudgetByWeek = array_fill_keys(range(1, 16), 0.0);
        $aggregateSubCents = [];
        $unmappedAssessments = [];
        $orphanSubLinks = [];
        $assessmentMeta = [];

        foreach ($assessments as $assessment) {
            $assessmentId = (string) $assessment->id;
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
                $namesByWeek[$weekNumber][] = trim((string) $assessment->name);
                $evidenceSourceByWeek[$weekNumber] = 'system_exam';
                $ownerByWeek[$weekNumber] = $assessmentId;
                $ownerNameByWeek[$weekNumber] = trim((string) $assessment->name);
                $assessmentTotalBudgetByWeek[$weekNumber] = round($weightCents / 100, 2);
                continue;
            }

            if ($weightCents > 0 && $linkedSubIds->isEmpty()) {
                $unmappedAssessments[] = [
                    'id' => $assessmentId,
                    'code' => (string) $assessment->code,
                    'name' => (string) $assessment->name,
                ];
                continue;
            }

            if ($weightCents <= 0 || $linkedSubIds->isEmpty()) {
                continue;
            }

            $base = intdiv($weightCents, $linkedSubIds->count());
            $remainder = $weightCents % $linkedSubIds->count();
            $subShares = [];

            foreach ($linkedSubIds as $index => $subId) {
                $share = $base + ($index < $remainder ? 1 : 0);
                $subShares[$subId] = $share;
                $aggregateSubCents[$subId] = ($aggregateSubCents[$subId] ?? 0) + $share;
            }

            $assessmentMeta[$assessmentId] = [
                'row' => $assessment,
                'subs' => $linkedSubIds->all(),
                'weight_cents' => $weightCents,
                'sub_shares' => $subShares,
            ];
        }

        $tasksByWeek = $tasks->groupBy(fn ($task) => (int) $task->due_week);
        $ambiguousEvidenceWeeks = [];
        $lockedWeeks = [];

        // RTM eksplisit pada suatu pekan adalah petunjuk terkuat mengenai
        // asesmen induk pekan tersebut. RTM hanya valid bila asesmennya memang
        // men-tag Sub-CPMK yang dipakai pada pekan itu.
        foreach ($teachingWeeks as $week) {
            $weekNumber = (int) $week->week_number;
            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            if (! $subId) continue;

            $taskCandidates = collect($tasksByWeek->get($weekNumber, []))
                ->filter(function ($task) use ($assessmentMeta, $subId): bool {
                    $assessmentId = filled($task->assessment_id ?? null)
                        ? (string) $task->assessment_id
                        : null;
                    return $assessmentId
                        && isset($assessmentMeta[$assessmentId])
                        && in_array($subId, $assessmentMeta[$assessmentId]['subs'], true);
                })
                ->values();

            if ($taskCandidates->isEmpty()) continue;

            $chosen = $taskCandidates->first();
            $ownerByWeek[$weekNumber] = (string) $chosen->assessment_id;
            $lockedWeeks[$weekNumber] = true;

            if ($taskCandidates->count() > 1) {
                $ambiguousEvidenceWeeks[] = [
                    'week' => $weekNumber,
                    'source' => 'rtm',
                    'candidates' => $taskCandidates
                        ->pluck('title')->map(fn ($title) => trim((string) $title))
                        ->filter()->unique()->values()->all(),
                ];
            }
        }

        // Setiap pasangan Asesmen↔Sub-CPMK harus mempunyai minimal satu pekan
        // pemilik agar bagian bobot pada matriks tidak hilang. Pilih pekan kosong
        // yang paling dekat dengan jadwal asesmen.
        foreach ($assessmentMeta as $assessmentId => $meta) {
            $preferred = (int) ($meta['row']->week_number ?? 0);

            foreach ($meta['subs'] as $subId) {
                $alreadyOwned = collect(self::TEACHING_WEEKS)->contains(function ($weekNumber) use (
                    $ownerByWeek,
                    $weekByNumber,
                    $assessmentId,
                    $subId
                ): bool {
                    $week = $weekByNumber->get($weekNumber);
                    return ($ownerByWeek[$weekNumber] ?? null) === $assessmentId
                        && $week
                        && (string) ($week->rps_sub_cpmk_id ?? '') === $subId;
                });

                if ($alreadyOwned) continue;

                $candidates = $teachingWeeks
                    ->filter(fn ($week) =>
                        (string) ($week->rps_sub_cpmk_id ?? '') === $subId
                        && empty($ownerByWeek[(int) $week->week_number])
                    )
                    ->sort(function ($a, $b) use ($preferred): int {
                        $aWeek = (int) $a->week_number;
                        $bWeek = (int) $b->week_number;
                        $aDistance = $preferred > 0 ? abs($aWeek - $preferred) : $aWeek;
                        $bDistance = $preferred > 0 ? abs($bWeek - $preferred) : $bWeek;
                        if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;
                        return $aWeek <=> $bWeek;
                    })
                    ->values();

                if ($candidates->isEmpty()) {
                    $orphanSubLinks[] = [
                        'assessment_id' => $assessmentId,
                        'assessment_name' => trim((string) $meta['row']->name),
                        'rps_sub_cpmk_id' => $subId,
                    ];
                    continue;
                }

                $ownerByWeek[(int) $candidates->first()->week_number] = $assessmentId;
            }
        }

        // Sisa pekan dibagikan ke asesmen yang eligible. Asesmen dengan bobot
        // relatif lebih besar cenderung memperoleh lebih banyak pekan, lalu
        // kedekatan jadwal menjadi tie-breaker.
        foreach ($teachingWeeks as $week) {
            $weekNumber = (int) $week->week_number;
            if (! empty($ownerByWeek[$weekNumber])) continue;

            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            if (! $subId) continue;

            $eligible = collect($assessmentMeta)
                ->filter(fn ($meta) => in_array($subId, $meta['subs'], true))
                ->sort(function ($a, $b) use ($ownerByWeek, $weekByNumber, $subId, $weekNumber): int {
                    $aId = (string) $a['row']->id;
                    $bId = (string) $b['row']->id;
                    $aOwned = collect(self::TEACHING_WEEKS)->filter(function ($number) use ($ownerByWeek, $weekByNumber, $aId, $subId) {
                        $row = $weekByNumber->get($number);
                        return ($ownerByWeek[$number] ?? null) === $aId
                            && $row && (string) ($row->rps_sub_cpmk_id ?? '') === $subId;
                    })->count();
                    $bOwned = collect(self::TEACHING_WEEKS)->filter(function ($number) use ($ownerByWeek, $weekByNumber, $bId, $subId) {
                        $row = $weekByNumber->get($number);
                        return ($ownerByWeek[$number] ?? null) === $bId
                            && $row && (string) ($row->rps_sub_cpmk_id ?? '') === $subId;
                    })->count();
                    $aShare = max(1, (int) ($a['sub_shares'][$subId] ?? 1));
                    $bShare = max(1, (int) ($b['sub_shares'][$subId] ?? 1));
                    $aRatio = $aOwned / $aShare;
                    $bRatio = $bOwned / $bShare;
                    if (abs($aRatio - $bRatio) > 0.000001) return $aRatio <=> $bRatio;

                    $aPreferred = (int) ($a['row']->week_number ?? 0);
                    $bPreferred = (int) ($b['row']->week_number ?? 0);
                    $aDistance = $aPreferred > 0 ? abs($aPreferred - $weekNumber) : 99;
                    $bDistance = $bPreferred > 0 ? abs($bPreferred - $weekNumber) : 99;
                    if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;
                    return strcmp((string) $a['row']->code, (string) $b['row']->code);
                })
                ->values();

            if ($eligible->isNotEmpty()) {
                $ownerByWeek[$weekNumber] = (string) $eligible->first()['row']->id;
            }
        }

        $weightOverrides = $this->weightOverrides($versionId);
        $invalidWeightOverrideWeeks = [];

        // Distribusi dilakukan per pasangan Asesmen↔Sub-CPMK. Dengan demikian
        // jumlah pekan untuk satu asesmen selalu tepat sama dengan bobot agregat
        // asesmen, dan bagian bobot per Sub-CPMK tetap sama dengan matriks.
        foreach ($assessmentMeta as $assessmentId => $meta) {
            foreach ($meta['subs'] as $subId) {
                $shareCents = (int) ($meta['sub_shares'][$subId] ?? 0);
                $groupWeeks = $teachingWeeks
                    ->filter(fn ($week) =>
                        (string) ($week->rps_sub_cpmk_id ?? '') === $subId
                        && ($ownerByWeek[(int) $week->week_number] ?? null) === $assessmentId
                    )
                    ->sortBy('week_number')
                    ->values();

                if ($groupWeeks->isEmpty()) continue;

                $manual = $groupWeeks
                    ->filter(fn ($week) => array_key_exists((int) $week->week_number, $weightOverrides))
                    ->mapWithKeys(fn ($week) => [
                        (int) $week->week_number => (int) round(
                            (float) $weightOverrides[(int) $week->week_number] * 100
                        ),
                    ]);

                $autoWeeks = $groupWeeks
                    ->reject(fn ($week) => $manual->has((int) $week->week_number))
                    ->values();
                $manualTotal = (int) $manual->sum();
                $remaining = $shareCents - $manualTotal;
                $valid = $shareCents > 0
                    && $manual->every(fn ($cents) => (int) $cents >= 1)
                    && $remaining >= $autoWeeks->count()
                    && ($autoWeeks->isNotEmpty() || $remaining === 0);

                if (! $valid && $manual->isNotEmpty()) {
                    foreach ($manual->keys() as $number) {
                        $invalidWeightOverrideWeeks[] = (int) $number;
                    }
                    $manual = collect();
                    $autoWeeks = $groupWeeks;
                    $manualTotal = 0;
                    $remaining = $shareCents;
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

                foreach ($groupWeeks as $week) {
                    $number = (int) $week->week_number;
                    $ownerNameByWeek[$number] = trim((string) $meta['row']->name);
                    $groupBudgetByWeek[$number] = round($shareCents / 100, 2);
                    $groupWeekCountByWeek[$number] = $groupWeeks->count();
                    $assessmentTotalBudgetByWeek[$number] = round($meta['weight_cents'] / 100, 2);
                }
            }
        }

        $missingEvidenceWeeks = [];
        foreach ($teachingWeeks as $week) {
            $weekNumber = (int) $week->week_number;
            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $ownerId = $ownerByWeek[$weekNumber] ?? null;

            if ($ownerId && isset($assessmentMeta[$ownerId])) {
                $owner = $assessmentMeta[$ownerId]['row'];
                $ownerNameByWeek[$weekNumber] = trim((string) $owner->name);

                $eligibleTasks = collect($tasksByWeek->get($weekNumber, []))
                    ->filter(function ($task) use ($ownerId, $subId, $taskLinks): bool {
                        if ((string) ($task->assessment_id ?? '') !== $ownerId) return false;
                        if (! $subId) return true;
                        $linked = collect($taskLinks->get($task->id, []))
                            ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique();
                        return $linked->isEmpty() || $linked->contains($subId);
                    })
                    ->values();

                if ($eligibleTasks->isNotEmpty()) {
                    $namesByWeek[$weekNumber] = [trim((string) $eligibleTasks->first()->title)];
                    $evidenceSourceByWeek[$weekNumber] = 'rtm';
                    if ($eligibleTasks->count() > 1) {
                        $ambiguousEvidenceWeeks[] = [
                            'week' => $weekNumber,
                            'source' => 'rtm',
                            'candidates' => $eligibleTasks->pluck('title')
                                ->map(fn ($title) => trim((string) $title))
                                ->filter()->unique()->values()->all(),
                        ];
                    }
                } else {
                    $namesByWeek[$weekNumber] = [trim((string) $owner->name)];
                    $evidenceSourceByWeek[$weekNumber] = 'assessment_owner';
                }
                continue;
            }

            $fallback = trim((string) ($week->assessment_method ?? ''));
            if ($fallback !== '') {
                $namesByWeek[$weekNumber] = [$fallback];
                $evidenceSourceByWeek[$weekNumber] = 'weekly_method';
            }

            if ((int) ($expectedCents[$weekNumber] ?? 0) > 0 && $fallback === '') {
                $missingEvidenceWeeks[] = $weekNumber;
            }
        }

        $ambiguousEvidenceWeeks = collect($ambiguousEvidenceWeeks)
            ->groupBy(fn ($item) => (int) ($item['week'] ?? 0))
            ->map(function ($items, $week) {
                $candidates = $items->flatMap(fn ($item) => $item['candidates'] ?? [])
                    ->filter()->unique()->values()->all();
                return [
                    'week' => (int) $week,
                    'source' => (string) ($items->first()['source'] ?? 'rtm'),
                    'candidates' => $candidates,
                ];
            })
            ->values()
            ->all();

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

        $expectedAssessmentCents = [];
        foreach (self::TEACHING_WEEKS as $weekNumber) {
            $ownerId = $ownerByWeek[$weekNumber] ?? null;
            if ($ownerId) {
                $expectedAssessmentCents[$ownerId] = ($expectedAssessmentCents[$ownerId] ?? 0)
                    + (int) ($expectedCents[$weekNumber] ?? 0);
            }
        }

        $assessmentBudgetMismatches = [];
        foreach ($assessmentMeta as $assessmentId => $meta) {
            $allocated = (int) ($expectedAssessmentCents[$assessmentId] ?? 0);
            if ($allocated !== (int) $meta['weight_cents']) {
                $assessmentBudgetMismatches[] = [
                    'assessment_id' => $assessmentId,
                    'assessment_name' => trim((string) $meta['row']->name),
                    'budget' => round($meta['weight_cents'] / 100, 2),
                    'allocated' => round($allocated / 100, 2),
                ];
            }
        }

        return [
            'expected_weekly_weights' => collect($expectedCents)
                ->map(fn ($cents) => round($cents / 100, 2))->all(),
            'assessment_names_by_week' => collect($namesByWeek)
                ->map(fn ($names) => collect($names)->filter()->unique()->values()->first() ?: '')->all(),
            'assessment_evidence_source_by_week' => $evidenceSourceByWeek,
            'assessment_owner_by_week' => $ownerByWeek,
            'assessment_owner_name_by_week' => $ownerNameByWeek,
            'assessment_group_budget_by_week' => $groupBudgetByWeek,
            'assessment_group_week_count_by_week' => $groupWeekCountByWeek,
            'assessment_total_budget_by_week' => $assessmentTotalBudgetByWeek,
            'assessment_budget_mismatches' => $assessmentBudgetMismatches,
            'ambiguous_evidence_weeks' => $ambiguousEvidenceWeeks,
            'missing_evidence_weeks' => array_values(array_unique($missingEvidenceWeeks)),
            'aggregate_sub_budgets' => collect($aggregateSubCents)
                ->map(fn ($cents) => round($cents / 100, 2))->all(),
            'actual_sub_budgets' => $actualSubBudgets,
            'unmapped_assessments' => $unmappedAssessments,
            'orphan_sub_links' => $orphanSubLinks,
            'weight_overrides' => $weightOverrides,
            'invalid_weight_override_weeks' => array_values(array_unique($invalidWeightOverrideWeeks)),
            'invalid_weight_override_sub_ids' => [],
            'aggregate_total' => round((float) $assessments->sum(
                fn ($assessment) => (float) ($assessment->weight ?? 0)
            ), 2),
        ];
    }'''

service = replace_between(service, '    public function snapshot(string $versionId): array', '    public function syncVersion(string $versionId): array', snapshot)

# Update invalid override cleanup in syncVersion.
old = """        $invalidSubIds = $snapshot['invalid_weight_override_sub_ids'] ?? [];
        if ($invalidSubIds !== []) {
            $this->dropWeightOverridesForSubCpmks($versionId, $invalidSubIds);
            $snapshot = $this->snapshot($versionId);
        }"""
new = """        $invalidOverrideWeeks = $snapshot['invalid_weight_override_weeks'] ?? [];
        if ($invalidOverrideWeeks !== []) {
            $this->dropWeightOverridesForWeeks($versionId, $invalidOverrideWeeks);
            $snapshot = $this->snapshot($versionId);
        }"""
if old not in service:
    raise SystemExit('syncVersion invalid override block not found')
service = service.replace(old, new, 1)

sync_task = r'''    public function syncTaskMappings(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(due_week, 99)')
            ->orderBy('code')
            ->get(['id', 'assessment_id', 'title', 'type', 'due_week']);

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

                $assessmentId = filled($task->assessment_id ?? null)
                    && $assessments->contains(fn ($assessment) => (string) $assessment->id === (string) $task->assessment_id)
                        ? (string) $task->assessment_id
                        : null;

                if ($assessmentId && $weekSubId && in_array($dueWeek, self::TEACHING_WEEKS, true)) {
                    $currentLinks = collect($assessmentLinks->get($assessmentId, []))
                        ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique();
                    if (! $currentLinks->contains($weekSubId)) $assessmentId = null;
                }

                if (! $assessmentId) {
                    $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);
                    $exact = $assessments->first(function ($assessment) use ($normalizedTaskTitle, $weekSubId, $dueWeek, $assessmentLinks): bool {
                        if ($this->normalizeLabel((string) $assessment->name) !== $normalizedTaskTitle) return false;
                        $type = strtolower((string) $assessment->type);
                        if ($dueWeek === 8) return $type === 'uts';
                        if ($dueWeek === 16) return $type === 'uas';
                        if (! $weekSubId || in_array($type, ['uts', 'uas'], true)) return false;
                        return collect($assessmentLinks->get($assessment->id, []))
                            ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->contains($weekSubId);
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
                                    ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->contains($weekSubId);
                            })
                            ->sort(function ($a, $b) use ($taskType, $dueWeek): int {
                                $aTypePenalty = strtolower((string) $a->type) === $taskType ? 0 : 1;
                                $bTypePenalty = strtolower((string) $b->type) === $taskType ? 0 : 1;
                                if ($aTypePenalty !== $bTypePenalty) return $aTypePenalty <=> $bTypePenalty;
                                $aDistance = abs(((int) ($a->week_number ?? 99)) - $dueWeek);
                                $bDistance = abs(((int) ($b->week_number ?? 99)) - $dueWeek);
                                if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;
                                return strcmp((string) $a->name, (string) $b->name);
                            })->values();
                        if ($candidates->isNotEmpty()) $assessmentId = (string) $candidates->first()->id;
                    }

                    if ($assessmentId) {
                        DB::table('rps_tasks')->where('id', $task->id)->update([
                            'assessment_id' => $assessmentId,
                            'updated_at' => now(),
                        ]);
                    }
                }

                $assessmentSubIds = $assessmentId
                    ? collect($assessmentLinks->get($assessmentId, []))
                        ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique()->values()
                    : collect();

                if (in_array($dueWeek, self::TEACHING_WEEKS, true) && $weekSubId) {
                    // RTM adalah bukti spesifik pekan, jadi tag RTM hanya Sub-CPMK
                    // pekan tersebut. Asesmen agregat tetap boleh men-tag beberapa
                    // Sub-CPMK pada matriks.
                    $subIds = ! $assessmentId || $assessmentSubIds->contains($weekSubId)
                        ? collect([$weekSubId])
                        : collect();
                } else {
                    $subIds = $assessmentId ? $assessmentSubIds : collect($weekSubId ? [$weekSubId] : []);
                }

                DB::table('rps_task_subcpmks')->where('rps_task_id', $task->id)->delete();
                foreach ($subIds as $subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $task->id,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($assessmentId) $linkedCount++;
            }
        });

        return $linkedCount;
    }'''
service = replace_between(service, '    public function syncTaskMappings(string $versionId): int', '    public function syncWeeklyIndicators(string $versionId): int', sync_task)

task_alignment = r'''    public function taskAlignment(string $versionId): array
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

        $weekRows = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))
            ->get(['week_number', 'rps_sub_cpmk_id', 'assessment_weight'])
            ->keyBy('week_number');

        $mismatchCount = 0;
        $dueWeekMismatchCount = 0;
        $unlinkedWeightedTaskCount = 0;

        foreach ($tasks as $task) {
            $dueWeek = (int) ($task->due_week ?? 0);
            $week = $weekRows->get($dueWeek);
            $actual = collect($taskLinks->get($task->id, []))
                ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique()->sort()->values()->all();

            if (! filled($task->assessment_id ?? null)) {
                if ($week && (float) ($week->assessment_weight ?? 0) > 0) $unlinkedWeightedTaskCount++;
                continue;
            }

            $assessmentSubIds = collect($assessmentLinks->get($task->assessment_id, []))
                ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique()->sort()->values();

            if (in_array($dueWeek, self::TEACHING_WEEKS, true) && $week && filled($week->rps_sub_cpmk_id ?? null)) {
                $weekSubId = (string) $week->rps_sub_cpmk_id;
                $expected = $assessmentSubIds->contains($weekSubId) ? [$weekSubId] : [];
                if (! $assessmentSubIds->contains($weekSubId)) $dueWeekMismatchCount++;
            } else {
                $expected = $assessmentSubIds->all();
            }

            sort($expected);
            if ($expected !== $actual) $mismatchCount++;
        }

        $requiredAssessmentIds = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->pluck('id')->map(fn ($id) => (string) $id)->unique()->values();

        $coveredAssessmentIds = $linkedTasks->pluck('assessment_id')
            ->filter()->map(fn ($id) => (string) $id)->unique()->values();
        $missingRequired = $requiredAssessmentIds->diff($coveredAssessmentIds)->values();

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
    }'''
service = replace_between(service, '    public function taskAlignment(string $versionId): array', '    public function rebalanceTeachingWeek(string $versionId, int $weekNumber, float $newWeight): array', task_alignment)

rebalance = r'''    public function rebalanceTeachingWeek(string $versionId, int $weekNumber, float $newWeight): array
    {
        if (! in_array($weekNumber, self::TEACHING_WEEKS, true)) {
            throw ValidationException::withMessages([
                'weight' => 'Bobot UTS/UAS mengikuti Asesmen Detail dan tidak diatur dari tabel RPS.',
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
        $assessmentId = (string) ($snapshot['assessment_owner_by_week'][$weekNumber] ?? '');
        $assessmentName = trim((string) ($snapshot['assessment_owner_name_by_week'][$weekNumber] ?? ''));
        $groupBudget = (float) ($snapshot['assessment_group_budget_by_week'][$weekNumber] ?? 0);
        $assessmentTotalBudget = (float) ($snapshot['assessment_total_budget_by_week'][$weekNumber] ?? 0);
        $groupBudgetCents = (int) round($groupBudget * 100);
        $newCents = (int) round(max(0, $newWeight) * 100);
        $subId = (string) $week->rps_sub_cpmk_id;

        if ($assessmentId === '' || $groupBudgetCents <= 0) {
            throw ValidationException::withMessages([
                'weight' => 'Pekan ini belum mempunyai asesmen induk yang valid. Perbaiki Asesmen Detail/RTM terlebih dahulu.',
            ]);
        }

        if ($newCents < 1) {
            throw ValidationException::withMessages([
                'weight' => 'Setiap pekan pembelajaran yang diukur harus memiliki bobot positif minimal 0,01%.',
            ]);
        }

        if ($newCents > $groupBudgetCents) {
            throw ValidationException::withMessages([
                'weight' => "Bobot pekan tidak boleh melebihi anggaran {$assessmentName} untuk {$this->subCpmkCode($subId)} sebesar {$groupBudget}%.",
            ]);
        }

        $ownerMap = collect($snapshot['assessment_owner_by_week'] ?? []);
        $group = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('rps_sub_cpmk_id', $subId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->orderBy('week_number')
            ->get(['id', 'week_number'])
            ->filter(fn ($item) => (string) $ownerMap->get((int) $item->week_number, '') === $assessmentId)
            ->values();

        if ($group->isEmpty()) {
            throw ValidationException::withMessages(['weight' => 'Kelompok distribusi asesmen untuk pekan ini tidak ditemukan.']);
        }

        $overrides = $this->weightOverrides($versionId);
        $overrides[$weekNumber] = round($newCents / 100, 2);
        $groupWeekNumbers = $group->pluck('week_number')->map(fn ($value) => (int) $value);
        $manual = collect($overrides)
            ->filter(fn ($value, $key) => $groupWeekNumbers->contains((int) $key))
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) round((float) $value * 100)]);

        $manualTotal = (int) $manual->sum();
        $autoWeeks = $group->reject(fn ($item) => $manual->has((int) $item->week_number))->values();
        $remaining = $groupBudgetCents - $manualTotal;

        if ($manualTotal > $groupBudgetCents) {
            throw ValidationException::withMessages([
                'weight' => "Jumlah pembagian manual untuk {$assessmentName} pada {$this->subCpmkCode($subId)} melebihi anggaran {$groupBudget}%.",
            ]);
        }
        if ($autoWeeks->isEmpty() && $remaining !== 0) {
            throw ValidationException::withMessages([
                'weight' => "Seluruh pekan kelompok ini sudah diatur manual. Totalnya harus tepat {$groupBudget}%.",
            ]);
        }
        if ($autoWeeks->isNotEmpty() && $remaining < $autoWeeks->count()) {
            throw ValidationException::withMessages([
                'weight' => 'Perubahan ini menyisakan kurang dari 0,01% untuk salah satu pekan lain yang memakai asesmen yang sama.',
            ]);
        }

        $distribution = [];
        foreach ($manual as $number => $cents) $distribution[(int) $number] = round($cents / 100, 2);
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
                DB::table('rps_weekly_plans')->where('id', $item->id)->update([
                    'assessment_weight' => (float) ($distribution[$number] ?? 0),
                    'updated_at' => now(),
                ]);
            }
            $this->saveWeightOverrides($versionId, $overrides);
        });

        return [
            'assessment_id' => $assessmentId,
            'assessment_name' => $assessmentName,
            'assessment_budget' => $assessmentTotalBudget,
            'group_budget' => $groupBudget,
            'sub_code' => $this->subCpmkCode($subId),
            'week_count' => $group->count(),
            'manual_week_count' => $manual->count(),
            'distribution' => $distribution,
        ];
    }'''
service = replace_between(service, '    public function rebalanceTeachingWeek(string $versionId, int $weekNumber, float $newWeight): array', '    private function weightOverrides(string $versionId): array', rebalance)

# Add helper for invalid overrides, retaining legacy helper below.
insert_marker = '    private function dropWeightOverridesForSubCpmks(string $versionId, array $subIds): void\n'
helper = r'''    private function dropWeightOverridesForWeeks(string $versionId, array $weekNumbers): void
    {
        $overrides = $this->weightOverrides($versionId);
        foreach ($weekNumbers as $weekNumber) {
            unset($overrides[(int) $weekNumber]);
        }
        $this->saveWeightOverrides($versionId, $overrides);
    }

'''
if insert_marker not in service:
    raise SystemExit('drop override insertion marker not found')
service = service.replace(insert_marker, helper + insert_marker, 1)
service_path.write_text(service)

# RpsTaskController: make RTM tags specific to the selected due week and resync weights after explicit task changes.
task_path = ROOT / 'app/Http/Controllers/RpsTaskController.php'
task = task_path.read_text()
task = task.replace("        $sync->syncTaskMappings($version->id);\n\n        return back()->with('success', 'RTM berhasil ditambahkan. Jika terhubung ke asesmen, tag Sub-CPMK otomatis mengikuti asesmen.');",
                    "        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'RTM berhasil ditambahkan. Tag Sub-CPMK mengikuti Sub-CPMK pekan, sedangkan asesmen agregat tetap menjadi sumber anggaran bobot.');", 1)
task = task.replace("        $sync->syncTaskMappings($version->id);\n\n        return back()->with('success', 'RTM berhasil diperbarui dan tag Sub-CPMK disinkronkan dengan asesmen terkait.');",
                    "        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'RTM berhasil diperbarui dan distribusi asesmen-pekan disinkronkan.');", 1)
task = task.replace("    public function destroy(Request $request, string $rps, string $task): RedirectResponse",
                    "    public function destroy(Request $request, string $rps, string $task, RpsAssessmentSyncService $sync): RedirectResponse", 1)
task = task.replace("        DB::table('rps_tasks')\n            ->where('id', $task)\n            ->where('rps_version_id', $version->id)\n            ->delete();\n\n        return back()->with('success', 'RTM berhasil dihapus.');",
                    "        DB::table('rps_tasks')\n            ->where('id', $task)\n            ->where('rps_version_id', $version->id)\n            ->delete();\n\n        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'RTM berhasil dihapus dan distribusi asesmen-pekan disinkronkan ulang.');", 1)

old_defaults = """        $validated['type'] = $type;
        $validated['sub_cpmk_ids'] = DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessment->id)
            ->pluck('rps_sub_cpmk_id')
            ->map('strval')
            ->unique()
            ->values()
            ->all();

        if (empty($validated['due_week']) && filled($assessment->week_number)) {
            $validated['due_week'] = (int) $assessment->week_number;
        }

        return $validated;"""
new_defaults = """        $validated['type'] = $type;
        $assessmentSubIds = DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessment->id)
            ->pluck('rps_sub_cpmk_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if (empty($validated['due_week']) && filled($assessment->week_number)) {
            $validated['due_week'] = (int) $assessment->week_number;
        }

        $dueWeek = (int) ($validated['due_week'] ?? 0);
        if (in_array($dueWeek, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true)) {
            $weekSubId = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->where('week_number', $dueWeek)
                ->value('rps_sub_cpmk_id');

            if (filled($weekSubId)) {
                if (! $assessmentSubIds->contains((string) $weekSubId)) {
                    throw \\Illuminate\\Validation\\ValidationException::withMessages([
                        'due_week' => 'Asesmen yang dipilih tidak mengukur Sub-CPMK pada Pekan '.$dueWeek.'. Ubah Asesmen Terkait atau Pekan Pengumpulan.',
                    ]);
                }
                $validated['sub_cpmk_ids'] = [(string) $weekSubId];
            } else {
                $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
            }
        } else {
            $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
        }

        return $validated;"""
if old_defaults not in task:
    raise SystemExit('RpsTaskController defaults block not found')
task = task.replace(old_defaults, new_defaults, 1)
task_path.write_text(task)

# RpsDocumentController success message now explains assessment-owned budget.
doc_path = ROOT / 'app/Http/Controllers/RpsDocumentController.php'
doc = doc_path.read_text()
old_msg = '            "Bobot Pekan {$week} disimpan {$newWeight}%. Anggaran {$result[\'sub_code\']} tetap {$result[\'sub_budget\']}%; distribusi: {$distribution}. Bobot asesmen agregat tidak berubah dan RTM/validator mengikuti bobot pekan terbaru."'
new_msg = '            "Bobot Pekan {$week} disimpan {$newWeight}%. Anggaran {$result[\'assessment_name\']} untuk {$result[\'sub_code\']} tetap {$result[\'group_budget\']}% (total asesmen {$result[\'assessment_budget\']}%); distribusi: {$distribution}."'
if old_msg not in doc:
    raise SystemExit('RpsDocumentController weight message not found')
doc = doc.replace(old_msg, new_msg, 1)
doc_path.write_text(doc)

# RpsController exposes assessment-owned group budget to the weekly editor.
controller_path = ROOT / 'app/Http/Controllers/RpsController.php'
controller = controller_path.read_text()
old_vars_start = """        $assessmentSubBudgets = collect(
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
            ->map(fn ($items) => $items->count());"""
new_vars = """        $assessmentOwnerByWeek = collect($assessmentSyncSnapshot['assessment_owner_by_week'] ?? []);
        $assessmentOwnerNameByWeek = collect($assessmentSyncSnapshot['assessment_owner_name_by_week'] ?? []);
        $assessmentGroupBudgetByWeek = collect($assessmentSyncSnapshot['assessment_group_budget_by_week'] ?? []);
        $assessmentGroupWeekCountByWeek = collect($assessmentSyncSnapshot['assessment_group_week_count_by_week'] ?? []);
        $assessmentTotalBudgetByWeek = collect($assessmentSyncSnapshot['assessment_total_budget_by_week'] ?? []);
        $weightOverrides = collect(
            $assessmentSyncSnapshot['weight_overrides'] ?? []
        );"""
if old_vars_start not in controller:
    raise SystemExit('RpsController old budget vars block not found')
controller = controller.replace(old_vars_start, new_vars, 1)
old_capture = """            $assessmentNamesByWeek,
            $assessmentSubBudgets,
            $weightOverrides,
            $teachingWeekCountsBySub
        ): object {"""
new_capture = """            $assessmentNamesByWeek,
            $assessmentOwnerByWeek,
            $assessmentOwnerNameByWeek,
            $assessmentGroupBudgetByWeek,
            $assessmentGroupWeekCountByWeek,
            $assessmentTotalBudgetByWeek,
            $weightOverrides
        ): object {"""
if old_capture not in controller:
    raise SystemExit('RpsController closure capture not found')
controller = controller.replace(old_capture, new_capture, 1)
old_week_budget = """            $subId = filled($week->rps_sub_cpmk_id ?? null)
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
                && $weightOverrides->has($weekNumber);"""
new_week_budget = """            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $ownerId = (string) $assessmentOwnerByWeek->get($weekNumber, '');
            $ownerName = (string) $assessmentOwnerNameByWeek->get($weekNumber, '');
            $groupBudget = (float) $assessmentGroupBudgetByWeek->get($weekNumber, 0);
            $groupWeekCount = (int) $assessmentGroupWeekCountByWeek->get($weekNumber, 0);
            $assessmentTotalBudget = (float) $assessmentTotalBudgetByWeek->get($weekNumber, 0);
            $isTeachingWeek = ! in_array($weekNumber, [8, 16], true);

            $week->assessment_owner_id = $ownerId ?: null;
            $week->assessment_owner_name = $ownerName;
            $week->assessment_group_budget = $groupBudget;
            $week->assessment_group_week_count = $groupWeekCount;
            $week->assessment_total_budget = $assessmentTotalBudget;
            // Alias lama dipertahankan sementara untuk kompatibilitas komponen UI.
            $week->assessment_sub_budget = $groupBudget;
            $week->assessment_sub_week_count = $groupWeekCount;
            $week->assessment_weight_editable = $isTeachingWeek
                && $subId !== null
                && $ownerId !== ''
                && $groupBudget > 0;
            $week->assessment_weight_manual = $isTeachingWeek
                && $weightOverrides->has($weekNumber);"""
if old_week_budget not in controller:
    raise SystemExit('RpsController week budget block not found')
controller = controller.replace(old_week_budget, new_week_budget, 1)
controller_path.write_text(controller)

# Validator: require exact per-assessment allocation in addition to per-Sub-CPMK alignment.
workspace_path = ROOT / 'app/Services/Rps/ObeWorkspaceService.php'
workspace = workspace_path.read_text()
needle = """        $assessmentSnapshot = $assessmentSync->snapshot($versionId);
        $tasks = (int) $taskAlignment['task_total'];"""
replacement = """        $assessmentSnapshot = $assessmentSync->snapshot($versionId);
        $assessmentBudgetMismatches = collect($assessmentSnapshot['assessment_budget_mismatches'] ?? []);
        $assessmentBudgetAligned = $assessmentBudgetMismatches->isEmpty();
        $tasks = (int) $taskAlignment['task_total'];"""
if needle not in workspace:
    raise SystemExit('workspace snapshot marker not found')
workspace = workspace.replace(needle, replacement, 1)
workspace = workspace.replace("""            && $subBudgetAligned
            && $weeklyEvidenceAligned""", """            && $subBudgetAligned
            && $assessmentBudgetAligned
            && $weeklyEvidenceAligned""", 1)
workspace = workspace.replace("""                    && $subBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,""", """                    && $subBudgetAligned
                    && $assessmentBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,""", 1)
workspace = workspace.replace("""                    'sub_budget_aligned' => $subBudgetAligned,
                    'weekly_sub_budgets' => $weeklySubBudgets->all(),""", """                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_sub_budgets' => $weeklySubBudgets->all(),""", 1)
old_chain_message = """                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : ($ambiguousWeekNumbers->isNotEmpty()"""
new_chain_message = """                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : (! $assessmentBudgetAligned
                        ? $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.'
                        : ($ambiguousWeekNumbers->isNotEmpty()"""
if old_chain_message not in workspace:
    raise SystemExit('workspace chain message start not found')
workspace = workspace.replace(old_chain_message, new_chain_message, 1)
old_tail = """                                ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                : 'Masih ada data penilaian yang belum konsisten.'))),"""
new_tail = """                                ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                : 'Masih ada data penilaian yang belum konsisten.')))),"""
if old_tail not in workspace:
    raise SystemExit('workspace chain message tail not found')
workspace = workspace.replace(old_tail, new_tail, 1)
workspace = workspace.replace("""                    'sub_budget_aligned' => $subBudgetAligned,
                    'weekly_evidence_aligned' => $weeklyEvidenceAligned,""", """                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_evidence_aligned' => $weeklyEvidenceAligned,""", 1)
workspace_path.write_text(workspace)

# Frontend confirmation wording: budget is owned by assessment + Sub-CPMK group.
show_path = ROOT / 'resources/js/pages/rps/show.tsx'
show = show_path.read_text()
show = show.replace("const budget = Number(week.assessment_sub_budget || 0);", "const budget = Number(week.assessment_group_budget || week.assessment_sub_budget || 0);", 1)
show = show.replace("const groupCount = Number(week.assessment_sub_week_count || 0);", "const groupCount = Number(week.assessment_group_week_count || week.assessment_sub_week_count || 0);", 1)
show = show.replace("`Anggaran ${week.sub_cpmk_code || 'Sub-CPMK'} tetap ${budget}% untuk ${groupCount} pekan. `", "`Anggaran ${week.assessment_owner_name || 'asesmen terkait'} untuk ${week.sub_cpmk_code || 'Sub-CPMK'} tetap ${budget}% pada ${groupCount} pekan. `", 1)
show_path.write_text(show)

print('assessment-owned weekly budget patch applied')
