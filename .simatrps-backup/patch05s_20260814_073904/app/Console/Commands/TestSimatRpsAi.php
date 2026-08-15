<?php

namespace App\Console\Commands;

use App\Services\Rps\AiRpsProviderService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TestSimatRpsAi extends Command
{
    protected $signature = 'simatrps:ai-test';
    protected $description = 'Tes provider utama dan backup AI SiMatRPS';

    public function handle(AiRpsProviderService $service): int
    {
        try {
            $primary = $service->testPrimary();
        } catch (ValidationException $e) {
            $this->error(
                collect($e->errors())->flatten()->first()
                ?: 'Tes AI utama gagal.'
            );
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Koneksi AI utama berhasil.');
        $this->line('Provider: '.strtoupper((string) ($primary['provider'] ?? '-')));
        $this->line('Model   : '.($primary['model'] ?? '-'));

        $backups = $service->testBackups();

        if ($backups === []) {
            $this->newLine();
            $this->line('Backup AI belum dikonfigurasi. Groq tetap dapat digunakan sendiri.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Tes backup AI:');

        foreach ($backups as $name => $result) {
            if ($result['ok']) {
                $this->line('  [OK] '.strtoupper($name).' / '.($result['result']['model'] ?? '-'));
            } else {
                $this->warn('  [GAGAL] '.strtoupper($name).' / '.$result['error']);
            }
        }

        return self::SUCCESS;
    }
}
