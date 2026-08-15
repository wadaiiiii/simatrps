<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05I extends Command
{
    protected $signature = 'simatrps:verify-patch05i';
    protected $description = 'Verifikasi Smart CPL Mapping, selective assessment, dan multi-provider AI';

    public function handle(): int
    {
        $controller = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $config = file_get_contents(config_path('simatrps-ai.php'));

        $checks = [
            ['Smart CPMK-CPL schema', str_contains($groq, 'cplMappingSchema')],
            ['Smart CPMK-CPL apply', str_contains($controller, 'applyCplMapping')],
            ['Selective asesmen', str_contains($controller, 'selected_assessment_indices')],
            ['Selective RTM', str_contains($controller, 'selected_task_indices')],
            ['UI checklist asesmen', str_contains($ui, 'onToggleAssessment')],
            ['UI checklist RTM', str_contains($ui, 'onToggleTask')],
            ['UI Pemetaan AI', str_contains($ui, 'Rekomendasi Pemetaan AI')],
            ['Mistral service', file_exists(app_path('Services/Rps/MistralRpsService.php'))],
            ['Cohere service', file_exists(app_path('Services/Rps/CohereRpsService.php'))],
            ['Provider chain', str_contains($provider, 'configuredProviders')],
            ['Gemini keluar dari chain', str_contains($config, "'groq,mistral,cohere'")],
            ['Backup config command', file_exists(app_path('Console/Commands/ConfigureSimatRpsBackupAi.php'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05I siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05I yang belum siap.');
        return self::FAILURE;
    }
}
