<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RpsCplScopeController extends Controller
{
    public function store(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $this->assertDocumentInfoReady($version->id);

        $data = $request->validate([
            'cpl_id' => ['required', 'uuid'],
            'rationale' => ['nullable', 'string', 'max:2000'],
        ]);

        $cpl = DB::table('cpls')
            ->where('id', $data['cpl_id'])
            ->where('curriculum_id', $record->curriculum_id)
            ->first();

        if (! $cpl) {
            throw ValidationException::withMessages([
                'cpl_id' => 'CPL tidak termasuk dalam kurikulum RPS ini.',
            ]);
        }

        $isOfficial = DB::table('course_cpls')
            ->where('course_id', $record->course_id)
            ->where('cpl_id', $cpl->id)
            ->exists();

        if ($isOfficial) {
            throw ValidationException::withMessages([
                'cpl_id' => "{$cpl->code} sudah menjadi CPL resmi mata kuliah.",
            ]);
        }

        $exists = DB::table('rps_additional_cpls')
            ->where('rps_version_id', $version->id)
            ->where('cpl_id', $cpl->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'cpl_id' => "{$cpl->code} sudah ditambahkan ke scope RPS.",
            ]);
        }

        DB::table('rps_additional_cpls')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'cpl_id' => $cpl->id,
            'source_type' => 'lecturer',
            'rationale' => ($data['rationale'] ?? null) ?: null,
            'added_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            "{$cpl->code} ditambahkan ke scope RPS. Master kurikulum tidak berubah."
        );
    }

    public function destroy(
        Request $request,
        string $rps,
        string $cpl
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);
        $this->assertDocumentInfoReady($version->id);

        $row = DB::table('rps_additional_cpls')
            ->join('cpls', 'cpls.id', '=', 'rps_additional_cpls.cpl_id')
            ->where('rps_additional_cpls.rps_version_id', $version->id)
            ->where('rps_additional_cpls.cpl_id', $cpl)
            ->first([
                'rps_additional_cpls.id',
                'rps_additional_cpls.cpl_id',
                'cpls.code',
            ]);

        if (! $row) {
            throw ValidationException::withMessages([
                'cpl' => 'CPL tambahan tidak ditemukan pada RPS ini.',
            ]);
        }

        $cpmkIds = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->pluck('id');

        DB::transaction(function () use ($row, $cpmkIds): void {
            if ($cpmkIds->isNotEmpty()) {
                DB::table('rps_cpmk_cpls')
                    ->whereIn('rps_cpmk_id', $cpmkIds)
                    ->where('cpl_id', $row->cpl_id)
                    ->delete();
            }

            DB::table('rps_additional_cpls')
                ->where('id', $row->id)
                ->delete();
        });

        return back()->with(
            'success',
            "{$row->code} dihapus dari tambahan RPS beserta mapping CPMK terkait. Master kurikulum tetap."
        );
    }

    private function assertDocumentInfoReady(string $versionId): void
    {
        $meta = DB::table('rps_document_meta')
            ->where('rps_version_id', $versionId)
            ->first();

        $required = [
            'course_cluster',
            'prepared_date',
            'published_date',
            'developer_name',
            'coordinator_name',
            'head_program_name',
            'lecturer_names',
            'software_media',
            'hardware_media',
            'prerequisite_text',
            'description_short',
        ];
        $missing = collect($required)
            ->filter(fn (string $field) => ! filled($meta->{$field} ?? null))
            ->values();

        if (! $meta || $missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'document_info' => 'Lengkapi dan simpan Edit Informasi RPS terlebih dahulu sebelum mengatur Scope CPL atau menyusun CPMK.',
            ]);
        }
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
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
