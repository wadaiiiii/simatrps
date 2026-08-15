<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05Y extends Command
{
    protected $signature = 'simatrps:verify-patch05y';
    protected $description = 'Verifikasi Patch 05Y: visible Pustaka AI, RTM editor, font sync, assessment panel';

    public function handle(): int
    {
        $show = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $doc = file_get_contents(app_path('Http/Controllers/RpsDocumentController.php'));

        $checks = [
            ['Telaah Pustaka AI langsung di baris Pustaka', str_contains($show, 'function PustakaInlineTools') && str_contains($show, 'Telaah Pustaka AI')],
            ['Pustaka AI route aktif', str_contains($routes, 'document-meta/ai-references') && str_contains($doc, 'generateAiReferences')],
            ['Cek keterkaitan Bahan Kajian dihapus', ! str_contains($show, 'Cek keterkaitan Bahan Kajian ↔ Sub-CPMK')],
            ['Panel asesmen dibuat terang', str_contains($show, 'KLIK UNTUK BUKA EDITOR')],
            ['RTM punya editor jelas', str_contains($show, 'Edit Isi RTM') && str_contains($show, 'Semua isi lembar RTM dapat diubah')],
            ['Font RPS 12px', str_contains($show, 'font-sans text-[12px] leading-5')],
            ['Font tabel mingguan 12px', str_contains($show, 'border-spacing-0 text-[12px] leading-5')],
            ['Font RTM 12px', str_contains($show, 'border-collapse font-sans text-[12px] leading-5')],
            ['Sidebar tanpa Platform', ! str_contains($sidebar, '>Platform<')],
            ['Sidebar dark gradient', str_contains($sidebar, "from-[#06182f]") && str_contains($sidebar, "to-[#0b625d]")],
            ['Info prodi ada di atas sidebar', str_contains($sidebar, 'Program Studi Matematika')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05Y siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05Y yang belum siap.');
        return self::FAILURE;
    }
}
