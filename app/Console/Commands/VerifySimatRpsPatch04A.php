<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch04A extends Command
{
    protected $signature = 'simatrps:verify-patch04a';
    protected $description = 'Memverifikasi file inti Patch 04A';

    public function handle(): int
    {
        $checks = [
            ['RpsSyllabusService.php', file_exists(app_path('Services/Rps/RpsSyllabusService.php'))],
            ['RpsSmartDraftService.php', file_exists(app_path('Services/Rps/RpsSmartDraftService.php'))],
            ['ObeWorkspaceController.php', file_exists(app_path('Http/Controllers/ObeWorkspaceController.php'))],
            ['RpsAssessmentController.php', file_exists(app_path('Http/Controllers/RpsAssessmentController.php'))],
            ['RpsController.php', file_exists(app_path('Http/Controllers/RpsController.php'))],
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
            $this->info('Patch 04A siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Ada file Patch 04A yang belum siap.');
        return self::FAILURE;
    }
}
