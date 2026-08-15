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

        $updated = 0;
        $subCount = $subCpmks->count();
        $weekCount = count(self::TEACHING_WEEKS);

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
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

            $method = $course?->has_practicum
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
                'learning_form' => $course?->has_practicum
                    ? 'Tatap Muka / Praktikum'
                    : 'Tatap Muka',
                'learning_method' => $method,
                'time_estimate' => $this->timeEstimate((int) ($course?->credits ?? 1)),
                'face_to_face_sessions' => 1,
                'learning_activity' => $activity,
                'independent_study_sessions' => 1,
                'student_assignment' => 'Latihan/tugas terstruktur yang selaras dengan '.$sub->code.'.',
                'structured_task_sessions' => 1,
                'online_activity' => 'Pengumpulan tugas atau diskusi melalui LMS bila diperlukan.',
                'assessment_indicator' => $indicator,
                'assessment_method' => 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.',
                'reference_text' => $this->referencesForPosition(
                    $referenceCodes,
                    $position
                ),
                'source_type' => 'smart_draft',
                'updated_at' => now(),
            ];

            $query = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $weekNumber);

            $current = $query->first();

            if (! $current) {
                continue;
            }

            if ($mode === 'overwrite') {
                $query->update($payload);
                $updated++;
                continue;
            }

            $fill = ['updated_at' => now()];

            foreach ($payload as $key => $value) {
                if ($key === 'updated_at') {
                    continue;
                }

                if (! filled($current->{$key} ?? null) && filled($value)) {
                    $fill[$key] = $value;
                }
            }

            if (count($fill) > 1) {
                $query->update($fill);
                $updated++;
            }
        }

        $this->fillExamWeek($version->id, 8, 'UTS', 'Ujian Tengah Semester');
        $this->fillExamWeek($version->id, 16, 'UAS', 'Ujian Akhir Semester');

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
            $meta = DB::table('rps_document_meta')
                ->where('rps_version_id', $versionId)
                ->first(['reference_text', 'supporting_reference_text']);

            if ($meta) {
                $text = trim(
                    (string) ($meta->reference_text ?? '')
                    ."\n"
                    .(string) ($meta->supporting_reference_text ?? '')
                );
            }
        }

        if ($text === '') {
            $text = (string) (
                DB::table('course_syllabi')
                    ->where('course_id', $courseId)
                    ->orderBy('source_entry_no')
                    ->value('reference_text') ?? ''
            );
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

        foreach ($items as $item) {
            $exists = DB::table('assessments')
                ->where('rps_version_id', $versionId)
                ->where('code', $item['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('assessments')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $versionId,
                ...$item,
                'weight' => null,
                'source_type' => 'system',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function fillExamWeek(
        string $versionId,
        int $week,
        string $type,
        string $activity
    ): void {
        $row = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $week)
            ->first();

        if (! $row) {
            return;
        }

        DB::table('rps_weekly_plans')
            ->where('id', $row->id)
            ->update([
                'is_exam' => true,
                'exam_type' => $type,
                'learning_activity' => filled($row->learning_activity)
                    ? $row->learning_activity
                    : $activity,
                'assessment_method' => filled($row->assessment_method)
                    ? $row->assessment_method
                    : $type,
                'updated_at' => now(),
            ]);
    }
}
