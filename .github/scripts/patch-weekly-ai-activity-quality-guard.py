from pathlib import Path

controller = Path('app/Http/Controllers/RpsAiController.php')
text = controller.read_text()

old = '''        try {
            $scannableLearningActivity = $this->formatScannableLearningActivity(
                (string) ($item['learning_activity'] ?? '')
            );
        } catch (\\Throwable $error) {
            // Formatter hanya untuk presentasi/scannability. Jangan biarkan output AI
            // yang aneh menjatuhkan keseluruhan request menjadi HTTP 500.
            report($error);
            $rawLearningActivity = trim((string) ($item['learning_activity'] ?? ''));
            $scannableLearningActivity = $rawLearningActivity !== ''
                ? $rawLearningActivity
                : null;
        }
'''
new = '''        try {
            $scannableLearningActivity = $this->formatScannableLearningActivity(
                (string) ($item['learning_activity'] ?? '')
            );
        } catch (\\Throwable $error) {
            // Formatter hanya untuk presentasi/scannability. Jangan biarkan output AI
            // yang aneh menjatuhkan keseluruhan request menjadi HTTP 500.
            report($error);
            $scannableLearningActivity = null;
        }

        // Output provider seperti 1E-16/0.0001 bukan aktivitas pembelajaran.
        // Jika aktivitas tidak bermakna, bentuk fallback scannable yang tetap
        // diturunkan dari metode, materi, indikator, dan Sub-CPMK aktif.
        if (! $this->isMeaningfulLearningActivity($scannableLearningActivity)) {
            $scannableLearningActivity = $this->fallbackScannableLearningActivity(
                (string) ($item['learning_method'] ?? ''),
                (string) ($resolvedMaterial ?? ''),
                (string) ($item['assessment_indicator'] ?? ''),
                (string) ($context['target_sub_cpmk']['description'] ?? '')
            );
        }
'''
if old not in text:
    raise SystemExit('scannable block marker not found')
text = text.replace(old, new, 1)

old_scalar = '''        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return trim((string) $value);
        }
'''
new_scalar = '''        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            $text = trim((string) $value);

            // Angka murni/scientific notation yang muncul pada field naratif
            // adalah artefak provider, bukan isi akademik yang layak disimpan.
            if (preg_match('/^[+-]?(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:e[+-]?\\d+)?$/i', $text) === 1) {
                return '';
            }

            return $text;
        }
'''
if old_scalar not in text:
    raise SystemExit('normalize scalar marker not found')
text = text.replace(old_scalar, new_scalar, 1)

old_flat = '''            if (is_string($item) || is_numeric($item) || is_bool($item)) {
                $text = trim((string) $item);
                return $text !== '' ? [$text] : [];
            }
'''
new_flat = '''            if (is_string($item) || is_numeric($item) || is_bool($item)) {
                $text = trim((string) $item);
                if ($text === '' || preg_match('/^[+-]?(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:e[+-]?\\d+)?$/i', $text) === 1) {
                    return [];
                }
                return [$text];
            }
'''
if old_flat not in text:
    raise SystemExit('normalize flatten marker not found')
text = text.replace(old_flat, new_flat, 1)

anchor = '''    private function formatScannableLearningActivity(string $value): ?string
    {
'''
helpers = r'''    private function isMeaningfulLearningActivity(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $plain = preg_replace('/(?:^|\n)\s*\d{1,2}[.)]\s*/u', ' ', $value) ?? $value;
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);

        if ($plain === '' || preg_match('/^[+\-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+\-]?\d+)?$/i', $plain) === 1) {
            return false;
        }

        // Minimal mengandung beberapa huruf agar bukan simbol/kode numerik.
        preg_match_all('/\pL/u', $plain, $letters);
        return count($letters[0] ?? []) >= 8;
    }

    private function fallbackScannableLearningActivity(
        string $method,
        string $material,
        string $indicator,
        string $subCpmk
    ): string {
        $methodKey = mb_strtolower(trim($method));
        $topic = $this->shortLearningTopic($material !== '' ? $material : $subCpmk);
        $evidence = $this->shortLearningTopic($indicator);

        if (str_contains($methodKey, 'problem-based') || str_contains($methodKey, 'problem based')) {
            $items = [
                'Orientasi masalah terkait '.$topic,
                'Identifikasi konsep dan informasi yang diperlukan',
                'Diskusi kelompok analisis alternatif penyelesaian',
                'Penyelesaian kasus atau latihan terarah',
                'Presentasi dan refleksi hasil analisis',
            ];
        } elseif (str_contains($methodKey, 'case')) {
            $items = [
                'Pengenalan kasus terkait '.$topic,
                'Identifikasi fakta dan konsep utama',
                'Diskusi kelompok analisis kasus',
                'Perumusan alternatif solusi atau keputusan',
                'Presentasi dan refleksi hasil',
            ];
        } elseif (str_contains($methodKey, 'project')) {
            $items = [
                'Penetapan tujuan proyek terkait '.$topic,
                'Perencanaan langkah dan pembagian pekerjaan',
                'Pelaksanaan analisis atau pengembangan produk',
                'Pemeriksaan hasil terhadap kriteria tugas',
                'Presentasi dan refleksi hasil proyek',
            ];
        } elseif (str_contains($methodKey, 'small group') || str_contains($methodKey, 'discussion')) {
            $items = [
                'Penjelasan ringkas konsep '.$topic,
                'Identifikasi unsur atau operasi utama',
                'Diskusi kelompok komparasi dan analisis',
                'Latihan penerapan pada contoh terarah',
                'Presentasi dan refleksi hasil kelompok',
            ];
        } else {
            $items = [
                'Penjelasan konsep utama '.$topic,
                'Identifikasi unsur atau prosedur penting',
                'Diskusi dan latihan penerapan terarah',
                'Analisis hasil berdasarkan konsep yang dipelajari',
                'Refleksi dan simpulan pembelajaran',
            ];
        }

        if ($evidence !== '' && $evidence !== $topic) {
            $items[3] = 'Latihan bukti belajar: '.$evidence;
        }

        return collect($items)
            ->map(fn ($item, $index) => ($index + 1).'. '.rtrim(trim($item), '.').'.')
            ->implode("\n");
    }

    private function shortLearningTopic(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/^(?:menerapkan|mengimplementasikan|menganalisis|menjelaskan|mengidentifikasi|membandingkan|merancang|menyusun|mengevaluasi)\s+/iu', '', $value) ?? $value;

        if ($value === '') {
            return 'materi pekan';
        }

        return rtrim(Str::words($value, 10, ''), ' .;,');
    }

'''
if anchor not in text:
    raise SystemExit('formatScannableLearningActivity anchor not found')
text = text.replace(anchor, helpers + anchor, 1)

# Reject numeric-only activity directly in the formatter as a final defense.
old_start = '''        $value = trim($value);
        if ($value === '') {
            return null;
        }
'''
new_start = '''        $value = trim($value);
        if (
            $value === ''
            || preg_match('/^[+-]?(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:e[+-]?\\d+)?$/i', $value) === 1
        ) {
            return null;
        }
'''
if old_start not in text:
    raise SystemExit('formatter start marker not found')
text = text.replace(old_start, new_start, 1)

controller.write_text(text)

# Trigger marker: weekly-ai-activity-quality-guard-v2
