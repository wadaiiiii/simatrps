from pathlib import Path

provider = Path('app/Services/Rps/AiRpsProviderService.php')
controller = Path('app/Http/Controllers/RpsAiController.php')
automation = Path('app/Http/Controllers/RpsAutomationController.php')

# 1) Weekly AI: one provider per request + a smaller per-provider HTTP timeout.
text = provider.read_text()
old = '''        // Susun AI per pekan berjalan di Vercel Function dengan maxDuration 60 detik.
        // Batasi dua provider per request agar fallback serial tidak melewati batas runtime.
        // Provider yang gagal/rate-limited akan masuk cooldown sehingga percobaan berikutnya
        // dapat bergerak ke provider sehat berikutnya.
        return $this->generateAcrossProviders(
            fn ($service) => $service->generateWeeklyBatch(
                $context,
                [$week],
                $instruction
            ),
            2
        );
    }

    private function generateWeeklyPlan'''
new = '''        // Susun AI per pekan harus selesai jauh sebelum batas Vercel Function.
        // Satu request hanya mencoba satu provider. Bila provider gagal, provider tersebut
        // masuk cooldown dan klik berikutnya bergerak ke provider sehat berikutnya.
        // Timeout HTTP per provider juga diperkecil khusus request pekanan.
        $this->applyWeeklyTimeoutBudget(14);

        return $this->generateAcrossProviders(
            fn ($service) => $service->generateWeeklyBatch(
                $context,
                [$week],
                $instruction
            ),
            1
        );
    }

    private function applyWeeklyTimeoutBudget(int $seconds): void
    {
        foreach (['groq', 'mistral', 'sambanova', 'openrouter', 'huggingface', 'cohere'] as $provider) {
            $key = 'simatrps-ai.'.$provider.'.timeout';
            $current = (int) config($key, 22);
            config([$key => max(5, min($current, $seconds))]);
        }
    }

    private function generateWeeklyPlan'''
if old not in text:
    raise SystemExit('provider weekly marker not found')
text = text.replace(old, new, 1)
provider.write_text(text)

# 2) Catch the whole per-week endpoint, not just the provider call.
text = controller.read_text()
old_sig = '''    public function generateWeek(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
'''
wrapper = '''    public function generateWeek(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        try {
            return $this->generateWeekInternal(
                $request,
                $rps,
                $week,
                $aiProvider,
                $contextService
            );
        } catch (ValidationException $error) {
            throw $error;
        } catch (\\Throwable $error) {
            report($error);

            throw ValidationException::withMessages([
                'ai' => 'Susun AI Pekan '.$week.' belum berhasil diproses. '
                    .'Request dihentikan dengan aman agar tidak menjadi Server Error 500. '
                    .'Coba sekali lagi; provider yang bermasalah akan dilewati melalui cooldown.',
            ]);
        }
    }

    private function generateWeekInternal(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
'''
if old_sig not in text:
    raise SystemExit('controller generateWeek signature not found')
text = text.replace(old_sig, wrapper, 1)
controller.write_text(text)

# 3) Reset endpoint: convert unexpected DB/reset failures to a validation notice.
text = automation.read_text()
old_update = '''        $updated = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', $teachingWeeks)
            ->update([
                'assessment_indicator' => null,
                'assessment_criteria' => null,
                'assessment_method' => null,
                'learning_form' => null,
                'learning_method' => null,
                'face_to_face_sessions' => 0,
                'learning_activity' => null,
                'independent_study_sessions' => 0,
                'student_assignment' => null,
                'structured_task_sessions' => 0,
                'online_activity' => null,
                'material_text' => null,
                'reference_text' => null,
                'time_estimate' => null,
                // Tetap anggap struktur pekan berasal dari alokasi pertemuan,
                // sehingga Isi Bagian Kosong dapat menyusun ulang dari awal.
                'source_type' => 'manual_allocation_auto',
                'updated_at' => now(),
            ]);
'''
new_update = '''        try {
            $updated = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->whereIn('week_number', $teachingWeeks)
                ->update([
                    'assessment_indicator' => null,
                    'assessment_criteria' => null,
                    'assessment_method' => null,
                    'learning_form' => null,
                    'learning_method' => null,
                    'face_to_face_sessions' => 0,
                    'learning_activity' => null,
                    'independent_study_sessions' => 0,
                    'student_assignment' => null,
                    'structured_task_sessions' => 0,
                    'online_activity' => null,
                    'material_text' => null,
                    'reference_text' => null,
                    'time_estimate' => null,
                    // Tetap anggap struktur pekan berasal dari alokasi pertemuan,
                    // sehingga Isi Data Teknis / Susun AI dapat menyusun ulang dari awal.
                    'source_type' => 'manual_allocation_auto',
                    'updated_at' => now(),
                ]);
        } catch (Throwable $error) {
            report($error);

            throw ValidationException::withMessages([
                'weeks' => 'Isi pekanan belum berhasil dikosongkan. Tidak ada data yang sengaja dihapus sebagian. Muat ulang halaman lalu coba kembali.',
            ]);
        }
'''
if old_update not in text:
    raise SystemExit('automation clear update marker not found')
text = text.replace(old_update, new_update, 1)
automation.write_text(text)
