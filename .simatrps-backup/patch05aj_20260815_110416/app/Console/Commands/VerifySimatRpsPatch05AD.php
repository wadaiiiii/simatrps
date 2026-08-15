<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AD extends Command
{
    protected $signature = 'simatrps:verify-patch05ad';
    protected $description = 'Verifikasi Patch 05AD';

    public function handle(): int
    {
        $controller = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $smart = file_get_contents(app_path('Services/Rps/RpsSmartDraftService.php'));
        $context = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $aiController = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));

        $checks = [
            ['Pustaka awal tidak fallback master', str_contains($controller, "storedMeta?->reference_text ?? ''")],
            ['Info RPS pustaka tidak fallback master', str_contains($ui, "reference_text: meta?.reference_text ?? ''")],
            ['Ambil dari Kurikulum tetap tersedia', str_contains($ui, 'Ambil dari Kurikulum')],
            ['Save pekan request eksplisit', str_contains($ui, 'setSaving(true)') && str_contains($ui, 'belum tersimpan.')],
            ['Isi Kosong mengisi kriteria', str_contains($smart, "'assessment_criteria' => 'Ketepatan, kelengkapan")],
            ['Smart Draft tidak fallback pustaka master', ! str_contains($smart, "->value('reference_text') ?? ''")],
            ['AI pekan tidak fallback pustaka master', ! str_contains($context, "full['master_syllabus']['references']")],
            ['Provider fallback chain aktif', str_contains($provider, 'generateAcrossProviders') && str_contains($provider, "'openrouter' =>") && str_contains($provider, "'sambanova' =>")],
            ['Assessment plan memakai AI provider service', str_contains($aiController, "'assessment_plan'") && str_contains($aiController, '$aiProvider->generate(')],
            ['UI provider memakai chain aktual', str_contains($controller, 'configuredProviderNames()') && str_contains($controller, 'primaryProvider()')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AD siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AD yang belum siap.');
        return self::FAILURE;
    }
}
