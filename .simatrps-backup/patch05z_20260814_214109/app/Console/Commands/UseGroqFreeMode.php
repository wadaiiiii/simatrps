<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UseGroqFreeMode extends Command
{
    protected $signature = 'simatrps:ai-use-groq-free';
    protected $description = 'Gunakan Groq sebagai primary; Mistral/Cohere menjadi backup jika key tersedia';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->error('.env tidak ditemukan.');
            return self::FAILURE;
        }

        if (! filled(env('GROQ_API_KEY'))) {
            $this->error('GROQ_API_KEY belum tersedia.');
            return self::FAILURE;
        }

        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'groq');
        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER_CHAIN', 'groq,mistral,cohere');
        $this->setEnv($envPath, 'GROQ_MODEL', 'openai/gpt-oss-20b');
        $this->setEnv($envPath, 'GROQ_BASE_URL', 'https://api.groq.com/openai/v1');
        $this->setEnv($envPath, 'MISTRAL_MODEL', env('MISTRAL_MODEL', 'mistral-small-latest'));
        $this->setEnv($envPath, 'MISTRAL_BASE_URL', 'https://api.mistral.ai/v1');
        $this->setEnv($envPath, 'COHERE_MODEL', env('COHERE_MODEL', 'command-a-plus-05-2026'));
        $this->setEnv($envPath, 'COHERE_BASE_URL', 'https://api.cohere.ai/compatibility/v1');

        Artisan::call('config:clear');

        $this->info('Mode provider AI SiMatRPS diaktifkan.');
        $this->line('Primary : Groq / openai/gpt-oss-20b');
        $this->line('Backup  : Mistral -> Cohere (otomatis dilewati jika key belum ada)');
        $this->line('Gemini  : tidak dipanggil.');

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
