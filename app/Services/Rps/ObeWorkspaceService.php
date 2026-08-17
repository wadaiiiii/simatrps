<?php

namespace App\Services\Rps;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ObeWorkspaceService
{
    public function progress(string $versionId): array
    {
        $context = DB::table('rps_versions')
            ->join('rps', 'rps.id', '=', 'rps_versions.rps_id')
            ->where('rps_versions.id', $versionId)
            ->first([
                'rps.course_id',
            ]);

        $courseId = $context?->course_id;

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id']);

        $cpmkIds = $cpmks->pluck('id');

        $mappedCpmkCount = $cpmkIds->isEmpty()
            ? 0
            : DB::table('rps_cpmk_cpls')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->distinct()
                ->count('rps_cpmk_id');

        $officialCplIds = $courseId
            ? DB::table('course_cpls')
                ->where('course_id', $courseId)
                ->pluck('cpl_id')
            : collect();

        $additionalCplIds = Schema::hasTable('rps_additional_cpls')
            ? DB::table('rps_additional_cpls')
                ->where('rps_version_id', $versionId)
                ->pluck('cpl_id')
            : collect();

        $scopeCplIds = $officialCplIds
            ->merge($additionalCplIds)
            ->unique()
            ->values();

        $mappedCplIds = $cpmkIds->isEmpty() || $scopeCplIds->isEmpty()
            ? collect()
            : DB::table('rps_cpmk_cpls')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->whereIn('cpl_id', $scopeCplIds)
                ->distinct()
                ->pluck('cpl_id');

        $mappedScopeCplCount = $mappedCplIds->count();
        $scopeCplCount = $scopeCplIds->count();
        $officialCplCount = $officialCplIds->unique()->count();
        $additionalCplCount = $additionalCplIds->unique()->count();

        $allCpmksMapped = $cpmks->isNotEmpty()
            && $mappedCpmkCount === $cpmks->count();

        $allScopeCplsMapped = $scopeCplCount > 0
            && $mappedScopeCplCount === $scopeCplCount;

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id']);

        $subCpmkIds = $subCpmks->pluck('id');

        $coveredCpmkCount = $cpmkIds->isEmpty()
            ? 0
            : DB::table('rps_cpmk_subcpmks')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->distinct()
                ->count('rps_cpmk_id');

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->count();

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->orderBy('week_number')
            ->get();

        $filledWeeks = $weeks
            ->filter(fn ($week) =>
                $week->is_exam
                || (
                    filled($week->rps_sub_cpmk_id)
                    && filled($week->material_text)
                    && filled($week->learning_method)
                    && filled($week->learning_activity)
                )
            )
            ->count();

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->get();

        $assessmentIds = $assessments->pluck('id');

        $weightTotal = round((float) $weeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);

        $teachingWeeks = $weeks->filter(
            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)
        );
        $weightedTeachingWeeks = $teachingWeeks->filter(
            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0
        );
        $teachingWeightTotal = round((float) $teachingWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $assessmentWeightTotal = round((float) $assessments->sum(
            fn ($assessment) => (float) ($assessment->weight ?? 0)
        ), 2);
        $nonExamAssessmentWeight = round((float) $assessments
            ->reject(fn ($assessment) => in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true))
            ->sum(fn ($assessment) => (float) ($assessment->weight ?? 0)), 2);
        $weightedWeeklySubCount = $weightedTeachingWeeks
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

        $assessedSubCount = ($subCpmkIds->isEmpty() || $assessmentIds->isEmpty())
            ? 0
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('rps_sub_cpmk_id');

        $mappedAssessmentCount = ($subCpmkIds->isEmpty() || $assessmentIds->isEmpty())
            ? 0
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('assessment_id');

        $allAssessmentsMapped = $assessments->isNotEmpty()
            && $mappedAssessmentCount === $assessments->count();

        $taskAssessments = $assessments
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation']);

        $assessmentSync = app(RpsAssessmentSyncService::class);
        $taskAlignment = $assessmentSync->taskAlignment($versionId);
        $assessmentSnapshot = $assessmentSync->snapshot($versionId);
        $assessmentBudgetMismatches = collect($assessmentSnapshot['assessment_budget_mismatches'] ?? []);
        $assessmentBudgetAligned = $assessmentBudgetMismatches->isEmpty();

        // Gunakan bobot efektif hasil snapshot sebagai sumber kebenaran untuk
        // tampilan/validator. Database lama boleh belum tersinkron sampai aksi
        // tulis berikutnya, tetapi pengguna tidak lagi melihat dua versi bobot.
        $expectedWeeklyWeights = collect($assessmentSnapshot['expected_weekly_weights'] ?? []);
        $effectiveWeeks = $weeks->map(function ($week) use ($expectedWeeklyWeights) {
            $copy = clone $week;
            $number = (int) $copy->week_number;
            if ($expectedWeeklyWeights->has($number)) {
                $copy->assessment_weight = (float) $expectedWeeklyWeights->get($number, 0);
            }
            return $copy;
        });
        $weightTotal = round((float) $effectiveWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $teachingWeeks = $effectiveWeeks->filter(
            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)
        );
        $weightedTeachingWeeks = $teachingWeeks->filter(
            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0
        );
        $teachingWeightTotal = round((float) $teachingWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')->filter()->unique()->count();
        $weeklySubBudgets = $teachingWeeks
            ->filter(fn ($week) => filled($week->rps_sub_cpmk_id ?? null))
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)
            ->map(fn ($items) => round((float) $items->sum(
                fn ($week) => (float) ($week->assessment_weight ?? 0)
            ), 2));
        $subBudgetAligned = $subCpmkIds->isNotEmpty()
            && $subCpmkIds->every(function ($subId) use ($weeklySubBudgets, $aggregateSubBudgets): bool {
                $weekly = (float) $weeklySubBudgets->get((string) $subId, 0);
                $aggregate = (float) $aggregateSubBudgets->get((string) $subId, 0);
                return $aggregate > 0 && abs($weekly - $aggregate) < 0.011;
            });

        $tasks = (int) $taskAlignment['task_total'];

        $evidenceNamesByWeek = collect($assessmentSnapshot['assessment_names_by_week'] ?? []);
        $evidenceSourcesByWeek = collect($assessmentSnapshot['assessment_evidence_source_by_week'] ?? []);
        $ambiguousEvidenceWeeks = collect($assessmentSnapshot['ambiguous_evidence_weeks'] ?? []);
        $weightedTeachingWeekNumbers = $weightedTeachingWeeks
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values();
        $coveredEvidenceWeeks = $weightedTeachingWeekNumbers
            ->filter(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $ambiguousWeightedWeeks = $ambiguousEvidenceWeeks
            ->filter(fn ($item) => $weightedTeachingWeekNumbers->contains((int) ($item['week'] ?? 0)))
            ->values();
        $missingEvidenceWeeks = $weightedTeachingWeekNumbers
            ->reject(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $weeklyEvidenceAligned = $weightedTeachingWeeks->count() === 14
            && $coveredEvidenceWeeks->count() === 14
            && $ambiguousWeightedWeeks->isEmpty();
        $ambiguousWeekNumbers = $ambiguousWeightedWeeks
            ->pluck('week')
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $missingWeekNumbers = $missingEvidenceWeeks
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();

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
            && $assessmentBudgetAligned
            && $weeklyEvidenceAligned
            && (bool) $taskAlignment['is_aligned'];

        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK terpetakan · CPL belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK · {$mappedScopeCplCount}/{$scopeCplCount} CPL terpetakan.";

        $checks = [
            [
                'key' => 'cpmk_cpl',
                'label' => 'CPMK ↔ CPL',
                'done' => $allCpmksMapped && $allScopeCplsMapped,
                'message' => $cplMessage,
                'details' => [
                    'cpmk_total' => $cpmks->count(),
                    'cpmk_mapped' => $mappedCpmkCount,
                    'cpl_scope_total' => $scopeCplCount,
                    'cpl_scope_mapped' => $mappedScopeCplCount,
                    'cpl_curriculum' => $officialCplCount,
                    'cpl_additional_lecturer' => $additionalCplCount,
                ],
            ],
            [
                'key' => 'sub_cpmk',
                'label' => 'Sub-CPMK',
                'done' => $subCpmks->isNotEmpty() && ($cpmks->isEmpty() || $coveredCpmkCount === $cpmks->count()),
                'message' => "{$subCpmks->count()} Sub-CPMK · {$coveredCpmkCount}/{$cpmks->count()} CPMK terwakili.",
            ],
            [
                'key' => 'materials',
                'label' => 'Bahan Kajian',
                'done' => $materials > 0,
                'message' => "{$materials} bahan kajian.",
            ],
            [
                'key' => 'weeks',
                'label' => '16 Pertemuan',
                'done' => $weeks->count() === 16 && $filledWeeks === 16,
                'message' => "{$filledWeeks}/16 pertemuan terisi.",
            ],
            [
                'key' => 'exam_weeks',
                'label' => 'UTS/UAS',
                'done' => $weeks->firstWhere('week_number', 8)?->exam_type === 'UTS'
                    && $weeks->firstWhere('week_number', 16)?->exam_type === 'UAS',
                'message' => 'UTS Pekan 8 · UAS Pekan 16.',
            ],
            [
                'key' => 'assessment_weight',
                'label' => 'Bobot Penilaian',
                'done' => abs($assessmentWeightTotal - 100.0) < 0.01
                    && $weightedTeachingWeeks->count() === 14
                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01
                    && $subBudgetAligned
                    && $assessmentBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan berbobot · Total {$weightTotal}%.",
                'details' => [
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'teaching_week_total' => $teachingWeightTotal,
                    'non_exam_assessment_budget' => $nonExamAssessmentWeight,
                    'weekly_total' => $weightTotal,
                    'aggregate_assessment_total' => $assessmentWeightTotal,
                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_sub_budgets' => $weeklySubBudgets->all(),
                    'aggregate_sub_budgets' => $aggregateSubBudgets->all(),
                ],
            ],
            [
                'key' => 'subcpmk_assessed',
                'label' => 'Pengukuran Sub-CPMK per Pekan',
                'done' => $subCpmks->isNotEmpty()
                    && $weightedTeachingWeeks->count() === 14
                    && $weightedWeeklySubCount === $subCpmks->count(),
                'message' => "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur · {$weightedTeachingWeeks->count()}/14 pekan.",
                'details' => [
                    'sub_cpmk_total' => $subCpmks->count(),
                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,
                    'assessment_mapping_count' => $mappedAssessmentCount,
                    'assessment_total' => $assessments->count(),
                ],
            ],
            [
                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'message' => $assessmentChainAligned
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
                'details' => [
                    'positive_non_exam_assessments' => $positiveNonExamAssessments->count(),
                    'mapped_positive_non_exam_assessments' => $positiveNonExamMappedCount,
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_evidence_aligned' => $weeklyEvidenceAligned,
                    'weekly_evidence_covered' => $coveredEvidenceWeeks->count(),
                    'weekly_evidence_ambiguous' => $ambiguousWeightedWeeks->count(),
                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
                    'rtm_unlinked_weighted' => $taskAlignment['unlinked_weighted_task_count'],
                    'rtm_due_week_subcpmk_mismatch' => $taskAlignment['due_week_subcpmk_mismatch_count'],
                    'rtm_required_missing' => $taskAlignment['missing_required_assessment_count'],
                ],
            ],
            [
                'key' => 'weekly_assessment_evidence',
                'label' => 'Bukti Penilaian per Pekan',
                'done' => $weeklyEvidenceAligned,
                'message' => $weeklyEvidenceAligned
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
            ],
            [
                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? "{$tasks} RTM · Semua sinkron."
                        : "{$tasks} RTM · {$taskAlignment['missing_required_assessment_count']} belum ada · {$taskAlignment['mapping_mismatch_count']} tidak sinkron."),
                'details' => $taskAlignment,
            ],
        ];

        $done = collect($checks)->where('done', true)->count();
        $percent = (int) round(($done / count($checks)) * 100);

        return [
            'checks' => $checks,
            'percent' => $percent,
            'is_valid' => $done === count($checks),
            'assessment_weight_total' => $weightTotal,
            'cpl_scope' => [
                'curriculum' => $officialCplCount,
                'additional' => $additionalCplCount,
                'total' => $scopeCplCount,
                'mapped' => $mappedScopeCplCount,
            ],
        ];
    }

    public function validateAndPersist(string $versionId): array
    {
        $result = $this->progress($versionId);

        DB::transaction(function () use ($versionId, $result): void {
            DB::table('obe_validation_results')
                ->where('rps_version_id', $versionId)
                ->delete();

            foreach ($result['checks'] as $check) {
                DB::table('obe_validation_results')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $versionId,
                    'rule_code' => $check['key'],
                    'severity' => $check['done'] ? 'info' : 'warning',
                    'is_passed' => $check['done'],
                    'message' => $check['message'],
                    'details' => json_encode($check),
                    'validated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $result;
    }

    public function allowedCplIds(string $courseId): Collection
    {
        return DB::table('course_cpls')
            ->where('course_id', $courseId)
            ->pluck('cpl_id');
    }
}
