<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05G extends Command
{
    protected $signature = 'simatrps:verify-patch05g';
    protected $description = 'Verifikasi seleksi AI, stabilitas preview, dan model Gemini Patch 05G';

    public function handle(): int
    {
        $controller = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $gemini = file_get_contents(app_path('Services/Rps/GeminiRpsService.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));

        $checks = [
            ['Gemini 3.6 Flash default', str_contains($gemini, 'gemini-3.6-flash')],
            ['Weekly batch diperkecil', str_contains($provider, '[1,2,3,4]') && str_contains($provider, '[13,14,15]')],
            ['CPMK selected_indexes', str_contains($controller, "'selected_indexes'")],
            ['CPMK apply stats', str_contains($controller, 'Usulan CPMK diterapkan')],
            ['Sub-CPMK apply stats', str_contains($controller, 'Usulan Sub-CPMK diterapkan')],
            ['Hanya suggestion pending', str_contains($rps, "->where('status', 'pending')")],
            ['UI safe aiArray()', str_contains($ui, 'function aiArray')],
            ['UI Terapkan Terpilih', str_contains($ui, 'Terapkan Terpilih')],
            ['UI checkbox rekomendasi', str_contains($ui, 'selectedIndexes.includes(index)')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05G siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05G yang belum siap.');
        return self::FAILURE;
    }
}
