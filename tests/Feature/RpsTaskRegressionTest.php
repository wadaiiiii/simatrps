<?php

use App\Models\User;
use App\Services\Rps\RpsTaskOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createRpsTaskRegressionFixture(User $lecturer): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $subCpmk1 = (string) Str::uuid();
    $subCpmk2 = (string) Str::uuid();
    $timestamp = now()->subMinute();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-RTM-REG',
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-RTM-REG',
        'name' => 'Kurikulum RTM Regression',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'MAT-RTM-REG',
        'official_code' => 'MAT998',
        'name' => 'Algoritma dan Dasar Pemrograman',
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
        'updated_at' => $timestamp,
    ]);

    foreach ([
        [$subCpmk1, 'Sub-CPMK-1', 'Menerapkan algoritma dasar.', 1],
        [$subCpmk2, 'Sub-CPMK-2', 'Menganalisis struktur data.', 2],
    ] as [$id, $code, $description, $sequence]) {
        DB::table('rps_sub_cpmks')->insert([
            'id' => $id,
            'rps_version_id' => $versionId,
            'code' => $code,
            'description' => $description,
            'bloom_level' => 'C3',
            'sequence_no' => $sequence,
            'source_type' => 'manual',
            'created_by' => $lecturer->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    return compact('rpsId', 'versionId', 'subCpmk1', 'subCpmk2');
}

test('manual RTM submission updates an already synchronized RTM instead of creating a duplicate', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $fixture = createRpsTaskRegressionFixture($lecturer);
    $assessmentId = (string) Str::uuid();
    $generatedTaskId = (string) Str::uuid();

    DB::table('assessments')->insert([
        'id' => $assessmentId,
        'rps_version_id' => $fixture['versionId'],
        'code' => 'ASM-02',
        'name' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'week_number' => 4,
        'description' => 'Mengukur Sub-CPMK-2.',
        'weight' => 10,
        'source_type' => 'manual',
        'created_by' => $lecturer->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('assessment_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'assessment_id' => $assessmentId,
        'rps_sub_cpmk_id' => $fixture['subCpmk2'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Represents the RTM that assessment synchronization already created but
    // that the partial client reload had not shown yet.
    DB::table('rps_tasks')->insert([
        'id' => $generatedTaskId,
        'rps_version_id' => $fixture['versionId'],
        'assessment_id' => $assessmentId,
        'code' => 'RTM-04',
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Generated purpose',
        'due_week' => 4,
        'source_type' => 'assessment_sync',
        'created_by' => $lecturer->id,
        'created_at' => now()->subSecond(),
        'updated_at' => now()->subSecond(),
    ]);

    $response = $this->actingAs($lecturer)->post(route('rps.tasks.store', $fixture['rpsId']), [
        'assessment_id' => $assessmentId,
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Tujuan RTM manual yang telah diperbaiki.',
        'instructions' => 'Kerjakan sesuai ketentuan asesmen.',
        'expected_output' => 'Laporan tugas.',
        'due_week' => 4,
        'sub_cpmk_ids' => [$fixture['subCpmk2']],
    ]);

    $response->assertSessionHasNoErrors();

    expect(DB::table('rps_tasks')
        ->where('rps_version_id', $fixture['versionId'])
        ->where('assessment_id', $assessmentId)
        ->count())->toBe(1);

    $this->assertDatabaseHas('rps_tasks', [
        'id' => $generatedTaskId,
        'assessment_id' => $assessmentId,
        'source_type' => 'manual',
        'purpose' => 'Tujuan RTM manual yang telah diperbaiki.',
        'due_week' => 4,
    ]);
});

test('manual RTM without an explicit parent assessment can only auto link to one exact matching assessment', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $fixture = createRpsTaskRegressionFixture($lecturer);
    $assessmentId = (string) Str::uuid();

    DB::table('assessments')->insert([
        'id' => $assessmentId,
        'rps_version_id' => $fixture['versionId'],
        'code' => 'ASM-01',
        'name' => 'Tugas Terstruktur 1',
        'type' => 'assignment',
        'week_number' => 3,
        'weight' => 5,
        'source_type' => 'manual',
        'created_by' => $lecturer->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('assessment_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'assessment_id' => $assessmentId,
        'rps_sub_cpmk_id' => $fixture['subCpmk1'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($lecturer)->post(route('rps.tasks.store', $fixture['rpsId']), [
        'assessment_id' => '',
        'title' => 'Tugas Terstruktur 1',
        'type' => 'assignment',
        'purpose' => 'Menguji Sub-CPMK-1.',
        'due_week' => 3,
        'sub_cpmk_ids' => [$fixture['subCpmk1']],
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('rps_tasks', [
        'rps_version_id' => $fixture['versionId'],
        'assessment_id' => $assessmentId,
        'title' => 'Tugas Terstruktur 1',
    ]);
});

test('RTM presentation codes are reordered by due week after a task change', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsTaskRegressionFixture($lecturer);

    foreach ([
        ['RTM-01', 'Proyek pekan 15', 15],
        ['RTM-02', 'Tugas pekan 6', 6],
        ['RTM-03', 'Tugas pekan 4', 4],
    ] as [$code, $title, $week]) {
        DB::table('rps_tasks')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $fixture['versionId'],
            'assessment_id' => null,
            'code' => $code,
            'title' => $title,
            'type' => 'assignment',
            'due_week' => $week,
            'source_type' => 'manual',
            'created_by' => $lecturer->id,
            'created_at' => now()->addSeconds($week),
            'updated_at' => now(),
        ]);
    }

    app(RpsTaskOrderService::class)->renumber($fixture['versionId']);

    $ordered = DB::table('rps_tasks')
        ->where('rps_version_id', $fixture['versionId'])
        ->orderBy('code')
        ->pluck('due_week')
        ->map(fn ($week) => (int) $week)
        ->all();

    expect($ordered)->toBe([4, 6, 15]);
});
