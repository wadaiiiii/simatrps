<?php

namespace App\Http\Controllers;

use App\Services\Rps\RpsAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpsTaskController extends Controller
{
    public function store(Request $request, string $rps, RpsAssessmentSyncService $sync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'assessment_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in([
                'assignment', 'project', 'practicum', 'presentation', 'other',
            ])],
            'purpose' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'expected_output' => ['nullable', 'string', 'max:3000'],
            'due_week' => ['nullable', 'integer', 'min:1', 'max:16'],
            'sub_cpmk_ids' => ['nullable', 'array'],
            'sub_cpmk_ids.*' => ['uuid'],
        ]);

        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }

        $next = (int) DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->count() + 1;

        $id = (string) Str::uuid();

        DB::transaction(function () use ($id, $version, $validated, $next, $request): void {
            DB::table('rps_tasks')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'assessment_id' => $validated['assessment_id'] ?: null,
                'code' => 'RTM-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'type' => $validated['type'],
                'purpose' => $validated['purpose'] ?: null,
                'instructions' => $validated['instructions'] ?: null,
                'expected_output' => $validated['expected_output'] ?: null,
                'due_week' => $validated['due_week'] ?: null,
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $allowed = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->pluck('id')
                ->all();

            foreach (array_unique($validated['sub_cpmk_ids'] ?? []) as $subId) {
                if (! in_array($subId, $allowed, true)) {
                    continue;
                }

                DB::table('rps_task_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_task_id' => $id,
                    'rps_sub_cpmk_id' => $subId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $sync->syncVersion($version->id);

        return back()->with('success', 'RTM berhasil ditambahkan. Satu RTM dapat mengukur satu atau lebih Sub-CPMK dalam cakupan asesmen induk; pekan hanya menjadi jadwal pengumpulan.');
    }


    public function update(Request $request, string $rps, string $task, RpsAssessmentSyncService $sync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $existing = DB::table('rps_tasks')
            ->where('id', $task)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($existing, 404);

        $validated = $request->validate([
            'assessment_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in([
                'assignment', 'project', 'practicum', 'presentation', 'other',
            ])],
            'purpose' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'expected_output' => ['nullable', 'string', 'max:3000'],
            'due_week' => ['nullable', 'integer', 'min:1', 'max:16'],
            'sub_cpmk_ids' => ['nullable', 'array'],
            'sub_cpmk_ids.*' => ['uuid'],
        ]);

        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }

        $allowedSubIds = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($task, $validated, $allowedSubIds): void {
            DB::table('rps_tasks')
                ->where('id', $task)
                ->update([
                    'assessment_id' => ($validated['assessment_id'] ?? null) ?: null,
                    'title' => $validated['title'],
                    'type' => $validated['type'],
                    'purpose' => ($validated['purpose'] ?? null) ?: null,
                    'instructions' => ($validated['instructions'] ?? null) ?: null,
                    'expected_output' => ($validated['expected_output'] ?? null) ?: null,
                    'due_week' => ($validated['due_week'] ?? null) ?: null,
                    'source_type' => 'manual',
                    'updated_at' => now(),
                ]);

            DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $task)
                ->delete();

            foreach (array_unique($validated['sub_cpmk_ids'] ?? []) as $subId) {
                if (! in_array($subId, $allowedSubIds, true)) {
                    continue;
                }

                DB::table('rps_task_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_task_id' => $task,
                    'rps_sub_cpmk_id' => $subId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $sync->syncVersion($version->id);

        return back()->with('success', 'RTM berhasil diperbarui. Cakupan Sub-CPMK RTM dipertahankan independen dari pekan pengumpulan dan tetap berada dalam asesmen induk.');
    }

    public function destroy(Request $request, string $rps, string $task, RpsAssessmentSyncService $sync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $existing = DB::table('rps_tasks')
            ->where('id', $task)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($existing, 404);

        if (filled($existing->assessment_id ?? null)) {
            $assessment = DB::table('assessments')
                ->where('id', $existing->assessment_id)
                ->where('rps_version_id', $version->id)
                ->first(['id', 'code', 'name', 'type', 'weight']);

            $requiresRtm = $assessment
                && in_array(strtolower((string) $assessment->type), [
                    'assignment', 'project', 'practicum', 'presentation',
                ], true)
                && (float) ($assessment->weight ?? 0) > 0;

            if ($requiresRtm) {
                $otherRtmCount = DB::table('rps_tasks')
                    ->where('rps_version_id', $version->id)
                    ->where('assessment_id', $assessment->id)
                    ->where('id', '!=', $task)
                    ->count();

                if ($otherRtmCount === 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'task' => 'RTM ini masih menjadi satu-satunya RTM untuk asesmen '
                            .trim((string) ($assessment->code ?? 'Asesmen')).' “'
                            .trim((string) $assessment->name)
                            .'”. Untuk menghapus RTM ini, buka Detail Asesmen → '
                            .trim((string) ($assessment->code ?? 'Asesmen')).' “'
                            .trim((string) $assessment->name)
                            .'”, lalu ubah atau hapus asesmen tersebut terlebih dahulu. Jika hanya ingin memindahkan jadwal, ubah Pekan Pengumpulan RTM.',
                    ]);
                }
            }
        }

        DB::table('rps_tasks')
            ->where('id', $task)
            ->where('rps_version_id', $version->id)
            ->delete();

        $sync->syncVersion($version->id);

        return back()->with('success', 'RTM berhasil dihapus dan distribusi asesmen-pekan disinkronkan ulang.');
    }

    private function applyAssessmentDefaults(array $validated, string $versionId): array
    {
        $assessment = DB::table('assessments')
            ->where('id', $validated['assessment_id'])
            ->where('rps_version_id', $versionId)
            ->first(['id', 'name', 'type', 'week_number']);

        if (! $assessment) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assessment_id' => 'Asesmen RTM tidak valid untuk RPS ini.',
            ]);
        }

        $type = strtolower((string) $assessment->type);
        $rtmTypes = ['assignment', 'project', 'practicum', 'presentation'];

        if (! in_array($type, $rtmTypes, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assessment_id' => 'Pilih asesmen tugas, proyek, praktikum, atau presentasi untuk RTM.',
            ]);
        }

        $validated['type'] = $type;
        $assessmentSubIds = DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessment->id)
            ->pluck('rps_sub_cpmk_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        if ($requestedSubIds->isEmpty()) {
            // Default aman: satu RTM mewarisi seluruh cakupan asesmen induk.
            // Dosen tetap dapat memilih sebagian Sub-CPMK melalui editor RTM.
            $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
        } else {
            $outsideAssessment = $requestedSubIds
                ->reject(fn ($id) => $assessmentSubIds->contains($id))
                ->values();

            if ($outsideAssessment->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sub_cpmk_ids' => 'RTM hanya boleh mengukur Sub-CPMK yang termasuk dalam cakupan asesmen induk. Tambahkan Sub-CPMK tersebut pada asesmen terlebih dahulu atau ubah pilihan RTM.',
                ]);
            }

            // Jangan mempersempit cakupan berdasarkan pekan pengumpulan. RTM
            // integratif dapat mengukur beberapa Sub-CPMK dan dikumpulkan pada
            // satu pekan tertentu.
            $validated['sub_cpmk_ids'] = $requestedSubIds->all();
        }

        if (empty($validated['due_week'])) {
            $latestCoverageWeek = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->whereIn('week_number', [1,2,3,4,5,6,7,9,10,11,12,13,14,15])
                ->whereIn('rps_sub_cpmk_id', $validated['sub_cpmk_ids'])
                ->max('week_number');

            $validated['due_week'] = max(
                (int) ($latestCoverageWeek ?? 0),
                (int) ($assessment->week_number ?? 0)
            ) ?: null;
        }

        return $validated;
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
