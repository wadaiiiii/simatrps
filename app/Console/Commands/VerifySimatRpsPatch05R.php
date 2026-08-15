<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05R extends Command
{
    protected $signature = 'simatrps:verify-patch05r';
    protected $description = 'Verifikasi Full RPS Evaluation & RTM Patch 05R';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $doc = file_get_contents(app_path('Http/Controllers/RpsDocumentController.php'));
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['Tanggal terbit tersedia', Schema::hasColumn('rps_document_meta', 'published_date')],
            ['Tabel simulasi tersedia', Schema::hasTable('rps_weekly_simulations')],
            ['Header UNSULBAR lengkap', str_contains($ui, 'UNIVERSITAS SULAWESI BARAT') && str_contains($ui, 'FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM')],
            ['Top RPS sesuai format baru', str_contains($ui, 'RENCANA PEMBELAJARAN SEMESTER (RPS)') && str_contains($ui, 'Tgl. Terbit')],
            ['Otorisasi 3 peran', str_contains($ui, 'Nama Koordinator Pengembang RPS') && str_contains($ui, 'Koordinator Mata Kuliah') && str_contains($ui, 'Koord. Program Studi')],
            ['Tabel penilaian CPL', str_contains($ui, 'Tabel Penilaian dan Evaluasi CPL')],
            ['Matriks bobot asesmen', str_contains($ui, 'Bobot per Bentuk Penilaian') && str_contains($ui, 'Total Bobot')],
            ['Simulasi nilai', str_contains($ui, 'TOTAL NILAI AKHIR') && str_contains($ui, 'HURUF MUTU')],
            ['Nilai simulasi editable', str_contains($ui, 'function SimulationScoreInput')],
            ['Skala nilai', str_contains($ui, '85 – 100') && str_contains($ui, 'A-') && str_contains($ui, 'Nilai Mutu')],
            ['RTM siap cetak', str_contains($ui, 'LEMBAR RENCANA TUGAS MAHASISWA') && str_contains($ui, 'METODE PENGERJAAN TUGAS')],
            ['RTM punya luaran & jadwal', str_contains($ui, 'BENTUK DAN FORMAT LUARAN') && str_contains($ui, 'JADWAL PELAKSANAAN')],
            ['AI Asesmen + RTM kontekstual', str_contains($ui, 'Telaah Asesmen + RTM AI')],
            ['Route simulasi', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/simulation/{week}')],
            ['Simulation prop', str_contains($rps, "'simulationScores' => \$simulationScores")],
            ['Controller simpan simulasi', str_contains($doc, 'updateSimulationScore')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05R siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05R yang belum siap.');
        return self::FAILURE;
    }
}
