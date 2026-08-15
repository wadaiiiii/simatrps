<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-config';
    protected $description = 'Konfigurasi provider AI SiMatRPS';

    public function handle(): int
    {
        $provider = $this->choice(
            'Pilih provider AI',
            [
                'Groq Free (disarankan untuk tahap pengembangan)',
                'Gemini (gunakan hanya jika project Gemini mempunyai akses API)',
            ],
            0
        );

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        if (str_starts_with($provider, 'Groq')) {
            $key = $this->secret(
                'Masukkan GROQ_API_KEY (kosongkan untuk mempertahankan key Groq yang sudah ada)'
            );

            if (filled($key)) {
                $this->setEnv($envPath, 'GROQ_API_KEY', $key);
            }

            if (! filled($key) && ! filled(env('GROQ_API_KEY'))) {
                $this->error('GROQ_API_KEY belum tersedia.');
                return self::FAILURE;
            }

            $model = $this->choice(
                'Pilih model Groq',
                [
                    'openai/gpt-oss-20b',
                    'openai/gpt-oss-120b',
                ],
                0
            );

            $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'groq');
            $this->setEnv($envPath, 'SIMATRPS_AI_FALLBACK', 'false');
            $this->setEnv($envPath, 'GROQ_MODEL', $model);
            $this->setEnv($envPath, 'GROQ_BASE_URL', 'https://api.groq.com/openai/v1');
            $this->setEnv($envPath, 'GROQ_TIMEOUT', '180');

            Artisan::call('config:clear');

            $this->info('Konfigurasi Groq tersimpan.');
            $this->line('Primary : Groq / '.$model);
            $this->line('Fallback: OFF agar error project Gemini tidak mengganggu.');
            return self::SUCCESS;
        }

        $key = $this->secret(
            'Masukkan GEMINI_API_KEY (kosongkan untuk mempertahankan key Gemini yang sudah ada)'
        );

        if (filled($key)) {
            $this->setEnv($envPath, 'GEMINI_API_KEY', $key);
        }

        if (! filled($key) && ! filled(env('GEMINI_API_KEY'))) {
            $this->error('GEMINI_API_KEY belum tersedia.');
            return self::FAILURE;
        }

        $model = $this->choice(
            'Pilih model Gemini',
            [
                'gemini-3.6-flash',
                'gemini-3.5-flash-lite',
                'gemini-3.5-flash',
            ],
            0
        );

        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'gemini');
        $this->setEnv($envPath, 'SIMATRPS_AI_FALLBACK', 'false');
        $this->setEnv($envPath, 'GEMINI_MODEL', $model);
        $this->setEnv($envPath, 'GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta');
        $this->setEnv($envPath, 'GEMINI_TIMEOUT', '120');

        Artisan::call('config:clear');

        $this->info('Konfigurasi Gemini tersimpan.');
        $this->line('Primary : Gemini / '.$model);
        $this->warn('Jika muncul "Your project has been denied access", ganti kembali ke Groq Free.');
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
