<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpsAssessmentController extends Controller
{
    public function store(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $validated = $this->validated($request);

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
                $validated['sub_cpmk_ids'] ?? []
            );
        });

        return back()->with('success', 'Asesmen berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        string $rps,
        string $assessment
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $exists = DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->exists();

        abort_unless($exists, 404);

        $validated = $this->validated($request);

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
                $validated['sub_cpmk_ids'] ?? []
            );
        });

        return back()->with('success', 'Asesmen diperbarui.');
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

        DB::table('assessments')->where('id', $assessment)->delete();

        return back()->with('success', 'Asesmen dihapus.');
    }

    private function validated(Request $request): array
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
            'sub_cpmk_ids' => ['nullable', 'array'],
            'sub_cpmk_ids.*' => ['uuid'],
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
