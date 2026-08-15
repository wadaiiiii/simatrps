<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;

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
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'week_number' => $assessment->week_number,
                    'weight' => $assessment->weight,
                    'sub_cpmk_codes' => $subCodes,
                ];
            })
            ->all();

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

        $parentCode = DB::table('rps_cpmk_subcpmks')
            ->join('rps_cpmks', 'rps_cpmks.id', '=', 'rps_cpmk_subcpmks.rps_cpmk_id')
            ->where('rps_cpmk_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->value('rps_cpmks.code');

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->where(function ($query) use ($targetSub): void {
                $query->where('rps_sub_cpmk_id', $targetSub->id)
                    ->orWhereNull('rps_sub_cpmk_id');
            })
            ->orderBy('sequence_no')
            ->limit(10)
            ->pluck('title')
            ->all();

        if ($materials === []) {
            $materials = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->orderBy('sequence_no')
                ->limit(10)
                ->pluck('title')
                ->all();
        }

        $syllabusItems = DB::table('course_syllabus_items')
            ->where('course_id', $rps->course_id)
            ->orderBy('sequence_no')
            ->limit(12)
            ->pluck('title')
            ->all();

        $references = DB::table('course_syllabi')
            ->where('course_id', $rps->course_id)
            ->orderBy('source_entry_no')
            ->value('reference_text');

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
            'materials' => $materials,
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

            'material_plan' => $base + [
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 650),
                    ],
                    $full['cpmks']
                ),
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'parent_cpmk_code' => $sub['parent_cpmk_code'],
                        'description' => $this->clip($sub['description'] ?? null, 500),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    $full['sub_cpmks']
                ),
                'existing_materials' => array_slice($full['materials'], 0, 30),
                'syllabus_items' => array_slice($full['master_syllabus']['items'] ?? [], 0, 30),
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
                'references' => $this->clip($full['master_syllabus']['references'] ?? null, 1400),
            ],

            'assessment_plan' => $base + [
                'sub_cpmks' => array_map(
                    fn (array $sub): array => [
                        'code' => $sub['code'],
                        'description' => $this->clip($sub['description'] ?? null, 550),
                        'bloom_level' => $sub['bloom_level'],
                    ],
                    $full['sub_cpmks']
                ),
                'weekly_evidence' => collect($full['weekly_plan'])
                    ->map(fn (array $week): array => [
                        'week_number' => $week['week_number'],
                        'sub_cpmk_code' => $week['sub_cpmk_code'],
                        'assessment_method' => $this->clip($week['assessment_method'] ?? null, 180),
                    ])
                    ->all(),
                'current_assessments' => $full['assessments'],
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

        $parts = preg_split('/\r\n|\r|\n/', $text) ?: [];

        if (count(array_filter($parts, fn ($line) => trim($line) !== '')) <= 1) {
            $parts = preg_split('/\s*;\s*(?=[A-Z0-9])/u', $text) ?: [$text];
        }

        return collect($parts)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->reject(fn ($line) => preg_match('/^(pustaka|utama|tambahan)\s*:?\s*$/i', $line))
            ->map(fn ($line) => preg_replace('/^\s*(?:\d+[\.\)]|[-•])\s*/u', '', $line) ?: $line)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($line, $index) => [
                'code' => '['.($index + 1).']',
                'text' => $this->clip($line, 420),
            ])
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
