<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-config';
    protected $description = 'Konfigurasi Gemini Free sebagai AI utama SiMatRPS dan Groq sebagai fallback';

    public function handle(): int
    {
        $models = [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ];

        $currentModel = env('GEMINI_MODEL', 'gemini-2.5-flash');

        $this->info('Konfigurasi AI SiMatRPS: Gemini Free (utama) + Groq (cadangan).');
        $this->line('Gemini API key disimpan server-side di .env, bukan di browser.');

        $key = $this->secret(
            'Masukkan GEMINI_API_KEY (kosongkan untuk mempertahankan key Gemini yang sudah ada)'
        );

        $model = $this->choice(
            'Pilih model Gemini',
            $models,
            array_search($currentModel, $models, true) ?: 0
        );

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        if (filled($key)) {
            $this->setEnv($envPath, 'GEMINI_API_KEY', $key);
        }

        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'gemini');
        $this->setEnv($envPath, 'SIMATRPS_AI_FALLBACK', 'true');
        $this->setEnv($envPath, 'GEMINI_MODEL', $model);
        $this->setEnv($envPath, 'GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');
        $this->setEnv($envPath, 'GEMINI_TIMEOUT', '120');

        // GROQ_API_KEY dan GROQ_MODEL lama sengaja tidak dihapus.
        Artisan::call('config:clear');

        $this->newLine();
        $this->info('Konfigurasi AI Gemini tersimpan.');
        $this->line('Primary : Gemini / '.$model);
        $this->line('Fallback: Groq '.(filled(env('GROQ_API_KEY')) ? 'tersedia' : 'belum dikonfigurasi'));
        $this->line('Selanjutnya jalankan: herd php artisan simatrps:ai-test');

        return self::SUCCESS;
    }

    private function setEnv(string $path, string $key, string $value): void
    {
        $contents = file_get_contents($path);
        $line = $key.'='.$this->quote($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $contents);
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
