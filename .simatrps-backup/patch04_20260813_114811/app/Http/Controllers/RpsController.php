<?php

namespace App\Http\Controllers;

use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RpsController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->where('rps.owner_id', $request->user()->id)
            ->orderByDesc('rps.updated_at')
            ->get([
                'rps.id',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.status',
                'rps.updated_at',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
                'courses.credits',
                'courses.semester_recommended',
            ]);

        return Inertia::render('rps/index', ['rpsRows' => $rows]);
    }

    public function create(RpsDraftService $service): Response
    {
        $curriculums = DB::table('curriculums')
            ->where('status', 'active')
            ->orderByDesc('year')
            ->get(['id', 'code', 'name', 'year', 'effective_academic_year']);

        $courses = DB::table('courses')
            ->where('is_active', true)
            ->orderBy('semester_recommended')
            ->orderBy('name')
            ->get()
            ->map(function ($course) use ($service): array {
                $cpls = DB::table('course_cpls')
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
                    'curriculum_id' => $course->curriculum_id,
                    'system_code' => $course->system_code,
                    'official_code' => $course->official_code,
                    'name' => $course->name,
                    'credits' => (float) $course->credits,
                    'semester_recommended' => $course->semester_recommended,
                    'has_practicum' => (bool) $course->has_practicum,
                    'official_cpl_codes' => $cpls,
                    'official_cpmk_count' => $cpmkCount,
                    'has_master_syllabus' => DB::table('course_syllabi')
                        ->where('course_id', $course->id)
                        ->exists(),
                    'generator_readiness' => $service->readiness($course, $cpmkCount),
                ];
            });

        return Inertia::render('rps/create', [
            'curriculums' => $curriculums,
            'courses' => $courses,
            'defaultAcademicYear' => $this->defaultAcademicYear(),
        ]);
    }

    public function store(Request $request, RpsDraftService $service): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'uuid', Rule::exists('courses', 'id')->where('is_active', true)],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'academic_semester' => ['required', Rule::in(['Ganjil', 'Genap', 'Pendek'])],
        ]);

        $result = $service->create(
            $validated['course_id'],
            $request->user()->id,
            $validated['academic_year'],
            $validated['academic_semester']
        );

        return redirect()
            ->route('rps.show', $result['rps_id'])
            ->with('success', $result['reused']
                ? 'Draft RPS yang masih aktif dibuka kembali.'
                : 'Draft RPS berhasil dibuat dari master kurikulum.');
    }

    public function show(
        Request $request,
        string $rps,
        ObeWorkspaceService $workspace
    ): Response {
        $record = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->join('curriculums', 'curriculums.id', '=', 'rps.curriculum_id')
            ->where('rps.id', $rps)
            ->first([
                'rps.*',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
                'courses.credits',
                'courses.semester_recommended',
                'courses.has_practicum',
                'courses.verification_status',
                'courses.code_status',
                'curriculums.name as curriculum_name',
            ]);

        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();

        abort_unless($version, 404);

        $cpls = DB::table('course_cpls')
            ->join('cpls', 'cpls.id', '=', 'course_cpls.cpl_id')
            ->where('course_cpls.course_id', $record->course_id)
            ->orderBy('cpls.sequence_no')
            ->get([
                'cpls.id',
                'cpls.code',
                'cpls.description',
            ]);

        $hasPatch03 = Schema::hasTable('rps_cpmk_cpls')
            && Schema::hasTable('rps_sub_cpmks')
            && Schema::hasTable('rps_materials')
            && Schema::hasColumn('rps_weekly_plans', 'rps_sub_cpmk_id');

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get([
                'id',
                'code',
                'description',
                'sequence_no',
                'source_type',
            ])
            ->map(function ($cpmk) use ($hasPatch03): object {
                $cpmk->cpl_ids = $hasPatch03
                    ? DB::table('rps_cpmk_cpls')
                        ->where('rps_cpmk_id', $cpmk->id)
                        ->pluck('cpl_id')
                        ->all()
                    : [];

                return $cpmk;
            });

        $subCpmks = collect();

        if ($hasPatch03) {
            $subCpmks = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->orderBy('sequence_no')
                ->get()
                ->map(function ($sub): object {
                    $sub->cpmk_ids = DB::table('rps_cpmk_subcpmks')
                        ->where('rps_sub_cpmk_id', $sub->id)
                        ->pluck('rps_cpmk_id')
                        ->all();

                    return $sub;
                });
        }

        $materials = $hasPatch03
            ? DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->orderBy('sequence_no')
                ->get()
            : collect();

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get();

        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $record->course_id)
            ->orderBy('source_entry_no')
            ->first();

        $progress = $hasPatch03
            ? $workspace->progress($version->id)
            : [
                'checks' => [],
                'percent' => 0,
            ];

        return Inertia::render('rps/show', [
            'rps' => $record,
            'version' => $version,
            'cpls' => $cpls,
            'cpmks' => $cpmks,
            'subCpmks' => $subCpmks,
            'materials' => $materials,
            'weeks' => $weeks,
            'syllabus' => $syllabus,
            'needsAiCpmk' => $cpmks->isEmpty(),
            'progress' => $progress,
            'patch03Ready' => $hasPatch03,
        ]);
    }

    private function defaultAcademicYear(): string
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');
        $start = $month >= 7 ? $year : $year - 1;

        return $start.'/'.($start + 1);
    }
}
