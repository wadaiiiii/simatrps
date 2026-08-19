<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createRpsForAdminReport(User $lecturer, string $status = 'final', ?string $reviewStatus = 'approved'): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $timestamp = now()->subMinutes(2);

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-REPORT',
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-REPORT',
        'name' => 'Kurikulum Rekap',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'MAT-REPORT',
        'official_code' => 'MAT998',
        'name' => 'Mata Kuliah Rekap',
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
        'status' => $status,
        'current_version_id' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_versions')->insert([
        'id' => $versionId,
        'rps_id' => $rpsId,
        'version_no' => 1,
        'status' => $status,
        'created_by' => $lecturer->id,
        'finalized_at' => $status === 'final' ? $timestamp : null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->where('id', $rpsId)->update([
        'current_version_id' => $versionId,
        'updated_at' => $timestamp,
    ]);

    if ($reviewStatus !== null) {
        $admin = User::factory()->create([
            'name' => 'Admin Reviewer',
            'role' => 'admin',
            'is_active' => true,
        ]);

        DB::table('rps_reviews')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $versionId,
            'reviewer_id' => $admin->id,
            'status' => $reviewStatus,
            'note' => $reviewStatus === 'approved' ? 'RPS siap digunakan.' : 'Perbaiki bagian penilaian.',
            'reviewed_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    return [
        'rps_id' => $rpsId,
        'version_id' => $versionId,
        'updated_at' => $timestamp,
    ];
}

test('admin can open RPS recap without mutating RPS data', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'name' => 'Dosen Rekap',
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReport($lecturer);

    $before = DB::table('rps')->where('id', $fixture['rps_id'])->first();

    $response = $this->actingAs($admin)->get(route('admin.reports'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/rps-report')
        ->where('stats.total', 1)
        ->where('stats.lecturers', 1)
        ->where('stats.final', 1)
        ->where('stats.approved', 1)
        ->where('rows.0.id', $fixture['rps_id'])
        ->where('rows.0.owner.name', 'Dosen Rekap')
        ->where('rows.0.course.name', 'Mata Kuliah Rekap')
        ->where('rows.0.review.status', 'approved')
        ->where('rows.0.review.outdated', false)
    );

    $after = DB::table('rps')->where('id', $fixture['rps_id'])->first();
    expect((string) $after->updated_at)->toBe((string) $before->updated_at);
});

test('lecturer cannot access admin recap or export', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);

    $this->actingAs($lecturer)
        ->get(route('admin.reports'))
        ->assertForbidden();

    $this->actingAs($lecturer)
        ->get(route('admin.reports.csv'))
        ->assertForbidden();
});

test('admin recap filters by current review follow up status', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    createRpsForAdminReport($lecturer, 'final', 'approved');

    $response = $this->actingAs($admin)->get(route('admin.reports', [
        'review_status' => 'revision_required',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.review_status', 'revision_required')
        ->where('stats.total', 0)
        ->has('rows', 0)
    );
});

test('admin can export filtered recap as UTF-8 CSV', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'name' => 'Dosen Rekap',
        'email' => 'dosen.rekap@example.test',
        'role' => 'dosen',
        'is_active' => true,
    ]);
    createRpsForAdminReport($lecturer);

    $response = $this->actingAs($admin)->get(route('admin.reports.csv', [
        'academic_year' => '2026/2027',
        'review_status' => 'approved',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)
        ->toContain('Dosen Rekap')
        ->toContain('dosen.rekap@example.test')
        ->toContain('Mata Kuliah Rekap')
        ->toContain('Disetujui');
});
