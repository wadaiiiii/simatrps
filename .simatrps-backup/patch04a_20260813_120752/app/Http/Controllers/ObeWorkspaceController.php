<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ObeWorkspaceController extends Controller
{
    public function saveCpmkCpl(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $data = $request->validate(['mappings' => ['array'], 'mappings.*' => ['array'], 'mappings.*.*' => ['uuid']]);
        $allowedCplIds = DB::table('course_cpls')->where('course_id', $record->course_id)->pluck('cpl_id')->all();
        $cpmkIds = DB::table('rps_cpmks')->where('rps_version_id', $version->id)->pluck('id')->all();

        DB::transaction(function () use ($data, $allowedCplIds, $cpmkIds, $request): void {
            DB::table('rps_cpmk_cpls')->whereIn('rps_cpmk_id', $cpmkIds)->delete();
            foreach (($data['mappings'] ?? []) as $cpmkId => $cplIds) {
                if (! in_array($cpmkId, $cpmkIds, true)) continue;
                foreach (array_unique($cplIds) as $cplId) {
                    if (! in_array($cplId, $allowedCplIds, true)) {
                        throw ValidationException::withMessages(['mappings' => 'CPMK hanya dapat dipetakan ke CPL resmi mata kuliah.']);
                    }
                    DB::table('rps_cpmk_cpls')->insert([
                        'id' => (string) Str::uuid(), 'rps_cpmk_id' => $cpmkId, 'cpl_id' => $cplId,
                        'source_type' => 'manual', 'created_by' => $request->user()->id,
                        'created_at' => now(), 'updated_at' => now(),
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
            'rps_cpmk_id' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:3000'],
            'bloom_level' => ['nullable', Rule::in(['C1','C2','C3','C4','C5','C6'])],
        ]);
        abort_unless(DB::table('rps_cpmks')->where('id', $data['rps_cpmk_id'])->where('rps_version_id', $version->id)->exists(), 422);
        $next = (int) DB::table('rps_sub_cpmks')->where('rps_version_id', $version->id)->max('sequence_no') + 1;
        $id = (string) Str::uuid();
        DB::transaction(function () use ($id, $version, $data, $next, $request): void {
            DB::table('rps_sub_cpmks')->insert([
                'id' => $id, 'rps_version_id' => $version->id, 'code' => 'Sub-CPMK-'.$next,
                'description' => $data['description'], 'bloom_level' => $data['bloom_level'] ?: null,
                'sequence_no' => $next, 'source_type' => 'manual', 'created_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('rps_cpmk_subcpmks')->insert([
                'id' => (string) Str::uuid(), 'rps_cpmk_id' => $data['rps_cpmk_id'], 'rps_sub_cpmk_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Sub-CPMK ditambahkan.');
    }

    public function destroySubCpmk(Request $request, string $rps, string $subCpmk): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        abort_unless(DB::table('rps_sub_cpmks')->where('id', $subCpmk)->where('rps_version_id', $version->id)->exists(), 404);
        DB::table('rps_sub_cpmks')->where('id', $subCpmk)->delete();
        return back()->with('success', 'Sub-CPMK dihapus.');
    }

    public function storeMaterial(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        $data = $request->validate(['rps_sub_cpmk_id' => ['nullable','uuid'], 'title' => ['required','string','max:500'], 'description' => ['nullable','string','max:3000']]);
        $next = (int) DB::table('rps_materials')->where('rps_version_id', $version->id)->max('sequence_no') + 1;
        DB::table('rps_materials')->insert([
            'id' => (string) Str::uuid(), 'rps_version_id' => $version->id,
            'rps_sub_cpmk_id' => $data['rps_sub_cpmk_id'] ?: null, 'title' => $data['title'],
            'description' => $data['description'] ?: null, 'sequence_no' => $next, 'source_type' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Bahan kajian ditambahkan.');
    }

    public function importSyllabusMaterials(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $items = DB::table('course_syllabus_items')->where('course_id', $record->course_id)->orderBy('sequence_no')->get();
        foreach ($items as $item) {
            DB::table('rps_materials')->updateOrInsert(
                ['rps_version_id' => $version->id, 'title' => $item->title],
                ['id' => (string) Str::uuid(), 'sequence_no' => $item->sequence_no, 'source_type' => 'curriculum_syllabus', 'updated_at' => now(), 'created_at' => now()]
            );
        }
        return back()->with('success', 'Bahan kajian dari silabus master dimuat.');
    }

    public function updateWeek(Request $request, string $rps, int $week): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        abort_unless($week >= 1 && $week <= 16, 404);
        $data = $request->validate([
            'rps_sub_cpmk_id' => ['nullable','uuid'], 'material_text' => ['nullable','string'],
            'learning_method' => ['nullable','string'], 'learning_activity' => ['nullable','string'],
            'assessment_indicator' => ['nullable','string'], 'assessment_method' => ['nullable','string'],
            'assessment_weight' => ['nullable','numeric','min:0','max:100'], 'reference_text' => ['nullable','string'],
        ]);
        DB::table('rps_weekly_plans')->where('rps_version_id', $version->id)->where('week_number', $week)->update([...$data, 'updated_at' => now()]);
        return back()->with('success', "Minggu {$week} diperbarui.");
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);
        abort_unless($record->owner_id === $request->user()->id || $request->user()->role === 'admin', 403);
        $version = DB::table('rps_versions')->where('id', $record->current_version_id)->first();
        abort_unless($version, 404);
        return [$record, $version];
    }
}
