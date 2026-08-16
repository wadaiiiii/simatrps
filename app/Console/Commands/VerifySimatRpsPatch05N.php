<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class VerifySimatRpsPatch05N extends Command
{
    protected $signature = 'simatrps:verify-patch05n';
    protected $description = 'Verifikasi table-first RPS, inline edit, dan pustaka bernomor';

    public function handle(): int
    {
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $obe = file_get_contents(app_path('Http/Controllers/ObeWorkspaceController.php'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $ctx = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['Pustaka bernomor dari controller', str_contains($rps, "'bibliography' => \$bibliography")],
            ['Struktur RPS seperti dokumen', str_contains($ui, 'Struktur RPS') && str_contains($ui, 'Matriks Sub-CPMK → CPMK')],
            ['Edit langsung baris tabel', str_contains($ui, 'function InlineWeekRow') && str_contains($ui, 'Pencil className')],
            ['Pustaka pekanan nomor saja', str_contains($ui, 'Pustaka nomor saja')],
            ['AI menerima bibliography', str_contains($ctx, "'bibliography' => \$this->bibliographyEntries")],
            ['AI menyimpan kode pustaka', str_contains($ai, 'normalizeAiReferenceCodes')],
            ['Input manual dinormalisasi', str_contains($obe, 'normalizeReferenceCodes')],
            ['Normalisasi pustaka lama', str_contains($obe, 'normalizeReferences')],
            ['Route normalisasi pustaka', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/weeks/normalize-references')],
            ['Feedback CPMK no-change', str_contains($ui, 'CPMK saat ini sudah memadai')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05N siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05N yang belum siap.');
        return self::FAILURE;
    }
}
