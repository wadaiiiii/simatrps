<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05Q extends Command
{
    protected $signature = 'simatrps:verify-patch05q';
    protected $description = 'Verifikasi Compact Editor, References & Weight Flow Patch 05Q';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $index = file_get_contents(resource_path('js/pages/rps/index.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $obe = file_get_contents(app_path('Http/Controllers/ObeWorkspaceController.php'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $smart = file_get_contents(app_path('Services/Rps/RpsSmartDraftService.php'));
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['Pustaka pendukung tersimpan', Schema::hasColumn('rps_document_meta', 'supporting_reference_text')],
            ['Delete CPMK tampil', str_contains($ui, 'Hapus CPMK')],
            ['Delete Sub-CPMK tampil', str_contains($ui, 'Hapus Sub-CPMK')],
            ['Matrix jelas baris/kolom', str_contains($ui, 'Baris ↓ CPMK') && str_contains($ui, 'Kolom → Sub-CPMK')],
            ['Mapping auto-collapse', str_contains($ui, 'onSaveMapping(() => setMappingOpen(false))')],
            ['Sidebar auto icon', str_contains($ui, 'setSidebarOpen(false)')],
            ['Pustaka utama + pendukung UI', str_contains($ui, 'Pustaka Utama') && str_contains($ui, 'Pustaka Pendukung')],
            ['Dosen pengampu multi-baris', str_contains($ui, 'Satu dosen per baris')],
            ['Normalizer pustaka multi-ref', str_contains($obe, 'referenceComparableText') && str_contains($obe, '$authorScore')],
            ['Normalizer pakai RPS refs', str_contains($obe, 'supporting_reference_text')],
            ['Smart Draft pakai kode [n]', str_contains($smart, 'referencesForPosition')],
            ['Smart Draft variasi referensi', str_contains($smart, '($position + 3) % $count')],
            ['AI weekly minta 2-4 pustaka', str_contains(file_get_contents(app_path('Services/Rps/GroqRpsService.php')), '2-4 kode')],
            ['AI asesmen >100 tidak dibatalkan', str_contains($ai, 'Rekomendasi tetap diterapkan')],
            ['Validator tetap kontrol 100%', str_contains(file_get_contents(app_path('Services/Rps/ObeWorkspaceService.php')), 'Total bobot pada tabel RPS')],
            ['Buka RPS lebih menonjol', str_contains($index, 'Buka RPS') && str_contains($index, 'bg-teal-700')],
            ['Sidebar collapsible icon tersedia', str_contains(file_get_contents(resource_path('js/components/app-sidebar.tsx')), 'collapsible="icon"')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05Q siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05Q yang belum siap.');
        return self::FAILURE;
    }
}
