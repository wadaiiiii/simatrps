from pathlib import Path
import re


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"Missing marker: {label}")
    return text.replace(old, new, 1)


def regex_once(text: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f"Regex marker failed ({count}): {label}")
    return updated

# ---------------------------------------------------------------------------
# 1) RpsAiController: detailed/integrative RTM instruction and cache version.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text()

assessment_instruction = r'''        if ($data['suggestion_type'] === 'assessment_plan') {
            $rtmInstruction = <<<'PROMPT'
Untuk Telaah Asesmen + RTM, perlakukan ASESMEN sebagai rencana pengukuran agregat dan RTM sebagai lembar instruksi tugas konkret bagi mahasiswa.

ATURAN CAKUPAN RTM:
1. Satu RTM BOLEH mengukur tepat satu Sub-CPMK ATAU beberapa Sub-CPMK sekaligus jika tugasnya integratif (proyek, praktikum, presentasi, tugas kasus, atau produk yang memang memerlukan beberapa capaian).
2. `tasks[*].sub_cpmk_codes` tidak boleh dipaksa sama dengan Sub-CPMK pada pekan pengumpulan. `due_week` hanya menunjukkan jadwal/pengumpulan; cakupan akademik RTM ditentukan oleh kemampuan yang benar-benar diukur tugas tersebut.
3. Seluruh `sub_cpmk_codes` sebuah RTM harus merupakan bagian dari `sub_cpmk_codes` asesmen induknya. RTM boleh mengukur sebagian atau seluruh cakupan asesmen induk.
4. Jangan membuat banyak RTM hanya untuk memaksa pola satu RTM = satu Sub-CPMK. Jika satu tugas secara alami mengintegrasikan 2-4 Sub-CPMK, gunakan satu RTM integratif.

KEDALAMAN ISI RTM:
5. `purpose` harus berupa uraian substantif 1-2 paragraf pendek: jelaskan konteks penugasan, kemampuan yang dilatih/diukur, dan pekerjaan intelektual atau keterampilan yang harus ditunjukkan mahasiswa. Jangan menggunakan kalimat generik seperti "mengukur ketercapaian Sub-CPMK melalui tugas" saja.
6. `instructions` harus operasional dan siap dibaca mahasiswa. Susun sedikitnya 5 langkah bernomor yang logis: persiapan/identifikasi masalah, pengumpulan atau pemilihan data/informasi bila relevan, proses analisis/perhitungan/perancangan/implementasi, pemeriksaan atau interpretasi hasil, dokumentasi, dan pengumpulan/presentasi. Sesuaikan dengan jenis mata kuliah dan Bahan Kajian aktif; jangan mengarang perangkat, data, ukuran kelompok, atau aplikasi yang tidak didukung konteks.
7. Untuk proyek/tugas integratif yang berlangsung lintas pekan atau mengukur beberapa Sub-CPMK, masukkan bagian "Tahap/Milestone" di dalam `instructions`. Gunakan pekan yang masuk akal dari `weekly_plan` dan pastikan tahap akhir tidak melewati `due_week`. Jangan membuat jadwal di luar semester.
8. `expected_output` harus menjelaskan luaran konkret dalam 3-5 butir, misalnya laporan, perhitungan/analisis, diagram/model, source code/notebook, peta, produk, atau bahan presentasi sesuai karakter mata kuliah. Jangan mengarang jumlah halaman, format file khusus, atau standar teknis yang tidak ada di konteks/instruksi dosen.
9. `assessments[*].description` harus berisi indikator/kriteria penilaian yang spesifik terhadap tugas, bukan kalimat umum. Boleh merinci aspek ketepatan konsep/metode, kualitas proses, kualitas hasil/interpretasi, dan komunikasi/dokumentasi sepanjang sesuai konteks.
10. Gunakan bahasa Indonesia akademik yang jelas, instruktif, dan cukup rinci seperti lembar Rencana Tugas Mahasiswa resmi. Hindari pengulangan kalimat template antar-RTM.
PROMPT;

            $effectiveInstruction = filled($effectiveInstruction)
                ? trim((string) $effectiveInstruction)."\n\n".$rtmInstruction
                : $rtmInstruction;
        }

'''
marker = "        $contextHash = hash(\n"
if 'ATURAN CAKUPAN RTM:' not in s:
    s = replace_once(s, marker, assessment_instruction + marker, 'assessment RTM instruction')

s = replace_once(
    s,
    "                    'ai_policy_version' => 'bloom-guard-v2',",
    "                    'ai_policy_version' => $data['suggestion_type'] === 'assessment_plan' ? 'rtm-integrative-v1' : 'bloom-guard-v2',",
    'assessment AI policy version',
)
p.write_text(s)

# ---------------------------------------------------------------------------
# 2) RpsController: display real RTM scope, never replace it with due-week Sub.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsController.php')
s = p.read_text()
pattern = r'''        \$weekSubByNumber = \$weeks\n            ->pluck\('rps_sub_cpmk_id', 'week_number'\);\n\n        \$assessmentById = .*?            : collect\(\);\n\n        \$simulationScores'''
replacement = r'''        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);

        $tasks = Schema::hasTable('rps_tasks')
            ? DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->orderBy('code')
                ->get()
                ->filter(function ($task) use ($assessmentById): bool {
                    // RTM manual boleh tidak memiliki asesmen induk. RTM hasil
                    // generator yang masih menunjuk asesmen yang sudah hilang
                    // disembunyikan sampai relasinya diperbaiki oleh sinkronisasi.
                    if (! $this->isGeneratedRtm($task)) {
                        return true;
                    }

                    $assessmentId = filled($task->assessment_id ?? null)
                        ? (string) $task->assessment_id
                        : null;

                    return $assessmentId !== null && $assessmentById->has($assessmentId);
                })
                ->values()
                ->map(function ($task): object {
                    // Satu RTM dapat mengukur satu atau lebih Sub-CPMK.
                    // Pekan pengumpulan hanya jadwal; jangan menimpa cakupan
                    // RTM dengan Sub-CPMK yang kebetulan aktif pada pekan itu.
                    $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')
                        ->where('rps_task_id', $task->id)
                        ->pluck('rps_sub_cpmk_id')
                        ->all();

                    return $task;
                })
            : collect();

        $simulationScores'''
s = regex_once(s, pattern, replacement, 'RpsController task scope block')
p.write_text(s)

# ---------------------------------------------------------------------------
# 3) RpsTaskController: assessment defaults preserve 1..N RTM Sub-CPMK.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsTaskController.php')
s = p.read_text()
s = s.replace(
    "return back()->with('success', 'RTM berhasil ditambahkan. Tag Sub-CPMK mengikuti Sub-CPMK pekan, sedangkan asesmen agregat tetap menjadi sumber anggaran bobot.');",
    "return back()->with('success', 'RTM berhasil ditambahkan. Satu RTM dapat mengukur satu atau lebih Sub-CPMK dalam cakupan asesmen induk; pekan hanya menjadi jadwal pengumpulan.');"
)
s = s.replace(
    "return back()->with('success', 'RTM berhasil diperbarui dan distribusi asesmen-pekan disinkronkan.');",
    "return back()->with('success', 'RTM berhasil diperbarui. Cakupan Sub-CPMK RTM dipertahankan independen dari pekan pengumpulan dan tetap berada dalam asesmen induk.');"
)
pattern = r'''    private function applyAssessmentDefaults\(array \$validated, string \$versionId\): array\n    \{.*?\n    \}\n\n    private function context'''
replacement = r'''    private function applyAssessmentDefaults(array $validated, string $versionId): array
    {
        $assessment = DB::table('assessments')
            ->where('id', $validated['assessment_id'])
            ->where('rps_version_id', $versionId)
            ->first(['id', 'name', 'type', 'week_number']);

        if (! $assessment) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assessment_id' => 'Asesmen RTM tidak valid untuk RPS ini.',
            ]);
        }

        $type = strtolower((string) $assessment->type);
        $rtmTypes = ['assignment', 'project', 'practicum', 'presentation'];

        if (! in_array($type, $rtmTypes, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assessment_id' => 'Pilih asesmen tugas, proyek, praktikum, atau presentasi untuk RTM.',
            ]);
        }

        $validated['type'] = $type;
        $assessmentSubIds = DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessment->id)
            ->pluck('rps_sub_cpmk_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if (empty($validated['due_week']) && filled($assessment->week_number)) {
            $validated['due_week'] = (int) $assessment->week_number;
        }

        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        if ($requestedSubIds->isEmpty()) {
            // Default aman: satu RTM mewarisi seluruh cakupan asesmen induk.
            // Dosen tetap dapat memilih sebagian Sub-CPMK melalui editor RTM.
            $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
            return $validated;
        }

        $outsideAssessment = $requestedSubIds
            ->reject(fn ($id) => $assessmentSubIds->contains($id))
            ->values();

        if ($outsideAssessment->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sub_cpmk_ids' => 'RTM hanya boleh mengukur Sub-CPMK yang termasuk dalam cakupan asesmen induk. Tambahkan Sub-CPMK tersebut pada asesmen terlebih dahulu atau ubah pilihan RTM.',
            ]);
        }

        // Jangan mempersempit cakupan berdasarkan pekan pengumpulan. RTM
        // integratif dapat mengukur beberapa Sub-CPMK dan dikumpulkan pada
        // satu pekan tertentu.
        $validated['sub_cpmk_ids'] = $requestedSubIds->all();

        return $validated;
    }

    private function context'''
s = regex_once(s, pattern, replacement, 'RpsTaskController assessment defaults')
p.write_text(s)

# ---------------------------------------------------------------------------
# 4) RpsAssessmentSyncService: task scope is subset/full assessment scope,
#    never due-week scope. AI/manual RTM titles need not equal assessment title.
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/RpsAssessmentSyncService.php')
s = p.read_text()

sync_function = r'''    public function syncTaskMappings(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(due_week, 99)')
            ->orderBy('code')
            ->get(['id', 'assessment_id', 'title', 'type', 'due_week', 'source_type', 'purpose', 'instructions', 'expected_output']);

        if ($tasks->isEmpty()) return 0;

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'name', 'type', 'week_number']);

        $assessmentLinks = $assessments->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessments->pluck('id')->all())
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy('assessment_id');

        $taskLinks = DB::table('rps_task_subcpmks')
            ->whereIn('rps_task_id', $tasks->pluck('id')->all())
            ->get(['rps_task_id', 'rps_sub_cpmk_id'])
            ->groupBy('rps_task_id');

        $linkedCount = 0;
        $allowedAssessmentTypes = ['assignment', 'project', 'practicum', 'presentation'];

        DB::transaction(function () use (
            $tasks,
            $assessments,
            $assessmentLinks,
            $taskLinks,
            $allowedAssessmentTypes,
            &$linkedCount
        ): void {
            foreach ($tasks as $task) {
                $sourceType = strtolower(trim((string) ($task->source_type ?? '')));
                $isGenerated = $this->isGeneratedTask($task);
                $currentAssessment = filled($task->assessment_id ?? null)
                    ? $assessments->first(
                        fn ($assessment) => (string) $assessment->id === (string) $task->assessment_id
                    )
                    : null;

                // RTM manual adalah keputusan dosen. Sinkronisasi tidak
                // memindahkan asesmen atau mengubah cakupan Sub-CPMK-nya.
                if (! $isGenerated) {
                    if ($currentAssessment) $linkedCount++;
                    continue;
                }

                $assessment = $currentAssessment;

                if (! $assessment || ! in_array(strtolower((string) $assessment->type), $allowedAssessmentTypes, true)) {
                    // RTM AI yang sudah diterima dosen tidak dihapus hanya
                    // karena judulnya berbeda atau relasi lama bermasalah.
                    // Validator akan meminta dosen memperbaiki hubungan.
                    if (in_array($sourceType, ['ai_accepted', 'ai_generated'], true)) {
                        continue;
                    }

                    // Hanya RTM sinkronisasi/legacy mekanis yang boleh dicari
                    // ulang berdasarkan nama asesmen yang sama.
                    $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);
                    $assessment = $assessments->first(function ($candidate) use ($normalizedTaskTitle, $allowedAssessmentTypes): bool {
                        return in_array(strtolower((string) $candidate->type), $allowedAssessmentTypes, true)
                            && $this->normalizeLabel((string) $candidate->name) === $normalizedTaskTitle;
                    });

                    if (! $assessment) {
                        DB::table('rps_task_subcpmks')->where('rps_task_id', $task->id)->delete();
                        DB::table('rps_tasks')->where('id', $task->id)->delete();
                        continue;
                    }

                    DB::table('rps_tasks')->where('id', $task->id)->update([
                        'assessment_id' => $assessment->id,
                        'source_type' => 'assessment_sync',
                        'updated_at' => now(),
                    ]);
                }

                $assessmentSubIds = collect($assessmentLinks->get($assessment->id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();

                if ($assessmentSubIds->isEmpty()) {
                    $linkedCount++;
                    continue;
                }

                $currentTaskSubIds = collect($taskLinks->get($task->id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();

                // RTM dapat mengukur sebagian atau seluruh Sub-CPMK asesmen.
                // Bila relasi RTM kosong/legacy-invalid, fallback ke seluruh
                // cakupan asesmen. Tidak pernah dipersempit oleh due_week.
                $normalizedSubIds = $currentTaskSubIds
                    ->filter(fn ($id) => $assessmentSubIds->contains($id))
                    ->values();

                if ($normalizedSubIds->isEmpty()) {
                    $normalizedSubIds = $assessmentSubIds;
                }

                $before = $currentTaskSubIds->sort()->values()->all();
                $after = $normalizedSubIds->sort()->values()->all();

                if ($before !== $after) {
                    DB::table('rps_task_subcpmks')->where('rps_task_id', $task->id)->delete();
                    foreach ($normalizedSubIds as $subId) {
                        DB::table('rps_task_subcpmks')->insert([
                            'id' => (string) Str::uuid(),
                            'rps_task_id' => $task->id,
                            'rps_sub_cpmk_id' => $subId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if ($sourceType !== 'ai_accepted' && $sourceType !== 'ai_generated' && $sourceType !== 'assessment_sync') {
                    DB::table('rps_tasks')->where('id', $task->id)->update([
                        'source_type' => 'assessment_sync',
                        'updated_at' => now(),
                    ]);
                }

                $linkedCount++;
            }
        });

        return $linkedCount;
    }
'''
pattern = r'''    public function syncTaskMappings\(string \$versionId\): int\n    \{.*?\n    \}\n\n    public function syncWeeklyIndicators'''
s = regex_once(s, pattern, sync_function + "\n    public function syncWeeklyIndicators", 'syncTaskMappings')

task_alignment = r'''    public function taskAlignment(string $versionId): array
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'assessment_id', 'due_week', 'title', 'source_type', 'purpose', 'instructions', 'expected_output'])
            ->values();

        $linkedTasks = $tasks->filter(fn ($task) => filled($task->assessment_id ?? null));
        $assessmentIds = $linkedTasks->pluck('assessment_id')->filter()->unique()->values();

        $assessmentLinks = $assessmentIds->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds->all())
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy('assessment_id');

        $validAssessmentIds = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        $taskLinks = $tasks->isEmpty()
            ? collect()
            : DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $tasks->pluck('id')->all())
                ->get(['rps_task_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_task_id');

        $weekRows = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))
            ->get(['week_number', 'assessment_weight'])
            ->keyBy('week_number');

        $mismatchCount = 0;
        $invalidDueWeekCount = 0;
        $unlinkedWeightedTaskCount = 0;

        foreach ($tasks as $task) {
            $dueWeek = (int) ($task->due_week ?? 0);
            $week = $weekRows->get($dueWeek);
            $actual = collect($taskLinks->get($task->id, []))
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if ($dueWeek < 1 || $dueWeek > 16) {
                $invalidDueWeekCount++;
            }

            if (! filled($task->assessment_id ?? null)) {
                if ($week && (float) ($week->assessment_weight ?? 0) > 0) {
                    $unlinkedWeightedTaskCount++;
                }
                continue;
            }

            $assessmentId = (string) $task->assessment_id;
            if (! $validAssessmentIds->contains($assessmentId)) {
                $mismatchCount++;
                continue;
            }

            $assessmentSubIds = collect($assessmentLinks->get($assessmentId, []))
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            // RTM valid bila memiliki minimal satu Sub-CPMK dan seluruhnya
            // berada di dalam cakupan asesmen induk. Tidak harus sama dengan
            // Sub-CPMK pada pekan pengumpulan dan tidak harus mencakup seluruh
            // asesmen induk.
            $outside = $actual->reject(fn ($id) => $assessmentSubIds->contains($id));
            if ($actual->isEmpty() || $assessmentSubIds->isEmpty() || $outside->isNotEmpty()) {
                $mismatchCount++;
            }
        }

        $requiredAssessmentIds = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->pluck('id')->map(fn ($id) => (string) $id)->unique()->values();

        $coveredAssessmentIds = $linkedTasks->pluck('assessment_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $validAssessmentIds->contains($id))
            ->unique()
            ->values();
        $missingRequired = $requiredAssessmentIds->diff($coveredAssessmentIds)->values();

        return [
            'task_total' => $tasks->count(),
            'linked_task_total' => $linkedTasks->count(),
            'required_assessment_total' => $requiredAssessmentIds->count(),
            'missing_required_assessment_count' => $missingRequired->count(),
            'mapping_mismatch_count' => $mismatchCount,
            'unlinked_weighted_task_count' => $unlinkedWeightedTaskCount,
            // Nama key legacy dipertahankan untuk kompatibilitas UI/API. Nilai
            // kini hanya menunjukkan jadwal pengumpulan yang tidak valid,
            // bukan ketidaksamaan Sub-CPMK pekan dengan RTM.
            'due_week_subcpmk_mismatch_count' => $invalidDueWeekCount,
            'is_aligned' => $missingRequired->isEmpty()
                && $mismatchCount === 0
                && $unlinkedWeightedTaskCount === 0
                && $invalidDueWeekCount === 0,
        ];
    }
'''
pattern = r'''    public function taskAlignment\(string \$versionId\): array\n    \{.*?\n    \}\n\n    public function rebalanceTeachingWeek'''
s = regex_once(s, pattern, task_alignment + "\n    public function rebalanceTeachingWeek", 'taskAlignment')
p.write_text(s)

# ---------------------------------------------------------------------------
# 5) Validator semantic RTM: judge title + purpose/instructions, not title only.
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text()
s = replace_once(
    s,
    "            ->get(['id', 'code', 'title', 'assessment_id', 'due_week']);",
    "            ->get(['id', 'code', 'title', 'assessment_id', 'due_week', 'purpose', 'instructions']);",
    'validator task fields',
)
s = replace_once(
    s,
    "            $taskCore = $this->assessmentCoreLabel((string) $task->title);\n            $assessmentCore = $this->assessmentCoreLabel((string) $assessment->name);",
    "            $taskCore = $this->assessmentCoreLabel(trim((string) $task->title.' '.(string) ($task->purpose ?? '').' '.(string) ($task->instructions ?? '')));\n            $assessmentCore = $this->assessmentCoreLabel(trim((string) $assessment->name.' '.(string) ($assessment->description ?? '')));",
    'validator RTM semantic context',
)
p.write_text(s)

# ---------------------------------------------------------------------------
# 6) Local assessment fallback: provide genuinely useful RTM content.
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/AiRpsProviderService.php')
s = p.read_text()
replacements = {
    "'purpose' => 'Mengukur penguasaan capaian Sub-CPMK awal secara terstruktur.',": "'purpose' => 'Penugasan ini digunakan untuk memperoleh bukti penguasaan capaian Sub-CPMK tahap awal melalui pekerjaan terstruktur yang menuntut mahasiswa menjelaskan konsep, menerapkan prosedur yang relevan, dan menunjukkan alasan atau proses penyelesaian secara dapat ditelusuri.',",
    "'instructions' => 'Kerjakan tugas berdasarkan materi dan aktivitas pembelajaran yang telah dilaksanakan. Sertakan proses/argumentasi yang mendukung jawaban.',": "'instructions' => \"1. Identifikasi konsep dan Bahan Kajian yang relevan dengan tugas.\\n2. Rumuskan persoalan/objek yang akan dikerjakan berdasarkan kegiatan pembelajaran yang sudah berlangsung.\\n3. Kerjakan analisis, perhitungan, pembuktian, atau prosedur yang sesuai dengan karakter mata kuliah.\\n4. Tunjukkan langkah kerja dan alasan pada setiap keputusan penting.\\n5. Periksa kembali konsistensi hasil dengan konsep yang digunakan.\\n6. Susun hasil secara runtut dan serahkan pada pekan yang ditetapkan.\",",
    "'expected_output' => 'Dokumen tugas/laporan ringkas sesuai ketentuan dosen.',": "'expected_output' => \"Luaran minimal memuat:\\n- rumusan/objek tugas yang dikerjakan;\\n- proses atau langkah penyelesaian yang dapat ditelusuri;\\n- hasil akhir beserta interpretasi singkat;\\n- dokumentasi pendukung yang relevan dengan karakter mata kuliah.\",",
    "'purpose' => 'Mengukur kemampuan penerapan konsep dan keterampilan pada tahap menengah.',": "'purpose' => 'Penugasan praktis/terstruktur ini menilai kemampuan mahasiswa menerapkan konsep pada situasi yang lebih operasional, menjalankan prosedur secara benar, membaca hasil yang diperoleh, serta mendokumentasikan proses dan temuan secara akademik.',",
    "'instructions' => 'Laksanakan aktivitas terstruktur sesuai konteks mata kuliah dan dokumentasikan proses serta hasil.',": "'instructions' => \"1. Pelajari kembali konsep dan prosedur yang menjadi dasar aktivitas.\\n2. Siapkan data, contoh, perangkat, atau objek kerja yang memang tersedia pada konteks mata kuliah.\\n3. Laksanakan prosedur/praktik secara bertahap dan catat keputusan penting.\\n4. Dokumentasikan hasil antara dan hasil akhir.\\n5. Analisis kesesuaian hasil dengan konsep atau kriteria yang dipelajari.\\n6. Susun laporan/produk dan lakukan pemeriksaan akhir sebelum pengumpulan.\",",
    "'expected_output' => 'Laporan/produk praktik atau tugas terstruktur.',": "'expected_output' => \"Luaran minimal memuat:\\n- dokumentasi proses pelaksanaan;\\n- hasil praktik/produk atau penyelesaian tugas;\\n- analisis atau interpretasi hasil;\\n- simpulan singkat berdasarkan bukti yang diperoleh.\",",
    "'purpose' => 'Mengintegrasikan capaian Sub-CPMK lanjut dalam satu pekerjaan komprehensif.',": "'purpose' => 'Proyek ini mengintegrasikan beberapa capaian Sub-CPMK tingkat lanjut dalam satu pekerjaan komprehensif. Mahasiswa diharapkan menghubungkan konsep, memilih pendekatan yang relevan, menghasilkan solusi/produk, mengevaluasi hasilnya, dan mengomunikasikan proses serta temuan secara runtut.',",
    "'instructions' => 'Susun proyek/tugas integratif yang menunjukkan proses analisis, penerapan, evaluasi, dan komunikasi hasil.',": "'instructions' => \"1. Tetapkan fokus masalah/produk proyek berdasarkan Bahan Kajian dan Sub-CPMK yang diukur.\\n2. Susun rancangan kerja dan tentukan metode/prosedur yang akan digunakan.\\n3. Laksanakan analisis, perancangan, implementasi, atau pengembangan sesuai karakter mata kuliah.\\n4. Uji/periksa hasil dan dokumentasikan bukti penting.\\n5. Evaluasi kelebihan, keterbatasan, atau implikasi hasil.\\n6. Susun laporan/produk akhir dan bahan komunikasi hasil.\\nTahap/Milestone: tahap awal = perumusan/rancangan; tahap tengah = pelaksanaan dan pemeriksaan hasil; tahap akhir = evaluasi, penyempurnaan, dan pengumpulan pada pekan yang ditetapkan.\",",
    "'expected_output' => 'Laporan/produk akhir dan bahan presentasi sesuai kebutuhan mata kuliah.',": "'expected_output' => \"Luaran minimal memuat:\\n- rancangan atau rumusan masalah proyek;\\n- bukti proses analisis/implementasi/pengembangan;\\n- produk atau hasil akhir;\\n- evaluasi/interpretasi hasil;\\n- laporan akhir dan bahan presentasi/komunikasi hasil bila relevan.\",",
}
for old, new in replacements.items():
    if old in s:
        s = s.replace(old, new, 1)
p.write_text(s)

# ---------------------------------------------------------------------------
# 7) Give assessment-plan providers enough output room for detailed RTMs.
# ---------------------------------------------------------------------------
for p in Path('app/Services/Rps').glob('*RpsService.php'):
    text = p.read_text()
    updated = re.sub(
        r"('assessment_plan'\s*=>\s*)(\d+)",
        lambda m: m.group(1) + str(max(3000, int(m.group(2)))),
        text,
    )
    if updated != text:
        p.write_text(updated)

# ---------------------------------------------------------------------------
# 8) Small UI/document wording clarifications.
# ---------------------------------------------------------------------------
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text()
s = s.replace(
    'Rencana tugas mahasiswa yang terhubung dengan asesmen.',
    'Satu RTM dapat mengukur satu atau lebih Sub-CPMK dalam cakupan asesmen induk.',
)
s = s.replace(
    '>PEKAN KE-</td>',
    '>PEKAN PENGUMPULAN</td>',
)
p.write_text(s)

print('Integrative RTM patch applied.')
