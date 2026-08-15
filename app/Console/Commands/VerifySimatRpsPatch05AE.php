<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05AE extends Command
{
    protected $signature = 'simatrps:verify-patch05ae';
    protected $description = 'Verifikasi persistensi Preferensi AI dan fast fallback';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $provider = file_get_contents(app_path('Services/Rps/AiRpsProviderService.php'));
        $context = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $aiController = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $document = file_get_contents(app_path('Http/Controllers/RpsDocumentController.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));

        $checks = [
            ['Preferensi AI tersimpan per RPS', str_contains($ui, 'simatrps:ai-preference:') && str_contains($ui, 'localStorage.setItem')],
            ['Preferensi AI dimuat kembali', str_contains($ui, 'localStorage.getItem')],
            ['Pustaka AI menerima Preferensi AI', str_contains($ui, '{ instruction: aiInstruction }') && str_contains($document, "'instruction' => ['nullable'")],
            ['Fallback memakai cooldown cache', str_contains($provider, 'simatrps:ai:cooldown:') && str_contains($provider, 'shouldCooldown')],
            ['Error semua provider diringkas', str_contains($provider, 'Semua provider AI aktif gagal')],
            ['Rekomendasi identik tidak call AI ulang', str_contains($aiController, 'context_hash') && str_contains($aiController, 'memakai hasil yang sudah ada')],
            ['Success menyebut fallback provider', str_contains($aiController, 'Provider utama gagal/dilewati')],
            ['Context material dirampingkan', str_contains($context, 'existing_materials') && str_contains($context, 'syllabus_items')],
            ['Context asesmen dirampingkan', str_contains($context, 'current_assessments') && str_contains($context, 'weekly_evidence')],
            ['Budget material diperkecil', str_contains($groq, "'material_plan' => 850")],
            ['Budget assessment diperkecil', str_contains($groq, "'assessment_plan' => 1750")],
            ['Instruksi di luar tugas diabaikan', str_contains($groq, 'Instruksi dosen yang tidak relevan')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn ($row) => $row[1])) {
            $this->info('Patch 05AE siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05AE yang belum siap.');
        return self::FAILURE;
    }
}
