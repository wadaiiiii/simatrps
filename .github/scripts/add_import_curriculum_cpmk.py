from pathlib import Path

# 1) Route
p = Path('routes/web.php')
s = p.read_text(encoding='utf-8')
old = """        Route::post('{rps}/cpmk', [ObeWorkspaceController::class, 'storeCpmk'])->name('cpmk.store');
        Route::put('{rps}/cpmk/{cpmk}', [ObeWorkspaceController::class, 'updateCpmk'])->name('cpmk.update');
"""
new = """        Route::post('{rps}/cpmk', [ObeWorkspaceController::class, 'storeCpmk'])->name('cpmk.store');
        Route::post('{rps}/cpmk/import-curriculum', [ObeWorkspaceController::class, 'importCurriculumCpmks'])->name('cpmk.import-curriculum');
        Route::put('{rps}/cpmk/{cpmk}', [ObeWorkspaceController::class, 'updateCpmk'])->name('cpmk.update');
"""
if old not in s:
    raise SystemExit('route target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# 2) Backend restore method
p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')
marker = """    public function updateCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
"""
method = r'''    public function importCurriculumCpmks(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);

        $masters = DB::table('curriculum_cpmks')
            ->where('course_id', $record->course_id)
            ->orderBy('sequence_no')
            ->get();

        if ($masters->isEmpty()) {
            throw ValidationException::withMessages([
                'cpmk' => 'CPMK master kurikulum belum tersedia untuk mata kuliah ini.',
            ]);
        }

        $existing = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->get(['source_cpmk_id', 'code']);

        $existingSourceIds = $existing
            ->pluck('source_cpmk_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $existingCodes = $existing
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->all();

        $missing = $masters
            ->filter(fn ($master) =>
                ! in_array((string) $master->id, $existingSourceIds, true)
                && ! in_array(strtoupper(trim((string) $master->code)), $existingCodes, true)
            )
            ->values();

        if ($missing->isEmpty()) {
            return back()->with(
                'success',
                'CPMK kurikulum sudah lengkap. Tidak ada CPMK master yang perlu dipulihkan.'
            );
        }

        $timestamp = now();
        $rows = $missing->map(fn ($master): array => [
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'code' => $master->code,
            'description' => $master->description,
            'bloom_level' => null,
            'source_type' => 'curriculum',
            'source_cpmk_id' => $master->id,
            'sequence_no' => $master->sequence_no,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        DB::table('rps_cpmks')->insert($rows);

        return back()->with(
            'success',
            count($rows).' CPMK berhasil dipulihkan dari master kurikulum.'
        );
    }

'''
if marker not in s:
    raise SystemExit('controller insertion target not found')
s = s.replace(marker, method + marker, 1)
p.write_text(s, encoding='utf-8')

# 3) Frontend button: add beside Tambah CPMK
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')
old_state = """function DocumentCpmkAdd({ rpsId }: any) {
    const [open, setOpen] = useState(false);
    const form = useForm({
"""
new_state = """function DocumentCpmkAdd({ rpsId }: any) {
    const [open, setOpen] = useState(false);
    const [restoringMaster, setRestoringMaster] = useState(false);
    const form = useForm({
"""
if old_state not in s:
    raise SystemExit('DocumentCpmkAdd state target not found')
s = s.replace(old_state, new_state, 1)

old_closed = """    if (!open) {
        return (
            <button
                type=\"button\"
                onClick={() => setOpen(true)}
                className=\"inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700\"
            >
                <Plus className=\"size-3\" /> Tambah CPMK
            </button>
        );
    }
"""
new_closed = """    if (!open) {
        const restoreFromCurriculum = () => {
            if (restoringMaster) return;

            const confirmed = confirm(
                'Pulihkan CPMK resmi dari master kurikulum yang belum ada di RPS? CPMK yang sudah ada tidak akan diubah.',
            );

            if (!confirmed) return;

            setRestoringMaster(true);
            router.post(
                `/rps/${rpsId}/cpmk/import-curriculum`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: (page: any) => {
                        const message = page?.props?.flash?.success
                            || 'Sinkronisasi CPMK dengan master kurikulum selesai.';
                        notify('success', message);
                    },
                    onError: (errors: any) => notify('error', firstError(errors)),
                    onFinish: () => setRestoringMaster(false),
                },
            );
        };

        return (
            <div className=\"flex flex-wrap items-center gap-1.5\">
                <button
                    type=\"button\"
                    disabled={restoringMaster}
                    onClick={restoreFromCurriculum}
                    title=\"Pulihkan CPMK resmi yang hilang dari master kurikulum tanpa menimpa CPMK yang sudah ada\"
                    className=\"inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50\"
                >
                    <RotateCcw className={`size-3 ${restoringMaster ? 'animate-spin' : ''}`} />
                    {restoringMaster ? 'Mengambil...' : 'Ambil CPMK Kurikulum'}
                </button>
                <button
                    type=\"button\"
                    onClick={() => setOpen(true)}
                    className=\"inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700\"
                >
                    <Plus className=\"size-3\" /> Tambah CPMK
                </button>
            </div>
        );
    }
"""
if old_closed not in s:
    raise SystemExit('DocumentCpmkAdd closed block target not found')
s = s.replace(old_closed, new_closed, 1)
p.write_text(s, encoding='utf-8')
