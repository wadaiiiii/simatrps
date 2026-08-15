<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05J extends Command
{
    protected $signature = 'simatrps:verify-patch05j';
    protected $description = 'Verifikasi sinkronisasi pemetaan AI dan penanganan timeout Patch 05J';

    public function handle(): int
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RpsAiController.php')
        );
        $ui = file_get_contents(
            resource_path('js/pages/rps/show.tsx')
        );

        $checks = [
            [
                'AI execution time 180 detik',
                str_contains($controller, 'extendAiExecutionTime')
                    && str_contains($controller, "set_time_limit(180)"),
            ],
            [
                'Provider error jadi validation error',
                str_contains($controller, 'Layanan AI tidak dapat menyelesaikan permintaan'),
            ],
            [
                'Mapping form sync server',
                str_contains($ui, 'serverMappingSignature')
                    && str_contains($ui, "mappingForm.setData('mappings', serverMappings)"),
            ],
            [
                'AI apply remount state',
                str_contains($ui, 'preserveState: false'),
            ],
            [
                'Pemetaan AI tersimpan otomatis',
                str_contains($ui, 'langsung disimpan'),
            ],
            [
                'Penjelasan arahan AI',
                str_contains($ui, 'preferensi untuk permintaan AI berikutnya'),
            ],
            [
                'Tombol bersihkan arahan AI',
                str_contains($ui, 'Bersihkan'),
            ],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(
                fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'],
                $checks
            )
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05J siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05J yang belum siap.');
        return self::FAILURE;
    }
}
