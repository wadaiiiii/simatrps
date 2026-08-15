<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05 extends Command
{
    protected $signature = 'simatrps:verify-patch05';
    protected $description = 'Verifikasi komponen AI Assistant Patch 05 SiMatRPS';

    public function handle(): int
    {
        $checks = [
            ['ai_suggestions table', Schema::hasTable('ai_suggestions')],
            ['config/simatrps-ai.php', file_exists(config_path('simatrps-ai.php'))],
            ['OpenAiRpsService.php', file_exists(app_path('Services/Rps/OpenAiRpsService.php'))],
            ['RpsAiContextService.php', file_exists(app_path('Services/Rps/RpsAiContextService.php'))],
            ['RpsAiController.php', file_exists(app_path('Http/Controllers/RpsAiController.php'))],
            ['ConfigureSimatRpsAi.php', file_exists(app_path('Console/Commands/ConfigureSimatRpsAi.php'))],
            ['TestSimatRpsAi.php', file_exists(app_path('Console/Commands/TestSimatRpsAi.php'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        $ok = collect($checks)->every(fn ($row) => $row[1]);

        if (! $ok) {
            $this->warn('Masih ada komponen Patch 05 yang belum siap.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Patch 05 siap. Status API: '.(filled(config('simatrps-ai.api_key')) ? 'TERKONFIGURASI' : 'BELUM DIKONFIGURASI'));
        $this->line('Model: '.config('simatrps-ai.model'));

        return self::SUCCESS;
    }
}
