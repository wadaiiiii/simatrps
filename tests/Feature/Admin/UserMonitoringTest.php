<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can open lecturer monitoring page', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.monitoring', $lecturer));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/user-monitoring')
        ->where('lecturer.id', $lecturer->id)
        ->where('lecturer.name', $lecturer->name)
        ->where('summary.total', 0)
        ->has('rpsItems', 0)
    );
});

test('lecturer cannot open another lecturer monitoring page', function () {
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $otherLecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);

    $this->actingAs($lecturer)
        ->get(route('admin.users.monitoring', $otherLecturer))
        ->assertForbidden();
});

test('administrator accounts are not valid lecturer monitoring targets', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $otherAdmin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.monitoring', $otherAdmin))
        ->assertForbidden();
});
