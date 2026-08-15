<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05B extends Command
{
    protected $signature = 'simatrps:verify-patch05b';
    protected $description = 'Memverifikasi Patch 05B Groq Free AI';

    public function handle(): int
    {
        $checks = [
            ['GroqRpsService.php', file_exists(app_path('Services/Rps/GroqRpsService.php'))],
            ['RpsAiController.php', file_exists(app_path('Http/Controllers/RpsAiController.php'))],
            ['ConfigureSimatRpsAi.php', file_exists(app_path('Console/Commands/ConfigureSimatRpsAi.php'))],
            ['TestSimatRpsAi.php', file_exists(app_path('Console/Commands/TestSimatRpsAi.php'))],
            ['config/simatrps-ai.php', file_exists(config_path('simatrps-ai.php'))],
            ['RPS Saya + Hapus', file_exists(resource_path('js/pages/rps/index.tsx'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05B siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05B yang belum siap.');
        return self::FAILURE;
    }
}
