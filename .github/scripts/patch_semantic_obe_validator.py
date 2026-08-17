from pathlib import Path

# Backend semantic validator
p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text()

s = s.replace(
"""        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id']);
""",
"""        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'description', 'bloom_level']);
""",
1)

s = s.replace(
"""        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id']);
""",
"""        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'description', 'bloom_level']);
""",
1)

old = """        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->count();
"""
new = """        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'title']);
        $materialCount = $materials->count();
"""
assert old in s, 'materials query marker not found'
s = s.replace(old, new, 1)

marker = """        $cplMessage = $scopeCplCount === 0
"""
assert marker in s, 'semantic insertion marker not found'
semantic = r'''        // --- Validator akademik/semantik -------------------------------------
        // Validator ini hanya memberi peringatan. Rumusan dosen tidak pernah
        // diubah otomatis hanya karena pemeriksaan semantik.
        $cpmkById = $cpmks->keyBy(fn ($item) => (string) $item->id);
        $subById = $subCpmks->keyBy(fn ($item) => (string) $item->id);

        $subMappings = ($cpmkIds->isEmpty() || $subCpmkIds->isEmpty())
            ? collect()
            : DB::table('rps_cpmk_subcpmks')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->get(['rps_cpmk_id', 'rps_sub_cpmk_id']);

        $bloomViolations = collect();
        foreach ($subMappings as $mapping) {
            $parent = $cpmkById->get((string) $mapping->rps_cpmk_id);
            $child = $subById->get((string) $mapping->rps_sub_cpmk_id);
            if (! $parent || ! $child) continue;

            $parentRank = $this->bloomRank($parent->bloom_level ?? null);
            $childRank = $this->bloomRank($child->bloom_level ?? null);
            if ($parentRank !== null && $childRank !== null && $childRank > $parentRank) {
                $bloomViolations->push([
                    'cpmk_id' => (string) $parent->id,
                    'cpmk_code' => (string) $parent->code,
                    'cpmk_bloom' => (string) $parent->bloom_level,
                    'sub_cpmk_id' => (string) $child->id,
                    'sub_cpmk_code' => (string) $child->code,
                    'sub_cpmk_bloom' => (string) $child->bloom_level,
                ]);
            }
        }
        $bloomHierarchyAligned = $bloomViolations->isEmpty();

        $duplicateMaterials = collect();
        for ($i = 0; $i < $materials->count(); $i++) {
            for ($j = $i + 1; $j < $materials->count(); $j++) {
                $a = $materials[$i];
                $b = $materials[$j];
                if ($this->semanticNearDuplicate((string) $a->title, (string) $b->title)) {
                    $duplicateMaterials->push([
                        'first_id' => (string) $a->id,
                        'first' => trim((string) $a->title),
                        'second_id' => (string) $b->id,
                        'second' => trim((string) $b->title),
                    ]);
                }
            }
        }
        $materialQualityAligned = $duplicateMaterials->isEmpty();

        $assessmentLinks = $assessmentIds->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy(fn ($item) => (string) $item->assessment_id);

        $assessmentSemanticIssues = collect();
        foreach ($nonExamAssessments as $assessment) {
            $linkedSubIds = collect($assessmentLinks->get((string) $assessment->id, []))
                ->pluck('rps_sub_cpmk_id')->map('strval')->unique()->values();
            if ($linkedSubIds->isEmpty()) continue;

            $text = trim((string) ($assessment->name ?? '').' '.(string) ($assessment->description ?? ''));
            $scores = $subCpmks->mapWithKeys(fn ($sub) => [
                (string) $sub->id => $this->semanticSimilarity($text, (string) $sub->description),
            ]);
            $bestSubId = (string) ($scores->sortDesc()->keys()->first() ?? '');
            $bestScore = (float) $scores->get($bestSubId, 0);
            $explicitCodes = $this->explicitSubCpmkNumbers($text);

            foreach ($linkedSubIds as $linkedSubId) {
                $linkedSub = $subById->get((string) $linkedSubId);
                if (! $linkedSub) continue;

                $linkedScore = (float) $scores->get((string) $linkedSubId, 0);
                $linkedNumber = $this->codeNumber((string) $linkedSub->code);
                $explicitMismatch = $explicitCodes !== []
                    && $linkedNumber !== null
                    && ! in_array($linkedNumber, $explicitCodes, true);
                $clearlyCloserElsewhere = $bestSubId !== ''
                    && $bestSubId !== (string) $linkedSubId
                    && $bestScore >= 0.34
                    && $linkedScore < 0.22
                    && ($bestScore - $linkedScore) >= 0.20;

                if ($explicitMismatch || $clearlyCloserElsewhere) {
                    $bestSub = $subById->get($bestSubId);
                    $assessmentSemanticIssues->push([
                        'assessment_id' => (string) $assessment->id,
                        'assessment_name' => trim((string) $assessment->name),
                        'linked_sub_id' => (string) $linkedSub->id,
                        'linked_sub_code' => (string) $linkedSub->code,
                        'suggested_sub_id' => $bestSub?->id ? (string) $bestSub->id : null,
                        'suggested_sub_code' => $bestSub?->code ? (string) $bestSub->code : null,
                        'reason' => $explicitMismatch ? 'explicit_sub_reference' : 'semantic_distance',
                        'linked_score' => round($linkedScore, 3),
                        'best_score' => round($bestScore, 3),
                    ]);
                }
            }
        }
        $assessmentSemanticIssues = $assessmentSemanticIssues
            ->unique(fn ($item) => $item['assessment_id'].'|'.$item['linked_sub_id'])
            ->values();
        $assessmentSemanticsAligned = $assessmentSemanticIssues->isEmpty();

        $taskRows = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'title', 'assessment_id', 'due_week']);
        $assessmentById = $assessments->keyBy(fn ($item) => (string) $item->id);
        $rtmSemanticIssues = collect();
        foreach ($taskRows as $task) {
            if (! filled($task->assessment_id ?? null)) continue;
            $assessment = $assessmentById->get((string) $task->assessment_id);
            if (! $assessment) continue;

            $taskCore = $this->assessmentCoreLabel((string) $task->title);
            $assessmentCore = $this->assessmentCoreLabel((string) $assessment->name);
            if ($taskCore === '' || $assessmentCore === '') continue;

            $similarity = $this->semanticSimilarity($taskCore, $assessmentCore);
            if ($similarity < 0.34) {
                $rtmSemanticIssues->push([
                    'task_id' => (string) $task->id,
                    'task_code' => (string) $task->code,
                    'task_title' => trim((string) $task->title),
                    'week' => (int) ($task->due_week ?? 0),
                    'assessment_id' => (string) $assessment->id,
                    'assessment_name' => trim((string) $assessment->name),
                    'similarity' => round($similarity, 3),
                ]);
            }
        }
        $rtmSemanticsAligned = $rtmSemanticIssues->isEmpty();

        $weeklyMaterialIssues = collect();
        foreach ($teachingWeeks as $week) {
            $currentSubId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $materialText = trim((string) ($week->material_text ?? ''));
            if (! $currentSubId || $materialText === '' || ! $subById->has($currentSubId)) continue;

            $scores = $subCpmks->mapWithKeys(fn ($sub) => [
                (string) $sub->id => $this->semanticSimilarity($materialText, (string) $sub->description),
            ]);
            $bestSubId = (string) ($scores->sortDesc()->keys()->first() ?? '');
            $bestScore = (float) $scores->get($bestSubId, 0);
            $currentScore = (float) $scores->get($currentSubId, 0);

            if (
                $bestSubId !== ''
                && $bestSubId !== $currentSubId
                && $bestScore >= 0.42
                && $currentScore < 0.18
                && ($bestScore - $currentScore) >= 0.28
            ) {
                $currentSub = $subById->get($currentSubId);
                $bestSub = $subById->get($bestSubId);
                $weeklyMaterialIssues->push([
                    'week' => (int) $week->week_number,
                    'material' => $materialText,
                    'current_sub_code' => (string) ($currentSub?->code ?? ''),
                    'suggested_sub_code' => (string) ($bestSub?->code ?? ''),
                    'current_score' => round($currentScore, 3),
                    'best_score' => round($bestScore, 3),
                ]);
            }
        }
        $weeklyMaterialSemanticsAligned = $weeklyMaterialIssues->isEmpty();

'''
s = s.replace(marker, semantic + marker, 1)

s = s.replace(
"""                'done' => $materials > 0,
                'message' => "{$materials} bahan kajian.",
""",
"""                'done' => $materialCount > 0,
                'message' => "{$materialCount} bahan kajian.",
""",
1)

# Add academic checks after Sub-CPMK card.
needle = """            [
                'key' => 'materials',
                'label' => 'Bahan Kajian',
"""
assert needle in s, 'materials check marker not found'
academic_checks = r'''            [
                'key' => 'bloom_hierarchy',
                'label' => 'Hierarki Bloom',
                'done' => $bloomHierarchyAligned,
                'message' => $bloomHierarchyAligned
                    ? 'Level Bloom CPMK dan Sub-CPMK konsisten.'
                    : (($first = $bloomViolations->first())
                        ? $first['sub_cpmk_code'].' '.$first['sub_cpmk_bloom'].' melampaui '.$first['cpmk_code'].' '.$first['cpmk_bloom'].'.'
                        : 'Ada hierarki Bloom yang perlu diperiksa.'),
                'details' => [
                    'violations' => $bloomViolations->all(),
                ],
            ],
'''
s = s.replace(needle, academic_checks + needle, 1)

# Add material quality card immediately after existing Bahan Kajian card.
needle = """            [
                'key' => 'weeks',
                'label' => '16 Pertemuan',
"""
assert needle in s, 'weeks check marker not found'
material_check = r'''            [
                'key' => 'material_quality',
                'label' => 'Kualitas Bahan Kajian',
                'done' => $materialQualityAligned,
                'message' => $materialQualityAligned
                    ? 'Tidak ada bahan kajian yang duplikat.'
                    : (($pair = $duplicateMaterials->first())
                        ? 'Bahan kajian mirip: '.$pair['first'].' ↔ '.$pair['second'].'.'
                        : 'Ada bahan kajian yang perlu dirapikan.'),
                'details' => [
                    'duplicates' => $duplicateMaterials->all(),
                ],
            ],
            [
                'key' => 'weekly_material_semantics',
                'label' => 'Kesesuaian Materi per Pekan',
                'done' => $weeklyMaterialSemanticsAligned,
                'message' => $weeklyMaterialSemanticsAligned
                    ? 'Materi pekan selaras dengan Sub-CPMK.'
                    : (($issue = $weeklyMaterialIssues->first())
                        ? 'Pekan '.$issue['week'].': materi lebih dekat ke '.$issue['suggested_sub_code'].' daripada '.$issue['current_sub_code'].'.'
                        : 'Ada materi pekan yang perlu ditelaah.'),
                'details' => [
                    'issues' => $weeklyMaterialIssues->all(),
                ],
            ],
'''
s = s.replace(needle, material_check + needle, 1)

# Add assessment semantic check before chain consistency.
needle = """            [
                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
"""
assert needle in s, 'assessment chain marker not found'
assessment_check = r'''            [
                'key' => 'assessment_semantics',
                'label' => 'Kesesuaian Asesmen',
                'done' => $assessmentSemanticsAligned,
                'message' => $assessmentSemanticsAligned
                    ? 'Rumusan asesmen selaras dengan Sub-CPMK.'
                    : (($issue = $assessmentSemanticIssues->first())
                        ? $issue['assessment_name'].': tag '.$issue['linked_sub_code'].' perlu ditelaah'.($issue['suggested_sub_code'] ? ' (lebih dekat ke '.$issue['suggested_sub_code'].').' : '.')
                        : 'Ada tag asesmen yang perlu ditelaah.'),
                'details' => [
                    'issues' => $assessmentSemanticIssues->all(),
                ],
            ],
'''
s = s.replace(needle, assessment_check + needle, 1)

# Add RTM semantic check before existing RTM technical card.
needle = """            [
                'key' => 'rtm',
                'label' => 'RTM',
"""
assert needle in s, 'rtm check marker not found'
rtm_check = r'''            [
                'key' => 'rtm_semantics',
                'label' => 'Kesesuaian RTM',
                'done' => $rtmSemanticsAligned,
                'message' => $rtmSemanticsAligned
                    ? 'Judul RTM selaras dengan asesmen induk.'
                    : (($issue = $rtmSemanticIssues->first())
                        ? $issue['task_code'].' tidak selaras dengan asesmen induknya.'
                        : 'Ada RTM yang perlu ditelaah.'),
                'details' => [
                    'issues' => $rtmSemanticIssues->all(),
                ],
            ],
'''
s = s.replace(needle, rtm_check + needle, 1)

# Add helper methods before validateAndPersist.
needle = """    public function validateAndPersist(string $versionId): array
"""
assert needle in s, 'method insertion marker not found'
helpers = r'''    private function bloomRank(?string $level): ?int
    {
        $value = strtoupper(trim((string) $level));
        if (preg_match('/^C([1-6])$/', $value, $match) !== 1) return null;

        return (int) $match[1];
    }

    private function codeNumber(string $code): ?int
    {
        return preg_match('/(\d+)/', $code, $match) === 1 ? (int) $match[1] : null;
    }

    private function explicitSubCpmkNumbers(string $text): array
    {
        preg_match_all('/sub\s*[- ]?cpmk\s*[- ]?(\d{1,2})/iu', $text, $matches);

        return collect($matches[1] ?? [])->map('intval')->unique()->values()->all();
    }

    private function assessmentCoreLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^(?:quiz|kuis|assignment|tugas|project|proyek|praktikum|presentasi)\s*[- ]*\d+\s*[-–—:]*/iu', '', $value) ?? $value;

        return trim($value);
    }

    private function semanticNearDuplicate(string $a, string $b): bool
    {
        $aNorm = $this->semanticNormalized($a);
        $bNorm = $this->semanticNormalized($b);
        if ($aNorm === '' || $bNorm === '') return false;
        if ($aNorm === $bNorm) return true;

        $short = mb_strlen($aNorm) <= mb_strlen($bNorm) ? $aNorm : $bNorm;
        $long = $short === $aNorm ? $bNorm : $aNorm;
        if (mb_strlen($short) >= 18 && str_contains($long, $short)) return true;

        $aTokens = $this->semanticTokens($a);
        $bTokens = $this->semanticTokens($b);
        if (count($aTokens) < 3 || count($bTokens) < 3) return false;

        $intersection = count(array_intersect($aTokens, $bTokens));
        return $intersection / max(1, min(count($aTokens), count($bTokens))) >= 0.78;
    }

    private function semanticSimilarity(string $a, string $b): float
    {
        $aTokens = $this->semanticTokens($a);
        $bTokens = $this->semanticTokens($b);
        if ($aTokens === [] || $bTokens === []) return 0.0;

        $intersection = count(array_intersect($aTokens, $bTokens));
        return $intersection / max(1, min(count($aTokens), count($bTokens)));
    }

    private function semanticNormalized(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['–', '—'], '-', $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function semanticTokens(string $value): array
    {
        $value = $this->semanticNormalized($value);
        $value = preg_replace('/\b(?:mengimplementasikan|implementasikan|implementasi)\b/u', ' implementasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:menganalisis|analisis)\b/u', ' analisis ', $value) ?? $value;
        $value = preg_replace('/\b(?:mengevaluasi|evaluasi)\b/u', ' evaluasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:pemrograman|memprogram|program)\b/u', ' program ', $value) ?? $value;
        $value = preg_replace('/\b(?:membangun|bangun)\b/u', ' bangun ', $value) ?? $value;
        $value = preg_replace('/\b(?:menggunakan|penggunaan|gunakan)\b/u', ' guna ', $value) ?? $value;
        $value = preg_replace('/\b(?:memvisualisasikan|visualisasi)\b/u', ' visualisasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:merancang|rancang)\b/u', ' rancang ', $value) ?? $value;
        $value = preg_replace('/\b(?:menjelaskan|penjelasan)\b/u', ' jelas ', $value) ?? $value;
        $value = preg_replace('/\b(?:mengidentifikasi|identifikasi)\b/u', ' identifikasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:melatih|pelatihan|training)\b/u', ' latih ', $value) ?? $value;

        $stop = [
            'dan','atau','yang','untuk','dengan','pada','dalam','dari','ke','serta','melalui','sesuai','tentang','secara','berbagai',
            'mahasiswa','kemampuan','ketercapaian','sub','cpmk','jaringan','syaraf','tiruan','model','nilai','tugas','akhir','awal',
            'mis','seperti','suatu','ini','itu','dapat','mampu','hasil','metode','teknik','praktik','praktis','data','bidang','lebih',
            'quiz','kuis','assignment','project','proyek','rtm','pekan','komponen',
        ];

        return collect(preg_split('/\s+/u', $value) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stop, true) && ! ctype_digit($token))
            ->unique()->values()->all();
    }

'''
s = s.replace(needle, helpers + needle, 1)
p.write_text(s)

# Frontend validator navigation metadata
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text()
needle = """    sub_cpmk: { label: 'Perbaiki Sub-CPMK', target: 'validator-target-cpmk' },
    materials: { label: 'Perbaiki Bahan Kajian', target: 'validator-target-materials' },
"""
replacement = """    sub_cpmk: { label: 'Perbaiki Sub-CPMK', target: 'validator-target-cpmk' },
    bloom_hierarchy: { label: 'Perbaiki Bloom', target: 'validator-target-cpmk' },
    materials: { label: 'Perbaiki Bahan Kajian', target: 'validator-target-materials' },
    material_quality: { label: 'Rapikan Bahan Kajian', target: 'validator-target-materials' },
    weekly_material_semantics: { label: 'Periksa Materi Pekan', target: 'validator-target-weeks' },
"""
assert needle in s, 'frontend cpmk/material metadata marker not found'
s = s.replace(needle, replacement, 1)

needle = """    subcpmk_assessed: { label: 'Atur Pertemuan', target: 'validator-target-weeks' },
    assessment_chain_sync: { label: 'Periksa RTM', target: 'validator-target-rtm' },
"""
replacement = """    subcpmk_assessed: { label: 'Atur Pertemuan', target: 'validator-target-weeks' },
    assessment_semantics: { label: 'Periksa Tag Asesmen', target: 'validator-target-assessment' },
    assessment_chain_sync: { label: 'Periksa RTM', target: 'validator-target-rtm' },
"""
assert needle in s, 'frontend assessment metadata marker not found'
s = s.replace(needle, replacement, 1)

needle = """    weekly_assessment_evidence: { label: 'Periksa RTM', target: 'validator-target-rtm' },
    rtm: { label: 'Perbaiki RTM', target: 'validator-target-rtm' },
"""
replacement = """    weekly_assessment_evidence: { label: 'Periksa RTM', target: 'validator-target-rtm' },
    rtm_semantics: { label: 'Periksa RTM', target: 'validator-target-rtm' },
    rtm: { label: 'Perbaiki RTM', target: 'validator-target-rtm' },
"""
assert needle in s, 'frontend rtm metadata marker not found'
s = s.replace(needle, replacement, 1)
p.write_text(s)
