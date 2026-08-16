from pathlib import Path
import re

root = Path('.')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected 1 marker, got {count}')
    return text.replace(old, new, 1)

# -----------------------------------------------------------------------------
# RpsAssessmentSyncService: one primary assessment evidence per teaching week.
# -----------------------------------------------------------------------------
p = root / 'app/Services/Rps/RpsAssessmentSyncService.php'
s = p.read_text(encoding='utf-8')

s = replace_once(
    s,
    "->get(['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight']);",
    "->get(['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight', 'assessment_method']);",
    'weekly assessment method select',
)

old_spread_names = """                    $expectedCents[$weekNumber] += $baseWeek + ($weekIndex < $weekRemainder ? 1 : 0);
                    if ($subShare > 0) {
                        $namesByWeek[$weekNumber][] = (string) $assessment->name;
                    }
"""
new_spread_names = """                    $expectedCents[$weekNumber] += $baseWeek + ($weekIndex < $weekRemainder ? 1 : 0);
"""
s = replace_once(s, old_spread_names, new_spread_names, 'remove aggregate names from every sub week')

pattern = re.compile(
    r"        // Simulasi menampilkan bukti/penugasan yang benar-benar jatuh\n"
    r".*?\n"
    r"        \$actualSubBudgets = \$weeks",
    flags=re.S,
)
replacement = r'''        // Simulasi harus menampilkan SATU bukti penilaian utama yang benar-benar
        // relevan pada setiap pekan, bukan seluruh asesmen agregat yang men-tag
        // Sub-CPMK yang sama. Prioritas:
        // 1) RTM yang jatuh tepat pada pekan dan sesuai Sub-CPMK pekan;
        // 2) asesmen agregat yang week_number-nya tepat pada pekan dan men-tag
        //    Sub-CPMK pekan;
        // 3) bentuk penilaian pada tabel RPS sebagai fallback formatif.
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('due_week')
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'due_week', 'title', 'assessment_id']);

        $taskIds = $tasks->pluck('id')->all();
        $taskLinks = $taskIds === []
            ? collect()
            : DB::table('rps_task_subcpmks')
                ->whereIn('rps_task_id', $taskIds)
                ->get(['rps_task_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_task_id');

        $tasksByWeek = $tasks->groupBy(fn ($task) => (int) $task->due_week);
        $evidenceSourceByWeek = array_fill_keys(range(1, 16), null);
        $ambiguousEvidenceWeeks = [];
        $missingEvidenceWeeks = [];

        foreach ($weeks as $week) {
            $weekNumber = (int) $week->week_number;

            if (in_array($weekNumber, [8, 16], true)) {
                $evidenceSourceByWeek[$weekNumber] = 'system_exam';
                continue;
            }

            if (! in_array($weekNumber, self::TEACHING_WEEKS, true)) {
                continue;
            }

            $subId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;

            $taskCandidates = collect($tasksByWeek->get($weekNumber, []))
                ->filter(function ($task) use ($taskLinks, $subId): bool {
                    if (! $subId) {
                        return false;
                    }

                    return collect($taskLinks->get($task->id, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->contains($subId);
                })
                ->values();

            if ($taskCandidates->isNotEmpty()) {
                $chosen = $taskCandidates->first();
                $namesByWeek[$weekNumber] = [trim((string) $chosen->title)];
                $evidenceSourceByWeek[$weekNumber] = 'rtm';

                if ($taskCandidates->count() > 1) {
                    $ambiguousEvidenceWeeks[] = [
                        'week' => $weekNumber,
                        'source' => 'rtm',
                        'candidates' => $taskCandidates
                            ->pluck('title')
                            ->map(fn ($title) => trim((string) $title))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                }

                continue;
            }

            $assessmentCandidates = $assessments
                ->filter(function ($assessment) use ($weekNumber, $subId, $links): bool {
                    if (! $subId || (int) ($assessment->week_number ?? 0) !== $weekNumber) {
                        return false;
                    }

                    $type = strtolower((string) ($assessment->type ?? ''));
                    if (in_array($type, ['uts', 'uas'], true) || (float) ($assessment->weight ?? 0) <= 0) {
                        return false;
                    }

                    return collect($links->get($assessment->id, []))
                        ->pluck('rps_sub_cpmk_id')
                        ->map('strval')
                        ->contains($subId);
                })
                ->values();

            if ($assessmentCandidates->isNotEmpty()) {
                $chosen = $assessmentCandidates->first();
                $namesByWeek[$weekNumber] = [trim((string) $chosen->name)];
                $evidenceSourceByWeek[$weekNumber] = 'assessment_week';

                if ($assessmentCandidates->count() > 1) {
                    $ambiguousEvidenceWeeks[] = [
                        'week' => $weekNumber,
                        'source' => 'assessment_week',
                        'candidates' => $assessmentCandidates
                            ->pluck('name')
                            ->map(fn ($name) => trim((string) $name))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                }

                continue;
            }

            $fallback = trim((string) ($week->assessment_method ?? ''));
            if ($fallback !== '') {
                $namesByWeek[$weekNumber] = [$fallback];
                $evidenceSourceByWeek[$weekNumber] = 'weekly_method';
                continue;
            }

            if ((float) ($week->assessment_weight ?? 0) > 0) {
                $missingEvidenceWeeks[] = $weekNumber;
            }
        }

        $actualSubBudgets = $weeks'''
s, count = pattern.subn(replacement, s, count=1)
if count != 1:
    raise SystemExit(f'primary evidence block replacement count {count}')

old_return = """            'assessment_names_by_week' => collect($namesByWeek)
                ->map(fn ($names) => collect($names)->filter()->unique()->values()->implode('; '))
                ->all(),
            'aggregate_sub_budgets' => collect($aggregateSubCents)
"""
new_return = """            'assessment_names_by_week' => collect($namesByWeek)
                ->map(fn ($names) => collect($names)->filter()->unique()->values()->first() ?: '')
                ->all(),
            'assessment_evidence_source_by_week' => $evidenceSourceByWeek,
            'ambiguous_evidence_weeks' => $ambiguousEvidenceWeeks,
            'missing_evidence_weeks' => array_values(array_unique($missingEvidenceWeeks)),
            'aggregate_sub_budgets' => collect($aggregateSubCents)
"""
s = replace_once(s, old_return, new_return, 'snapshot evidence metadata return')

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# ObeWorkspaceService: validator for one clear evidence item per weighted week.
# -----------------------------------------------------------------------------
p = root / 'app/Services/Rps/ObeWorkspaceService.php'
s = p.read_text(encoding='utf-8')

old_alignment_prelude = """        $assessmentSync = app(RpsAssessmentSyncService::class);
        $taskAlignment = $assessmentSync->taskAlignment($versionId);
        $tasks = (int) $taskAlignment['task_total'];

        $positiveNonExamAssessments = $nonExamAssessments->filter(
"""
new_alignment_prelude = """        $assessmentSync = app(RpsAssessmentSyncService::class);
        $taskAlignment = $assessmentSync->taskAlignment($versionId);
        $assessmentSnapshot = $assessmentSync->snapshot($versionId);
        $tasks = (int) $taskAlignment['task_total'];

        $evidenceNamesByWeek = collect($assessmentSnapshot['assessment_names_by_week'] ?? []);
        $evidenceSourcesByWeek = collect($assessmentSnapshot['assessment_evidence_source_by_week'] ?? []);
        $ambiguousEvidenceWeeks = collect($assessmentSnapshot['ambiguous_evidence_weeks'] ?? []);
        $weightedTeachingWeekNumbers = $weightedTeachingWeeks
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values();
        $coveredEvidenceWeeks = $weightedTeachingWeekNumbers
            ->filter(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $ambiguousWeightedWeeks = $ambiguousEvidenceWeeks
            ->filter(fn ($item) => $weightedTeachingWeekNumbers->contains((int) ($item['week'] ?? 0)))
            ->values();
        $missingEvidenceWeeks = $weightedTeachingWeekNumbers
            ->reject(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $weeklyEvidenceAligned = $weightedTeachingWeeks->count() === 14
            && $coveredEvidenceWeeks->count() === 14
            && $ambiguousWeightedWeeks->isEmpty();

        $positiveNonExamAssessments = $nonExamAssessments->filter(
"""
s = replace_once(s, old_alignment_prelude, new_alignment_prelude, 'validator evidence prelude')

old_chain = """        $assessmentChainAligned = $allPositiveNonExamMapped
            && $weightedTeachingWeeks->count() === 14
            && $weightedWeeklySubCount === $subCpmks->count()
            && $subBudgetAligned
            && (bool) $taskAlignment['is_aligned'];
"""
new_chain = """        $assessmentChainAligned = $allPositiveNonExamMapped
            && $weightedTeachingWeeks->count() === 14
            && $weightedWeeklySubCount === $subCpmks->count()
            && $subBudgetAligned
            && $weeklyEvidenceAligned
            && (bool) $taskAlignment['is_aligned'];
"""
s = replace_once(s, old_chain, new_chain, 'assessment chain include weekly evidence')

rtm_marker = """            [
                'key' => 'rtm',
                'label' => 'RTM',
"""
evidence_check = """            [
                'key' => 'weekly_assessment_evidence',
                'label' => 'Bukti Penilaian per Pekan',
                'done' => $weeklyEvidenceAligned,
                'message' => "{$coveredEvidenceWeeks->count()}/14 pekan berbobot memiliki satu bukti penilaian utama; "
                    .$ambiguousWeightedWeeks->count()." pekan ambigu; "
                    .$missingEvidenceWeeks->count()." pekan belum memiliki bukti penilaian yang jelas.",
                'details' => [
                    'covered_weeks' => $coveredEvidenceWeeks->all(),
                    'missing_weeks' => $missingEvidenceWeeks->all(),
                    'ambiguous_weeks' => $ambiguousWeightedWeeks->all(),
                    'source_by_week' => $evidenceSourcesByWeek->all(),
                ],
            ],
            [
                'key' => 'rtm',
                'label' => 'RTM',
"""
s = replace_once(s, rtm_marker, evidence_check, 'insert weekly evidence validator')

old_chain_message = """                    ."RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']}; RTM berbobot tanpa asesmen {$taskAlignment['unlinked_weighted_task_count']}; ketidaksesuaian Sub-CPMK RTM dengan pekan {$taskAlignment['due_week_subcpmk_mismatch_count']}; asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.",
"""
new_chain_message = """                    ."bukti penilaian utama {$coveredEvidenceWeeks->count()}/14 pekan, ambigu {$ambiguousWeightedWeeks->count()}; "
                    ."RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']}; RTM berbobot tanpa asesmen {$taskAlignment['unlinked_weighted_task_count']}; ketidaksesuaian Sub-CPMK RTM dengan pekan {$taskAlignment['due_week_subcpmk_mismatch_count']}; asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.",
"""
s = replace_once(s, old_chain_message, new_chain_message, 'assessment chain evidence message')

old_chain_details = """                    'sub_budget_aligned' => $subBudgetAligned,
                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
"""
new_chain_details = """                    'sub_budget_aligned' => $subBudgetAligned,
                    'weekly_evidence_aligned' => $weeklyEvidenceAligned,
                    'weekly_evidence_covered' => $coveredEvidenceWeeks->count(),
                    'weekly_evidence_ambiguous' => $ambiguousWeightedWeeks->count(),
                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
"""
s = replace_once(s, old_chain_details, new_chain_details, 'assessment chain evidence details')

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# show.tsx: explain the new primary-evidence rule in Simulasi.
# -----------------------------------------------------------------------------
p = root / 'resources/js/pages/rps/show.tsx'
s = p.read_text(encoding='utf-8')

old_help = """                    Bobot non-UTS/UAS merupakan distribusi dari tag Sub-CPMK pada asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut. Nama asesmen pada simulasi mengikuti tag yang sama.
                    UTS dan UAS tetap mengikuti bobot asesmen sistem.
"""
new_help = """                    Bobot non-UTS/UAS merupakan distribusi dari tag Sub-CPMK pada asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut.
                    Nama asesmen/bentuk penilaian memakai satu bukti utama per pekan: RTM pada pekan tersebut, lalu asesmen yang memang dijadwalkan pada pekan yang sama, dan Bentuk Penilaian tabel RPS sebagai fallback.
                    UTS dan UAS tetap mengikuti bobot asesmen sistem.
"""
s = replace_once(s, old_help, new_help, 'simulation primary evidence help')

p.write_text(s, encoding='utf-8')

print('weekly primary assessment evidence patch applied')
