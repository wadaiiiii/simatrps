<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch04D extends Command
{
    protected $signature = 'simatrps:verify-patch04d';
    protected $description = 'Memverifikasi hotfix Patch 04D CPL scope';

    public function handle(): int
    {
        $checks = [
            ['rps_additional_cpls', Schema::hasTable('rps_additional_cpls')],
            ['RpsCplScopeController.php', file_exists(app_path('Http/Controllers/RpsCplScopeController.php'))],
            ['RpsController.php', file_exists(app_path('Http/Controllers/RpsController.php'))],
            ['ObeWorkspaceController.php', file_exists(app_path('Http/Controllers/ObeWorkspaceController.php'))],
            ['rps/show.tsx', file_exists(resource_path('js/pages/rps/show.tsx'))],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 04D siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 04D yang belum siap.');
        return self::FAILURE;
    }
}
