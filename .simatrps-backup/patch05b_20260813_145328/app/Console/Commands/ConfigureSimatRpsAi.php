<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-config';
    protected $description = 'Konfigurasi OpenAI API untuk AI Assistant SiMatRPS';

    public function handle(): int
    {
        $currentModel = env('OPENAI_MODEL', 'gpt-5-mini');

        $this->info('Konfigurasi AI SiMatRPS (server-side). API key tidak disimpan di browser.');

        $key = $this->secret('Masukkan OPENAI_API_KEY (kosongkan untuk mempertahankan key yang sudah ada)');

        $model = $this->choice(
            'Pilih model',
            ['gpt-5-mini', 'gpt-5', 'gpt-5-nano'],
            array_search($currentModel, ['gpt-5-mini', 'gpt-5', 'gpt-5-nano'], true) ?: 0
        );

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        if (filled($key)) {
            $this->setEnv($envPath, 'OPENAI_API_KEY', $key);
        }

        $this->setEnv($envPath, 'OPENAI_MODEL', $model);
        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'openai');
        $this->setEnv($envPath, 'OPENAI_TIMEOUT', '120');
        $this->setEnv($envPath, 'OPENAI_MAX_OUTPUT_TOKENS', '12000');

        Artisan::call('config:clear');

        $this->newLine();
        $this->info('Konfigurasi AI tersimpan.');
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
