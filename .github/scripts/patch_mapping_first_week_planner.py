from pathlib import Path

# 1) Smart Draft: replace count-based weekly distribution with mapping-first planner.
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')
start = s.index('    private function buildTeachingSequence($subCpmks, $materials): array\n    {')
end = s.index('    private function materialRelevanceScore(string $subDescription, string $materialTitle): int\n    {', start)
new_block = r'''    private function buildTeachingSequence($subCpmks, $materials): array
    {
        $subs = $subCpmks->values();
        $weekTotal = count(self::TEACHING_WEEKS);

        if ($subs->isEmpty()) {
            return [];
        }

        if ($subs->count() > $weekTotal) {
            throw ValidationException::withMessages([
                'smart_draft' => 'Jumlah Sub-CPMK ('.$subs->count().') melebihi '.$weekTotal.' pertemuan efektif. Gabungkan atau rapikan Sub-CPMK terlebih dahulu agar setiap Sub-CPMK dapat memperoleh minimal satu pertemuan.',
            ]);
        }

        $materialRows = $materials
            ->filter(fn ($material) => filled($material->title ?? null))
            ->values();

        $subIndexById = [];
        foreach ($subs as $index => $sub) {
            $subIndexById[(string) $sub->id] = (int) $index;
        }

        // Relasi eksplisit selalu menjadi prioritas. Jika pivot many-to-many
        // tersedia, satu Bahan Kajian boleh memang dipakai oleh lebih dari satu
        // Sub-CPMK. Bahan Kajian tanpa relasi eksplisit dipetakan satu kali ke
        // Sub-CPMK yang paling relevan secara semantik.
        $pivotLinks = collect();
        $materialIds = $materialRows
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if (
            $materialIds !== []
            && Schema::hasTable('rps_material_subcpmks')
        ) {
            $pivotLinks = DB::table('rps_material_subcpmks')
                ->whereIn('rps_material_id', $materialIds)
                ->get(['rps_material_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_material_id');
        }

        $assignments = array_fill(0, $subs->count(), []);
        $materialCount = max(1, $materialRows->count());

        foreach ($materialRows as $materialIndex => $material) {
            $title = trim((string) $material->title);
            $explicitIndexes = [];

            if (filled($material->rps_sub_cpmk_id ?? null)) {
                $direct = $subIndexById[(string) $material->rps_sub_cpmk_id] ?? null;
                if ($direct !== null) {
                    $explicitIndexes[] = $direct;
                }
            }

            if (filled($material->id ?? null) && $pivotLinks->has((string) $material->id)) {
                foreach ($pivotLinks->get((string) $material->id) as $link) {
                    $linked = $subIndexById[(string) $link->rps_sub_cpmk_id] ?? null;
                    if ($linked !== null) {
                        $explicitIndexes[] = $linked;
                    }
                }
            }

            $explicitIndexes = array_values(array_unique($explicitIndexes));
            if ($explicitIndexes !== []) {
                foreach ($explicitIndexes as $subIndex) {
                    $assignments[$subIndex][] = [
                        'title' => $title,
                        'index' => (int) $materialIndex,
                    ];
                }
                continue;
            }

            $scores = [];
            foreach ($subs as $subIndex => $sub) {
                $scores[$subIndex] = $this->materialRelevanceScore(
                    (string) ($sub->description ?? ''),
                    $title
                );
            }

            $bestScore = $scores === [] ? 0 : max($scores);
            $expectedIndex = $materialRows->count() <= 1
                ? 0.0
                : ((float) $materialIndex / (float) ($materialCount - 1)) * max(0, $subs->count() - 1);

            $candidateIndexes = array_keys(
                array_filter(
                    $scores,
                    fn ($score) => $score === $bestScore
                )
            );

            if ($candidateIndexes === []) {
                $candidateIndexes = range(0, $subs->count() - 1);
            }

            usort($candidateIndexes, function (int $a, int $b) use ($expectedIndex): int {
                $distanceA = abs($a - $expectedIndex);
                $distanceB = abs($b - $expectedIndex);
                return $distanceA <=> $distanceB ?: $a <=> $b;
            });

            $targetIndex = (int) $candidateIndexes[0];
            $assignments[$targetIndex][] = [
                'title' => $title,
                'index' => (int) $materialIndex,
            ];
        }

        $titlesBySub = [];
        foreach ($assignments as $subIndex => $rows) {
            $titlesBySub[$subIndex] = collect($rows)
                ->sortBy('index')
                ->pluck('title')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        // Setiap Sub-CPMK minimal satu minggu. Sisa minggu TIDAK dibagi rata.
        // Alokasi tambahan mempertimbangkan beban Bahan Kajian dan tingkat Bloom.
        // Karena itu satu Sub-CPMK boleh 1 minggu, sementara yang lebih kompleks
        // dapat memperoleh beberapa minggu.
        $weekCounts = array_fill(0, $subs->count(), 1);
        $demand = [];

        foreach ($subs as $subIndex => $sub) {
            $materialLoad = count($titlesBySub[$subIndex] ?? []);
            $materialFactor = $materialLoad === 0
                ? 0.35
                : min(3.25, $materialLoad * 0.65);

            $demand[$subIndex] = 1.0
                + $materialFactor
                + $this->bloomComplexityWeight((string) ($sub->bloom_level ?? ''));
        }

        $remaining = $weekTotal - $subs->count();
        while ($remaining > 0) {
            $bestIndex = 0;
            $bestPriority = -INF;

            foreach ($subs as $subIndex => $_sub) {
                $priority = $demand[$subIndex] / max(1, $weekCounts[$subIndex]);
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestIndex = (int) $subIndex;
                }
            }

            $weekCounts[$bestIndex]++;
            $remaining--;
        }

        $sequence = [];
        foreach ($subs as $subIndex => $sub) {
            $groups = $this->splitMaterialsAcrossWeeks(
                $titlesBySub[$subIndex] ?? [],
                $weekCounts[$subIndex],
                $sub
            );

            foreach ($groups as $materialText) {
                $sequence[] = [
                    'sub' => $sub,
                    'material' => $materialText,
                ];
            }
        }

        return array_slice($sequence, 0, $weekTotal);
    }

    private function bloomComplexityWeight(string $level): float
    {
        return match (strtoupper(trim($level))) {
            'C1' => 0.00,
            'C2' => 0.15,
            'C3' => 0.35,
            'C4' => 0.65,
            'C5' => 0.90,
            'C6' => 1.15,
            default => 0.25,
        };
    }

    private function splitMaterialsAcrossWeeks(array $titles, int $weekCount, object $sub): array
    {
        $weekCount = max(1, $weekCount);
        $titles = array_values(array_filter(array_map(
            fn ($title) => trim((string) $title),
            $titles
        )));

        if ($titles === []) {
            return array_fill(0, $weekCount, null);
        }

        $materialCount = count($titles);
        $groups = [];

        // Jika Bahan Kajian lebih banyak daripada minggu yang dialokasikan,
        // beberapa Bahan Kajian yang berurutan digabung dalam satu pertemuan.
        if ($materialCount >= $weekCount) {
            for ($week = 0; $week < $weekCount; $week++) {
                $start = (int) floor(($week * $materialCount) / $weekCount);
                $end = (int) floor((($week + 1) * $materialCount) / $weekCount);
                $chunk = array_slice($titles, $start, max(1, $end - $start));
                $groups[] = implode('; ', $chunk);
            }

            return $groups;
        }

        // Jika minggu lebih banyak daripada Bahan Kajian, materi tidak diputar
        // mentah. Minggu tambahan diberi tujuan pedagogis eksplisit sesuai Bloom.
        foreach ($titles as $title) {
            $groups[] = $title;
        }

        $baseTitle = $titles[count($titles) - 1];
        while (count($groups) < $weekCount) {
            $groups[] = $this->pedagogicalExtension(
                (string) ($sub->bloom_level ?? ''),
                $baseTitle,
                count($groups) - $materialCount
            );
        }

        return $groups;
    }

    private function pedagogicalExtension(string $bloom, string $title, int $extensionIndex): string
    {
        $prefixes = match (strtoupper(trim($bloom))) {
            'C1', 'C2' => [
                'Penguatan konsep dan latihan',
                'Pendalaman pemahaman dan pembahasan',
            ],
            'C3' => [
                'Latihan penerapan dan pemecahan masalah',
                'Pendalaman penerapan melalui latihan terarah',
            ],
            'C4' => [
                'Analisis kasus dan pembahasan',
                'Pendalaman analisis dan perbandingan kasus',
            ],
            'C5' => [
                'Evaluasi kasus dan pembahasan kritis',
                'Pendalaman evaluasi dan argumentasi',
            ],
            'C6' => [
                'Perancangan/pengembangan dan umpan balik',
                'Penyempurnaan rancangan dan refleksi',
            ],
            default => [
                'Pendalaman dan latihan',
                'Penguatan dan pembahasan lanjutan',
            ],
        };

        $prefix = $prefixes[$extensionIndex % count($prefixes)];
        return $prefix.': '.$title;
    }

'''
s = s[:start] + new_block + s[end:]

# Smart Draft-generated weeks may be re-aligned, including clearing an old
# mismatched material when the new planner finds no legitimate material.
s = s.replace(
    "                if ($refreshGeneratedField && filled($value)) {\n",
    "                if ($refreshGeneratedField) {\n",
    1,
)
p.write_text(s, encoding='utf-8')

# 2) AI per week fallback: use the same mapping-first idea instead of equal split.
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
start = s.index('    private function targetSubCpmkForWeek(\n')
end = s.index('    private function defaultTimeEstimate(int $credits): string\n', start)
new_method = r'''    private function targetSubCpmkForWeek(
        string $versionId,
        int $week
    ): ?object {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $position = array_search($week, $teachingWeeks, true);

        if ($position === false) {
            return null;
        }

        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code', 'sequence_no', 'description', 'bloom_level']);

        if ($subs->isEmpty()) {
            return null;
        }

        if ($subs->count() > count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'ai' => 'Jumlah Sub-CPMK melebihi 14 pertemuan efektif. Rapikan Sub-CPMK terlebih dahulu sebelum menggunakan AI Pekan.',
            ]);
        }

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'rps_sub_cpmk_id', 'title']);

        $subIndexById = [];
        foreach ($subs as $index => $sub) {
            $subIndexById[(string) $sub->id] = (int) $index;
        }

        $pivotLinks = collect();
        $materialIds = $materials->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        if ($materialIds !== [] && Schema::hasTable('rps_material_subcpmks')) {
            $pivotLinks = DB::table('rps_material_subcpmks')
                ->whereIn('rps_material_id', $materialIds)
                ->get(['rps_material_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_material_id');
        }

        $materialLoads = array_fill(0, $subs->count(), 0);
        $materialCount = max(1, $materials->count());

        foreach ($materials as $materialIndex => $material) {
            $explicit = [];
            if (filled($material->rps_sub_cpmk_id ?? null)) {
                $index = $subIndexById[(string) $material->rps_sub_cpmk_id] ?? null;
                if ($index !== null) $explicit[] = $index;
            }
            if ($pivotLinks->has((string) $material->id)) {
                foreach ($pivotLinks->get((string) $material->id) as $link) {
                    $index = $subIndexById[(string) $link->rps_sub_cpmk_id] ?? null;
                    if ($index !== null) $explicit[] = $index;
                }
            }
            $explicit = array_values(array_unique($explicit));
            if ($explicit !== []) {
                foreach ($explicit as $index) $materialLoads[$index]++;
                continue;
            }

            $titleTokens = $this->semanticTokens((string) ($material->title ?? ''));
            $scores = [];
            foreach ($subs as $subIndex => $sub) {
                $scores[$subIndex] = count(array_intersect(
                    $titleTokens,
                    $this->semanticTokens((string) $sub->description)
                ));
            }
            $bestScore = $scores === [] ? 0 : max($scores);
            $expected = $materials->count() <= 1
                ? 0.0
                : ((float) $materialIndex / (float) ($materialCount - 1)) * max(0, $subs->count() - 1);
            $candidates = array_keys(array_filter($scores, fn ($score) => $score === $bestScore));
            if ($candidates === []) $candidates = range(0, $subs->count() - 1);
            usort($candidates, fn ($a, $b) => abs($a - $expected) <=> abs($b - $expected) ?: $a <=> $b);
            $materialLoads[(int) $candidates[0]]++;
        }

        $counts = array_fill(0, $subs->count(), 1);
        $demand = [];
        foreach ($subs as $subIndex => $sub) {
            $load = $materialLoads[$subIndex];
            $materialFactor = $load === 0 ? 0.35 : min(3.25, $load * 0.65);
            $bloomWeight = match ($this->bloomRank((string) ($sub->bloom_level ?? ''))) {
                1 => 0.00,
                2 => 0.15,
                3 => 0.35,
                4 => 0.65,
                5 => 0.90,
                6 => 1.15,
                default => 0.25,
            };
            $demand[$subIndex] = 1.0 + $materialFactor + $bloomWeight;
        }

        $remaining = count($teachingWeeks) - $subs->count();
        while ($remaining > 0) {
            $bestIndex = 0;
            $bestPriority = -INF;
            foreach ($subs as $subIndex => $_sub) {
                $priority = $demand[$subIndex] / max(1, $counts[$subIndex]);
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestIndex = (int) $subIndex;
                }
            }
            $counts[$bestIndex]++;
            $remaining--;
        }

        $sequence = [];
        foreach ($subs as $subIndex => $sub) {
            for ($i = 0; $i < $counts[$subIndex]; $i++) {
                $sequence[] = $sub;
            }
        }

        return $sequence[$position] ?? $subs->last();
    }

'''
s = s[:start] + new_method + s[end:]
p.write_text(s, encoding='utf-8')

# 3) AI context: when no explicit material mapping exists, provide semantic
# target materials instead of treating every material as equally relevant.
p = Path('app/Services/Rps/RpsAiContextService.php')
s = p.read_text(encoding='utf-8')
needle = """        if ($targetMaterials === [] && Schema::hasColumn('rps_materials', 'rps_sub_cpmk_id')) {
            $targetMaterials = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->where('rps_sub_cpmk_id', $targetSub->id)
                ->orderBy('sequence_no')
                ->limit(10)
                ->pluck('title')
                ->all();
        }

"""
replacement = needle + r'''        if ($targetMaterials === [] && $materials !== []) {
            $targetTokens = $this->semanticTokens((string) $targetSub->description);
            $ranked = collect($materials)
                ->map(function (string $title) use ($targetTokens): array {
                    return [
                        'title' => $title,
                        'score' => count(array_intersect(
                            $targetTokens,
                            $this->semanticTokens($title)
                        )),
                    ];
                })
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sortByDesc('score')
                ->pluck('title')
                ->take(6)
                ->values()
                ->all();

            if ($ranked !== []) {
                $targetMaterials = $ranked;
            }
        }

'''
if needle not in s:
    raise SystemExit('RpsAiContext target material block not found')
s = s.replace(needle, replacement, 1)

marker = """    private function clip(?string $value, int $maxChars): ?string
    {
"""
helper = r'''    private function semanticTokens(string $value): array
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        $stopwords = [
            'yang','dan','atau','untuk','dengan','dalam','pada','dari','ke',
            'serta','melalui','mahasiswa','mampu','dapat','konsep','materi',
            'pembelajaran','dasar','contoh','masalah','permasalahan','sesuai',
        ];

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

'''
if marker not in s:
    raise SystemExit('RpsAiContext helper marker not found')
s = s.replace(marker, helper + marker, 1)
p.write_text(s, encoding='utf-8')
