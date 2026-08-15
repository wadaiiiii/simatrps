<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SiMatRpsCurriculum2025Seeder extends Seeder
{
    private string $sourceReference = 'Buku Kurikulum Program Studi Matematika 2025';

    public function run(): void
    {
        value(function (): void {
            $master = $this->json('curriculum_2025_master.json');
            $mapping = $this->json('course_cpl_2025.json');
            $syllabi = $this->json('course_syllabi_2025.json');
            $cpmks = $this->json('curriculum_cpmks_2025.json');
            $items = $this->json('course_syllabus_items_2025.json');

            $studyProgramId = $this->upsertUuid(
                'study_programs',
                ['code' => $master['study_program']['code']],
                [
                    ...$master['study_program'],
                    'updated_at' => now(),
                ]
            );

            $curriculumId = $this->upsertUuid(
                'curriculums',
                [
                    'study_program_id' => $studyProgramId,
                    'code' => $master['curriculum']['code'],
                ],
                [
                    ...$master['curriculum'],
                    'study_program_id' => $studyProgramId,
                    'updated_at' => now(),
                ]
            );

            $cplIds = [];
            foreach ($master['cpls'] as $row) {
                $cplIds[$row['code']] = $this->upsertUuid(
                    'cpls',
                    ['curriculum_id' => $curriculumId, 'code' => $row['code']],
                    [
                        ...$row,
                        'curriculum_id' => $curriculumId,
                        'is_active' => true,
                        'source_reference' => $this->sourceReference.' - Bagian E Rumusan CPL',
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($master['kbks'] as $row) {
                $this->upsertUuid(
                    'kbks',
                    ['curriculum_id' => $curriculumId, 'code' => $row['code']],
                    [
                        ...$row,
                        'curriculum_id' => $curriculumId,
                        'is_active' => true,
                        'source_reference' => $this->sourceReference.' - Bagian F Penetapan Bahan Kajian',
                        'updated_at' => now(),
                    ]
                );
            }

            $categoryIds = [];
            foreach ($master['categories'] as $row) {
                DB::table('course_categories')->updateOrInsert(
                    ['code' => $row['code']],
                    $row
                );
                $categoryIds[$row['code']] = DB::table('course_categories')
                    ->where('code', $row['code'])
                    ->value('id');
            }

            $courseIds = [];
            foreach ($master['courses'] as $row) {
                $verification = $row['code_status'] === 'internal'
                    ? 'needs_review'
                    : 'source_verified';

                $courseIds[$row['system_code']] = $this->upsertUuid(
                    'courses',
                    [
                        'curriculum_id' => $curriculumId,
                        'system_code' => $row['system_code'],
                    ],
                    [
                        'curriculum_id' => $curriculumId,
                        'system_code' => $row['system_code'],
                        'official_code' => $row['official_code'],
                        'name' => $row['name'],
                        'credits' => $row['credits'],
                        'semester_recommended' => $row['semester_recommended'],
                        'is_mandatory' => $row['is_mandatory'],
                        'category_id' => $categoryIds[$row['category_code']] ?? null,
                        'course_type' => $row['course_type'],
                        'has_practicum' => $row['has_practicum'],
                        'is_recognition_course' => $row['is_recognition_course'],
                        'is_course_group' => $row['is_course_group'],
                        'code_status' => $row['code_status'],
                        'prerequisite_note' => $row['prerequisite_note'],
                        'verification_status' => $verification,
                        'source_reference' => $this->sourceReference.' - Struktur/Distribusi Mata Kuliah',
                        'is_active' => true,
                        'updated_at' => now(),
                    ]
                );
            }

            $religionCourseId = $courseIds['USB-AGAMA'];
            foreach ($master['variants'] as $row) {
                $this->upsertUuid(
                    'course_variants',
                    ['parent_course_id' => $religionCourseId, 'variant_code' => $row['variant_code']],
                    [
                        ...$row,
                        'parent_course_id' => $religionCourseId,
                        'is_active' => true,
                        'source_reference' => $this->sourceReference.' - Semester I',
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($master['prerequisites'] as $row) {
                $targetId = $courseIds[$row['target_code']] ?? null;
                $prerequisiteId = $courseIds[$row['prerequisite_code']] ?? null;

                if (! $targetId || ! $prerequisiteId) {
                    throw new RuntimeException('Kode mata kuliah prasyarat tidak ditemukan.');
                }

                $this->upsertUuid(
                    'course_prerequisites',
                    [
                        'course_id' => $targetId,
                        'prerequisite_course_id' => $prerequisiteId,
                        'prerequisite_type' => $row['prerequisite_type'],
                    ],
                    [
                        'course_id' => $targetId,
                        'prerequisite_course_id' => $prerequisiteId,
                        'prerequisite_type' => $row['prerequisite_type'],
                        'source_reference' => $row['source_reference'],
                        'note' => $row['note'],
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($mapping as $row) {
                $courseId = $courseIds[$row['system_code']] ?? null;
                $cplId = $cplIds[$row['cpl_code']] ?? null;

                if (! $courseId || ! $cplId) {
                    throw new RuntimeException('Mapping MK-CPL mengandung kode yang tidak ditemukan.');
                }

                $this->upsertUuid(
                    'course_cpls',
                    ['course_id' => $courseId, 'cpl_id' => $cplId],
                    [
                        'course_id' => $courseId,
                        'cpl_id' => $cplId,
                        'contribution_level' => 'supporting',
                        'planned_weight' => null,
                        'source_reference' => $this->sourceReference.' - Matriks Struktur Kurikulum dan CPL',
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($syllabi as $row) {
                $courseId = $courseIds[$row['system_code']] ?? null;
                if (! $courseId) {
                    throw new RuntimeException('Silabus mengandung mata kuliah yang tidak ditemukan.');
                }

                $this->upsertUuid(
                    'course_syllabi',
                    ['course_id' => $courseId, 'source_entry_no' => $row['source_entry_no']],
                    [
                        'course_id' => $courseId,
                        'source_entry_no' => $row['source_entry_no'],
                        'source_variant_code' => $row['source_variant_code'],
                        'source_course_code' => $row['source_course_code'],
                        'source_course_header' => $row['source_course_header'],
                        'source_credits' => $row['source_credits'],
                        'source_prerequisite_text' => $row['source_prerequisite_text'],
                        'description' => $this->nullIfEmpty($row['description']),
                        'syllabus_text' => $this->nullIfEmpty($row['syllabus_text']),
                        'reference_text' => $this->nullIfEmpty($row['reference_text']),
                        'verification_status' => $row['verification_status'],
                        'source_reference' => $this->sourceReference.' - BAB III.A Silabus Mata Kuliah',
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($cpmks as $row) {
                $courseId = $courseIds[$row['system_code']] ?? null;
                if (! $courseId) {
                    throw new RuntimeException('CPMK mengandung mata kuliah yang tidak ditemukan.');
                }

                $this->upsertUuid(
                    'curriculum_cpmks',
                    ['course_id' => $courseId, 'code' => $row['code']],
                    [
                        'course_id' => $courseId,
                        'code' => $row['code'],
                        'description' => $row['description'],
                        'sequence_no' => $row['sequence_no'],
                        'verification_status' => $row['verification_status'],
                        'source_reference' => $this->sourceReference.' - BAB III.A Silabus Mata Kuliah',
                        'source_entry_no' => $row['source_entry_no'],
                        'source_course_code' => $row['source_course_code'],
                        'source_course_header' => $row['source_course_header'],
                        'source_variant_code' => $row['source_variant_code'],
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($items as $row) {
                $courseId = $courseIds[$row['system_code']] ?? null;
                if (! $courseId) {
                    throw new RuntimeException('Item silabus mengandung mata kuliah yang tidak ditemukan.');
                }

                $this->upsertUuid(
                    'course_syllabus_items',
                    [
                        'course_id' => $courseId,
                        'source_entry_no' => $row['source_entry_no'],
                        'sequence_no' => $row['sequence_no'],
                    ],
                    [
                        'course_id' => $courseId,
                        'source_entry_no' => $row['source_entry_no'],
                        'sequence_no' => $row['sequence_no'],
                        'title' => $row['title'],
                        'source_reference' => $this->sourceReference.' - BAB III.A Silabus Mata Kuliah',
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($master['issues'] as $row) {
                $this->upsertUuid(
                    'curriculum_data_issues',
                    ['curriculum_id' => $curriculumId, 'issue_code' => $row['issue_code']],
                    [
                        ...$row,
                        'curriculum_id' => $curriculumId,
                        'status' => 'open',
                        'updated_at' => now(),
                    ]
                );
            }

            $templateId = $this->upsertUuid(
                'rps_templates',
                [
                    'study_program_id' => $studyProgramId,
                    'code' => $master['template']['code'],
                    'version_no' => $master['template']['version_no'],
                ],
                [
                    ...$master['template'],
                    'study_program_id' => $studyProgramId,
                    'updated_at' => now(),
                ]
            );

            foreach ($master['template_sections'] as $row) {
                $this->upsertUuid(
                    'rps_template_sections',
                    ['template_id' => $templateId, 'section_key' => $row['section_key']],
                    [
                        ...$row,
                        'template_id' => $templateId,
                        'config' => json_encode([]),
                        'updated_at' => now(),
                    ]
                );
            }

            foreach ($master['obe_rules'] as $row) {
                $this->upsertUuid(
                    'obe_rules',
                    ['code' => $row['code']],
                    [
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'severity' => $row['severity'],
                        'is_active' => $row['is_active'],
                        'config' => json_encode($row['config']),
                        'updated_at' => now(),
                    ]
                );
            }

            $this->assertCounts($curriculumId);
        });
    }

    private function json(string $file): array
    {
        $path = database_path('seeders/data/'.$file);
        $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function upsertUuid(string $table, array $where, array $values): string
    {
        $existing = DB::table($table)->where($where)->value('id');

        if ($existing) {
            DB::table($table)->where('id', $existing)->update($values);
            return (string) $existing;
        }

        $id = (string) Str::uuid();

        DB::table($table)->insert([
            'id' => $id,
            ...$values,
            'created_at' => $values['created_at'] ?? now(),
        ]);

        return $id;
    }

    private function nullIfEmpty(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    private function assertCounts(string $curriculumId): void
    {
        $checks = [
            'CPL' => [DB::table('cpls')->where('curriculum_id', $curriculumId)->count(), 8],
            'Mata Kuliah' => [DB::table('courses')->where('curriculum_id', $curriculumId)->count(), 63],
            'Prasyarat' => [
                DB::table('course_prerequisites')
                    ->join('courses', 'courses.id', '=', 'course_prerequisites.course_id')
                    ->where('courses.curriculum_id', $curriculumId)
                    ->count(),
                35,
            ],
            'MK-CPL' => [
                DB::table('course_cpls')
                    ->join('courses', 'courses.id', '=', 'course_cpls.course_id')
                    ->where('courses.curriculum_id', $curriculumId)
                    ->count(),
                255,
            ],
            'Silabus' => [
                DB::table('course_syllabi')
                    ->join('courses', 'courses.id', '=', 'course_syllabi.course_id')
                    ->where('courses.curriculum_id', $curriculumId)
                    ->count(),
                62,
            ],
            'CPMK' => [
                DB::table('curriculum_cpmks')
                    ->join('courses', 'courses.id', '=', 'curriculum_cpmks.course_id')
                    ->where('courses.curriculum_id', $curriculumId)
                    ->count(),
                290,
            ],
            'Item Silabus' => [
                DB::table('course_syllabus_items')
                    ->join('courses', 'courses.id', '=', 'course_syllabus_items.course_id')
                    ->where('courses.curriculum_id', $curriculumId)
                    ->count(),
                259,
            ],
        ];

        foreach ($checks as $label => [$actual, $expected]) {
            if ($actual !== $expected) {
                throw new RuntimeException("Verifikasi {$label} gagal: expected {$expected}, found {$actual}.");
            }
        }
    }
}
