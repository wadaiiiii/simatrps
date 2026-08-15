<?php

namespace App\Http\Controllers;

use App\Services\Rps\RpsSyllabusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ObeWorkspaceController extends Controller
{
    public function storeCpmk(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:4000'],
            'bloom_level' => ['nullable', Rule::in(['C1','C2','C3','C4','C5','C6'])],
        ]);

        $next = (int) DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->max('sequence_no') + 1;

        DB::table('rps_cpmks')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'code' => 'CPMK-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT),
            'description' => $data['description'],
            'bloom_level' => $data['bloom_level'] ?: null,
            'source_type' => 'manual',
            'source_cpmk_id' => null,
            'sequence_no' => $next,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CPMK baru berhasil ditambahkan ke RPS.');
    }

    public function updateCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:4000'],
            'bloom_level' => ['nullable', Rule::in(['C1','C2','C3','C4','C5','C6'])],
        ]);

        DB::table('rps_cpmks')->where('id', $cpmk)->update([
            'description' => $data['description'],
            'bloom_level' => $data['bloom_level'] ?: null,
            'source_type' => $row->source_cpmk_id ? 'adapted' : 'manual',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CPMK RPS berhasil diperbarui.');
    }

    public function resetCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        if (! $row->source_cpmk_id) {
            throw ValidationException::withMessages([
                'cpmk' => 'CPMK ini dibuat oleh dosen dan tidak memiliki versi master untuk dipulihkan.',
            ]);
        }

        $master = DB::table('curriculum_cpmks')
            ->where('id', $row->source_cpmk_id)
            ->first();

        abort_unless($master, 404);

        DB::table('rps_cpmks')->where('id', $cpmk)->update([
            'description' => $master->description,
            'bloom_level' => null,
            'source_type' => 'curriculum',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CPMK dikembalikan ke rumusan master kurikulum.');
    }

    public function destroyCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $linked = DB::table('rps_cpmk_subcpmks')
            ->where('rps_cpmk_id', $cpmk)
            ->exists();

        if ($linked) {
            throw ValidationException::withMessages([
                'cpmk' => 'CPMK masih memiliki Sub-CPMK. Pindahkan atau hapus Sub-CPMK terlebih dahulu.',
            ]);
        }

        DB::table('rps_cpmks')->where('id', $cpmk)->delete();

        return back()->with('success', 'CPMK dihapus dari RPS. Master kurikulum tidak berubah.');
    }

    public function saveCpmkCpl(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'mappings' => ['array'],
            'mappings.*' => ['array'],
            'mappings.*.*' => ['uuid'],
        ]);

        $allowedCplIds = DB::table('course_cpls')
            ->where('course_id', $record->course_id)
            ->pluck('cpl_id')
            ->all();

        $cpmkIds = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($data, $allowedCplIds, $cpmkIds, $request): void {
            DB::table('rps_cpmk_cpls')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->delete();

            foreach (($data['mappings'] ?? []) as $cpmkId => $cplIds) {
                if (! in_array($cpmkId, $cpmkIds, true)) {
                    continue;
                }

                foreach (array_unique($cplIds) as $cplId) {
                    if (! in_array($cplId, $allowedCplIds, true)) {
                        throw ValidationException::withMessages([
                            'mappings' => 'CPMK hanya dapat dipetakan ke CPL resmi mata kuliah.',
                        ]);
                    }

                    DB::table('rps_cpmk_cpls')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_cpmk_id' => $cpmkId,
                        'cpl_id' => $cplId,
                        'source_type' => 'manual',
                        'created_by' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Pemetaan CPMK → CPL disimpan.');
    }

    public function storeSubCpmk(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'rps_cpmk_id' => ['required', 'uuid'],
            'description' => ['required', 'string', 'max:3000'],
            'bloom_level' => ['nullable', Rule::in(['C1','C2','C3','C4','C5','C6'])],
        ]);

        abort_unless(
            DB::table('rps_cpmks')
                ->where('id', $data['rps_cpmk_id'])
                ->where('rps_version_id', $version->id)
                ->exists(),
            422
        );

        $next = (int) DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->max('sequence_no') + 1;

        $id = (string) Str::uuid();

        DB::transaction(function () use ($id, $version, $data, $next, $request): void {
            DB::table('rps_sub_cpmks')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'code' => 'Sub-CPMK-'.$next,
                'description' => $data['description'],
                'bloom_level' => $data['bloom_level'] ?: null,
                'sequence_no' => $next,
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rps_cpmk_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_cpmk_id' => $data['rps_cpmk_id'],
                'rps_sub_cpmk_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Sub-CPMK ditambahkan.');
    }

    public function destroySubCpmk(Request $request, string $rps, string $subCpmk): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        abort_unless(
            DB::table('rps_sub_cpmks')
                ->where('id', $subCpmk)
                ->where('rps_version_id', $version->id)
                ->exists(),
            404
        );

        DB::table('rps_sub_cpmks')->where('id', $subCpmk)->delete();

        return back()->with('success', 'Sub-CPMK dihapus.');
    }

    public function storeMaterial(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'rps_sub_cpmk_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $next = (int) DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->max('sequence_no') + 1;

        DB::table('rps_materials')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'rps_sub_cpmk_id' => $data['rps_sub_cpmk_id'] ?: null,
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'sequence_no' => $next,
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Bahan kajian ditambahkan.');
    }

    public function importSyllabusMaterials(
        Request $request,
        string $rps,
        RpsSyllabusService $syllabus
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $count = $syllabus->syncMaterials(
            $record->course_id,
            $version->id,
            true
        );

        return back()->with(
            'success',
            "{$count} bahan kajian disinkronkan dari bagian Silabus. Pustaka tidak lagi dimasukkan sebagai bahan kajian."
        );
    }

    public function destroyMaterial(Request $request, string $rps, string $material): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        abort_unless(
            DB::table('rps_materials')
                ->where('id', $material)
                ->where('rps_version_id', $version->id)
                ->exists(),
            404
        );

        DB::table('rps_materials')->where('id', $material)->delete();

        return back()->with('success', 'Bahan kajian dihapus.');
    }

    public function updateWeek(Request $request, string $rps, int $week): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        abort_unless($week >= 1 && $week <= 16, 404);

        $data = $request->validate([
            'rps_sub_cpmk_id' => ['nullable', 'uuid'],
            'material_text' => ['nullable', 'string', 'max:5000'],
            'learning_method' => ['nullable', 'string', 'max:3000'],
            'learning_activity' => ['nullable', 'string', 'max:5000'],
            'assessment_indicator' => ['nullable', 'string', 'max:5000'],
            'assessment_criteria' => ['nullable', 'string', 'max:5000'],
            'assessment_method' => ['nullable', 'string', 'max:3000'],
            'assessment_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reference_text' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->update([...$data, 'updated_at' => now()]);

        return back()->with('success', "Minggu {$week} diperbarui.");
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
