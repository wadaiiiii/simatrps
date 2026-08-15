<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;

class RpsAiContextService
{
    public function build(object $rps, object $version): array
    {
        $course = DB::table('courses')->where('id', $rps->course_id)->first();
        $curriculum = DB::table('curriculums')->where('id', $rps->curriculum_id)->first();

        $officialCplIds = DB::table('course_cpls')
            ->where('course_id', $rps->course_id)
            ->pluck('cpl_id')
            ->all();

        $additionalCplIds = DB::table('rps_additional_cpls')
            ->where('rps_version_id', $version->id)
            ->pluck('cpl_id')
            ->all();

        $scopeCplIds = array_values(array_unique([
            ...$officialCplIds,
            ...$additionalCplIds,
        ]));

        $cpls = DB::table('cpls')
            ->whereIn('id', $scopeCplIds)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description'])
            ->map(function ($cpl) use ($officialCplIds): array {
                return [
                    'code' => $cpl->code,
                    'description' => $cpl->description,
                    'source' => in_array($cpl->id, $officialCplIds, true)
                        ? 'curriculum'
                        : 'lecturer_addition',
                ];
            })
            ->values()
            ->all();

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level', 'source_type'])
            ->map(function ($cpmk): array {
                $mapped = DB::table('rps_cpmk_cpls')
                    ->join('cpls', 'cpls.id', '=', 'rps_cpmk_cpls.cpl_id')
                    ->where('rps_cpmk_cpls.rps_cpmk_id', $cpmk->id)
                    ->orderBy('cpls.sequence_no')
                    ->pluck('cpls.code')
                    ->all();

                return [
                    'code' => $cpmk->code,
                    'description' => $cpmk->description,
                    'bloom_level' => $cpmk->bloom_level,
                    'source_type' => $cpmk->source_type,
                    'cpl_codes' => $mapped,
                ];
            })
            ->all();

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level', 'source_type'])
            ->map(function ($sub): array {
                $parent = DB::table('rps_cpmk_subcpmks')
                    ->join('rps_cpmks', 'rps_cpmks.id', '=', 'rps_cpmk_subcpmks.rps_cpmk_id')
                    ->where('rps_cpmk_subcpmks.rps_sub_cpmk_id', $sub->id)
                    ->value('rps_cpmks.code');

                return [
                    'code' => $sub->code,
                    'parent_cpmk_code' => $parent,
                    'description' => $sub->description,
                    'bloom_level' => $sub->bloom_level,
                    'source_type' => $sub->source_type,
                ];
            })
            ->all();

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->pluck('title')
            ->all();

        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $rps->course_id)
            ->orderBy('source_entry_no')
            ->first();

        $syllabusItems = DB::table('course_syllabus_items')
            ->where('course_id', $rps->course_id)
            ->orderBy('sequence_no')
            ->pluck('title')
            ->all();

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get()
            ->map(function ($week): array {
                $subCode = $week->rps_sub_cpmk_id
                    ? DB::table('rps_sub_cpmks')->where('id', $week->rps_sub_cpmk_id)->value('code')
                    : null;

                return [
                    'week_number' => (int) $week->week_number,
                    'exam_type' => $week->exam_type,
                    'sub_cpmk_code' => $subCode,
                    'material' => $week->material_text,
                    'learning_method' => $week->learning_method,
                    'learning_activity' => $week->learning_activity,
                    'assessment_indicator' => $week->assessment_indicator,
                    'assessment_criteria' => $week->assessment_criteria,
                    'assessment_method' => $week->assessment_method,
                    'reference' => $week->reference_text,
                ];
            })
            ->all();

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->get()
            ->map(function ($assessment): array {
                $subCodes = DB::table('assessment_subcpmks')
                    ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
                    ->where('assessment_subcpmks.assessment_id', $assessment->id)
                    ->pluck('rps_sub_cpmks.code')
                    ->all();

                return [
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'week_number' => $assessment->week_number,
                    'weight' => $assessment->weight,
                    'sub_cpmk_codes' => $subCodes,
                ];
            })
            ->all();

        return [
            'curriculum' => [
                'code' => $curriculum?->code,
                'name' => $curriculum?->name,
                'year' => $curriculum?->year,
            ],
            'course' => [
                'system_code' => $course?->system_code,
                'official_code' => $course?->official_code,
                'name' => $course?->name,
                'credits' => $course?->credits,
                'semester_recommended' => $course?->semester_recommended,
                'has_practicum' => (bool) ($course?->has_practicum ?? false),
            ],
            'period' => [
                'academic_year' => $rps->academic_year,
                'academic_semester' => $rps->academic_semester,
            ],
            'cpl_scope' => $cpls,
            'cpmks' => $cpmks,
            'sub_cpmks' => $subCpmks,
            'materials' => $materials,
            'master_syllabus' => [
                'description' => $syllabus?->description,
                'items' => $syllabusItems,
                'references' => $syllabus?->reference_text,
            ],
            'weekly_plan' => $weeks,
            'assessments' => $assessments,
            'constraints' => [
                'cpl_is_locked_to_current_rps_scope' => true,
                'do_not_create_new_cpl' => true,
                'uts_week' => 8,
                'uas_week' => 16,
                'teaching_weeks' => [1,2,3,4,5,6,7,9,10,11,12,13,14,15],
                'lecturer_must_review_before_apply' => true,
            ],
        ];
    }
}
