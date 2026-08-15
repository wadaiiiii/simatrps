<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class VerifySimatRpsPatch05A extends Command
{
    protected $signature = 'simatrps:verify-patch05a';
    protected $description = 'Memverifikasi Patch 05A SiMatRPS';

    public function handle(): int
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $deleteRoute = $routes->contains(
            fn ($route) =>
                in_array('DELETE', $route->methods(), true)
                && $route->uri() === 'rps/{rps}'
        );

        $checks = [
            ['RpsDeleteController.php', file_exists(app_path('Http/Controllers/RpsDeleteController.php'))],
            ['DELETE /rps/{rps}', $deleteRoute],
            ['RPS Saya UI', file_exists(resource_path('js/pages/rps/index.tsx'))],
            ['AI config command', file_exists(app_path('Console/Commands/ConfigureSimatRpsAi.php'))],
            ['OpenAI service', file_exists(app_path('Services/Rps/OpenAiRpsService.php'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05A siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05A yang belum siap.');
        return self::FAILURE;
    }
}
