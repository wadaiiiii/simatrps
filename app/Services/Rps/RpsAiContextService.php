<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RpsAiContextService
{
    public function build(object $rps, object $version, ?string $suggestionType = null): array
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
                    'code' => $assessment->code,
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'week_number' => $assessment->week_number,
                    'description' => $assessment->description,
                    'weight' => $assessment->weight,
                    'source_type' => $assessment->source_type,
                    'sub_cpmk_codes' => $subCodes,
                ];
            })
            ->all();

        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->orderBy('due_week')
            ->orderBy('code')
            ->get()
            ->map(function ($task): array {
                $subCodes = DB::table('rps_task_subcpmks')
                    ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'rps_task_subcpmks.rps_sub_cpmk_id')
                    ->where('rps_task_subcpmks.rps_task_id', $task->id)
                    ->orderBy('rps_sub_cpmks.sequence_no')
                    ->pluck('rps_sub_cpmks.code')
                    ->all();

                $assessment = filled($task->assessment_id ?? null)
                    ? DB::table('assessments')->where('id', $task->assessment_id)->first(['code', 'name'])
                    : null;

                return [
                    'code' => $task->code,
                    'title' => $task->title,
                    'type' => $task->type,
                    'assessment_code' => $assessment?->code,
                    'assessment_name' => $assessment?->name,
                    'due_week' => $task->due_week,
                    'purpose' => $task->purpose,
                    'instructions' => $task->instructions,
                    'expected_output' => $task->expected_output,
                    'source_type' => $task->source_type,
                    'sub_cpmk_codes' => $subCodes,
                ];
            })
            ->all();

        $documentMeta = null;

        if (Schema::hasTable('rps_document_meta')) {
            $documentMeta = DB::table('rps_document_meta')
                ->where('rps_version_id', $version->id)
                ->first(['reference_text', 'supporting_reference_text']);
        }

        $full = [
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
            'document' => [
                'reference_text' => $documentMeta?->reference_text,
                'supporting_reference_text' => $documentMeta?->supporting_reference_text,
            ],
            'assessments' => $assessments,
            'tasks' => $tasks,
            'constraints' => [
                'cpl_is_locked_to_current_rps_scope' => true,
                'do_not_create_new_cpl' => true,
                'uts_week' => 8,
                'uas_week' => 16,
                'teaching_weeks' => [1,2,3,4,5,6,7,9,10,11,12,13,14,15],
                'lecturer_must_review_before_apply' => true,
            ],
        ];

        return $this->compactForType($full, $suggestionType);
    }

    public function buildWeekContext(
        object $rps,
        object $version,
        int $week,
        string $targetSubCpmkCode
    ): array {
        $course = DB::table('courses')->where('id', $rps->course_id)->first();

        $targetSub = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->where('code', $targetSubCpmkCode)
            ->first(['id', 'code', 'description', 'bloom_level']);

        abort_unless($targetSub, 404);

        $parentCpmk = DB::table('rps_cpmk_subcpmks')
            ->join('rps_cpmks', 'rps_cpmks.id', '=', 'rps_cpmk_subcpmks.rps_cpmk_id')
            ->where('rps_cpmk_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->first([
                'rps_cpmks.code',
                'rps_cpmks.description',
                'rps_cpmks.bloom_level',
            ]);

        $parentCode = $parentCpmk?->code;

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->limit(20)
            ->pluck('title')
            ->all();

        $targetMaterials = [];

        if (Schema::hasTable('rps_material_subcpmks')) {
            $targetMaterials = DB::table('rps_material_subcpmks')
                ->join('rps_materials', 'rps_materials.id', '=', 'rps_material_subcpmks.rps_material_id')
                ->where('rps_material_subcpmks.rps_sub_cpmk_id', $targetSub->id)
                ->where('rps_materials.rps_version_id', $version->id)
                ->orderBy('rps_materials.sequence_no')
                ->limit(10)
                ->pluck('rps_materials.title')
                ->all();
        }

        if ($targetMaterials === [] && Schema::hasColumn('rps_materials', 'rps_sub_cpmk_id')) {
            $targetMaterials = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->where('rps_sub_cpmk_id', $targetSub->id)
                ->orderBy('sequence_no')
                ->limit(10)
                ->pluck('title')
                ->all();
        }

        if ($targetMaterials === [] && $materials !== []) {
            $targetTokens = $this->semanticTokens((string) $targetSub->description);
            $ranked = collect($materials)
                ->map(function (string $title) use ($targetTokens): array {
                    return [
                        'title' => $title,
                        'score' => count(array_intersect(
                            $targetTokens,
                            $this->semanticTokens($title)
                        )),
                    ];
                })
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sortByDesc('score')
                ->pluck('title')
                ->take(6)
                ->values()
                ->all();

            if ($ranked !== []) {
                $targetMaterials = $ranked;
            }
        }

        $targetAssessments = DB::table('assessment_subcpmks')
            ->join('assessments', 'assessments.id', '=', 'assessment_subcpmks.assessment_id')
            ->where('assessment_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->where('assessments.rps_version_id', $version->id)
            ->orderByRaw('COALESCE(assessments.week_number, 99)')
            ->get([
                'assessments.name',
                'assessments.type',
                'assessments.week_number',
                'assessments.description',
                'assessments.weight',
            ])
            ->map(fn ($assessment): array => [
                'name' => $assessment->name,
                'type' => $assessment->type,
                'week_number' => $assessment->week_number,
                'description' => $this->clip($assessment->description, 300),
                'weight' => $assessment->weight,
            ])
            ->values()
            ->all();

        $currentWeek = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        $syllabusItems = DB::table('course_syllabus_items')
            ->where('course_id', $rps->course_id)
            ->orderBy('sequence_no')
            ->limit(12)
            ->pluck('title')
            ->all();

        $references = null;

        if (
            Schema::hasTable('rps_document_meta')
            && Schema::hasColumn('rps_document_meta', 'reference_text')
        ) {
            $meta = DB::table('rps_document_meta')
                ->where('rps_version_id', $version->id)
                ->first(['reference_text', 'supporting_reference_text']);

            if ($meta) {
                $references = trim(
                    "Utama:
".trim((string) ($meta->reference_text ?? ''))
                    .(filled($meta->supporting_reference_text ?? null)
                        ? "
Pendukung:
".trim((string) $meta->supporting_reference_text)
                        : '')
                );
            }
        }

        $previous = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', '<', $week)
            ->where('is_exam', false)
            ->orderByDesc('week_number')
            ->first();

        $previousSubCode = $previous?->rps_sub_cpmk_id
            ? DB::table('rps_sub_cpmks')->where('id', $previous->rps_sub_cpmk_id)->value('code')
            : null;

        $credits = max(1, (int) ($course?->credits ?? 1));

        return [
            'course' => [
                'official_code' => $course?->official_code,
                'name' => $course?->name,
                'credits' => $credits,
            ],
            'target_week' => $week,
            'target_sub_cpmk' => [
                'code' => $targetSub->code,
                'description' => $this->clip($targetSub->description, 600),
                'bloom_level' => $targetSub->bloom_level,
                'parent_cpmk_code' => $parentCode,
            ],
            'parent_cpmk' => $parentCpmk ? [
                'code' => $parentCpmk->code,
                'description' => $this->clip($parentCpmk->description, 700),
                'bloom_level' => $parentCpmk->bloom_level,
            ] : null,
            'target_materials' => $targetMaterials,
            'materials' => $materials,
            'target_assessments' => $targetAssessments,
            'current_week' => $currentWeek ? [
                'material' => $this->clip($currentWeek->material_text, 300),
                'learning_form' => $this->clip($currentWeek->learning_form ?? null, 160),
                'learning_method' => $this->clip($currentWeek->learning_method, 220),
                'learning_activity' => $this->clip($currentWeek->learning_activity, 500),
                'student_assignment' => $this->clip($currentWeek->student_assignment ?? null, 400),
                'assessment_indicator' => $this->clip($currentWeek->assessment_indicator, 500),
                'assessment_criteria' => $this->clip($currentWeek->assessment_criteria, 400),
                'assessment_method' => $this->clip($currentWeek->assessment_method, 250),
            ] : null,
            'syllabus_items' => $syllabusItems,
            'bibliography' => $this->bibliographyEntries((string) $references),
            'previous_week' => $previous ? [
                'week_number' => (int) $previous->week_number,
                'sub_cpmk_code' => $previousSubCode,
                'material' => $this->clip($previous->material_text, 220),
                'learning_method' => $this->clip($previous->learning_method, 140),
            ] : null,
            'time_standard' => [
                'tatap_muka' => "{$credits}×50 menit",
                'tugas_terstruktur' => "{$credits}×60 menit",
                'belajar_mandiri' => "{$credits}×60 menit",
            ],
            'constraints' => [
                'target_weeks' => [$week],
                'must_use_target_sub_cpmk' => true,
                'do_not_move_backward_to_earlier_sub_cpmk' => true,
                'concept_guard_bfs' => 'BFS untuk jalur terpendek hanya pada graf tak berbobot atau bobot seragam. Untuk graf berbobot positif gunakan Dijkstra; A* digunakan bila heuristik relevan.',
            ],
        ];
    }

    private function compactForType(array $full, ?string $type): array
    {
        if (! $type) {
            return $full;
        }

        $base = [
            'course' => $full['course'],
            'period' => $full['period'],
            'constraints' => $full['constraints'],
        ];

        return match ($type) {
            'cpl_mapping' => $base + [
                'cpl_scope' => array_map(
                    fn (array $cpl): array => [
                        'code' => $cpl['code'],
                        'description' => $this->clip($cpl['description'] ?? null, 750),
                        'source' => $cpl['source'],
                    ],
                    $full['cpl_scope']
                ),
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 900),
                        'bloom_level' => $cpmk['bloom_level'],
                        'current_cpl_codes' => $cpmk['cpl_codes'],
                    ],
                    $full['cpmks']
                ),
            ],

            'bloom_mapping' => $base + [
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 900),
                        'bloom_level' => $cpmk['bloom_level'],
                    ],
                    $full['cpmks']
                ),
            ],

            'cpmk_review' => $base + [
                'cpl_scope' => array_map(
                    fn (array $cpl): array => [
                        'code' => $cpl['code'],
                        'description' => $this->clip($cpl['description'] ?? null, 650),
                        'source' => $cpl['source'],
                    ],
                    $full['cpl_scope']
                ),
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 900),
                        'bloom_level' => $cpmk['bloom_level'],
                        'cpl_codes' => $cpmk['cpl_codes'],
                    ],
                    $full['cpmks']
                ),
            ],

            'reference_plan' => $base + [
                'course_description' => $this->clip(
                    $full['course']['description_short'] ?? null,
                    500
                ),
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'description' => $this->clip($sub['description'] ?? null, 280),
                    ],
                    array_slice($full['sub_cpmks'], 0, 16)
                ),
                'materials' => array_slice($full['materials'], 0, 16),
                'existing_references' => [
                    'main' => $this->clip(
                        (string) ($full['document']['reference_text'] ?? ''),
                        1800
                    ),
                    'supporting' => $this->clip(
                        (string) ($full['document']['supporting_reference_text'] ?? ''),
                        1200
                    ),
                ],
            ],

            'material_plan' => $base + [
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 320),
                    ],
                    $full['cpmks']
                ),
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'parent_cpmk_code' => $sub['parent_cpmk_code'],
                        'description' => $this->clip($sub['description'] ?? null, 300),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    array_slice($full['sub_cpmks'], 0, 16)
                ),
                'existing_materials' => array_slice($full['materials'], 0, 16),
                'syllabus_items' => array_slice(
                    $full['master_syllabus']['items'] ?? [],
                    0,
                    16
                ),
            ],

            'sub_cpmk' => $base + [
                'cpl_scope' => array_map(
                    fn (array $cpl): array => [
                        'code' => $cpl['code'],
                        'description' => $this->clip($cpl['description'] ?? null, 500),
                    ],
                    $full['cpl_scope']
                ),
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 850),
                        'bloom_level' => $cpmk['bloom_level'],
                        'cpl_codes' => $cpmk['cpl_codes'],
                    ],
                    $full['cpmks']
                ),
                'existing_sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'parent_cpmk_code' => $sub['parent_cpmk_code'],
                        'description' => $this->clip($sub['description'] ?? null, 650),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    $full['sub_cpmks']
                ),
                'materials' => array_slice($full['materials'], 0, 30),
                'syllabus_items' => array_slice($full['master_syllabus']['items'] ?? [], 0, 30),
            ],

            'weekly_plan' => $base + [
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 700),
                    ],
                    $full['cpmks']
                ),
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'parent_cpmk_code' => $sub['parent_cpmk_code'],
                        'description' => $this->clip($sub['description'] ?? null, 600),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    $full['sub_cpmks']
                ),
                'materials' => array_slice($full['materials'], 0, 30),
                'syllabus_items' => array_slice($full['master_syllabus']['items'] ?? [], 0, 30),
                'bibliography' => $this->bibliographyEntries(
                    trim(
                        (string) ($full['document']['reference_text'] ?? '')
                        ."\n"
                        .(string) ($full['document']['supporting_reference_text'] ?? '')
                    )
                ),
            ],

            'assessment_plan' => $base + [
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'description' => $this->clip($sub['description'] ?? null, 300),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    array_slice($full['sub_cpmks'], 0, 16)
                ),
                'weekly_evidence' => collect($full['weekly_plan'])
                    ->map(fn (array $week): array => [
                        'week_number' => $week['week_number'],
                        'sub_cpmk_code' => $week['sub_cpmk_code'],
                        'assessment_method' => $this->clip(
                            $week['assessment_method'] ?? null,
                            100
                        ),
                    ])
                    ->all(),
                'current_assessments' => collect($full['assessments'])
                    ->map(fn (array $assessment): array => [
                        'code' => $assessment['code'] ?? null,
                        'name' => $this->clip($assessment['name'] ?? null, 120),
                        'type' => $assessment['type'] ?? null,
                        'week_number' => $assessment['week_number'] ?? null,
                        'description' => $this->clip($assessment['description'] ?? null, 260),
                        'weight' => $assessment['weight'] ?? null,
                        'source_type' => $assessment['source_type'] ?? null,
                        'sub_cpmk_codes' => $assessment['sub_cpmk_codes'] ?? [],
                    ])
                    ->take(20)
                    ->values()
                    ->all(),
                'current_tasks' => collect($full['tasks'] ?? [])
                    ->map(fn (array $task): array => [
                        'code' => $task['code'] ?? null,
                        'title' => $this->clip($task['title'] ?? null, 140),
                        'type' => $task['type'] ?? null,
                        'assessment_code' => $task['assessment_code'] ?? null,
                        'assessment_name' => $this->clip($task['assessment_name'] ?? null, 120),
                        'due_week' => $task['due_week'] ?? null,
                        'purpose' => $this->clip($task['purpose'] ?? null, 360),
                        'source_type' => $task['source_type'] ?? null,
                        'sub_cpmk_codes' => $task['sub_cpmk_codes'] ?? [],
                    ])
                    ->take(24)
                    ->values()
                    ->all(),
            ],

            default => $base,
        };
    }

    private function bibliographyEntries(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $normalized = preg_replace(
            '/\s+(?=[a-z]\.\s+[A-Z0-9])/u',
            "\n",
            $text
        ) ?? $text;

        $parts = preg_split('/\r\n|\r|\n/', $normalized) ?: [$normalized];
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
                'code' => '['.($index + 1).']',
                'category' => $item['category'],
                'text' => $this->clip($item['text'], 420),
            ])
            ->all();
    }

    private function semanticTokens(string $value): array
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        $stopwords = [
            'yang','dan','atau','untuk','dengan','dalam','pada','dari','ke',
            'serta','melalui','mahasiswa','mampu','dapat','konsep','materi',
            'pembelajaran','dasar','contoh','masalah','permasalahan','sesuai',
        ];

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function clip(?string $value, int $maxChars): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_strlen($value) <= $maxChars
            ? $value
            : rtrim(mb_substr($value, 0, $maxChars - 1)).'…';
    }
}
