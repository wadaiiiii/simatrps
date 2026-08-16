from pathlib import Path

# --- UI ---
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')

repls = [
    (
        'className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm hover:border-teal-200 hover:text-teal-700"',
        'className="inline-flex items-center gap-2 rounded-xl border border-teal-700 bg-teal-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-teal-700"',
        'Edit Informasi RPS style',
    ),
    (
        'className="cursor-pointer rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600"',
        'className="cursor-pointer rounded-lg border border-violet-200 bg-violet-100 px-3 py-2 text-xs font-bold text-violet-800 transition hover:bg-violet-200"',
        'Preferensi AI style',
    ),
    (
        'className="rounded border border-violet-200 bg-violet-50 px-1.5 py-1 text-[9px] font-bold text-violet-700 disabled:opacity-40"',
        'title="Buat rekomendasi AI khusus untuk pekan ini"\n                        className="rounded-lg border border-violet-700 bg-violet-600 px-2 py-1.5 text-[10px] font-extrabold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40"',
        'AI Pekan style',
    ),
    (
        "{aiBusy ? 'AI...' : '✨ AI'}",
        "{aiBusy ? 'AI...' : '✨ AI Pekan'}",
        'AI Pekan label',
    ),
]
for old, new, label in repls:
    if old not in s:
        raise SystemExit(f'{label} target not found')
    s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# --- Smart Draft weekly alignment ---
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

old = """        $updated = 0;
        $subCount = $subCpmks->count();
        $weekCount = count(self::TEACHING_WEEKS);
        $rows = [];
        $updateColumns = [];

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
            $current = $currentWeeks->get($weekNumber);

            if (! $current) {
                continue;
            }

            $subIndex = min(
                $subCount - 1,
                (int) floor(($position * $subCount) / $weekCount)
            );

            $sub = $subCpmks[$subIndex];

            $linkedMaterials = $materials
                ->where('rps_sub_cpmk_id', $sub->id)
                ->values();

            if ($linkedMaterials->isEmpty()) {
                $globalMaterials = $materials
                    ->whereNull('rps_sub_cpmk_id')
                    ->values();

                $material = $globalMaterials->isNotEmpty()
                    ? $globalMaterials[$position % $globalMaterials->count()]
                    : null;

                $materialText = $material?->title;
            } else {
                $materialText = $linkedMaterials
                    ->take(3)
                    ->pluck('title')
                    ->implode('; ');
            }
"""
new = """        $updated = 0;
        $rows = [];
        $updateColumns = [];

        // Susun 14 minggu sebagai blok Sub-CPMK yang berurutan. Materi tidak
        // lagi diputar secara global; setiap materi harus relevan dengan
        // Sub-CPMK pada minggu tersebut. Jika satu Sub-CPMK memerlukan minggu
        // tambahan setelah seluruh materinya terpakai, label Pendalaman dibuat
        // eksplisit agar tidak terlihat sebagai pengulangan tanpa alasan.
        $teachingSequence = $this->buildTeachingSequence($subCpmks, $materials);

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
            $current = $currentWeeks->get($weekNumber);

            if (! $current) {
                continue;
            }

            $slot = $teachingSequence[$position] ?? null;
            if (! $slot) {
                continue;
            }

            $sub = $slot['sub'];
            $materialText = $slot['material'];
"""
if old not in s:
    raise SystemExit('SmartDraft weekly allocation block not found')
s = s.replace(old, new, 1)

old = """                if ($legacyGeneratedIndicator) {
                    $merged[$key] = $value;
                    if ($existing !== $value) {
                        $changed = true;
                    }
                    continue;
                }

                if ($mode === 'overwrite') {
"""
new = """                if ($legacyGeneratedIndicator) {
                    $merged[$key] = $value;
                    if ($existing !== $value) {
                        $changed = true;
                    }
                    continue;
                }

                // Nilai 0 pada frekuensi hasil generator lama bukan estimasi
                // pembelajaran yang valid. Lengkapi RPS Otomatis boleh
                // memperbaikinya menjadi minimal 1 tanpa menyentuh angka
                // manual lain yang sudah positif.
                $sessionField = in_array($key, [
                    'face_to_face_sessions',
                    'structured_task_sessions',
                    'independent_study_sessions',
                ], true);
                if ($sessionField && (int) $existing < 1 && (int) $value >= 1) {
                    $merged[$key] = $value;
                    $changed = true;
                    continue;
                }

                if ($mode === 'overwrite') {
"""
if old not in s:
    raise SystemExit('SmartDraft merge block not found')
s = s.replace(old, new, 1)

marker = """    private function indicatorFromSubCpmk(string $description): string
    {
"""
helpers = r'''    private function buildTeachingSequence($subCpmks, $materials): array
    {
        $subs = $subCpmks->values()->take(count(self::TEACHING_WEEKS));
        if ($subs->isEmpty()) {
            return [];
        }

        $materialRows = $materials->values();
        $relevant = [];

        foreach ($subs as $subIndex => $sub) {
            $ranked = $materialRows
                ->map(function ($material, $materialIndex) use ($sub): array {
                    return [
                        'title' => trim((string) ($material->title ?? '')),
                        'index' => (int) $materialIndex,
                        'score' => $this->materialRelevanceScore(
                            (string) ($sub->description ?? ''),
                            (string) ($material->title ?? '')
                        ),
                        'linked' => filled($material->rps_sub_cpmk_id ?? null)
                            && (string) $material->rps_sub_cpmk_id === (string) $sub->id,
                    ];
                })
                ->filter(fn (array $item) => $item['title'] !== '' && ($item['linked'] || $item['score'] > 0))
                ->sort(function (array $a, array $b): int {
                    if ($a['linked'] !== $b['linked']) {
                        return $a['linked'] ? -1 : 1;
                    }
                    if ($a['score'] !== $b['score']) {
                        return $b['score'] <=> $a['score'];
                    }
                    return $a['index'] <=> $b['index'];
                })
                ->pluck('title')
                ->unique()
                ->values()
                ->all();

            $relevant[$subIndex] = $ranked;
        }

        // Setiap Sub-CPMK memperoleh minimal satu minggu. Sisa minggu terlebih
        // dahulu diberikan kepada Sub-CPMK yang masih memiliki bahan kajian
        // relevan yang belum digunakan; setelah itu baru untuk pendalaman.
        $counts = array_fill(0, $subs->count(), 1);
        $remaining = count(self::TEACHING_WEEKS) - $subs->count();

        while ($remaining > 0) {
            $allocated = false;
            foreach ($subs as $subIndex => $_sub) {
                if ($remaining <= 0) break;
                if ($counts[$subIndex] < max(1, count($relevant[$subIndex] ?? []))) {
                    $counts[$subIndex]++;
                    $remaining--;
                    $allocated = true;
                }
            }
            if (! $allocated) break;
        }

        $cursor = 0;
        while ($remaining > 0) {
            $counts[$cursor % $subs->count()]++;
            $remaining--;
            $cursor++;
        }

        $sequence = [];
        foreach ($subs as $subIndex => $sub) {
            $titles = $relevant[$subIndex] ?? [];
            for ($occurrence = 0; $occurrence < $counts[$subIndex]; $occurrence++) {
                $material = null;
                if (isset($titles[$occurrence])) {
                    $material = $titles[$occurrence];
                } elseif ($titles !== []) {
                    $material = 'Pendalaman dan latihan: '.$titles[count($titles) - 1];
                }

                $sequence[] = [
                    'sub' => $sub,
                    'material' => $material,
                ];
            }
        }

        return array_slice($sequence, 0, count(self::TEACHING_WEEKS));
    }

    private function materialRelevanceScore(string $subDescription, string $materialTitle): int
    {
        $subTokens = $this->academicTokens($subDescription);
        $materialTokens = $this->academicTokens($materialTitle);

        if ($subTokens === [] || $materialTokens === []) {
            return 0;
        }

        return count(array_intersect($subTokens, $materialTokens));
    }

    private function academicTokens(string $value): array
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        $stop = [
            'mahasiswa','mampu','dapat','dan','atau','yang','dalam','pada','untuk',
            'dengan','serta','secara','melalui','konsep','prinsip','dasar','contoh',
            'permasalahan','masalah','kehidupan','terkait','sesuai',
        ];

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stop, true))
            ->unique()
            ->values()
            ->all();
    }

'''
if marker not in s:
    raise SystemExit('SmartDraft helper marker not found')
s = s.replace(marker, helpers + marker, 1)
p.write_text(s, encoding='utf-8')

# --- AI week guard ---
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')

old = """Pastikan `assessment_criteria` menilai kualitas bukti tersebut dan `assessment_method` konsisten dengan asesmen yang tersedia.
PROMPT;
"""
new = """Pastikan `assessment_criteria` menilai kualitas bukti tersebut dan `assessment_method` konsisten dengan asesmen yang tersedia.
Materi minggu WAJIB selaras dengan `target_sub_cpmk`. Prioritaskan `target_materials` bila tersedia. Jangan memilih bahan kajian hanya karena urutannya berdekatan, dan jangan mengulang bahan kajian yang tidak relevan dengan Sub-CPMK target. Jika perlu pengulangan untuk penguatan, nyatakan eksplisit sebagai pendalaman/latihan.
PROMPT;
"""
if old not in s:
    raise SystemExit('AI week prompt target not found')
s = s.replace(old, new, 1)

old = """        $resolvedMaterial = $this->resolveWeekMaterial(
            (string) ($item['material'] ?? ''),
            $context['materials'] ?? [],
            (string) ($context['target_sub_cpmk']['description'] ?? '')
        );
"""
new = """        $allowedMaterials = ! empty($context['target_materials'] ?? [])
            ? $context['target_materials']
            : ($context['materials'] ?? []);

        $resolvedMaterial = $this->resolveWeekMaterial(
            (string) ($item['material'] ?? ''),
            $allowedMaterials,
            (string) ($context['target_sub_cpmk']['description'] ?? '')
        );
"""
if old not in s:
    raise SystemExit('AI material resolution target not found')
s = s.replace(old, new, 1)

old = """            'face_to_face_sessions' => (int) ($weekly->face_to_face_sessions ?? 1),
            'student_assignment' => $item['student_assignment'] ?? null,
            'structured_task_sessions' => (int) ($weekly->structured_task_sessions ?? 1),
"""
new = """            'face_to_face_sessions' => max(1, (int) ($weekly->face_to_face_sessions ?? 1)),
            'student_assignment' => $item['student_assignment'] ?? null,
            'structured_task_sessions' => max(1, (int) ($weekly->structured_task_sessions ?? 1)),
"""
if old not in s:
    raise SystemExit('AI sessions part 1 target not found')
s = s.replace(old, new, 1)

old = """            'independent_study_sessions' => (int) ($weekly->independent_study_sessions ?? 1),
"""
new = """            'independent_study_sessions' => max(1, (int) ($weekly->independent_study_sessions ?? 1)),
"""
if old not in s:
    raise SystemExit('AI sessions part 2 target not found')
s = s.replace(old, new, 1)

old = """            if ($overwrite || ! filled($weekly->{$key} ?? null)) {
                $updates[$key] = $value;
            }
"""
new = """            $sessionField = in_array($key, [
                'face_to_face_sessions',
                'structured_task_sessions',
                'independent_study_sessions',
            ], true);

            if (
                $overwrite
                || ! filled($weekly->{$key} ?? null)
                || ($sessionField && (int) ($weekly->{$key} ?? 0) < 1)
            ) {
                $updates[$key] = $value;
            }
"""
if old not in s:
    raise SystemExit('AI update session guard target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
