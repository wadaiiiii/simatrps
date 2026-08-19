<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('admin dashboard reports persisted blocking OBE validation progress', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);

    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $timestamp = now();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT',
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-2025',
        'name' => 'Kurikulum 2025',
        'year' => 2025,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'MAT-TEST',
        'official_code' => 'MAT999',
        'name' => 'Mata Kuliah Uji Monitoring',
        'credits' => 3,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->insert([
        'id' => $rpsId,
        'curriculum_id' => $curriculumId,
        'course_id' => $courseId,
        'owner_id' => $lecturer->id,
        'academic_year' => '2026/2027',
        'academic_semester' => 'Ganjil',
        'status' => 'draft',
        'current_version_id' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_versions')->insert([
        'id' => $versionId,
        'rps_id' => $rpsId,
        'version_no' => 1,
        'status' => 'draft',
        'created_by' => $lecturer->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->where('id', $rpsId)->update([
        'current_version_id' => $versionId,
    ]);

    foreach ([
        ['code' => 'required-pass', 'passed' => true, 'severity' => 'required'],
        ['code' => 'required-fail', 'passed' => false, 'severity' => 'required'],
        ['code' => 'advisory-fail', 'passed' => false, 'severity' => 'advisory'],
    ] as $validation) {
        DB::table('obe_validation_results')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $versionId,
            'rule_code' => $validation['code'],
            'severity' => $validation['severity'],
            'is_passed' => $validation['passed'],
            'message' => 'Uji validator dashboard',
            'details' => json_encode(['severity' => $validation['severity']]),
            'validated_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/dashboard')
        ->where('stats.rps_total', 1)
        ->where('stats.rps_obe_valid', 0)
        ->where('rpsRows.data.0.owner.id', $lecturer->id)
        ->where('rpsRows.data.0.progress.obe_percent', 50)
    );
});

test('final RPS counts as OBE valid on admin dashboard', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);

    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $timestamp = now();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-FINAL',
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-FINAL',
        'name' => 'Kurikulum Final',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'MAT-FINAL',
        'name' => 'RPS Final Uji',
        'credits' => 2,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('rps')->insert([
        'id' => $rpsId,
        'curriculum_id' => $curriculumId,
        'course_id' => $courseId,
        'owner_id' => $lecturer->id,
        'academic_year' => '2026/2027',
        'academic_semester' => 'Ganjil',
        'status' => 'final',
        'current_version_id' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('rps_versions')->insert([
        'id' => $versionId,
        'rps_id' => $rpsId,
        'version_no' => 1,
        'status' => 'final',
        'created_by' => $lecturer->id,
        'finalized_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('rps')->where('id', $rpsId)->update(['current_version_id' => $versionId]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.rps_obe_valid', 1)
        ->where('stats.rps_final', 1)
        ->where('rpsRows.data.0.progress.obe_percent', 100)
    );
});
