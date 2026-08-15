<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch03 extends Command
{
    protected $signature = 'simatrps:verify-patch03';
    protected $description = 'Memverifikasi struktur database dan file inti Patch 03';

    public function handle(): int
    {
        $checks = [
            ['rps_cpmk_cpls', Schema::hasTable('rps_cpmk_cpls')],
            ['rps_sub_cpmks', Schema::hasTable('rps_sub_cpmks')],
            ['rps_cpmk_subcpmks', Schema::hasTable('rps_cpmk_subcpmks')],
            ['rps_materials', Schema::hasTable('rps_materials')],
            ['rps_weekly_plans.rps_sub_cpmk_id', Schema::hasColumn('rps_weekly_plans', 'rps_sub_cpmk_id')],
            ['RpsController.php', file_exists(app_path('Http/Controllers/RpsController.php'))],
            ['ObeWorkspaceController.php', file_exists(app_path('Http/Controllers/ObeWorkspaceController.php'))],
            ['ObeWorkspaceService.php', file_exists(app_path('Services/Rps/ObeWorkspaceService.php'))],
            ['rps/show.tsx', file_exists(resource_path('js/pages/rps/show.tsx'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(
                fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'],
                $checks
            )
        );

        $ok = collect($checks)->every(fn (array $row) => $row[1]);

        if ($ok) {
            $this->info('Patch 03 siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 03 yang belum siap.');
        return self::FAILURE;
    }
}
