<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05T extends Command
{
    protected $signature = 'simatrps:verify-patch05t';
    protected $description = 'Verifikasi Teal Header, Logo, Auto Tanggal Terbit, Compact CPMK & AI Backup';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $aiConfig = config('simatrps-ai');

        $checks = [
            ['Logo UNSULBAR tersedia', file_exists(public_path('logo-unsulbar.png'))],
            ['Judul universitas presisi tengah', str_contains($ui, 'grid-cols-[110px_1fr_110px]')],
            ['Jurusan Matematika tampil', str_contains($ui, 'JURUSAN MATEMATIKA')],
            ['Header warna sistem', str_contains($ui, 'from-teal-50 via-cyan-50 to-sky-50')],
            ['Tanggal Terbit default hari ini', str_contains($rps, "'published_date' => \$storedMeta?->published_date") && str_contains($rps, "now()->toDateString()")],
            ['Tanggal Terbit tetap editable', str_contains($ui, 'Tanggal Terbit') && str_contains($ui, "form.setData('published_date'")],
            ['CPMK + Bloom no-wrap', str_contains($ui, 'w-[175px] min-w-[175px]') && str_contains($ui, "cpmk.code.replace('-', ' ')")],
            ['Sub-CPMK + Bloom + CPMK no-wrap', str_contains($ui, 'w-[210px] min-w-[210px]') && str_contains($ui, "sub.code.replace('-', ' ')")],
            ['Mistral ada pada fallback', in_array('mistral', $aiConfig['provider_chain'] ?? [], true)],
            ['Cohere ada pada fallback', in_array('cohere', $aiConfig['provider_chain'] ?? [], true)],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05T siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05T yang belum siap.');
        return self::FAILURE;
    }
}
