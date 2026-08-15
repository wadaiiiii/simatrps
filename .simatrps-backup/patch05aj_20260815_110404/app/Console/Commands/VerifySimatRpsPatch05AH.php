<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AH extends Command
{
    protected $signature = 'simatrps:verify-patch05ah';
    protected $description = 'Verifikasi hard sync bobot asesmen ke tabel RPS';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $assessment = file_get_contents(
            app_path('Http/Controllers/RpsAssessmentController.php')
        );
        $rps = file_get_contents(
            app_path('Http/Controllers/RpsController.php')
        );
        $sync = file_get_contents(
            app_path('Console/Commands/SyncSimatRpsAssessmentWeights.php')
        );

        $checks = [
            [
                'Input bobot RPS mengikuti prop server terbaru',
                str_contains($ui, "useEffect(() => {\n        form.setData('weight', original)")
            ],
            [
                'Input bobot diremount ketika bobot berubah',
                str_contains($ui, 'key={`weight-${week.week_number}-${Number(week.assessment_weight || 0)}`}')
            ],
            [
                'Simpan asesmen reload bobot RPS',
                str_contains($ui, 'Bobot RPS ikut disinkronkan.')
                    && str_contains($ui, "only: ['weeks', 'assessments', 'progress', 'simulationScores']")
            ],
            [
                'Backend update asesmen sinkron ke pekan',
                str_contains($assessment, 'syncWeekPrintWeight')
                    && str_contains($assessment, "sum(DB::raw('COALESCE(weight, 0)'))")
            ],
            [
                'RPS memakai jumlah asesmen sebagai canonical weight',
                str_contains($rps, '$assessmentWeightsByWeek->has($weekNumber)')
            ],
            [
                'Command repair data lama tersedia',
                str_contains($sync, 'simatrps:sync-assessment-weights')
            ],
            [
                'Bobot total >100 tidak diblokir di detail asesmen',
                ! str_contains($assessment, '$this->assertWeightWithinLimit(')
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
            $this->info('Patch 05AH siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AH yang belum siap.');
        return self::FAILURE;
    }
}
