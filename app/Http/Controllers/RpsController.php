<?php

namespace App\Http\Controllers;

use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsDraftService;
use App\Services\Rps\RpsAssessmentSyncService;
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
                DB::raw('CAST(courses.credits AS INTEGER) as credits'),
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
                    'credits' => (int) round((float) $course->credits),
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
        ObeWorkspaceService $workspace,
        AiRpsProviderService $aiProvider,
        RpsAssessmentSyncService $assessmentSync
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
                DB::raw('CAST(courses.credits AS INTEGER) as credits'),
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

        // Safe, idempotent repair only: normalize explicit AI assessment scope
        // and remove/remap stale generated RTM. It never creates a replacement
        // RTM while the page is merely being opened.
        $assessmentSync->repairGeneratedArtifacts($version->id);

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

        // Halaman RPS bersifat read-only terhadap RTM. RTM yang hilang/duplikat
        // diperbaiki hanya melalui aksi eksplisit (asesmen, RTM, atau sinkronisasi),
        // bukan saat halaman sekadar dibuka. Ini mencegah RTM yang dihapus
        // muncul kembali secara otomatis.

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get();

        $teachingWeekNumbers = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $meetingPlanReady = $weeks
            ->filter(fn ($week) =>
                in_array((int) $week->week_number, $teachingWeekNumbers, true)
                && filled($week->rps_sub_cpmk_id ?? null)
                && str_starts_with((string) ($week->source_type ?? ''), 'manual_allocation')
            )
            ->count() === count($teachingWeekNumbers);

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
            ->filter(fn ($assessment) =>
                filled($assessment->week_number)
                && in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true)
            )
            ->groupBy(fn ($assessment) => (int) $assessment->week_number)
            ->map(fn ($items) => round(
                (float) $items->sum(
                    fn ($assessment) => (float) ($assessment->weight ?? 0)
                ),
                2
            ));
        $assessmentSyncSnapshot = $assessmentSync->snapshot($version->id);
        $expectedWeeklyWeights = collect(
            $assessmentSyncSnapshot['expected_weekly_weights'] ?? []
        );
        $assessmentNamesByWeek = collect(
            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []
        );
        $assessmentOwnerByWeek = collect($assessmentSyncSnapshot['assessment_owner_by_week'] ?? []);
        $assessmentOwnerNameByWeek = collect($assessmentSyncSnapshot['assessment_owner_name_by_week'] ?? []);
        $assessmentGroupBudgetByWeek = collect($assessmentSyncSnapshot['assessment_group_budget_by_week'] ?? []);
        $assessmentGroupWeekCountByWeek = collect($assessmentSyncSnapshot['assessment_group_week_count_by_week'] ?? []);
        $assessmentTotalBudgetByWeek = collect($assessmentSyncSnapshot['assessment_total_budget_by_week'] ?? []);
        $weightOverrides = collect(
            $assessmentSyncSnapshot['weight_overrides'] ?? []
        );

        $weeks = $weeks->map(function ($week) use (
            $subById,
            $assessmentWeightsByWeek,
            $expectedWeeklyWeights,
            $assessmentNamesByWeek,
            $assessmentOwnerByWeek,
            $assessmentOwnerNameByWeek,
            $assessmentGroupBudgetByWeek,
            $assessmentGroupWeekCountByWeek,
            $assessmentTotalBudgetByWeek,
            $weightOverrides
        ): object {
            $sub = $week->rps_sub_cpmk_id
                ? $subById->get($week->rps_sub_cpmk_id)
                : null;

            $week->sub_cpmk_code = $sub?->code;
            $week->sub_cpmk_description = $sub?->description;

            foreach (['assessment_criteria', 'learning_activity', 'student_assignment', 'online_activity'] as $field) {
                $week->{$field} = $this->normalizeWeekSubCpmkNarrative(
                    $week->{$field} ?? null,
                    $sub?->code
                );
            }

            $storedWeight = $week->assessment_weight ?? null;
            $weekNumber = (int) $week->week_number;

            // Untuk 14 pekan pembelajaran, assessment_weight yang tersimpan
            // pada rps_weekly_plans adalah distribusi bobot pengukuran per pekan.
            // UTS/UAS tetap mengikuti bobot asesmen sistem dan disinkronkan ke
            // pekan 8/16 agar kedua representasi konsisten.
            $week->assessment_weight = in_array($weekNumber, [8, 16], true)
                && $assessmentWeightsByWeek->has($weekNumber)
                    ? (float) $assessmentWeightsByWeek->get($weekNumber, 0)
                    : ($expectedWeeklyWeights->has($weekNumber)
                        ? (float) $expectedWeeklyWeights->get($weekNumber, 0)
                        : (float) ($storedWeight ?? 0));

            $week->assessment_names = $assessmentNamesByWeek->get($weekNumber, '');
            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $ownerId = (string) $assessmentOwnerByWeek->get($weekNumber, '');
            $ownerName = (string) $assessmentOwnerNameByWeek->get($weekNumber, '');
            $groupBudget = (float) $assessmentGroupBudgetByWeek->get($weekNumber, 0);
            $groupWeekCount = (int) $assessmentGroupWeekCountByWeek->get($weekNumber, 0);
            $assessmentTotalBudget = (float) $assessmentTotalBudgetByWeek->get($weekNumber, 0);
            $isTeachingWeek = ! in_array($weekNumber, [8, 16], true);

            if ($isTeachingWeek) {
                $week->assessment_method = $ownerName !== '' ? $ownerName : null;
            }

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
                && $weightOverrides->has($weekNumber);

            return $week;
        });

        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $record->course_id)
            ->orderBy('source_entry_no')
            ->first();

        $masterDescription = trim((string) ($syllabus?->description ?? ''));
        $masterReferenceText = trim((string) ($syllabus?->reference_text ?? ''));
        $masterReferenceGroups = $this->splitReferenceGroups($masterReferenceText);

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

        $effectiveDescription = trim((string) (
            $storedMeta?->description_short
                ?: ($version->description_short ?: $masterDescription)
        ));

        // Pustaka draft RPS sengaja kosong saat pertama kali dibuat.
        // Pustaka master tetap tersedia melalui tombol Ambil dari Kurikulum.
        $effectivePrimaryReferenceText = trim((string) (
            $storedMeta?->reference_text ?? ''
        ));

        $effectiveSupportingReferenceText = trim((string) (
            $storedMeta?->supporting_reference_text ?? ''
        ));

        $combinedReferenceText = trim(
            "Utama:
".$effectivePrimaryReferenceText
            .($effectiveSupportingReferenceText !== ''
                ? "
Pendukung:
".$effectiveSupportingReferenceText
                : '')
        );

        $bibliography = $this->parseBibliography($combinedReferenceText);
        $bibliographyCount = count($bibliography);
        $weeks = $weeks->map(function ($week) use ($bibliographyCount): object {
            $week->reference_text = $this->filterWeeklyReferenceCodesForDisplay(
                (string) ($week->reference_text ?? ''),
                $bibliographyCount
            );
            return $week;
        });

        $documentMeta = [
            'course_cluster' => $storedMeta?->course_cluster ?: $kbkName,
            'prepared_date' => $storedMeta?->prepared_date
                ?: optional($record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : now())->format('Y-m-d'),
            'published_date' => $storedMeta?->published_date
                ?: now()->toDateString(),
            'developer_name' => $storedMeta?->developer_name ?: $request->user()->name,
            'coordinator_name' => $storedMeta?->coordinator_name,
            'head_program_name' => $storedMeta?->head_program_name,
            'lecturer_names' => $storedMeta?->lecturer_names ?: $request->user()->name,
            'software_media' => $storedMeta?->software_media ?: '-',
            'hardware_media' => $storedMeta?->hardware_media ?: '-',
            'prerequisite_text' => $storedMeta?->prerequisite_text
                ?: ($syllabus?->source_prerequisite_text ?: ($record->prerequisite_note ?? '-')),
            'description_short' => $effectiveDescription,
            'reference_text' => $effectivePrimaryReferenceText,
            'supporting_reference_text' => $effectiveSupportingReferenceText,
        ];

        $documentInfoRequiredFields = [
            'course_cluster',
            'prepared_date',
            'published_date',
            'developer_name',
            'coordinator_name',
            'head_program_name',
            'lecturer_names',
            'software_media',
            'hardware_media',
            'prerequisite_text',
            'description_short',
        ];
        $documentInfoReady = $storedMeta !== null
            && collect($documentInfoRequiredFields)->every(
                fn (string $field) => filled($storedMeta->{$field} ?? null)
            );

        $masterSyllabus = [
            'description' => $masterDescription,
            'reference_text' => $masterReferenceGroups['utama'] ?? $masterReferenceText,
            'supporting_reference_text' => $masterReferenceGroups['pendukung'] ?? '',
            'prerequisite_text' => trim((string) ($syllabus?->source_prerequisite_text ?? '')),
        ];

        $courseSummary = [
            'description' => $documentMeta['description_short'],
            'prerequisite' => $documentMeta['prerequisite_text'],
            'lecturer' => $documentMeta['lecturer_names'],
        ];

        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);

        $tasks = Schema::hasTable('rps_tasks')
            ? DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->orderBy('code')
                ->get()
                ->filter(function ($task) use ($assessmentById): bool {
                    // RTM manual boleh tidak memiliki asesmen induk. RTM hasil
                    // generator yang masih menunjuk asesmen yang sudah hilang
                    // disembunyikan sampai relasinya diperbaiki oleh sinkronisasi.
                    if (! $this->isGeneratedRtm($task)) {
                        return true;
                    }

                    $assessmentId = filled($task->assessment_id ?? null)
                        ? (string) $task->assessment_id
                        : null;

                    return $assessmentId !== null && $assessmentById->has($assessmentId);
                })
                ->values()
                ->map(function ($task): object {
                    // Satu RTM dapat mengukur satu atau lebih Sub-CPMK.
                    // Pekan pengumpulan hanya jadwal; jangan menimpa cakupan
                    // RTM dengan Sub-CPMK yang kebetulan aktif pada pekan itu.
                    $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')
                        ->where('rps_task_id', $task->id)
                        ->pluck('rps_sub_cpmk_id')
                        ->all();

                    return $task;
                })
            : collect();

        $simulationScores = Schema::hasTable('rps_weekly_simulations')
            ? DB::table('rps_weekly_simulations')
                ->where('rps_version_id', $version->id)
                ->pluck('score', 'week_number')
                ->map(fn ($score) => $score === null ? null : (float) $score)
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
            'documentInfoReady' => $documentInfoReady,
            'masterSyllabus' => $masterSyllabus,
            'weeks' => $weeks,
            'meetingPlanReady' => $meetingPlanReady,
            'assessments' => $assessments,
            'tasks' => $tasks,
            'simulationScores' => $simulationScores,
            'validationHistory' => $validationHistory,
            'ai' => [
                'configured' => count($aiProvider->configuredProviderNames()) > 0,
                'provider' => $aiProvider->primaryProvider(),
                'model' => $aiProvider->displayModel(),
                'fallbacks' => array_values(array_slice(
                    $aiProvider->configuredProviderNames(),
                    1
                )),
            ],
            'aiSuggestions' => $aiSuggestions,
            'needsAiCpmk' => $cpmks->isEmpty(),
            'progress' => Schema::hasTable('assessments')
                ? $workspace->progress($version->id)
                : ['percent' => 0, 'checks' => [], 'assessment_weight_total' => 0],
        ]);
    }

    private function isGeneratedRtm(object $task): bool
    {
        $sourceType = strtolower(trim((string) ($task->source_type ?? '')));

        if (in_array($sourceType, ['assessment_sync', 'ai_accepted', 'ai_generated', 'automation'], true)) return true;
        if ($sourceType === 'manual') return false;
        if ($sourceType !== '' && $sourceType !== 'legacy') return false;

        $purpose = mb_strtolower(trim((string) ($task->purpose ?? '')));
        $instructions = mb_strtolower(trim((string) ($task->instructions ?? '')));
        $output = mb_strtolower(trim((string) ($task->expected_output ?? '')));
        $signals = 0;

        if (str_starts_with($purpose, 'mengukur ketercapaian sub-cpmk melalui')) $signals++;
        if (str_starts_with($instructions, 'kerjakan ')
            && str_contains($instructions, 'sesuai arahan dosen')) $signals++;
        if (str_starts_with($output, 'luaran ')
            && str_contains($output, 'sesuai ketentuan asesmen')) $signals++;

        return $signals >= 2;
    }

    private function normalizeWeekSubCpmkNarrative(mixed $value, mixed $currentCode): mixed
    {
        if (! is_string($value) || trim($value) === '' || ! is_string($currentCode) || trim($currentCode) === '') {
            return $value;
        }

        $pattern = '/Sub[\s\-‐‑‒–—]*CPMK[\s\-‐‑‒–—]*\d+/iu';
        preg_match_all($pattern, $value, $matches);
        $codes = collect($matches[0] ?? [])
            ->map(fn ($match) => $this->normalizeRtmLabel((string) $match))
            ->filter()->unique()->values();

        if ($codes->count() !== 1) return $value;
        if ($codes->first() === $this->normalizeRtmLabel($currentCode)) return $value;

        return preg_replace($pattern, trim($currentCode), $value) ?? $value;
    }

    private function normalizeRtmLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function filterWeeklyReferenceCodesForDisplay(string $value, int $entryCount): ?string
    {
        if ($entryCount <= 0 || trim($value) === '') return null;
        preg_match_all('/\[\s*(\d+)\s*\]/', $value, $matches);
        if (($matches[1] ?? []) === []) return $value;

        $codes = collect($matches[1])
            ->map(fn ($number) => (int) $number)
            ->filter(fn ($number) => $number >= 1 && $number <= $entryCount)
            ->unique()->sort()->map(fn ($number) => '['.$number.']')->implode(', ');

        return $codes !== '' ? $codes : null;
    }

    private function splitReferenceGroups(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['utama' => '', 'pendukung' => ''];
        }

        $normalized = preg_replace(
            '/\s+(?=[a-z]\.\s+[A-Z0-9])/u',
            "\n",
            $text
        ) ?? $text;

        $lines = preg_split('/\r\n|\r|\n/', $normalized) ?: [$normalized];
        $category = 'utama';
        $groups = ['utama' => [], 'pendukung' => []];

        foreach ($lines as $line) {
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

            $line = preg_replace(
                '/^\s*(?:(?:\d+|[a-z])[\.\)]|[-•])\s*/iu',
                '',
                $line
            ) ?: $line;

            if ($line !== '') {
                $groups[$category][] = $line;
            }
        }

        return [
            'utama' => implode("\n", array_values(array_unique($groups['utama']))),
            'pendukung' => implode("\n", array_values(array_unique($groups['pendukung']))),
        ];
    }

    private function parseBibliography(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // Master kurikulum banyak menyimpan pustaka dalam satu baris:
        // "a. Buku A. b. Buku B. c. Buku C."
        $normalized = preg_replace(
            '/\s+(?=[a-z]\.\s+[A-Z0-9])/u',
            "\n",
            $text
        ) ?? $text;

        $parts = preg_split('/\r\n|\r|\n/', $normalized) ?: [$normalized];

        if (count(array_filter($parts, fn ($line) => trim($line) !== '')) <= 1) {
            $parts = preg_split('/\s*;\s*(?=[A-Z0-9])/u', $normalized) ?: [$normalized];
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

            $line = preg_replace(
                '/^\s*(?:(?:\d+|[a-z])[\.\)]|[-•])\s*/iu',
                '',
                $line
            ) ?: $line;

            if ($line === '') {
                continue;
            }

            $entries[] = [
                'category' => $category,
                'text' => $line,
            ];
        }

        return collect($entries)
            ->unique(fn ($item) => mb_strtolower($item['text']))
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
