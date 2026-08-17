from pathlib import Path
p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text()
old = "preg_match_all('/sub\\s*[- ]?cpmk\\s*[- ]?(\\d{1,2})/iu', $text, $matches);"
new = "preg_match_all('/sub\\s*[\\p{Pd}\\- ]?cpmk\\s*[\\p{Pd}\\- ]?(\\d{1,2})/iu', $text, $matches);"
assert old in s, 'explicit Sub-CPMK regex marker not found'
s = s.replace(old, new, 1)
p.write_text(s)
