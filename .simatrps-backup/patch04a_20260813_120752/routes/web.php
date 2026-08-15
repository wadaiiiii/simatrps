<?php

use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ObeWorkspaceController;
use App\Http\Controllers\RpsAssessmentController;
use App\Http\Controllers\RpsAutomationController;
use App\Http\Controllers\RpsController;
use App\Http\Controllers\RpsTaskController;
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
        Route::delete('{rps}/materials/{material}', [ObeWorkspaceController::class, 'destroyMaterial'])->name('materials.destroy');

        Route::put('{rps}/weeks/{week}', [ObeWorkspaceController::class, 'updateWeek'])->name('weeks.update');

        Route::post('{rps}/smart-draft', [RpsAutomationController::class, 'smartDraft'])->name('smart-draft');
        Route::post('{rps}/weeks/{week}/copy-previous', [RpsAutomationController::class, 'copyPrevious'])->name('weeks.copy-previous');
        Route::post('{rps}/weeks/apply-method', [RpsAutomationController::class, 'applyMethod'])->name('weeks.apply-method');
        Route::post('{rps}/validate-obe', [RpsAutomationController::class, 'validateObe'])->name('validate-obe');

        Route::post('{rps}/assessments', [RpsAssessmentController::class, 'store'])->name('assessments.store');
        Route::put('{rps}/assessments/{assessment}', [RpsAssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('{rps}/assessments/{assessment}', [RpsAssessmentController::class, 'destroy'])->name('assessments.destroy');

        Route::post('{rps}/tasks', [RpsTaskController::class, 'store'])->name('tasks.store');
        Route::delete('{rps}/tasks/{task}', [RpsTaskController::class, 'destroy'])->name('tasks.destroy');
    });
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('kurikulum', CurriculumController::class)->name('curriculum');
        Route::inertia('template-rps', 'admin/templates')->name('templates');
        Route::inertia('pengguna', 'admin/users')->name('users');
    });

require __DIR__.'/settings.php';
