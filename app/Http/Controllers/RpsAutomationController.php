<?php

namespace App\Http\Controllers;

use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsSmartDraftService;
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
        RpsSmartDraftService $service
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

        return back()->with(
            'success',
            "RPS berhasil dilengkapi otomatis. {$result['updated_weeks']} pertemuan diperbarui."
        );
    }

    public function allocateSubCpmkMeetings(
        Request $request,
        string $rps
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

                $update = [
                    'rps_sub_cpmk_id' => $newSubId,
                    'source_type' => 'manual_allocation',
                    'updated_at' => now(),
                ];

                // Jika alokasi menggeser baris yang sebelumnya dihasilkan otomatis,
                // kosongkan konten turunan agar tidak terjadi Sub-CPMK baru dengan
                // materi/indikator milik Sub-CPMK lama. Isian manual/AI dosen tidak
                // dihapus; dosen tetap dapat meninjaunya.
                if (
                    $oldSubId !== $newSubId
                    && in_array($oldSource, ['smart_draft', 'manual_allocation'], true)
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

        return back()->with(
            'success',
            'Alokasi pertemuan Sub-CPMK disimpan. Lengkapi RPS Otomatis akan mengikuti jumlah pertemuan yang ditetapkan dosen.'
        );
    }

    public function copyPrevious(
        Request $request,
        string $rps,
        int $week,
        RpsSmartDraftService $service
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $service->copyPreviousWeek($version->id, $week);

        return back()->with('success', "Minggu {$week} menyalin draft minggu sebelumnya.");
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
