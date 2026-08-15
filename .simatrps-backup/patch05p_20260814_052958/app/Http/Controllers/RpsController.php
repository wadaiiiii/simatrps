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
                'courses.prerequisite_note',
                'courses.course_type',
                'courses.kbk_id',
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

        $subById = $subCpmks->keyBy('id');
        $assessmentWeightsByWeek = $assessments
            ->filter(fn ($assessment) => filled($assessment->week_number))
            ->groupBy(fn ($assessment) => (int) $assessment->week_number)
            ->map(fn ($items) => round((float) $items->sum('weight'), 2));
        $assessmentNamesByWeek = $assessments
            ->filter(fn ($assessment) => filled($assessment->week_number))
            ->groupBy(fn ($assessment) => (int) $assessment->week_number)
            ->map(fn ($items) => $items->pluck('name')->filter()->implode('; '));

        $weeks = $weeks->map(function ($week) use (
            $subById,
            $assessmentWeightsByWeek,
            $assessmentNamesByWeek
        ): object {
            $sub = $week->rps_sub_cpmk_id
                ? $subById->get($week->rps_sub_cpmk_id)
                : null;

            $week->sub_cpmk_code = $sub?->code;
            $week->sub_cpmk_description = $sub?->description;
            $storedWeight = $week->assessment_weight ?? null;
            $week->assessment_weight = $storedWeight !== null
                ? (float) $storedWeight
                : (float) ($assessmentWeightsByWeek->get((int) $week->week_number, 0));
            $week->assessment_names = $assessmentNamesByWeek->get((int) $week->week_number, '');

            return $week;
        });

        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $record->course_id)
            ->orderBy('source_entry_no')
            ->first();

        $bibliography = $this->parseBibliography(
            (string) ($syllabus?->reference_text ?? '')
        );

        $kbkName = null;

        if (
            filled($record->kbk_id ?? null)
            && Schema::hasTable('kbks')
        ) {
            $kbkName = DB::table('kbks')
                ->where('id', $record->kbk_id)
                ->value('name');
        }

        $storedMeta = Schema::hasTable('rps_document_meta')
            ? DB::table('rps_document_meta')
                ->where('rps_version_id', $version->id)
                ->first()
            : null;

        $documentMeta = [
            'course_cluster' => $storedMeta?->course_cluster ?: $kbkName,
            'prepared_date' => $storedMeta?->prepared_date
                ?: optional($record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : now())->format('Y-m-d'),
            'developer_name' => $storedMeta?->developer_name ?: $request->user()->name,
            'coordinator_name' => $storedMeta?->coordinator_name,
            'head_program_name' => $storedMeta?->head_program_name,
            'lecturer_names' => $storedMeta?->lecturer_names ?: $request->user()->name,
            'software_media' => $storedMeta?->software_media ?: '-',
            'hardware_media' => $storedMeta?->hardware_media ?: '-',
            'prerequisite_text' => $storedMeta?->prerequisite_text
                ?: ($syllabus?->source_prerequisite_text ?: ($record->prerequisite_note ?? '-')),
            'description_short' => $storedMeta?->description_short
                ?: ($version->description_short ?: ($syllabus?->description ?? '')),
        ];

        $courseSummary = [
            'description' => $documentMeta['description_short'],
            'prerequisite' => $documentMeta['prerequisite_text'],
            'lecturer' => $documentMeta['lecturer_names'],
        ];

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
            'bibliography' => $bibliography,
            'courseSummary' => $courseSummary,
            'documentMeta' => $documentMeta,
            'weeks' => $weeks,
            'assessments' => $assessments,
            'tasks' => $tasks,
            'validationHistory' => $validationHistory,
            'ai' => [
                'configured' => collect([
                    config('simatrps-ai.groq.api_key'),
                    config('simatrps-ai.mistral.api_key'),
                    config('simatrps-ai.cohere.api_key'),
                ])->filter()->isNotEmpty(),
                'provider' => filled(config('simatrps-ai.groq.api_key'))
                    ? 'groq'
                    : (filled(config('simatrps-ai.mistral.api_key'))
                        ? 'mistral'
                        : 'cohere'),
                'model' => filled(config('simatrps-ai.groq.api_key'))
                    ? config('simatrps-ai.groq.model', 'openai/gpt-oss-20b')
                    : (filled(config('simatrps-ai.mistral.api_key'))
                        ? config('simatrps-ai.mistral.model', 'mistral-small-latest')
                        : config('simatrps-ai.cohere.model', 'command-a-plus-05-2026')),
                'fallbacks' => collect([
                    'mistral' => filled(config('simatrps-ai.mistral.api_key')),
                    'cohere' => filled(config('simatrps-ai.cohere.api_key')),
                ])->filter()->keys()->values()->all(),
            ],
            'aiSuggestions' => $aiSuggestions,
            'needsAiCpmk' => $cpmks->isEmpty(),
            'progress' => Schema::hasTable('assessments')
                ? $workspace->progress($version->id)
                : ['percent' => 0, 'checks' => [], 'assessment_weight_total' => 0],
        ]);
    }

    private function parseBibliography(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', $text) ?: [];

        if (count(array_filter($parts, fn ($line) => trim($line) !== '')) <= 1) {
            $parts = preg_split('/\s*;\s*(?=[A-Z0-9])/u', $text) ?: [$text];
        }

        $category = 'utama';
        $entries = [];

        foreach ($parts as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(pustaka\s*)?utama\s*:?\s*$/i', $line)) {
                $category = 'utama';
                continue;
            }

            if (preg_match('/^(pendukung|tambahan)\s*:?\s*$/i', $line)) {
                $category = 'pendukung';
                continue;
            }

            $line = preg_replace('/^\s*(?:\d+[\.\)]|[-•])\s*/u', '', $line) ?: $line;

            if ($line === '') {
                continue;
            }

            $entries[] = [
                'category' => $category,
                'text' => $line,
            ];
        }

        return collect($entries)
            ->unique(fn ($item) => $item['text'])
            ->values()
            ->map(fn ($item, $index) => [
                'number' => $index + 1,
                'code' => '['.($index + 1).']',
                'category' => $item['category'],
                'text' => $item['text'],
            ])
            ->all();
    }

    private function defaultAcademicYear(): string
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');
        $start = $month >= 7 ? $year : $year - 1;

        return $start.'/'.($start + 1);
    }
}
