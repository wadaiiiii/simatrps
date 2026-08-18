from pathlib import Path

def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"{label} marker not found")
    return text.replace(old, new, 1)

# 1) Detail Asesmen is the source of truth for weekly form/weight.
sync_path = Path("app/Services/Rps/RpsAssessmentSyncService.php")
sync = sync_path.read_text()

sync = replace_once(sync, """            $fallback = trim((string) ($week->assessment_method ?? ''));
            if ($fallback !== '') {
                $namesByWeek[$weekNumber] = [$fallback];
                $evidenceSourceByWeek[$weekNumber] = 'weekly_method';
            }

            if ((int) ($expectedCents[$weekNumber] ?? 0) > 0 && $fallback === '') {
                $missingEvidenceWeeks[] = $weekNumber;
            }
""", """            // Tidak ada fallback dari assessment_method pekanan. Detail
            // Asesmen adalah sumber kebenaran bentuk/bukti penilaian. Jika belum
            // ada asesmen induk, pekan tetap ditandai belum terhubung.
            if ((int) ($expectedCents[$weekNumber] ?? 0) > 0) {
                $missingEvidenceWeeks[] = $weekNumber;
            }
""", "remove weekly assessment fallback")

sync = replace_once(sync, """        return [
            'expected_weekly_weights' => collect($expectedCents)
""", """        $coveredNonExamSubIds = collect($assessmentMeta)
            ->flatMap(fn ($meta) => $meta['subs'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()->unique()->values();
        $teachingSubIds = $teachingWeeks
            ->pluck('rps_sub_cpmk_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()->values();
        $subCodeById = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('code', 'id');
        $uncoveredNonExamSubCodes = $teachingSubIds
            ->diff($coveredNonExamSubIds)
            ->map(fn ($id) => trim((string) $subCodeById->get($id, '')))
            ->filter()->unique()->values()->all();

        return [
            'expected_weekly_weights' => collect($expectedCents)
""", "insert non-exam coverage snapshot")

sync = replace_once(sync, """            'assessment_budget_mismatches' => $assessmentBudgetMismatches,
            'ambiguous_evidence_weeks' => $ambiguousEvidenceWeeks,
""", """            'assessment_budget_mismatches' => $assessmentBudgetMismatches,
            'uncovered_non_exam_sub_codes' => $uncoveredNonExamSubCodes,
            'ambiguous_evidence_weeks' => $ambiguousEvidenceWeeks,
""", "return non-exam coverage")

sync = replace_once(sync, """        DB::transaction(function () use ($versionId, $snapshot): void {
            foreach ($snapshot['expected_weekly_weights'] as $week => $weight) {
                DB::table('rps_weekly_plans')
                    ->where('rps_version_id', $versionId)
                    ->where('week_number', (int) $week)
                    ->update([
                        'assessment_weight' => (float) $weight,
                        'updated_at' => now(),
                    ]);
            }
        });
""", """        DB::transaction(function () use ($versionId, $snapshot): void {
            $ownerNames = collect($snapshot['assessment_owner_name_by_week'] ?? []);

            foreach ($snapshot['expected_weekly_weights'] as $week => $weight) {
                $weekNumber = (int) $week;
                $updates = [
                    'assessment_weight' => (float) $weight,
                    'updated_at' => now(),
                ];

                // Bentuk penilaian pekanan tidak lagi berdiri sendiri. Ia selalu
                // mengikuti asesmen induk pada Detail Asesmen.
                if (in_array($weekNumber, self::TEACHING_WEEKS, true)) {
                    $ownerName = trim((string) $ownerNames->get($weekNumber, ''));
                    $updates['assessment_method'] = $ownerName !== '' ? $ownerName : null;
                }

                DB::table('rps_weekly_plans')
                    ->where('rps_version_id', $versionId)
                    ->where('week_number', $weekNumber)
                    ->update($updates);
            }
        });
""", "sync weekly assessment method from owner")

sync = replace_once(sync, """        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'assessment_id', 'due_week', 'title', 'source_type', 'purpose', 'instructions', 'expected_output'])
            ->values();
""", """        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'assessment_id', 'due_week', 'title', 'source_type', 'purpose', 'instructions', 'expected_output'])
            ->values();
""", "task alignment codes")

sync = replace_once(sync, """        $mismatchCount = 0;
        $invalidDueWeekCount = 0;
        $unlinkedWeightedTaskCount = 0;
""", """        $mismatchCount = 0;
        $invalidDueWeekCount = 0;
        $unlinkedWeightedTaskCount = 0;
        $mappingIssues = [];
        $invalidDueWeekIssues = [];
        $unlinkedIssues = [];
""", "task alignment issue arrays")

sync = replace_once(sync, """            if ($dueWeek < 1 || $dueWeek > 16) {
                $invalidDueWeekCount++;
            } else {
                $latestCoverageWeek = $weekRows
                    ->filter(fn ($row, $number) =>
                        in_array((int) $number, self::TEACHING_WEEKS, true)
                        && filled($row->rps_sub_cpmk_id ?? null)
                        && $actual->contains((string) $row->rps_sub_cpmk_id)
                    )
                    ->keys()->map(fn ($number) => (int) $number)->max();

                if ($latestCoverageWeek && $dueWeek < (int) $latestCoverageWeek) {
                    $invalidDueWeekCount++;
                }
            }
""", """            if ($dueWeek < 1 || $dueWeek > 16) {
                $invalidDueWeekCount++;
                $invalidDueWeekIssues[] = [
                    'id' => (string) $task->id,
                    'code' => trim((string) ($task->code ?? 'RTM')),
                    'title' => trim((string) $task->title),
                    'week' => $dueWeek,
                    'reason' => 'Pekan pengumpulan tidak valid.',
                ];
            } else {
                $latestCoverageWeek = $weekRows
                    ->filter(fn ($row, $number) =>
                        in_array((int) $number, self::TEACHING_WEEKS, true)
                        && filled($row->rps_sub_cpmk_id ?? null)
                        && $actual->contains((string) $row->rps_sub_cpmk_id)
                    )
                    ->keys()->map(fn ($number) => (int) $number)->max();

                if ($latestCoverageWeek && $dueWeek < (int) $latestCoverageWeek) {
                    $invalidDueWeekCount++;
                    $invalidDueWeekIssues[] = [
                        'id' => (string) $task->id,
                        'code' => trim((string) ($task->code ?? 'RTM')),
                        'title' => trim((string) $task->title),
                        'week' => $dueWeek,
                        'reason' => 'Pekan pengumpulan '.$dueWeek.' lebih awal dari pekan terakhir cakupan Sub-CPMK '.(int) $latestCoverageWeek.'.',
                    ];
                }
            }
""", "task due week details")

sync = replace_once(sync, """            if (! filled($task->assessment_id ?? null)) {
                if ($week && (float) ($week->assessment_weight ?? 0) > 0) {
                    $unlinkedWeightedTaskCount++;
                }
                continue;
            }
""", """            if (! filled($task->assessment_id ?? null)) {
                if ($week && (float) ($week->assessment_weight ?? 0) > 0) {
                    $unlinkedWeightedTaskCount++;
                    $unlinkedIssues[] = [
                        'id' => (string) $task->id,
                        'code' => trim((string) ($task->code ?? 'RTM')),
                        'title' => trim((string) $task->title),
                        'week' => $dueWeek,
                        'reason' => 'RTM belum terhubung ke asesmen induk.',
                    ];
                }
                continue;
            }
""", "unlinked task details")

sync = replace_once(sync, """            $assessmentId = (string) $task->assessment_id;
            if (! $validAssessmentIds->contains($assessmentId)) {
                $mismatchCount++;
                continue;
            }
""", """            $assessmentId = (string) $task->assessment_id;
            if (! $validAssessmentIds->contains($assessmentId)) {
                $mismatchCount++;
                $mappingIssues[] = [
                    'id' => (string) $task->id,
                    'code' => trim((string) ($task->code ?? 'RTM')),
                    'title' => trim((string) $task->title),
                    'week' => $dueWeek,
                    'reason' => 'Asesmen induk RTM tidak valid atau bukan asesmen yang memerlukan RTM.',
                ];
                continue;
            }
""", "invalid assessment task details")

sync = replace_once(sync, """            $outside = $actual->reject(fn ($id) => $assessmentSubIds->contains($id));
            if ($actual->isEmpty() || $assessmentSubIds->isEmpty() || $outside->isNotEmpty()) {
                $mismatchCount++;
            }
""", """            $outside = $actual->reject(fn ($id) => $assessmentSubIds->contains($id));
            if ($actual->isEmpty() || $assessmentSubIds->isEmpty() || $outside->isNotEmpty()) {
                $mismatchCount++;
                $mappingIssues[] = [
                    'id' => (string) $task->id,
                    'code' => trim((string) ($task->code ?? 'RTM')),
                    'title' => trim((string) $task->title),
                    'week' => $dueWeek,
                    'reason' => $actual->isEmpty()
                        ? 'RTM belum memiliki Sub-CPMK yang diukur.'
                        : ($assessmentSubIds->isEmpty()
                            ? 'Asesmen induk belum memiliki cakupan Sub-CPMK.'
                            : 'Cakupan Sub-CPMK RTM berada di luar cakupan asesmen induk.'),
                ];
            }
""", "mapping scope details")

sync = replace_once(sync, """        $requiredAssessmentIds = DB::table('assessments')
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
""", """        $requiredAssessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->whereRaw('COALESCE(weight, 0) > 0')
            ->get(['id', 'code', 'name']);
        $requiredAssessmentIds = $requiredAssessments
            ->pluck('id')->map(fn ($id) => (string) $id)->unique()->values();

        $coveredAssessmentIds = $linkedTasks->pluck('assessment_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $validAssessmentIds->contains($id))
            ->unique()
            ->values();
        $missingRequired = $requiredAssessments
            ->reject(fn ($assessment) => $coveredAssessmentIds->contains((string) $assessment->id))
            ->values();
        $problemTaskIds = collect(array_merge($mappingIssues, $invalidDueWeekIssues, $unlinkedIssues))
            ->pluck('id')->filter()->unique()->values()->all();

        return [
            'task_total' => $tasks->count(),
            'linked_task_total' => $linkedTasks->count(),
            'required_assessment_total' => $requiredAssessmentIds->count(),
            'missing_required_assessment_count' => $missingRequired->count(),
            'missing_required_assessments' => $missingRequired->map(fn ($assessment) => [
                'id' => (string) $assessment->id,
                'code' => trim((string) $assessment->code),
                'name' => trim((string) $assessment->name),
            ])->all(),
            'mapping_mismatch_count' => $mismatchCount,
            'mapping_mismatches' => $mappingIssues,
            'unlinked_weighted_task_count' => $unlinkedWeightedTaskCount,
            'unlinked_tasks' => $unlinkedIssues,
            'due_week_subcpmk_mismatch_count' => $invalidDueWeekCount,
            'invalid_due_weeks' => $invalidDueWeekIssues,
            'problem_task_ids' => $problemTaskIds,
            'is_aligned' => $missingRequired->isEmpty()
                && $mismatchCount === 0
                && $unlinkedWeightedTaskCount === 0
                && $invalidDueWeekCount === 0,
        ];
""", "task alignment detailed return")

sync_path.write_text(sync)

# 2) Validator identifies exact weeks/Sub-CPMK/RTM.
obe_path = Path("app/Services/Rps/ObeWorkspaceService.php")
obe = obe_path.read_text()

obe = replace_once(obe, """        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')->filter()->unique()->count();
        $weeklySubBudgets = $teachingWeeks
""", """        $weightedWeeklySubIds = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        $weightedWeeklySubCount = $weightedWeeklySubIds->count();
        $unmeasuredSubCodes = $subCpmks
            ->reject(fn ($sub) => $weightedWeeklySubIds->contains((string) $sub->id))
            ->pluck('code')->map(fn ($code) => trim((string) $code))->filter()->values();
        $uncoveredNonExamSubCodes = collect($assessmentSnapshot['uncovered_non_exam_sub_codes'] ?? [])
            ->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
        $weeklySubBudgets = $teachingWeeks
""", "validator weighted sub ids")

obe = replace_once(obe, """        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK terpetakan · CPL belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK · {$mappedScopeCplCount}/{$scopeCplCount} CPL terpetakan.";

        $checks = [
""", """        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK terpetakan · CPL belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK · {$mappedScopeCplCount}/{$scopeCplCount} CPL terpetakan.";

        $rtmProblemTasks = collect(array_merge(
            $taskAlignment['mapping_mismatches'] ?? [],
            $taskAlignment['invalid_due_weeks'] ?? [],
            $taskAlignment['unlinked_tasks'] ?? []
        ))->unique('id')->values();
        $rtmProblemLabels = $rtmProblemTasks
            ->map(fn ($item) => trim((string) ($item['code'] ?? 'RTM')))
            ->filter()->unique()->values();
        $missingRtmAssessments = collect($taskAlignment['missing_required_assessments'] ?? [])
            ->map(fn ($item) => trim((string) ($item['code'] ?? '')).' '.trim((string) ($item['name'] ?? '')))
            ->map(fn ($label) => trim($label))->filter()->values();

        $checks = [
""", "validator rtm labels")

obe = replace_once(obe, """                'message' => "{$weightedTeachingWeeks->count()}/14 pekan berbobot · Total {$weightTotal}%.",
                'details' => [
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
""", """                'message' => abs($assessmentWeightTotal - 100.0) < 0.01
                    ? ($weightedTeachingWeeks->count() === 14
                        ? 'Total bobot asesmen 100% · 14/14 pekan menerima distribusi bobot.'
                        : 'Total bobot asesmen 100% · Pekan '.$unweightedTeachingWeekNumbers->implode(', ').' belum menerima distribusi bobot dari Detail Asesmen.'
                            .($uncoveredNonExamSubCodes->isNotEmpty()
                                ? ' Sub-CPMK tanpa asesmen non-UTS/UAS: '.$uncoveredNonExamSubCodes->implode(', ').'.'
                                : ''))
                    : 'Total bobot asesmen '.$assessmentWeightTotal.'% · target harus 100%.',
                'details' => [
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'unweighted_weeks' => $unweightedTeachingWeekNumbers->all(),
                    'uncovered_non_exam_sub_codes' => $uncoveredNonExamSubCodes->all(),
""", "assessment weight message")

obe = replace_once(obe, """                'message' => "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur · {$weightedTeachingWeeks->count()}/14 pekan.",
                'details' => [
                    'sub_cpmk_total' => $subCpmks->count(),
                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,
""", """                'message' => $weightedWeeklySubCount === $subCpmks->count()
                    ? "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur pada pekan berbobot."
                    : "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur. Belum terukur melalui asesmen non-UTS/UAS: ".$unmeasuredSubCodes->implode(', ').'.',
                'details' => [
                    'sub_cpmk_total' => $subCpmks->count(),
                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,
                    'unmeasured_sub_codes' => $unmeasuredSubCodes->all(),
""", "subcpmk measurement message")

obe = replace_once(obe, """                        : ($weightedTeachingWeeks->count() < 14
                            ? $unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
""", """                        : ($weightedTeachingWeeks->count() < 14
                            ? 'Pekan '.$unweightedTeachingWeekNumbers->implode(', ').' belum menerima distribusi bobot dari Detail Asesmen.'
                                .($uncoveredNonExamSubCodes->isNotEmpty()
                                    ? ' Lengkapi asesmen non-UTS/UAS untuk '.$uncoveredNonExamSubCodes->implode(', ').'.'
                                    : '')
""", "consistency exact weeks")

obe = replace_once(obe, """                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'sub_budget_aligned' => $subBudgetAligned,
""", """                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'unweighted_weeks' => $unweightedTeachingWeekNumbers->all(),
                    'uncovered_non_exam_sub_codes' => $uncoveredNonExamSubCodes->all(),
                    'sub_budget_aligned' => $subBudgetAligned,
""", "consistency details")

obe = replace_once(obe, """                    : ($weightedTeachingWeeks->count() < 14
                        ? 'Belum dapat diperiksa: '.$unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
""", """                    : ($weightedTeachingWeeks->count() < 14
                        ? 'Belum dapat diperiksa: Pekan '.$unweightedTeachingWeekNumbers->implode(', ').' belum menerima bobot dari asesmen induk.'
""", "weekly evidence exact weeks")

obe = replace_once(obe, """                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? "{$tasks} RTM · Semua sinkron."
                        : "{$tasks} RTM · {$taskAlignment['missing_required_assessment_count']} belum ada · {$taskAlignment['mapping_mismatch_count']} tidak sinkron."),
                'details' => $taskAlignment,
""", """                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? "{$tasks} RTM tersedia · Semua sinkron dengan asesmen induk."
                        : "{$tasks} RTM tersedia"
                            .($missingRtmAssessments->isNotEmpty()
                                ? ' · asesmen belum memiliki RTM: '.$missingRtmAssessments->implode(', ')
                                : ' · semua asesmen wajib sudah memiliki RTM')
                            .($rtmProblemLabels->isNotEmpty()
                                ? ' · perlu sinkronisasi: '.$rtmProblemLabels->implode(', ').'.'
                                : '.')),
                'details' => [
                    ...$taskAlignment,
                    'problem_tasks' => $rtmProblemTasks->all(),
                ],
""", "rtm exact problem labels")

obe_path.write_text(obe)

# 3) Weekly AI cannot invent assessment form; assessment AI must cover all Sub-CPMK.
ai_path = Path("app/Http/Controllers/RpsAiController.php")
ai = ai_path.read_text()
ai = ai.replace("rtm-integrative-v4-safe-merge", "rtm-integrative-v5-assessment-source")

ai = replace_once(ai, """- Pastikan seluruh Sub-CPMK aktif memiliki bukti asesmen dan RTM yang relevan; gunakan keterkaitan Sub-CPMK sebagai dasar utama constructive alignment.

ATURAN CAKUPAN RTM:
""", """- Pastikan seluruh Sub-CPMK aktif memiliki bukti asesmen dan RTM yang relevan; gunakan keterkaitan Sub-CPMK sebagai dasar utama constructive alignment.
- SETIAP Sub-CPMK aktif WAJIB tercakup minimal satu asesmen NON-UTS/UAS dengan bobot positif. UTS/UAS boleh mengukur Sub-CPMK yang sama sebagai asesmen sumatif, tetapi UTS/UAS tidak boleh menjadi satu-satunya asesmen untuk suatu Sub-CPMK.
- Detail Asesmen adalah sumber kebenaran bentuk dan bobot penilaian pekanan. Jangan membuat bentuk penilaian pekanan yang berdiri sendiri di luar asesmen agregat.

ATURAN CAKUPAN RTM:
""", "assessment AI full non-exam coverage prompt")

ai = replace_once(ai, """        $payload['tasks'] = $tasks;
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""", """        $payload['tasks'] = $tasks;
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""", "sanitize coverage guard")

helper = r"""
    private function assertNonExamAssessmentCoverage(array $payload, object $version): array
    {
        $activeCodes = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->pluck('code')
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();

        if ($activeCodes->isEmpty()) return $payload;

        $coveredCodes = collect($payload['assessments'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->reject(fn ($item) => in_array(strtolower(trim((string) ($item['type'] ?? 'other'))), ['uts', 'uas'], true))
            ->filter(fn ($item) => (float) ($item['weight'] ?? 0) > 0)
            ->flatMap(fn ($item) => $item['sub_cpmk_codes'] ?? [])
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();

        $missing = $activeCodes->diff($coveredCodes)->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'ai' => 'Telaah Asesmen + RTM AI belum memenuhi constructive alignment. Sub-CPMK berikut belum memiliki asesmen non-UTS/UAS berbobot: '
                    .$missing->implode(', ').'. Jalankan Telaah Asesmen + RTM AI kembali; rekomendasi yang tidak menutup seluruh Sub-CPMK tidak akan diterapkan.',
            ]);
        }

        return $payload;
    }

"""
ai = replace_once(ai, """    private function annotateAssessmentMergeActions(array $payload, object $version): array
""", helper + """    private function annotateAssessmentMergeActions(array $payload, object $version): array
""", "insert assessment coverage helper")

ai = replace_once(ai, """        // Re-evaluate merge actions at APPLY time against the latest RPS state.
        // This also repairs older pending suggestions that were previously
        // classified as ADD because name/type differed from manual data.
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""", """        // Re-evaluate coverage and merge actions at APPLY time against the latest RPS state.
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""", "apply-time coverage guard")

ai = replace_once(ai, """    public function generateWeek(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
""", """    public function generateWeek(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
""", "generateWeek inject sync")

ai = replace_once(ai, """                $week,
                $aiProvider,
                $contextService
            );
""", """                $week,
                $aiProvider,
                $contextService,
                $assessmentSync
            );
""", "generateWeek pass sync")

ai = replace_once(ai, """    private function generateWeekInternal(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
""", """    private function generateWeekInternal(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
""", "generateWeekInternal inject sync")

ai = replace_once(ai, """        $context = $contextService->buildWeekContext(
            $record,
            $version,
            $week,
            $targetSub->code
        );

        $indicatorInstruction = <<<'PROMPT'
""", """        $context = $contextService->buildWeekContext(
            $record,
            $version,
            $week,
            $targetSub->code
        );
        $assessmentSnapshot = $assessmentSync->snapshot($version->id);
        $assessmentOwnerName = trim((string) ($assessmentSnapshot['assessment_owner_name_by_week'][$week] ?? ''));

        $indicatorInstruction = <<<'PROMPT'
""", "weekly owner snapshot")

ai = replace_once(ai, """Pastikan `assessment_criteria` menilai kualitas bukti tersebut dan `assessment_method` konsisten dengan asesmen yang tersedia.
""", """Pastikan `assessment_criteria` menilai kualitas bukti tersebut. `assessment_method` TIDAK boleh menciptakan bentuk penilaian baru: bentuk resmi selalu berasal dari Detail Asesmen. Jika pekan belum mempunyai asesmen induk pada `target_assessments`, kosongkan `assessment_method`; sistem akan meminta dosen melengkapi Detail Asesmen.
""", "weekly AI assessment method prompt")

ai = replace_once(ai, """            'assessment_indicator' => $item['assessment_indicator'] ?? null,
            'assessment_criteria' => $item['assessment_criteria'] ?? null,
            'assessment_method' => $item['assessment_method'] ?? null,
            'reference_text' => $resolvedReferences,
""", """            'assessment_indicator' => $item['assessment_indicator'] ?? null,
            'assessment_criteria' => $item['assessment_criteria'] ?? null,
            'assessment_method' => $assessmentOwnerName !== '' ? $assessmentOwnerName : null,
            'reference_text' => $resolvedReferences,
""", "weekly candidate assessment method")

ai = replace_once(ai, """        if ($updates === []) {
            return back()->with(
""", """        $currentAssessmentMethod = trim((string) ($weekly->assessment_method ?? ''));
        if ($currentAssessmentMethod !== $assessmentOwnerName) {
            $updates['assessment_method'] = $assessmentOwnerName !== '' ? $assessmentOwnerName : null;
        }

        if ($updates === []) {
            return back()->with(
""", "force weekly source of truth")

ai = replace_once(ai, """        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($updates);

        // Store as accepted audit history
""", """        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($updates);

        $assessmentSync->syncVersion($version->id);

        // Store as accepted audit history
""", "sync after weekly AI")

ai = replace_once(ai, """        return back()->with(
            'success',
            'AI berhasil '.($overwrite ? 'menyusun ulang' : 'melengkapi')
                .' pekan '.$week.' menggunakan '
                .strtoupper((string) ($result['provider'] ?? 'AI')).'.'
        );
""", """        $assessmentNote = $assessmentOwnerName !== ''
            ? ' Bentuk dan bobot penilaian mengikuti Detail Asesmen “'.$assessmentOwnerName.'”.'
            : ' Pekan ini belum memiliki asesmen induk; bentuk dan bobot penilaian tidak dibuat oleh AI pekanan. Lengkapi Detail Asesmen untuk menyinkronkannya.';

        return back()->with(
            'success',
            'AI berhasil '.($overwrite ? 'menyusun ulang' : 'melengkapi').' pekan '.$week.'.'.$assessmentNote
        );
""", "weekly AI source guidance")

ai_path.write_text(ai)

# 4) Manual assessment guardrails and guidance.
assessment_path = Path("app/Http/Controllers/RpsAssessmentController.php")
assessment = assessment_path.read_text()
assessment = replace_once(assessment, """        $sync->syncVersion($version->id);

        return back()->with('success', 'Asesmen berhasil ditambahkan; tag Sub-CPMK, bobot pekan, RTM, matriks, dan simulasi tersinkron.');
""", """        $syncResult = $sync->syncVersion($version->id);
        $guidance = $this->assessmentGuidance($version->id, $id, $syncResult);

        return back()->with('success', 'Asesmen berhasil ditambahkan; tag Sub-CPMK, bobot pekan, RTM, matriks, dan simulasi tersinkron.'.$guidance);
""", "assessment store guidance")
assessment = replace_once(assessment, """        $sync->syncVersion($version->id);

        return back()->with(
            'success',
            in_array($row->code, ['UTS', 'UAS'], true)
                ? "{$row->code} berhasil disimpan dan seluruh tabel bobot tersinkron."
                : 'Asesmen diperbarui dan seluruh tabel bobot tersinkron.'
        );
""", """        $syncResult = $sync->syncVersion($version->id);
        $guidance = $this->assessmentGuidance($version->id, $assessment, $syncResult);

        return back()->with(
            'success',
            (in_array($row->code, ['UTS', 'UAS'], true)
                ? "{$row->code} berhasil disimpan dan seluruh tabel bobot tersinkron."
                : 'Asesmen diperbarui dan seluruh tabel bobot tersinkron.').$guidance
        );
""", "assessment update guidance")
assessment = replace_once(assessment, """        $sync->syncVersion($version->id);

        return back()->with(
            'success',
            'Asesmen diperbarui; Detail Asesmen, tabel RPS, RTM, Tabel Penilaian, dan Simulasi langsung tersinkron.'
        );
""", """        $syncResult = $sync->syncVersion($version->id);
        $guidance = $this->assessmentGuidance($version->id, $assessment, $syncResult);

        return back()->with(
            'success',
            'Asesmen diperbarui; Detail Asesmen, tabel RPS, RTM, Tabel Penilaian, dan Simulasi langsung tersinkron.'.$guidance
        );
""", "assessment matrix guidance")

guidance_helper = r"""
    private function assessmentGuidance(string $versionId, string $assessmentId, array $syncResult): string
    {
        $assessment = DB::table('assessments')->where('rps_version_id', $versionId)->where('id', $assessmentId)
            ->first(['id', 'code', 'name', 'type', 'week_number', 'weight']);
        if (! $assessment) return '';

        $notes = collect();
        $type = strtolower((string) ($assessment->type ?? 'other'));
        $isExam = in_array($type, ['uts', 'uas'], true);
        if (! $isExam) {
            $linkedSubIds = DB::table('assessment_subcpmks')->where('assessment_id', $assessmentId)
                ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique()->values();
            $coverageWeeks = $linkedSubIds->isEmpty() ? collect() : DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->whereIn('week_number', [1,2,3,4,5,6,7,9,10,11,12,13,14,15])
                ->whereIn('rps_sub_cpmk_id', $linkedSubIds->all())->orderBy('week_number')
                ->pluck('week_number')->map(fn ($week) => (int) $week)->unique()->values();
            if ($linkedSubIds->isNotEmpty() && $coverageWeeks->isEmpty()) {
                $notes->push('Sub-CPMK yang dipilih belum memiliki alokasi pertemuan sehingga bobot belum dapat didistribusikan.');
            }
            $latestCoverage = $coverageWeeks->isNotEmpty() ? (int) $coverageWeeks->max() : 0;
            $assessmentWeek = (int) ($assessment->week_number ?? 0);
            if ($assessmentWeek > 0 && $latestCoverage > 0 && $assessmentWeek < $latestCoverage) {
                $notes->push('Pekan asesmen '.$assessmentWeek.' lebih awal daripada pekan terakhir cakupan Sub-CPMK '.$latestCoverage.'; pertimbangkan Pekan '.$latestCoverage.' atau setelahnya.');
            }
            $budgetMismatch = collect($syncResult['assessment_budget_mismatches'] ?? [])
                ->first(fn ($item) => (string) ($item['assessment_id'] ?? '') === $assessmentId);
            if ($budgetMismatch) {
                $notes->push('Bobot “'.trim((string) $assessment->name).'” belum seluruhnya dapat didistribusikan ('.(float) ($budgetMismatch['allocated'] ?? 0).'% dari '.(float) ($budgetMismatch['budget'] ?? 0).'%).');
            }
        }
        $uncovered = collect($syncResult['uncovered_non_exam_sub_codes'] ?? [])->map(fn ($code) => trim((string) $code))->filter()->unique()->values();
        if ($uncovered->isNotEmpty()) {
            $notes->push('Masih ada Sub-CPMK tanpa asesmen non-UTS/UAS berbobot: '.$uncovered->implode(', ').'. Tambahkan atau perluas asesmen agar seluruh pekan dapat memperoleh bukti penilaian.');
        }
        return $notes->isEmpty()
            ? ' Status sinkron: asesmen dapat didistribusikan ke pekan terkait.'
            : ' Perlu diperiksa: '.$notes->unique()->implode(' ');
    }

"""
assessment = replace_once(assessment, """    private function context(Request $request, string $rps): array
""", guidance_helper + """    private function context(Request $request, string $rps): array
""", "insert manual assessment guidance helper")
assessment_path.write_text(assessment)

# 5) Read-side legacy guard.
rps_path = Path("app/Http/Controllers/RpsController.php")
rps = rps_path.read_text()
rps = replace_once(rps, """            $assessmentTotalBudget = (float) $assessmentTotalBudgetByWeek->get($weekNumber, 0);
            $isTeachingWeek = ! in_array($weekNumber, [8, 16], true);

            $week->assessment_owner_id = $ownerId ?: null;
""", """            $assessmentTotalBudget = (float) $assessmentTotalBudgetByWeek->get($weekNumber, 0);
            $isTeachingWeek = ! in_array($weekNumber, [8, 16], true);

            if ($isTeachingWeek) {
                $week->assessment_method = $ownerName !== '' ? $ownerName : null;
            }

            $week->assessment_owner_id = $ownerId ?: null;
""", "read-side assessment source")
rps_path.write_text(rps)

# 6) UI source-of-truth and exact validator targets.
show_path = Path("resources/js/pages/rps/show.tsx")
show = show_path.read_text()
show = replace_once(show, """    const ambiguous = safeList(details.ambiguous_weeks);
    const missing = safeList(details.missing_weeks);
    const candidate = ambiguous[0] ?? missing[0];
""", """    const ambiguous = safeList(details.ambiguous_weeks);
    const missing = safeList(details.missing_weeks);
    const unweighted = safeList(details.unweighted_weeks);
    const candidate = ambiguous[0] ?? missing[0] ?? unweighted[0];
""", "validator problem week unweighted")
show = replace_once(show, """    if (check?.key === 'rtm_semantics') {
        const issue = safeList(check?.details?.issues)[0];
        if (issue?.task_code) return `Edit ${issue.task_code}`;
    }

    const week = validatorProblemWeek(check);
""", """    if (check?.key === 'rtm_semantics') {
        const issue = safeList(check?.details?.issues)[0];
        if (issue?.task_code) return `Edit ${issue.task_code}`;
    }
    if (check?.key === 'rtm') {
        const issue = safeList(check?.details?.problem_tasks)[0];
        if (issue?.code) return `Edit ${issue.code}`;
    }

    const week = validatorProblemWeek(check);
""", "validator rtm label")
show = replace_once(show, """    const semanticIssue = safeList(check?.details?.issues)[0];
    if (check?.key === 'rtm_semantics' && semanticIssue?.task_id) {
        targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-rtm-id="${semanticIssue.task_id}"]`));
    } else if (check?.key === 'assessment_semantics' && semanticIssue?.assessment_id) {
""", """    const semanticIssue = safeList(check?.details?.issues)[0];
    const rtmProblem = safeList(check?.details?.problem_tasks)[0];
    if (check?.key === 'rtm' && rtmProblem?.id) {
        targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-rtm-id="${rtmProblem.id}"]`));
    } else if (check?.key === 'rtm_semantics' && semanticIssue?.task_id) {
        targets = Array.from(document.querySelectorAll<HTMLElement>(`[data-rtm-id="${semanticIssue.task_id}"]`));
    } else if (check?.key === 'assessment_semantics' && semanticIssue?.assessment_id) {
""", "validator direct RTM target")
show = replace_once(show, """                                    Asesmen menyimpan bobot agregat/anggaran penilaian (total 100%). Bobot non-UTS/UAS kemudian didistribusikan ke 14 pekan pada tabel RPS. Bobot pekan boleh dikoreksi langsung dari tabel RPS setelah asesmen/tag Sub-CPMK tersedia; perubahan hanya mengatur distribusi pekan, tidak mengubah bobot agregat, dan RTM serta Validator OBE ikut tersinkron.
""", """                                    <strong>Detail Asesmen adalah sumber utama sistem penilaian RPS.</strong> Setiap asesmen wajib terkait minimal satu Sub-CPMK. Nama/bentuk asesmen dan bobot agregat (total 100%) digunakan untuk menyusun distribusi bobot pekanan, RTM, matriks evaluasi, dan simulasi. Indikator serta kriteria boleh spesifik per pekan, tetapi Bentuk Penilaian dan bobot pekan selalu mengikuti asesmen induk. Jika input manual belum dapat disinkronkan, SiMatRPS akan memberi arahan tanpa mengubah keputusan dosen secara diam-diam.
""", "assessment source explanation")
show = show.replace("""<input value={form.data.assessment_method} onChange={(e) => form.setData('assessment_method', e.target.value)} className={`${input} mt-1`} placeholder="Bentuk / teknik" />""", """<input value={form.data.assessment_method} readOnly className={`${input} mt-1 bg-slate-50 text-slate-500`} placeholder="Mengikuti Detail Asesmen" title="Bentuk penilaian mengikuti Detail Asesmen dan tidak diedit dari pekan." />""")
show = show.replace("""<input value={form.data.assessment_method} onChange={(e) => form.setData('assessment_method', e.target.value)} className={`${inputClass} mt-2`} placeholder="Bentuk / teknik" />""", """<input value={form.data.assessment_method} readOnly className={`${inputClass} mt-2 bg-slate-50 text-slate-500`} placeholder="Mengikuti Detail Asesmen" title="Bentuk penilaian mengikuti Detail Asesmen dan tidak diedit dari pekan." />""")
show = replace_once(show, """                        <input
                            value={form.data.assessment_method}
                            onChange={(e) => form.setData('assessment_method', e.target.value)}
                            placeholder="Non-test (tanya jawab), tugas individu, kuis..."
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
""", """                        <input
                            value={form.data.assessment_method}
                            readOnly
                            placeholder="Mengikuti Detail Asesmen"
                            title="Bentuk penilaian mengikuti Detail Asesmen dan tidak diedit dari pekan."
                            className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-500"
                        />
                        <span className="mt-1 block text-[10px] leading-4 text-slate-400">Bentuk Penilaian berasal dari Detail Asesmen. Edit asesmen induk bila perlu mengubahnya.</span>
""", "week editor readonly assessment method")
show = show.replace("""<div className="mt-2"><strong>Bentuk:</strong> {week.assessment_method || '-'}</div>""", """<div className="mt-2"><strong>Bentuk:</strong> {week.assessment_method || 'Belum terhubung ke Detail Asesmen'}</div>""")
show_path.write_text(show)

# 7) Clearing weekly content immediately re-asserts assessment source.
automation_path = Path("app/Http/Controllers/RpsAutomationController.php")
automation = automation_path.read_text()
automation = replace_once(automation, """    public function clearWeeklyContent(
        Request $request,
        string $rps
    ): RedirectResponse {
""", """    public function clearWeeklyContent(
        Request $request,
        string $rps,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
""", "clear weekly inject sync")
automation = replace_once(automation, """                    'assessment_method' => null,
                    'learning_form' => null,
""", """                    // assessment_method tidak dihapus: ia mengikuti Detail Asesmen.
                    'learning_form' => null,
""", "keep assessment source during clear")
automation = replace_once(automation, """        return back()->with(
            'success',
            "Isi {$updated} pekan pembelajaran dikosongkan, termasuk Tatap Muka/Luring, Belajar Mandiri, Tugas Mandiri/Terstruktur, dan Daring/LMS. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan."
        );
""", """        $assessmentSync->syncVersion($version->id);

        return back()->with(
            'success',
            "Isi {$updated} pekan pembelajaran dikosongkan, termasuk Tatap Muka/Luring, Belajar Mandiri, Tugas Mandiri/Terstruktur, dan Daring/LMS. Alokasi Sub-CPMK, Detail Asesmen, bentuk/bobot penilaian, UTS/UAS, dan RTM tetap dipertahankan serta disinkronkan."
        );
""", "sync after clear")
automation_path.write_text(automation)
