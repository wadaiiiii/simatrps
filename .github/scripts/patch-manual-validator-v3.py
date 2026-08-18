from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f"Pattern not found in {path}: {old!r}")
    p.write_text(text.replace(old, new, 1))

workspace = "app/Services/Rps/ObeWorkspaceService.php"

# Build short, concrete validator messages from the detailed alignment payload.
anchor = """        $missingRtmAssessments = collect($taskAlignment['missing_required_assessments'] ?? [])
            ->map(fn ($item) => trim((string) ($item['code'] ?? '')).' '.trim((string) ($item['name'] ?? '')))
            ->map(fn ($label) => trim($label))->filter()->values();

        $checks = [
"""
replacement = """        $missingRtmAssessments = collect($taskAlignment['missing_required_assessments'] ?? [])
            ->map(fn ($item) => trim((string) ($item['code'] ?? '')).' '.trim((string) ($item['name'] ?? '')))
            ->map(fn ($label) => trim($label))->filter()->values();

        $firstMappingIssue = collect($taskAlignment['mapping_mismatches'] ?? [])->first();
        $firstUnlinkedIssue = collect($taskAlignment['unlinked_tasks'] ?? [])->first();
        $firstDueWeekIssue = collect($taskAlignment['invalid_due_weeks'] ?? [])->first();
        $rtmPrimaryIssue = $firstMappingIssue ?: ($firstUnlinkedIssue ?: $firstDueWeekIssue);
        $rtmPrimaryCode = trim((string) ($rtmPrimaryIssue['code'] ?? 'RTM'));
        $rtmPrimaryReason = trim((string) ($rtmPrimaryIssue['reason'] ?? 'Hubungan RTM perlu diperiksa.'));
        $rtmAdditionalIssueCount = max(0, $rtmProblemTasks->count() - ($rtmPrimaryIssue ? 1 : 0));

        $assessmentChainIssueMessage = 'Periksa hubungan asesmen, Sub-CPMK, bobot pekan, dan RTM.';
        if (! $assessmentBudgetAligned) {
            $assessmentChainIssueMessage = $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.';
        } elseif ($positiveNonExamMappedCount < $positiveNonExamAssessments->count()) {
            $assessmentChainIssueMessage = ($positiveNonExamAssessments->count() - $positiveNonExamMappedCount).' asesmen berbobot belum terhubung ke Sub-CPMK.';
        } elseif ($weightedTeachingWeeks->count() < 14) {
            $assessmentChainIssueMessage = 'Pekan '.$unweightedTeachingWeekNumbers->implode(', ').' belum menerima bobot dari Detail Asesmen.';
            if ($uncoveredNonExamSubCodes->isNotEmpty()) {
                $assessmentChainIssueMessage .= ' Lengkapi asesmen untuk '.$uncoveredNonExamSubCodes->implode(', ').'.';
            }
        } elseif (! $subBudgetAligned) {
            $assessmentChainIssueMessage = 'Distribusi bobot per Sub-CPMK belum sesuai dengan Detail Asesmen.';
        } elseif ($ambiguousWeekNumbers->isNotEmpty()) {
            $assessmentChainIssueMessage = $ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.';
        } elseif ($missingWeekNumbers->isNotEmpty()) {
            $assessmentChainIssueMessage = 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.';
        } elseif ($taskAlignment['missing_required_assessment_count'] > 0) {
            $assessmentChainIssueMessage = $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.';
        } elseif ($rtmPrimaryIssue) {
            $assessmentChainIssueMessage = $rtmPrimaryCode.' belum sinkron: '.$rtmPrimaryReason;
        }

        $rtmMessage = 'RTM tidak diperlukan.';
        if (! $taskAssessments->isEmpty()) {
            if ((bool) $taskAlignment['is_aligned']) {
                $rtmMessage = $tasks.' RTM tersedia · Semua sinkron dengan asesmen induk.';
            } else {
                $parts = [$tasks.' RTM tersedia'];
                if ($missingRtmAssessments->isNotEmpty()) {
                    $parts[] = 'asesmen belum memiliki RTM: '.$missingRtmAssessments->implode(', ');
                }
                if ($rtmPrimaryIssue) {
                    $issueText = $rtmPrimaryCode.' belum sinkron: '.$rtmPrimaryReason;
                    if ($rtmAdditionalIssueCount > 0) {
                        $issueText .= ' (+'.$rtmAdditionalIssueCount.' RTM lain)';
                    }
                    $parts[] = $issueText;
                }
                $rtmMessage = implode(' · ', $parts).'.';
            }
        }

        $checks = [
"""
replace_once(workspace, anchor, replacement)

old_chain = """                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'hint' => 'Memeriksa hubungan asesmen, Sub-CPMK, bobot 14 pekan, bukti penilaian, dan RTM.',
                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : (! $assessmentBudgetAligned
                        ? $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.'
                        : ($weightedTeachingWeeks->count() < 14
                            ? 'Pekan '.$unweightedTeachingWeekNumbers->implode(', ').' belum menerima distribusi bobot dari Detail Asesmen.'
                                .($uncoveredNonExamSubCodes->isNotEmpty()
                                    ? ' Lengkapi asesmen non-UTS/UAS untuk '.$uncoveredNonExamSubCodes->implode(', ').'.'
                                    : '')
                            : ($ambiguousWeekNumbers->isNotEmpty()
                                ? ($ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.')
                                : ($missingWeekNumbers->isNotEmpty()
                                    ? 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'
                                    : ($taskAlignment['missing_required_assessment_count'] > 0
                                        ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                        : 'Rantai asesmen–Sub-CPMK–bobot pekan–RTM belum sepenuhnya selaras.'))))),
"""
new_chain = """                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'hint' => 'Mencocokkan asesmen ↔ Sub-CPMK ↔ bobot pekan ↔ RTM.',
                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : $assessmentChainIssueMessage,
"""
replace_once(workspace, old_chain, new_chain)

old_rtm = """                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'hint' => 'Memeriksa asesmen induk, cakupan Sub-CPMK, dan pekan pengumpulan setiap RTM.',
                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? \"{$tasks} RTM tersedia · Semua sinkron dengan asesmen induk.\"
                        : \"{$tasks} RTM tersedia\"
                            .($missingRtmAssessments->isNotEmpty()
                                ? ' · asesmen belum memiliki RTM: '.$missingRtmAssessments->implode(', ')
                                : ' · seluruh asesmen yang memerlukan RTM sudah memiliki RTM')
                            .($rtmProblemLabels->isNotEmpty()
                                ? ' · periksa hubungan asesmen/Sub-CPMK/jadwal: '.$rtmProblemLabels->implode(', ').'.'
                                : '.')),
"""
new_rtm = """                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'hint' => 'RTM harus sesuai dengan asesmen induk, Sub-CPMK, dan pekan pengumpulan.',
                'message' => $rtmMessage,
"""
replace_once(workspace, old_rtm, new_rtm)

print('validator messages now expose the exact first assessment/RTM issue')
