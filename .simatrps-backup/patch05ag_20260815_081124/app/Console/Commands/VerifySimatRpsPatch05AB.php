<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AB extends Command
{
    protected $signature = 'simatrps:verify-patch05ab';
    protected $description = 'Verifikasi Patch 05AB';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $validator = file_get_contents(app_path('Services/Rps/ObeWorkspaceService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));

        $checks = [
            ['Ikon segitiga biru dihapus', ! str_contains($ui, '>▶<')],
            ['RTM Sub-CPMK diurutkan', str_contains($ui, '.sort((a: any, b: any) =>')],
            ['Belajar Mandiri hanya waktu', str_contains($ui, 'Bagian ini hanya menyimpan estimasi waktu belajar mandiri.')],
            ['Narasi masuk metode pembelajaran', str_contains($ui, 'Rincian Aktivitas / Strategi Pembelajaran')],
            ['AI coverage RTM aktif', str_contains($ai, 'ensureAllSubCpmksCoveredByTasks')],
            ['Validator coverage RTM aktif', str_contains($validator, 'taskCoveredSubCount')],
            ['Prompt RTM wajib seluruh Sub-CPMK', str_contains($groq, 'WAJIB mencakup semua Sub-CPMK aktif')],
            ['Prompt weekly pisahkan belajar mandiri', str_contains($groq, 'JANGAN menulis narasi belajar mandiri')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AB siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AB yang belum siap.');
        return self::FAILURE;
    }
}
