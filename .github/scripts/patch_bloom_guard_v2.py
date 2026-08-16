from pathlib import Path

p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')


def replace_once(old: str, new: str, label: str):
    global s
    if old not in s:
        raise SystemExit(f'{label} target not found')
    s = s.replace(old, new, 1)

# 1. Add stronger provider-independent instructions for Sub-CPMK Bloom classification.
old = '''        if ($data['suggestion_type'] === 'bloom_mapping') {
            $bloomInstruction = 'Fokus HANYA pada klasifikasi Taksonomi Bloom untuk setiap CPMK yang sudah ada. '
                .'Jangan mengubah rumusan CPMK, jangan menambah atau menghapus CPMK, dan jangan memetakan CPL. '
                .'Kembalikan tepat satu rekomendasi untuk SETIAP CPMK dengan target_code yang sama, description yang sama persis, '
                .'dan bloom_level C1-C6 yang paling sesuai dengan kata kerja operasional serta tuntutan kognitif rumusannya. '
                .'Gunakan action adapt bila level Bloom perlu diisi atau diubah, dan keep bila level saat ini sudah tepat.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\\n\\n".$bloomInstruction
                : $bloomInstruction;
        }
'''
new = '''        if ($data['suggestion_type'] === 'bloom_mapping') {
            $bloomInstruction = 'Fokus HANYA pada klasifikasi Taksonomi Bloom untuk setiap CPMK yang sudah ada. '
                .'Jangan mengubah rumusan CPMK, jangan menambah atau menghapus CPMK, dan jangan memetakan CPL. '
                .'Kembalikan tepat satu rekomendasi untuk SETIAP CPMK dengan target_code yang sama, description yang sama persis, '
                .'dan bloom_level C1-C6 yang paling sesuai dengan kata kerja operasional serta tuntutan kognitif rumusannya. '
                .'Gunakan action adapt bila level Bloom perlu diisi atau diubah, dan keep bila level saat ini sudah tepat. '
                .'Jangan menaikkan level hanya agar terlihat progresif: pahami/menjelaskan umumnya C2; menerapkan/menggunakan/menyelesaikan C3; '
                .'menganalisis/membandingkan C4; mengevaluasi/menilai C5; merancang/menciptakan/mengembangkan C6, dengan tetap membaca konteks rumusan.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\\n\\n".$bloomInstruction
                : $bloomInstruction;
        } elseif ($data['suggestion_type'] === 'sub_cpmk') {
            $subBloomInstruction = 'Klasifikasikan Bloom setiap Sub-CPMK secara INDIVIDUAL berdasarkan kata kerja operasional dan tuntutan kognitif rumusannya. '
                .'JANGAN menyeragamkan semua Sub-CPMK pada C3 atau level lain hanya karena berada dalam satu mata kuliah. '
                .'Gunakan pola umum: mengingat/menyebutkan C1; memahami/menjelaskan/mengidentifikasi C2; menerapkan/menggunakan/menghitung/menyelesaikan C3; '
                .'menganalisis/membandingkan/membedakan C4; mengevaluasi/menilai/memvalidasi C5; merancang/menciptakan/mengembangkan C6. '
                .'Jika satu rumusan memuat beberapa KKO, pilih tuntutan kognitif tertinggi yang benar-benar menjadi hasil belajar utama. '
                .'Sub-CPMK boleh bertahap dari level lebih rendah menuju level CPMK induk, tetapi TIDAK BOLEH melebihi tuntutan Bloom CPMK induknya. '
                .'Gunakan variasi level hanya jika didukung rumusan, bukan untuk sekadar membuat distribusi berbeda.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\\n\\n".$subBloomInstruction
                : $subBloomInstruction;
        }
'''
replace_once(old, new, 'instruction block')

# 2. Invalidate old pending suggestions generated before the Bloom guard policy.
old = '''                    'type' => $data['suggestion_type'],
                    'instruction' => trim((string) ($data['instruction'] ?? '')),
                    'context' => $context,
'''
new = '''                    'type' => $data['suggestion_type'],
                    'instruction' => trim((string) ($data['instruction'] ?? '')),
                    'ai_policy_version' => 'bloom-guard-v2',
                    'context' => $context,
'''
replace_once(old, new, 'context hash')

# 3. Sanitize Sub-CPMK output regardless of which AI provider generated it.
old = '''        } elseif ($data['suggestion_type'] === 'bloom_mapping') {
            $result['payload'] = $this->sanitizeBloomMappingPayload(
                $result['payload'] ?? [],
                $version
            );
        }
'''
new = '''        } elseif ($data['suggestion_type'] === 'bloom_mapping') {
            $result['payload'] = $this->sanitizeBloomMappingPayload(
                $result['payload'] ?? [],
                $version
            );
        } elseif ($data['suggestion_type'] === 'sub_cpmk') {
            $result['payload'] = $this->sanitizeSubCpmkPayload(
                $result['payload'] ?? [],
                $version
            );
        }
'''
replace_once(old, new, 'sanitizer dispatch')

# 4. Strengthen CPMK Bloom sanitizer with an observable-verb guard.
old = '''            $newBloom = strtoupper(trim((string) ($candidate['bloom_level'] ?? '')));
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
'''
new = '''            $providerBloom = strtoupper(trim((string) ($candidate['bloom_level'] ?? '')));
            if (! in_array($providerBloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                continue;
            }

            $inferredBloom = $this->inferBloomLevel((string) $existing->description);
            $newBloom = $inferredBloom ?: $providerBloom;
            $guardAdjusted = $inferredBloom !== null && $inferredBloom !== $providerBloom;

            $oldBloom = strtoupper(trim((string) ($existing->bloom_level ?? '')));
            $same = $oldBloom === $newBloom;

            $rationale = trim((string) ($candidate['rationale'] ?? ''));
            if ($guardAdjusted) {
                $rationale = 'Guard Bloom SiMatRPS menyesuaikan hasil provider dari '.$providerBloom.' menjadi '.$newBloom
                    .' berdasarkan kata kerja operasional eksplisit pada rumusan CPMK.';
            } elseif ($rationale === '') {
                $rationale = $same
                    ? 'Level Bloom saat ini sudah sesuai dengan tuntutan kognitif CPMK.'
                    : 'Level Bloom disesuaikan dengan kata kerja operasional dan tuntutan kognitif CPMK.';
            }

            $items[] = [
                'action' => $same ? 'keep' : 'adapt',
                'target_code' => $existing->code,
                'description' => $existing->description,
                'bloom_level' => $newBloom,
                'cpl_codes' => [],
                'rationale' => $rationale,
            ];
'''
replace_once(old, new, 'CPMK Bloom sanitizer')

# 5. Add Sub-CPMK sanitizer + reusable Bloom helpers before resolveWeekMaterial.
marker = '''    private function resolveWeekMaterial(
        string $value,
        array $materials,
        string $subDescription
    ): ?string {
'''
insert = r'''    private function sanitizeSubCpmkPayload(array $payload, object $version): array
    {
        $existingSubs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->get(['id', 'code', 'description', 'bloom_level'])
            ->keyBy(fn ($row) => mb_strtolower(trim((string) $row->code)));

        $items = $payload['items'] ?? [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));
            $targetCode = trim((string) ($item['target_code'] ?? ''));
            $existing = $targetCode !== ''
                ? $existingSubs->get(mb_strtolower($targetCode))
                : null;

            $description = trim((string) ($item['description'] ?? ($existing->description ?? '')));
            if ($description === '' && $existing) {
                $description = (string) $existing->description;
            }

            $parent = $this->resolveAiParentCpmk(
                $version->id,
                (string) ($item['parent_cpmk_code'] ?? ''),
                $existing ? (string) $existing->code : null,
                $description
            );

            $providerBloom = strtoupper(trim((string) ($item['bloom_level'] ?? '')));
            if (! in_array($providerBloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                $providerBloom = '';
            }

            $inferredBloom = $this->inferBloomLevel($description);
            $newBloom = $inferredBloom ?: ($providerBloom !== '' ? $providerBloom : 'C2');
            $adjustments = [];

            if ($inferredBloom !== null && $providerBloom !== '' && $inferredBloom !== $providerBloom) {
                $adjustments[] = 'hasil provider '.$providerBloom.' disesuaikan menjadi '.$inferredBloom.' berdasarkan KKO rumusan';
            }

            if ($parent) {
                $parentBloom = $this->inferBloomLevel((string) $parent->description)
                    ?: strtoupper(trim((string) ($parent->bloom_level ?? '')));

                if (
                    in_array($parentBloom, ['C1','C2','C3','C4','C5','C6'], true)
                    && $this->bloomRank($newBloom) > $this->bloomRank($parentBloom)
                ) {
                    $adjustments[] = $newBloom.' diturunkan ke '.$parentBloom.' agar tidak melampaui CPMK induk '.$parent->code;
                    $newBloom = $parentBloom;
                }

                $items[$index]['parent_cpmk_code'] = $parent->code;
            }

            $oldBloom = $existing
                ? strtoupper(trim((string) ($existing->bloom_level ?? '')))
                : '';

            if ($action === 'keep' && $existing && $oldBloom !== $newBloom) {
                $items[$index]['action'] = 'adapt';
            }

            $items[$index]['description'] = $description;
            $items[$index]['bloom_level'] = $newBloom;

            if ($adjustments !== []) {
                $items[$index]['rationale'] = 'Guard Bloom SiMatRPS: '.implode('; ', $adjustments).'.';
            } elseif (! filled($items[$index]['rationale'] ?? null)) {
                $items[$index]['rationale'] = 'Level Bloom ditetapkan berdasarkan kata kerja operasional, tuntutan kognitif, dan hierarki CPMK induk.';
            }
        }

        $payload['summary'] = 'Telaah Sub-CPMK dengan pemeriksaan KKO Bloom per rumusan dan batas hierarki terhadap CPMK induk.';
        $payload['items'] = $items;

        return $payload;
    }

    private function inferBloomLevel(string $description): ?string
    {
        $text = mb_strtolower($description);

        // Urutkan dari tuntutan kognitif tertinggi. Jika satu rumusan memuat
        // beberapa KKO, level tertinggi yang benar-benar tertulis menjadi guard.
        $patterns = [
            'C6' => [
                'merancang', 'menciptakan', 'mengembangkan', 'membangun',
                'memformulasikan', 'merumuskan', 'mengonstruksi', 'menghasilkan',
            ],
            'C5' => [
                'mengevaluasi', 'menilai', 'memvalidasi', 'mengkritik',
                'mengkritisi', 'mempertimbangkan',
            ],
            'C4' => [
                'menganalisis', 'membandingkan', 'membedakan', 'menelaah',
                'menginvestigasi', 'mengategorikan',
            ],
            'C3' => [
                'menerapkan', 'menggunakan', 'menghitung', 'menyelesaikan',
                'mengimplementasikan', 'mendemonstrasikan', 'menentukan',
                'memecahkan',
            ],
            'C2' => [
                'memahami', 'menjelaskan', 'menginterpretasikan', 'menafsirkan',
                'mengklasifikasikan', 'merangkum', 'menggambarkan',
                'mengidentifikasi', 'menguraikan',
            ],
            'C1' => [
                'mengingat', 'menyebutkan', 'mendefinisikan', 'mengenali',
                'mendaftar',
            ],
        ];

        foreach ($patterns as $level => $verbs) {
            foreach ($verbs as $verb) {
                if (preg_match('/(?<![\\pL])'.preg_quote($verb, '/').'(?![\\pL])/u', $text) === 1) {
                    return $level;
                }
            }
        }

        return null;
    }

    private function bloomRank(string $level): int
    {
        return match (strtoupper(trim($level))) {
            'C1' => 1,
            'C2' => 2,
            'C3' => 3,
            'C4' => 4,
            'C5' => 5,
            'C6' => 6,
            default => 0,
        };
    }

'''
if marker not in s:
    raise SystemExit('helper insertion target not found')
s = s.replace(marker, insert + marker, 1)

p.write_text(s, encoding='utf-8')
