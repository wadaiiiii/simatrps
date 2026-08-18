from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"{label} marker not found")
    return text.replace(old, new, 1)


# 1) Expose deterministic stored/remaining assessment budget to AI context.
context_path = Path("app/Services/Rps/RpsAiContextService.php")
context = context_path.read_text()
context = replace_once(
    context,
    """                    ->take(20)
                    ->values()
                    ->all(),
                'current_tasks' => collect($full['tasks'] ?? [])
""",
    """                    ->take(20)
                    ->values()
                    ->all(),
                'assessment_budget' => [
                    'existing_weight_total' => round((float) collect($full['assessments'])
                        ->sum(fn (array $assessment) => (float) ($assessment['weight'] ?? 0)), 2),
                    'remaining_weight' => round(max(0, 100 - (float) collect($full['assessments'])
                        ->sum(fn (array $assessment) => (float) ($assessment['weight'] ?? 0))), 2),
                    'weighted_assessment_codes' => collect($full['assessments'])
                        ->filter(fn (array $assessment) => (float) ($assessment['weight'] ?? 0) > 0)
                        ->pluck('code')->filter()->values()->all(),
                ],
                'current_tasks' => collect($full['tasks'] ?? [])
""",
    "assessment budget context",
)
context_path.write_text(context)


# 2) Make existing saved weights a locked baseline; AI only fills remaining weight.
ai_path = Path("app/Http/Controllers/RpsAiController.php")
ai = ai_path.read_text()
ai = replace_once(
    ai,
    """- `current_assessments` dan `current_tasks` adalah kondisi RPS dosen SAAT INI. Telaah dan manfaatkan data itu; jangan berasumsi RPS kosong.
- Pertahankan asesmen/RTM lama yang sudah selaras. Jangan menduplikasi item yang secara akademik sudah mewakili fungsi yang sama.
""",
    """- `current_assessments` dan `current_tasks` adalah kondisi RPS dosen SAAT INI. Telaah dan manfaatkan data itu; jangan berasumsi RPS kosong.
- `assessment_budget.existing_weight_total` adalah total bobot ASESMEN YANG SUDAH TERSIMPAN. Semua asesmen existing dengan bobot > 0 adalah BASELINE TERKUNCI: pertahankan bobot dan identitasnya, jangan keluarkan ulang sebagai rekomendasi asesmen baru/perbaikan hanya untuk membentuk paket 100%.
- `assessment_budget.remaining_weight` adalah SATU-SATUNYA anggaran bobot untuk rekomendasi asesmen baru atau asesmen existing berbobot 0 yang memang perlu dilengkapi. Jumlah bobot seluruh rekomendasi asesmen yang benar-benar baru/dilengkapi WAJIB tepat sebesar `remaining_weight`, sehingga existing + rekomendasi = tepat 100%.
- Jika `remaining_weight` = 0, jangan rekomendasikan asesmen baru. Telaah hanya RTM/keterkaitan yang masih perlu diperbaiki tanpa mengubah bobot asesmen yang sudah tersimpan.
- Pertahankan asesmen/RTM lama yang sudah selaras. Jangan menduplikasi item yang secara akademik sudah mewakili fungsi yang sama.
""",
    "remaining budget prompt",
)
ai = replace_once(
    ai,
    "'rtm-integrative-v5-assessment-source'",
    "'rtm-integrative-v6-remaining-budget'",
    "assessment policy version",
)
ai = replace_once(
    ai,
    """        $payload['tasks'] = $tasks;
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""",
    """        $payload['tasks'] = $tasks;
        $payload = $this->constrainAssessmentPlanToRemainingBudget($payload, $version);
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
""",
    "sanitize remaining budget",
)

helper = r'''
    private function constrainAssessmentPlanToRemainingBudget(array $payload, object $version): array
    {
        $existing = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'description', 'weight', 'source_type']);

        $existingTotal = round((float) $existing->sum(fn ($row) => (float) ($row->weight ?? 0)), 2);
        if ($existingTotal > 100.001) {
            throw ValidationException::withMessages([
                'ai' => 'Total bobot asesmen yang sudah tersimpan '.$existingTotal.'%. Rapikan bobot manual hingga maksimal 100% sebelum menjalankan Telaah Asesmen + RTM AI.',
            ]);
        }

        $remaining = round(max(0, 100 - $existingTotal), 2);
        $positive = $existing->filter(fn ($row) => (float) ($row->weight ?? 0) > 0)->values();
        $positiveIds = $positive->pluck('id')->map('strval')->all();
        $links = $positiveIds === []
            ? collect()
            : DB::table('assessment_subcpmks')
                ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
                ->whereIn('assessment_subcpmks.assessment_id', $positiveIds)
                ->get(['assessment_subcpmks.assessment_id', 'rps_sub_cpmks.code'])
                ->groupBy('assessment_id');

        $nameRemap = [];
        $candidates = [];

        foreach (($payload['assessments'] ?? []) as $item) {
            if (! is_array($item)) continue;

            $name = trim((string) ($item['name'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'other')));
            $week = $type === 'uts' ? 8 : ($type === 'uas' ? 16 : (int) ($item['week_number'] ?? 0));
            $targetCode = trim((string) ($item['target_code'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->sort()->values()->all();

            $match = null;
            if ($targetCode !== '') {
                $match = $positive->first(fn ($row) => strcasecmp((string) $row->code, $targetCode) === 0);
            }
            if (! $match && in_array($type, ['uts', 'uas'], true)) {
                $match = $positive->first(fn ($row) => strtolower((string) $row->type) === $type);
            }
            if (! $match && $name !== '') {
                $needle = $this->comparableText($name);
                $match = $positive->first(fn ($row) => $this->comparableText((string) $row->name) === $needle);
            }
            if (! $match && ! in_array($type, ['uts', 'uas'], true) && $wantedSubs !== []) {
                $match = $positive->first(function ($row) use ($links, $type, $week, $wantedSubs): bool {
                    if (strtolower((string) $row->type) !== $type) return false;
                    if ($week > 0 && (int) ($row->week_number ?? 0) !== $week) return false;
                    $currentSubs = collect($links->get($row->id, []))
                        ->pluck('code')
                        ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                        ->filter()->unique()->sort()->values()->all();
                    return $currentSubs === $wantedSubs;
                });
            }

            if ($match) {
                if ($name !== '') {
                    $nameRemap[$this->comparableText($name)] = trim((string) $match->name);
                }
                continue;
            }

            $candidates[] = $item;
        }

        $tasks = $payload['tasks'] ?? [];
        foreach ($tasks as $index => $task) {
            if (! is_array($task)) continue;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));
            $key = $assessmentName !== '' ? $this->comparableText($assessmentName) : '';
            if ($key !== '' && isset($nameRemap[$key])) {
                $tasks[$index]['assessment_name'] = $nameRemap[$key];
            }
        }
        $payload['tasks'] = $tasks;

        if ($remaining <= 0.001) {
            $candidates = [];
        } elseif ($candidates === []) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan asesmen tambahan untuk bobot sisa '.$remaining.'%. Jalankan Telaah Asesmen + RTM AI kembali; asesmen yang sudah berbobot tetap dipertahankan.',
            ]);
        } else {
            $requestedTotal = collect($candidates)->sum(fn ($item) => max(0, (float) ($item['weight'] ?? 0)));
            $allocated = 0.0;
            $lastIndex = count($candidates) - 1;

            foreach ($candidates as $index => $item) {
                if ($index === $lastIndex) {
                    $weight = round(max(0, $remaining - $allocated), 2);
                } elseif ($requestedTotal > 0.001) {
                    $weight = round($remaining * (max(0, (float) ($item['weight'] ?? 0)) / $requestedTotal), 2);
                    $allocated = round($allocated + $weight, 2);
                } else {
                    $weight = round($remaining / count($candidates), 2);
                    $allocated = round($allocated + $weight, 2);
                }
                $candidates[$index]['weight'] = $weight;
            }
        }

        $payload['assessments'] = array_values($candidates);
        $payload['_assessment_budget'] = [
            'existing_weight_total' => $existingTotal,
            'remaining_weight' => $remaining,
            'recommended_new_weight_total' => round((float) collect($candidates)->sum(fn ($item) => (float) ($item['weight'] ?? 0)), 2),
            'target_total' => 100.0,
        ];

        $summary = trim((string) ($payload['summary'] ?? ''));
        $budgetNote = $remaining > 0.001
            ? 'Bobot tersimpan '.$existingTotal.'% dipertahankan; rekomendasi asesmen baru disaring menjadi '.$remaining.'% agar total akhir tepat 100%.'
            : 'Bobot tersimpan sudah 100%; AI tidak menambahkan asesmen baru dan hanya menelaah RTM/keterkaitan.';
        $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$budgetNote : $budgetNote;

        return $payload;
    }

'''
ai = replace_once(
    ai,
    "\n\n    private function assertNonExamAssessmentCoverage(array $payload, object $version): array\n",
    "\n\n" + helper + "    private function assertNonExamAssessmentCoverage(array $payload, object $version): array\n",
    "insert remaining budget helper",
)

old_coverage = """        $coveredCodes = collect($payload['assessments'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->reject(fn ($item) => in_array(strtolower(trim((string) ($item['type'] ?? 'other'))), ['uts', 'uas'], true))
            ->filter(fn ($item) => (float) ($item['weight'] ?? 0) > 0)
            ->flatMap(fn ($item) => $item['sub_cpmk_codes'] ?? [])
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();
"""
new_coverage = """        $existingCoveredCodes = DB::table('assessment_subcpmks')
            ->join('assessments', 'assessments.id', '=', 'assessment_subcpmks.assessment_id')
            ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
            ->where('assessments.rps_version_id', $version->id)
            ->whereNotIn('assessments.type', ['uts', 'uas'])
            ->whereRaw('COALESCE(assessments.weight, 0) > 0')
            ->pluck('rps_sub_cpmks.code')
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();

        $coveredCodes = $existingCoveredCodes->merge(
            collect($payload['assessments'] ?? [])
                ->filter(fn ($item) => is_array($item))
                ->reject(fn ($item) => in_array(strtolower(trim((string) ($item['type'] ?? 'other'))), ['uts', 'uas'], true))
                ->filter(fn ($item) => (float) ($item['weight'] ?? 0) > 0)
                ->flatMap(fn ($item) => $item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()
        )->unique()->values();
"""
ai = replace_once(ai, old_coverage, new_coverage, "coverage union existing")

old_apply = """        // Re-evaluate coverage and merge actions at APPLY time against the latest RPS state.
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
"""
new_apply = """        // Re-evaluate coverage and merge actions at APPLY time against the latest RPS state.
        // A remaining-budget recommendation is valid only while the stored baseline
        // weight is unchanged; otherwise the lecturer must review the new remainder.
        $budgetAtReview = $payload['_assessment_budget'] ?? null;
        if (! is_array($budgetAtReview)) {
            throw ValidationException::withMessages([
                'ai' => 'Rekomendasi ini dibuat dengan kebijakan bobot lama. Jalankan Telaah Asesmen + RTM AI kembali agar rekomendasi menyesuaikan bobot sisa terbaru.',
            ]);
        }
        $currentStoredTotal = round((float) DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->sum('weight'), 2);
        $reviewStoredTotal = round((float) ($budgetAtReview['existing_weight_total'] ?? 0), 2);
        if (abs($currentStoredTotal - $reviewStoredTotal) > 0.01) {
            throw ValidationException::withMessages([
                'ai' => 'Bobot asesmen berubah sejak rekomendasi dibuat (saat telaah '.$reviewStoredTotal.'%, sekarang '.$currentStoredTotal.'%). Jalankan Telaah Asesmen + RTM AI kembali agar bobot rekomendasi dihitung dari sisa terbaru.',
            ]);
        }

        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
"""
ai = replace_once(ai, old_apply, new_apply, "stale budget apply guard")
ai_path.write_text(ai)
