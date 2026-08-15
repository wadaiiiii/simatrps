<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05D extends Command
{
    protected $signature = 'simatrps:verify-patch05d';
    protected $description = 'Memverifikasi Gemini Free + Groq fallback pada Patch 05D';

    public function handle(): int
    {
        $checks = [
            ['GeminiRpsService.php', file_exists(app_path('Services/Rps/GeminiRpsService.php'))],
            ['GroqRpsService.php', file_exists(app_path('Services/Rps/GroqRpsService.php'))],
            ['AiRpsProviderService.php', file_exists(app_path('Services/Rps/AiRpsProviderService.php'))],
            ['RpsAiContextService compact', str_contains(file_get_contents(app_path('Services/Rps/RpsAiContextService.php')), 'compactForType')],
            ['Provider utama Gemini', config('simatrps-ai.provider') === 'gemini'],
            ['Fallback aktif', (bool) config('simatrps-ai.fallback_enabled', true)],
            ['RPS Saya + Hapus', file_exists(resource_path('js/pages/rps/index.tsx'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05D siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05D yang belum siap.');
        return self::FAILURE;
    }
}
