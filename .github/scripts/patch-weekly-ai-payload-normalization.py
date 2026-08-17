from pathlib import Path

controller = Path('app/Http/Controllers/RpsAiController.php')
text = controller.read_text()

old = """        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'ai' => 'AI tidak mengembalikan data yang valid untuk pekan '.$week.'.',
            ]);
        }

        $subId = $targetSub->id;
"""
new = """        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'ai' => 'AI tidak mengembalikan data yang valid untuk pekan '.$week.'.',
            ]);
        }

        // Provider tertentu kadang mengembalikan field teks sebagai array/list
        // walaupun schema meminta string. Normalisasi dahulu agar tidak terjadi
        // `Array to string conversion` yang sebelumnya dapat berujung HTTP 500.
        $item = $this->normalizeWeeklyAiItem($item);

        $subId = $targetSub->id;
"""
if old not in text:
    raise SystemExit('item validation marker not found')
text = text.replace(old, new, 1)

anchor = """    private function formatScannableLearningActivity(string $value): ?string
    {
"""
helper = r'''    private function normalizeWeeklyAiItem(array $item): array
    {
        $textFields = [
            'material',
            'learning_form',
            'learning_method',
            'student_assignment',
            'online_activity',
            'assessment_indicator',
            'assessment_criteria',
            'assessment_method',
        ];

        foreach ($textFields as $field) {
            $item[$field] = $this->normalizeWeeklyAiText($item[$field] ?? null, '; ');
        }

        // Aktivitas kelas lebih baik mempertahankan satu item per baris agar
        // formatter scannable dapat membuat 3-5 fase yang rapi.
        $item['learning_activity'] = $this->normalizeWeeklyAiText(
            $item['learning_activity'] ?? null,
            "\n"
        );

        // Referensi array seperti ["[1]", "[2]"] tetap menjadi kode yang
        // dapat dibaca resolver pustaka.
        $item['references'] = $this->normalizeWeeklyAiText(
            $item['references'] ?? null,
            ', '
        );

        return $item;
    }

    private function normalizeWeeklyAiText(mixed $value, string $separator = '; '): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $flatten = function (mixed $item) use (&$flatten): array {
            if ($item === null) {
                return [];
            }

            if (is_string($item) || is_numeric($item) || is_bool($item)) {
                $text = trim((string) $item);
                return $text !== '' ? [$text] : [];
            }

            if (! is_array($item)) {
                return [];
            }

            $result = [];
            foreach ($item as $key => $child) {
                // Untuk object-like array, pertahankan value substantif;
                // key teknis tidak perlu ikut masuk ke dokumen RPS.
                foreach ($flatten($child) as $part) {
                    $result[] = $part;
                }
            }

            return $result;
        };

        return collect($flatten($value))
            ->filter(fn ($part) => trim((string) $part) !== '')
            ->unique(fn ($part) => mb_strtolower(trim((string) $part)))
            ->implode($separator);
    }

'''
if anchor not in text:
    raise SystemExit('format helper anchor not found')
text = text.replace(anchor, helper + anchor, 1)

# Make the outer diagnostic safer but more useful if another unexpected error remains.
old_msg = """            throw ValidationException::withMessages([
                'ai' => 'Susun AI Pekan '.$week.' belum berhasil diproses. '
                    .'Request dihentikan dengan aman agar tidak menjadi Server Error 500. '
                    .'Coba sekali lagi; provider yang bermasalah akan dilewati melalui cooldown.',
            ]);
"""
new_msg = """            $diagnostic = strtoupper(substr(hash(
                'sha256',
                get_class($error).'|'.$error->getMessage().'|'.$error->getFile().'|'.$error->getLine()
            ), 0, 8));

            throw ValidationException::withMessages([
                'ai' => 'Susun AI Pekan '.$week.' belum berhasil diproses. '
                    .'Request dihentikan dengan aman agar tidak menjadi Server Error 500. '
                    .'Kode diagnostik: '.$diagnostic.'. Muat ulang lalu coba kembali.',
            ]);
"""
if old_msg not in text:
    raise SystemExit('safe error message marker not found')
text = text.replace(old_msg, new_msg, 1)

controller.write_text(text)
