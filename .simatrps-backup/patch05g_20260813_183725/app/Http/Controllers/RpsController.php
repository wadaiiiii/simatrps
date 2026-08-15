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

        $allCpls = DB::table('cpls')
            ->where('curriculum_id', $record->curriculum_id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'sequence_no']);

        $officialCplIds = DB::table('course_cpls')
            ->where('course_id', $record->course_id)
            ->pluck('cpl_id')
            ->all();

        $additionalCplIds = Schema::hasTable('rps_additional_cpls')
            ? DB::table('rps_additional_cpls')
                ->where('rps_version_id', $version->id)
                ->pluck('cpl_id')
                ->all()
            : [];

        $scopeCplIds = array_values(array_unique([
            ...$officialCplIds,
            ...$additionalCplIds,
        ]));

        $cpls = $allCpls
            ->filter(fn ($cpl) => in_array($cpl->id, $scopeCplIds, true))
            ->map(function ($cpl) use ($officialCplIds, $additionalCplIds): object {
                $cpl->scope_source = in_array($cpl->id, $officialCplIds, true)
                    ? 'curriculum'
                    : 'lecturer';
                $cpl->is_official = in_array($cpl->id, $officialCplIds, true);
                $cpl->is_additional = in_array($cpl->id, $additionalCplIds, true);

                return $cpl;
            })
            ->values();

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level', 'sequence_no', 'source_type', 'source_cpmk_id'])
            ->map(function ($cpmk): object {
                $cpmk->cpl_ids = DB::table('rps_cpmk_cpls')
                    ->where('rps_cpmk_id', $cpmk->id)
                    ->pluck('cpl_id')
                    ->all();

                return $cpmk;
            });

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

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get();

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get();

        $assessments = Schema::hasTable('assessments')
            ? DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->orderByRaw('COALESCE(week_number, 99)')
                ->orderBy('code')
                ->get()
                ->map(function ($assessment): object {
                    $assessment->sub_cpmk_ids = DB::table('assessment_subcpmks')
                        ->where('assessment_id', $assessment->id)
                        ->pluck('rps_sub_cpmk_id')
                        ->all();

                    return $assessment;
                })
            : collect();

        $tasks = Schema::hasTable('rps_tasks')
            ? DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->orderBy('code')
                ->get()
                ->map(function ($task): object {
                    $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')
                        ->where('rps_task_id', $task->id)
                        ->pluck('rps_sub_cpmk_id')
                        ->all();

                    return $task;
                })
            : collect();

        $validationHistory = Schema::hasTable('obe_validation_results')
            ? DB::table('obe_validation_results')
                ->where('rps_version_id', $version->id)
                ->orderByDesc('validated_at')
                ->limit(8)
                ->get()
            : collect();

        $aiSuggestions = Schema::hasTable('ai_suggestions')
            ? DB::table('ai_suggestions')
                ->where('rps_version_id', $version->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(function ($suggestion): object {
                    $suggestion->payload = json_decode($suggestion->suggestion_payload, true) ?: [];
                    $suggestion->context_meta = json_decode($suggestion->input_context, true) ?: [];
                    unset($suggestion->suggestion_payload, $suggestion->input_context, $suggestion->accepted_payload);

                    return $suggestion;
                })
            : collect();

        return Inertia::render('rps/show', [
            'rps' => $record,
            'version' => $version,
            'cpls' => $cpls,
            'allCpls' => $allCpls,
            'officialCplIds' => $officialCplIds,
            'additionalCplIds' => $additionalCplIds,
            'cplScopeStats' => [
                'curriculum' => count($officialCplIds),
                'additional' => count($additionalCplIds),
                'available' => $allCpls->count(),
                'scope_total' => count($scopeCplIds),
            ],
            'cpmks' => $cpmks,
            'subCpmks' => $subCpmks,
            'materials' => $materials,
            'weeks' => $weeks,
            'assessments' => $assessments,
            'tasks' => $tasks,
            'validationHistory' => $validationHistory,
            'ai' => [
                'configured' => filled(config('simatrps-ai.gemini.api_key')),
                'provider' => config('simatrps-ai.provider', 'gemini'),
                'model' => config('simatrps-ai.gemini.model', 'gemini-3.6-flash'),
                'fallback' => filled(config('simatrps-ai.groq.api_key')) ? 'groq' : null,
            ],
            'aiSuggestions' => $aiSuggestions,
            'needsAiCpmk' => $cpmks->isEmpty(),
            'progress' => Schema::hasTable('assessments')
                ? $workspace->progress($version->id)
                : ['percent' => 0, 'checks' => [], 'assessment_weight_total' => 0],
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
