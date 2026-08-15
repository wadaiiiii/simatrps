<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05S extends Command
{
    protected $signature = 'simatrps:verify-patch05s';
    protected $description = 'Verifikasi Runtime Fix + Flexible Sidebar + Compact Rows';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));

        $checks = [
            ['simulationScores props', str_contains($ui, 'simulationScores = {}') && str_contains($rps, "'simulationScores' => \$simulationScores")],
            ['Runtime black-page fix', str_contains($ui, 'simulationScores={simulationScores}')],
            ['Sidebar tidak dipaksa minimize', ! str_contains($ui, 'setSidebarOpen(false)')],
            ['Sidebar toggle fleksibel', str_contains($sidebar, 'Tampilkan / minimalkan menu') && str_contains($sidebar, 'collapsible="icon"')],
            ['Dosen Pengampu compact', str_contains($ui, 'Nama Dosen 1; Nama Dosen 2; Nama Dosen 3')],
            ['Multi dosen render', str_contains($ui, 'formatLecturerNames')],
            ['CPMK + Bloom sebaris', str_contains($ui, "cpmk.code.replace('-', ' ')") && str_contains($ui, "cpmk.bloom_level || 'Bloom —'")],
            ['Sub-CPMK + Bloom + CPMK sebaris', str_contains($ui, "sub.code.replace('-', ' ')") && str_contains($ui, '{parentCode}')],
            ['Tabel simulasi ada', Schema::hasTable('rps_weekly_simulations')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05S siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen yang belum siap.');
        return self::FAILURE;
    }
}
