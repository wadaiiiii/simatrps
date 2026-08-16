<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RpsSmartDraftService
{
    private const TEACHING_WEEKS = [1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15];

    public function __construct(
        private readonly RpsSyllabusService $syllabus
    ) {
    }

    public function generate(
        object $rps,
        object $version,
        int $userId,
        string $mode = 'fill_empty'
    ): array {
        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get();

        if ($subCpmks->isEmpty()) {
            throw ValidationException::withMessages([
                'smart_draft' => 'Tambahkan minimal satu Sub-CPMK sebelum menyusun pertemuan otomatis.',
            ]);
        }

        $weeklyColumns = array_flip(Schema::getColumnListing('rps_weekly_plans'));

        // Sinkronisasi silabus hanya memperkaya draft. Jika data master bermasalah,
        // proses utama tetap dapat mengisi minggu dari Sub-CPMK yang tersedia.
        try {
            $importedExists = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->where('source_type', 'curriculum_syllabus')
                ->exists();

            if (! $importedExists || $this->syllabus->importedMaterialsLookLikeReferences($version->id)) {
                $this->syllabus->syncMaterials(
                    $rps->course_id,
                    $version->id,
                    true
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->ensureExamAssessments($version->id, $userId);

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get();

        $referenceCodes = $this->referenceCodes(
            $rps->course_id,
            $version->id
        );

        $course = DB::table('courses')
            ->where('id', $rps->course_id)
            ->first();

        $hasPracticum = (bool) ($course->has_practicum ?? false);
        $credits = max(1, (int) ($course->credits ?? 1));

        $currentWeeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))
            ->get()
            ->keyBy('week_number');

        $updated = 0;
        $subCount = $subCpmks->count();
        $weekCount = count(self::TEACHING_WEEKS);
        $rows = [];
        $updateColumns = [];

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
            $current = $currentWeeks->get($weekNumber);

            if (! $current) {
                continue;
            }

            $subIndex = min(
                $subCount - 1,
                (int) floor(($position * $subCount) / $weekCount)
            );

            $sub = $subCpmks[$subIndex];

            $linkedMaterials = $materials
                ->where('rps_sub_cpmk_id', $sub->id)
                ->values();

            if ($linkedMaterials->isEmpty()) {
                $globalMaterials = $materials
                    ->whereNull('rps_sub_cpmk_id')
                    ->values();

                $material = $globalMaterials->isNotEmpty()
                    ? $globalMaterials[$position % $globalMaterials->count()]
                    : null;

                $materialText = $material?->title;
            } else {
                $materialText = $linkedMaterials
                    ->take(3)
                    ->pluck('title')
                    ->implode('; ');
            }

            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';

            $activity = 'Mahasiswa mempelajari '
                .($materialText ?: 'bahan kajian yang ditetapkan')
                .', mendiskusikan contoh, dan menyelesaikan latihan yang mendukung '
                .$sub->code.'.';

            $indicator = 'Mahasiswa mampu menunjukkan ketercapaian '.$sub->code
                .' sesuai rumusan: '.$sub->description;

            $payload = [
                'rps_sub_cpmk_id' => $sub->id,
                'material_text' => $materialText,
                'learning_form' => $hasPracticum
                    ? 'Tatap Muka / Praktikum'
                    : 'Tatap Muka',
                'learning_method' => $method,
                'time_estimate' => $this->timeEstimate($credits),
                'face_to_face_sessions' => 1,
                'learning_activity' => $activity,
                'independent_study_sessions' => 1,
                'student_assignment' => 'Latihan/tugas terstruktur yang selaras dengan '.$sub->code.'.',
                'structured_task_sessions' => 1,
                'online_activity' => 'Pengumpulan tugas atau diskusi melalui LMS bila diperlukan.',
                'assessment_indicator' => $indicator,
                'assessment_criteria' => 'Ketepatan, kelengkapan, dan kesesuaian jawaban/kinerja terhadap indikator '
                    .$sub->code.'.',
                'assessment_method' => 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.',
                'reference_text' => $this->referencesForPosition(
                    $referenceCodes,
                    $position
                ),
                'source_type' => 'smart_draft',
            ];

            // Deployment lama mungkin belum memiliki seluruh kolom tambahan.
            // Jangan biarkan satu kolom opsional membuat seluruh proses gagal.
            $payload = array_intersect_key($payload, $weeklyColumns);

            $merged = [
                'id' => $current->id,
                'rps_version_id' => $version->id,
                'week_number' => $weekNumber,
            ];
            $changed = false;

            foreach ($payload as $key => $value) {
                $existing = $current->{$key} ?? null;

                if ($mode === 'overwrite') {
                    $merged[$key] = $value;
                    if ($existing != $value) {
                        $changed = true;
                    }
                    continue;
                }

                if (! filled($existing) && filled($value)) {
                    $merged[$key] = $value;
                    $changed = true;
                } else {
                    $merged[$key] = $existing;
                }
            }

            if (! $changed) {
                continue;
            }

            $merged['updated_at'] = now();
            $rows[] = $merged;
            $updated++;

            if ($updateColumns === []) {
                $updateColumns = [...array_keys($payload), 'updated_at'];
            }
        }

        if ($rows !== []) {
            DB::table('rps_weekly_plans')->upsert(
                $rows,
                ['rps_version_id', 'week_number'],
                $updateColumns
            );
        }

        $this->fillExamWeeks($version->id, $currentWeeks);

        return [
            'updated_weeks' => $updated,
            'mode' => $mode,
        ];
    }

    public function copyPreviousWeek(
        string $versionId,
        int $weekNumber
    ): void {
        if ($weekNumber <= 1 || in_array($weekNumber, [8, 16], true)) {
            throw ValidationException::withMessages([
                'week' => 'Minggu ini tidak dapat menyalin isi minggu sebelumnya.',
            ]);
        }

        $previous = $weekNumber - 1;

        if ($previous === 8) {
            $previous = 7;
        }

        $source = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $previous)
            ->first();

        $target = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $weekNumber)
            ->first();

        if (! $source || ! $target) {
            throw ValidationException::withMessages([
                'week' => 'Data pertemuan tidak ditemukan.',
            ]);
        }

        DB::table('rps_weekly_plans')
            ->where('id', $target->id)
            ->update([
                'rps_sub_cpmk_id' => $source->rps_sub_cpmk_id,
                'material_text' => $source->material_text,
                'learning_method' => $source->learning_method,
                'learning_activity' => $source->learning_activity,
                'assessment_indicator' => $source->assessment_indicator,
                'assessment_criteria' => $source->assessment_criteria,
                'assessment_method' => $source->assessment_method,
                'reference_text' => $source->reference_text,
                'source_type' => 'copied_previous',
                'updated_at' => now(),
            ]);
    }

    public function applyMethod(
        string $versionId,
        array $weeks,
        string $method
    ): int {
        $weeks = array_values(array_filter(
            array_unique(array_map('intval', $weeks)),
            fn (int $week) => $week >= 1 && $week <= 16 && ! in_array($week, [8, 16], true)
        ));

        if ($weeks === []) {
            return 0;
        }

        return DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $weeks)
            ->update([
                'learning_method' => $method,
                'updated_at' => now(),
            ]);
    }

    private function referenceCodes(
        string $courseId,
        string $versionId
    ): array {
        $text = '';

        if (
            Schema::hasTable('rps_document_meta')
            && Schema::hasColumn('rps_document_meta', 'reference_text')
        ) {
            $columns = ['reference_text'];

            if (Schema::hasColumn('rps_document_meta', 'supporting_reference_text')) {
                $columns[] = 'supporting_reference_text';
            }

            $meta = DB::table('rps_document_meta')
                ->where('rps_version_id', $versionId)
                ->first($columns);

            if ($meta) {
                $text = trim(
                    (string) ($meta->reference_text ?? '')
                    ."\n"
                    .(string) ($meta->supporting_reference_text ?? '')
                );
            }
        }

        $normalized = preg_replace(
            '/\s+(?=[a-z]\.\s+[A-Z0-9])/u',
            "\n",
            $text
        ) ?? $text;

        $parts = collect(preg_split('/\r\n|\r|\n/', $normalized) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->reject(fn ($line) => preg_match('/^(pustaka\s*)?(utama|pendukung|tambahan)\s*:?\s*$/i', $line))
            ->map(fn ($line) => preg_replace(
                '/^\s*(?:(?:\d+|[a-z])[\.\)]|[-•])\s*/iu',
                '',
                $line
            ) ?: $line)
            ->filter()
            ->unique()
            ->values();

        return $parts
            ->map(fn ($line, $index) => '['.($index + 1).']')
            ->all();
    }

    private function referencesForPosition(
        array $codes,
        int $position
    ): ?string {
        $count = count($codes);

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $codes[0];
        }

        $indexes = [
            $position % $count,
            ($position + 1) % $count,
        ];

        if ($count >= 4) {
            $indexes[] = ($position + 3) % $count;
        }

        return collect($indexes)
            ->unique()
            ->map(fn ($index) => $codes[$index])
            ->implode(', ');
    }

    private function timeEstimate(int $credits): string
    {
        $credits = max(1, $credits);

        return "Tatap muka: 1 × ({$credits} × 50 menit); "
            ."Tugas terstruktur: 1 × ({$credits} × 60 menit); "
            ."Belajar mandiri: 1 × ({$credits} × 60 menit)";
    }

    private function ensureExamAssessments(string $versionId, int $userId): void
    {
        $items = [
            ['code' => 'UTS', 'name' => 'Ujian Tengah Semester', 'type' => 'uts', 'week_number' => 8],
            ['code' => 'UAS', 'name' => 'Ujian Akhir Semester', 'type' => 'uas', 'week_number' => 16],
        ];

        $existingCodes = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('code', ['UTS', 'UAS'])
            ->pluck('code')
            ->all();

        $rows = [];

        foreach ($items as $item) {
            if (in_array($item['code'], $existingCodes, true)) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'rps_version_id' => $versionId,
                ...$item,
                'weight' => null,
                'source_type' => 'system',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('assessments')->insertOrIgnore($rows);
        }
    }

    private function fillExamWeeks(string $versionId, $currentWeeks): void
    {
        $config = [
            8 => ['type' => 'UTS', 'activity' => 'Ujian Tengah Semester'],
            16 => ['type' => 'UAS', 'activity' => 'Ujian Akhir Semester'],
        ];

        $rows = [];

        foreach ($config as $week => $item) {
            $current = $currentWeeks->get($week);

            if (! $current) {
                continue;
            }

            $rows[] = [
                'id' => $current->id,
                'rps_version_id' => $versionId,
                'week_number' => $week,
                'is_exam' => true,
                'exam_type' => $item['type'],
                'learning_activity' => filled($current->learning_activity ?? null)
                    ? $current->learning_activity
                    : $item['activity'],
                'assessment_method' => filled($current->assessment_method ?? null)
                    ? $current->assessment_method
                    : $item['type'],
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('rps_weekly_plans')->upsert(
            $rows,
            ['rps_version_id', 'week_number'],
            ['is_exam', 'exam_type', 'learning_activity', 'assessment_method', 'updated_at']
        );
    }
}
