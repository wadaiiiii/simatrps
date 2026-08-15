<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05V extends Command
{
    protected $signature = 'simatrps:verify-patch05v';
    protected $description = 'Verifikasi Bahan Kajian ↔ Sub-CPMK Alignment Patch 05V';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $obe = file_get_contents(app_path('Http/Controllers/ObeWorkspaceController.php'));
        $validator = file_get_contents(app_path('Services/Rps/ObeWorkspaceService.php'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));

        $checks = [
            ['Pivot material↔Sub-CPMK tersedia', Schema::hasTable('rps_material_subcpmks')],
            ['RPS expose multi mapping', str_contains($rps, 'sub_cpmk_ids')],
            ['Manual material multi-select', str_contains($ui, 'Mendukung Sub-CPMK')],
            ['Update material multi mapping', str_contains($obe, 'syncMaterialSubCpmks')],
            ['AI material multi mapping', str_contains($ai, 'sub_cpmk_codes')],
            ['Cakupan inline bahan kajian', str_contains($ui, 'Cakupan {materialCoveredSubIds.length}/{subCpmks.length} Sub-CPMK')],
            ['Gap Sub-CPMK terlihat', str_contains($ui, 'Belum didukung bahan kajian:')],
            ['Urutan utama tetap', strpos($ui, 'Deskripsi Singkat MK') < strpos($ui, 'Bahan Kajian:') && strpos($ui, 'Bahan Kajian:') < strpos($ui, '>Pustaka<')],
            ['Tidak ada tabel utama baru', ! str_contains($ui, 'Korelasi Bahan Kajian terhadap Sub-CPMK')],
            ['Validator cek cakupan materi', str_contains($validator, 'materialCoveredSubCount')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05V siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05V yang belum siap.');
        return self::FAILURE;
    }
}
