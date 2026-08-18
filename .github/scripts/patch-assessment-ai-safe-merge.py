from pathlib import Path

# --- Context: assessment AI must see existing assessment + RTM state ---
context_path = Path('app/Services/Rps/RpsAiContextService.php')
context = context_path.read_text()

old_assessment_return = '''                return [
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'week_number' => $assessment->week_number,
                    'weight' => $assessment->weight,
                    'sub_cpmk_codes' => $subCodes,
                ];
'''
new_assessment_return = '''                return [
                    'code' => $assessment->code,
                    'name' => $assessment->name,
                    'type' => $assessment->type,
                    'week_number' => $assessment->week_number,
                    'description' => $assessment->description,
                    'weight' => $assessment->weight,
                    'source_type' => $assessment->source_type,
                    'sub_cpmk_codes' => $subCodes,
                ];
'''
if old_assessment_return not in context:
    raise SystemExit('assessment context return marker not found')
context = context.replace(old_assessment_return, new_assessment_return, 1)

marker = '''        $documentMeta = null;
'''
tasks_block = '''        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->orderBy('due_week')
            ->orderBy('code')
            ->get()
            ->map(function ($task): array {
                $subCodes = DB::table('rps_task_subcpmks')
                    ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'rps_task_subcpmks.rps_sub_cpmk_id')
                    ->where('rps_task_subcpmks.rps_task_id', $task->id)
                    ->orderBy('rps_sub_cpmks.sequence_no')
                    ->pluck('rps_sub_cpmks.code')
                    ->all();

                $assessment = filled($task->assessment_id ?? null)
                    ? DB::table('assessments')->where('id', $task->assessment_id)->first(['code', 'name'])
                    : null;

                return [
                    'code' => $task->code,
                    'title' => $task->title,
                    'type' => $task->type,
                    'assessment_code' => $assessment?->code,
                    'assessment_name' => $assessment?->name,
                    'due_week' => $task->due_week,
                    'purpose' => $task->purpose,
                    'instructions' => $task->instructions,
                    'expected_output' => $task->expected_output,
                    'source_type' => $task->source_type,
                    'sub_cpmk_codes' => $subCodes,
                ];
            })
            ->all();

'''
if marker not in context:
    raise SystemExit('document meta marker not found')
context = context.replace(marker, tasks_block + marker, 1)

old_full = "            'assessments' => $assessments,\n            'constraints' => ["
new_full = "            'assessments' => $assessments,\n            'tasks' => $tasks,\n            'constraints' => ["
if old_full not in context:
    raise SystemExit('full context assessment marker not found')
context = context.replace(old_full, new_full, 1)

old_compact = '''                'current_assessments' => collect($full['assessments'])
                    ->map(fn (array $assessment): array => [
                        'code' => $assessment['code'] ?? null,
                        'name' => $this->clip($assessment['name'] ?? null, 120),
                        'type' => $assessment['type'] ?? null,
                        'week_number' => $assessment['week_number'] ?? null,
                        'weight' => $assessment['weight'] ?? null,
                        'sub_cpmk_codes' => $assessment['sub_cpmk_codes'] ?? [],
                    ])
                    ->take(16)
                    ->values()
                    ->all(),
'''
new_compact = '''                'current_assessments' => collect($full['assessments'])
                    ->map(fn (array $assessment): array => [
                        'code' => $assessment['code'] ?? null,
                        'name' => $this->clip($assessment['name'] ?? null, 120),
                        'type' => $assessment['type'] ?? null,
                        'week_number' => $assessment['week_number'] ?? null,
                        'description' => $this->clip($assessment['description'] ?? null, 260),
                        'weight' => $assessment['weight'] ?? null,
                        'source_type' => $assessment['source_type'] ?? null,
                        'sub_cpmk_codes' => $assessment['sub_cpmk_codes'] ?? [],
                    ])
                    ->take(20)
                    ->values()
                    ->all(),
                'current_tasks' => collect($full['tasks'] ?? [])
                    ->map(fn (array $task): array => [
                        'code' => $task['code'] ?? null,
                        'title' => $this->clip($task['title'] ?? null, 140),
                        'type' => $task['type'] ?? null,
                        'assessment_code' => $task['assessment_code'] ?? null,
                        'assessment_name' => $this->clip($task['assessment_name'] ?? null, 120),
                        'due_week' => $task['due_week'] ?? null,
                        'purpose' => $this->clip($task['purpose'] ?? null, 360),
                        'source_type' => $task['source_type'] ?? null,
                        'sub_cpmk_codes' => $task['sub_cpmk_codes'] ?? [],
                    ])
                    ->take(24)
                    ->values()
                    ->all(),
'''
if old_compact not in context:
    raise SystemExit('compact assessment context marker not found')
context = context.replace(old_compact, new_compact, 1)
context_path.write_text(context)

# --- Controller: safe merge semantics and deterministic target annotation ---
controller_path = Path('app/Http/Controllers/RpsAiController.php')
controller = controller_path.read_text()

old_intro = '''Untuk Telaah Asesmen + RTM, perlakukan ASESMEN sebagai rencana pengukuran agregat dan RTM sebagai lembar instruksi tugas konkret bagi mahasiswa.

ATURAN CAKUPAN RTM:
'''
new_intro = '''Untuk Telaah Asesmen + RTM, perlakukan ASESMEN sebagai rencana pengukuran agregat dan RTM sebagai lembar instruksi tugas konkret bagi mahasiswa.

MODE TELAAH / MERGE AMAN:
- `current_assessments` dan `current_tasks` adalah kondisi RPS dosen SAAT INI. Telaah dan manfaatkan data itu; jangan berasumsi RPS kosong.
- Pertahankan asesmen/RTM lama yang sudah selaras. Jangan menduplikasi item yang secara akademik sudah mewakili fungsi yang sama.
- Jika item lama perlu diperbaiki, rekomendasikan bentuk target-state yang masih dapat dikenali dari tipe, cakupan Sub-CPMK, jadwal, dan konteks tugasnya. Sistem akan menandainya sebagai PERBAIKI dan hanya mengubahnya bila dosen memilih rekomendasi tersebut.
- Tambahkan item baru hanya untuk celah asesmen/RTM yang benar-benar belum tercakup.
- Jangan menghapus asesmen/RTM lama secara implisit. Penghapusan tetap keputusan eksplisit dosen di editor.
- Target-state asesmen setelah mempertahankan/perbaiki/menambah harus tepat 100%, bukan 100% baru yang ditumpuk di atas bobot lama.
- Pastikan seluruh Sub-CPMK aktif memiliki bukti asesmen dan RTM yang relevan; gunakan keterkaitan Sub-CPMK sebagai dasar utama constructive alignment.

ATURAN CAKUPAN RTM:
'''
if old_intro not in controller:
    raise SystemExit('assessment instruction intro marker not found')
controller = controller.replace(old_intro, new_intro, 1)
controller = controller.replace("'rtm-integrative-v3-constructive-alignment'", "'rtm-integrative-v4-safe-merge'", 1)

old_tasks_assign = '''        $payload['tasks'] = $tasks;

        if ($adjusted > 0) {
'''
new_tasks_assign = '''        $payload['tasks'] = $tasks;
        $payload = $this->annotateAssessmentMergeActions($payload, $version);

        if ($adjusted > 0) {
'''
if old_tasks_assign not in controller:
    raise SystemExit('sanitize task assignment marker not found')
controller = controller.replace(old_tasks_assign, new_tasks_assign, 1)

helper_anchor = '''    private function normalizeSubCpmkLookupCode(string $value): string
    {
'''
helpers = r'''    private function annotateAssessmentMergeActions(array $payload, object $version): array
    {
        $existingAssessments = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'description', 'weight', 'source_type']);

        $assessmentIds = $existingAssessments->pluck('id')->all();
        $assessmentLinks = $assessmentIds === []
            ? collect()
            : DB::table('assessment_subcpmks')
                ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
                ->whereIn('assessment_subcpmks.assessment_id', $assessmentIds)
                ->get(['assessment_subcpmks.assessment_id', 'rps_sub_cpmks.code'])
                ->groupBy('assessment_id');

        $claimedAssessmentIds = [];
        $assessmentItems = $payload['assessments'] ?? [];

        foreach ($assessmentItems as $index => $item) {
            if (! is_array($item)) continue;

            $type = strtolower(trim((string) ($item['type'] ?? 'other')));
            $week = $type === 'uts' ? 8 : ($type === 'uas' ? 16 : (int) ($item['week_number'] ?? 0));
            $name = trim((string) ($item['name'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->values();

            $available = $existingAssessments
                ->reject(fn ($row) => in_array((string) $row->id, $claimedAssessmentIds, true))
                ->values();

            $match = null;
            if (in_array($type, ['uts', 'uas'], true)) {
                $match = $available->first(fn ($row) => strtolower((string) $row->type) === $type);
            }

            if (! $match && $name !== '') {
                $needle = $this->comparableText($name);
                $match = $available->first(fn ($row) => $this->comparableText((string) $row->name) === $needle);
            }

            if (! $match) {
                $ranked = $available
                    ->filter(fn ($row) => strtolower((string) $row->type) === $type)
                    ->map(function ($row) use ($assessmentLinks, $wantedSubs, $week, $name): array {
                        $currentSubs = collect($assessmentLinks->get($row->id, []))
                            ->pluck('code')
                            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                            ->filter()->unique()->values();
                        $overlap = $wantedSubs->intersect($currentSubs)->count();
                        $sameWeek = $week > 0 && (int) ($row->week_number ?? 0) === $week;
                        $nameOverlap = count(array_intersect(
                            $this->semanticTokens($name),
                            $this->semanticTokens((string) $row->name)
                        ));
                        $score = ($overlap * 6) + ($sameWeek ? 3 : 0) + min(3, $nameOverlap);
                        return ['row' => $row, 'score' => $score, 'overlap' => $overlap];
                    })
                    ->sortByDesc('score')
                    ->values();

                $best = $ranked->first();
                if ($best && (($best['overlap'] ?? 0) > 0 || ($best['score'] ?? 0) >= 5)) {
                    $match = $best['row'];
                }
            }

            if ($match) {
                $claimedAssessmentIds[] = (string) $match->id;
                $currentSubs = collect($assessmentLinks->get($match->id, []))
                    ->pluck('code')
                    ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                    ->filter()->unique()->sort()->values()->all();
                $newSubs = $wantedSubs->sort()->values()->all();
                $same = $this->comparableText((string) $match->name) === $this->comparableText($name)
                    && strtolower((string) $match->type) === $type
                    && (int) ($match->week_number ?? 0) === $week
                    && abs((float) $match->weight - (float) ($item['weight'] ?? 0)) < 0.01
                    && $currentSubs === $newSubs
                    && $this->comparableText((string) ($match->description ?? '')) === $this->comparableText((string) ($item['description'] ?? ''));

                $assessmentItems[$index]['action'] = $same ? 'keep' : 'adapt';
                $assessmentItems[$index]['target_code'] = (string) $match->code;
                $assessmentItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $assessmentItems[$index]['rationale'] = $same
                    ? 'Asesmen yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'Asesmen yang sudah ada dikenali sebagai target perbaikan berdasarkan tipe, jadwal, dan cakupan Sub-CPMK.';
            } else {
                $assessmentItems[$index]['action'] = 'add';
                $assessmentItems[$index]['target_code'] = null;
                $assessmentItems[$index]['target_source_type'] = null;
                $assessmentItems[$index]['rationale'] = 'Belum ada asesmen aktif yang cukup setara; rekomendasi ini merupakan tambahan.';
            }
        }

        $payload['assessments'] = $assessmentItems;

        $existingTasks = DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'type', 'assessment_id', 'due_week', 'purpose', 'instructions', 'expected_output', 'source_type']);
        $taskIds = $existingTasks->pluck('id')->all();
        $taskLinks = $taskIds === [] ? collect() : DB::table('rps_task_subcpmks')
            ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'rps_task_subcpmks.rps_sub_cpmk_id')
            ->whereIn('rps_task_subcpmks.rps_task_id', $taskIds)
            ->get(['rps_task_subcpmks.rps_task_id', 'rps_sub_cpmks.code'])
            ->groupBy('rps_task_id');
        $assessmentById = $existingAssessments->keyBy(fn ($row) => (string) $row->id);
        $claimedTaskIds = [];
        $taskItems = $payload['tasks'] ?? [];

        foreach ($taskItems as $index => $item) {
            if (! is_array($item)) continue;

            $title = trim((string) ($item['title'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'assignment')));
            $dueWeek = (int) ($item['due_week'] ?? 0);
            $assessmentName = trim((string) ($item['assessment_name'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->values();
            $available = $existingTasks
                ->reject(fn ($row) => in_array((string) $row->id, $claimedTaskIds, true))
                ->values();

            $match = null;
            if ($title !== '') {
                $needle = $this->comparableText($title);
                $match = $available->first(fn ($row) => $this->comparableText((string) $row->title) === $needle);
            }

            if (! $match) {
                $ranked = $available
                    ->filter(fn ($row) => strtolower((string) $row->type) === $type)
                    ->map(function ($row) use ($taskLinks, $assessmentById, $wantedSubs, $dueWeek, $assessmentName): array {
                        $currentSubs = collect($taskLinks->get($row->id, []))
                            ->pluck('code')
                            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                            ->filter()->unique()->values();
                        $overlap = $wantedSubs->intersect($currentSubs)->count();
                        $sameWeek = $dueWeek > 0 && (int) ($row->due_week ?? 0) === $dueWeek;
                        $parent = filled($row->assessment_id ?? null)
                            ? $assessmentById->get((string) $row->assessment_id)
                            : null;
                        $sameAssessment = $parent && $assessmentName !== ''
                            && $this->comparableText((string) $parent->name) === $this->comparableText($assessmentName);
                        $score = ($overlap * 6) + ($sameWeek ? 2 : 0) + ($sameAssessment ? 4 : 0);
                        return ['row' => $row, 'score' => $score, 'overlap' => $overlap];
                    })
                    ->sortByDesc('score')->values();
                $best = $ranked->first();
                if ($best && (($best['overlap'] ?? 0) > 0 || ($best['score'] ?? 0) >= 6)) {
                    $match = $best['row'];
                }
            }

            if ($match) {
                $claimedTaskIds[] = (string) $match->id;
                $currentSubs = collect($taskLinks->get($match->id, []))
                    ->pluck('code')
                    ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                    ->filter()->unique()->sort()->values()->all();
                $newSubs = $wantedSubs->sort()->values()->all();
                $parent = filled($match->assessment_id ?? null)
                    ? $assessmentById->get((string) $match->assessment_id)
                    : null;
                $same = $this->comparableText((string) $match->title) === $this->comparableText($title)
                    && strtolower((string) $match->type) === $type
                    && (int) ($match->due_week ?? 0) === $dueWeek
                    && $currentSubs === $newSubs
                    && $this->comparableText((string) ($parent->name ?? '')) === $this->comparableText($assessmentName)
                    && $this->comparableText((string) ($match->purpose ?? '')) === $this->comparableText((string) ($item['purpose'] ?? ''));

                $taskItems[$index]['action'] = $same ? 'keep' : 'adapt';
                $taskItems[$index]['target_code'] = (string) $match->code;
                $taskItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $taskItems[$index]['rationale'] = $same
                    ? 'RTM yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'RTM yang sudah ada dikenali sebagai target perbaikan berdasarkan asesmen induk, jadwal, dan cakupan Sub-CPMK.';
            } else {
                $taskItems[$index]['action'] = 'add';
                $taskItems[$index]['target_code'] = null;
                $taskItems[$index]['target_source_type'] = null;
                $taskItems[$index]['rationale'] = 'Belum ada RTM aktif yang cukup setara; rekomendasi ini merupakan tambahan.';
            }
        }

        $payload['tasks'] = $taskItems;
        $payload['_merge_mode'] = 'safe_review';
        $summary = trim((string) ($payload['summary'] ?? ''));
        $note = 'Telaah bersifat non-destruktif: item lama dipertahankan, diperbaiki hanya bila dipilih, dan tidak dihapus otomatis.';
        $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$note : $note;

        return $payload;
    }

'''
if helper_anchor not in controller:
    raise SystemExit('controller helper anchor not found')
controller = controller.replace(helper_anchor, helpers + helper_anchor, 1)

start = controller.find('    private function applyAssessmentPlanSelective(')
end = controller.find('    private function ensureAllSubCpmksCoveredByTasks(', start)
if start < 0 or end < 0:
    raise SystemExit('applyAssessmentPlanSelective block not found')

new_apply = r'''    private function applyAssessmentPlanSelective(
        array $payload,
        array $selectedAssessmentIndices,
        array $selectedTaskIndices,
        object $version,
        int $userId
    ): array {
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
        $changedTasks = 0;
        $affectedWeeks = [];

        // Hitung target bobot sebagai MERGE: ADAPT mengganti bobot lama,
        // ADD menambah, KEEP tidak mengubah. Ini mencegah kasus 10% lama +
        // rencana AI 100% dibaca keliru sebagai 110% padahal salah satu item
        // seharusnya merupakan perbaikan asesmen lama.
        $projectedTotal = (float) DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->sum('weight');

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) continue;
            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') continue;

            $newWeight = (float) ($item['weight'] ?? 0);
            if ($action === 'adapt') {
                $targetCode = trim((string) ($item['target_code'] ?? ''));
                $target = $targetCode !== ''
                    ? DB::table('assessments')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first(['id', 'weight'])
                    : null;
                if (! $target) {
                    throw ValidationException::withMessages([
                        'ai' => 'Target asesmen yang akan diperbaiki sudah berubah atau tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
                $projectedTotal -= (float) $target->weight;
            }
            $projectedTotal += $newWeight;
        }

        $projectedTotal = round($projectedTotal, 2);
        if ($projectedTotal > 100.001) {
            throw ValidationException::withMessages([
                'ai' => "Pilihan rekomendasi akan membuat total bobot asesmen {$projectedTotal}%. Telaah ulang atau pilih hanya rekomendasi PERBAIKI/TAMBAH yang diperlukan. Asesmen lama tidak dihapus otomatis.",
            ]);
        }

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan asesmen AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') continue;
            if (! in_array($action, ['adapt', 'add'], true)) {
                throw ValidationException::withMessages(['ai' => 'Aksi asesmen AI tidak dikenali. Buat telaah baru.']);
            }

            $type = strtolower((string) ($item['type'] ?? 'other'));
            $week = (int) ($item['week_number'] ?? 1);
            if ($type === 'uts') $week = 8;
            if ($type === 'uas') $week = 16;

            $existing = null;
            if ($action === 'adapt') {
                $targetCode = trim((string) ($item['target_code'] ?? ''));
                $existing = $targetCode !== ''
                    ? DB::table('assessments')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first()
                    : null;
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali agar konteks diperbarui.',
                    ]);
                }
            } else {
                $name = trim((string) ($item['name'] ?? ''));
                $duplicate = $name !== '' && DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen yang akan ditambahkan ternyata sudah ada. Jalankan telaah ulang agar AI menandainya sebagai PERBAIKI/PERTAHANKAN.',
                    ]);
                }
            }

            $assessmentId = $existing?->id ?: (string) Str::uuid();
            $values = [
                'name' => trim((string) ($item['name'] ?? 'Asesmen AI')),
                'type' => $type,
                'week_number' => $week,
                'description' => (string) ($item['description'] ?? ''),
                'weight' => (float) ($item['weight'] ?? 0),
                'source_type' => $action === 'adapt' ? 'ai_adapted' : 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('assessments')->where('id', $assessmentId)->update($values);
            } else {
                DB::table('assessments')->insert($values + [
                    'id' => $assessmentId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextAssessmentCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('assessment_subcpmks')->where('assessment_id', $assessmentId)->delete();
            foreach (array_unique($item['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');
                if ($subId) {
                    DB::table('assessment_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'assessment_id' => $assessmentId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $changedAssessments++;
            if (in_array($type, ['uts', 'uas'], true)) $affectedWeeks[] = $week;
        }

        foreach ($selectedTaskIndices as $index) {
            $task = $tasks[$index] ?? null;
            if (! is_array($task)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan RTM AI tidak valid.']);
            }

            $action = strtolower((string) ($task['action'] ?? 'add'));
            if ($action === 'keep') continue;
            if (! in_array($action, ['adapt', 'add'], true)) {
                throw ValidationException::withMessages(['ai' => 'Aksi RTM AI tidak dikenali. Buat telaah baru.']);
            }

            $title = trim((string) ($task['title'] ?? 'RTM AI'));
            $existing = null;
            if ($action === 'adapt') {
                $targetCode = trim((string) ($task['target_code'] ?? ''));
                $existing = $targetCode !== ''
                    ? DB::table('rps_tasks')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first()
                    : null;
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
            } else {
                $duplicate = DB::table('rps_tasks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM yang akan ditambahkan ternyata sudah ada. Jalankan telaah ulang agar AI menandainya sebagai PERBAIKI/PERTAHANKAN.',
                    ]);
                }
            }

            $assessmentId = null;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));
            if ($assessmentName !== '') {
                $assessmentId = DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($assessmentName)])
                    ->value('id');
            }
            if (! $assessmentId && $existing && filled($existing->assessment_id ?? null)) {
                $assessmentId = $existing->assessment_id;
            }
            if (! $assessmentId) {
                throw ValidationException::withMessages([
                    'ai' => 'Asesmen induk untuk RTM belum tersedia. Pilih juga rekomendasi asesmen terkait atau jalankan telaah ulang.',
                ]);
            }

            $taskId = $existing?->id ?: (string) Str::uuid();
            $values = [
                'assessment_id' => $assessmentId,
                'title' => $title,
                'type' => (string) ($task['type'] ?? 'assignment'),
                'purpose' => (string) ($task['purpose'] ?? ''),
                'instructions' => (string) ($task['instructions'] ?? ''),
                'expected_output' => (string) ($task['expected_output'] ?? ''),
                'due_week' => (int) ($task['due_week'] ?? 1),
                'source_type' => $action === 'adapt' ? 'ai_adapted' : 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('rps_tasks')->where('id', $taskId)->update($values);
            } else {
                DB::table('rps_tasks')->insert($values + [
                    'id' => $taskId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextTaskCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('rps_task_subcpmks')->where('rps_task_id', $taskId)->delete();
            foreach (array_unique($task['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');
                if ($subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $taskId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $changedTasks++;
        }

        foreach (array_unique($affectedWeeks) as $affectedWeek) {
            if (! in_array((int) $affectedWeek, [8, 16], true)) continue;
            $weekWeight = round((float) DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $affectedWeek)
                ->whereIn('type', ['uts', 'uas'])
                ->sum('weight'), 2);
            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $affectedWeek)
                ->update(['assessment_weight' => $weekWeight, 'updated_at' => now()]);
        }

        app(RpsAssessmentSyncService::class)->syncVersion($version->id);

        $totalWeight = round((float) DB::table('assessments')
            ->where('rps_version_id', $version->id)->sum('weight'), 2);
        $message = "{$changedAssessments} asesmen dan {$changedTasks} RTM terpilih diterapkan dengan mode merge aman.";
        if ($changedAssessments > 0 && abs($totalWeight - 100.0) >= 0.01) {
            $message .= " Total bobot asesmen saat ini {$totalWeight}%; sesuaikan hingga tepat 100%.";
        } elseif ($changedAssessments > 0) {
            $message .= ' Total bobot asesmen 100%. Distribusi bobot pekan, RTM, matriks, dan simulasi disinkronkan.';
        }

        return ['changed' => $changedAssessments + $changedTasks, 'message' => $message];
    }

'''
controller = controller[:start] + new_apply + controller[end:]
controller_path.write_text(controller)

# --- Provider system prompts: no more implicit full replacement ---
services = [
    Path('app/Services/Rps/GroqRpsService.php'),
    Path('app/Services/Rps/MistralRpsService.php'),
    Path('app/Services/Rps/SambaNovaRpsService.php'),
    Path('app/Services/Rps/OpenRouterRpsService.php'),
    Path('app/Services/Rps/HuggingFaceRpsService.php'),
    Path('app/Services/Rps/CohereRpsService.php'),
]
old_prompt = 'Tugas: telaah asesmen/RTM yang sudah ada lalu rekomendasikan SATU rencana asesmen lengkap sebagai pengganti bila dosen menyetujuinya.'
new_prompt = 'Tugas: telaah asesmen dan RTM yang sudah ada sebagai kondisi aktif, lalu susun target-state asesmen yang aman untuk digabung bila dosen menyetujuinya. Pertahankan item yang sudah selaras, perbaiki fungsi yang sama tanpa menduplikasi, dan tambahkan hanya celah yang belum tercakup. Jangan menghapus data lama secara implisit.'
replaced = 0
for path in services:
    if not path.exists():
        continue
    text = path.read_text()
    if old_prompt in text:
        text = text.replace(old_prompt, new_prompt, 1)
        path.write_text(text)
        replaced += 1
if replaced < 1:
    raise SystemExit('assessment provider prompt marker not found in any provider')

# --- UI: select only actionable merge items and communicate semantics ---
show_path = Path('resources/js/pages/rps/show.tsx')
show = show_path.read_text()
old_states = '''    const [selectedAssessmentIndices, setSelectedAssessmentIndices] = useState<number[]>(
        safeList(payload.assessments).map((_: any, index: number) => index),
    );
    const [selectedTaskIndices, setSelectedTaskIndices] = useState<number[]>(
        safeList(payload.tasks).map((_: any, index: number) => index),
    );
'''
new_states = '''    const [selectedAssessmentIndices, setSelectedAssessmentIndices] = useState<number[]>(
        safeList(payload.assessments)
            .map((item: any, index: number) => ({ item, index }))
            .filter(({ item }: any) => String(item?.action ?? 'add').toLowerCase() !== 'keep')
            .map(({ index }: any) => index),
    );
    const [selectedTaskIndices, setSelectedTaskIndices] = useState<number[]>(
        safeList(payload.tasks)
            .map((item: any, index: number) => ({ item, index }))
            .filter(({ item }: any) => String(item?.action ?? 'add').toLowerCase() !== 'keep')
            .map(({ index }: any) => index),
    );
'''
if old_states not in show:
    raise SystemExit('AI suggestion selected state marker not found')
show = show.replace(old_states, new_states, 1)

old_confirm = '''        const message = suggestion.suggestion_type === 'assessment_plan'
            ? `Terapkan ${selectedAssessmentIndices.length} asesmen dan ${selectedTaskIndices.length} RTM yang dipilih? Data yang tidak dipilih tidak akan diubah.`
'''
new_confirm = '''        const message = suggestion.suggestion_type === 'assessment_plan'
            ? `Terapkan ${selectedAssessmentIndices.length} perubahan asesmen dan ${selectedTaskIndices.length} perubahan RTM yang dipilih? Item berstatus Pertahankan dan data lain yang tidak dipilih tidak akan diubah atau dihapus.`
'''
if old_confirm not in show:
    raise SystemExit('assessment confirmation marker not found')
show = show.replace(old_confirm, new_confirm, 1)

# Add badges in assessment/task previews without changing payload semantics.
assessment_name = '''                                <strong>{safeText(item?.name)}</strong>
                                {' | '}Pekan {safeText(item?.week_number)}
'''
assessment_badge = '''                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>{safeText(item?.name)}</strong>
                                    <span className={`rounded-full px-2 py-0.5 text-[9px] font-extrabold ${
                                        String(item?.action ?? 'add').toLowerCase() === 'keep'
                                            ? 'bg-slate-100 text-slate-600'
                                            : String(item?.action ?? 'add').toLowerCase() === 'adapt'
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-emerald-100 text-emerald-800'
                                    }`}>
                                        {String(item?.action ?? 'add').toLowerCase() === 'keep' ? 'Pertahankan' : String(item?.action ?? 'add').toLowerCase() === 'adapt' ? 'Perbaiki' : 'Tambah'}
                                    </span>
                                </div>
                                Pekan {safeText(item?.week_number)}
'''
if assessment_name in show:
    show = show.replace(assessment_name, assessment_badge, 1)

# Task preview title occurs separately; enrich if found.
task_title = '''                                <strong>{safeText(item?.title)}</strong>
'''
task_badge = '''                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>{safeText(item?.title)}</strong>
                                    <span className={`rounded-full px-2 py-0.5 text-[9px] font-extrabold ${
                                        String(item?.action ?? 'add').toLowerCase() === 'keep'
                                            ? 'bg-slate-100 text-slate-600'
                                            : String(item?.action ?? 'add').toLowerCase() === 'adapt'
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-emerald-100 text-emerald-800'
                                    }`}>
                                        {String(item?.action ?? 'add').toLowerCase() === 'keep' ? 'Pertahankan' : String(item?.action ?? 'add').toLowerCase() === 'adapt' ? 'Perbaiki' : 'Tambah'}
                                    </span>
                                </div>
'''
if task_title in show:
    show = show.replace(task_title, task_badge, 1)

show_path.write_text(show)
