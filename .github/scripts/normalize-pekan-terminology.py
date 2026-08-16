from pathlib import Path

root = Path('.')
changed = []

for base in [root / 'app', root / 'resources' / 'js']:
    for path in base.rglob('*'):
        if path.suffix not in {'.php', '.tsx', '.ts', '.jsx', '.js'}:
            continue
        text = path.read_text(encoding='utf-8')
        new = text.replace('DUE / MINGGU', 'PEKAN KE-')
        new = new.replace('Minggu', 'Pekan')
        new = new.replace('minggu', 'pekan')
        if new != text:
            path.write_text(new, encoding='utf-8')
            changed.append(str(path))

# Simulation header abbreviation should also be explicit.
show = root / 'resources/js/pages/rps/show.tsx'
text = show.read_text(encoding='utf-8')
text = text.replace('>Mg</th>', '>Pekan</th>')
show.write_text(text, encoding='utf-8')

required = {
    'resources/js/pages/rps/show.tsx': ['PEKAN KE-', 'Pekan {task.due_week', '>Pekan</th>'],
    'app/Services/Rps/ObeWorkspaceService.php': ['UTS pekan 8 dan UAS pekan 16.'],
}
for filename, markers in required.items():
    data = (root / filename).read_text(encoding='utf-8')
    for marker in markers:
        if marker not in data:
            raise SystemExit(f'missing marker {marker!r} in {filename}')

print('Normalized Minggu -> Pekan in', len(changed), 'source files')
for item in changed:
    print(item)
