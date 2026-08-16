from pathlib import Path

p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')

anchor = """function safeList(value: any): any[] {
    return Array.isArray(value) ? value : [];
}
"""
helper = """function safeList(value: any): any[] {
    return Array.isArray(value) ? value : [];
}

function stripMaterialListPrefix(value: any) {
    return String(value ?? '')
        .replace(/^\\s*(?:(?:[a-z]|\\d{1,2})[.)]\\s*)+/iu, '')
        .trim();
}
"""
if 'function stripMaterialListPrefix' not in s:
    if anchor not in s:
        raise SystemExit('safeList anchor not found')
    s = s.replace(anchor, helper, 1)

old_li = '<li key={item.id}>{item.title}</li>'
new_li = '<li key={item.id}>{stripMaterialListPrefix(item.title)}</li>'
if old_li in s:
    s = s.replace(old_li, new_li, 1)
elif new_li not in s:
    raise SystemExit('material list item target not found')

old_form = """function MaterialEditRow({ rpsId, material }: any) {
    const form = useForm({
        title: material.title ?? '',
    });"""
new_form = """function MaterialEditRow({ rpsId, material }: any) {
    const form = useForm({
        title: stripMaterialListPrefix(material.title),
    });"""
if old_form in s:
    s = s.replace(old_form, new_form, 1)
elif new_form not in s:
    raise SystemExit('MaterialEditRow target not found')

p.write_text(s, encoding='utf-8')
