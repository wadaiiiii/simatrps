<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch04C extends Command
{
    protected $signature = 'simatrps:verify-patch04c';
    protected $description = 'Memverifikasi Patch 04C scope CPL RPS';

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
            array_map(
                fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'],
                $checks
            )
        );

        $ok = collect($checks)->every(fn (array $row) => $row[1]);

        if ($ok) {
            $this->info('Patch 04C siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 04C yang belum siap.');
        return self::FAILURE;
    }
}
