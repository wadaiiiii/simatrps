<?php

namespace App\Http\Controllers;

use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsSmartDraftService;
use App\Services\Rps\RpsAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RpsAutomationController extends Controller
{
    public function smartDraft(
        Request $request,
        string $rps,
        RpsSmartDraftService $service,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['fill_empty', 'overwrite'])],
        ]);

        try {
            $result = $service->generate(
                $record,
                $version,
                $request->user()->id,
                $validated['mode'] ?? 'fill_empty'
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'smart_draft' => 'RPS belum berhasil dilengkapi otomatis. Muat ulang halaman lalu coba kembali. Jika masih gagal, gunakan AI per pekan sementara proses otomatis diperiksa.',
            ]);
        }

        $weightMessage = trim((string) ($result['weight_message'] ?? ''));
        $syncResult = $assessmentSync->syncVersion($version->id);
        $syncMessage = trim((string) ($syncResult['message'] ?? ''));

        return back()->with(
            'success',
            "Bagian kosong berhasil diisi. {$result['updated_weeks']} pertemuan diperbarui."
                .($weightMessage !== '' ? ' '.$weightMessage : '')
                .($syncMessage !== '' ? ' '.$syncMessage : '')
        );
    }

    public function allocateSubCpmkMeetings(
        Request $request,
        string $rps,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code']);

        if ($subCpmks->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tambahkan minimal satu Sub-CPMK sebelum mengatur jumlah pertemuan.',
            ]);
        }

        if ($subCpmks->count() > 14) {
            throw ValidationException::withMessages([
                'allocations' => 'Jumlah Sub-CPMK melebihi 14 pertemuan efektif. Rapikan Sub-CPMK terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['required', 'integer', 'min:1', 'max:14'],
        ]);

        $allocations = $validated['allocations'];
        $validIds = $subCpmks->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($validIds as $subId) {
            if (! array_key_exists($subId, $allocations)) {
                throw ValidationException::withMessages([
                    'allocations' => 'Setiap Sub-CPMK harus memiliki jumlah pertemuan minimal 1.',
                ]);
            }
        }

        foreach (array_keys($allocations) as $subId) {
            if (! in_array((string) $subId, $validIds, true)) {
                throw ValidationException::withMessages([
                    'allocations' => 'Terdapat Sub-CPMK yang tidak valid pada pengaturan pertemuan.',
                ]);
            }
        }

        $total = array_sum(array_map('intval', $allocations));
        if ($total !== 14) {
            throw ValidationException::withMessages([
                'allocations' => "Total pertemuan pembelajaran harus tepat 14. Saat ini totalnya {$total}.",
            ]);
        }

        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $expanded = [];
        foreach ($subCpmks as $sub) {
            $count = (int) $allocations[(string) $sub->id];
            for ($i = 0; $i < $count; $i++) {
                $expanded[] = (string) $sub->id;
            }
        }

        $currentWeeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', $teachingWeeks)
            ->get()
            ->keyBy('week_number');

        if ($currentWeeks->count() !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'allocations' => 'Struktur 14 pertemuan belum lengkap. Muat ulang RPS atau lengkapi struktur minggu terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($currentWeeks, $expanded, $teachingWeeks): void {
            foreach ($teachingWeeks as $index => $weekNumber) {
                $row = $currentWeeks->get($weekNumber);
                $newSubId = $expanded[$index];
                $oldSubId = filled($row->rps_sub_cpmk_id ?? null)
                    ? (string) $row->rps_sub_cpmk_id
                    : null;
                $oldSource = (string) ($row->source_type ?? '');
                $legacyLooksGenerated = $oldSource === 'manual_allocation'
                    && $this->legacyManualAllocationLooksGenerated($row);

                if (
                    $oldSource === 'manual'
                    || $oldSource === 'copied_previous'
                    || $oldSource === 'manual_allocation_manual'
                    || ($oldSource === 'manual_allocation' && ! $legacyLooksGenerated)
                ) {
                    $newSource = 'manual_allocation_manual';
                } elseif (
                    $oldSource === 'ai_accepted'
                    || $oldSource === 'manual_allocation_ai'
                ) {
                    $newSource = 'manual_allocation_ai';
                } else {
                    $newSource = 'manual_allocation_auto';
                }

                $update = [
                    'rps_sub_cpmk_id' => $newSubId,
                    'source_type' => $newSource,
                    'updated_at' => now(),
                ];

                // Hanya konten generator yang dikosongkan saat Sub-CPMK bergeser.
                // Lengkapi RPS Otomatis akan langsung menyusunnya kembali tanpa
                // reset manual. Isi manual dan AI tetap dilindungi.
                if (
                    $oldSubId !== $newSubId
                    && $newSource === 'manual_allocation_auto'
                ) {
                    foreach ([
                        'material_text',
                        'learning_activity',
                        'student_assignment',
                        'assessment_indicator',
                        'assessment_criteria',
                        'assessment_method',
                        'reference_text',
                    ] as $column) {
                        $update[$column] = null;
                    }
                }

                DB::table('rps_weekly_plans')
                    ->where('id', $row->id)
                    ->update($update);
            }
        });

        $assessmentSync->syncVersion($version->id);

        return back()->with(
            'success',
            'Alokasi pertemuan Sub-CPMK disimpan. Tag asesmen, bobot pekan, RTM, matriks, dan simulasi langsung disinkronkan ke alokasi terbaru.'
        );
    }

    public function copyPrevious(
        Request $request,
        string $rps,
        int $week,
        RpsSmartDraftService $service,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $service->copyPreviousWeek($version->id, $week);
        $assessmentSync->syncVersion($version->id);

        return back()->with('success', "Minggu {$week} menyalin draft minggu sebelumnya dan rantai asesmen disinkronkan.");
    }

    public function applyMethod(
        Request $request,
        string $rps,
        RpsSmartDraftService $service
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*' => ['integer', 'min:1', 'max:16'],
            'learning_method' => ['required', 'string', 'max:2000'],
        ]);

        $count = $service->applyMethod(
            $version->id,
            $validated['weeks'],
            $validated['learning_method']
        );

        return back()->with('success', "Metode pembelajaran diterapkan pada {$count} minggu.");
    }

    public function validateObe(
        Request $request,
        string $rps,
        ObeWorkspaceService $workspace
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $result = $workspace->validateAndPersist($version->id);

        return back()->with(
            'success',
            $result['is_valid']
                ? 'Validasi OBE selesai: seluruh pemeriksaan lulus.'
                : "Validasi OBE selesai: kelengkapan {$result['percent']}%."
        );
    }

    private function isManualAllocationSource(string $source): bool
    {
        return $source === 'manual_allocation'
            || str_starts_with($source, 'manual_allocation_');
    }

    private function legacyManualAllocationLooksGenerated(object $week): bool
    {
        $core = [
            trim((string) ($week->material_text ?? '')),
            trim((string) ($week->learning_activity ?? '')),
            trim((string) ($week->student_assignment ?? '')),
            trim((string) ($week->assessment_indicator ?? '')),
            trim((string) ($week->assessment_criteria ?? '')),
            trim((string) ($week->assessment_method ?? '')),
        ];

        if (collect($core)->filter(fn ($value) => $value !== '')->isEmpty()) {
            return true;
        }

        $signals = 0;
        if (preg_match('/^Mahasiswa mempelajari .+mendiskusikan contoh, dan menyelesaikan latihan yang mendukung Sub-CPMK-?\d+\.$/u', (string) ($week->learning_activity ?? '')) === 1) {
            $signals++;
        }
        if (preg_match('/^Latihan\/tugas terstruktur yang selaras dengan Sub-CPMK-?\d+\.$/u', (string) ($week->student_assignment ?? '')) === 1) {
            $signals++;
        }
        if (str_starts_with((string) ($week->assessment_criteria ?? ''), 'Ketepatan, kelengkapan, dan kesesuaian jawaban/kinerja terhadap indikator Sub-CPMK-')) {
            $signals++;
        }
        if ((string) ($week->assessment_method ?? '') === 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.') {
            $signals++;
        }
        if (
            str_contains((string) ($week->learning_method ?? ''), 'Ceramah interaktif')
            && str_contains((string) ($week->learning_method ?? ''), 'latihan terbimbing')
        ) {
            $signals++;
        }

        return $signals >= 2;
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')->where('id', $record->current_version_id)->first();
        abort_unless($version, 404);

        return [$record, $version];
    }
}
