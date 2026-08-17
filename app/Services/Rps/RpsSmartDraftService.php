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

        // Lengkapi RPS Otomatis hanya mengisi tabel pekanan/asesmen dasar.
        // Bahan Kajian dan Pustaka adalah data akademik yang dikelola eksplisit
        // melalui Edit, Ambil dari Kurikulum, atau Telaah AI masing-masing.
        // Karena itu proses otomatis TIDAK BOLEH menyinkronkan atau menulis ulang
        // rps_materials maupun pustaka yang sudah diputuskan dosen.
        $this->ensureExamAssessments($version->id, $userId);

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get();

        // Jika daftar Bahan Kajian benar-benar kosong, topik silabus hanya dipakai
        // sebagai fallback sementara untuk mengisi kolom Materi pada tabel pekan.
        // Fallback ini TIDAK disimpan ke rps_materials sehingga daftar Bahan Kajian
        // milik dosen tetap tidak berubah.
        if ($materials->isEmpty()) {
            $materials = collect($this->syllabus->topics($rps->course_id))
                ->map(fn (string $title) => (object) [
                    'id' => null,
                    'rps_sub_cpmk_id' => null,
                    'title' => $title,
                    'source_type' => 'syllabus_fallback_readonly',
                ])
                ->values();
        }

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
        $rows = [];
        $updateColumns = [];

        // Susun 14 pekan sebagai blok Sub-CPMK yang berurutan. Materi tidak
        // lagi diputar secara global; setiap materi harus relevan dengan
        // Sub-CPMK pada pekan tersebut. Jika satu Sub-CPMK memerlukan pekan
        // tambahan setelah seluruh materinya terpakai, label Pendalaman dibuat
        // eksplisit agar tidak terlihat sebagai pengulangan tanpa alasan.
        $teachingSequence = $this->buildTeachingSequence($subCpmks, $materials);

        // Jika dosen sudah menetapkan jumlah pertemuan melalui Atur Pertemuan,
        // alokasi itu menjadi hard constraint. Smart Draft hanya melengkapi isi
        // tiap pekan dan tidak boleh membagi ulang Sub-CPMK.
        $manualAllocationCounts = [];
        foreach (self::TEACHING_WEEKS as $manualWeek) {
            $manualRow = $currentWeeks->get($manualWeek);
            if (
                $manualRow
                && $this->isManualAllocationSource((string) ($manualRow->source_type ?? ''))
                && filled($manualRow->rps_sub_cpmk_id ?? null)
            ) {
                $key = (string) $manualRow->rps_sub_cpmk_id;
                $manualAllocationCounts[$key] = ($manualAllocationCounts[$key] ?? 0) + 1;
            }
        }
        $manualOccurrence = [];

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
            $current = $currentWeeks->get($weekNumber);

            if (! $current) {
                continue;
            }

            $slot = $teachingSequence[$position] ?? null;
            if (! $slot) {
                continue;
            }

            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $currentSource = (string) ($current->source_type ?? '');
            $manualAllocation = $this->isManualAllocationSource($currentSource)
                && filled($current->rps_sub_cpmk_id ?? null);
            $legacyManualAllocationAuto = $currentSource === 'manual_allocation'
                && $this->legacyManualAllocationLooksGenerated($current);

            if ($manualAllocation) {
                $manualSub = $subCpmks->first(
                    fn ($candidate) => (string) $candidate->id === (string) $current->rps_sub_cpmk_id
                );

                if ($manualSub) {
                    $sub = $manualSub;
                    $subKey = (string) $sub->id;
                    $occurrence = $manualOccurrence[$subKey] ?? 0;
                    $manualOccurrence[$subKey] = $occurrence + 1;
                    $materialText = $this->materialForAllocatedWeek(
                        $sub,
                        $materials,
                        $occurrence,
                        max(1, (int) ($manualAllocationCounts[$subKey] ?? 1))
                    );
                }
            }

            $resultSourceType = 'smart_draft';
            if ($manualAllocation) {
                if (in_array($currentSource, ['manual_allocation_manual', 'manual_allocation_ai'], true)) {
                    $resultSourceType = $currentSource;
                } elseif ($currentSource === 'manual_allocation' && ! $legacyManualAllocationAuto) {
                    $resultSourceType = 'manual_allocation_manual';
                } else {
                    $resultSourceType = 'manual_allocation_auto';
                }
            }

            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';

            $activity = $this->learningActivityForWeek(
                $sub,
                $hasPracticum
            );

            $indicator = $this->indicatorFromSubCpmk((string) $sub->description);

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
                'assessment_method' => 'Tugas/latihan terukur, kuis, atau observasi kinerja sesuai aktivitas pembelajaran.',
                'reference_text' => $this->referencesForPosition(
                    $referenceCodes,
                    $position
                ),
                'source_type' => $resultSourceType,
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
            $refreshableGeneratedSource = $currentSource === 'smart_draft'
                || $currentSource === 'manual_allocation_auto'
                || $legacyManualAllocationAuto;

            foreach ($payload as $key => $value) {
                $existing = $current->{$key} ?? null;

                // Normalisasi provenance legacy tanpa mengubah isi manual/AI.
                if ($key === 'source_type' && $manualAllocation) {
                    $merged[$key] = $resultSourceType;
                    if ($existing !== $resultSourceType) {
                        $changed = true;
                    }
                    continue;
                }

                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'
                    && is_string($existing)
                    && preg_match(
                        '/^Mahasiswa\s+mampu\s+menunjukkan\s+ketercapaian\s+Sub-?CPMK-?\d+\s+sesuai\s+rumusan\s*:/iu',
                        $existing
                    ) === 1;

                if ($legacyGeneratedIndicator) {
                    $merged[$key] = $value;
                    if ($existing !== $value) {
                        $changed = true;
                    }
                    continue;
                }

                // Data pekan yang dibuat Smart Draft versi lama boleh
                // dinormalisasi ke algoritme penyelarasan baru. Ini hanya berlaku
                // bila source_type masih smart_draft; edit manual/AI tidak disentuh.
                $refreshGeneratedField = $refreshableGeneratedSource
                    && in_array($key, [
                        'rps_sub_cpmk_id',
                        'material_text',
                        'learning_activity',
                        'student_assignment',
                        'assessment_indicator',
                        'assessment_criteria',
                        'assessment_method',
                        'time_estimate',
                    ], true);

                if ($refreshGeneratedField) {
                    $merged[$key] = $value;
                    if ($existing != $value) {
                        $changed = true;
                    }
                    continue;
                }

                // Nilai 0 pada frekuensi hasil generator lama bukan estimasi
                // pembelajaran yang valid. Lengkapi RPS Otomatis boleh
                // memperbaikinya menjadi minimal 1 tanpa menyentuh angka
                // manual lain yang sudah positif.
                $sessionField = in_array($key, [
                    'face_to_face_sessions',
                    'structured_task_sessions',
                    'independent_study_sessions',
                ], true);
                if ($sessionField && (int) $existing < 1 && (int) $value >= 1) {
                    $merged[$key] = $value;
                    $changed = true;
                    continue;
                }

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
        $this->syncExamWeekWeights($version->id);
        $weightMessage = $this->fillEmptyTeachingWeights($version->id);

        return [
            'updated_weeks' => $updated,
            'mode' => $mode,
            'weight_message' => $weightMessage,
        ];
    }

    public function copyPreviousWeek(
        string $versionId,
        int $weekNumber
    ): void {
        if ($weekNumber <= 1 || in_array($weekNumber, [8, 16], true)) {
            throw ValidationException::withMessages([
                'week' => 'Pekan ini tidak dapat menyalin isi pekan sebelumnya.',
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

        $targetHasManualAllocation = $this->isManualAllocationSource(
            (string) ($target->source_type ?? '')
        );

        DB::table('rps_weekly_plans')
            ->where('id', $target->id)
            ->update([
                'rps_sub_cpmk_id' => $targetHasManualAllocation
                    ? $target->rps_sub_cpmk_id
                    : $source->rps_sub_cpmk_id,
                'material_text' => $source->material_text,
                'learning_method' => $source->learning_method,
                'learning_activity' => $source->learning_activity,
                'assessment_indicator' => $source->assessment_indicator,
                'assessment_criteria' => $source->assessment_criteria,
                'assessment_method' => $source->assessment_method,
                'reference_text' => $source->reference_text,
                'source_type' => $targetHasManualAllocation
                    ? 'manual_allocation_manual'
                    : 'copied_previous',
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

    private function isManualAllocationSource(string $source): bool
    {
        return $source === 'manual_allocation'
            || str_starts_with($source, 'manual_allocation_');
    }

    private function legacyManualAllocationLooksGenerated(object $week): bool
    {
        $core = [
            trim((string) ($week->material_text ?? '')),
            trim((string) ($week->learning_activity ?? '')),
            trim((string) ($week->student_assignment ?? '')),
            trim((string) ($week->assessment_indicator ?? '')),
            trim((string) ($week->assessment_criteria ?? '')),
            trim((string) ($week->assessment_method ?? '')),
        ];

        if (collect($core)->filter(fn ($value) => $value !== '')->isEmpty()) {
            return true;
        }

        $signals = 0;
        $activity = (string) ($week->learning_activity ?? '');
        $assignment = (string) ($week->student_assignment ?? '');
        $criteria = (string) ($week->assessment_criteria ?? '');
        $method = (string) ($week->assessment_method ?? '');
        $learningMethod = (string) ($week->learning_method ?? '');

        if (preg_match('/^Mahasiswa mempelajari .+mendiskusikan contoh, dan menyelesaikan latihan yang mendukung Sub-CPMK-?\d+\.$/u', $activity) === 1) {
            $signals++;
        }
        if (preg_match('/^Latihan\/tugas terstruktur yang selaras dengan Sub-CPMK-?\d+\.$/u', $assignment) === 1) {
            $signals++;
        }
        if (str_starts_with($criteria, 'Ketepatan, kelengkapan, dan kesesuaian jawaban/kinerja terhadap indikator Sub-CPMK-')) {
            $signals++;
        }
        if ($method === 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.') {
            $signals++;
        }
        if (str_contains($learningMethod, 'Ceramah interaktif') && str_contains($learningMethod, 'latihan terbimbing')) {
            $signals++;
        }

        return $signals >= 2;
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

    private function buildTeachingSequence($subCpmks, $materials): array
    {
        $subs = $subCpmks->values();
        $weekTotal = count(self::TEACHING_WEEKS);

        if ($subs->isEmpty()) {
            return [];
        }

        if ($subs->count() > $weekTotal) {
            throw ValidationException::withMessages([
                'smart_draft' => 'Jumlah Sub-CPMK ('.$subs->count().') melebihi '.$weekTotal.' pertemuan efektif. Gabungkan atau rapikan Sub-CPMK terlebih dahulu agar setiap Sub-CPMK dapat memperoleh minimal satu pertemuan.',
            ]);
        }

        $materialRows = $materials
            ->filter(fn ($material) => filled($material->title ?? null))
            ->values();

        $subIndexById = [];
        foreach ($subs as $index => $sub) {
            $subIndexById[(string) $sub->id] = (int) $index;
        }

        // Relasi eksplisit selalu menjadi prioritas. Jika pivot many-to-many
        // tersedia, satu Bahan Kajian boleh memang dipakai oleh lebih dari satu
        // Sub-CPMK. Bahan Kajian tanpa relasi eksplisit dipetakan satu kali ke
        // Sub-CPMK yang paling relevan secara semantik.
        $pivotLinks = collect();
        $materialIds = $materialRows
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (
            $materialIds !== []
            && Schema::hasTable('rps_material_subcpmks')
        ) {
            $pivotLinks = DB::table('rps_material_subcpmks')
                ->whereIn('rps_material_id', $materialIds)
                ->get(['rps_material_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_material_id');
        }

        $assignments = array_fill(0, $subs->count(), []);
        $materialCount = max(1, $materialRows->count());

        foreach ($materialRows as $materialIndex => $material) {
            $title = trim((string) $material->title);
            $explicitIndexes = [];

            if (filled($material->rps_sub_cpmk_id ?? null)) {
                $direct = $subIndexById[(string) $material->rps_sub_cpmk_id] ?? null;
                if ($direct !== null) {
                    $explicitIndexes[] = $direct;
                }
            }

            if (filled($material->id ?? null) && $pivotLinks->has((string) $material->id)) {
                foreach ($pivotLinks->get((string) $material->id) as $link) {
                    $linked = $subIndexById[(string) $link->rps_sub_cpmk_id] ?? null;
                    if ($linked !== null) {
                        $explicitIndexes[] = $linked;
                    }
                }
            }

            $explicitIndexes = array_values(array_unique($explicitIndexes));
            if ($explicitIndexes !== []) {
                foreach ($explicitIndexes as $subIndex) {
                    $assignments[$subIndex][] = [
                        'title' => $title,
                        'index' => (int) $materialIndex,
                    ];
                }
                continue;
            }

            $scores = [];
            foreach ($subs as $subIndex => $sub) {
                $scores[$subIndex] = $this->materialRelevanceScore(
                    (string) ($sub->description ?? ''),
                    $title
                );
            }

            $bestScore = $scores === [] ? 0 : max($scores);
            $expectedIndex = $materialRows->count() <= 1
                ? 0.0
                : ((float) $materialIndex / (float) ($materialCount - 1)) * max(0, $subs->count() - 1);

            $candidateIndexes = array_keys(
                array_filter(
                    $scores,
                    fn ($score) => $score === $bestScore
                )
            );

            if ($candidateIndexes === []) {
                $candidateIndexes = range(0, $subs->count() - 1);
            }

            usort($candidateIndexes, function (int $a, int $b) use ($expectedIndex): int {
                $distanceA = abs($a - $expectedIndex);
                $distanceB = abs($b - $expectedIndex);
                return $distanceA <=> $distanceB ?: $a <=> $b;
            });

            $targetIndex = (int) $candidateIndexes[0];
            $assignments[$targetIndex][] = [
                'title' => $title,
                'index' => (int) $materialIndex,
            ];
        }

        $titlesBySub = [];
        foreach ($assignments as $subIndex => $rows) {
            $titlesBySub[$subIndex] = collect($rows)
                ->sortBy('index')
                ->pluck('title')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        // Setiap Sub-CPMK minimal satu pekan. Sisa pekan TIDAK dibagi rata.
        // Alokasi tambahan mempertimbangkan beban Bahan Kajian dan tingkat Bloom.
        // Karena itu satu Sub-CPMK boleh 1 pekan, sementara yang lebih kompleks
        // dapat memperoleh beberapa pekan.
        $weekCounts = array_fill(0, $subs->count(), 1);
        $demand = [];

        foreach ($subs as $subIndex => $sub) {
            $materialLoad = count($titlesBySub[$subIndex] ?? []);
            $materialFactor = $materialLoad === 0
                ? 0.35
                : min(3.25, $materialLoad * 0.65);

            $demand[$subIndex] = 1.0
                + $materialFactor
                + $this->bloomComplexityWeight((string) ($sub->bloom_level ?? ''));
        }

        $remaining = $weekTotal - $subs->count();
        while ($remaining > 0) {
            $bestIndex = 0;
            $bestPriority = -INF;

            foreach ($subs as $subIndex => $_sub) {
                $priority = $demand[$subIndex] / max(1, $weekCounts[$subIndex]);
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestIndex = (int) $subIndex;
                }
            }

            $weekCounts[$bestIndex]++;
            $remaining--;
        }

        $sequence = [];
        foreach ($subs as $subIndex => $sub) {
            $groups = $this->splitMaterialsAcrossWeeks(
                $titlesBySub[$subIndex] ?? [],
                $weekCounts[$subIndex],
                $sub
            );

            foreach ($groups as $materialText) {
                $sequence[] = [
                    'sub' => $sub,
                    'material' => $materialText,
                ];
            }
        }

        return array_slice($sequence, 0, $weekTotal);
    }

    private function bloomComplexityWeight(string $level): float
    {
        return match (strtoupper(trim($level))) {
            'C1' => 0.00,
            'C2' => 0.15,
            'C3' => 0.35,
            'C4' => 0.65,
            'C5' => 0.90,
            'C6' => 1.15,
            default => 0.25,
        };
    }

    private function splitMaterialsAcrossWeeks(array $titles, int $weekCount, object $sub): array
    {
        $weekCount = max(1, $weekCount);
        $titles = array_values(array_filter(array_map(
            fn ($title) => trim((string) $title),
            $titles
        )));

        if ($titles === []) {
            return array_fill(0, $weekCount, null);
        }

        // Maksimal dua topik inti per pekan. Daftar Bahan Kajian asli tetap utuh;
        // pembatasan ini hanya untuk penyajian pada tabel rencana pertemuan.
        $maxTitles = max(1, $weekCount * 2);
        if (count($titles) > $maxTitles) {
            $titles = array_slice($titles, 0, $maxTitles);
        }

        $materialCount = count($titles);
        $groups = [];

        // Jika Bahan Kajian lebih banyak daripada pekan yang dialokasikan,
        // beberapa Bahan Kajian yang berurutan digabung dalam satu pertemuan.
        if ($materialCount >= $weekCount) {
            for ($week = 0; $week < $weekCount; $week++) {
                $start = (int) floor(($week * $materialCount) / $weekCount);
                $end = (int) floor((($week + 1) * $materialCount) / $weekCount);
                $chunk = array_slice($titles, $start, max(1, $end - $start));
                $groups[] = implode('; ', $chunk);
            }

            return $groups;
        }

        // Jika pekan lebih banyak daripada Bahan Kajian, materi tidak diputar
        // mentah. Pekan tambahan diberi tujuan pedagogis eksplisit sesuai Bloom.
        foreach ($titles as $title) {
            $groups[] = $title;
        }

        $baseTitle = $titles[count($titles) - 1];
        while (count($groups) < $weekCount) {
            $groups[] = $this->pedagogicalExtension(
                (string) ($sub->bloom_level ?? ''),
                $baseTitle,
                count($groups) - $materialCount
            );
        }

        return $groups;
    }

    private function pedagogicalExtension(string $bloom, string $title, int $extensionIndex): string
    {
        $prefixes = match (strtoupper(trim($bloom))) {
            'C1', 'C2' => [
                'Penguatan konsep dan latihan',
                'Pendalaman pemahaman dan pembahasan',
            ],
            'C3' => [
                'Latihan penerapan dan pemecahan masalah',
                'Pendalaman penerapan melalui latihan terarah',
            ],
            'C4' => [
                'Analisis kasus dan pembahasan',
                'Pendalaman analisis dan perbandingan kasus',
            ],
            'C5' => [
                'Evaluasi kasus dan pembahasan kritis',
                'Pendalaman evaluasi dan argumentasi',
            ],
            'C6' => [
                'Perancangan/pengembangan dan umpan balik',
                'Penyempurnaan rancangan dan refleksi',
            ],
            default => [
                'Pendalaman dan latihan',
                'Penguatan dan pembahasan lanjutan',
            ],
        };

        $prefix = $prefixes[$extensionIndex % count($prefixes)];
        return $prefix.': '.$title;
    }

    private function materialForAllocatedWeek(
        object $sub,
        $materials,
        int $occurrence,
        int $weekCount
    ): ?string {
        $linkedMaterialIds = [];

        if (Schema::hasTable('rps_material_subcpmks')) {
            $linkedMaterialIds = DB::table('rps_material_subcpmks')
                ->where('rps_sub_cpmk_id', $sub->id)
                ->pluck('rps_material_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $materialRows = $materials
            ->values()
            ->map(function ($material, $index) use ($sub, $linkedMaterialIds): array {
                $title = trim((string) ($material->title ?? ''));
                $direct = filled($material->rps_sub_cpmk_id ?? null)
                    && (string) $material->rps_sub_cpmk_id === (string) $sub->id;
                $pivot = filled($material->id ?? null)
                    && in_array((string) $material->id, $linkedMaterialIds, true);

                return [
                    'title' => $title,
                    'index' => (int) $index,
                    'linked' => $direct || $pivot,
                    'score' => $this->materialRelevanceScore(
                        (string) ($sub->description ?? ''),
                        $title
                    ),
                ];
            })
            ->filter(fn (array $item) => $item['title'] !== '')
            ->values();

        $linkedTitles = $materialRows
            ->filter(fn (array $item) => $item['linked'])
            ->sortBy('index')
            ->pluck('title')
            ->unique()
            ->values()
            ->all();

        if ($linkedTitles !== []) {
            // Relasi eksplisit adalah keputusan akademik dosen/AI material plan.
            // Jangan campurkan materi lain hanya karena memiliki satu kata yang sama.
            $titles = $linkedTitles;
        } else {
            $scored = $materialRows
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sort(function (array $a, array $b): int {
                    if ($a['score'] !== $b['score']) {
                        return $b['score'] <=> $a['score'];
                    }
                    return $a['index'] <=> $b['index'];
                })
                ->values();

            $bestScore = $scored->isEmpty() ? 0 : (int) $scored->max('score');
            $candidateLimit = max(1, $weekCount * 2);

            // Satu kecocokan kata pada banyak materi adalah sinyal ambigu.
            // Gunakan urutan kurikulum sebagai fallback, bukan memasukkan semuanya.
            $ambiguous = $bestScore <= 1 && $scored->count() > $candidateLimit;

            if (! $ambiguous && $bestScore > 0) {
                $minimumScore = max(1, $bestScore - 1);
                $titles = $scored
                    ->filter(fn (array $item) => $item['score'] >= $minimumScore)
                    ->take($candidateLimit)
                    ->sortBy('index')
                    ->pluck('title')
                    ->unique()
                    ->values()
                    ->all();
            } else {
                $allTitles = $materialRows->sortBy('index')->pluck('title')->values();
                $totalMaterials = $allTitles->count();
                $subTotal = max(1, DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $sub->rps_version_id)
                    ->count());
                $subIndex = max(0, min($subTotal - 1, ((int) ($sub->sequence_no ?? 1)) - 1));
                $start = (int) floor(($subIndex * $totalMaterials) / $subTotal);
                $end = (int) floor((($subIndex + 1) * $totalMaterials) / $subTotal);
                $length = max(1, $end - $start);

                $titles = $allTitles
                    ->slice($start, $length)
                    ->take($candidateLimit)
                    ->values()
                    ->all();
            }
        }

        if ($titles === []) {
            return null;
        }

        $groups = $this->splitMaterialsAcrossWeeks($titles, max(1, $weekCount), $sub);
        return $groups[$occurrence] ?? end($groups) ?: null;
    }

    private function materialRelevanceScore(string $subDescription, string $materialTitle): int
    {
        $subTokens = $this->academicTokens($subDescription);
        $materialTokens = $this->academicTokens($materialTitle);

        if ($subTokens === [] || $materialTokens === []) {
            return 0;
        }

        return count(array_intersect($subTokens, $materialTokens));
    }

    private function academicTokens(string $value): array
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        $stop = [
            'mahasiswa','mampu','dapat','dan','atau','yang','dalam','pada','untuk',
            'dengan','serta','secara','melalui','konsep','prinsip','dasar','contoh',
            'permasalahan','masalah','kehidupan','terkait','sesuai',
        ];

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stop, true))
            ->unique()
            ->values()
            ->all();
    }

    private function learningActivityForWeek(object $sub, bool $hasPracticum): string
    {
        $code = trim((string) ($sub->code ?? 'Sub-CPMK')) ?: 'Sub-CPMK';
        $level = strtoupper(trim((string) ($sub->bloom_level ?? '')));

        $activity = match ($level) {
            'C1', 'C2' => 'Mengidentifikasi konsep utama, mendiskusikan contoh, dan melakukan latihan pemahaman.',
            'C3' => 'Menerapkan konsep melalui contoh dan latihan terarah, kemudian membahas hasilnya.',
            'C4' => 'Menganalisis kasus/contoh, membandingkan hasil, dan menyusun alasan atas temuan.',
            'C5' => 'Mengevaluasi kasus atau hasil kerja menggunakan kriteria yang ditetapkan dan memberikan argumentasi.',
            'C6' => 'Merancang atau mengembangkan solusi, menguji hasil, dan melakukan perbaikan berdasarkan umpan balik.',
            default => 'Membahas konsep, menganalisis contoh, dan menyelesaikan latihan terarah.',
        };

        if ($hasPracticum) {
            $activity = rtrim($activity, '.').' melalui diskusi dan/atau praktikum.';
        }

        return $activity.' Aktivitas diarahkan untuk mencapai '.$code.'.';
    }

    private function indicatorFromSubCpmk(string $description): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);

        // Kolom indikator berdiri di samping Sub-CPMK, sehingga tidak perlu
        // mengulang label Sub-CPMK maupun frasa administratif ketercapaian.
        $text = preg_replace('/^(?:Mahasiswa\s+)?mampu\s+/iu', '', $text) ?? $text;
        $text = preg_replace('/^Sub-?CPMK-?\d+\s*[:\-]?\s*/iu', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B\"'");

        if ($text === '') {
            return 'Menunjukkan hasil belajar yang dapat diamati dan dinilai.';
        }

        $text = mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);

        if (! preg_match('/[.!?]$/u', $text)) {
            $text .= '.';
        }

        return $text;
    }

    private function timeEstimate(int $credits): string
    {
        $credits = max(1, $credits);

        return "Tatap muka: 1 × ({$credits} × 50 menit); "
            ."Tugas terstruktur: 1 × ({$credits} × 60 menit); "
            ."Belajar mandiri: 1 × ({$credits} × 60 menit)";
    }

    private function syncExamWeekWeights(string $versionId): void
    {
        $weights = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['uts', 'uas'])
            ->get(['type', 'weight'])
            ->mapWithKeys(fn ($row) => [strtolower((string) $row->type) => round((float) ($row->weight ?? 0), 2)]);

        foreach ([8 => 'uts', 16 => 'uas'] as $week => $type) {
            $updates = [
                'assessment_weight' => (float) $weights->get($type, 0),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {
                $updates['assessment_weight_source'] = 'exam';
            }

            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->where('week_number', $week)
                ->update($updates);
        }
    }

    private function fillEmptyTeachingWeights(string $versionId): string
    {
        $result = app(RpsAssessmentSyncService::class)->syncVersion($versionId);

        return (string) ($result['message']
            ?? 'Bobot pekan disinkronkan dari asesmen dan tag Sub-CPMK.');
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
