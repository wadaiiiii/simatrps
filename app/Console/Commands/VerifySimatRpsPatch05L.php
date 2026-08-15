<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class VerifySimatRpsPatch05L extends Command
{
    protected $signature = 'simatrps:verify-patch05l';
    protected $description = 'Verifikasi AI Flow Alignment Patch 05L';

    public function handle(): int
    {
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $ctx = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['CPMK sama jadi KEEP', str_contains($ai, 'hanya mengklasifikasikan level Bloom')],
            ['No-op CPMK auto close', str_contains($ai, 'tidak ada perubahan substantif')],
            ['AI Bahan Kajian backend', str_contains($ai, 'applyMaterialPlan')],
            ['AI Bahan Kajian schema', str_contains($groq, 'materialSchema')],
            ['Context AI minggu ringkas', str_contains($ctx, 'buildWeekContext')],
            ['Sub-CPMK minggu deterministik', str_contains($ai, 'targetSubCpmkForWeek')],
            ['Waktu SKS deterministik', str_contains($ai, 'defaultTimeEstimate')],
            ['Asesmen AI >100 diblok', str_contains($ai, 'Transaksi dibatalkan')],
            ['UI hasil per bagian', str_contains($ui, 'cpmkAiSuggestions') && str_contains($ui, 'assessmentAiSuggestions')],
            ['UI Bahan Kajian AI', str_contains($ui, 'Telaah Bahan Kajian AI')],
            ['UI rapikan Sub-CPMK', str_contains($ui, 'Rapikan Alur Sub-CPMK')],
            ['UI waktu SKS', str_contains($ui, 'Terapkan Waktu')],
            ['UI metode + waktu', str_contains($ui, 'Bentuk / Metode / Waktu')],
            ['Route rapikan Sub-CPMK', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/weeks/align-subcpmk')],
            ['Route waktu SKS', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/weeks/apply-time-standard')],
        ];

        $this->table(['Komponen', 'Status'], array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks));

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05L siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05L yang belum siap.');
        return self::FAILURE;
    }
}
