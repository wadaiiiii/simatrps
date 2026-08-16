from pathlib import Path

p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

old_indicator = """            $indicator = 'Mahasiswa mampu menunjukkan ketercapaian '.$sub->code
                .' sesuai rumusan: '.$sub->description;
"""
new_indicator = """            $indicator = $this->indicatorFromSubCpmk((string) $sub->description);
"""
if old_indicator not in s:
    raise SystemExit('indicator target not found')
s = s.replace(old_indicator, new_indicator, 1)

old_merge = """            foreach ($payload as $key => $value) {
                $existing = $current->{$key} ?? null;

                if ($mode === 'overwrite') {
"""
new_merge = """            foreach ($payload as $key => $value) {
                $existing = $current->{$key} ?? null;

                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'
                    && is_string($existing)
                    && preg_match(
                        '/^Mahasiswa\\s+mampu\\s+menunjukkan\\s+ketercapaian\\s+Sub-?CPMK-?\\d+\\s+sesuai\\s+rumusan\\s*:/iu',
                        $existing
                    ) === 1;

                if ($legacyGeneratedIndicator) {
                    $merged[$key] = $value;
                    if ($existing !== $value) {
                        $changed = true;
                    }
                    continue;
                }

                if ($mode === 'overwrite') {
"""
if old_merge not in s:
    raise SystemExit('merge target not found')
s = s.replace(old_merge, new_merge, 1)

marker = """    private function timeEstimate(int $credits): string
"""
helper = """    private function indicatorFromSubCpmk(string $description): string
    {
        $text = trim(preg_replace('/\\s+/u', ' ', $description) ?? $description);

        // Kolom indikator berdiri di samping Sub-CPMK, sehingga tidak perlu
        // mengulang label Sub-CPMK maupun frasa administratif ketercapaian.
        $text = preg_replace('/^(?:Mahasiswa\\s+)?mampu\\s+/iu', '', $text) ?? $text;
        $text = preg_replace('/^Sub-?CPMK-?\\d+\\s*[:\\-]?\\s*/iu', '', $text) ?? $text;
        $text = trim($text, " \\t\\n\\r\\0\\x0B\\\"'");

        if ($text === '') {
            return 'Menunjukkan hasil belajar yang dapat diamati dan dinilai.';
        }

        $text = mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);

        if (! preg_match('/[.!?]$/u', $text)) {
            $text .= '.';
        }

        return $text;
    }

"""
if marker not in s:
    raise SystemExit('helper marker not found')
s = s.replace(marker, helper + marker, 1)

p.write_text(s, encoding='utf-8')
