<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05Z extends Command
{
    protected $signature = 'simatrps:verify-patch05z';
    protected $description = 'Verifikasi UI cleanup Patch 05Z';

    public function handle(): int
    {
        $show = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));
        $nav = file_get_contents(resource_path('js/components/nav-main.tsx'));

        $checks = [
            ['Program Studi di bawah brand tanpa box', str_contains($sidebar, 'Program Studi Matematika') && ! str_contains($sidebar, 'rounded-xl border border-white/10 bg-white/5')],
            ['Tidak ada label Platform sidebar', ! str_contains($sidebar, 'Platform') && ! str_contains($nav, 'Platform')],
            ['Logo collapse dipusatkan', str_contains($sidebar, 'group-data-[collapsible=icon]:mx-auto') && str_contains($sidebar, 'group-data-[collapsible=icon]:size-11')],
            ['Sidebar brand gradient konsisten', str_contains($sidebar, "from-[#04182d]") && str_contains($sidebar, "to-[#075a56]")],
            ['Cek keterkaitan bahan kajian dihapus', ! str_contains($show, 'Cek keterkaitan Bahan Kajian ↔ Sub-CPMK')],
            ['Cakupan bahan kajian tidak memenuhi UI utama', ! str_contains($show, 'Cakupan {materialCoveredSubIds.length}/{subCpmks.length} Sub-CPMK')],
            ['Pustaka utama sejajar toolbar', str_contains($show, 'mb-1 flex flex-wrap items-start justify-between gap-2')],
            ['Telaah Pustaka AI tetap tersedia', str_contains($show, 'Telaah Pustaka AI')],
            ['Font dokumen kembali 11px', str_contains($show, 'font-sans text-[11px] leading-[1.45]')],
            ['Tabel mingguan 11px', str_contains($show, 'border-spacing-0 text-[11px] leading-[1.45]')],
            ['RTM 11px', str_contains($show, 'border-collapse font-sans text-[11px] leading-[1.45]')],
            ['Panel editor OBE tetap terlihat', str_contains($show, 'Buka Editor')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05Z siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05Z yang belum siap.');
        return self::FAILURE;
    }
}
