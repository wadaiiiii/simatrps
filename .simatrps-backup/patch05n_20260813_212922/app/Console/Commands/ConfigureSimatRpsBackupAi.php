<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ConfigureSimatRpsBackupAi extends Command
{
    protected $signature = 'simatrps:ai-config-backups';
    protected $description = 'Konfigurasi Mistral dan Cohere sebagai backup AI SiMatRPS';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        $this->info('Backup 1: Mistral AI');
        $mistralKey = $this->secret(
            'Masukkan MISTRAL_API_KEY (kosongkan jika belum ingin mengaktifkan)'
        );

        if (filled($mistralKey)) {
            $this->setEnv($envPath, 'MISTRAL_API_KEY', $mistralKey);
            $this->setEnv($envPath, 'MISTRAL_MODEL', 'mistral-small-latest');
            $this->setEnv($envPath, 'MISTRAL_BASE_URL', 'https://api.mistral.ai/v1');
        }

        $this->newLine();
        $this->info('Backup 2: Cohere');
        $cohereKey = $this->secret(
            'Masukkan COHERE_API_KEY (kosongkan jika belum ingin mengaktifkan)'
        );

        if (filled($cohereKey)) {
            $this->setEnv($envPath, 'COHERE_API_KEY', $cohereKey);
            $this->setEnv($envPath, 'COHERE_MODEL', 'command-a-plus-05-2026');
            $this->setEnv($envPath, 'COHERE_BASE_URL', 'https://api.cohere.ai/compatibility/v1');
        }

        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'groq');
        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER_CHAIN', 'groq,mistral,cohere');

        Artisan::call('config:clear');

        $this->info('Konfigurasi backup disimpan.');
        $this->line('Urutan: Groq -> Mistral -> Cohere');
        $this->line('Provider tanpa API key otomatis dilewati.');

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
