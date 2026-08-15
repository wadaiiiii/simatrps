<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AI extends Command
{
    protected $signature = 'simatrps:verify-patch05ai';
    protected $description = 'Verifikasi UI editor Pustaka yang presisi';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));

        $checks = [
            [
                'Pustaka editor memakai lebar penuh',
                str_contains($ui, 'function PustakaInlineTools')
                    && str_contains($ui, '<div className="w-full print:hidden">')
            ],
            [
                'Editor utama/pendukung seimbang',
                str_contains($ui, 'xl:grid-cols-2')
                    && str_contains($ui, 'Pustaka Utama')
                    && str_contains($ui, 'Pustaka Pendukung')
            ],
            [
                'Tinggi textarea konsisten',
                substr_count($ui, 'h-36 w-full resize-y') >= 2
            ],
            [
                'Action footer rapi',
                str_contains($ui, 'Simpan Pustaka')
                    && str_contains($ui, '>Batal<')
            ],
            [
                'Header pustaka memberi ruang penuh ke editor',
                str_contains($ui, 'min-w-0 flex-1')
            ],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(
                fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'],
                $checks
            )
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AI siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AI yang belum siap.');
        return self::FAILURE;
    }
}
