from pathlib import Path

path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text(encoding='utf-8')

helper_marker = "function actionOptions(message: string, afterSuccess?: () => void) {"
helper = r'''const VALIDATOR_FIX_META: Record<string, { label: string; target: string }> = {
    cpmk_cpl: { label: 'Perbaiki CPMK ↔ CPL', target: 'validator-target-cpmk' },
    sub_cpmk: { label: 'Perbaiki Sub-CPMK', target: 'validator-target-cpmk' },
    materials: { label: 'Perbaiki Bahan Kajian', target: 'validator-target-materials' },
    weeks: { label: 'Perbaiki Tabel RPS', target: 'validator-target-weeks' },
    exam_weeks: { label: 'Perbaiki Asesmen', target: 'validator-target-assessment' },
    assessment_weight: { label: 'Perbaiki Bobot', target: 'validator-target-assessment' },
    subcpmk_assessed: { label: 'Atur Pertemuan', target: 'validator-target-weeks' },
    assessment_chain_sync: { label: 'Periksa RTM', target: 'validator-target-rtm' },
    weekly_assessment_evidence: { label: 'Periksa RTM', target: 'validator-target-rtm' },
    rtm: { label: 'Perbaiki RTM', target: 'validator-target-rtm' },
};

function validatorProblemWeek(check: any): number | null {
    const details = check?.details ?? {};
    const ambiguous = safeList(details.ambiguous_weeks);
    const missing = safeList(details.missing_weeks);
    const candidate = ambiguous[0] ?? missing[0];
    const raw = candidate && typeof candidate === 'object' ? candidate.week : candidate;
    const numeric = Number(raw);

    if (Number.isFinite(numeric) && numeric >= 1 && numeric <= 16) {
        return numeric;
    }

    const match = String(check?.message ?? '').match(/Pekan\s+(\d{1,2})/i);
    const fromMessage = match ? Number(match[1]) : Number.NaN;

    return Number.isFinite(fromMessage) && fromMessage >= 1 && fromMessage <= 16
        ? fromMessage
        : null;
}

function validatorFixLabel(check: any) {
    const meta = VALIDATOR_FIX_META[check?.key];
    if (!meta) return 'Perbaiki';

    const week = validatorProblemWeek(check);
    if (week && meta.target === 'validator-target-rtm') {
        return `Periksa RTM Pekan ${week}`;
    }

    return meta.label;
}

function goToValidatorFix(check: any) {
    const meta = VALIDATOR_FIX_META[check?.key];
    if (!meta) return;

    const week = validatorProblemWeek(check);
    let targets: HTMLElement[] = [];

    if (week && meta.target === 'validator-target-rtm') {
        targets = Array.from(
            document.querySelectorAll<HTMLElement>(`[data-rtm-week="${week}"]`),
        );
    }

    if (targets.length === 0) {
        const section = document.getElementById(meta.target);
        if (section) targets = [section];
    }

    if (targets.length === 0) {
        notify('info', 'Bagian perbaikan belum ditemukan pada tampilan ini.');
        return;
    }

    targets[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    targets.forEach((target) => {
        target.classList.add('ring-2', 'ring-amber-400', 'ring-offset-2');
        window.setTimeout(() => {
            target.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-2');
        }, 3200);
    });
}

'''

if 'const VALIDATOR_FIX_META:' not in text:
    assert helper_marker in text, 'helper insertion marker not found'
    text = text.replace(helper_marker, helper + helper_marker, 1)

# CPMK target.
old = '''                                <tr>\n                                    <td colSpan={5} className="border border-slate-400 bg-slate-50 px-2 py-1.5 font-bold">\n                                        <div className="flex items-center justify-between gap-2">\n                                            <span>Capaian Pembelajaran Mata Kuliah (CPMK)</span>'''
new = '''                                <tr id="validator-target-cpmk" className="scroll-mt-24">\n                                    <td colSpan={5} className="border border-slate-400 bg-slate-50 px-2 py-1.5 font-bold">\n                                        <div className="flex items-center justify-between gap-2">\n                                            <span>Capaian Pembelajaran Mata Kuliah (CPMK)</span>'''
if 'id="validator-target-cpmk"' not in text:
    assert old in text, 'CPMK target marker not found'
    text = text.replace(old, new, 1)

# Bahan Kajian target.
old = '''                                <tr>\n                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">\n                                        Bahan Kajian:'''
new = '''                                <tr id="validator-target-materials" className="scroll-mt-24">\n                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">\n                                        Bahan Kajian:'''
if 'id="validator-target-materials"' not in text:
    assert old in text, 'materials target marker not found'
    text = text.replace(old, new, 1)

# Weekly RPS target.
old = '''                    <div className="flex flex-wrap items-center justify-between gap-2 border-x border-t border-slate-300 bg-slate-50 px-3 py-2 print:hidden">\n                        <div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>'''
new = '''                    <div id="validator-target-weeks" className="scroll-mt-24 flex flex-wrap items-center justify-between gap-2 border-x border-t border-slate-300 bg-slate-50 px-3 py-2 print:hidden">\n                        <div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>'''
if 'id="validator-target-weeks"' not in text:
    assert old in text, 'weekly target marker not found'
    text = text.replace(old, new, 1)

# Assessment editor target.
old = '''                            <div>\n                                <div className="font-bold text-slate-900">Asesmen Detail & RTM</div>'''
new = '''                            <div id="validator-target-assessment" className="scroll-mt-24">\n                                <div className="font-bold text-slate-900">Asesmen Detail & RTM</div>'''
if 'id="validator-target-assessment"' not in text:
    assert old in text, 'assessment target marker not found'
    text = text.replace(old, new, 1)

# RTM editor target.
old = '''                        <div className="mt-5 border-t border-slate-100 pt-4">\n                            <div className="font-bold text-slate-900">RTM</div>'''
new = '''                        <div id="validator-target-rtm" className="scroll-mt-24 mt-5 border-t border-slate-100 pt-4">\n                            <div className="font-bold text-slate-900">RTM</div>'''
if 'id="validator-target-rtm"' not in text:
    assert old in text, 'RTM target marker not found'
    text = text.replace(old, new, 1)

# Mark each visible RTM card with its due week, so ambiguous weeks can highlight all matching cards.
old = '''    if (!editing) {\n        return (\n            <div className="rounded-xl border border-slate-100 bg-white/60 p-4">'''
new = '''    if (!editing) {\n        return (\n            <div\n                data-rtm-week={task.due_week ?? ''}\n                className="rounded-xl border border-slate-100 bg-white/60 p-4 transition-shadow"\n            >'''
if 'data-rtm-week={task.due_week' not in text:
    assert old in text, 'TaskCard marker not found'
    text = text.replace(old, new, 1)

# Add action button to every incomplete validator card.
old = '''                                        <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>\n                                    </div>'''
new = '''                                        <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>\n                                        {!check.done && VALIDATOR_FIX_META[check.key] && (\n                                            <button\n                                                type="button"\n                                                onClick={() => goToValidatorFix(check)}\n                                                className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-2.5 py-1.5 text-[10px] font-bold text-amber-800 shadow-sm hover:bg-amber-100"\n                                            >\n                                                <Pencil className="size-3" />\n                                                {validatorFixLabel(check)}\n                                            </button>\n                                        )}\n                                    </div>'''
if 'validatorFixLabel(check)' not in text:
    assert old in text, 'validator card marker not found'
    text = text.replace(old, new, 1)

# Sanity checks.
for marker in [
    'const VALIDATOR_FIX_META:',
    'id="validator-target-cpmk"',
    'id="validator-target-materials"',
    'id="validator-target-weeks"',
    'id="validator-target-assessment"',
    'id="validator-target-rtm"',
    'data-rtm-week={task.due_week',
    'validatorFixLabel(check)',
]:
    assert marker in text, f'missing final marker: {marker}'

path.write_text(text, encoding='utf-8')
