from pathlib import Path

root = Path('.')

# RpsAutomationController: resync after meeting allocation and copy previous.
p = root / 'app/Http/Controllers/RpsAutomationController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('use App\\Services\\Rps\\RpsSmartDraftService;\n', 'use App\\Services\\Rps\\RpsSmartDraftService;\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
old = '''    public function allocateSubCpmkMeetings(
        Request $request,
        string $rps
    ): RedirectResponse {'''
new = '''    public function allocateSubCpmkMeetings(
        Request $request,
        string $rps,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {'''
if old not in s: raise SystemExit('allocate signature missing')
s = s.replace(old, new, 1)
old = '''        return back()->with(
            'success',
            'Alokasi pertemuan Sub-CPMK disimpan. Isi otomatis dapat disegarkan langsung dengan Lengkapi RPS Otomatis tanpa mengosongkan 14 pertemuan; edit manual dan AI tetap dilindungi.'
        );'''
new = '''        $assessmentSync->syncVersion($version->id);

        return back()->with(
            'success',
            'Alokasi pertemuan Sub-CPMK disimpan. Tag asesmen, bobot pekan, RTM, matriks, dan simulasi langsung disinkronkan ke alokasi terbaru.'
        );'''
if old not in s: raise SystemExit('allocate return missing')
s = s.replace(old, new, 1)
old = '''    public function copyPrevious(
        Request $request,
        string $rps,
        int $week,
        RpsSmartDraftService $service
    ): RedirectResponse {'''
new = '''    public function copyPrevious(
        Request $request,
        string $rps,
        int $week,
        RpsSmartDraftService $service,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {'''
if old not in s: raise SystemExit('copy signature missing')
s = s.replace(old, new, 1)
s = s.replace("        $service->copyPreviousWeek($version->id, $week);\n\n        return back()->with('success', \"Minggu {$week} menyalin draft minggu sebelumnya.\");", "        $service->copyPreviousWeek($version->id, $week);\n        $assessmentSync->syncVersion($version->id);\n\n        return back()->with('success', \"Minggu {$week} menyalin draft minggu sebelumnya dan rantai asesmen disinkronkan.\");", 1)
p.write_text(s, encoding='utf-8')

# ObeWorkspaceController: resync whenever a week Sub-CPMK changes, including dormant align utility.
p = root / 'app/Http/Controllers/ObeWorkspaceController.php'
s = p.read_text(encoding='utf-8')
s = s.replace('use App\\Services\\Rps\\RpsSyllabusService;\n', 'use App\\Services\\Rps\\RpsSyllabusService;\nuse App\\Services\\Rps\\RpsAssessmentSyncService;\n')
old = '''    public function alignSubCpmkSequence(
        Request $request,
        string $rps
    ): RedirectResponse {'''
new = '''    public function alignSubCpmkSequence(
        Request $request,
        string $rps,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {'''
if old not in s: raise SystemExit('align signature missing')
s = s.replace(old, new, 1)
old = '''        return back()->with(
            'success',
            'Alur Sub-CPMK minggu pembelajaran dirapikan secara berurutan. UTS/UAS tidak diubah.'
        );'''
new = '''        $assessmentSync->syncVersion($version->id);

        return back()->with(
            'success',
            'Alur Sub-CPMK minggu pembelajaran dirapikan dan rantai asesmen disinkronkan. UTS/UAS tidak diubah.'
        );'''
if old not in s: raise SystemExit('align return missing')
s = s.replace(old, new, 1)
s = s.replace('public function updateWeek(Request $request, string $rps, int $week): RedirectResponse', 'public function updateWeek(Request $request, string $rps, int $week, RpsAssessmentSyncService $assessmentSync): RedirectResponse')
needle = '''        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($payload);

        return back()->with('''
if needle not in s: raise SystemExit('updateWeek update marker missing')
s = s.replace(needle, '''        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($payload);

        if (! $isExam) {
            $assessmentSync->syncVersion($version->id);
        }

        return back()->with(''', 1)
p.write_text(s, encoding='utf-8')

# RpsAiController: full weekly-plan AI must also resync assessment chain after changing weekly Sub-CPMK.
p = root / 'app/Http/Controllers/RpsAiController.php'
s = p.read_text(encoding='utf-8')
old = '''        return [
            'changed' => 14,
            'message' => 'Rencana 14 minggu AI diterapkan ke workspace.',
        ];
    }

    private function applyAssessmentPlanSelective'''
new = '''        app(RpsAssessmentSyncService::class)->syncVersion($version->id);

        return [
            'changed' => 14,
            'message' => 'Rencana 14 minggu AI diterapkan ke workspace dan rantai asesmen disinkronkan.',
        ];
    }

    private function applyAssessmentPlanSelective'''
if old not in s: raise SystemExit('AI weekly return marker missing')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

for path, marker in [
    ('app/Http/Controllers/RpsAutomationController.php', 'Tag asesmen, bobot pekan, RTM, matriks, dan simulasi langsung disinkronkan'),
    ('app/Http/Controllers/ObeWorkspaceController.php', '$assessmentSync->syncVersion($version->id);'),
    ('app/Http/Controllers/RpsAiController.php', 'rantai asesmen disinkronkan'),
]:
    text = (root/path).read_text(encoding='utf-8')
    if marker not in text:
        raise SystemExit(f'missing {marker} in {path}')

print('Assessment chain resync triggers applied.')
