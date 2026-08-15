<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AC extends Command
{
    protected $signature = 'simatrps:verify-patch05ac';
    protected $description = 'Verifikasi clear isian pekan dan logika simulasi bobot';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));

        $checks = [
            ['Tombol Kosongkan pekan tersedia', str_contains($ui, 'clearWeekFields') && str_contains($ui, '>Kosongkan<')],
            ['Clear mempertahankan Sub-CPMK', str_contains($ui, 'rps_sub_cpmk_id: form.data.rps_sub_cpmk_id')],
            ['Bobot nol tampil kosong', str_contains($ui, "numericOriginal > 0 ? String(numericOriginal) : ''")],
            ['Bobot dapat di-clear menjadi 0', str_contains($ui, "weight === '' ? 0 : Number(weight)")],
            ['Nilai simulasi nonaktif jika bobot kosong', str_contains($ui, 'disabled={weekWeight <= 0}')],
            ['Nilai contoh hanya pekan berbobot', str_contains($ui, 'weekWeight > 0')],
            ['Huruf mutu hanya jika bobot 100%', str_contains($ui, "Math.abs(totalWeeklyWeight - 100) < 0.01")],
            ['Catatan simulasi menjelaskan bobot kosong', str_contains($ui, 'Pekan tanpa bobot ditampilkan')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AC siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AC yang belum siap.');
        return self::FAILURE;
    }
}
