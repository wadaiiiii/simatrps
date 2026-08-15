<?php

namespace App\Services\Rps;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ObeWorkspaceService
{
    public function progress(string $versionId): array
    {
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

        $weightTotal = round((float) $assessments->sum(
            fn ($assessment) => (float) ($assessment->weight ?? 0)
        ), 2);

        $assessedSubCount = $subCpmkIds->isEmpty()
            ? 0
            : DB::table('assessment_subcpmks')
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('rps_sub_cpmk_id');

        $taskAssessments = $assessments
            ->whereIn('type', ['assignment', 'project', 'practicum']);

        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->count();

        $checks = [
            [
                'key' => 'cpmk_cpl',
                'label' => 'CPMK → CPL',
                'done' => $cpmks->isNotEmpty() && $mappedCpmkCount === $cpmks->count(),
                'message' => "{$mappedCpmkCount}/{$cpmks->count()} CPMK sudah memiliki CPL.",
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
                'label' => 'Bobot Asesmen',
                'done' => abs($weightTotal - 100.0) < 0.01,
                'message' => "Total bobot asesmen {$weightTotal}%.",
            ],
            [
                'key' => 'subcpmk_assessed',
                'label' => 'Cakupan Asesmen',
                'done' => $subCpmks->isNotEmpty() && $assessedSubCount === $subCpmks->count(),
                'message' => "{$assessedSubCount}/{$subCpmks->count()} Sub-CPMK terhubung ke asesmen.",
            ],
            [
                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || $tasks > 0,
                'message' => $taskAssessments->isEmpty()
                    ? 'Belum ada asesmen tugas/proyek yang mewajibkan RTM.'
                    : "{$tasks} RTM tersedia untuk asesmen tugas/proyek/praktikum.",
            ],
        ];

        $done = collect($checks)->where('done', true)->count();
        $percent = (int) round(($done / count($checks)) * 100);

        return [
            'checks' => $checks,
            'percent' => $percent,
            'is_valid' => $done === count($checks),
            'assessment_weight_total' => $weightTotal,
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
