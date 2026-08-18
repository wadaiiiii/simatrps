from pathlib import Path

path = Path('app/Http/Controllers/RpsAiController.php')
text = path.read_text()

old_rank = '''            if (! $match) {
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
'''

new_rank = '''            if (! $match && ! in_array($type, ['uts', 'uas'], true) && $wantedSubs->isNotEmpty()) {
                // Constructive alignment is the primary identity of a non-exam
                // assessment. A lecturer may rename or change the form while it
                // still measures the same Sub-CPMK. Rank all non-exam items by
                // Sub-CPMK coverage first; type/name/week are tie-breakers.
                $ranked = $available
                    ->reject(fn ($row) => in_array(strtolower((string) $row->type), ['uts', 'uas'], true))
                    ->map(function ($row) use ($assessmentLinks, $wantedSubs, $week, $name, $type): array {
                        $currentSubs = collect($assessmentLinks->get($row->id, []))
                            ->pluck('code')
                            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                            ->filter()->unique()->values();
                        $overlap = $wantedSubs->intersect($currentSubs)->count();
                        $sameCoverage = $wantedSubs->sort()->values()->all()
                            === $currentSubs->sort()->values()->all();
                        $sameType = strtolower((string) $row->type) === $type;
                        $sameWeek = $week > 0 && (int) ($row->week_number ?? 0) === $week;
                        $nameOverlap = count(array_intersect(
                            $this->semanticTokens($name),
                            $this->semanticTokens((string) $row->name)
                        ));
                        $score = ($overlap * 10)
                            + ($sameCoverage ? 20 : 0)
                            + ($sameType ? 3 : 0)
                            + ($sameWeek ? 2 : 0)
                            + min(3, $nameOverlap);
                        return [
                            'row' => $row,
                            'score' => $score,
                            'overlap' => $overlap,
                            'same_coverage' => $sameCoverage,
                        ];
                    })
                    ->sortByDesc('score')
                    ->values();

                $best = $ranked->first();
                // Never adapt solely because type/week/name happen to match.
                // At least one shared Sub-CPMK is required for non-exam items.
                if ($best && ($best['overlap'] ?? 0) > 0) {
                    $match = $best['row'];
                }
            }
'''

if old_rank not in text:
    raise SystemExit('assessment ranking marker not found')
text = text.replace(old_rank, new_rank, 1)

old_rationale = "                    : 'Asesmen yang sudah ada dikenali sebagai target perbaikan berdasarkan tipe, jadwal, dan cakupan Sub-CPMK.';"
new_rationale = "                    : 'Asesmen yang sudah ada dikenali sebagai target perbaikan terutama dari kesamaan cakupan Sub-CPMK; tipe, jadwal, dan nama menjadi penguat.';"
if old_rationale in text:
    text = text.replace(old_rationale, new_rationale, 1)

old_apply = '''    ): array {
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
'''
new_apply = '''    ): array {
        // Re-evaluate merge actions at APPLY time against the latest RPS state.
        // This also repairs older pending suggestions that were previously
        // classified as ADD because name/type differed from manual data.
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
'''
if old_apply not in text:
    raise SystemExit('apply merge refresh marker not found')
text = text.replace(old_apply, new_apply, 1)

path.write_text(text)
