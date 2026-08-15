<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05F extends Command
{
    protected $signature = 'simatrps:verify-patch05f';
    protected $description = 'Verifikasi edit RTM dan struktur pembelajaran siap cetak RPS';

    public function handle(): int
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['learning_form', Schema::hasColumn('rps_weekly_plans', 'learning_form')],
            ['time_estimate', Schema::hasColumn('rps_weekly_plans', 'time_estimate')],
            ['student_assignment', Schema::hasColumn('rps_weekly_plans', 'student_assignment')],
            ['online_activity', Schema::hasColumn('rps_weekly_plans', 'online_activity')],
            ['PUT RTM tersedia', $routes->contains(fn ($route) =>
                $route->uri() === 'rps/{rps}/tasks/{task}'
                && in_array('PUT', $route->methods(), true)
            )],
            ['RTM update()', str_contains(
                file_get_contents(app_path('Http/Controllers/RpsTaskController.php')),
                'public function update'
            )],
            ['AI learning_form', str_contains(
                file_get_contents(app_path('Services/Rps/GeminiRpsService.php')),
                "'learning_form'"
            )],
            ['AI student_assignment', str_contains(
                file_get_contents(app_path('Services/Rps/GeminiRpsService.php')),
                "'student_assignment'"
            )],
            ['UI edit RTM', str_contains(
                file_get_contents(resource_path('js/pages/rps/show.tsx')),
                'Simpan Perubahan RTM'
            )],
            ['UI Bentuk Pembelajaran', str_contains(
                file_get_contents(resource_path('js/pages/rps/show.tsx')),
                'Bentuk Pembelajaran'
            )],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05F siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05F yang belum siap.');
        return self::FAILURE;
    }
}
