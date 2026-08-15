<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AA extends Command
{
    protected $signature = 'simatrps:verify-patch05aa';
    protected $description = 'Verifikasi sinkronisasi Bahan Kajian, AI mingguan, Pustaka, dan validator';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $validator = file_get_contents(app_path('Services/Rps/ObeWorkspaceService.php'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $ctx = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));

        $checks = [
            ['Chip keterkaitan material dihapus', ! str_contains($ui, 'Mendukung Sub-CPMK')],
            ['Mapping material AI tidak ditampilkan', ! str_contains($ui, "Sub-CPMK: {safeText(item?.sub_cpmk_code")],
            ['Validator material hanya cek tersedia', str_contains($validator, "'done' => $materials > 0") && ! str_contains($validator, 'materialCoveredSubCount')],
            ['Apply AI material memastikan judul tersedia', str_contains($ai, 'diterapkan dan tersedia pada daftar materi')],
            ['AI pekan menghormati Sub-CPMK aktif', str_contains($ai, 'filled($weekly->rps_sub_cpmk_id')],
            ['AI pekan sinkron ke Bahan Kajian', str_contains($ai, 'resolveWeekMaterial')],
            ['AI pekan sinkron ke Pustaka', str_contains($ai, 'resolveWeekReferenceCodes')],
            ['Context pekan memakai semua material aktif', ! str_contains($ctx, "->where(function ($query) use ($targetSub)")],
            ['Prompt weekly wajib judul material aktif', str_contains($groq, 'WAJIB menggunakan judul yang sama persis')],
            ['Prompt weekly wajib kode bibliography', str_contains($groq, 'WAJIB pilih kode dari `bibliography`')],
            ['Panah editor asesmen satu baris', str_contains($ui, 'group-open:rotate-90') && str_contains($ui, '[&::-webkit-details-marker]:hidden')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AA siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AA yang belum siap.');
        return self::FAILURE;
    }
}
