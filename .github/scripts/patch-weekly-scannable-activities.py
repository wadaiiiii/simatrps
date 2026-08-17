from pathlib import Path

controller_path = Path('app/Http/Controllers/RpsAiController.php')
show_path = Path('resources/js/pages/rps/show.tsx')

controller = controller_path.read_text(encoding='utf-8')
show = show_path.read_text(encoding='utf-8')

prompt_anchor = "Materi pekan WAJIB selaras dengan `target_sub_cpmk`. Prioritaskan `target_materials` bila tersedia. Jangan memilih bahan kajian hanya karena urutannya berdekatan, dan jangan mengulang bahan kajian yang tidak relevan dengan Sub-CPMK target. Jika perlu pengulangan untuk penguatan, nyatakan eksplisit sebagai pendalaman/latihan.\n"
prompt_add = """Materi pekan WAJIB selaras dengan `target_sub_cpmk`. Prioritaskan `target_materials` bila tersedia. Jangan memilih bahan kajian hanya karena urutannya berdekatan, dan jangan mengulang bahan kajian yang tidak relevan dengan Sub-CPMK target. Jika perlu pengulangan untuk penguatan, nyatakan eksplisit sebagai pendalaman/latihan.\n\nFORMAT SCANNABLE METODE DAN AKTIVITAS PEMBELAJARAN:\n- `learning_method` hanya berisi nama metode/model pembelajaran yang ringkas, misalnya \"Problem-Based Learning\", \"Case Method\", \"Project-Based Learning\", \"Small Group Discussion\", atau kombinasi singkat yang benar-benar relevan. Jangan menulis uraian aktivitas di field ini.\n- `learning_activity` WAJIB berupa 3-5 fase aktivitas kelas dalam daftar bernomor, SATU aktivitas per baris. Gunakan frasa ringkas sekitar 4-12 kata per poin, bukan paragraf atau kalimat naratif panjang.\n- Gunakan pola: kata/frasa aktivitas + objek belajar yang konkret. Hindari pembuka berulang \"Dosen...\" atau \"Mahasiswa...\" dan hindari penjelasan prosedural panjang.\n- Contoh format yang diutamakan:\n  1. Penjelasan konsep quicksort dan mergesort.\n  2. Diskusi kelompok komparasi algoritma.\n  3. Latihan implementasi kode di IDE.\n- Setiap fase harus selaras dengan `target_sub_cpmk`, materi pekan, level Bloom, dan `learning_method`. Jangan mengarang perangkat lunak tertentu bila tidak tersedia pada konteks.\n"""
if prompt_anchor not in controller:
    raise SystemExit('Prompt anchor not found')
controller = controller.replace(prompt_anchor, prompt_add, 1)

old_candidate = "            'learning_activity' => $item['learning_activity'] ?? null,"
new_candidate = "            'learning_activity' => $this->formatScannableLearningActivity((string) ($item['learning_activity'] ?? ''))," 
if old_candidate not in controller:
    raise SystemExit('learning_activity candidate anchor not found')
controller = controller.replace(old_candidate, new_candidate, 1)

apply_marker = "\n    public function apply(Request $request, string $rps, string $suggestion): RedirectResponse\n"
helper = r'''
    private function formatScannableLearningActivity(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace(
            '/^\s*(?:fase[-\s]*fase\s+aktivitas\s+pembelajaran|aktivitas\s+kelas)\s*:?\s*/iu',
            '',
            $value
        ) ?? $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/\s+(?=\d{1,2}[.)]\s+)/u', "\n", $value) ?? $value;

        $parts = preg_split(
            '/(?:^|\n)\s*(?:\d{1,2}[.)]|[-•])\s*/u',
            "\n".$value,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        if (count($parts) < 2) {
            $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d])/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value];
        }

        $items = collect($parts)
            ->map(function ($part) {
                $item = trim(strip_tags((string) $part));
                $item = preg_replace('/\s+/u', ' ', $item) ?? $item;
                $item = preg_replace('/^(?:dosen|mahasiswa)\s+/iu', '', $item) ?? $item;
                $item = trim($item, " \t\n\r\0\x0B-•;.");

                // Bila provider masih membuat kalimat sangat panjang, pertahankan
                // inti aktivitas agar tabel RPS tetap mudah dipindai.
                $clauses = preg_split('/\s*;\s*/u', $item, 2);
                $item = trim((string) ($clauses[0] ?? $item));
                if (str_word_count($item, 0, 'À-ÿ') > 16) {
                    $item = Str::words($item, 16, '');
                }

                return trim($item);
            })
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->take(5)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return $items
            ->map(fn ($item, $index) => ($index + 1).'. '.rtrim($item, '.').'.')
            ->implode("\n");
    }
'''
if apply_marker not in controller:
    raise SystemExit('apply marker not found')
controller = controller.replace(apply_marker, "\n" + helper + apply_marker, 1)

old_print = '''                <div className="mt-2"><strong>Metode Pembelajaran</strong></div>
                <div>{normalizeAcademicTerm(week.learning_method) || '-'}</div>
                {week.learning_activity && (
                    <div className="mt-1">{normalizeAcademicTerm(week.learning_activity)}</div>
                )}
                <div className="mt-2 font-bold">Belajar Mandiri</div>'''
new_print = '''                <div className="mt-2"><strong>Metode:</strong> {normalizeAcademicTerm(week.learning_method) || '-'}</div>
                {week.learning_activity && (
                    <>
                        <div className="mt-2 font-bold">Aktivitas Kelas:</div>
                        <div className="mt-1 whitespace-pre-line">{normalizeAcademicTerm(week.learning_activity)}</div>
                    </>
                )}
                <div className="mt-2 font-bold">Belajar Mandiri</div>'''
if old_print not in show:
    raise SystemExit('Printable learning activity anchor not found')
show = show.replace(old_print, new_print, 1)

old_inline = '''                    <div className="mt-2"><strong>Metode:</strong> {week.learning_method || '-'}</div>
                    <div className="mt-2"><strong>Belajar Mandiri:</strong> {week.learning_activity || '-'}</div>
                    <div className="mt-1 text-sky-700">{formatIndependentTime(week, c)}</div>'''
new_inline = '''                    <div className="mt-2"><strong>Metode:</strong> {week.learning_method || '-'}</div>
                    {week.learning_activity && (
                        <>
                            <div className="mt-2"><strong>Aktivitas Kelas:</strong></div>
                            <div className="mt-1 whitespace-pre-line">{week.learning_activity}</div>
                        </>
                    )}
                    <div className="mt-2"><strong>Belajar Mandiri:</strong></div>
                    <div className="mt-1 text-sky-700">{formatIndependentTime(week, c)}</div>'''
if old_inline in show:
    show = show.replace(old_inline, new_inline, 1)

controller_path.write_text(controller, encoding='utf-8')
show_path.write_text(show, encoding='utf-8')
print('Patched weekly AI activities to scannable phases.')
