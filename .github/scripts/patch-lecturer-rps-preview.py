from pathlib import Path


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'Expected snippet not found in {path}: {old[:120]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


# 1) Lecturer sidebar: add Preview RPS menu.
sidebar = Path('resources/js/components/app-sidebar.tsx')
replace_once(
    sidebar,
    "    FilePlus2,\n    Files,\n    LayoutDashboard,",
    "    Eye,\n    FilePlus2,\n    Files,\n    LayoutDashboard,",
)
replace_once(
    sidebar,
    "    { title: 'RPS Saya', href: '/rps', icon: Files },\n];",
    "    { title: 'RPS Saya', href: '/rps', icon: Files },\n    { title: 'Preview RPS', href: '/rps/preview', icon: Eye },\n];",
)

# 2) Routes: reuse the existing controller data/show logic for dedicated preview URLs.
routes = Path('routes/web.php')
replace_once(
    routes,
    "        Route::get('/', [RpsController::class, 'index'])->name('index');\n        Route::get('baru', [RpsController::class, 'create'])->name('create');",
    "        Route::get('/', [RpsController::class, 'index'])->name('index');\n        Route::get('preview', [RpsController::class, 'index'])->name('preview');\n        Route::get('preview/{rps}', [RpsController::class, 'show'])->name('preview.show');\n        Route::get('baru', [RpsController::class, 'create'])->name('create');",
)

# 3) RPS list: switch labels/actions when opened through /rps/preview.
index = Path('resources/js/pages/rps/index.tsx')
replace_once(
    index,
    "import { Head, Link, router } from '@inertiajs/react';",
    "import { Head, Link, router, usePage } from '@inertiajs/react';",
)
replace_once(
    index,
    "    const [notice, setNotice] = useState<string | null>(null);\n    const [deletingId, setDeletingId] = useState<string | null>(null);",
    "    const [notice, setNotice] = useState<string | null>(null);\n    const [deletingId, setDeletingId] = useState<string | null>(null);\n    const isPreviewMode = usePage().url.startsWith('/rps/preview');",
)
replace_once(
    index,
    "            <Head title=\"RPS Saya\" />",
    "            <Head title={isPreviewMode ? 'Preview RPS' : 'RPS Saya'} />",
)
replace_once(
    index,
    "                        <h1 className=\"text-2xl font-bold tracking-tight\">RPS Saya</h1>\n                        <p className=\"mt-1 text-sm text-slate-500\">\n                            Daftar RPS yang Anda susun di SiMatRPS.\n                        </p>",
    "                        <h1 className=\"text-2xl font-bold tracking-tight\">\n                            {isPreviewMode ? 'Preview RPS' : 'RPS Saya'}\n                        </h1>\n                        <p className=\"mt-1 text-sm text-slate-500\">\n                            {isPreviewMode\n                                ? 'Pilih RPS yang ingin ditampilkan dalam mode lihat saja.'\n                                : 'Daftar RPS yang Anda susun di SiMatRPS.'}\n                        </p>",
)
replace_once(
    index,
    "                    <Link\n                        href=\"/rps/baru\"\n                        className=\"inline-flex w-fit items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white\"\n                    >\n                        <FilePlus2 className=\"size-4\" />\n                        Buat RPS\n                    </Link>",
    "                    {!isPreviewMode && (\n                        <Link\n                            href=\"/rps/baru\"\n                            className=\"inline-flex w-fit items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white\"\n                        >\n                            <FilePlus2 className=\"size-4\" />\n                            Buat RPS\n                        </Link>\n                    )}",
)
replace_once(
    index,
    "                            <p className=\"mt-1 text-sm text-slate-500\">Pilih Buat RPS untuk membuat dokumen pertama.</p>",
    "                            <p className=\"mt-1 text-sm text-slate-500\">\n                                {isPreviewMode\n                                    ? 'Belum ada RPS yang dapat dipreview.'\n                                    : 'Pilih Buat RPS untuk membuat dokumen pertama.'}\n                            </p>",
)
replace_once(
    index,
    "                                                    <Link\n                                                        href={`/rps/${row.id}`}\n                                                        className=\"inline-flex items-center gap-1.5 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-teal-800 hover:shadow-md\"\n                                                    >\n                                                        Buka RPS\n                                                        <ArrowRight className=\"size-3.5\" />\n                                                    </Link>\n                                                    <button\n                                                        type=\"button\"\n                                                        disabled={deletingId === row.id}\n                                                        onClick={() => destroyRps(row)}\n                                                        className=\"inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50 disabled:opacity-50\"\n                                                    >\n                                                        <Trash2 className=\"size-3.5\" />\n                                                        {deletingId === row.id ? 'Menghapus...' : 'Hapus'}\n                                                    </button>",
    "                                                    <Link\n                                                        href={isPreviewMode ? `/rps/preview/${row.id}` : `/rps/${row.id}`}\n                                                        className=\"inline-flex items-center gap-1.5 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-teal-800 hover:shadow-md\"\n                                                    >\n                                                        {isPreviewMode ? 'Lihat Preview' : 'Buka RPS'}\n                                                        <ArrowRight className=\"size-3.5\" />\n                                                    </Link>\n                                                    {!isPreviewMode && (\n                                                        <button\n                                                            type=\"button\"\n                                                            disabled={deletingId === row.id}\n                                                            onClick={() => destroyRps(row)}\n                                                            className=\"inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50 disabled:opacity-50\"\n                                                        >\n                                                            <Trash2 className=\"size-3.5\" />\n                                                            {deletingId === row.id ? 'Menghapus...' : 'Hapus'}\n                                                        </button>\n                                                    )}",
)

# 4) RPS show page: dedicated read-only visual preview mode.
show = Path('resources/js/pages/rps/show.tsx')
replace_once(
    show,
    "import { Head, router, useForm } from '@inertiajs/react';",
    "import { Head, Link, router, useForm, usePage } from '@inertiajs/react';",
)
replace_once(
    show,
    "    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);\n\n    const aiPreferenceKey",
    "    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);\n    const isPreviewMode = usePage().url.startsWith('/rps/preview/');\n\n    const aiPreferenceKey",
)
replace_once(
    show,
    "            <Head title={`RPS ${rps.course_name}`} />\n            <ActionNotifications />\n\n            <div className=\"p-3 md:p-5\">",
    "            <Head title={`${isPreviewMode ? 'Preview RPS' : 'RPS'} ${rps.course_name}`} />\n            <ActionNotifications />\n\n            {isPreviewMode && (\n                <style>{`\n                    .rps-preview .print\\:hidden { display: none !important; }\n                    .rps-preview section button,\n                    .rps-preview section input,\n                    .rps-preview section textarea,\n                    .rps-preview section select { display: none !important; }\n                `}</style>\n            )}\n\n            <div className={`p-3 md:p-5 ${isPreviewMode ? 'rps-preview' : ''}`}>\n                {isPreviewMode && (\n                    <div className=\"mb-4 flex flex-col gap-3 rounded-2xl border border-teal-100 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between\">\n                        <div className=\"flex items-center gap-3\">\n                            <SidebarTrigger\n                                className=\"size-9 shrink-0 rounded-xl border border-slate-200 bg-white shadow-sm\"\n                                title=\"Minimalkan / tampilkan menu\"\n                            />\n                            <div>\n                                <div className=\"text-xs font-bold uppercase tracking-wider text-teal-700\">Preview RPS</div>\n                                <div className=\"mt-0.5 font-bold text-slate-900\">{rps.course_name}</div>\n                                <div className=\"text-xs text-slate-500\">Mode lihat saja — isian RPS tidak dapat diubah dari halaman ini.</div>\n                            </div>\n                        </div>\n                        <Link\n                            href=\"/rps/preview\"\n                            className=\"inline-flex w-fit items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50\"\n                        >\n                            Kembali ke Daftar Preview\n                        </Link>\n                    </div>\n                )}",
)

print('Lecturer RPS preview patch applied successfully.')
