<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05X extends Command
{
    protected $signature = 'simatrps:verify-patch05x';
    protected $description = 'Verifikasi hotfix AI bahan kajian, tabel evaluasi editable, RTM editable, dan sidebar gelap';

    public function handle(): int
    {
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $assessment = file_get_contents(app_path('Http/Controllers/RpsAssessmentController.php'));
        $show = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
        $nav = file_get_contents(resource_path('js/components/nav-main.tsx'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $checks = [
            ['Hotfix Schema RpsAiController', str_contains($ai, 'Facades\\Schema')],
            ['Endpoint matrix assessment', str_contains($assessment, 'updateMatrix')],
            ['Route matrix assessment', str_contains($routes, 'assessments/{assessment}/matrix')],
            ['Matrix CPL editable', str_contains($show, 'AssessmentMatrixLinkCell')],
            ['Bobot asesmen editable', str_contains($show, 'AssessmentMatrixWeightInput')],
            ['Simulasi bobot editable', str_contains($show, 'SimulationWeightInput')],
            ['RTM editable dari lembar', str_contains($show, 'initialEditing') && str_contains($show, 'Edit RTM')],
            ['RTM font diperbesar', str_contains($show, 'text-[11.5px] leading-5')],
            ['Sidebar gradasi gelap', str_contains($sidebar, "from-[#06182f]")],
            ['Label Platform dihapus', ! str_contains($nav, 'Platform') && ! str_contains($sidebar, 'Platform')],
            ['Info prodi di atas', str_contains($sidebar, 'Program Studi Matematika')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05X siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05X yang belum siap.');
        return self::FAILURE;
    }
}
