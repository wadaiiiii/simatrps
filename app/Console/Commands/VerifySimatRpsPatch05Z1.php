<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05Z1 extends Command
{
    protected $signature = 'simatrps:verify-patch05z1';
    protected $description = 'Verifikasi hotfix black screen halaman RPS';

    public function handle(): int
    {
        $show = file_get_contents(resource_path('js/pages/rps/show.tsx'));

        $checks = [
            ['Variabel materialCoveredSubIds sudah hilang', ! str_contains($show, 'materialCoveredSubIds')],
            ['Variabel uncoveredMaterialSubs sudah hilang', ! str_contains($show, 'uncoveredMaterialSubs')],
            ['Bagian Bahan Kajian tetap tersedia', str_contains($show, 'Bahan Kajian:')],
            ['Telaah Bahan Kajian AI tetap tersedia', str_contains($show, 'Telaah Bahan Kajian AI')],
            ['Telaah Pustaka AI tetap tersedia', str_contains($show, 'Telaah Pustaka AI')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05Z1 siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen hotfix yang belum siap.');
        return self::FAILURE;
    }
}
