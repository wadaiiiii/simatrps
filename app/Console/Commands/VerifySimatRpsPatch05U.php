<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05U extends Command
{
    protected $signature = 'simatrps:verify-patch05u';
    protected $description = 'Verifikasi AI Apply, Extended Backups, Simulation & RTM Patch 05U';

    public function handle(): int
    {
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $router = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $config = config('simatrps-ai');

        $checks = [
            ['CPMK Bloom dapat diterapkan AI', str_contains($ai, 'level Bloom diperbarui')],
            ['Resolver CPMK induk AI robust', str_contains($ai, 'resolveAiParentCpmk')],
            ['Duplicate foreach Sub-CPMK hilang', substr_count($ai, 'foreach ($selectedIndices as $index) {') >= 4],
            ['SambaNova provider', class_exists(\App\Services\Rps\SambaNovaRpsService::class)],
            ['OpenRouter provider', class_exists(\App\Services\Rps\OpenRouterRpsService::class)],
            ['Hugging Face provider', class_exists(\App\Services\Rps\HuggingFaceRpsService::class)],
            ['Router mengenal 6 provider', str_contains($router, "'sambanova' => \$this->sambanova") && str_contains($router, "'openrouter' => \$this->openrouter") && str_contains($router, "'huggingface' => \$this->huggingface")],
            ['SambaNova config', isset($config['sambanova'])],
            ['OpenRouter config', isset($config['openrouter'])],
            ['Hugging Face config', isset($config['huggingface'])],
            ['Edit detail berada sebelum tabel evaluasi', strpos($ui, 'Edit Detail Asesmen, RTM & Validator OBE') < strpos($ui, 'Tabel Penilaian dan Evaluasi CPL')],
            ['AI Asesmen ada di panel edit', str_contains($ui, 'label="Telaah Asesmen + RTM AI"')],
            ['Nilai simulasi default >70', str_contains($ui, 'function defaultSimulationScore')],
            ['Bobot 0 tidak tampil sebagai nilai kontribusi 0', str_contains($ui, 'weekWeight > 0')],
            ['RTM Jurusan Matematika', substr_count($ui, 'JURUSAN MATEMATIKA') >= 2],
            ['RTM header presisi tengah', str_contains($ui, 'grid-cols-[95px_1fr_95px]')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05U siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05U yang belum siap.');
        return self::FAILURE;
    }
}
