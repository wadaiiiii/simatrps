<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch04 extends Command
{
    protected $signature = 'simatrps:verify-patch04';
    protected $description = 'Memverifikasi struktur Patch 04 SiMatRPS';

    public function handle(): int
    {
        $checks = [
            ['assessments', Schema::hasTable('assessments')],
            ['assessment_subcpmks', Schema::hasTable('assessment_subcpmks')],
            ['rps_tasks', Schema::hasTable('rps_tasks')],
            ['rps_task_subcpmks', Schema::hasTable('rps_task_subcpmks')],
            ['obe_validation_results', Schema::hasTable('obe_validation_results')],
            ['ai_suggestions', Schema::hasTable('ai_suggestions')],
            ['RpsSmartDraftService.php', file_exists(app_path('Services/Rps/RpsSmartDraftService.php'))],
            ['RpsAutomationController.php', file_exists(app_path('Http/Controllers/RpsAutomationController.php'))],
            ['RpsAssessmentController.php', file_exists(app_path('Http/Controllers/RpsAssessmentController.php'))],
            ['RpsTaskController.php', file_exists(app_path('Http/Controllers/RpsTaskController.php'))],
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
            $this->info('Patch 04 siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 04 yang belum siap.');
        return self::FAILURE;
    }
}
