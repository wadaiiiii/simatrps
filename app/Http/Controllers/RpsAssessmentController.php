<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RpsAssessmentController extends Controller
{
    public function store(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $validated = $this->validated($request, $version->id);
        $this->assertWeightWithinLimit($version->id, $validated['weight'] ?? null);

        $next = (int) DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->count() + 1;

        $id = (string) Str::uuid();

        DB::transaction(function () use ($id, $version, $validated, $next, $request): void {
            DB::table('assessments')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'code' => 'ASM-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT),
                'name' => $validated['name'],
                'type' => $validated['type'],
                'week_number' => $validated['week_number'] ?: null,
                'description' => $validated['description'] ?: null,
                'weight' => $validated['weight'] === null || $validated['weight'] === ''
                    ? null
                    : $validated['weight'],
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncSubCpmks(
                $id,
                $version->id,
                $validated['sub_cpmk_ids']
            );
        });

        if (
            in_array($validated['type'], ['uts', 'uas'], true)
            && filled($validated['week_number'] ?? null)
        ) {
            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);
        }

        return back()->with('success', 'Asesmen berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        string $rps,
        string $assessment
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $validated = $this->validated($request, $version->id);
        $oldWeek = $row->week_number ? (int) $row->week_number : null;

        if ($row->code === 'UTS') {
            $validated['type'] = 'uts';
            $validated['week_number'] = 8;
        }

        if ($row->code === 'UAS') {
            $validated['type'] = 'uas';
            $validated['week_number'] = 16;
        }

        $this->assertWeightWithinLimit(
            $version->id,
            $validated['weight'] ?? null,
            $assessment
        );

        DB::transaction(function () use ($assessment, $version, $validated): void {
            DB::table('assessments')->where('id', $assessment)->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'week_number' => $validated['week_number'] ?: null,
                'description' => $validated['description'] ?: null,
                'weight' => $validated['weight'] === null || $validated['weight'] === ''
                    ? null
                    : $validated['weight'],
                'updated_at' => now(),
            ]);

            $this->syncSubCpmks(
                $assessment,
                $version->id,
                $validated['sub_cpmk_ids']
            );
        });

        $isExamAssessment = in_array($validated['type'], ['uts', 'uas'], true);

        if ($isExamAssessment && $oldWeek) {
            $this->syncWeekPrintWeight($version->id, $oldWeek);
        }

        if ($isExamAssessment && filled($validated['week_number'] ?? null)) {
            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);
        }

        return back()->with(
            'success',
            in_array($row->code, ['UTS', 'UAS'], true)
                ? "{$row->code} berhasil disimpan dan seluruh tabel bobot tersinkron."
                : 'Asesmen diperbarui dan seluruh tabel bobot tersinkron.'
        );
    }

    public function updateMatrix(
        Request $request,
        string $rps,
        string $assessment
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:500'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sub_cpmk_ids' => ['sometimes', 'array', 'min:1'],
            'sub_cpmk_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('rps_sub_cpmks', 'id')->where(
                    fn ($query) => $query->where('rps_version_id', $version->id)
                ),
            ],
        ], [
            'sub_cpmk_ids.min' => 'Setiap asesmen OBE wajib terkait dengan minimal satu Sub-CPMK.',
            'sub_cpmk_ids.*.exists' => 'Sub-CPMK yang dipilih tidak termasuk dalam RPS ini.',
        ]);

        $updates = [];

        if (array_key_exists('name', $validated) && filled($validated['name'])) {
            $updates['name'] = trim((string) $validated['name']);
        }

        if (array_key_exists('weight', $validated)) {
            $this->assertWeightWithinLimit(
                $version->id,
                $validated['weight'],
                $assessment
            );
            $updates['weight'] = round((float) ($validated['weight'] ?? 0), 2);
        }

        DB::transaction(function () use ($assessment, $version, $validated, $updates): void {
            if ($updates !== []) {
                DB::table('assessments')
                    ->where('id', $assessment)
                    ->update([
                        ...$updates,
                        'updated_at' => now(),
                    ]);
            }

            if (array_key_exists('sub_cpmk_ids', $validated)) {
                $this->syncSubCpmks(
                    $assessment,
                    $version->id,
                    $validated['sub_cpmk_ids']
                );
            }
        });

        if (array_key_exists('weight', $validated) && in_array($row->type, ['uts', 'uas'], true)) {
            $this->syncWeekPrintWeight(
                $version->id,
                $row->type === 'uts' ? 8 : 16
            );
        }

        return back()->with(
            'success',
            array_key_exists('weight', $validated)
                ? 'Bobot asesmen agregat diperbarui. Jalankan Isi Bagian Kosong bila distribusi bobot pekan perlu dilengkapi.'
                : 'Pemetaan Sub-CPMK pada Tabel Penilaian dan Evaluasi CPL berhasil diperbarui.'
        );
    }

    public function destroy(Request $request, string $rps, string $assessment): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        if (in_array($row->code, ['UTS', 'UAS'], true)) {
            return back()->with('success', 'UTS/UAS adalah asesmen sistem. Bobot dan cakupannya dapat diedit, tetapi tidak dihapus.');
        }

        $oldWeek = $row->week_number ? (int) $row->week_number : null;

        DB::table('assessments')->where('id', $assessment)->delete();

        // Asesmen non-UTS/UAS adalah rekap/instrumen agregat dan tidak lagi
        // menjadi sumber langsung bobot pekan. Karena UTS/UAS tidak dapat
        // dihapus, penghapusan asesmen biasa tidak perlu menyentuh bobot pekan.

        return back()->with('success', 'Asesmen dihapus. Distribusi bobot pekan tidak diubah.');
    }

    private function syncWeekPrintWeight(
        string $versionId,
        int $week
    ): void {
        $sum = round(
            (float) DB::table('assessments')
                ->where('rps_version_id', $versionId)
                ->where('week_number', $week)
                ->whereIn('type', ['uts', 'uas'])
                ->sum(DB::raw('COALESCE(weight, 0)')),
            2
        );

        $query = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $week);

        if ($query->exists()) {
            $query->update([
                'assessment_weight' => $sum,
                'updated_at' => now(),
            ]);
        }
    }

    private function assertWeightWithinLimit(
        string $versionId,
        mixed $newWeight,
        ?string $excludeAssessmentId = null
    ): void {
        $all = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'weight']);

        $currentTotal = round(
            (float) $all->sum(fn ($row) => (float) ($row->weight ?? 0)),
            2
        );

        $oldWeight = 0.0;

        if ($excludeAssessmentId) {
            $existing = $all->firstWhere('id', $excludeAssessmentId);
            $oldWeight = (float) ($existing?->weight ?? 0);
        }

        $incomingWeight = (float) (($newWeight === null || $newWeight === '') ? 0 : $newWeight);
        $projectedTotal = round(
            $currentTotal - $oldWeight + $incomingWeight,
            2
        );

        /*
         * Jika data lama sudah terlanjur >100%, dosen tetap harus bisa
         * menyimpan perubahan nama/Sub-CPMK atau MENURUNKAN bobot secara
         * bertahap. Yang dilarang adalah penambahan/kenaikan yang membuat
         * total semakin besar di atas 100%.
         */
        if ($projectedTotal > 100.0 && $projectedTotal > ($currentTotal + 0.001)) {
            throw ValidationException::withMessages([
                'weight' => "Total bobot akan meningkat menjadi {$projectedTotal}%. Bobot asesmen tidak boleh melebihi 100%.",
            ]);
        }
    }

    private function validated(Request $request, string $versionId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in([
                'quiz', 'assignment', 'project', 'presentation',
                'practicum', 'uts', 'uas', 'other',
            ])],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:16'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:4000'],
            'sub_cpmk_ids' => ['required', 'array', 'min:1'],
            'sub_cpmk_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('rps_sub_cpmks', 'id')->where(
                    fn ($query) => $query->where('rps_version_id', $versionId)
                ),
            ],
        ], [
            'sub_cpmk_ids.required' => 'Setiap asesmen OBE wajib terkait dengan minimal satu Sub-CPMK.',
            'sub_cpmk_ids.min' => 'Setiap asesmen OBE wajib terkait dengan minimal satu Sub-CPMK.',
            'sub_cpmk_ids.*.exists' => 'Sub-CPMK yang dipilih tidak termasuk dalam RPS ini.',
        ]);
    }

    private function syncSubCpmks(
        string $assessmentId,
        string $versionId,
        array $subIds
    ): void {
        $allowed = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('id')
            ->all();

        DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessmentId)
            ->delete();

        foreach (array_unique($subIds) as $subId) {
            if (! in_array($subId, $allowed, true)) {
                continue;
            }

            DB::table('assessment_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'assessment_id' => $assessmentId,
                'rps_sub_cpmk_id' => $subId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();

        abort_unless($version, 404);

        return [$record, $version];
    }
}
