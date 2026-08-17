from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'Missing marker: {label}')
    return text.replace(old, new, 1)

# ---------------------------------------------------------------------------
# routes/web.php: dedicated reference-only update route.
# ---------------------------------------------------------------------------
p = Path('routes/web.php')
s = p.read_text()
s = replace_once(
    s,
    "        Route::put('{rps}/document-meta', [RpsDocumentController::class, 'updateMeta'])->name('document-meta.update');\n        Route::post('{rps}/document-meta/ai-references', [RpsDocumentController::class, 'generateAiReferences'])->name('document-meta.ai-references');",
    "        Route::put('{rps}/document-meta', [RpsDocumentController::class, 'updateMeta'])->name('document-meta.update');\n        Route::put('{rps}/document-meta/references', [RpsDocumentController::class, 'updateReferences'])->name('document-meta.references.update');\n        Route::post('{rps}/document-meta/ai-references', [RpsDocumentController::class, 'generateAiReferences'])->name('document-meta.ai-references');",
    'reference-only route',
)
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsDocumentController: save only Pustaka without requiring all document meta.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsDocumentController.php')
s = p.read_text()
marker = "    public function generateAiReferences(\n"
method = r'''    public function updateReferences(
        Request $request,
        string $rps
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'reference_text' => ['nullable', 'string', 'max:30000'],
            'supporting_reference_text' => ['nullable', 'string', 'max:30000'],
        ]);

        $values = [
            'reference_text' => trim((string) ($data['reference_text'] ?? '')),
            'supporting_reference_text' => trim((string) ($data['supporting_reference_text'] ?? '')),
            'updated_at' => now(),
        ];

        $existing = DB::table('rps_document_meta')
            ->where('rps_version_id', $version->id)
            ->first();

        if ($existing) {
            DB::table('rps_document_meta')
                ->where('id', $existing->id)
                ->update($values);
        } else {
            DB::table('rps_document_meta')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $version->id,
                ...$values,
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'Pustaka berhasil diperbarui.');
    }

'''
if 'public function updateReferences(' not in s:
    s = replace_once(s, marker, method + marker, 'updateReferences method')
p.write_text(s)

# ---------------------------------------------------------------------------
# show.tsx: dedicated endpoint + force editor to current props after AI.
# ---------------------------------------------------------------------------
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text()

old_save = r'''    const save = () => {
        form.put(
            `/rps/${rpsId}/document-meta`,
            actionOptions('Pustaka berhasil diperbarui.', () => setOpen(false)),
        );
    };
'''
new_save = r'''    const syncEditorFromMeta = (nextMeta: any = meta) => {
        form.setData({
            reference_text: nextMeta?.reference_text ?? '',
            supporting_reference_text: nextMeta?.supporting_reference_text ?? '',
        });
        form.clearErrors();
    };

    const toggleEditor = () => {
        if (!open) syncEditorFromMeta(meta);
        setOpen((value) => !value);
    };

    const save = () => {
        form.put(
            `/rps/${rpsId}/document-meta/references`,
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    const refreshed = page?.props?.documentMeta ?? meta;
                    syncEditorFromMeta(refreshed);
                    setOpen(false);
                    notify('success', 'Pustaka berhasil diperbarui.');
                },
                onError: (errors: any) => notify('error', firstError(errors)),
            },
        );
    };
'''
s = replace_once(s, old_save, new_save, 'PustakaInlineTools save')

s = replace_once(
    s,
    '                    onClick={() => setOpen((value) => !value)}\n                    className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-[10px] font-bold transition ${',
    '                    onClick={toggleEditor}\n                    className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-[10px] font-bold transition ${',
    'Pustaka edit toggle',
)

old_ai = r'''                onSuccess: () => notify(
                    'success',
                    'Pustaka berhasil ditelaah AI berdasarkan Bahan Kajian aktif.',
                ),
'''
new_ai = r'''                onSuccess: (page: any) => {
                    const refreshed = page?.props?.documentMeta ?? {};
                    syncEditorFromMeta(refreshed);
                    notify(
                        'success',
                        'Pustaka berhasil ditelaah AI berdasarkan Bahan Kajian aktif. Editor sudah disinkronkan dengan hasil terbaru.',
                    );
                },
'''
s = replace_once(s, old_ai, new_ai, 'Pustaka AI state refresh')

p.write_text(s)
