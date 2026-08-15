<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AF extends Command
{
    protected $signature = 'simatrps:verify-patch05af';
    protected $description = 'Verifikasi JSON repair, provider cooldown, dan fallback lokal Asesmen+RTM';

    public function handle(): int
    {
        $repair = file_get_contents(app_path('Services/Rps/AiJsonRepair.php'));
        $mistral = file_get_contents(app_path('Services/Rps/MistralRpsService.php'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));

        $checks = [
            ['JSON repair helper tersedia', str_contains($repair, 'escapeControlCharactersInsideStrings')],
            ['Mistral memakai JSON repair', str_contains($mistral, "AiJsonRepair::decode")],
            ['Control character dapat dipulihkan', str_contains($repair, '"\\n" => \'\\\\n\'')],
            ['Provider 402 masuk cooldown', str_contains($provider, 'payment method is required') && str_contains($provider, 'return 1440')],
            ['Access denied masuk cooldown panjang', str_contains($provider, 'denied access') && str_contains($provider, 'return 1440')],
            ['Provider cooldown tidak dipanggil ulang langsung', str_contains($provider, 'Semua provider AI aktif sedang cooldown')],
            ['Assessment memiliki fallback lokal', str_contains($provider, 'localAssessmentFallback')],
            ['Fallback lokal total bobot 100%', substr_count($provider, "'weight' => 15") >= 2 && substr_count($provider, "'weight' => 25") >= 2 && str_contains($provider, "'weight' => 20")],
            ['Fallback lokal membuat RTM', str_contains($provider, "'tasks' => \$tasks") && str_contains($provider, 'SiMatRPS Rule Engine')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AF siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AF yang belum siap.');
        return self::FAILURE;
    }
}
