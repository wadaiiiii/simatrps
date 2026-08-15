<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch04E extends Command
{
    protected $signature = 'simatrps:verify-patch04e';
    protected $description = 'Memverifikasi sinkronisasi validator CPMK-CPL dengan scope CPL RPS';

    public function handle(): int
    {
        $checks = [
            ['rps_additional_cpls', Schema::hasTable('rps_additional_cpls')],
            ['rps_cpmk_cpls', Schema::hasTable('rps_cpmk_cpls')],
            ['ObeWorkspaceService.php', file_exists(app_path('Services/Rps/ObeWorkspaceService.php'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        $rpsRows = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->whereNotNull('rps.current_version_id')
            ->orderByDesc('rps.updated_at')
            ->limit(10)
            ->get([
                'rps.id',
                'rps.current_version_id',
                'rps.course_id',
                'courses.name as course_name',
            ]);

        if ($rpsRows->isNotEmpty()) {
            $summary = [];

            foreach ($rpsRows as $rps) {
                $cpmkIds = DB::table('rps_cpmks')
                    ->where('rps_version_id', $rps->current_version_id)
                    ->pluck('id');

                $cpmkTotal = $cpmkIds->count();
                $cpmkMapped = $cpmkIds->isEmpty()
                    ? 0
                    : DB::table('rps_cpmk_cpls')
                        ->whereIn('rps_cpmk_id', $cpmkIds)
                        ->distinct()
                        ->count('rps_cpmk_id');

                $official = DB::table('course_cpls')
                    ->where('course_id', $rps->course_id)
                    ->pluck('cpl_id');

                $additional = DB::table('rps_additional_cpls')
                    ->where('rps_version_id', $rps->current_version_id)
                    ->pluck('cpl_id');

                $scope = $official->merge($additional)->unique()->values();

                $scopeMapped = $cpmkIds->isEmpty() || $scope->isEmpty()
                    ? 0
                    : DB::table('rps_cpmk_cpls')
                        ->whereIn('rps_cpmk_id', $cpmkIds)
                        ->whereIn('cpl_id', $scope)
                        ->distinct()
                        ->count('cpl_id');

                $ok = $cpmkTotal > 0
                    && $cpmkMapped === $cpmkTotal
                    && $scope->count() > 0
                    && $scopeMapped === $scope->count();

                $summary[] = [
                    $rps->course_name,
                    "{$cpmkMapped}/{$cpmkTotal}",
                    $official->unique()->count(),
                    $additional->unique()->count(),
                    "{$scopeMapped}/{$scope->count()}",
                    $ok ? 'OK' : 'CEK',
                ];
            }

            $this->newLine();
            $this->table(
                ['RPS', 'CPMK Terpetakan', 'CPL Kurikulum', 'CPL Tambahan', 'CPL Scope Terpakai', 'Validator'],
                $summary
            );
        }

        $ok = collect($checks)->every(fn (array $row) => $row[1]);

        if ($ok) {
            $this->info('Patch 04E siap digunakan. Validator kini menghitung CPMK dan CPL scope secara dua arah.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 04E yang belum siap.');
        return self::FAILURE;
    }
}
