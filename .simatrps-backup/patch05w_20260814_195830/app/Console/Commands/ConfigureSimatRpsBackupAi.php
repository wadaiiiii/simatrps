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

        $providers = [
            [
                'title' => 'Backup 1: Mistral AI',
                'prompt' => 'Masukkan MISTRAL_API_KEY (kosong = pertahankan konfigurasi lama)',
                'key' => 'MISTRAL_API_KEY',
                'model_key' => 'MISTRAL_MODEL',
                'model' => 'mistral-small-latest',
                'url_key' => 'MISTRAL_BASE_URL',
                'url' => 'https://api.mistral.ai/v1',
            ],
            [
                'title' => 'Backup 2: SambaNova Cloud',
                'prompt' => 'Masukkan SAMBANOVA_API_KEY (kosong = lewati/pertahankan)',
                'key' => 'SAMBANOVA_API_KEY',
                'model_key' => 'SAMBANOVA_MODEL',
                'model' => 'Meta-Llama-3.3-70B-Instruct',
                'url_key' => 'SAMBANOVA_BASE_URL',
                'url' => 'https://api.sambanova.ai/v1',
            ],
            [
                'title' => 'Backup 3: OpenRouter',
                'prompt' => 'Masukkan OPENROUTER_API_KEY (default model: openrouter/free)',
                'key' => 'OPENROUTER_API_KEY',
                'model_key' => 'OPENROUTER_MODEL',
                'model' => 'openrouter/free',
                'url_key' => 'OPENROUTER_BASE_URL',
                'url' => 'https://openrouter.ai/api/v1',
            ],
            [
                'title' => 'Backup 4: Hugging Face Inference Providers',
                'prompt' => 'Masukkan HF_TOKEN yang memiliki izin Inference Providers',
                'key' => 'HF_TOKEN',
                'model_key' => 'HF_MODEL',
                'model' => 'Qwen/Qwen3-4B-Thinking-2507:fastest',
                'url_key' => 'HF_BASE_URL',
                'url' => 'https://router.huggingface.co/v1',
            ],
            [
                'title' => 'Backup 5: Cohere',
                'prompt' => 'Masukkan COHERE_API_KEY (kosong = pertahankan konfigurasi lama)',
                'key' => 'COHERE_API_KEY',
                'model_key' => 'COHERE_MODEL',
                'model' => 'command-a-plus-05-2026',
                'url_key' => 'COHERE_BASE_URL',
                'url' => 'https://api.cohere.ai/compatibility/v1',
            ],
        ];

        foreach ($providers as $provider) {
            $this->newLine();
            $this->info($provider['title']);

            $apiKey = $this->secret($provider['prompt']);

            if (filled($apiKey)) {
                $this->setEnv($envPath, $provider['key'], $apiKey);
                $this->setEnv($envPath, $provider['model_key'], $provider['model']);
                $this->setEnv($envPath, $provider['url_key'], $provider['url']);
                $this->line('  Disimpan: '.$provider['model']);
            } else {
                $this->line('  Dilewati. Nilai .env lama tidak dihapus.');
            }
        }

        $chain = 'groq,mistral,sambanova,openrouter,huggingface,cohere';

        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER', 'groq');
        $this->setEnv($envPath, 'SIMATRPS_AI_PROVIDER_CHAIN', $chain);

        Artisan::call('config:clear');

        $this->newLine();
        $this->info('Konfigurasi backup AI disimpan.');
        $this->line('Urutan fallback: Groq -> Mistral -> SambaNova -> OpenRouter -> Hugging Face -> Cohere');
        $this->line('Provider tanpa API key/token otomatis dilewati.');

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
