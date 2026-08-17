from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"Missing marker: {label}")
    return text.replace(old, new, 1)


p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text(encoding='utf-8')
s = replace_once(
    s,
    """        $weeklyMaterialIssues = collect();
        foreach ($teachingWeeks as $week) {
""",
    """        $weeklyMaterialIssues = collect();
        $confirmedWeeklyMaterialCount = 0;
        foreach ($teachingWeeks as $week) {
""",
    'weekly material confirmed counter',
)

old_issue = """                $currentSub = $subById->get($currentSubId);
                $bestSub = $subById->get($bestSubId);
                $weeklyMaterialIssues->push([
                    'week' => (int) $week->week_number,
                    'material' => $materialText,
                    'current_sub_code' => (string) ($currentSub?->code ?? ''),
                    'suggested_sub_code' => (string) ($bestSub?->code ?? ''),
                    'current_score' => round($currentScore, 3),
                    'best_score' => round($bestScore, 3),
                ]);
"""
new_issue = """                $currentSub = $subById->get($currentSubId);
                $bestSub = $subById->get($bestSubId);
                $decisionKey = 'weekly-material:week:'.(int) $week->week_number
                    .':sub:'.$currentSubId
                    .':'.sha1(
                        $this->semanticNormalized($materialText)
                        .'|'.$currentSubId
                        .'|'.$bestSubId
                    );

                if ($keptDecisionKeys->has($decisionKey)) {
                    $confirmedWeeklyMaterialCount++;
                    continue;
                }

                $weeklyMaterialIssues->push([
                    'decision_key' => $decisionKey,
                    'week' => (int) $week->week_number,
                    'material' => $materialText,
                    'current_sub_code' => (string) ($currentSub?->code ?? ''),
                    'suggested_sub_code' => (string) ($bestSub?->code ?? ''),
                    'current_score' => round($currentScore, 3),
                    'best_score' => round($bestScore, 3),
                ]);
"""
s = replace_once(s, old_issue, new_issue, 'weekly material decision key')

old_check = """                'done' => $weeklyMaterialSemanticsAligned,
                'message' => $weeklyMaterialSemanticsAligned
                    ? 'Materi pekan selaras dengan Sub-CPMK.'
                    : (($issue = $weeklyMaterialIssues->first())
                        ? 'Pekan '.$issue['week'].': materi lebih dekat ke '.$issue['suggested_sub_code'].' daripada '.$issue['current_sub_code'].'.'
                        : 'Ada materi pekan yang perlu ditelaah.'),
                'details' => [
                    'issues' => $weeklyMaterialIssues->all(),
                ],
"""
new_check = """                'done' => $weeklyMaterialSemanticsAligned,
                'message' => $weeklyMaterialSemanticsAligned
                    ? ($confirmedWeeklyMaterialCount > 0
                        ? 'Materi pekan diterima · '.$confirmedWeeklyMaterialCount.' keputusan dosen untuk tidak mengikuti rekomendasi dipertahankan.'
                        : 'Materi pekan selaras dengan Sub-CPMK.')
                    : (($issue = $weeklyMaterialIssues->first())
                        ? 'Pekan '.$issue['week'].': materi lebih dekat ke '.$issue['suggested_sub_code'].' daripada '.$issue['current_sub_code'].'. Dosen boleh memperbaiki materi atau melanjutkan tanpa mengikuti rekomendasi.'
                        : 'Ada materi pekan yang perlu ditelaah.'),
                'details' => [
                    'issues' => $weeklyMaterialIssues->all(),
                    'confirmed_count' => $confirmedWeeklyMaterialCount,
                ],
"""
s = replace_once(s, old_check, new_check, 'weekly material check message')
p.write_text(s, encoding='utf-8')


p = Path('app/Http/Controllers/RpsValidatorDecisionController.php')
s = p.read_text(encoding='utf-8')
s = replace_once(
    s,
    "Rule::in(['assessment_semantics', 'rtm_semantics'])",
    "Rule::in(['assessment_semantics', 'rtm_semantics', 'weekly_material_semantics'])",
    'validator decision allowed keys',
)
p.write_text(s, encoding='utf-8')


p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')
s = replace_once(
    s,
    "['assessment_semantics', 'rtm_semantics'].includes(check.key)",
    "['assessment_semantics', 'rtm_semantics', 'weekly_material_semantics'].includes(check.key)",
    'validator decision UI keys',
)

old_success = """                                                                        : check.key === 'assessment_semantics'
                                                                            ? 'Tag Sub-CPMK dipertahankan sebagai keputusan dosen.'
                                                                            : 'Hubungan RTM dipertahankan sebagai keputusan dosen.',
"""
new_success = """                                                                        : check.key === 'assessment_semantics'
                                                                            ? 'Tag Sub-CPMK dipertahankan sebagai keputusan dosen.'
                                                                            : check.key === 'weekly_material_semantics'
                                                                                ? 'Materi pekan dipertahankan. Rekomendasi kesesuaian materi tidak diikuti.'
                                                                                : 'Hubungan RTM dipertahankan sebagai keputusan dosen.',
"""
s = replace_once(s, old_success, new_success, 'validator decision success copy')

old_label = """                                                            if (count > 1) return `Pertahankan Semua (${count})`;
                                                            return check.key === 'assessment_semantics'
                                                                ? 'Pertahankan Tag'
                                                                : 'Pertahankan Hubungan';
"""
new_label = """                                                            if (check.key === 'weekly_material_semantics') {
                                                                return count > 1
                                                                    ? `Lanjut Semua (${count})`
                                                                    : 'Lanjut (Tidak Ikut Rekomendasi)';
                                                            }
                                                            if (count > 1) return `Pertahankan Semua (${count})`;
                                                            return check.key === 'assessment_semantics'
                                                                ? 'Pertahankan Tag'
                                                                : 'Pertahankan Hubungan';
"""
s = replace_once(s, old_label, new_label, 'validator decision button copy')
p.write_text(s, encoding='utf-8')
