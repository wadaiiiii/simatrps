<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RpsDraftService
{
    public function create(
        string $courseId,
        int $ownerId,
        string $academicYear,
        string $academicSemester
    ): array {
        return DB::transaction(function () use ($courseId, $ownerId, $academicYear, $academicSemester): array {
            $course = DB::table('courses')->where('id', $courseId)->where('is_active', true)->first();

            if (! $course) {
                throw ValidationException::withMessages([
                    'course_id' => 'Mata kuliah tidak ditemukan.',
                ]);
            }

            $cpmkCount = DB::table('curriculum_cpmks')->where('course_id', $courseId)->count();
            $readiness = $this->readiness($course, $cpmkCount);

            if ($readiness === 'needs_admin_review') {
                throw ValidationException::withMessages([
                    'course_id' => 'Mata kuliah ini masih memerlukan review Admin sebelum RPS dapat dibuat.',
                ]);
            }

            $existing = DB::table('rps')
                ->where('course_id', $courseId)
                ->where('owner_id', $ownerId)
                ->where('academic_year', $academicYear)
                ->where('academic_semester', $academicSemester)
                ->first();

            if ($existing?->current_version_id) {
                $version = DB::table('rps_versions')->where('id', $existing->current_version_id)->first();
                if ($version && $version->status === 'draft') {
                    return [
                        'rps_id' => (string) $existing->id,
                        'version_id' => (string) $version->id,
                        'reused' => true,
                        'readiness' => $readiness,
                    ];
                }
            }

            $rpsId = $existing?->id ?: (string) Str::uuid();

            if (! $existing) {
                DB::table('rps')->insert([
                    'id' => $rpsId,
                    'curriculum_id' => $course->curriculum_id,
                    'course_id' => $courseId,
                    'owner_id' => $ownerId,
                    'academic_year' => $academicYear,
                    'academic_semester' => $academicSemester,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $maxVersion = (float) (DB::table('rps_versions')
                ->where('rps_id', $rpsId)
                ->max('version_no') ?? 0);

            $versionId = (string) Str::uuid();
            $templateId = DB::table('rps_templates')->where('status', 'active')->value('id');

            DB::table('rps_versions')->insert([
                'id' => $versionId,
                'rps_id' => $rpsId,
                'version_no' => $maxVersion + 1,
                'template_id' => $templateId,
                'status' => 'draft',
                'description_short' => 'Draft RPS '.$course->name,
                'change_summary' => 'Versi awal dibuat dari master kurikulum.',
                'ai_generation_meta' => json_encode([
                    'master_cpmk_count' => $cpmkCount,
                    'readiness' => $readiness,
                    'cpmk_cpl_mapping_generated' => false,
                ]),
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $masterCpmks = DB::table('curriculum_cpmks')
                ->where('course_id', $courseId)
                ->orderBy('sequence_no')
                ->get();

            foreach ($masterCpmks as $cpmk) {
                DB::table('rps_cpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $versionId,
                    'code' => $cpmk->code,
                    'description' => $cpmk->description,
                    'source_type' => 'curriculum',
                    'source_cpmk_id' => $cpmk->id,
                    'sequence_no' => $cpmk->sequence_no,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            for ($week = 1; $week <= 16; $week++) {
                $isUts = $week === 8;
                $isUas = $week === 16;

                DB::table('rps_weekly_plans')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $versionId,
                    'week_number' => $week,
                    'is_exam' => $isUts || $isUas,
                    'exam_type' => $isUts ? 'UTS' : ($isUas ? 'UAS' : null),
                    'source_type' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('rps')->where('id', $rpsId)->update([
                'current_version_id' => $versionId,
                'status' => 'draft',
                'updated_at' => now(),
            ]);

            return [
                'rps_id' => $rpsId,
                'version_id' => $versionId,
                'reused' => false,
                'readiness' => $readiness,
            ];
        });
    }

    public function readiness(object $course, int $cpmkCount): string
    {
        if ($course->code_status === 'internal' || $course->verification_status === 'needs_review') {
            return 'needs_admin_review';
        }

        return $cpmkCount > 0
            ? 'ready_with_master_cpmk'
            : 'ai_cpmk_required';
    }
}
