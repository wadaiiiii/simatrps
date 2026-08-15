<?php

use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ObeWorkspaceController;
use App\Http\Controllers\RpsController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', EnsureUserIsActive::class])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::prefix('rps')->name('rps.')->group(function (): void {
        Route::get('/', [RpsController::class, 'index'])->name('index');
        Route::get('baru', [RpsController::class, 'create'])->name('create');
        Route::post('/', [RpsController::class, 'store'])->name('store');
        Route::get('{rps}', [RpsController::class, 'show'])->name('show');
        Route::put('{rps}/cpmk-cpl', [ObeWorkspaceController::class, 'saveCpmkCpl'])->name('cpmk-cpl.update');
        Route::post('{rps}/sub-cpmk', [ObeWorkspaceController::class, 'storeSubCpmk'])->name('sub-cpmk.store');
        Route::delete('{rps}/sub-cpmk/{subCpmk}', [ObeWorkspaceController::class, 'destroySubCpmk'])->name('sub-cpmk.destroy');
        Route::post('{rps}/materials', [ObeWorkspaceController::class, 'storeMaterial'])->name('materials.store');
        Route::post('{rps}/materials/import-syllabus', [ObeWorkspaceController::class, 'importSyllabusMaterials'])->name('materials.import-syllabus');
        Route::put('{rps}/weeks/{week}', [ObeWorkspaceController::class, 'updateWeek'])->name('weeks.update');
    });
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('kurikulum', CurriculumController::class)->name('curriculum');
    Route::inertia('template-rps', 'admin/templates')->name('templates');
    Route::inertia('pengguna', 'admin/users')->name('users');
});

require __DIR__.'/settings.php';
