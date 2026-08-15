<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', EnsureUserIsActive::class])->group(function (): void {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('rps')->name('rps.')->group(function (): void {
        Route::inertia('/', 'rps/index')->name('index');
        Route::inertia('baru', 'rps/create')->name('create');
    });
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::inertia('kurikulum', 'admin/curriculum')->name('curriculum');
        Route::inertia('template-rps', 'admin/templates')->name('templates');
        Route::inertia('pengguna', 'admin/users')->name('users');
    });

require __DIR__.'/settings.php';
