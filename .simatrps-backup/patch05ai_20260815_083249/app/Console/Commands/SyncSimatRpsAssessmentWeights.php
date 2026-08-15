<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncSimatRpsAssessmentWeights extends Command
{
    protected $signature = 'simatrps:sync-assessment-weights
                            {--rps= : Opsional, hanya sinkronkan satu ID RPS}';

    protected $description = 'Sinkronkan bobot asesmen detail ke assessment_weight setiap pekan RPS';

    public function handle(): int
    {
        if (! Schema::hasTable('assessments')
            || ! Schema::hasTable('rps_weekly_plans')
            || ! Schema::hasTable('rps_versions')) {
            $this->error('Tabel asesmen/RPS belum tersedia.');
            return self::FAILURE;
        }

        $rpsId = trim((string) $this->option('rps'));

        $versions = DB::table('rps_versions')
            ->when(
                $rpsId !== '',
                fn ($query) => $query->where('rps_id', $rpsId)
            )
            ->pluck('id');

        if ($versions->isEmpty()) {
            $this->warn(
                $rpsId !== ''
                    ? "Versi RPS untuk ID {$rpsId} tidak ditemukan."
                    : 'Tidak ada versi RPS yang ditemukan.'
            );
            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($versions, &$updated): void {
            foreach ($versions as $versionId) {
                for ($week = 1; $week <= 16; $week++) {
                    $sum = round(
                        (float) DB::table('assessments')
                            ->where('rps_version_id', $versionId)
                            ->where('week_number', $week)
                            ->sum(DB::raw('COALESCE(weight, 0)')),
                        2
                    );

                    $updated += DB::table('rps_weekly_plans')
                        ->where('rps_version_id', $versionId)
                        ->where('week_number', $week)
                        ->update([
                            'assessment_weight' => $sum,
                            'updated_at' => now(),
                        ]);
                }
            }
        });

        $this->info(
            "Sinkronisasi selesai. {$versions->count()} versi RPS diproses; {$updated} baris pekan diperbarui."
        );

        return self::SUCCESS;
    }
}
