<?php

namespace App\Console\Commands;

use App\Services\Rps\GroqRpsService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TestSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-test';
    protected $description = 'Tes koneksi OpenAI API SiMatRPS tanpa membuat RPS';

    public function handle(GroqRpsService $service): int
    {
        try {
            $result = $service->testConnection();
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?: 'Tes AI gagal.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Koneksi Groq API berhasil.');
        $this->line('Model: '.$result['model']);

        return self::SUCCESS;
    }
}
