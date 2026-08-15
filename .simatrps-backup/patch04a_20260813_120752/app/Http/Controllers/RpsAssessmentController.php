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

        $validated = $request->validate([
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
                'weight' => $validated['weight'] === null ? null : $validated['weight'],
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

                DB::table('assessment_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'assessment_id' => $id,
                    'rps_sub_cpmk_id' => $subId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Asesmen berhasil ditambahkan.');
    }

    public function update(Request $request, string $rps, string $assessment): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $exists = DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->exists();

        abort_unless($exists, 404);

        $validated = $request->validate([
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:16'],
        ]);

        DB::table('assessments')->where('id', $assessment)->update([
            'weight' => $validated['weight'] === null ? null : $validated['weight'],
            'week_number' => $validated['week_number'] ?: null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Asesmen diperbarui.');
    }

    public function destroy(Request $request, string $rps, string $assessment): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        DB::table('assessments')
            ->where('id', $assessment)
            ->where('rps_version_id', $version->id)
            ->delete();

        return back()->with('success', 'Asesmen dihapus.');
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
