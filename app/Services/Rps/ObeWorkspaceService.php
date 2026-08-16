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

        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK memiliki CPL; scope CPL RPS belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK memiliki CPL; "
                ."{$mappedScopeCplCount}/{$scopeCplCount} CPL scope terpetakan "
                ."({$officialCplCount} kurikulum + {$additionalCplCount} tambahan dosen).";

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
                'message' => "{$subCpmks->count()} Sub-CPMK; {$coveredCpmkCount}/{$cpmks->count()} CPMK terwakili.",
            ],
            [
                'key' => 'materials',
                'label' => 'Bahan Kajian',
                'done' => $materials > 0,
                'message' => "{$materials} bahan kajian tersedia.",
            ],
            [
                'key' => 'weeks',
                'label' => '16 Pertemuan',
                'done' => $weeks->count() === 16 && $filledWeeks === 16,
                'message' => "{$filledWeeks}/16 pertemuan sudah terisi.",
            ],
            [
                'key' => 'exam_weeks',
                'label' => 'UTS/UAS',
                'done' => $weeks->firstWhere('week_number', 8)?->exam_type === 'UTS'
                    && $weeks->firstWhere('week_number', 16)?->exam_type === 'UAS',
                'message' => 'UTS minggu 8 dan UAS minggu 16.',
            ],
            [
                'key' => 'assessment_weight',
                'label' => 'Bobot Penilaian',
                'done' => abs($assessmentWeightTotal - 100.0) < 0.01
                    && $weightedTeachingWeeks->count() === 14
                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01
                    && $subBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; distribusi pekan non-ujian {$teachingWeightTotal}% dari anggaran asesmen non-UTS/UAS {$nonExamAssessmentWeight}%; kesesuaian bobot per Sub-CPMK ".($subBudgetAligned ? 'sesuai' : 'belum sesuai')."; total tabel RPS {$weightTotal}% dan total asesmen agregat {$assessmentWeightTotal}%.",
                'details' => [
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'teaching_week_total' => $teachingWeightTotal,
                    'non_exam_assessment_budget' => $nonExamAssessmentWeight,
                    'weekly_total' => $weightTotal,
                    'aggregate_assessment_total' => $assessmentWeightTotal,
                    'sub_budget_aligned' => $subBudgetAligned,
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
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; {$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK tercakup oleh pekan berbobot.",
                'details' => [
                    'sub_cpmk_total' => $subCpmks->count(),
                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,
                    'assessment_mapping_count' => $mappedAssessmentCount,
                    'assessment_total' => $assessments->count(),
                ],
            ],
            [
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
