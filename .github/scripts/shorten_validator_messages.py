from pathlib import Path

p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text(encoding='utf-8')

replacements = {
'''        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK memiliki CPL; scope CPL RPS belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK memiliki CPL; "
                ."{$mappedScopeCplCount}/{$scopeCplCount} CPL scope terpetakan "
                ."({$officialCplCount} kurikulum + {$additionalCplCount} tambahan dosen).";
''': '''        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK terpetakan · CPL belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK · {$mappedScopeCplCount}/{$scopeCplCount} CPL terpetakan.";
''',
'''                'message' => "{$subCpmks->count()} Sub-CPMK; {$coveredCpmkCount}/{$cpmks->count()} CPMK terwakili.",
''': '''                'message' => "{$subCpmks->count()} Sub-CPMK · {$coveredCpmkCount}/{$cpmks->count()} CPMK terwakili.",
''',
'''                'message' => "{$materials} bahan kajian tersedia.",
''': '''                'message' => "{$materials} bahan kajian.",
''',
'''                'message' => "{$filledWeeks}/16 pertemuan sudah terisi.",
''': '''                'message' => "{$filledWeeks}/16 pertemuan terisi.",
''',
'''                'message' => 'UTS pekan 8 dan UAS pekan 16.',
''': '''                'message' => 'UTS Pekan 8 · UAS Pekan 16.',
''',
'''                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; distribusi pekan non-ujian {$teachingWeightTotal}% dari anggaran asesmen non-UTS/UAS {$nonExamAssessmentWeight}%; kesesuaian bobot per Sub-CPMK ".($subBudgetAligned ? 'sesuai' : 'belum sesuai')."; total tabel RPS {$weightTotal}% dan total asesmen agregat {$assessmentWeightTotal}%.",
''': '''                'message' => "{$weightedTeachingWeeks->count()}/14 pekan berbobot · Total {$weightTotal}%.",
''',
'''                'message' => "{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; {$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK tercakup oleh pekan berbobot.",
''': '''                'message' => "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur · {$weightedTeachingWeeks->count()}/14 pekan.",
''',
'''                'message' => "{$positiveNonExamMappedCount}/{$positiveNonExamAssessments->count()} asesmen non-UTS/UAS berbobot memiliki tag Sub-CPMK; "
                    ."{$weightedTeachingWeeks->count()}/14 pekan berbobot; "
                    ."kecocokan anggaran per Sub-CPMK ".($subBudgetAligned ? 'sesuai' : 'belum sesuai')."; "
                    ."bukti penilaian utama {$coveredEvidenceWeeks->count()}/14 pekan, ambigu {$ambiguousWeightedWeeks->count()}; "
                    ."RTM tidak sinkron {$taskAlignment['mapping_mismatch_count']}; RTM berbobot tanpa asesmen {$taskAlignment['unlinked_weighted_task_count']}; ketidaksesuaian Sub-CPMK RTM dengan pekan {$taskAlignment['due_week_subcpmk_mismatch_count']}; asesmen yang membutuhkan RTM tetapi belum memiliki RTM {$taskAlignment['missing_required_assessment_count']}.",
''': '''                'message' => $assessmentChainAligned
                    ? 'Asesmen, RTM, bukti pekan, dan bobot sinkron.'
                    : "Belum sinkron · {$ambiguousWeightedWeeks->count()} pekan ambigu · {$missingEvidenceWeeks->count()} tanpa bukti · {$taskAlignment['missing_required_assessment_count']} RTM belum ada.",
''',
'''                'message' => "{$coveredEvidenceWeeks->count()}/14 pekan berbobot memiliki satu bukti penilaian utama; "
                    .$ambiguousWeightedWeeks->count()." pekan ambigu; "
                    .$missingEvidenceWeeks->count()." pekan belum memiliki bukti penilaian yang jelas.",
''': '''                'message' => "{$coveredEvidenceWeeks->count()}/14 pekan punya bukti · {$ambiguousWeightedWeeks->count()} ambigu · {$missingEvidenceWeeks->count()} tanpa bukti.",
''',
'''                'message' => $taskAssessments->isEmpty()
                    ? 'Belum ada asesmen tugas/proyek/presentasi yang mewajibkan RTM.'
                    : "{$tasks} RTM tersedia; {$taskAlignment['missing_required_assessment_count']} asesmen tugas belum memiliki RTM; {$taskAlignment['unlinked_weighted_task_count']} RTM berbobot belum terhubung asesmen; {$taskAlignment['due_week_subcpmk_mismatch_count']} RTM tidak cocok dengan Sub-CPMK pekannya; {$taskAlignment['mapping_mismatch_count']} RTM memiliki tag berbeda dari asesmennya.",
''': '''                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? "{$tasks} RTM · Semua sinkron."
                        : "{$tasks} RTM · {$taskAlignment['missing_required_assessment_count']} belum ada · {$taskAlignment['mapping_mismatch_count']} tidak sinkron."),
''',
}

for old, new in replacements.items():
    if old not in s:
        raise SystemExit('missing expected validator message block:\n' + old[:180])
    s = s.replace(old, new, 1)

p.write_text(s, encoding='utf-8')
print('validator messages simplified')
