from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"Missing marker: {label}")
    return text.replace(old, new, 1)

# ---------------------------------------------------------------------------
# 1. RpsController: expose a persistent allocation-ready flag to Inertia.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsController.php')
s = p.read_text(encoding='utf-8')
marker = """        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get();

        $assessments = Schema::hasTable('assessments')
"""
replacement = """        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->orderBy('week_number')
            ->get();

        $teachingWeekNumbers = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $meetingPlanReady = $weeks
            ->filter(fn ($week) =>
                in_array((int) $week->week_number, $teachingWeekNumbers, true)
                && filled($week->rps_sub_cpmk_id ?? null)
                && str_starts_with((string) ($week->source_type ?? ''), 'manual_allocation')
            )
            ->count() === count($teachingWeekNumbers);

        $assessments = Schema::hasTable('assessments')
"""
s = replace_once(s, marker, replacement, 'RpsController meeting ready calculation')
s = replace_once(
    s,
    """            'weeks' => $weeks,
            'assessments' => $assessments,
""",
    """            'weeks' => $weeks,
            'meetingPlanReady' => $meetingPlanReady,
            'assessments' => $assessments,
""",
    'RpsController meeting ready prop',
)
p.write_text(s, encoding='utf-8')

# ---------------------------------------------------------------------------
# 2. RpsAutomationController: protect deterministic/legacy weekly writers.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsAutomationController.php')
s = p.read_text(encoding='utf-8')
# smartDraft
s = replace_once(
    s,
    """        [$record, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['fill_empty', 'overwrite'])],
""",
    """        [$record, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['fill_empty', 'overwrite'])],
""",
    'smartDraft hard gate',
)
# copyPrevious
s = replace_once(
    s,
    """        [, $version] = $this->context($request, $rps);

        $service->copyPreviousWeek($version->id, $week);
""",
    """        [, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $service->copyPreviousWeek($version->id, $week);
""",
    'copy previous hard gate',
)
# applyMethod
s = replace_once(
    s,
    """        [, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'weeks' => ['required', 'array', 'min:1'],
""",
    """        [, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $validated = $request->validate([
            'weeks' => ['required', 'array', 'min:1'],
""",
    'apply method hard gate',
)
# clearWeeklyContent: commit 4ba8c6e3 added this method. Insert gate in that method only.
clear_sig = "public function clearWeeklyContent"
if clear_sig not in s:
    raise SystemExit('Missing clearWeeklyContent from commit 4ba8c6e3')
start = s.index(clear_sig)
ctx = "        [, $version] = $this->context($request, $rps);\n"
pos = s.find(ctx, start)
if pos < 0:
    raise SystemExit('Missing clearWeeklyContent context marker')
pos += len(ctx)
s = s[:pos] + "        $this->assertMeetingAllocationConfigured($version->id);\n" + s[pos:]
# helper
helper_marker = """    private function isManualAllocationSource(string $source): bool
"""
helper_code = """    private function assertMeetingAllocationConfigured(string $versionId): void
    {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        $configured = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $teachingWeeks)
            ->whereNotNull('rps_sub_cpmk_id')
            ->where('source_type', 'like', 'manual_allocation%')
            ->count();

        if ($configured !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'weeks' => 'Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu. Setelah total 14/14 disimpan, penyusunan RPS pekanan akan aktif.',
            ]);
        }
    }

    private function isManualAllocationSource(string $source): bool
"""
s = replace_once(s, helper_marker, helper_code, 'automation hard gate helper')
p.write_text(s, encoding='utf-8')

# ---------------------------------------------------------------------------
# 3. ObeWorkspaceController: block manual weekly editing and make the old
#    time utility a safe "Lengkapi Data Teknis" action only.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')
# Gate updateWeek.
s = replace_once(
    s,
    """    public function updateWeek(Request $request, string $rps, int $week, RpsAssessmentSyncService $assessmentSync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        abort_unless($week >= 1 && $week <= 16, 404);
""",
    """    public function updateWeek(Request $request, string $rps, int $week, RpsAssessmentSyncService $assessmentSync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        abort_unless($week >= 1 && $week <= 16, 404);
""",
    'updateWeek hard gate',
)
# Replace applyTimeStandard with a strictly technical helper.
start_marker = """    public function applyTimeStandard(
        Request $request,
        string $rps
    ): RedirectResponse {
"""
end_marker = """    public function normalizeReferences(
"""
start = s.find(start_marker)
end = s.find(end_marker, start)
if start < 0 or end < 0:
    raise SystemExit('Missing applyTimeStandard block')
new_method = """    public function applyTimeStandard(
        Request $request,
        string $rps
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $credits = max(1, (int) (
            DB::table('courses')->where('id', $record->course_id)->value('credits') ?? 1
        ));

        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->whereIn('week_number', $teachingWeeks)
            ->get();

        $updated = 0;

        DB::transaction(function () use ($weeks, $credits, &$updated): void {
            foreach ($weeks as $week) {
                $face = max(1, (int) ($week->face_to_face_sessions ?? 0));
                $structured = max(1, (int) ($week->structured_task_sessions ?? 0));
                $independent = max(1, (int) ($week->independent_study_sessions ?? 0));

                $technical = [
                    'learning_form' => filled($week->learning_form ?? null)
                        ? $week->learning_form
                        : 'Kuliah tatap muka',
                    'face_to_face_sessions' => $face,
                    'structured_task_sessions' => $structured,
                    'independent_study_sessions' => $independent,
                    'time_estimate' => "Tatap muka: {$face} × ({$credits} × 50 menit); "
                        ."Tugas terstruktur: {$structured} × ({$credits} × 60 menit); "
                        ."Belajar mandiri: {$independent} × ({$credits} × 60 menit)",
                    'updated_at' => now(),
                ];

                DB::table('rps_weekly_plans')
                    ->where('id', $week->id)
                    ->update($technical);
                $updated++;
            }
        });

        return back()->with(
            'success',
            "Data teknis {$updated} pekan dilengkapi: bentuk pembelajaran dasar dan estimasi waktu sesuai {$credits} SKS. Isi akademik tidak diubah."
        );
    }

"""
s = s[:start] + new_method + s[end:]
# Add helper before nextAvailableSubSequence.
helper_marker = """    private function nextAvailableSubSequence(string $versionId): int
"""
helper_code = """    private function assertMeetingAllocationConfigured(string $versionId): void
    {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        $configured = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $teachingWeeks)
            ->whereNotNull('rps_sub_cpmk_id')
            ->where('source_type', 'like', 'manual_allocation%')
            ->count();

        if ($configured !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'weeks' => 'Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu. Setelah total 14/14 disimpan, editor pekanan akan aktif.',
            ]);
        }
    }

    private function nextAvailableSubSequence(string $versionId): int
"""
s = replace_once(s, helper_marker, helper_code, 'workspace hard gate helper')
p.write_text(s, encoding='utf-8')

# ---------------------------------------------------------------------------
# 4. RpsAiController: AI per week and legacy weekly-plan generation are gated.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
# generic generate: after validation gate only weekly_plan.
validation_end = """        $context = $contextService->build($record, $version, $data['suggestion_type']);
"""
s = replace_once(
    s,
    validation_end,
    """        if ($data['suggestion_type'] === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }

        $context = $contextService->build($record, $version, $data['suggestion_type']);
""",
    'generic weekly AI gate',
)
# generateWeek hard gate.
s = replace_once(
    s,
    """        [$record, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:3000'],
            'overwrite' => ['nullable', 'boolean'],
""",
    """        [$record, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:3000'],
            'overwrite' => ['nullable', 'boolean'],
""",
    'AI week hard gate',
)
# gate applying legacy weekly_plan suggestions.
s = replace_once(
    s,
    """        $row = $this->suggestion($version->id, $suggestion);

        if ($row->status !== 'pending') {
""",
    """        $row = $this->suggestion($version->id, $suggestion);

        if ($row->suggestion_type === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }

        if ($row->status !== 'pending') {
""",
    'apply weekly AI gate',
)
# Add helper immediately before the controller context helper.
context_marker = """    private function context(Request $request, string $rps): array
"""
helper_code = """    private function assertMeetingAllocationConfigured(string $versionId): void
    {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        $configured = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $teachingWeeks)
            ->whereNotNull('rps_sub_cpmk_id')
            ->where('source_type', 'like', 'manual_allocation%')
            ->count();

        if ($configured !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'ai' => 'Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu. Setelah total 14/14 disimpan, Susun AI per pekan akan aktif.',
            ]);
        }
    }

    private function context(Request $request, string $rps): array
"""
s = replace_once(s, context_marker, helper_code, 'AI hard gate helper')
p.write_text(s, encoding='utf-8')

# ---------------------------------------------------------------------------
# 5. React UI: actual hard gate + technical utility; keep reset button.
# ---------------------------------------------------------------------------
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')
# prop
s = replace_once(
    s,
    """        weeks = [],
        assessments = [],
""",
    """        weeks = [],
        meetingPlanReady = false,
        assessments = [],
""",
    'meetingPlanReady prop',
)
# toolbar content. Use stable start/end anchors.
tool_start = s.find('<div id="validator-target-weeks"')
if tool_start < 0:
    raise SystemExit('Missing weekly toolbar start')
table_comment = s.find('                    {/* Weekly table, exact print columns */}', tool_start)
if table_comment < 0:
    raise SystemExit('Missing weekly table comment')
old_toolbar = s[tool_start:table_comment]
if 'Kosongkan Isi Pekanan' not in old_toolbar:
    raise SystemExit('Reset button from 4ba8c6e3 is missing')
new_toolbar = '''<div id="validator-target-weeks" className="scroll-mt-24 flex flex-wrap items-center justify-between gap-2 border-x border-t border-slate-300 bg-slate-50 px-3 py-2 print:hidden">
                        <div>
                            <div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>
                            <div className={`mt-1 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[9px] font-bold ${meetingPlanReady ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}`}>
                                {meetingPlanReady
                                    ? 'Alokasi 14 pertemuan sudah ditetapkan. Edit Pekan dan Susun AI sudah aktif.'
                                    : 'Wajib: Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu (14/14).'}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                onClick={() => setMeetingPlannerOpen(true)}
                                className={`rounded-lg border px-2.5 py-1.5 text-[11px] font-extrabold text-white shadow-sm ${meetingPlanReady ? 'border-emerald-600 bg-emerald-600 hover:bg-emerald-700' : 'border-amber-600 bg-amber-600 hover:bg-amber-700 ring-2 ring-amber-200'}`}
                                title="Tetapkan jumlah pertemuan untuk setiap Sub-CPMK sebelum menyusun isi pekanan"
                            >
                                1. Atur Pertemuan
                            </button>
                            <button
                                type="button"
                                disabled={!meetingPlanReady}
                                onClick={() => router.post(
                                    `/rps/${rps.id}/weeks/apply-time-standard`,
                                    {},
                                    actionOptions('Data teknis pekanan berhasil dilengkapi tanpa mengubah isi akademik.'),
                                )}
                                className="rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1.5 text-[10px] font-bold text-sky-700 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-35"
                                title={meetingPlanReady
                                    ? 'Mengisi bentuk pembelajaran dasar dan estimasi waktu sesuai SKS. Tidak membuat indikator, materi, aktivitas, kriteria, atau tugas.'
                                    : 'Selesaikan Atur Pertemuan terlebih dahulu.'}
                            >
                                Lengkapi Data Teknis
                            </button>
                            <button
                                type="button"
                                disabled={!meetingPlanReady}
                                onClick={() => {
                                    if (!confirm('Kosongkan seluruh isi akademik 14 pekan pembelajaran? Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;
                                    router.post(
                                        `/rps/${rps.id}/weeks/clear-content`,
                                        {},
                                        actionOptions('Isi pekanan dikosongkan. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),
                                    );
                                }}
                                className="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35"
                                title={meetingPlanReady ? 'Reset isi akademik pekanan tanpa menghapus struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}
                            >
                                Kosongkan Isi Pekanan
                            </button>
                        </div>
                    </div>

'''
s = s[:tool_start] + new_toolbar + s[table_comment:]
# pass gate to rows
s = replace_once(
    s,
    """                                        aiConfigured={ai.configured}
                                        aiBusy={aiBusyWeek === week.week_number}
""",
    """                                        aiConfigured={ai.configured}
                                        meetingPlanReady={meetingPlanReady}
                                        aiBusy={aiBusyWeek === week.week_number}
""",
    'pass meeting gate to row',
)
# function signature
s = replace_once(
    s,
    """    bibliography,
    aiConfigured,
    aiBusy,
""",
    """    bibliography,
    aiConfigured,
    meetingPlanReady,
    aiBusy,
""",
    'DocumentWeekRow gate prop',
)
# Edit button disabled + title.
s = replace_once(
    s,
    """                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="rounded-lg border border-sky-700 bg-sky-600 px-2 py-1.5 text-[9px] font-extrabold text-white shadow-sm transition hover:bg-sky-700"
                    >
                        Edit Pekan
                    </button>
""",
    """                    <button
                        type="button"
                        disabled={!meetingPlanReady}
                        onClick={() => setEditing(true)}
                        title={meetingPlanReady ? 'Edit isi pekan' : 'Atur Pertemuan harus disimpan 14/14 terlebih dahulu.'}
                        className="rounded-lg border border-sky-700 bg-sky-600 px-2 py-1.5 text-[9px] font-extrabold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
                    >
                        Edit Pekan
                    </button>
""",
    'Edit Pekan hard gate',
)
# AI button gate/title.
s = replace_once(
    s,
    """                        disabled={!aiConfigured || aiBusy}
                        onClick={() => onGenerateAi(info.count >= 7)}
                        title="Susun rekomendasi AI untuk pekan ini"
""",
    """                        disabled={!meetingPlanReady || !aiConfigured || aiBusy}
                        onClick={() => onGenerateAi(info.count >= 7)}
                        title={!meetingPlanReady
                            ? 'Atur Pertemuan harus disimpan 14/14 terlebih dahulu.'
                            : 'Susun rekomendasi AI untuk pekan ini'}
""",
    'Susun AI hard gate',
)
p.write_text(s, encoding='utf-8')

print('Weekly hard gate patch applied.')
