<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-config';
    protected $description = 'Konfigurasi Groq Free API untuk AI Assistant SiMatRPS';

    public function handle(): int
    {
        $models = [
            'openai/gpt-oss-120b',
            'openai/gpt-oss-20b',
        ];

        $currentModel = env('GROQ_MODEL', 'openai/gpt-oss-120b');

        $this->info('Konfigurasi AI SiMatRPS menggunakan Groq API (server-side).');
        $this->line('Untuk tahap awal gunakan Free Plan Groq.');

        $key = $this->secret(
            'Masukkan GROQ_API_KEY (kosongkan untuk mempertahankan key Groq yang sudah ada)'
        );

        $model = $this->choice(
            'Pilih model',
            $models,
            array_search($currentModel, $models, true) ?: 0
        );

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        if (filled($key)) {
            $this->setEnv($envPath, 'GROQ_API_KEY', $key);
        }

        $this->setEnv($envPath, 'GROQ_MODEL', $model);
        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'groq');
        $this->setEnv($envPath, 'GROQ_BASE_URL', 'https://api.groq.com/openai/v1');
        $this->setEnv($envPath, 'GROQ_TIMEOUT', '120');
        $this->setEnv($envPath, 'GROQ_MAX_OUTPUT_TOKENS', '6000');

        Artisan::call('config:clear');

        $this->newLine();
        $this->info('Konfigurasi AI Groq tersimpan.');
        $this->line('Model: '.$model);
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
