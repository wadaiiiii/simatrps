from pathlib import Path

path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text()

marker = """function safeList(value: any): any[] {
    return Array.isArray(value) ? value : [];
}
"""
helper = """function safeList(value: any): any[] {
    return Array.isArray(value) ? value : [];
}

function hasMeaningfulOnlineActivity(value: any): boolean {
    const text = String(value ?? '').trim();
    if (!text) return false;

    return !/^(?:-|—|tidak ada|tidak tersedia|n\\/?a|none)$/iu.test(text);
}
"""
if marker not in text:
    raise SystemExit('safeList marker not found')
text = text.replace(marker, helper, 1)

old_doc = """                <div className=\"mt-2\">{normalizeAcademicTerm(week.online_activity) || '-'}</div>"""
new_doc = """                {hasMeaningfulOnlineActivity(week.online_activity) && (
                    <div className=\"mt-2\">{normalizeAcademicTerm(week.online_activity)}</div>
                )}"""
if old_doc not in text:
    raise SystemExit('document online activity marker not found')
text = text.replace(old_doc, new_doc, 1)

old_inline = """                    <div className=\"mt-2\"><strong>Daring/LMS:</strong> {week.online_activity || '-'}</div>"""
new_inline = """                    {hasMeaningfulOnlineActivity(week.online_activity) && (
                        <div className=\"mt-2\"><strong>Daring/LMS:</strong> {week.online_activity}</div>
                    )}"""
if old_inline not in text:
    raise SystemExit('inline online activity marker not found')
text = text.replace(old_inline, new_inline, 1)

path.write_text(text)
