from pathlib import Path

path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text(encoding='utf-8')

replacements = [
    (
        '''label="Telaah CPMK AI"\n                                                    busy={aiBusyType === 'cpmk_review'}\n                                                    disabled={!documentInfoReady || !ai.configured || cpmks.length === 0}''',
        '''label="Telaah CPMK AI"\n                                                    busy={aiBusyType === 'cpmk_review'}\n                                                    disabled={!ai.configured || cpmks.length === 0}'''
    ),
    (
        '''label="Pemetaan Bloom AI"\n                                                    busy={aiBusyType === 'bloom_mapping'}\n                                                    disabled={!documentInfoReady || !ai.configured || cpmks.length === 0}''',
        '''label="Pemetaan Bloom AI"\n                                                    busy={aiBusyType === 'bloom_mapping'}\n                                                    disabled={!ai.configured || cpmks.length === 0}'''
    ),
    (
        '''label="Pemetaan CPMK → CPL AI"\n                                                    busy={aiBusyType === 'cpl_mapping'}\n                                                    disabled={!documentInfoReady || !ai.configured || cpmks.length === 0}''',
        '''label="Pemetaan CPMK → CPL AI"\n                                                    busy={aiBusyType === 'cpl_mapping'}\n                                                    disabled={!ai.configured || cpmks.length === 0 || cpls.length === 0}'''
    ),
]

for old, new in replacements:
    if old not in text:
        raise SystemExit(f'Expected marker not found: {old.splitlines()[0]}')
    text = text.replace(old, new, 1)

# Guard against regressions: CPMK AI actions must no longer depend on documentInfoReady.
for label in ['Telaah CPMK AI', 'Pemetaan Bloom AI', 'Pemetaan CPMK → CPL AI']:
    pos = text.index(f'label="{label}"')
    snippet = text[pos:pos+500]
    if 'disabled={!documentInfoReady' in snippet:
        raise SystemExit(f'{label} still depends on documentInfoReady')

path.write_text(text, encoding='utf-8')
print('CPMK AI button guards updated successfully.')
