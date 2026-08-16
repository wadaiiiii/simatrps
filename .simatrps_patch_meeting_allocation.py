from pathlib import Path
import re

# routes/web.php
p = Path('routes/web.php')
s = p.read_text(encoding='utf-8')
needle = "        Route::post('{rps}/weeks/align-subcpmk', [ObeWorkspaceController::class, 'alignSubCpmkSequence'])->name('weeks.align-subcpmk');\n"
insert = needle + "        Route::post('{rps}/weeks/allocate-subcpmk', [RpsAutomationController::class, 'allocateSubCpmkMeetings'])->name('weeks.allocate-subcpmk');\n"
if "weeks/allocate-subcpmk" not in s:
    if needle not in s:
        raise SystemExit('route insertion point not found')
    s = s.replace(needle, insert, 1)
p.write_text(s, encoding='utf-8')

# RpsAutomationController.php
p = Path('app/Http/Controllers/RpsAutomationController.php')
s = p.read_text(encoding='utf-8')
if 'public function allocateSubCpmkMeetings' not in s:
    marker = "    public function copyPrevious(\n"
    if marker not in s:
        raise SystemExit('automation controller marker not found')
    method = r'''    public function allocateSubCpmkMeetings(
        Request $request,
        string $rps
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code']);

        if ($subCpmks->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tambahkan minimal satu Sub-CPMK sebelum mengatur jumlah pertemuan.',
            ]);
        }

        if ($subCpmks->count() > 14) {
            throw ValidationException::withMessages([
                'allocations' => 'Jumlah Sub-CPMK melebihi 14 pertemuan efektif. Rapikan Sub-CPMK terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['required', 'integer', 'min:1', 'max:14'],
        ]);

        $allocations = $validated['allocations'];
        $validIds = $subCpmks->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach ($validIds as $subId) {
            if (! array_key_exists($subId, $allocations)) {
                throw ValidationException::withMessages([
                    'allocations' => 'Setiap Sub-CPMK harus memiliki jumlah pertemuan minimal 1.',
                ]);
            }
        }

        foreach (array_keys($allocations) as $subId) {
            if (! in_array((string) $subId, $validIds, true)) {
                throw ValidationException::withMessages([
                    'allocations' => 'Terdapat Sub-CPMK yang tidak valid pada pengaturan pertemuan.',
                ]);
            }
        }

        $total = array_sum(array_map('intval', $allocations));
        if ($total !== 14) {
            throw ValidationException::withMessages([
                'allocations' => "Total pertemuan pembelajaran harus tepat 14. Saat ini totalnya {$total}.",
            ]);
        }

        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $expanded = [];
        foreach ($subCpmks as $sub) {
            $count = (int) $allocations[(string) $sub->id];
            for ($i = 0; $i < $count; $i++) {
                $expanded[] = (string) $sub->id;
            }
        }

        $currentWeeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', $teachingWeeks)
            ->get()
            ->keyBy('week_number');

        if ($currentWeeks->count() !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'allocations' => 'Struktur 14 pertemuan belum lengkap. Muat ulang RPS atau lengkapi struktur minggu terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($currentWeeks, $expanded, $teachingWeeks): void {
            foreach ($teachingWeeks as $index => $weekNumber) {
                $row = $currentWeeks->get($weekNumber);
                $newSubId = $expanded[$index];
                $oldSubId = filled($row->rps_sub_cpmk_id ?? null)
                    ? (string) $row->rps_sub_cpmk_id
                    : null;
                $oldSource = (string) ($row->source_type ?? '');

                $update = [
                    'rps_sub_cpmk_id' => $newSubId,
                    'source_type' => 'manual_allocation',
                    'updated_at' => now(),
                ];

                // Jika alokasi menggeser baris yang sebelumnya dihasilkan otomatis,
                // kosongkan konten turunan agar tidak terjadi Sub-CPMK baru dengan
                // materi/indikator milik Sub-CPMK lama. Isian manual/AI dosen tidak
                // dihapus; dosen tetap dapat meninjaunya.
                if (
                    $oldSubId !== $newSubId
                    && in_array($oldSource, ['smart_draft', 'manual_allocation'], true)
                ) {
                    foreach ([
                        'material_text',
                        'learning_activity',
                        'student_assignment',
                        'assessment_indicator',
                        'assessment_criteria',
                        'assessment_method',
                        'reference_text',
                    ] as $column) {
                        $update[$column] = null;
                    }
                }

                DB::table('rps_weekly_plans')
                    ->where('id', $row->id)
                    ->update($update);
            }
        });

        return back()->with(
            'success',
            'Alokasi pertemuan Sub-CPMK disimpan. Lengkapi RPS Otomatis akan mengikuti jumlah pertemuan yang ditetapkan dosen.'
        );
    }

'''
    s = s.replace(marker, method + marker, 1)
p.write_text(s, encoding='utf-8')

# ObeWorkspaceController: prevent Rapikan from overriding manual allocation
p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')
start = s.find('    public function alignSubCpmkSequence(')
if start < 0:
    raise SystemExit('alignSubCpmkSequence not found')
context_line = "        [, $version] = $this->context($request, $rps);\n"
pos = s.find(context_line, start)
if pos < 0:
    raise SystemExit('align context line not found')
insert_pos = pos + len(context_line)
guard = r'''
        $manualAllocationActive = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', [1,2,3,4,5,6,7,9,10,11,12,13,14,15])
            ->where('source_type', 'manual_allocation')
            ->exists();

        if ($manualAllocationActive) {
            throw ValidationException::withMessages([
                'sub_cpmk' => 'Alokasi pertemuan manual sedang aktif. Ubah jumlah pertemuan melalui tombol Atur Pertemuan agar keputusan dosen tidak tertimpa.',
            ]);
        }
'''
if 'Alokasi pertemuan manual sedang aktif' not in s:
    s = s[:insert_pos] + guard + s[insert_pos:]
p.write_text(s, encoding='utf-8')

# Smart Draft: respect manual allocation and derive material for allocated Sub-CPMK
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')
marker = "        $teachingSequence = $this->buildTeachingSequence($subCpmks, $materials);\n\n        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {\n"
replacement = r'''        $teachingSequence = $this->buildTeachingSequence($subCpmks, $materials);

        // Jika dosen sudah menetapkan jumlah pertemuan melalui Atur Pertemuan,
        // alokasi itu menjadi hard constraint. Smart Draft hanya melengkapi isi
        // tiap minggu dan tidak boleh membagi ulang Sub-CPMK.
        $manualAllocationCounts = [];
        foreach (self::TEACHING_WEEKS as $manualWeek) {
            $manualRow = $currentWeeks->get($manualWeek);
            if (
                $manualRow
                && ($manualRow->source_type ?? null) === 'manual_allocation'
                && filled($manualRow->rps_sub_cpmk_id ?? null)
            ) {
                $key = (string) $manualRow->rps_sub_cpmk_id;
                $manualAllocationCounts[$key] = ($manualAllocationCounts[$key] ?? 0) + 1;
            }
        }
        $manualOccurrence = [];

        foreach (self::TEACHING_WEEKS as $position => $weekNumber) {
'''
if marker not in s:
    raise SystemExit('smart draft sequence marker not found')
s = s.replace(marker, replacement, 1)

old = "            $sub = $slot['sub'];\n            $materialText = $slot['material'];\n\n            $method = $hasPracticum\n"
new = r'''            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $manualAllocation = ($current->source_type ?? null) === 'manual_allocation'
                && filled($current->rps_sub_cpmk_id ?? null);

            if ($manualAllocation) {
                $manualSub = $subCpmks->first(
                    fn ($candidate) => (string) $candidate->id === (string) $current->rps_sub_cpmk_id
                );

                if ($manualSub) {
                    $sub = $manualSub;
                    $subKey = (string) $sub->id;
                    $occurrence = $manualOccurrence[$subKey] ?? 0;
                    $manualOccurrence[$subKey] = $occurrence + 1;
                    $materialText = $this->materialForAllocatedWeek(
                        $sub,
                        $materials,
                        $occurrence,
                        max(1, (int) ($manualAllocationCounts[$subKey] ?? 1))
                    );
                }
            }

            $method = $hasPracticum
'''
if old not in s:
    raise SystemExit('smart draft sub/material marker not found')
s = s.replace(old, new, 1)

old_source = "                'source_type' => 'smart_draft',\n"
new_source = "                'source_type' => $manualAllocation ? 'manual_allocation' : 'smart_draft',\n"
if old_source not in s:
    raise SystemExit('smart draft source_type marker not found')
s = s.replace(old_source, new_source, 1)

helper_marker = "    private function materialRelevanceScore(string $subDescription, string $materialTitle): int\n"
if 'private function materialForAllocatedWeek' not in s:
    helper = r'''    private function materialForAllocatedWeek(
        object $sub,
        $materials,
        int $occurrence,
        int $weekCount
    ): ?string {
        $linkedMaterialIds = [];

        if (Schema::hasTable('rps_material_subcpmks')) {
            $linkedMaterialIds = DB::table('rps_material_subcpmks')
                ->where('rps_sub_cpmk_id', $sub->id)
                ->pluck('rps_material_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $titles = $materials
            ->values()
            ->map(function ($material, $index) use ($sub, $linkedMaterialIds): array {
                $title = trim((string) ($material->title ?? ''));
                $direct = filled($material->rps_sub_cpmk_id ?? null)
                    && (string) $material->rps_sub_cpmk_id === (string) $sub->id;
                $pivot = filled($material->id ?? null)
                    && in_array((string) $material->id, $linkedMaterialIds, true);

                return [
                    'title' => $title,
                    'index' => (int) $index,
                    'linked' => $direct || $pivot,
                    'score' => $this->materialRelevanceScore(
                        (string) ($sub->description ?? ''),
                        $title
                    ),
                ];
            })
            ->filter(fn (array $item) =>
                $item['title'] !== '' && ($item['linked'] || $item['score'] > 0)
            )
            ->sort(function (array $a, array $b): int {
                if ($a['linked'] !== $b['linked']) {
                    return $a['linked'] ? -1 : 1;
                }
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                return $a['index'] <=> $b['index'];
            })
            ->pluck('title')
            ->unique()
            ->values()
            ->all();

        if ($titles === []) {
            return null;
        }

        $groups = $this->splitMaterialsAcrossWeeks($titles, max(1, $weekCount), $sub);
        return $groups[$occurrence] ?? end($groups) ?: null;
    }

'''
    if helper_marker not in s:
        raise SystemExit('material helper marker not found')
    s = s.replace(helper_marker, helper + helper_marker, 1)
p.write_text(s, encoding='utf-8')

# AI per-week fallback: honor manual allocation first
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
method_start = s.find('    private function targetSubCpmkForWeek(')
if method_start < 0:
    raise SystemExit('targetSubCpmkForWeek not found')
position_check = "        if ($position === false) {\n            return null;\n        }\n"
pos = s.find(position_check, method_start)
if pos < 0:
    raise SystemExit('target week position block not found')
insert_pos = pos + len(position_check)
manual_target = r'''

        $manualSubId = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $week)
            ->where('source_type', 'manual_allocation')
            ->value('rps_sub_cpmk_id');

        if ($manualSubId) {
            $manualSub = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $versionId)
                ->where('id', $manualSubId)
                ->first(['id', 'code', 'sequence_no', 'description', 'bloom_level']);

            if ($manualSub) {
                return $manualSub;
            }
        }
'''
if "where('source_type', 'manual_allocation')" not in s[method_start:method_start+3500]:
    s = s[:insert_pos] + manual_target + s[insert_pos:]
p.write_text(s, encoding='utf-8')

# Frontend show.tsx
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')
state_line = "    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);\n"
if 'meetingPlannerOpen' not in s:
    if state_line not in s:
        raise SystemExit('frontend state marker not found')
    s = s.replace(state_line, state_line + "    const [meetingPlannerOpen, setMeetingPlannerOpen] = useState(false);\n", 1)

notify_line = "            <ActionNotifications />\n"
modal_render = r'''            <ActionNotifications />
            {meetingPlannerOpen && (
                <SubCpmkMeetingPlanner
                    rpsId={rps.id}
                    subCpmks={subCpmks}
                    weeks={weeks}
                    onClose={() => setMeetingPlannerOpen(false)}
                />
            )}
'''
if '<SubCpmkMeetingPlanner' not in s:
    if notify_line not in s:
        raise SystemExit('frontend notification marker not found')
    s = s.replace(notify_line, modal_render, 1)

button_marker = """                                Rapikan Sub-CPMK
                            </button>
                            <button
                                type=\"button\"
                                onClick={() => router.post(
                                    `/rps/${rps.id}/weeks/apply-time-standard`,
"""
button_repl = """                                Rapikan Sub-CPMK
                            </button>
                            <button
                                type=\"button\"
                                onClick={() => setMeetingPlannerOpen(true)}
                                className=\"rounded-lg border border-emerald-600 bg-emerald-600 px-2.5 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-emerald-700\"
                                title=\"Tetapkan jumlah pertemuan untuk setiap Sub-CPMK\"
                            >
                                Atur Pertemuan
                            </button>
                            <button
                                type=\"button\"
                                onClick={() => router.post(
                                    `/rps/${rps.id}/weeks/apply-time-standard`,
"""
if 'Tetapkan jumlah pertemuan untuk setiap Sub-CPMK' not in s:
    if button_marker not in s:
        raise SystemExit('frontend button marker not found')
    s = s.replace(button_marker, button_repl, 1)

component_marker = "function DocumentWeekRow({\n"
if 'function SubCpmkMeetingPlanner(' not in s:
    component = r'''function SubCpmkMeetingPlanner({ rpsId, subCpmks, weeks, onClose }: any) {
    const currentAllocations = useMemo(() => {
        const counts: Record<string, number> = {};
        subCpmks.forEach((sub: any) => { counts[sub.id] = 0; });

        weeks
            .filter((week: any) => TEACHING_WEEKS.includes(Number(week.week_number)))
            .forEach((week: any) => {
                if (week.rps_sub_cpmk_id && counts[week.rps_sub_cpmk_id] !== undefined) {
                    counts[week.rps_sub_cpmk_id] += 1;
                }
            });

        // Untuk RPS yang belum pernah dialokasikan, setiap Sub-CPMK diberi
        // nilai awal 1 agar dosen tinggal membagi sisa pertemuan.
        subCpmks.forEach((sub: any) => {
            if (counts[sub.id] < 1) counts[sub.id] = 1;
        });

        return counts;
    }, [subCpmks, weeks]);

    const [allocations, setAllocations] = useState<Record<string, number>>(currentAllocations);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setAllocations(currentAllocations);
    }, [currentAllocations]);

    const total = Object.values(allocations).reduce((sum, value) => sum + Number(value || 0), 0);
    const remaining = 14 - total;

    const save = () => {
        if (total !== 14 || saving) return;
        setSaving(true);

        router.post(
            `/rps/${rpsId}/weeks/allocate-subcpmk`,
            { allocations },
            {
                preserveScroll: true,
                onSuccess: () => {
                    notify('success', 'Jumlah pertemuan per Sub-CPMK berhasil disimpan.');
                    onClose();
                },
                onError: (errors) => notify('error', firstError(errors)),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/45 p-4 print:hidden">
            <div className="max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div className="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-5 py-4">
                    <div>
                        <h3 className="text-base font-extrabold text-slate-900">Atur Pertemuan Sub-CPMK</h3>
                        <p className="mt-1 text-xs text-slate-500">
                            Tetapkan jumlah pertemuan pembelajaran untuk setiap Sub-CPMK. UTS dan UAS tidak dihitung.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                    >
                        Tutup
                    </button>
                </div>

                <div className="p-5">
                    <div className="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                        Jumlah yang ditetapkan dosen menjadi acuan utama. <strong>Lengkapi RPS Otomatis</strong> dan <strong>Susun AI</strong> akan mengikuti alokasi ini, bukan membagi ulang Sub-CPMK.
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <table className="w-full border-collapse text-xs">
                            <thead className="bg-slate-50 text-slate-700">
                                <tr>
                                    <th className="border-b border-slate-200 px-3 py-2 text-left">Sub-CPMK</th>
                                    <th className="border-b border-slate-200 px-3 py-2 text-left">Rumusan</th>
                                    <th className="w-36 border-b border-slate-200 px-3 py-2 text-center">Pertemuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subCpmks.map((sub: any) => (
                                    <tr key={sub.id} className="align-top">
                                        <td className="border-b border-slate-100 px-3 py-2 font-bold text-slate-800">{sub.code}</td>
                                        <td className="border-b border-slate-100 px-3 py-2 text-slate-600">{sub.description}</td>
                                        <td className="border-b border-slate-100 px-3 py-2 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                max="14"
                                                value={allocations[sub.id] ?? 1}
                                                onChange={(e) => setAllocations((current) => ({
                                                    ...current,
                                                    [sub.id]: Math.max(1, Math.min(14, Number(e.target.value || 1))),
                                                }))}
                                                className="w-20 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center font-bold text-slate-800"
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className={`mt-4 flex items-center justify-between rounded-xl border px-4 py-3 ${total === 14 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
                        <div>
                            <div className="text-xs font-bold text-slate-700">Total pertemuan</div>
                            <div className="mt-0.5 text-[11px] text-slate-500">
                                {total === 14
                                    ? 'Sudah tepat 14 pertemuan efektif.'
                                    : remaining > 0
                                      ? `Masih perlu dialokasikan ${remaining} pertemuan.`
                                      : `Kelebihan ${Math.abs(remaining)} pertemuan.`}
                            </div>
                        </div>
                        <div className={`text-2xl font-extrabold ${total === 14 ? 'text-emerald-700' : 'text-amber-700'}`}>
                            {total}/14
                        </div>
                    </div>

                    <div className="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            disabled={total !== 14 || saving}
                            onClick={save}
                            className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {saving ? 'Menyimpan...' : 'Simpan Alokasi'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

'''
    if component_marker not in s:
        raise SystemExit('frontend component marker not found')
    s = s.replace(component_marker, component + component_marker, 1)

p.write_text(s, encoding='utf-8')
