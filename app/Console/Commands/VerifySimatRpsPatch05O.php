<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05O extends Command
{
    protected $signature = 'simatrps:verify-patch05o';
    protected $description = 'Verifikasi RPS Document Editor Patch 05O';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $doc = file_get_contents(app_path('Http/Controllers/RpsDocumentController.php'));
        $workspace = file_get_contents(app_path('Services/Rps/ObeWorkspaceService.php'));

        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['Meta dokumen tersedia', Schema::hasTable('rps_document_meta')],
            ['Bobot pekanan tersedia', Schema::hasColumn('rps_weekly_plans', 'assessment_weight')],
            ['Format RPS document-style', str_contains($ui, 'RENCANA PEMBELAJARAN SEMESTER')],
            ['Header identitas RPS lengkap', str_contains($ui, 'Dosen Pengembang RPS') && str_contains($ui, 'Koordinator RMK') && str_contains($ui, 'Ka PRODI')],
            ['CPL/CPMK/Sub-CPMK satu tabel', str_contains($ui, 'CPL-PRODI yang dibebankan pada MK') && str_contains($ui, 'Kemampuan akhir tiap tahapan belajar')],
            ['Matriks Sub-CPMK→CPMK', str_contains($ui, 'SubCpmkMatrix')],
            ['AI kontekstual di section', str_contains($ui, 'SectionAiButton')],
            ['AI tidak menambah kolom cetak', ! str_contains($ui, '>AI</th>')],
            ['Edit pekanan dalam baris', str_contains($ui, 'function DocumentWeekRow')],
            ['Bobot langsung di tabel', str_contains($ui, 'function InlineWeightInput')],
            ['UTS/UAS baris gabung', str_contains($ui, 'colSpan={6}')],
            ['Bobot validator dari tabel RPS', str_contains($workspace, 'Total bobot pada tabel RPS')],
            ['Route meta RPS', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/document-meta')],
            ['Route bobot pekanan', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/weeks/{week}/weight')],
            ['Error rate-limit diringkas', str_contains($ui, 'Kuota harian provider AI sedang penuh')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05O siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05O yang belum siap.');
        return self::FAILURE;
    }
}
