<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Rps\RpsDraftService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function __invoke(RpsDraftService $service): Response
    {
        $curriculum = DB::table('curriculums')
            ->where('code', 'KUR-MAT-2025')
            ->first();

        abort_unless($curriculum, 404);

        $courses = DB::table('courses')
            ->where('curriculum_id', $curriculum->id)
            ->orderBy('semester_recommended')
            ->orderBy('name')
            ->get()
            ->map(function ($course) use ($service): array {
                $cplCodes = DB::table('course_cpls')
                    ->join('cpls', 'cpls.id', '=', 'course_cpls.cpl_id')
                    ->where('course_cpls.course_id', $course->id)
                    ->orderBy('cpls.sequence_no')
                    ->pluck('cpls.code')
                    ->all();

                $cpmkCount = DB::table('curriculum_cpmks')
                    ->where('course_id', $course->id)
                    ->count();

                return [
                    'id' => $course->id,
                    'system_code' => $course->system_code,
                    'official_code' => $course->official_code,
                    'name' => $course->name,
                    'credits' => (float) $course->credits,
                    'semester_recommended' => $course->semester_recommended,
                    'is_mandatory' => (bool) $course->is_mandatory,
                    'cpl_codes' => $cplCodes,
                    'cpmk_count' => $cpmkCount,
                    'has_syllabus' => DB::table('course_syllabi')->where('course_id', $course->id)->exists(),
                    'readiness' => $service->readiness($course, $cpmkCount),
                ];
            });

        $cpls = DB::table('cpls')
            ->where('curriculum_id', $curriculum->id)
            ->orderBy('sequence_no')
            ->get();

        $issues = DB::table('curriculum_data_issues')
            ->where('curriculum_id', $curriculum->id)
            ->where('status', 'open')
            ->orderBy('severity')
            ->orderBy('issue_code')
            ->get();

        return Inertia::render('admin/curriculum', [
            'curriculum' => $curriculum,
            'summary' => [
                'cpl' => $cpls->count(),
                'kbk' => DB::table('kbks')->where('curriculum_id', $curriculum->id)->count(),
                'courses' => $courses->count(),
                'courseCpl' => DB::table('course_cpls')
                    ->join('courses', 'courses.id', '=', 'course_cpls.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'cpmk' => DB::table('curriculum_cpmks')
                    ->join('courses', 'courses.id', '=', 'curriculum_cpmks.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'syllabi' => DB::table('course_syllabi')
                    ->join('courses', 'courses.id', '=', 'course_syllabi.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'issues' => $issues->count(),
            ],
            'cpls' => $cpls,
            'courses' => $courses,
            'issues' => $issues,
        ]);
    }
}
