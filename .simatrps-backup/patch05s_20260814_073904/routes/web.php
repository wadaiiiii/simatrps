<?php

use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ObeWorkspaceController;
use App\Http\Controllers\RpsAssessmentController;
use App\Http\Controllers\RpsAiController;
use App\Http\Controllers\RpsCplScopeController;
use App\Http\Controllers\RpsAutomationController;
use App\Http\Controllers\RpsController;
use App\Http\Controllers\RpsDeleteController;
use App\Http\Controllers\RpsDocumentController;
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
        Route::delete('{rps}', [RpsDeleteController::class, 'destroy'])->name('destroy');

        Route::put('{rps}/document-meta', [RpsDocumentController::class, 'updateMeta'])->name('document-meta.update');
        Route::put('{rps}/simulation/{week}', [RpsDocumentController::class, 'updateSimulationScore'])->name('simulation.update');
        Route::put('{rps}/weeks/{week}/weight', [RpsDocumentController::class, 'updateWeekWeight'])->name('weeks.weight.update');

        Route::post('{rps}/cpmk', [ObeWorkspaceController::class, 'storeCpmk'])->name('cpmk.store');
        Route::put('{rps}/cpmk/{cpmk}', [ObeWorkspaceController::class, 'updateCpmk'])->name('cpmk.update');
        Route::post('{rps}/cpmk/{cpmk}/reset', [ObeWorkspaceController::class, 'resetCpmk'])->name('cpmk.reset');
        Route::delete('{rps}/cpmk/{cpmk}', [ObeWorkspaceController::class, 'destroyCpmk'])->name('cpmk.destroy');

        Route::put('{rps}/cpmk-cpl', [ObeWorkspaceController::class, 'saveCpmkCpl'])->name('cpmk-cpl.update');

        Route::post('{rps}/cpl-scope', [RpsCplScopeController::class, 'store'])->name('cpl-scope.store');
        Route::delete('{rps}/cpl-scope/{cpl}', [RpsCplScopeController::class, 'destroy'])->name('cpl-scope.destroy');

        Route::post('{rps}/sub-cpmk', [ObeWorkspaceController::class, 'storeSubCpmk'])->name('sub-cpmk.store');
        Route::put('{rps}/sub-cpmk/{subCpmk}', [ObeWorkspaceController::class, 'updateSubCpmk'])->name('sub-cpmk.update');
        Route::delete('{rps}/sub-cpmk/{subCpmk}', [ObeWorkspaceController::class, 'destroySubCpmk'])->name('sub-cpmk.destroy');

        Route::post('{rps}/materials', [ObeWorkspaceController::class, 'storeMaterial'])->name('materials.store');
        Route::put('{rps}/materials/{material}', [ObeWorkspaceController::class, 'updateMaterial'])->name('materials.update');
        Route::post('{rps}/materials/import-syllabus', [ObeWorkspaceController::class, 'importSyllabusMaterials'])->name('materials.import-syllabus');
        Route::delete('{rps}/materials/{material}', [ObeWorkspaceController::class, 'destroyMaterial'])->name('materials.destroy');

        Route::put('{rps}/weeks/{week}', [ObeWorkspaceController::class, 'updateWeek'])->name('weeks.update');

        Route::post('{rps}/ai/suggestions', [RpsAiController::class, 'generate'])->name('ai.generate');
        Route::post('{rps}/ai/weeks/{week}', [RpsAiController::class, 'generateWeek'])->name('ai.week.generate');
        Route::post('{rps}/ai/suggestions/{suggestion}/apply', [RpsAiController::class, 'apply'])->name('ai.apply');
        Route::post('{rps}/ai/suggestions/{suggestion}/reject', [RpsAiController::class, 'reject'])->name('ai.reject');

        Route::post('{rps}/smart-draft', [RpsAutomationController::class, 'smartDraft'])->name('smart-draft');
        Route::post('{rps}/weeks/{week}/copy-previous', [RpsAutomationController::class, 'copyPrevious'])->name('weeks.copy-previous');
        Route::post('{rps}/weeks/apply-method', [RpsAutomationController::class, 'applyMethod'])->name('weeks.apply-method');
        Route::post('{rps}/weeks/align-subcpmk', [ObeWorkspaceController::class, 'alignSubCpmkSequence'])->name('weeks.align-subcpmk');
        Route::post('{rps}/weeks/apply-time-standard', [ObeWorkspaceController::class, 'applyTimeStandard'])->name('weeks.apply-time-standard');
        Route::post('{rps}/weeks/normalize-references', [ObeWorkspaceController::class, 'normalizeReferences'])->name('weeks.normalize-references');
        Route::post('{rps}/validate-obe', [RpsAutomationController::class, 'validateObe'])->name('validate-obe');

        Route::post('{rps}/assessments', [RpsAssessmentController::class, 'store'])->name('assessments.store');
        Route::put('{rps}/assessments/{assessment}', [RpsAssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('{rps}/assessments/{assessment}', [RpsAssessmentController::class, 'destroy'])->name('assessments.destroy');

        Route::post('{rps}/tasks', [RpsTaskController::class, 'store'])->name('tasks.store');
        Route::put('{rps}/tasks/{task}', [RpsTaskController::class, 'update'])->name('tasks.update');
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
