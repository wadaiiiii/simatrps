<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function adminUser(): User
{
    return User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function lecturerUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'dosen',
        'is_active' => true,
    ], $attributes));
}

test('admin can update lecturer account details', function () {
    $admin = adminUser();
    $lecturer = lecturerUser([
        'name' => 'Dosen Lama',
        'email' => 'lama@example.com',
        'nidn' => '0011223344',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $lecturer), [
        'name' => 'Dosen Baru',
        'academic_title' => 'M.Si.',
        'nidn' => '0099887766',
        'email' => 'baru@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'id' => $lecturer->id,
        'name' => 'Dosen Baru',
        'academic_title' => 'M.Si.',
        'nidn' => '0099887766',
        'email' => 'baru@example.com',
        'role' => 'dosen',
    ]);
});

test('admin can deactivate lecturer and revoke stored sessions', function () {
    $admin = adminUser();
    $lecturer = lecturerUser();

    DB::table('sessions')->insert([
        'id' => 'lecturer-session',
        'user_id' => $lecturer->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.users.status', $lecturer), [
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($lecturer->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseMissing('sessions', ['id' => 'lecturer-session']);
});

test('admin can reset lecturer password and revoke stored sessions', function () {
    $admin = adminUser();
    $lecturer = lecturerUser();

    DB::table('sessions')->insert([
        'id' => 'password-reset-session',
        'user_id' => $lecturer->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.users.password', $lecturer), [
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertRedirect();
    expect(Hash::check('NewPassword123!', $lecturer->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'password-reset-session']);
});

test('admin accounts are protected from lecturer management actions', function () {
    $admin = adminUser();
    $otherAdmin = adminUser();

    $this->actingAs($admin)
        ->patch(route('admin.users.status', $otherAdmin), ['is_active' => false])
        ->assertForbidden();

    expect($otherAdmin->fresh()->is_active)->toBeTrue();
});

test('lecturer cannot access admin user management', function () {
    $lecturer = lecturerUser();

    $this->actingAs($lecturer)
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('inactive lecturer cannot sign in', function () {
    $lecturer = lecturerUser(['is_active' => false]);

    $this->post(route('login.store'), [
        'email' => $lecturer->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('inactive lecturer cannot access dashboard or settings', function () {
    $lecturer = lecturerUser(['is_active' => false]);

    $this->actingAs($lecturer)
        ->get(route('dashboard'))
        ->assertForbidden();

    $this->actingAs($lecturer)
        ->get(route('profile.edit'))
        ->assertForbidden();
});
