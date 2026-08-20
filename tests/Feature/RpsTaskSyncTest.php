<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createRpsTaskSyncFixture(User $lecturer): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $sub1 = (string) Str::uuid();
    $sub2 = (string) Str::uuid();
    $timestamp = now()->subMinute();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-RTM-'.Str::lower(Str::random(6)),
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-RTM-'.Str::lower(Str::random(6)),
        'name' => 'Kurikulum RTM',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'SYS-RTM-'.Str::lower(Str::random(6)),
        'official_code' => 'MAT-RTM',
        'name' => 'Algoritma dan Dasar Pemrograman',
        'credits' => 3,
        'semester_recommended' => 2,
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

    foreach ([[$sub1, 'Sub-CPMK-1', 1], [$sub2, 'Sub-CPMK-2', 2]] as [$id, $code, $sequence]) {
        DB::table('rps_sub_cpmks')->insert([
            'id' => $id,
            'rps_version_id' => $versionId,
            'code' => $code,
            'description' => "Rumusan {$code}",
            'bloom_level' => 'C3',
            'sequence_no' => $sequence,
            'source_type' => 'manual',
            'created_by' => $lecturer->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    return compact('rpsId', 'versionId', 'sub1', 'sub2', 'timestamp');
}

function insertAssessmentForRtm(array $fixture, User $lecturer, string $name, int $week, float $weight, string $subId): string
{
    $assessmentId = (string) Str::uuid();
    DB::table('assessments')->insert([
        'id' => $assessmentId,
        'rps_version_id' => $fixture['versionId'],
        'code' => 'ASM-'.Str::upper(Str::random(5)),
        'name' => $name,
        'type' => 'assignment',
        'week_number' => $week,
        'description' => $name,
        'weight' => $weight,
        'source_type' => 'manual',
        'created_by' => $lecturer->id,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);
    DB::table('assessment_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'assessment_id' => $assessmentId,
        'rps_sub_cpmk_id' => $subId,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    return $assessmentId;
}

test('manual RTM adopts generated placeholder instead of creating a duplicate', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createRpsTaskSyncFixture($lecturer);
    $assessmentId = insertAssessmentForRtm($fixture, $lecturer, 'Tugas Terstruktur 2', 4, 10, $fixture['sub2']);
    $generatedId = (string) Str::uuid();

    DB::table('rps_tasks')->insert([
        'id' => $generatedId,
        'rps_version_id' => $fixture['versionId'],
        'assessment_id' => $assessmentId,
        'code' => 'RTM-01',
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Placeholder otomatis.',
        'instructions' => 'Placeholder otomatis.',
        'expected_output' => 'Placeholder otomatis.',
        'due_week' => 4,
        'source_type' => 'assessment_sync',
        'created_by' => $lecturer->id,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);
    DB::table('rps_task_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'rps_task_id' => $generatedId,
        'rps_sub_cpmk_id' => $fixture['sub2'],
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    $this->actingAs($lecturer)->post(route('rps.tasks.store', $fixture['rpsId']), [
        'assessment_id' => '',
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Tujuan RTM manual untuk Sub-CPMK-2.',
        'instructions' => 'Kerjakan tugas sesuai instruksi manual.',
        'expected_output' => 'Laporan tugas terstruktur.',
        'due_week' => 4,
        'sub_cpmk_ids' => [$fixture['sub2']],
    ])->assertSessionHasNoErrors();

    $tasks = DB::table('rps_tasks')
        ->where('rps_version_id', $fixture['versionId'])
        ->where('assessment_id', $assessmentId)
        ->get();

    expect($tasks)->toHaveCount(1)
        ->and((string) $tasks->first()->id)->toBe($generatedId)
        ->and($tasks->first()->source_type)->toBe('manual')
        ->and($tasks->first()->purpose)->toBe('Tujuan RTM manual untuk Sub-CPMK-2.');
});

test('RTM cards are returned in due week order', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createRpsTaskSyncFixture($lecturer);

    foreach ([['RTM-01', 'RTM pekan akhir', 15], ['RTM-02', 'RTM tambahan pekan 4', 4]] as [$code, $title, $week]) {
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
            'created_at' => $fixture['timestamp'],
            'updated_at' => $fixture['timestamp'],
        ]);
    }

    $this->actingAs($lecturer)
        ->get(route('rps.show', $fixture['rpsId']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.due_week', 4)
            ->where('tasks.0.title', 'RTM tambahan pekan 4')
            ->where('tasks.1.due_week', 15)
        );
});
