from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"{label} marker not found")
    return text.replace(old, new, 1)


path = Path("resources/js/pages/rps/show.tsx")
text = path.read_text()

old = """    const selectedAssessmentTotal =
        selectedAssessmentIndices.length + selectedTaskIndices.length;

    const apply = () => {
"""
new = """    const selectedAssessmentTotal =
        selectedAssessmentIndices.length + selectedTaskIndices.length;

    const assessmentBudget = payload?._assessment_budget ?? null;
    const hasAssessmentBudget = suggestion.suggestion_type === 'assessment_plan'
        && assessmentBudget
        && typeof assessmentBudget === 'object';
    const storedAssessmentWeight = hasAssessmentBudget
        ? Number(assessmentBudget.existing_weight_total || 0)
        : 0;
    const remainingAssessmentWeight = hasAssessmentBudget
        ? Number(assessmentBudget.remaining_weight || 0)
        : 0;
    const recommendedAssessmentWeight = hasAssessmentBudget
        ? Number(assessmentBudget.recommended_new_weight_total || 0)
        : safeList(payload.assessments).reduce(
            (sum: number, item: any) => sum + Number(item?.weight || 0),
            0,
        );
    const selectedAssessmentWeight = safeList(payload.assessments).reduce(
        (sum: number, item: any, index: number) =>
            selectedAssessmentIndices.includes(index)
                ? sum + Number(item?.weight || 0)
                : sum,
        0,
    );
    const projectedAssessmentWeight = Math.round(
        (storedAssessmentWeight + selectedAssessmentWeight) * 100,
    ) / 100;
    const assessmentProjectionReady = Math.abs(projectedAssessmentWeight - 100) < 0.01;

    const apply = () => {
"""
text = replace_once(text, old, new, "assessment card budget calculations")

old = """        const message = suggestion.suggestion_type === 'assessment_plan'
            ? `Terapkan ${selectedAssessmentIndices.length} perubahan asesmen dan ${selectedTaskIndices.length} perubahan RTM yang dipilih? Item berstatus Pertahankan dan data lain yang tidak dipilih tidak akan diubah atau dihapus.`
"""
new = """        const message = suggestion.suggestion_type === 'assessment_plan'
            ? `Terapkan ${selectedAssessmentIndices.length} perubahan asesmen (${Number(selectedAssessmentWeight.toFixed(2))}%) dan ${selectedTaskIndices.length} perubahan RTM? Total bobot setelah diterapkan menjadi ${Number(projectedAssessmentWeight.toFixed(2))}%. Item berstatus Pertahankan dan data lain yang tidak dipilih tidak akan diubah atau dihapus.`
"""
text = replace_once(text, old, new, "assessment apply confirmation")

text = replace_once(
    text,
    """                        <span className=\"rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700\">\n                            pending\n                        </span>\n""",
    """                        <span className=\"rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-extrabold text-amber-700\">\n                            Menunggu review\n                        </span>\n""",
    "pending badge",
)

old = """                    <div className=\"mt-2 text-xs font-semibold text-slate-400\">\n                        {countText}\n                    </div>\n\n                    {meta.fallback_used && (\n"""
new = """                    <div className=\"mt-2 text-xs font-semibold text-slate-400\">\n                        {countText}\n                    </div>\n\n                    {suggestion.suggestion_type === 'assessment_plan' && hasAssessmentBudget && (\n                        <div className=\"mt-4 overflow-hidden rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-teal-50\">\n                            <div className=\"flex flex-col gap-2 border-b border-sky-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between\">\n                                <div>\n                                    <div className=\"text-[11px] font-black uppercase tracking-[0.12em] text-sky-700\">Acuan Bobot Telaah AI</div>\n                                    <div className=\"mt-1 text-[11px] leading-5 text-slate-500\">\n                                        AI membaca <strong>data yang sudah disimpan</strong> saat Telaah dijalankan. Perubahan field yang belum disimpan tidak ikut dihitung.\n                                    </div>\n                                </div>\n                                <span className={`shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-black ${\n                                    assessmentProjectionReady\n                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'\n                                        : projectedAssessmentWeight > 100\n                                          ? 'border-rose-200 bg-rose-50 text-rose-700'\n                                          : 'border-amber-200 bg-amber-50 text-amber-700'\n                                }`}>\n                                    Total jika diterapkan: {Number(projectedAssessmentWeight.toFixed(2))}%\n                                </span>\n                            </div>\n\n                            <div className=\"grid grid-cols-2 gap-px bg-sky-100 lg:grid-cols-4\">\n                                {[\n                                    ['Bobot tersimpan', storedAssessmentWeight, 'Baseline terkunci'],\n                                    ['Sisa saat telaah', remainingAssessmentWeight, 'Ruang rekomendasi'],\n                                    ['Rekomendasi AI', recommendedAssessmentWeight, 'Total usulan baru'],\n                                    ['Dipilih sekarang', selectedAssessmentWeight, assessmentProjectionReady ? 'Siap menjadi 100%' : 'Bisa diubah sebelum terapkan'],\n                                ].map(([label, value, note]: any) => (\n                                    <div key={label} className=\"bg-white/85 px-4 py-3\">\n                                        <div className=\"text-[9px] font-bold uppercase tracking-wide text-slate-400\">{label}</div>\n                                        <div className=\"mt-1 text-xl font-black text-slate-900\">{Number(Number(value || 0).toFixed(2))}%</div>\n                                        <div className=\"mt-0.5 text-[9px] font-semibold text-slate-400\">{note}</div>\n                                    </div>\n                                ))}\n                            </div>\n                        </div>\n                    )}\n\n                    {meta.fallback_used && (\n"""
text = replace_once(text, old, new, "assessment budget panel")

text = replace_once(
    text,
    """            <details className=\"mt-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3\">\n                <summary className=\"cursor-pointer text-xs font-bold text-sky-700\">\n                    Lihat detail rekomendasi\n                </summary>\n""",
    """            <details open={suggestion.suggestion_type === 'assessment_plan'} className=\"mt-3 rounded-xl border border-slate-200 bg-slate-50/60 p-3\">\n                <summary className=\"cursor-pointer select-none text-xs font-extrabold text-sky-700\">\n                    {suggestion.suggestion_type === 'assessment_plan' ? 'Tinjau komponen rekomendasi' : 'Lihat detail rekomendasi'}\n                </summary>\n""",
    "assessment details disclosure",
)

start_marker = """    return (\n        <div className=\"mt-3 space-y-4 text-xs\">\n            <div>\n                <div className=\"mb-2 flex items-center justify-between gap-3\">\n                    <div className=\"font-bold text-slate-700\">Asesmen</div>\n"""
end_marker = """        </div>\n    );\n}\n\nRpsShow.layout = {\n"""
start = text.find(start_marker)
if start < 0:
    raise SystemExit("assessment preview start marker not found")
end = text.find(end_marker, start)
if end < 0:
    raise SystemExit("assessment preview end marker not found")

replacement = r'''    const previewBudget = payload?._assessment_budget ?? {};
    const previewStoredWeight = Number(previewBudget?.existing_weight_total || 0);
    const previewSelectedWeight = safeList(payload.assessments).reduce(
        (sum: number, item: any, index: number) =>
            selectedAssessmentIndices.includes(index)
                ? sum + Number(item?.weight || 0)
                : sum,
        0,
    );
    const previewProjectedTotal = Math.round((previewStoredWeight + previewSelectedWeight) * 100) / 100;
    const previewRemainingAfterSelection = Math.round((100 - previewProjectedTotal) * 100) / 100;

    return (
        <div className="mt-3 space-y-4 text-xs">
            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                <div className="flex flex-wrap items-center gap-2 text-[10px] font-bold">
                    <span className="text-slate-500">Arti status:</span>
                    <span className="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">Tambah = data baru</span>
                    <span className="rounded-full bg-amber-100 px-2 py-1 text-amber-800">Perbaiki = ubah target tertentu</span>
                    <span className="rounded-full bg-slate-100 px-2 py-1 text-slate-600">Pertahankan = tidak diubah</span>
                    <span className="ml-auto text-slate-400">RTM tidak menambah bobot.</span>
                </div>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div className="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="font-black text-slate-800">Rekomendasi Asesmen</div>
                        <div className="mt-0.5 text-[10px] text-slate-500">Centang hanya komponen yang ingin diterapkan. Asesmen tersimpan tidak dihapus otomatis.</div>
                    </div>
                    <div className={`rounded-xl border px-3 py-2 text-right ${Math.abs(previewProjectedTotal - 100) < 0.01 ? 'border-emerald-200 bg-emerald-50' : previewProjectedTotal > 100 ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'}`}>
                        <div className="text-[9px] font-bold uppercase tracking-wide text-slate-400">Total setelah pilihan</div>
                        <div className={`text-base font-black ${Math.abs(previewProjectedTotal - 100) < 0.01 ? 'text-emerald-700' : previewProjectedTotal > 100 ? 'text-rose-700' : 'text-amber-700'}`}>{Number(previewProjectedTotal.toFixed(2))}%</div>
                        {Math.abs(previewRemainingAfterSelection) >= 0.01 && (
                            <div className="text-[9px] font-semibold text-slate-500">{previewRemainingAfterSelection > 0 ? `Masih tersisa ${Number(previewRemainingAfterSelection.toFixed(2))}%` : `Kelebihan ${Number(Math.abs(previewRemainingAfterSelection).toFixed(2))}%`}</div>
                        )}
                    </div>
                </div>

                {safeList(payload.assessments).length === 0 ? (
                    <div className="px-4 py-5 text-center text-xs text-slate-500">Tidak ada asesmen baru yang perlu ditambahkan. Bobot tersimpan sudah menjadi baseline; telaah berfokus pada RTM/keterkaitan.</div>
                ) : (
                    <div className="grid gap-3 p-3 lg:grid-cols-2">
                        {safeList(payload.assessments).map((item: any, index: number) => {
                            const action = String(item?.action ?? 'add').toLowerCase();
                            const actionable = action !== 'keep';
                            const selected = actionable && selectedAssessmentIndices.includes(index);
                            const actionLabel = action === 'keep' ? 'Pertahankan' : action === 'adapt' ? 'Perbaiki' : 'Tambah';
                            const actionClass = action === 'keep' ? 'border-slate-200 bg-slate-100 text-slate-600' : action === 'adapt' ? 'border-amber-200 bg-amber-100 text-amber-800' : 'border-emerald-200 bg-emerald-100 text-emerald-800';
                            const typeLabel: Record<string, string> = { quiz: 'Kuis', assignment: 'Tugas', project: 'Proyek', presentation: 'Presentasi', practicum: 'Praktikum', uts: 'UTS', uas: 'UAS', other: 'Lainnya' };
                            const itemType = String(item?.type || 'other');

                            return (
                                <label key={index} className={`relative flex items-start gap-3 rounded-xl border p-3.5 transition ${actionable ? 'cursor-pointer' : 'cursor-default'} ${selected ? 'border-teal-300 bg-teal-50/70 shadow-sm ring-1 ring-teal-100' : action === 'keep' ? 'border-slate-200 bg-slate-50/70' : 'border-slate-200 bg-white hover:border-teal-200'}`}>
                                    <input type="checkbox" className="mt-1 size-4 shrink-0 accent-teal-700" disabled={!actionable} checked={selected} onChange={() => actionable && onToggleAssessment?.(index)} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className={`rounded-full border px-2 py-0.5 text-[9px] font-black ${actionClass}`}>{actionLabel}</span>
                                                    <span className="text-[10px] font-bold text-slate-400">{typeLabel[itemType] || safeText(item?.type, 'Asesmen')}</span>
                                                    <span className="text-[10px] text-slate-300">•</span>
                                                    <span className="text-[10px] font-semibold text-slate-500">Pekan {safeText(item?.week_number, '-')}</span>
                                                </div>
                                                <div className="mt-1.5 text-sm font-black leading-5 text-slate-900">{safeText(item?.name, 'Asesmen tanpa nama')}</div>
                                            </div>
                                            <div className="shrink-0 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-center">
                                                <div className="text-[8px] font-bold uppercase text-sky-500">Bobot</div>
                                                <div className="text-sm font-black text-sky-800">{Number(item?.weight || 0)}%</div>
                                            </div>
                                        </div>
                                        <div className="mt-2 flex flex-wrap gap-1.5">{safeList(item?.sub_cpmk_codes).map((code: any) => <span key={String(code)} className="rounded-full border border-teal-100 bg-teal-50 px-2 py-0.5 text-[9px] font-bold text-teal-700">{safeText(code)}</span>)}</div>
                                        {safeText(item?.description, '') && <div className="mt-2 line-clamp-3 text-[11px] leading-5 text-slate-600">{safeText(item?.description, '')}</div>}
                                        {safeText(item?.rationale, '') && <div className="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-[10px] leading-4 text-slate-500"><strong className="text-slate-600">Alasan AI:</strong> {safeText(item?.rationale, '')}</div>}
                                        {!actionable && <div className="mt-2 text-[10px] font-bold text-slate-400">Tidak perlu dicentang karena data existing dipertahankan.</div>}
                                    </div>
                                </label>
                            );
                        })}
                    </div>
                )}
            </section>

            {safeList(payload.tasks).length > 0 && (
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-col gap-1 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><div className="font-black text-slate-800">Rekomendasi RTM</div><div className="mt-0.5 text-[10px] text-slate-500">RTM mengikuti asesmen induk dan tidak menambah total bobot.</div></div>
                        <div className="text-[10px] font-bold text-slate-400">Dipilih {selectedTaskIndices.length}/{safeList(payload.tasks).length}</div>
                    </div>
                    <div className="grid gap-3 p-3 lg:grid-cols-2">
                        {safeList(payload.tasks).map((task: any, index: number) => {
                            const action = String(task?.action ?? 'add').toLowerCase();
                            const actionable = action !== 'keep';
                            const selected = actionable && selectedTaskIndices.includes(index);
                            const actionLabel = action === 'keep' ? 'Pertahankan' : action === 'adapt' ? 'Perbaiki' : 'Tambah';
                            const actionClass = action === 'keep' ? 'border-slate-200 bg-slate-100 text-slate-600' : action === 'adapt' ? 'border-amber-200 bg-amber-100 text-amber-800' : 'border-violet-200 bg-violet-100 text-violet-800';

                            return (
                                <label key={index} className={`flex items-start gap-3 rounded-xl border p-3.5 transition ${actionable ? 'cursor-pointer' : 'cursor-default'} ${selected ? 'border-violet-300 bg-violet-50/70 shadow-sm ring-1 ring-violet-100' : action === 'keep' ? 'border-slate-200 bg-slate-50/70' : 'border-slate-200 bg-white hover:border-violet-200'}`}>
                                    <input type="checkbox" className="mt-1 size-4 shrink-0 accent-violet-700" disabled={!actionable} checked={selected} onChange={() => actionable && onToggleTask?.(index)} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-1.5"><span className={`rounded-full border px-2 py-0.5 text-[9px] font-black ${actionClass}`}>{actionLabel}</span><span className="text-[10px] font-semibold text-slate-500">Pengumpulan Pekan {safeText(task?.due_week, '-')}</span></div>
                                        <div className="mt-1.5 text-sm font-black leading-5 text-slate-900">{safeText(task?.title, 'RTM tanpa judul')}</div>
                                        <div className="mt-1 text-[10px] text-slate-500">Asesmen induk: <strong className="text-slate-700">{safeText(task?.assessment_name, '-')}</strong></div>
                                        <div className="mt-2 flex flex-wrap gap-1.5">{safeList(task?.sub_cpmk_codes).map((code: any) => <span key={String(code)} className="rounded-full border border-violet-100 bg-violet-50 px-2 py-0.5 text-[9px] font-bold text-violet-700">{safeText(code)}</span>)}</div>
                                        {safeText(task?.expected_output, '') && <div className="mt-2 line-clamp-2 text-[11px] leading-5 text-slate-600"><strong>Luaran:</strong> {safeText(task?.expected_output, '')}</div>}
                                        {safeText(task?.rationale, '') && <div className="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-[10px] leading-4 text-slate-500"><strong className="text-slate-600">Alasan AI:</strong> {safeText(task?.rationale, '')}</div>}
                                    </div>
                                </label>
                            );
                        })}
                    </div>
                </section>
            )}
        </div>
    );
}

RpsShow.layout = {
'''

text = text[:start] + replacement + text[end + len(end_marker):]
path.write_text(text)
