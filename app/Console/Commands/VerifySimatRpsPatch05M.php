<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05M extends Command
{
    protected $signature = 'simatrps:verify-patch05m';
    protected $description = 'Verifikasi format tabel RPS siap cetak Patch 05M';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $controller = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $workspace = file_get_contents(app_path('Http/Controllers/ObeWorkspaceController.php'));

        $checks = [
            ['Frekuensi tatap muka', Schema::hasColumn('rps_weekly_plans', 'face_to_face_sessions')],
            ['Frekuensi tugas terstruktur', Schema::hasColumn('rps_weekly_plans', 'structured_task_sessions')],
            ['Frekuensi belajar mandiri', Schema::hasColumn('rps_weekly_plans', 'independent_study_sessions')],
            ['Header Penilaian 2 kolom', str_contains($ui, 'Kriteria & Bentuk')],
            ['Header Luring/Daring', str_contains($ui, 'Tatap muka / Luring') && str_contains($ui, '>Daring<')],
            ['Materi [Pustaka]', str_contains($ui, 'Materi Pembelajaran') && str_contains($ui, '[Pustaka]')],
            ['UTS/UAS baris gabung', str_contains($ui, 'Ujian Tengah Semester') && str_contains($ui, 'colSpan={6}')],
            ['Bobot pekanan dari asesmen', str_contains($controller, 'assessmentWeightsByWeek')],
            ['Editor sesuai kolom tabel', str_contains($ui, 'Isian Sesuai Kolom Tabel RPS')],
            ['Waktu mengikuti SKS', str_contains($ui, '50 menit') && str_contains($ui, '60 menit')],
            ['Backend simpan frekuensi', str_contains($workspace, 'face_to_face_sessions') && str_contains($workspace, 'structured_task_sessions')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05M siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05M yang belum siap.');
        return self::FAILURE;
    }
}
