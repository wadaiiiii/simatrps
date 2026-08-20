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

        if (empty($validated['assessment_id'])) {
            $validated['assessment_id'] = $this->inferAssessmentId($validated, $version->id);
        }

        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }

        // A stale browser can submit a manual RTM after assessment sync has already
        // produced its minimum placeholder. Reuse that generated row so the same
        // assessment does not suddenly appear twice after save.
        $placeholder = $this->generatedPlaceholder(
            $version->id,
            $validated['assessment_id'] ?? null
        );

        $id = $placeholder?->id ?: (string) Str::uuid();
        $code = $placeholder?->code ?: $this->nextTaskCode($version->id);

        DB::transaction(function () use ($id, $code, $placeholder, $version, $validated, $request): void {
            $values = [
                'assessment_id' => ($validated['assessment_id'] ?? null) ?: null,
                'title' => $validated['title'],
                'type' => $validated['type'],
                'purpose' => ($validated['purpose'] ?? null) ?: null,
                'instructions' => ($validated['instructions'] ?? null) ?: null,
                'expected_output' => ($validated['expected_output'] ?? null) ?: null,
                'due_week' => ($validated['due_week'] ?? null) ?: null,
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
                'updated_at' => now(),
            ];

            if ($placeholder) {
                DB::table('rps_tasks')
                    ->where('id', $id)
                    ->where('rps_version_id', $version->id)
                    ->update($values);
            } else {
                DB::table('rps_tasks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    ...$values,
                    'created_at' => now(),
                ]);
            }

            $this->syncTaskSubCpmks($id, $version->id, $validated['sub_cpmk_ids'] ?? []);
        });

        $sync->syncVersion($version->id);

        return back()->with(
            'success',
            $placeholder
                ? 'RTM berhasil disimpan. RTM otomatis untuk asesmen yang sama diperbarui menjadi RTM manual sehingga tidak terbentuk duplikat.'
                : 'RTM berhasil ditambahkan. Urutan tampilan RTM mengikuti pekan pengumpulan.'
        );
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

        if (empty($validated['assessment_id'])) {
            $validated['assessment_id'] = $this->inferAssessmentId($validated, $version->id);
        }

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

    private function inferAssessmentId(array $validated, string $versionId): ?string
    {
        $title = $this->normalizeLabel((string) ($validated['title'] ?? ''));
        $type = strtolower(trim((string) ($validated['type'] ?? '')));

        if ($title === '' || ! in_array($type, ['assignment', 'project', 'practicum', 'presentation'], true)) {
            return null;
        }

        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $candidates = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->where('type', $type)
            ->get(['id', 'name'])
            ->filter(function ($assessment) use ($title, $requestedSubIds): bool {
                if ($this->normalizeLabel((string) $assessment->name) !== $title) {
                    return false;
                }

                if ($requestedSubIds->isEmpty()) {
                    return true;
                }

                $assessmentSubIds = DB::table('assessment_subcpmks')
                    ->where('assessment_id', $assessment->id)
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id);

                return $requestedSubIds->diff($assessmentSubIds)->isEmpty();
            })
            ->values();

        return $candidates->count() === 1
            ? (string) $candidates->first()->id
            : null;
    }

    private function generatedPlaceholder(string $versionId, mixed $assessmentId): ?object
    {
        if (! filled($assessmentId)) {
            return null;
        }

        return DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->where('assessment_id', (string) $assessmentId)
            ->where('source_type', 'assessment_sync')
            ->orderBy('created_at')
            ->first(['id', 'code']);
    }

    private function nextTaskCode(string $versionId): string
    {
        $next = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                return preg_match('/RTM-(\\d+)/i', (string) $code, $match) === 1
                    ? (int) $match[1]
                    : 0;
            })
            ->max() + 1;

        return 'RTM-'.str_pad((string) max(1, $next), 2, '0', STR_PAD_LEFT);
    }

    private function syncTaskSubCpmks(string $taskId, string $versionId, array $subIds): void
    {
        $allowed = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        DB::table('rps_task_subcpmks')
            ->where('rps_task_id', $taskId)
            ->delete();

        foreach (array_unique(array_map('strval', $subIds)) as $subId) {
            if (! in_array($subId, $allowed, true)) {
                continue;
            }

            DB::table('rps_task_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_task_id' => $taskId,
                'rps_sub_cpmk_id' => $subId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
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
