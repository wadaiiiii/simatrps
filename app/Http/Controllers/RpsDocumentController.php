<?php

namespace App\Http\Controllers;

use App\Services\Rps\RpsAssessmentSyncService;
use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RpsDocumentController extends Controller
{
    public function updateMeta(
        Request $request,
        string $rps
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'course_cluster' => ['required', 'string', 'max:255'],
            'prepared_date' => ['required', 'date'],
            'published_date' => ['required', 'date'],
            'developer_name' => ['required', 'string', 'max:500'],
            'coordinator_name' => ['required', 'string', 'max:500'],
            'head_program_name' => ['required', 'string', 'max:500'],
            'lecturer_names' => ['required', 'string', 'max:4000'],
            'software_media' => ['required', 'string', 'max:4000'],
            'hardware_media' => ['required', 'string', 'max:4000'],
            'prerequisite_text' => ['required', 'string', 'max:4000'],
            'description_short' => ['required', 'string', 'max:8000'],
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

        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }
        @ini_set('max_execution_time', '60');

        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:3000'],
        ]);

        $context = $contextService->build($record, $version, 'reference_plan');
        $result = $aiProvider->generate(
            'reference_plan',
            $context,
            $data['instruction'] ?? null
        );
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

        $providerUsed = strtoupper((string) ($result['provider'] ?? 'AI'));
        $fallbackNote = (bool) ($result['fallback_used'] ?? false)
            ? " Provider utama dilewati/gagal; hasil dibuat dengan {$providerUsed}."
            : " Provider: {$providerUsed}.";

        return back()->with(
            'success',
            'Pustaka berhasil ditelaah AI dan disesuaikan dengan bahan kajian aktif.'
                .$fallbackNote
        );
    }

    public function updateWeekWeight(
        Request $request,
        string $rps,
        int $week,
        RpsAssessmentSyncService $sync
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        abort_unless($week >= 1 && $week <= 16, 422);

        $data = $request->validate([
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $newWeight = round((float) $data['weight'], 2);
        $result = $sync->rebalanceTeachingWeek(
            $version->id,
            $week,
            $newWeight
        );

        $distribution = collect($result['distribution'] ?? [])
            ->map(fn ($weight, $number) => 'P'.$number.'='.$weight.'%')
            ->implode(', ');

        return back()->with(
            'success',
            "Bobot Pekan {$week} disimpan {$newWeight}%. Anggaran {$result['assessment_name']} untuk {$result['sub_code']} tetap {$result['group_budget']}% (total asesmen {$result['assessment_budget']}%); distribusi: {$distribution}."
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
            "Nilai simulasi pekan {$week} berhasil disimpan."
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
