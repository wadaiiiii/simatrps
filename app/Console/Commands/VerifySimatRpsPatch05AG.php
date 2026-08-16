<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AG extends Command
{
    protected $signature = 'simatrps:verify-patch05ag';
    protected $description = 'Verifikasi sinkronisasi bobot asesmen ke tabel RPS';

    public function handle(): int
    {
        $assessment = file_get_contents(
            app_path('Http/Controllers/RpsAssessmentController.php')
        );
        $rps = file_get_contents(
            app_path('Http/Controllers/RpsController.php')
        );
        $ui = file_get_contents(
            resource_path('js/pages/rps/show.tsx')
        );

        $checks = [
            [
                'Update asesmen sinkron ke bobot pekan',
                str_contains($assessment, 'syncWeekPrintWeight')
                    && str_contains($assessment, "sum('weight')")
            ],
            [
                'Pindah pekan sinkron pekan lama dan baru',
                str_contains($assessment, '$oldWeek')
                    && substr_count($assessment, 'syncWeekPrintWeight') >= 5
            ],
            [
                'RPS memakai bobot asesmen sebagai sumber utama',
                str_contains($rps, '$assessmentWeightsByWeek->has($weekNumber)')
            ],
            [
                'Simpan detail asesmen refresh data RPS',
                str_contains($ui, 'Bobot RPS ikut disinkronkan.')
                    && str_contains($ui, "only: ['weeks', 'assessments', 'progress', 'simulationScores']")
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
            $this->info('Patch 05AG siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AG yang belum siap.');
        return self::FAILURE;
    }
}
