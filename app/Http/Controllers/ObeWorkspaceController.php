<?php

namespace App\Http\Controllers;

use App\Services\Rps\RpsSyllabusService;
use App\Services\Rps\RpsAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function importCurriculumCpmks(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);

        $masters = DB::table('curriculum_cpmks')
            ->where('course_id', $record->course_id)
            ->orderBy('sequence_no')
            ->get();

        if ($masters->isEmpty()) {
            throw ValidationException::withMessages([
                'cpmk' => 'CPMK master kurikulum belum tersedia untuk mata kuliah ini.',
            ]);
        }

        $existing = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->get(['source_cpmk_id', 'code']);

        $existingSourceIds = $existing
            ->pluck('source_cpmk_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $existingCodes = $existing
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->all();

        $missing = $masters
            ->filter(fn ($master) =>
                ! in_array((string) $master->id, $existingSourceIds, true)
                && ! in_array(strtoupper(trim((string) $master->code)), $existingCodes, true)
            )
            ->values();

        if ($missing->isEmpty()) {
            return back()->with(
                'success',
                'CPMK kurikulum sudah lengkap. Tidak ada CPMK master yang perlu dipulihkan.'
            );
        }

        $timestamp = now();
        $rows = $missing->map(fn ($master): array => [
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'code' => $master->code,
            'description' => $master->description,
            'bloom_level' => null,
            'source_type' => 'curriculum',
            'source_cpmk_id' => $master->id,
            'sequence_no' => $master->sequence_no,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        DB::table('rps_cpmks')->insert($rows);

        return back()->with(
            'success',
            count($rows).' CPMK berhasil dipulihkan dari master kurikulum.'
        );
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

        $officialCplIds = DB::table('course_cpls')
            ->where('course_id', $record->course_id)
            ->pluck('cpl_id')
            ->all();

        $additionalCplIds = DB::table('rps_additional_cpls')
            ->where('rps_version_id', $version->id)
            ->pluck('cpl_id')
            ->all();

        $allowedCplIds = array_values(array_unique([
            ...$officialCplIds,
            ...$additionalCplIds,
        ]));

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
                            'mappings' => 'CPMK hanya dapat dipetakan ke CPL yang berada dalam scope RPS (CPL kurikulum + CPL tambahan dosen).',
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

        $next = $this->nextAvailableSubSequence($version->id);

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


    public function updateSubCpmk(
        Request $request,
        string $rps,
        string $subCpmk
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('rps_sub_cpmks')
            ->where('id', $subCpmk)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $data = $request->validate([
            'rps_cpmk_id' => ['required', 'uuid'],
            'description' => ['required', 'string', 'max:3000'],
            'bloom_level' => ['nullable', Rule::in(['C1','C2','C3','C4','C5','C6'])],
        ]);

        $parentExists = DB::table('rps_cpmks')
            ->where('id', $data['rps_cpmk_id'])
            ->where('rps_version_id', $version->id)
            ->exists();

        if (! $parentExists) {
            throw ValidationException::withMessages([
                'rps_cpmk_id' => 'CPMK induk tidak valid untuk RPS ini.',
            ]);
        }

        DB::transaction(function () use ($subCpmk, $data): void {
            DB::table('rps_sub_cpmks')
                ->where('id', $subCpmk)
                ->update([
                    'description' => $data['description'],
                    'bloom_level' => $data['bloom_level'] ?: null,
                    'source_type' => 'manual',
                    'updated_at' => now(),
                ]);

            DB::table('rps_cpmk_subcpmks')
                ->where('rps_sub_cpmk_id', $subCpmk)
                ->delete();

            DB::table('rps_cpmk_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_cpmk_id' => $data['rps_cpmk_id'],
                'rps_sub_cpmk_id' => $subCpmk,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', "{$row->code} berhasil diperbarui.");
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

        DB::transaction(function () use ($subCpmk, $version): void {
            DB::table('rps_sub_cpmks')
                ->where('id', $subCpmk)
                ->delete();

            $remaining = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->orderBy('sequence_no')
                ->orderBy('code')
                ->get(['id']);

            // Dua tahap untuk menghindari bentrok unique code saat, misalnya,
            // Sub-CPMK-8 harus berubah menjadi Sub-CPMK-6 sementara kode lama
            // masih ada pada baris berikutnya. Relasi lain tetap aman karena
            // seluruh mapping menggunakan UUID Sub-CPMK, bukan teks kodenya.
            foreach ($remaining as $index => $item) {
                DB::table('rps_sub_cpmks')
                    ->where('id', $item->id)
                    ->update([
                        'code' => '__renumber__'.$item->id,
                        'sequence_no' => 1000 + $index,
                        'updated_at' => now(),
                    ]);
            }

            foreach ($remaining as $index => $item) {
                $sequence = $index + 1;

                DB::table('rps_sub_cpmks')
                    ->where('id', $item->id)
                    ->update([
                        'code' => 'Sub-CPMK-'.$sequence,
                        'sequence_no' => $sequence,
                        'updated_at' => now(),
                    ]);
            }

            // Rekomendasi Sub-CPMK yang belum diterapkan menyimpan target_code.
            // Setelah renumber target tersebut dapat menjadi basi, jadi hapus
            // rekomendasi pending agar telaah berikutnya memakai struktur baru.
            DB::table('ai_suggestions')
                ->where('rps_version_id', $version->id)
                ->where('suggestion_type', 'sub_cpmk')
                ->where('status', 'pending')
                ->delete();
        });

        return back()->with(
            'success',
            'Sub-CPMK dihapus dan urutan Sub-CPMK dirapikan otomatis.'
        );
    }

    public function storeMaterial(Request $request, string $rps): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $next = (int) DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->max('sequence_no') + 1;

        DB::table('rps_materials')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'rps_sub_cpmk_id' => null,
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null)
                ? trim((string) $data['description'])
                : null,
            'sequence_no' => $next,
            'source_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Bahan kajian ditambahkan.');
    }

    public function updateMaterial(
        Request $request,
        string $rps,
        string $material
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $row = DB::table('rps_materials')
            ->where('id', $material)
            ->where('rps_version_id', $version->id)
            ->first();

        abort_unless($row, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
        ]);

        DB::table('rps_materials')
            ->where('id', $material)
            ->update([
                'title' => trim($data['title']),
                'rps_sub_cpmk_id' => null,
                'source_type' => in_array($row->source_type, ['curriculum', 'curriculum_syllabus'], true)
                    ? 'adapted'
                    : 'manual',
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('rps_material_subcpmks')) {
            DB::table('rps_material_subcpmks')
                ->where('rps_material_id', $material)
                ->delete();
        }

        return back()->with('success', 'Bahan kajian berhasil diperbarui.');
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

    public function alignSubCpmkSequence(
        Request $request,
        string $rps,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $manualAllocationActive = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', [1,2,3,4,5,6,7,9,10,11,12,13,14,15])
            ->where('source_type', 'like', 'manual_allocation%')
            ->exists();

        if ($manualAllocationActive) {
            throw ValidationException::withMessages([
                'sub_cpmk' => 'Alokasi pertemuan manual sedang aktif. Ubah jumlah pertemuan melalui tombol Atur Pertemuan agar keputusan dosen tidak tertimpa.',
            ]);
        }

        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code']);

        if ($subs->isEmpty()) {
            throw ValidationException::withMessages([
                'sub_cpmk' => 'Belum ada Sub-CPMK yang dapat dirapikan.',
            ]);
        }

        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        foreach ($teachingWeeks as $position => $weekNumber) {
            $index = min(
                $subs->count() - 1,
                (int) floor(($position * $subs->count()) / count($teachingWeeks))
            );

            $sub = $subs->values()->get($index);

            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $weekNumber)
                ->update([
                    'rps_sub_cpmk_id' => $sub->id,
                    'updated_at' => now(),
                ]);
        }

        $assessmentSync->syncVersion($version->id);

        return back()->with(
            'success',
            'Alur Sub-CPMK pekan pembelajaran dirapikan dan rantai asesmen disinkronkan. UTS/UAS tidak diubah.'
        );
    }

    public function applyTimeStandard(
        Request $request,
        string $rps
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $credits = max(1, (int) (
            DB::table('courses')->where('id', $record->course_id)->value('credits') ?? 1
        ));

        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', $teachingWeeks)
            ->get();

        $updated = 0;

        DB::transaction(function () use ($weeks, $credits, &$updated): void {
            foreach ($weeks as $week) {
                $face = max(1, (int) ($week->face_to_face_sessions ?? 0));
                $structured = max(1, (int) ($week->structured_task_sessions ?? 0));
                $independent = max(1, (int) ($week->independent_study_sessions ?? 0));

                $technical = [
                    'learning_form' => filled($week->learning_form ?? null)
                        ? $week->learning_form
                        : 'Kuliah tatap muka',
                    'face_to_face_sessions' => $face,
                    'structured_task_sessions' => $structured,
                    'independent_study_sessions' => $independent,
                    'time_estimate' => "Tatap muka: {$face} × ({$credits} × 50 menit); "
                        ."Tugas terstruktur: {$structured} × ({$credits} × 60 menit); "
                        ."Belajar mandiri: {$independent} × ({$credits} × 60 menit)",
                    'updated_at' => now(),
                ];

                DB::table('rps_weekly_plans')
                    ->where('id', $week->id)
                    ->update($technical);
                $updated++;
            }
        });

        return back()->with(
            'success',
            "Data teknis {$updated} pekan dilengkapi: bentuk pembelajaran dasar dan estimasi waktu sesuai {$credits} SKS. Isi akademik tidak diubah."
        );
    }

    public function normalizeReferences(
        Request $request,
        string $rps
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $referenceText = '';

        if (
            Schema::hasTable('rps_document_meta')
            && Schema::hasColumn('rps_document_meta', 'reference_text')
        ) {
            $meta = DB::table('rps_document_meta')
                ->where('rps_version_id', $version->id)
                ->first(['reference_text', 'supporting_reference_text']);

            if ($meta) {
                $referenceText = trim(
                    "Utama:
".trim((string) ($meta->reference_text ?? ''))
                    .(filled($meta->supporting_reference_text ?? null)
                        ? "
Pendukung:
".trim((string) $meta->supporting_reference_text)
                        : '')
                );
            }
        }

        if ($referenceText === '') {
            $referenceText = (string) (
                DB::table('course_syllabi')
                    ->where('course_id', $record->course_id)
                    ->orderBy('source_entry_no')
                    ->value('reference_text') ?? ''
            );
        }

        $entries = $this->bibliographyEntries($referenceText);
        $changed = 0;

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereNotNull('reference_text')
            ->get(['id', 'reference_text']);

        foreach ($weeks as $week) {
            $normalized = $this->normalizeReferenceAgainstBibliography(
                (string) $week->reference_text,
                $entries
            );

            if ($normalized !== '' && $normalized !== (string) $week->reference_text) {
                DB::table('rps_weekly_plans')
                    ->where('id', $week->id)
                    ->update([
                        'reference_text' => $normalized,
                        'updated_at' => now(),
                    ]);
                $changed++;
            }
        }

        return back()->with(
            'success',
            "{$changed} baris pustaka dinormalisasi menjadi nomor [n]."
        );
    }

    public function updateWeek(Request $request, string $rps, int $week, RpsAssessmentSyncService $assessmentSync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        abort_unless($week >= 1 && $week <= 16, 404);

        $weekly = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        if (! $weekly) {
            throw ValidationException::withMessages([
                'week' => "Data pekan {$week} tidak ditemukan.",
            ]);
        }

        $data = $request->validate([
            'rps_sub_cpmk_id' => ['nullable', 'uuid'],
            'material_text' => ['nullable', 'string', 'max:5000'],
            'learning_form' => ['nullable', 'string', 'max:1000'],
            'learning_method' => ['nullable', 'string', 'max:3000'],
            'time_estimate' => ['nullable', 'string', 'max:255'],
            'face_to_face_sessions' => ['nullable', 'integer', 'min:0', 'max:10'],
            'student_assignment' => ['nullable', 'string', 'max:5000'],
            'structured_task_sessions' => ['nullable', 'integer', 'min:0', 'max:10'],
            'online_activity' => ['nullable', 'string', 'max:5000'],
            'learning_activity' => ['nullable', 'string', 'max:5000'],
            'independent_study_sessions' => ['nullable', 'integer', 'min:0', 'max:10'],
            'assessment_indicator' => ['nullable', 'string', 'max:5000'],
            'assessment_criteria' => ['nullable', 'string', 'max:5000'],
            'assessment_method' => ['nullable', 'string', 'max:3000'],
            'reference_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $subId = $data['rps_sub_cpmk_id'] ?? null;

        if ($subId) {
            $validSub = DB::table('rps_sub_cpmks')
                ->where('id', $subId)
                ->where('rps_version_id', $version->id)
                ->exists();

            if (! $validSub) {
                throw ValidationException::withMessages([
                    'rps_sub_cpmk_id' => 'Sub-CPMK pertemuan tidak valid atau sudah dihapus.',
                ]);
            }
        }

        $isExam = in_array($week, [8, 16], true);
        $examType = $week === 8 ? 'UTS' : ($week === 16 ? 'UAS' : null);

        $payload = [
            'rps_sub_cpmk_id' => $subId ?: null,
            'material_text' => $data['material_text'] ?: null,
            'learning_form' => $data['learning_form'] ?: null,
            'learning_method' => $data['learning_method'] ?: null,
            'time_estimate' => $data['time_estimate'] ?: null,
            'face_to_face_sessions' => (int) ($data['face_to_face_sessions'] ?? 1),
            'student_assignment' => $data['student_assignment'] ?: null,
            'structured_task_sessions' => (int) ($data['structured_task_sessions'] ?? 1),
            'online_activity' => $data['online_activity'] ?: null,
            'learning_activity' => $data['learning_activity'] ?: null,
            'independent_study_sessions' => (int) ($data['independent_study_sessions'] ?? 1),
            'assessment_indicator' => $data['assessment_indicator'] ?: null,
            'assessment_criteria' => $data['assessment_criteria'] ?: null,
            'assessment_method' => $data['assessment_method'] ?: ($isExam ? $examType : null),
            'reference_text' => $this->normalizeReferenceCodes((string) ($data['reference_text'] ?? '')),
            'source_type' => str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
                ? 'manual_allocation_manual'
                : 'manual',
            'updated_at' => now(),
        ];

        if ($isExam) {
            $payload['is_exam'] = true;
            $payload['exam_type'] = $examType;
        }

        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($payload);

        if (! $isExam) {
            $assessmentSync->syncVersion($version->id);
        }

        return back()->with(
            'success',
            $isExam
                ? "Pekan {$week} ({$examType}) berhasil disimpan."
                : "Pekan {$week} berhasil disimpan."
        );
    }

    private function assertMeetingAllocationConfigured(string $versionId): void
    {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        $configured = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $teachingWeeks)
            ->whereNotNull('rps_sub_cpmk_id')
            ->where('source_type', 'like', 'manual_allocation%')
            ->count();

        if ($configured !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'weeks' => 'Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu. Setelah total 14/14 disimpan, editor pekanan akan aktif.',
            ]);
        }
    }

    private function nextAvailableSubSequence(string $versionId): int
    {
        $used = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->pluck('sequence_no')
            ->map(fn ($value) => (int) $value)
            ->all();

        $next = 1;

        foreach ($used as $number) {
            if ($number === $next) {
                $next++;
                continue;
            }

            if ($number > $next) {
                break;
            }
        }

        return $next;
    }

    private function normalizeReferenceCodes(string $value): ?string
    {
        preg_match_all('/\d+/', $value, $matches);

        $numbers = collect($matches[0] ?? [])
            ->map(fn ($number) => (int) $number)
            ->filter(fn ($number) => $number > 0)
            ->unique()
            ->sort()
            ->values();

        return $numbers->isEmpty()
            ? null
            : $numbers->map(fn ($number) => '['.$number.']')->implode(', ');
    }

    private function bibliographyEntries(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $normalized = preg_replace(
            '/\s+(?=[a-z]\.\s+[A-Z0-9])/u',
            "\n",
            $text
        ) ?? $text;

        $parts = preg_split('/\r\n|\r|\n/', $normalized) ?: [$normalized];
        $category = 'utama';
        $entries = [];

        foreach ($parts as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(pustaka\s*)?utama\s*:?\s*$/i', $line)) {
                $category = 'utama';
                continue;
            }

            if (preg_match('/^(pendukung|tambahan)\s*:?\s*$/i', $line)) {
                $category = 'pendukung';
                continue;
            }

            $line = preg_replace(
                '/^\s*(?:(?:\d+|[a-z])[\.\)]|[-•])\s*/iu',
                '',
                $line
            ) ?: $line;

            if ($line !== '') {
                $entries[] = [
                    'number' => count($entries) + 1,
                    'category' => $category,
                    'text' => $line,
                ];
            }
        }

        return collect($entries)
            ->unique(fn ($item) => mb_strtolower($item['text']))
            ->values()
            ->map(fn ($item, $index) => [
                ...$item,
                'number' => $index + 1,
            ])
            ->all();
    }

    private function normalizeReferenceAgainstBibliography(
        string $value,
        array $entries
    ): string {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Jika memang sudah berupa nomor saja, pertahankan.
        if (preg_match('/^\s*(?:\[\s*\d+\s*\]|\d+)(?:\s*[,;]\s*(?:\[\s*\d+\s*\]|\d+))*\s*$/', $value)) {
            $direct = $this->normalizeReferenceCodes($value);

            return $this->filterReferenceCodesToEntries(
                (string) ($direct ?? ''),
                count($entries)
            );
        }

        // Pertahankan kode [n] yang memang sudah eksplisit.
        preg_match_all('/\[\s*(\d+)\s*\]/', $value, $explicitMatches);
        $matched = collect($explicitMatches[1] ?? [])
            ->map(fn ($number) => (int) $number)
            ->filter(fn ($number) => $number >= 1 && $number <= count($entries))
            ->all();

        $haystack = $this->referenceComparableText($value);

        foreach ($entries as $entry) {
            $candidate = $this->referenceComparableText((string) ($entry['text'] ?? ''));

            if ($candidate === '') {
                continue;
            }

            $lead = trim(mb_substr($candidate, 0, 42));

            preg_match('/\b(19|20)\d{2}\b/', $candidate, $yearMatch);
            $year = $yearMatch[0] ?? null;

            $authorTokens = collect(preg_split('/\s+/', $candidate) ?: [])
                ->filter(fn ($token) => mb_strlen($token) >= 4)
                ->reject(fn ($token) => in_array($token, [
                    'edition','edisi','press','publisher','university',
                    'universitas','elementary','introduction','pengantar',
                    'theory','teori','springer','pearson'
                ], true))
                ->take(3)
                ->values()
                ->all();

            $fullMatch = mb_strlen($candidate) >= 12 && str_contains($haystack, $candidate);
            $leadMatch = mb_strlen($lead) >= 18 && str_contains($haystack, $lead);

            $authorScore = collect($authorTokens)
                ->filter(fn ($token) => str_contains($haystack, $token))
                ->count();

            $yearMatchFound = $year ? str_contains($haystack, $year) : false;

            if (
                $fullMatch
                || $leadMatch
                || ($authorScore >= 2 && ($year === null || $yearMatchFound))
                || ($authorScore >= 1 && $yearMatchFound)
            ) {
                $matched[] = (int) $entry['number'];
            }
        }

        return collect($matched)
            ->filter(fn ($number) => $number >= 1 && $number <= count($entries))
            ->unique()
            ->sort()
            ->map(fn ($number) => '['.$number.']')
            ->implode(', ');
    }

    private function filterReferenceCodesToEntries(
        string $codes,
        int $entryCount
    ): string {
        preg_match_all('/\d+/', $codes, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($number) => (int) $number)
            ->filter(fn ($number) => $number >= 1 && $number <= $entryCount)
            ->unique()
            ->sort()
            ->map(fn ($number) => '['.$number.']')
            ->implode(', ');
    }

    private function referenceComparableText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function syncMaterialSubCpmks(
        string $materialId,
        array $subIds
    ): void {
        if (! Schema::hasTable('rps_material_subcpmks')) {
            return;
        }

        DB::table('rps_material_subcpmks')
            ->where('rps_material_id', $materialId)
            ->delete();

        foreach (array_values(array_unique(array_filter($subIds))) as $subId) {
            DB::table('rps_material_subcpmks')->insert([
                'rps_material_id' => $materialId,
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
