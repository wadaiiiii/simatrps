<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05W extends Command
{
    protected $signature = 'simatrps:verify-patch05w';
    protected $description = 'Verifikasi Patch 05W UI, Sidebar, AI Pustaka, dan normalisasi indikator';

    public function handle(): int
    {
        $show = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $doc = file_get_contents(app_path('Http/Controllers/RpsDocumentController.php'));
        $ctx = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));

        $checks = [
            ['Sidebar gradient baru', str_contains($sidebar, 'bg-gradient-to-b')],
            ['Label Platform dihapus', ! str_contains($sidebar, '>Platform<')],
            ['Info prodi dipindah ke atas', str_contains($sidebar, 'Program Studi Matematika<br />')],
            ['Button Telaah Pustaka AI', str_contains($show, 'Telaah Pustaka AI')],
            ['Route AI pustaka tersedia', str_contains($routes, "document-meta/ai-references")],
            ['Controller AI pustaka tersedia', str_contains($doc, 'generateAiReferences')],
            ['Context reference_plan tersedia', str_contains($ctx, "'reference_plan' =>")],
            ['Provider schema reference_plan tersedia', str_contains($groq, 'referenceSchema()')],
            ['Normalisasi istilah Siswa', str_contains($show, 'normalizeAcademicTerm')],
            ['Font tabel evaluasi diperbesar', str_contains($show, 'border-collapse text-[11px]')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05W siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05W yang belum siap.');
        return self::FAILURE;
    }
}
