from pathlib import Path


def ro(text, old, new, label):
    if old not in text:
        raise SystemExit(f"{label}: marker not found")
    return text.replace(old, new, 1)


# RpsAiController
p = Path("app/Http/Controllers/RpsAiController.php")
t = p.read_text(encoding="utf-8")

t = ro(t, """                'cpmk_review',
                'cpl_mapping',""", """                'cpmk_review',
                'bloom_mapping',
                'cpl_mapping',""", "allow bloom")

t = ro(t, """        $context = $contextService->build($record, $version, $data['suggestion_type']);

        $contextHash = hash(""", """        $context = $contextService->build($record, $version, $data['suggestion_type']);

        $providerType = $data['suggestion_type'] === 'bloom_mapping'
            ? 'cpmk_review'
            : $data['suggestion_type'];

        $effectiveInstruction = $data['instruction'] ?? null;
        if ($data['suggestion_type'] === 'bloom_mapping') {
            $bloomInstruction = 'Fokus HANYA pada klasifikasi Taksonomi Bloom untuk setiap CPMK yang sudah ada. '
                .'Jangan mengubah rumusan CPMK, jangan menambah atau menghapus CPMK, dan jangan memetakan CPL. '
                .'Kembalikan tepat satu rekomendasi untuk SETIAP CPMK dengan target_code yang sama, description yang sama persis, '
                .'dan bloom_level C1-C6 yang paling sesuai dengan kata kerja operasional serta tuntutan kognitif rumusannya. '
                .'Gunakan action adapt bila level Bloom perlu diisi atau diubah, dan keep bila level saat ini sudah tepat.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\\n\\n".$bloomInstruction
                : $bloomInstruction;
        }

        $contextHash = hash(""", "effective bloom instruction")

t = ro(t, """            $result = $aiProvider->generate(
                $data['suggestion_type'],
                $context,
                $data['instruction'] ?? null
            );""", """            $result = $aiProvider->generate(
                $providerType,
                $context,
                $effectiveInstruction
            );""", "provider type")

t = ro(t, """        if ($data['suggestion_type'] === 'cpmk_review') {
            $result['payload'] = $this->sanitizeCpmkReviewPayload(
                $result['payload'] ?? [],
                $version
            );
        }
""", """        if ($data['suggestion_type'] === 'cpmk_review') {
            $result['payload'] = $this->sanitizeCpmkReviewPayload(
                $result['payload'] ?? [],
                $version
            );
        } elseif ($data['suggestion_type'] === 'bloom_mapping') {
            $result['payload'] = $this->sanitizeBloomMappingPayload(
                $result['payload'] ?? [],
                $version
            );
        }
""", "sanitize bloom")

t = ro(t, """        if ($data['suggestion_type'] === 'cpmk_review') {
            $actionable = collect($result['payload']['recommendations'] ?? [])
                ->contains(fn ($item) =>
                    is_array($item)
                    && in_array(strtolower((string) ($item['action'] ?? 'keep')), ['adapt', 'add'], true)
                );

            if (! $actionable) {
                DB::table('ai_suggestions')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $version->id,
                    'suggestion_type' => 'cpmk_review',""", """        if (in_array($data['suggestion_type'], ['cpmk_review', 'bloom_mapping'], true)) {
            $actionable = collect($result['payload']['recommendations'] ?? [])
                ->contains(fn ($item) =>
                    is_array($item)
                    && in_array(strtolower((string) ($item['action'] ?? 'keep')), ['adapt', 'add'], true)
                );

            if (! $actionable) {
                DB::table('ai_suggestions')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $version->id,
                    'suggestion_type' => $data['suggestion_type'],""", "auto accept")

t = ro(t, """                return back()->with(
                    'success',
                    'Telaah AI selesai: CPMK sudah memadai dan tidak ada perubahan substantif yang perlu diterapkan.'
                );""", """                return back()->with(
                    'success',
                    $data['suggestion_type'] === 'bloom_mapping'
                        ? 'Pemetaan Bloom AI selesai: level Bloom CPMK yang dianalisis sudah sesuai; tidak ada perubahan yang perlu diterapkan.'
                        : 'Telaah AI selesai: CPMK sudah memadai dan tidak ada perubahan substantif yang perlu diterapkan.'
                );""", "auto accept message")

t = ro(t, """        if (in_array($row->suggestion_type, ['cpmk_review', 'cpl_mapping', 'material_plan', 'sub_cpmk'], true) && $selectedIndices === []) {""", """        if (in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping', 'material_plan', 'sub_cpmk'], true) && $selectedIndices === []) {""", "apply validate")

t = ro(t, """                'cpmk_review' => $this->applyCpmkReview($payload, $selectedIndices, $record, $version, $request->user()->id),
                'cpl_mapping' =>""", """                'cpmk_review' => $this->applyCpmkReview($payload, $selectedIndices, $record, $version, $request->user()->id),
                'bloom_mapping' => $this->applyBloomMapping($payload, $selectedIndices, $version),
                'cpl_mapping' =>""", "match bloom")

t = ro(t, """            if (($result['changed'] ?? 0) < 1 && in_array($row->suggestion_type, ['cpmk_review', 'cpl_mapping', 'material_plan', 'sub_cpmk', 'assessment_plan'], true)) {""", """            if (($result['changed'] ?? 0) < 1 && in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping', 'material_plan', 'sub_cpmk', 'assessment_plan'], true)) {""", "changed list")

start = t.index("    private function sanitizeCpmkReviewPayload(")
end = t.index("    private function resolveWeekMaterial(", start)
methods = '''    private function sanitizeCpmkReviewPayload(
        array $payload,
        object $version
    ): array {
        $current = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->get()
            ->keyBy('code');

        $items = $payload['recommendations'] ?? [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));
            $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));

            if ($action === 'adapt' && $current->has($target)) {
                $existing = $current->get($target);
                $sameDescription = $this->comparableText((string) ($item['description'] ?? ''))
                    === $this->comparableText((string) ($existing->description ?? ''));

                if ($sameDescription) {
                    $items[$index]['action'] = 'keep';
                    $items[$index]['target_code'] = $target;
                    $items[$index]['description'] = $existing->description;
                    $items[$index]['bloom_level'] = $existing->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                    $items[$index]['rationale'] = 'Rumusan CPMK sudah memadai; Bloom dan CPL dipetakan pada tahap terpisah.';
                } else {
                    $items[$index]['target_code'] = $target;
                    $items[$index]['bloom_level'] = $existing->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                }
            }

            if ($action === 'add') {
                $newText = $this->comparableText((string) ($item['description'] ?? ''));
                $duplicate = $current->first(fn ($row) =>
                    $newText !== ''
                    && $this->comparableText((string) ($row->description ?? '')) === $newText
                );

                if ($duplicate) {
                    $items[$index]['action'] = 'keep';
                    $items[$index]['target_code'] = $duplicate->code;
                    $items[$index]['description'] = $duplicate->description;
                    $items[$index]['bloom_level'] = $duplicate->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                    $items[$index]['rationale'] = 'Usulan identik dengan CPMK yang sudah ada.';
                } else {
                    $items[$index]['bloom_level'] = null;
                    $items[$index]['cpl_codes'] = [];
                }
            }
        }

        $payload['summary'] = 'Telaah rumusan CPMK tanpa mengubah level Bloom maupun pemetaan CPL.';
        $payload['recommendations'] = $items;
        return $payload;
    }

    private function sanitizeBloomMappingPayload(array $payload, object $version): array
    {
        $current = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level'])
            ->keyBy('code');

        $byTarget = collect($payload['recommendations'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->keyBy(fn ($item) => $this->normalizeCpmkCode((string) ($item['target_code'] ?? '')));

        $items = [];
        foreach ($current as $code => $existing) {
            $candidate = $byTarget->get($code);
            if (! is_array($candidate)) {
                continue;
            }

            $newBloom = strtoupper(trim((string) ($candidate['bloom_level'] ?? '')));
            if (! in_array($newBloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                continue;
            }

            $oldBloom = strtoupper(trim((string) ($existing->bloom_level ?? '')));
            $same = $oldBloom === $newBloom;

            $items[] = [
                'action' => $same ? 'keep' : 'adapt',
                'target_code' => $existing->code,
                'description' => $existing->description,
                'bloom_level' => $newBloom,
                'cpl_codes' => [],
                'rationale' => trim((string) ($candidate['rationale'] ?? ''))
                    ?: ($same
                        ? 'Level Bloom saat ini sudah sesuai dengan tuntutan kognitif CPMK.'
                        : 'Level Bloom disesuaikan dengan kata kerja operasional dan tuntutan kognitif CPMK.'),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan pemetaan Bloom C1-C6 yang valid. Coba kembali.',
            ]);
        }

        $payload['summary'] = 'Pemetaan Taksonomi Bloom untuk CPMK yang sudah final. Rumusan CPMK dan pemetaan CPL tidak diubah.';
        $payload['recommendations'] = $items;
        return $payload;
    }

'''
t = t[:start] + methods + t[end:]

t = ro(t, """                    'description' => trim((string) ($item['description'] ?? $cpmk->description)),
                    'bloom_level' => ($item['bloom_level'] ?? null) ?: null,
                    'source_type' => 'ai_adapted',""", """                    'description' => trim((string) ($item['description'] ?? $cpmk->description)),
                    'source_type' => 'ai_adapted',""", "remove bloom adapt")

t = ro(t, """                $this->replaceCpmkMappings($cpmk->id, $item['cpl_codes'] ?? [], $scopeCpls);
                $changed++;""", """                $changed++;""", "remove cpl adapt")

t = ro(t, """                    'description' => $description,
                    'bloom_level' => ($item['bloom_level'] ?? null) ?: null,
                    'source_type' => 'ai_added',""", """                    'description' => $description,
                    'bloom_level' => null,
                    'source_type' => 'ai_added',""", "null bloom add")

t = ro(t, """                $this->replaceCpmkMappings($id, $item['cpl_codes'] ?? [], $scopeCpls);
                $changed++;""", """                $changed++;""", "remove cpl add")

marker = "    private function applyCpmkReview(\n"
idx = t.index(marker)
apply_bloom = '''    private function applyBloomMapping(array $payload, array $selectedIndices, object $version): array
    {
        $items = $payload['recommendations'] ?? [];
        $changed = 0;

        foreach ($selectedIndices as $index) {
            $item = $items[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan pemetaan Bloom AI tidak valid.']);
            }
            if (strtolower((string) ($item['action'] ?? 'keep')) === 'keep') {
                continue;
            }

            $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
            $bloom = strtoupper(trim((string) ($item['bloom_level'] ?? '')));
            if (! in_array($bloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                throw ValidationException::withMessages(['ai' => 'Level Bloom '.$target.' tidak valid.']);
            }

            $cpmk = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $target)
                ->first();
            if (! $cpmk) {
                throw ValidationException::withMessages(['ai' => 'CPMK target '.$target.' tidak ditemukan.']);
            }

            if (strtoupper(trim((string) ($cpmk->bloom_level ?? ''))) === $bloom) {
                continue;
            }

            DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                'bloom_level' => $bloom,
                'updated_at' => now(),
            ]);
            $changed++;
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} level Bloom CPMK berhasil dipetakan.",
        ];
    }

'''
t = t[:idx] + apply_bloom + t[idx:]
p.write_text(t, encoding="utf-8")


# Context service
p = Path("app/Services/Rps/RpsAiContextService.php")
t = p.read_text(encoding="utf-8")
marker = """            'cpmk_review' => $base + [
"""
ctx = """            'bloom_mapping' => $base + [
                'cpmks' => array_map(
                    fn (array $cpmk): array => [
                        'code' => $cpmk['code'],
                        'description' => $this->clip($cpmk['description'] ?? null, 900),
                        'bloom_level' => $cpmk['bloom_level'],
                    ],
                    $full['cpmks']
                ),
            ],

"""
if marker not in t:
    raise SystemExit("context marker not found")
t = t.replace(marker, ctx + marker, 1)
p.write_text(t, encoding="utf-8")


# Frontend
p = Path("resources/js/pages/rps/show.tsx")
t = p.read_text(encoding="utf-8")

t = ro(t, """    const cplMappingSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'cpl_mapping',
    );
    const cpmkAiSuggestions = [...cpmkReviewSuggestions, ...cplMappingSuggestions];""", """    const bloomMappingSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'bloom_mapping',
    );
    const cplMappingSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'cpl_mapping',
    );
    const cpmkAiSuggestions = [...cpmkReviewSuggestions, ...bloomMappingSuggestions, ...cplMappingSuggestions];""", "filters")

t = ro(t, 'label="Telaah CPMK + Bloom AI"', 'label="Telaah CPMK AI"', "rename label")

button = """                                                <SectionAiButton
                                                    label="Pemetaan CPMK → CPL AI"
"""
bloom_button = """                                                <SectionAiButton
                                                    label="Pemetaan Bloom AI"
                                                    busy={aiBusyType === 'bloom_mapping'}
                                                    disabled={!ai.configured || cpmks.length === 0}
                                                    onClick={() => generateAi('bloom_mapping')}
                                                    suggestions={bloomMappingSuggestions}
                                                    rpsId={rps.id}
                                                />
"""
if button not in t:
    raise SystemExit("CPL mapping button marker not found")
t = t.replace(button, bloom_button + button, 1)

add_start = t.index("function DocumentCpmkAdd")
add_end = t.index("function DocumentSubCpmkAdd", add_start)
block = t[add_start:add_end]
select_start = block.find("""                <select
                    value={form.data.bloom_level}""")
if select_start < 0:
    raise SystemExit("Add CPMK Bloom select not found")
select_end = block.find("                </select>\n", select_start)
if select_end < 0:
    raise SystemExit("Add CPMK Bloom select end not found")
select_end += len("                </select>\n")
block = block[:select_start] + block[select_end:]
t = t[:add_start] + block + t[add_end:]

t = ro(t, """        'cpmk_review',
        'cpl_mapping',""", """        'cpmk_review',
        'bloom_mapping',
        'cpl_mapping',""", "selectable")

t = ro(t, """    const sourceItems = suggestion.suggestion_type === 'cpmk_review'
        ? safeList(payload.recommendations)
        : suggestion.suggestion_type === 'cpl_mapping'""", """    const sourceItems = ['cpmk_review', 'bloom_mapping'].includes(suggestion.suggestion_type)
        ? safeList(payload.recommendations)
        : suggestion.suggestion_type === 'cpl_mapping'""", "source")

t = ro(t, """        cpmk_review: 'Telaah CPMK',
        cpl_mapping:""", """        cpmk_review: 'Telaah CPMK',
        bloom_mapping: 'Pemetaan Bloom CPMK',
        cpl_mapping:""", "labels")

t = ro(t, """    const countText = suggestion.suggestion_type === 'cpmk_review'
        ? `${safeList(payload.recommendations).length} rekomendasi CPMK`
        : suggestion.suggestion_type === 'cpl_mapping'""", """    const countText = suggestion.suggestion_type === 'cpmk_review'
        ? `${safeList(payload.recommendations).length} rekomendasi CPMK`
        : suggestion.suggestion_type === 'bloom_mapping'
          ? `${safeList(payload.recommendations).length} rekomendasi Bloom`
        : suggestion.suggestion_type === 'cpl_mapping'""", "count")

preview_marker = """    if (type === 'cpmk_review') {
"""
bloom_preview = """    if (type === 'bloom_mapping') {
        return (
            <div className="mt-3 space-y-2">
                {safeList(payload.recommendations).map((item: any, index: number) => {
                    const action = safeText(item?.action, 'keep').toLowerCase();
                    const actionable = action !== 'keep';
                    return (
                        <div key={index} className="rounded-lg bg-white p-3 text-xs">
                            <div className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-indigo-700"
                                    disabled={!actionable}
                                    checked={actionable && selectedIndices.includes(index)}
                                    onChange={() => actionable && onToggle?.(index)}
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="font-bold text-slate-800">
                                        {safeText(item?.target_code)} → {safeText(item?.bloom_level)}
                                    </div>
                                    <div className="mt-1 leading-5 text-slate-600">{safeText(item?.description)}</div>
                                    <div className="mt-1 text-slate-400">{safeText(item?.rationale, '')}</div>
                                    {!actionable && <div className="mt-1 text-[10px] font-bold text-emerald-600">Level Bloom sudah sesuai.</div>}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }

"""
if preview_marker not in t:
    raise SystemExit("preview marker not found")
t = t.replace(preview_marker, bloom_preview + preview_marker, 1)
p.write_text(t, encoding="utf-8")
