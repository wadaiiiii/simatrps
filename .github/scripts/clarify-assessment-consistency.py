from pathlib import Path

p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text(encoding='utf-8')

old = """        $weeklyEvidenceAligned = $weightedTeachingWeeks->count() === 14
            && $coveredEvidenceWeeks->count() === 14
            && $ambiguousWeightedWeeks->isEmpty();

        $positiveNonExamAssessments = $nonExamAssessments->filter(
"""
new = """        $weeklyEvidenceAligned = $weightedTeachingWeeks->count() === 14
            && $coveredEvidenceWeeks->count() === 14
            && $ambiguousWeightedWeeks->isEmpty();
        $ambiguousWeekNumbers = $ambiguousWeightedWeeks
            ->pluck('week')
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $missingWeekNumbers = $missingEvidenceWeeks
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $positiveNonExamAssessments = $nonExamAssessments->filter(
"""
if old not in s:
    raise SystemExit('marker 1 not found')
s = s.replace(old, new, 1)

old = """            [
                'key' => 'assessment_chain_sync',
                'label' => 'Sinkronisasi Rantai Asesmen',
                'done' => $assessmentChainAligned,
                'message' => $assessmentChainAligned
                    ? 'Asesmen, RTM, bukti pekan, dan bobot sinkron.'
                    : \"Belum sinkron · {$ambiguousWeightedWeeks->count()} pekan ambigu · {$missingEvidenceWeeks->count()} tanpa bukti · {$taskAlignment['missing_required_assessment_count']} RTM belum ada.\",
"""
new = """            [
                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : ($ambiguousWeekNumbers->isNotEmpty()
                        ? 'Pekan '.$ambiguousWeekNumbers->implode(', ').' memiliki lebih dari satu bukti penilaian.'
                        : ($missingWeekNumbers->isNotEmpty()
                            ? 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'
                            : ($taskAlignment['missing_required_assessment_count'] > 0
                                ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                : 'Masih ada data penilaian yang belum konsisten.'))),
"""
if old not in s:
    raise SystemExit('marker 2 not found')
s = s.replace(old, new, 1)

old = """            [
                'key' => 'weekly_assessment_evidence',
                'label' => 'Bukti Penilaian per Pekan',
                'done' => $weeklyEvidenceAligned,
                'message' => \"{$coveredEvidenceWeeks->count()}/14 pekan punya bukti · {$ambiguousWeightedWeeks->count()} ambigu · {$missingEvidenceWeeks->count()} tanpa bukti.\",
"""
new = """            [
                'key' => 'weekly_assessment_evidence',
                'label' => 'Bukti Penilaian per Pekan',
                'done' => $weeklyEvidenceAligned,
                'message' => $weeklyEvidenceAligned
                    ? '14/14 pekan memiliki satu bukti penilaian.'
                    : ($ambiguousWeekNumbers->isNotEmpty()
                        ? 'Pekan '.$ambiguousWeekNumbers->implode(', ').' memiliki lebih dari satu bukti.'
                        : 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'),
"""
if old not in s:
    raise SystemExit('marker 3 not found')
s = s.replace(old, new, 1)

p.write_text(s, encoding='utf-8')
