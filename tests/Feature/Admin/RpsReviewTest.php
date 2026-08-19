<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createRpsForAdminReview(User $lecturer, string $status = 'draft'): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $timestamp = now()->subMinute();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-REVIEW',
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-REVIEW',
        'name' => 'Kurikulum Review',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'MAT-REVIEW',
        'official_code' => 'MAT999',
        'name' => 'Mata Kuliah Review',
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

    return [
        'rps_id' => $rpsId,
        'version_id' => $versionId,
        'timestamp' => $timestamp,
    ];
}

test('admin can open a read only RPS review without mutating the RPS', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer);

    $before = DB::table('rps')->where('id', $fixture['rps_id'])->first();

    $response = $this->actingAs($admin)
        ->get(route('admin.rps.review', $fixture['rps_id']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/rps-review')
        ->where('rps.id', $fixture['rps_id'])
        ->where('rps.owner.id', $lecturer->id)
        ->where('rps.course.name', 'Mata Kuliah Review')
        ->where('review.latest', null)
        ->where('review.outdated', false)
        ->has('review.history', 0)
    );

    $after = DB::table('rps')->where('id', $fixture['rps_id'])->first();

    expect((string) $after->updated_at)->toBe((string) $before->updated_at)
        ->and(DB::table('rps_reviews')->count())->toBe(0);
});

test('lecturer cannot open the admin RPS review page', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer);

    $this->actingAs($lecturer)
        ->get(route('admin.rps.review', $fixture['rps_id']))
        ->assertForbidden();
});

test('admin can request revision with a required note', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer);

    $response = $this->actingAs($admin)
        ->from(route('admin.rps.review', $fixture['rps_id']))
        ->post(route('admin.rps.review.store', $fixture['rps_id']), [
            'status' => 'revision_required',
            'note' => 'Perbaiki relasi RTM dengan asesmen induknya.',
        ]);

    $response->assertRedirect(route('admin.rps.review', $fixture['rps_id']));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('rps_reviews', [
        'rps_version_id' => $fixture['version_id'],
        'reviewer_id' => $admin->id,
        'status' => 'revision_required',
        'note' => 'Perbaiki relasi RTM dengan asesmen induknya.',
    ]);

    $dashboard = $this->actingAs($lecturer)->get(route('dashboard'));
    $dashboard->assertInertia(fn (Assert $page) => $page
        ->where('recentRps.0.id', $fixture['rps_id'])
        ->where('recentRps.0.review_status', 'revision_required')
        ->where('recentRps.0.review_note', 'Perbaiki relasi RTM dengan asesmen induknya.')
        ->where('recentRps.0.review_outdated', false)
    );
});

test('revision decision cannot be saved without a note', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer);

    $response = $this->actingAs($admin)
        ->from(route('admin.rps.review', $fixture['rps_id']))
        ->post(route('admin.rps.review.store', $fixture['rps_id']), [
            'status' => 'revision_required',
            'note' => '',
        ]);

    $response->assertRedirect(route('admin.rps.review', $fixture['rps_id']));
    $response->assertSessionHasErrors('note');
    expect(DB::table('rps_reviews')->count())->toBe(0);
});

test('draft RPS cannot be approved', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer, 'draft');

    $response = $this->actingAs($admin)
        ->from(route('admin.rps.review', $fixture['rps_id']))
        ->post(route('admin.rps.review.store', $fixture['rps_id']), [
            'status' => 'approved',
            'note' => 'Sudah sesuai.',
        ]);

    $response->assertRedirect(route('admin.rps.review', $fixture['rps_id']));
    $response->assertSessionHasErrors('status');
    expect(DB::table('rps_reviews')->count())->toBe(0);
});

test('final RPS can be approved and becomes the current follow up status', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer, 'final');

    $response = $this->actingAs($admin)
        ->from(route('admin.rps.review', $fixture['rps_id']))
        ->post(route('admin.rps.review.store', $fixture['rps_id']), [
            'status' => 'approved',
            'note' => 'RPS disetujui untuk digunakan.',
        ]);

    $response->assertRedirect(route('admin.rps.review', $fixture['rps_id']));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('rps_reviews', [
        'rps_version_id' => $fixture['version_id'],
        'reviewer_id' => $admin->id,
        'status' => 'approved',
    ]);

    $monitoring = $this->actingAs($admin)
        ->get(route('admin.users.monitoring', $lecturer));

    $monitoring->assertInertia(fn (Assert $page) => $page
        ->where('rpsItems.0.id', $fixture['rps_id'])
        ->where('rpsItems.0.review_status', 'approved')
        ->where('rpsItems.0.review_outdated', false)
        ->where('summary.review_approved', 1)
    );
});