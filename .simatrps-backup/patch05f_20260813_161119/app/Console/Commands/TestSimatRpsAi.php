<?php

namespace App\Console\Commands;

use App\Services\Rps\AiRpsProviderService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TestSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-test';
    protected $description = 'Tes koneksi provider AI SiMatRPS';

    public function handle(AiRpsProviderService $service): int
    {
        try {
            $primary = $service->testPrimary();
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?: 'Tes AI utama gagal.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Koneksi AI utama berhasil.');
        $this->line('Provider: '.strtoupper($primary['provider'] ?? $service->primaryProvider()));
        $this->line('Model   : '.$primary['model']);

        try {
            $fallback = $service->testFallback();
            if ($fallback) {
                $this->newLine();
                $this->info('Fallback AI juga siap.');
                $this->line('Provider: '.strtoupper($fallback['provider'] ?? 'fallback'));
                $this->line('Model   : '.$fallback['model']);
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->warn('AI utama tetap siap, tetapi fallback gagal dites: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
