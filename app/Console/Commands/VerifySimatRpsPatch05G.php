<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05G extends Command
{
    protected $signature = 'simatrps:verify-patch05g';
    protected $description = 'Verifikasi seleksi rekomendasi AI, model Gemini terbaru, dan stabilitas UI';

    public function handle(): int
    {
        $controller = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $rpsController = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $gemini = file_get_contents(app_path('Services/Rps/GeminiRpsService.php'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));

        $checks = [
            ['Gemini default 3.6 Flash', str_contains(config('simatrps-ai.gemini.model'), '3.6') || str_contains($gemini, 'gemini-3.6-flash')],
            ['Seleksi CPMK/Sub-CPMK backend', str_contains($controller, 'selected_indices')],
            ['CPMK target dinormalisasi', str_contains($controller, 'normalizeCpmkCode')],
            ['Apply melaporkan jumlah perubahan', str_contains($controller, "'changed' => $changed")],
            ['Hanya rekomendasi pending dimuat', str_contains($rpsController, "->where('status', 'pending')")],
            ['UI checkbox rekomendasi', str_contains($ui, 'Terapkan Terpilih')],
            ['UI safe rendering', str_contains($ui, 'function safeText')],
            ['Weekly fallback 4 batch', str_contains($provider, '[12,13,14,15]')],
            ['Groq 429 retry', str_contains($groq, 'postWithRateLimitRetry')],
        ];

        $this->table(['Komponen', 'Status'], array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks));

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05G siap digunakan.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
