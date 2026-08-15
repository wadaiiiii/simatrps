<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;

class RpsDocumentController extends Controller
{
    public function updateMeta(
        Request $request,
        string $rps
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'course_cluster' => ['nullable', 'string', 'max:255'],
            'prepared_date' => ['nullable', 'date'],
            'published_date' => ['nullable', 'date'],
            'developer_name' => ['nullable', 'string', 'max:500'],
            'coordinator_name' => ['nullable', 'string', 'max:500'],
            'head_program_name' => ['nullable', 'string', 'max:500'],
            'lecturer_names' => ['nullable', 'string', 'max:4000'],
            'software_media' => ['nullable', 'string', 'max:4000'],
            'hardware_media' => ['nullable', 'string', 'max:4000'],
            'prerequisite_text' => ['nullable', 'string', 'max:4000'],
            'description_short' => ['nullable', 'string', 'max:8000'],
            'reference_text' => ['nullable', 'string', 'max:30000'],
            'supporting_reference_text' => ['nullable', 'string', 'max:30000'],
        ]);

        $existing = DB::table('rps_document_meta')
            ->where('rps_version_id', $version->id)
            ->first();

        $values = [
            ...$data,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('rps_document_meta')
                ->where('id', $existing->id)
                ->update($values);
        } else {
            DB::table('rps_document_meta')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $version->id,
                ...$values,
                'created_at' => now(),
            ]);
        }

        if (array_key_exists('description_short', $data)) {
            DB::table('rps_versions')
                ->where('id', $version->id)
                ->update([
                    'description_short' => $data['description_short'] ?: null,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('success', 'Informasi dokumen RPS berhasil disimpan.');
    }


    public function generateAiReferences(
        Request $request,
        string $rps,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        if (! $aiProvider->isConfigured()) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum dikonfigurasi. Aktifkan minimal satu provider AI terlebih dahulu.',
            ]);
        }

        @set_time_limit(180);

        $context = $contextService->build($record, $version, 'reference_plan');
        $result = $aiProvider->generate('reference_plan', $context);
        $payload = $result['payload'] ?? [];

        $main = collect($payload['main_references'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values();

        $supporting = collect($payload['supporting_references'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->reject(fn (string $item) => $main->contains($item))
            ->unique()
            ->values();

        if ($main->isEmpty()) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan daftar pustaka utama yang valid.',
            ]);
        }

        $values = [
            'reference_text' => $main->implode("\n"),
            'supporting_reference_text' => $supporting->implode("\n"),
            'updated_at' => now(),
        ];

        $existing = DB::table('rps_document_meta')
            ->where('rps_version_id', $version->id)
            ->first();

        if ($existing) {
            DB::table('rps_document_meta')
                ->where('id', $existing->id)
                ->update($values);
        } else {
            DB::table('rps_document_meta')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $version->id,
                ...$values,
                'created_at' => now(),
            ]);
        }

        return back()->with(
            'success',
            'Pustaka berhasil ditelaah AI dan disesuaikan dengan bahan kajian aktif.'
        );
    }

    public function updateWeekWeight(
        Request $request,
        string $rps,
        int $week
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $row = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        abort_unless($row, 404);

        $newWeight = round((float) $data['weight'], 2);

        $otherTotal = round(
            (float) DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', '!=', $week)
                ->sum(DB::raw('COALESCE(assessment_weight, 0)')),
            2
        );

        $newTotal = round($otherTotal + $newWeight, 2);

        if ($newTotal > 100.0) {
            throw ValidationException::withMessages([
                'weight' => "Total bobot penilaian akan menjadi {$newTotal}%. Total tidak boleh melebihi 100%.",
            ]);
        }

        DB::transaction(function () use ($version, $row, $week, $newWeight): void {
            DB::table('rps_weekly_plans')
                ->where('id', $row->id)
                ->update([
                    'assessment_weight' => $newWeight,
                    'updated_at' => now(),
                ]);

            if (! Schema::hasTable('assessments')) {
                return;
            }

            $assessments = DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $week)
                ->get();

            // UTS/UAS wajib tersinkron dengan asesmen sistem.
            if (in_array($week, [8, 16], true)) {
                $code = $week === 8 ? 'UTS' : 'UAS';

                $query = DB::table('assessments')
                    ->where('rps_version_id', $version->id);

                if (Schema::hasColumn('assessments', 'code')) {
                    $query->where('code', $code);
                } elseif (Schema::hasColumn('assessments', 'type')) {
                    $query->where('type', strtolower($code));
                }

                $query->update([
                    'weight' => $newWeight,
                    'updated_at' => now(),
                ]);

                return;
            }

            // Bila minggu hanya mempunyai satu asesmen detail, bobot dapat
            // disinkronkan tanpa ambiguitas. Jika beberapa asesmen berada di
            // minggu yang sama, bobot tabel tetap menjadi sumber cetak.
            if ($assessments->count() === 1) {
                DB::table('assessments')
                    ->where('id', $assessments->first()->id)
                    ->update([
                        'weight' => $newWeight,
                        'updated_at' => now(),
                    ]);
            }
        });

        return back()->with(
            'success',
            "Bobot minggu {$week} disimpan. Total bobot saat ini {$newTotal}%."
        );
    }

    public function updateSimulationScore(
        Request $request,
        string $rps,
        int $week
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        abort_unless($week >= 1 && $week <= 16, 422);

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $score = ($data['score'] ?? null) === null
            || $data['score'] === ''
                ? null
                : round((float) $data['score'], 2);

        $existing = DB::table('rps_weekly_simulations')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        if ($existing) {
            DB::table('rps_weekly_simulations')
                ->where('id', $existing->id)
                ->update([
                    'score' => $score,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('rps_weekly_simulations')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $version->id,
                'week_number' => $week,
                'score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with(
            'success',
            "Nilai simulasi minggu {$week} berhasil disimpan."
        );
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')
            ->where('id', $rps)
            ->first();

        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id
                || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();

        abort_unless($version, 404);

        return [$record, $version];
    }
}
