from pathlib import Path

path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text(encoding='utf-8')


def replace_once(old: str, new: str, label: str) -> None:
    global text
    if old not in text:
        raise SystemExit(f'{label}: marker not found')
    text = text.replace(old, new, 1)


replace_once(
'''                                {ai.configured
                                    ? `${String(ai.provider).toUpperCase()} | ${ai.model}${safeList(ai.fallbacks).length ? ` | Backup ${safeList(ai.fallbacks).map((x: any) => String(x).toUpperCase()).join(' → ')}` : ''}`
                                    : 'Belum aktif'}''',
'''                                {ai.configured ? 'AI aktif' : 'AI belum aktif'}''',
'AI provider status',
)

replace_once(
'''                        </table>
                    </div>

                    {/* Weekly toolbar */}''',
'''                        </table>
                    </div>

                    {/* Spacer nyata agar tabel identitas dan tabel pekan tidak menempel, termasuk di Chrome print preview. */}
                    <div aria-hidden="true" className="w-full shrink-0" style={{ height: '32px' }} />

                    {/* Weekly toolbar */}''',
'RPS metadata/weekly spacer',
)

replace_once(
'''                            <button
                                type="button"
                                onClick={() => router.post(
                                    `/rps/${rps.id}/smart-draft`,
                                    { mode: 'fill_empty' },
                                    actionOptions('Bagian yang masih kosong berhasil dilengkapi.'),
                                )}
                                className="rounded-lg bg-teal-700 px-2.5 py-1.5 text-[11px] font-bold text-white"
                            >
                                Isi Kosong
                            </button>''',
'''                            <button
                                type="button"
                                title="Mengisi otomatis bagian RPS yang masih kosong tanpa mengubah isian yang sudah ada."
                                onClick={() => router.post(
                                    `/rps/${rps.id}/smart-draft`,
                                    { mode: 'fill_empty' },
                                    actionOptions('Bagian RPS yang masih kosong berhasil dilengkapi tanpa mengubah isian yang sudah ada.'),
                                )}
                                className="rounded-lg bg-teal-700 px-2.5 py-1.5 text-[11px] font-bold text-white"
                            >
                                Lengkapi Bagian Kosong
                            </button>''',
'fill-empty button copy',
)

path.write_text(text, encoding='utf-8')
