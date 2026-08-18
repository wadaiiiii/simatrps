from pathlib import Path

controller = Path('app/Http/Controllers/RpsAiController.php')
text = controller.read_text()

# 1. Split run-on AI activity prose when a new instructional action verb starts.
old = '''        $value = str_replace(["\\r\\n", "\\r"], "\\n", $value);
        $value = preg_replace('/\\s+(?=\\d{1,2}[.)]\\s+)/u', "\\n", $value) ?? $value;
'''
new = '''        $value = str_replace(["\\r\\n", "\\r"], "\\n", $value);
        $value = preg_replace('/\\s+(?=\\d{1,2}[.)]\\s+)/u', "\\n", $value) ?? $value;
        $value = preg_replace(
            '/\\s+(?=(?:menganalisis|mendiskusikan|mengidentifikasi|membandingkan|menghitung|menyusun|mempresentasikan|menjelaskan|menerapkan|mengimplementasikan|merancang|mengevaluasi|mempraktikkan|menguji|merefleksikan|menginterpretasikan|mengamati|menelusuri|memecahkan|menentukan|mengembangkan)\\b)/iu',
            "\\n",
            $value
        ) ?? $value;
'''
if old not in text:
    raise SystemExit('controller split marker not found')
text = text.replace(old, new, 1)

# 2. Make each phase genuinely scannable and avoid dangling prepositions/connectors.
old = '''                if (str_word_count($item, 0, 'À-ÿ') > 16) {
                    $item = Str::words($item, 16, '');
                }

                return trim($item);
'''
new = '''                if (str_word_count($item, 0, 'À-ÿ') > 10) {
                    $item = Str::words($item, 10, '');
                }

                $item = preg_replace(
                    '/\\s+(?:dan|atau|dengan|untuk|pada|ke|dari|dalam|yang|serta|sebagai|melalui)$/iu',
                    '',
                    $item
                ) ?? $item;

                return trim($item);
'''
if old not in text:
    raise SystemExit('controller word-limit marker not found')
text = text.replace(old, new, 1)

# 3. Keep fallback topic compact as well.
text = text.replace("return rtrim(Str::words($value, 10, ''), ' .;,');", "return rtrim(Str::words($value, 6, ''), ' .;,');", 1)
controller.write_text(text)

show = Path('resources/js/pages/rps/show.tsx')
ui = show.read_text()

# 4. Add render-time normalization so previously stored weeks are also compact.
anchor = '''function hasMeaningfulOnlineActivity(value: any): boolean {
    const text = String(value ?? '').trim();
    if (!text) return false;

    return !/^(?:-|—|tidak ada|tidak tersedia|n\\/?a|none)$/iu.test(text);
}
'''
helper = r'''

const ACTIVITY_ACTION_VERBS = 'menganalisis|mendiskusikan|mengidentifikasi|membandingkan|menghitung|menyusun|mempresentasikan|menjelaskan|menerapkan|mengimplementasikan|merancang|mengevaluasi|mempraktikkan|menguji|merefleksikan|menginterpretasikan|mengamati|menelusuri|memecahkan|menentukan|mengembangkan';

function compactActivityPhrase(value: any): string {
    let text = String(value ?? '')
        .replace(/^\s*(?:\d{1,2}[.)]|[-•])\s*/u, '')
        .replace(/^(?:dosen|mahasiswa)\s+/iu, '')
        .replace(/\s+/gu, ' ')
        .trim()
        .replace(/[.;,]+$/u, '');

    if (!text) return '';

    let words = text.split(/\s+/u).filter(Boolean);
    if (words.length > 10) words = words.slice(0, 10);

    const dangling = /^(?:dan|atau|dengan|untuk|pada|ke|dari|dalam|yang|serta|sebagai|melalui)$/iu;
    while (words.length > 0 && dangling.test(words[words.length - 1])) words.pop();

    text = words.join(' ').trim();
    return text;
}

function shortActivityTopic(value: any): string {
    const words = String(value ?? '')
        .replace(/[_]+/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim()
        .split(/\s+/u)
        .filter(Boolean)
        .slice(0, 6);

    return words.join(' ').replace(/[.;,]+$/u, '') || 'materi pekan';
}

function scannableLearningActivity(week: any): string {
    let raw = String(week?.learning_activity ?? '').trim();

    if (/^[+\-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+\-]?\d+)?$/iu.test(raw)) raw = '';

    if (raw) {
        raw = raw
            .replace(/\r\n?/gu, '\n')
            .replace(/\s+(?=\d{1,2}[.)]\s+)/gu, '\n')
            .replace(new RegExp(`\\s+(?=(?:${ACTIVITY_ACTION_VERBS})\\b)`, 'giu'), '\n');
    }

    let parts = raw
        ? raw.split(/\n+/u).flatMap((line) => {
            const cleaned = line.trim();
            if (!cleaned) return [];
            const numbered = cleaned.split(/\s*(?=\d{1,2}[.)]\s+)/u).filter(Boolean);
            if (numbered.length > 1) return numbered;
            return cleaned.split(/(?<=[.!?;])\s+/u).filter(Boolean);
        })
        : [];

    let items = Array.from(new Set(
        parts
            .map(compactActivityPhrase)
            .filter((item) => item && (item.match(/\p{L}/gu)?.length ?? 0) >= 8),
    )).slice(0, 5);

    if (items.length === 0) {
        const topic = shortActivityTopic(week?.material_text || week?.sub_cpmk_description);
        const method = String(week?.learning_method ?? '').toLowerCase();

        if (method.includes('problem-based') || method.includes('problem based')) {
            items = [
                `Orientasi masalah terkait ${topic}`,
                'Identifikasi konsep dan informasi penting',
                'Diskusi kelompok alternatif penyelesaian',
                'Latihan pemecahan masalah terarah',
                'Presentasi dan refleksi hasil',
            ];
        } else if (method.includes('project')) {
            items = [
                `Penetapan tujuan proyek terkait ${topic}`,
                'Perencanaan langkah kerja',
                'Pelaksanaan analisis atau pengembangan',
                'Pemeriksaan hasil terhadap kriteria',
                'Presentasi dan refleksi hasil proyek',
            ];
        } else {
            items = [
                `Penjelasan konsep ${topic}`,
                'Identifikasi unsur atau prosedur utama',
                'Diskusi dan latihan penerapan',
                'Analisis hasil latihan',
                'Refleksi dan simpulan pembelajaran',
            ];
        }
    }

    return items
        .map((item, index) => `${index + 1}. ${compactActivityPhrase(item)}.`)
        .join('\n');
}
'''
if anchor not in ui:
    raise SystemExit('show helper anchor not found')
ui = ui.replace(anchor, anchor + helper, 1)

# 5. Make the two learning columns explicit in the header.
ui = ui.replace('>Tatap muka / Luring</th>', '>Tatap Muka / Luring</th>', 1)
ui = ui.replace('>Daring</th>', '>Daring / LMS</th>', 1)

# 6. Standardize weekly rendering and apply scannable display to legacy rows too.
old = '''            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-bold">{week.learning_form || '-'}</div>
                {Number(week.face_to_face_sessions || 0) > 0 && (
                    <div>{formatFaceToFaceTime(week, c)}</div>
                )}
                <div className="mt-2"><strong>Metode:</strong> {normalizeAcademicTerm(week.learning_method) || '-'}</div>
                {week.learning_activity && (
                    <>
                        <div className="mt-2 font-bold">Aktivitas Kelas:</div>
                        <div className="mt-1 whitespace-pre-line">{normalizeAcademicTerm(week.learning_activity)}</div>
                    </>
                )}
                {Number(week.independent_study_sessions || 0) > 0 && (
                    <>
                        <div className="mt-2 font-bold">Belajar Mandiri</div>
                        <div>{formatIndependentTime(week, c)}</div>
                    </>
                )}
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-bold">Tugas mandiri / terstruktur</div>
                <div>{normalizeAcademicTerm(week.student_assignment) || '-'}</div>
                {Number(week.structured_task_sessions || 0) > 0 && (
                    <div>{formatStructuredTime(week, c)}</div>
                )}
                {hasMeaningfulOnlineActivity(week.online_activity) && (
                    <div className="mt-2">{normalizeAcademicTerm(week.online_activity)}</div>
                )}
            </td>
'''
new = '''            <td className="border border-slate-300 px-2 py-1.5">
                <div><strong>Bentuk:</strong> {normalizeAcademicTerm(String(week.learning_form || '').replace(/_/g, ' ')) || '-'}</div>
                {Number(week.face_to_face_sessions || 0) > 0 && (
                    <div className="mt-1">{formatFaceToFaceTime(week, c)}</div>
                )}
                <div className="mt-2"><strong>Metode:</strong> {normalizeAcademicTerm(week.learning_method) || '-'}</div>
                <div className="mt-2 font-bold">Aktivitas Kelas:</div>
                <div className="mt-1 whitespace-pre-line">{scannableLearningActivity(week)}</div>
                {Number(week.independent_study_sessions || 0) > 0 && (
                    <div className="mt-2 font-semibold">{formatIndependentTime(week, c)}</div>
                )}
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-bold">Tugas Mandiri / Terstruktur:</div>
                <div>{normalizeAcademicTerm(week.student_assignment) || '-'}</div>
                {Number(week.structured_task_sessions || 0) > 0 && (
                    <div className="mt-1">{formatStructuredTime(week, c)}</div>
                )}
                {hasMeaningfulOnlineActivity(week.online_activity) && (
                    <div className="mt-2"><strong>Daring / LMS:</strong> {normalizeAcademicTerm(week.online_activity)}</div>
                )}
            </td>
'''
if old not in ui:
    raise SystemExit('show weekly rendering block not found')
ui = ui.replace(old, new, 1)
show.write_text(ui)
