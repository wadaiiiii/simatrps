from pathlib import Path


def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit(f'target not found: {label}')
    return text.replace(old, new, 1)

# Smart draft: derive each Sub-CPMK budget from aggregate assessment mappings.
path = Path('app/Services/Rps/RpsSmartDraftService.php')
s = path.read_text(encoding='utf-8')
old = r'''        // Setiap pekan yang mengukur Sub-CPMK harus memperoleh bobot positif.
        // Beri minimum 0,01% terlebih dahulu, lalu distribusikan sisanya menurut
        // target bobot per Sub-CPMK. Target Sub-CPMK dibagi rata, kemudian target
        // Sub-CPMK dibagi lagi menurut jumlah pertemuannya.
        $allocations = [];
        foreach ($emptyWeeks as $week) {
            $allocations[(string) $week->id] = 1;
        }
        $remainingCents -= $emptyWeeks->count();

        $groups = $weeks->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id);
        $subIds = $groups->keys()->values();
        $subCount = max(1, $subIds->count());
        $baseTarget = intdiv($budgetCents, $subCount);
        $targetRemainder = $budgetCents % $subCount;
        $desiredBySub = [];

        foreach ($subIds as $index => $subId) {
            $group = $groups->get($subId);
            $target = $baseTarget + ($index < $targetRemainder ? 1 : 0);
            $existing = 0;
            $emptyIds = [];

            foreach ($group as $week) {
                $id = (string) $week->id;
                $weightCents = (int) round(max(0, (float) ($week->assessment_weight ?? 0)) * 100);
                if ($weightCents > 0) {
                    $existing += $weightCents;
                } else {
                    $emptyIds[] = $id;
                    $existing += $allocations[$id] ?? 0;
                }
            }

            if ($emptyIds !== []) {
                $desiredBySub[$subId] = [
                    'ids' => $emptyIds,
                    'desired' => max(0, $target - $existing),
                ];
            }
        }

        $totalDesired = array_sum(array_column($desiredBySub, 'desired'));
        $groupPool = min($remainingCents, $totalDesired);
        $assignedGroupPool = 0;
        $entries = array_values($desiredBySub);

        foreach ($entries as $index => $entry) {
            if ($groupPool <= 0 || $entry['desired'] <= 0) {
                continue;
            }

            $isLast = $index === count($entries) - 1;
            $groupAllocation = $totalDesired > 0
                ? ($isLast
                    ? $groupPool - $assignedGroupPool
                    : (int) floor($groupPool * ($entry['desired'] / $totalDesired)))
                : 0;
            $groupAllocation = max(0, min($groupAllocation, $remainingCents - $assignedGroupPool));

            $count = count($entry['ids']);
            $base = $count > 0 ? intdiv($groupAllocation, $count) : 0;
            $remainder = $count > 0 ? $groupAllocation % $count : 0;

            foreach ($entry['ids'] as $position => $id) {
                $allocations[$id] += $base + ($position < $remainder ? 1 : 0);
            }

            $assignedGroupPool += $groupAllocation;
        }

        $remainingCents -= $assignedGroupPool;

        // Jika ada bobot manual yang membuat satu Sub-CPMK melebihi target rata,
        // sisa anggaran tidak boleh hilang. Sebarkan sisa ke semua pekan kosong
        // tanpa mengubah bobot yang sudah diputuskan dosen.
        if ($remainingCents > 0) {
            $ids = array_keys($allocations);
            $base = intdiv($remainingCents, count($ids));
            $remainder = $remainingCents % count($ids);

            foreach ($ids as $index => $id) {
                $allocations[$id] += $base + ($index < $remainder ? 1 : 0);
            }
        }

        foreach ($allocations as $id => $cents) {
            DB::table('rps_weekly_plans')
                ->where('id', $id)
                ->update([
                    'assessment_weight' => round($cents / 100, 2),
                    'updated_at' => now(),
                ]);
        }

        return 'Bobot pekan kosong dibagi dari anggaran asesmen non-UTS/UAS: bobot per Sub-CPMK dibagi lagi sesuai jumlah pertemuannya.';
'''
new = r'''        // Target bobot Sub-CPMK diturunkan dari pemetaan asesmen agregat.
        // Contoh: asesmen 10% hanya terkait Sub-CPMK-1, maka target Sub-CPMK-1
        // adalah 10%. Bila Sub-CPMK-1 muncul pada dua pekan yang masih kosong,
        // pembagian default menjadi 5% + 5%. Jika satu asesmen terkait beberapa
        // Sub-CPMK, bobot asesmen dibagi merata seperti matriks evaluasi CPL.
        $groups = $weeks->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id);
        $subIds = $groups->keys()->values();
        $targetBySub = array_fill_keys($subIds->all(), 0);

        $nonExamAssessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereNotIn('type', ['uts', 'uas'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'weight']);

        foreach ($nonExamAssessments as $assessment) {
            $linkedSubIds = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->whereIn('rps_sub_cpmk_id', $subIds->all())
                ->orderBy('rps_sub_cpmk_id')
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if ($linkedSubIds->isEmpty()) {
                return 'Bobot pekan belum dibagi: asesmen '.($assessment->code ?: $assessment->name).' belum dipetakan ke Sub-CPMK yang digunakan pada 14 pekan.';
            }

            $assessmentCents = (int) round(max(0, (float) ($assessment->weight ?? 0)) * 100);
            $base = intdiv($assessmentCents, $linkedSubIds->count());
            $remainder = $assessmentCents % $linkedSubIds->count();

            foreach ($linkedSubIds as $index => $subId) {
                $targetBySub[$subId] = ($targetBySub[$subId] ?? 0)
                    + $base
                    + ($index < $remainder ? 1 : 0);
            }
        }

        foreach ($subIds as $subId) {
            if (($targetBySub[$subId] ?? 0) <= 0) {
                return 'Bobot pekan belum dibagi: setiap Sub-CPMK yang digunakan pada pekan pembelajaran harus memiliki alokasi bobot dari asesmen non-UTS/UAS.';
            }
        }

        if (array_sum($targetBySub) !== $budgetCents) {
            return 'Bobot pekan belum dibagi karena pemetaan asesmen ke Sub-CPMK belum merepresentasikan seluruh anggaran asesmen non-UTS/UAS.';
        }

        $allocations = [];

        // Validasi seluruh kelompok lebih dulu agar proses bersifat atomik secara
        // logis: bila satu Sub-CPMK sudah melebihi target akibat bobot manual,
        // jangan mengisi kelompok lain lalu meninggalkan distribusi setengah jadi.
        foreach ($subIds as $subId) {
            $group = $groups->get($subId);
            $target = (int) ($targetBySub[$subId] ?? 0);
            $existing = 0;
            $emptyIds = [];

            foreach ($group as $week) {
                $id = (string) $week->id;
                $weightCents = (int) round(max(0, (float) ($week->assessment_weight ?? 0)) * 100);
                if ($weightCents > 0) {
                    $existing += $weightCents;
                } else {
                    $emptyIds[] = $id;
                }
            }

            if ($existing > $target) {
                return 'Bobot pekan belum dibagi karena bobot manual pada salah satu Sub-CPMK sudah melebihi anggaran asesmen agregatnya. Turunkan bobot pekan terkait terlebih dahulu.';
            }

            $needed = $target - $existing;

            if ($emptyIds === []) {
                if ($needed !== 0) {
                    return 'Distribusi bobot salah satu Sub-CPMK belum sama dengan anggaran asesmen agregatnya. Sesuaikan bobot pekan terkait terlebih dahulu.';
                }
                continue;
            }

            if ($needed < count($emptyIds)) {
                return 'Sisa anggaran salah satu Sub-CPMK terlalu kecil untuk memberi bobot positif pada setiap pekannya. Sesuaikan bobot manual terlebih dahulu.';
            }

            $base = intdiv($needed, count($emptyIds));
            $remainder = $needed % count($emptyIds);

            foreach ($emptyIds as $index => $id) {
                $allocations[$id] = $base + ($index < $remainder ? 1 : 0);
            }
        }

        foreach ($allocations as $id => $cents) {
            DB::table('rps_weekly_plans')
                ->where('id', $id)
                ->update([
                    'assessment_weight' => round($cents / 100, 2),
                    'updated_at' => now(),
                ]);
        }

        return 'Bobot pekan kosong dibagi dari pemetaan asesmen non-UTS/UAS ke Sub-CPMK, lalu dibagi sesuai jumlah pertemuan masing-masing Sub-CPMK.';
'''
s = replace_once(s, old, new, 'mapped smart draft weekly distribution')
path.write_text(s, encoding='utf-8')

# Validator: require weekly budget per Sub-CPMK to match aggregate assessment mapping.
path = Path('app/Services/Rps/ObeWorkspaceService.php')
s = path.read_text(encoding='utf-8')
old_metrics_end = r'''        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')
            ->filter()
            ->unique()
            ->count();
'''
new_metrics_end = r'''        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')
            ->filter()
            ->unique()
            ->count();

        $weeklySubBudgets = $teachingWeeks
            ->filter(fn ($week) => filled($week->rps_sub_cpmk_id ?? null))
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)
            ->map(fn ($items) => round((float) $items->sum(
                fn ($week) => (float) ($week->assessment_weight ?? 0)
            ), 2));

        $aggregateSubBudgets = collect();
        $nonExamAssessments = $assessments->reject(
            fn ($assessment) => in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true)
        );

        foreach ($nonExamAssessments as $assessment) {
            $linked = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->orderBy('rps_sub_cpmk_id')
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if ($linked->isEmpty() || (float) ($assessment->weight ?? 0) <= 0) {
                continue;
            }

            $cents = (int) round((float) $assessment->weight * 100);
            $base = intdiv($cents, $linked->count());
            $remainder = $cents % $linked->count();

            foreach ($linked as $index => $subId) {
                $share = ($base + ($index < $remainder ? 1 : 0)) / 100;
                $aggregateSubBudgets->put(
                    $subId,
                    round((float) $aggregateSubBudgets->get($subId, 0) + $share, 2)
                );
            }
        }

        $subBudgetAligned = $subCpmkIds->isNotEmpty()
            && $subCpmkIds->every(function ($subId) use ($weeklySubBudgets, $aggregateSubBudgets): bool {
                $weekly = (float) $weeklySubBudgets->get((string) $subId, 0);
                $aggregate = (float) $aggregateSubBudgets->get((string) $subId, 0);

                return $aggregate > 0 && abs($weekly - $aggregate) < 0.011;
            });
'''
s = replace_once(s, old_metrics_end, new_metrics_end, 'validator per-sub budgets')

old_done = r'''                'done' => abs($assessmentWeightTotal - 100.0) < 0.01
                    && $weightedTeachingWeeks->count() === 14
                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01
                    && abs($weightTotal - 100.0) < 0.01,
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; distribusi pekan non-ujian {$teachingWeightTotal}% dari anggaran asesmen non-UTS/UAS {$nonExamAssessmentWeight}%; total tabel RPS {$weightTotal}% dan total asesmen agregat {$assessmentWeightTotal}%.",
'''
new_done = r'''                'done' => abs($assessmentWeightTotal - 100.0) < 0.01
                    && $weightedTeachingWeeks->count() === 14
                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01
                    && $subBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; distribusi pekan non-ujian {$teachingWeightTotal}% dari anggaran asesmen non-UTS/UAS {$nonExamAssessmentWeight}%; kesesuaian bobot per Sub-CPMK ".($subBudgetAligned ? 'sesuai' : 'belum sesuai')."; total tabel RPS {$weightTotal}% dan total asesmen agregat {$assessmentWeightTotal}%.",
'''
s = replace_once(s, old_done, new_done, 'validator require sub-budget alignment')

old_details = r'''                    'aggregate_assessment_total' => $assessmentWeightTotal,
                ],
'''
new_details = r'''                    'aggregate_assessment_total' => $assessmentWeightTotal,
                    'sub_budget_aligned' => $subBudgetAligned,
                    'weekly_sub_budgets' => $weeklySubBudgets->all(),
                    'aggregate_sub_budgets' => $aggregateSubBudgets->all(),
                ],
'''
s = replace_once(s, old_details, new_details, 'validator budget details')
path.write_text(s, encoding='utf-8')

print('mapped Sub-CPMK weekly weight distribution patch applied')
