from pathlib import Path
import re


def read(path):
    return Path(path).read_text(encoding='utf-8')


def write(path, text):
    Path(path).write_text(text, encoding='utf-8')


def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit(f'target not found: {label}')
    return text.replace(old, new, 1)


# 1) Weekly weight endpoint: weekly weights are canonical distribution for teaching weeks.
path = 'app/Http/Controllers/RpsDocumentController.php'
s = read(path)
old = '''    public function updateWeekWeight(\n        Request $request,\n        string $rps,\n        int $week\n    ): RedirectResponse {\n        $this->context($request, $rps);\n\n        throw ValidationException::withMessages([\n            'weight' => 'Bobot penilaian tidak diedit dari tabel RPS atau simulasi. Ubah bobot melalui Edit Detail Asesmen; tabel RPS, Tabel Penilaian dan Evaluasi CPL, serta Simulasi akan tersinkron otomatis.',\n        ]);\n    }\n'''
new = '''    public function updateWeekWeight(\n        Request $request,\n        string $rps,\n        int $week\n    ): RedirectResponse {\n        [, $version] = $this->context($request, $rps);\n\n        abort_unless($week >= 1 && $week <= 16, 422);\n\n        if (in_array($week, [8, 16], true)) {\n            throw ValidationException::withMessages([\n                'weight' => 'Bobot UTS/UAS diatur pada asesmen sistem. Bobot pekan 8 dan 16 akan tersinkron otomatis.',\n            ]);\n        }\n\n        $data = $request->validate([\n            'weight' => ['required', 'numeric', 'min:0', 'max:100'],\n        ]);\n\n        $row = DB::table('rps_weekly_plans')\n            ->where('rps_version_id', $version->id)\n            ->where('week_number', $week)\n            ->first(['id', 'assessment_weight']);\n\n        abort_unless($row, 404);\n\n        $newWeight = round((float) $data['weight'], 2);\n        $oldWeight = round((float) ($row->assessment_weight ?? 0), 2);\n\n        $nonExamAssessmentBudget = round(\n            (float) DB::table('assessments')\n                ->where('rps_version_id', $version->id)\n                ->whereNotIn('type', ['uts', 'uas'])\n                ->sum(DB::raw('COALESCE(weight, 0)')),\n            2\n        );\n\n        $examWeight = round(\n            (float) DB::table('assessments')\n                ->where('rps_version_id', $version->id)\n                ->whereIn('type', ['uts', 'uas'])\n                ->sum(DB::raw('COALESCE(weight, 0)')),\n            2\n        );\n\n        $teachingTotal = round(\n            (float) DB::table('rps_weekly_plans')\n                ->where('rps_version_id', $version->id)\n                ->whereNotIn('week_number', [8, 16])\n                ->sum(DB::raw('COALESCE(assessment_weight, 0)')),\n            2\n        );\n\n        // Jika asesmen agregat non-ujian sudah disusun, itulah plafon distribusi\n        // 14 pekan. Selama belum disusun, gunakan sisa dari 100% agar dosen tetap\n        // dapat mengisi bobot pekan lebih dulu. Validator akan menandai mismatch.\n        $teachingBudget = $nonExamAssessmentBudget > 0\n            ? $nonExamAssessmentBudget\n            : max(0, round(100 - $examWeight, 2));\n\n        $currentTeachingTotal = $teachingTotal;\n        $projectedTeachingTotal = round(\n            $teachingTotal - $oldWeight + $newWeight,\n            2\n        );\n\n        if (\n            $projectedTeachingTotal > ($teachingBudget + 0.001)\n            && $projectedTeachingTotal > ($currentTeachingTotal + 0.001)\n        ) {\n            throw ValidationException::withMessages([\n                'weight' => \"Total bobot 14 pekan akan menjadi {$projectedTeachingTotal}%, melebihi anggaran asesmen non-UTS/UAS {$teachingBudget}%. Turunkan bobot pekan lain atau sesuaikan rekap asesmen terlebih dahulu.\",\n            ]);\n        }\n\n        DB::table('rps_weekly_plans')\n            ->where('id', $row->id)\n            ->update([\n                'assessment_weight' => $newWeight,\n                'updated_at' => now(),\n            ]);\n\n        return back()->with(\n            'success',\n            \"Bobot pengukuran minggu {$week} disimpan {$newWeight}%.\"\n        );\n    }\n'''
s = replace_once(s, old, new, 'RpsDocumentController::updateWeekWeight')
write(path, s)


# 2) Assessment detail remains aggregate budget, but only exam weights sync directly to weeks.
path = 'app/Http/Controllers/RpsAssessmentController.php'
s = read(path)
s = replace_once(
    s,
    '''        if (filled($validated['week_number'] ?? null)) {\n            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);\n        }\n''',
    '''        if (\n            in_array($validated['type'], ['uts', 'uas'], true)\n            && filled($validated['week_number'] ?? null)\n        ) {\n            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);\n        }\n''',
    'assessment store exam-only sync',
)

old = '''        $this->assertWeightWithinLimit(\n            $version->id,\n            $validated['weight'] ?? null,\n            $assessment\n        );\n'''
new = old  # aggregate assessment total remains canonical 100% budget
# Keep exact logic; marker assertion only.
if old not in s:
    raise SystemExit('target not found: assessment update weight guard')

old_sync = '''        if ($oldWeek) {\n            $this->syncWeekPrintWeight($version->id, $oldWeek);\n        }\n\n        if (filled($validated['week_number'] ?? null)) {\n            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);\n        }\n'''
new_sync = '''        $isExamAssessment = in_array($validated['type'], ['uts', 'uas'], true);\n\n        if ($isExamAssessment && $oldWeek) {\n            $this->syncWeekPrintWeight($version->id, $oldWeek);\n        }\n\n        if ($isExamAssessment && filled($validated['week_number'] ?? null)) {\n            $this->syncWeekPrintWeight($version->id, (int) $validated['week_number']);\n        }\n'''
s = replace_once(s, old_sync, new_sync, 'assessment update exam-only sync')

old_destroy = '''        if ($oldWeek) {\n            $this->syncWeekPrintWeight($version->id, $oldWeek);\n        }\n\n        return back()->with('success', 'Asesmen dihapus.');\n'''
new_destroy = '''        // Asesmen non-UTS/UAS adalah rekap/instrumen agregat dan tidak lagi\n        // menjadi sumber langsung bobot pekan. Karena UTS/UAS tidak dapat\n        // dihapus, penghapusan asesmen biasa tidak perlu menyentuh bobot pekan.\n\n        return back()->with('success', 'Asesmen dihapus. Distribusi bobot pekan tidak diubah.');\n'''
s = replace_once(s, old_destroy, new_destroy, 'assessment destroy no weekly sync')

old_matrix_guard = '''        if ($request->has('weight')) {\n            throw ValidationException::withMessages([\n                'weight' => 'Bobot hanya dapat diubah melalui Edit Detail Asesmen. Tabel Penilaian dan Evaluasi CPL hanya menampilkan bobot hasil sinkronisasi.',\n            ]);\n        }\n\n        $validated = $request->validate([\n            'name' => ['nullable', 'string', 'max:500'],\n            'sub_cpmk_ids' => ['sometimes', 'array', 'min:1'],\n'''
new_matrix_guard = '''        $validated = $request->validate([\n            'name' => ['nullable', 'string', 'max:500'],\n            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],\n            'sub_cpmk_ids' => ['sometimes', 'array', 'min:1'],\n'''
s = replace_once(s, old_matrix_guard, new_matrix_guard, 'matrix allow aggregate weight')

old_updates = '''        $updates = [];\n\n        if (array_key_exists('name', $validated) && filled($validated['name'])) {\n            $updates['name'] = trim((string) $validated['name']);\n        }\n\n        DB::transaction(function () use ($assessment, $version, $validated, $updates): void {\n'''
new_updates = '''        $updates = [];\n\n        if (array_key_exists('name', $validated) && filled($validated['name'])) {\n            $updates['name'] = trim((string) $validated['name']);\n        }\n\n        if (array_key_exists('weight', $validated)) {\n            $this->assertWeightWithinLimit(\n                $version->id,\n                $validated['weight'],\n                $assessment\n            );\n            $updates['weight'] = round((float) ($validated['weight'] ?? 0), 2);\n        }\n\n        DB::transaction(function () use ($assessment, $version, $validated, $updates): void {\n'''
s = replace_once(s, old_updates, new_updates, 'matrix update aggregate weight')

old_matrix_return = '''        return back()->with(\n            'success',\n            'Pemetaan Sub-CPMK pada Tabel Penilaian dan Evaluasi CPL berhasil diperbarui.'\n        );\n'''
new_matrix_return = '''        if (array_key_exists('weight', $validated) && in_array($row->type, ['uts', 'uas'], true)) {\n            $this->syncWeekPrintWeight(\n                $version->id,\n                $row->type === 'uts' ? 8 : 16\n            );\n        }\n\n        return back()->with(\n            'success',\n            array_key_exists('weight', $validated)\n                ? 'Bobot asesmen agregat diperbarui. Jalankan Isi Bagian Kosong bila distribusi bobot pekan perlu dilengkapi.'\n                : 'Pemetaan Sub-CPMK pada Tabel Penilaian dan Evaluasi CPL berhasil diperbarui.'\n        );\n'''
s = replace_once(s, old_matrix_return, new_matrix_return, 'matrix return and exam sync')

old_sum = '''            (float) DB::table('assessments')\n                ->where('rps_version_id', $versionId)\n                ->where('week_number', $week)\n                ->sum(DB::raw('COALESCE(weight, 0)')),\n'''
new_sum = '''            (float) DB::table('assessments')\n                ->where('rps_version_id', $versionId)\n                ->where('week_number', $week)\n                ->whereIn('type', ['uts', 'uas'])\n                ->sum(DB::raw('COALESCE(weight, 0)')),\n'''
s = replace_once(s, old_sum, new_sum, 'syncWeekPrintWeight exam-only query')
write(path, s)


# 3) On read: stored weekly weights are canonical for teaching weeks; exams mirror UTS/UAS aggregate assessments.
path = 'app/Http/Controllers/RpsController.php'
s = read(path)
old_weights = '''        $assessmentWeightsByWeek = $assessments\n            ->filter(fn ($assessment) => filled($assessment->week_number))\n            ->groupBy(fn ($assessment) => (int) $assessment->week_number)\n'''
new_weights = '''        $assessmentWeightsByWeek = $assessments\n            ->filter(fn ($assessment) =>\n                filled($assessment->week_number)\n                && in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true)\n            )\n            ->groupBy(fn ($assessment) => (int) $assessment->week_number)\n'''
s = replace_once(s, old_weights, new_weights, 'RpsController exam weights map')

old_week_logic = '''            // Jika ada asesmen detail pada pekan ini, bobot asesmen adalah\n            // sumber kebenaran. Ini memastikan perubahan dari\n            // "Edit Detail Asesmen, RTM & Validator OBE" langsung tampil\n            // kembali pada kolom Bobot Penilaian di tabel RPS.\n            $week->assessment_weight = $assessmentWeightsByWeek->has($weekNumber)\n                ? (float) $assessmentWeightsByWeek->get($weekNumber, 0)\n                : (float) ($storedWeight ?? 0);\n'''
new_week_logic = '''            // Untuk 14 pekan pembelajaran, assessment_weight yang tersimpan\n            // pada rps_weekly_plans adalah distribusi bobot pengukuran per pekan.\n            // UTS/UAS tetap mengikuti bobot asesmen sistem dan disinkronkan ke\n            // pekan 8/16 agar kedua representasi konsisten.\n            $week->assessment_weight = in_array($weekNumber, [8, 16], true)\n                && $assessmentWeightsByWeek->has($weekNumber)\n                    ? (float) $assessmentWeightsByWeek->get($weekNumber, 0)\n                    : (float) ($storedWeight ?? 0);\n'''
s = replace_once(s, old_week_logic, new_week_logic, 'RpsController canonical weekly weight')
write(path, s)


# 4) Smart fill: distribute aggregate non-exam budget across Sub-CPMK and meetings, only into empty weights.
path = 'app/Services/Rps/RpsSmartDraftService.php'
s = read(path)
s = replace_once(
    s,
    "                'assessment_method' => 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.',",
    "                'assessment_method' => 'Tugas/latihan terukur, kuis, atau observasi kinerja sesuai aktivitas pembelajaran.',",
    'smart draft measured assessment wording',
)
old_return = '''        $this->fillExamWeeks($version->id, $currentWeeks);\n\n        return [\n            'updated_weeks' => $updated,\n            'mode' => $mode,\n        ];\n'''
new_return = '''        $this->fillExamWeeks($version->id, $currentWeeks);\n        $this->syncExamWeekWeights($version->id);\n        $weightMessage = $this->fillEmptyTeachingWeights($version->id);\n\n        return [\n            'updated_weeks' => $updated,\n            'mode' => $mode,\n            'weight_message' => $weightMessage,\n        ];\n'''
s = replace_once(s, old_return, new_return, 'smart draft weight distribution call')

insert_before = '''    private function ensureExamAssessments(string $versionId, int $userId): void\n'''
methods = r'''    private function syncExamWeekWeights(string $versionId): void
    {
        $weights = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', ['uts', 'uas'])
            ->get(['type', 'weight'])
            ->mapWithKeys(fn ($row) => [strtolower((string) $row->type) => round((float) ($row->weight ?? 0), 2)]);

        foreach ([8 => 'uts', 16 => 'uas'] as $week => $type) {
            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->where('week_number', $week)
                ->update([
                    'assessment_weight' => (float) $weights->get($type, 0),
                    'updated_at' => now(),
                ]);
        }
    }

    private function fillEmptyTeachingWeights(string $versionId): string
    {
        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->get(['type', 'weight']);

        $assessmentTotal = round((float) $assessments->sum(
            fn ($row) => (float) ($row->weight ?? 0)
        ), 2);
        $examTotal = round((float) $assessments
            ->filter(fn ($row) => in_array(strtolower((string) $row->type), ['uts', 'uas'], true))
            ->sum(fn ($row) => (float) ($row->weight ?? 0)), 2);
        $teachingBudget = round($assessmentTotal - $examTotal, 2);

        if (abs($assessmentTotal - 100.0) >= 0.01 || $teachingBudget <= 0) {
            return 'Bobot 14 pekan belum dibagi otomatis karena total bobot asesmen agregat belum tepat 100% atau belum tersedia anggaran asesmen non-UTS/UAS.';
        }

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->orderBy('week_number')
            ->get(['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight']);

        if ($weeks->count() !== count(self::TEACHING_WEEKS)) {
            return 'Distribusi bobot pekan menunggu struktur 14 pekan pembelajaran yang lengkap.';
        }

        if ($weeks->contains(fn ($week) => ! filled($week->rps_sub_cpmk_id ?? null))) {
            return 'Distribusi bobot pekan menunggu setiap pekan memiliki Sub-CPMK.';
        }

        $emptyWeeks = $weeks->filter(
            fn ($week) => (float) ($week->assessment_weight ?? 0) <= 0
        )->values();

        if ($emptyWeeks->isEmpty()) {
            return 'Bobot seluruh pekan pembelajaran sudah terisi; isian manual tidak diubah.';
        }

        $existingCents = (int) round($weeks->sum(
            fn ($week) => max(0, (float) ($week->assessment_weight ?? 0))
        ) * 100);
        $budgetCents = (int) round($teachingBudget * 100);
        $remainingCents = $budgetCents - $existingCents;

        if ($remainingCents <= 0) {
            return 'Anggaran bobot pekan sudah habis oleh isian yang ada; pekan kosong tidak diubah.';
        }

        if ($remainingCents < $emptyWeeks->count()) {
            return 'Sisa bobot terlalu kecil untuk memberi bobot positif pada setiap pekan kosong. Sesuaikan bobot manual terlebih dahulu.';
        }

        // Setiap pekan yang mengukur Sub-CPMK harus memperoleh bobot positif.
        // Beri minimum 0,01% terlebih dahulu, lalu distribusikan sisanya menurut
        // target bobot per Sub-CPMK. Target Sub-CPMK dibagi rata, kemudian target
        // Sub-CPMK dibagi lagi menurut jumlah pertemuannya.
        $allocations = [];
        foreach ($emptyWeeks as $week) {
            $allocations[(string) $week->id] = 1;
        }
        $remainingCents -= $emptyWeeks->count();

        $groups = $weeks->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id);
        $subIds = $groups->keys()->values();
        $subCount = max(1, $subIds->count());
        $baseTarget = intdiv($budgetCents, $subCount);
        $targetRemainder = $budgetCents % $subCount;
        $desiredBySub = [];

        foreach ($subIds as $index => $subId) {
            $group = $groups->get($subId);
            $target = $baseTarget + ($index < $targetRemainder ? 1 : 0);
            $existing = 0;
            $emptyIds = [];

            foreach ($group as $week) {
                $id = (string) $week->id;
                $weightCents = (int) round(max(0, (float) ($week->assessment_weight ?? 0)) * 100);
                if ($weightCents > 0) {
                    $existing += $weightCents;
                } else {
                    $emptyIds[] = $id;
                    $existing += $allocations[$id] ?? 0;
                }
            }

            if ($emptyIds !== []) {
                $desiredBySub[$subId] = [
                    'ids' => $emptyIds,
                    'desired' => max(0, $target - $existing),
                ];
            }
        }

        $totalDesired = array_sum(array_column($desiredBySub, 'desired'));
        $groupPool = min($remainingCents, $totalDesired);
        $assignedGroupPool = 0;
        $entries = array_values($desiredBySub);

        foreach ($entries as $index => $entry) {
            if ($groupPool <= 0 || $entry['desired'] <= 0) {
                continue;
            }

            $isLast = $index === count($entries) - 1;
            $groupAllocation = $totalDesired > 0
                ? ($isLast
                    ? $groupPool - $assignedGroupPool
                    : (int) floor($groupPool * ($entry['desired'] / $totalDesired)))
                : 0;
            $groupAllocation = max(0, min($groupAllocation, $remainingCents - $assignedGroupPool));

            $count = count($entry['ids']);
            $base = $count > 0 ? intdiv($groupAllocation, $count) : 0;
            $remainder = $count > 0 ? $groupAllocation % $count : 0;

            foreach ($entry['ids'] as $position => $id) {
                $allocations[$id] += $base + ($position < $remainder ? 1 : 0);
            }

            $assignedGroupPool += $groupAllocation;
        }

        $remainingCents -= $assignedGroupPool;

        // Jika ada bobot manual yang membuat satu Sub-CPMK melebihi target rata,
        // sisa anggaran tidak boleh hilang. Sebarkan sisa ke semua pekan kosong
        // tanpa mengubah bobot yang sudah diputuskan dosen.
        if ($remainingCents > 0) {
            $ids = array_keys($allocations);
            $base = intdiv($remainingCents, count($ids));
            $remainder = $remainingCents % count($ids);

            foreach ($ids as $index => $id) {
                $allocations[$id] += $base + ($index < $remainder ? 1 : 0);
            }
        }

        foreach ($allocations as $id => $cents) {
            DB::table('rps_weekly_plans')
                ->where('id', $id)
                ->update([
                    'assessment_weight' => round($cents / 100, 2),
                    'updated_at' => now(),
                ]);
        }

        return 'Bobot pekan kosong dibagi dari anggaran asesmen non-UTS/UAS: bobot per Sub-CPMK dibagi lagi sesuai jumlah pertemuannya.';
    }

'''
if insert_before not in s:
    raise SystemExit('target not found: insert smart draft weight helpers')
s = s.replace(insert_before, methods + insert_before, 1)
write(path, s)


# 5) Automation success message exposes weight behavior.
path = 'app/Http/Controllers/RpsAutomationController.php'
s = read(path)
old = '''        return back()->with(\n            'success',\n            \"RPS berhasil dilengkapi otomatis. {$result['updated_weeks']} pertemuan diperbarui.\"\n        );\n'''
new = '''        $weightMessage = trim((string) ($result['weight_message'] ?? ''));\n\n        return back()->with(\n            'success',\n            \"Bagian kosong berhasil diisi. {$result['updated_weeks']} pertemuan diperbarui.\"\n                .($weightMessage !== '' ? ' '.$weightMessage : '')\n        );\n'''
s = replace_once(s, old, new, 'automation success weight message')
write(path, s)


# 6) AI assessment application: aggregate weights stay in assessments; only UTS/UAS sync directly to week rows.
path = 'app/Http/Controllers/RpsAiController.php'
s = read(path)
old_affected = '''            $changedAssessments++;\n            $affectedWeeks[] = $week;\n'''
new_affected = '''            $changedAssessments++;\n            if (in_array($type, ['uts', 'uas'], true)) {\n                $affectedWeeks[] = $week;\n            }\n'''
s = replace_once(s, old_affected, new_affected, 'AI affected weeks exam-only')

old_ai_sum = '''                (float) DB::table('assessments')\n                    ->where('rps_version_id', $version->id)\n                    ->where('week_number', $affectedWeek)\n                    ->sum('weight'),\n'''
new_ai_sum = '''                (float) DB::table('assessments')\n                    ->where('rps_version_id', $version->id)\n                    ->where('week_number', $affectedWeek)\n                    ->whereIn('type', ['uts', 'uas'])\n                    ->sum('weight'),\n'''
s = replace_once(s, old_ai_sum, new_ai_sum, 'AI exam week sync query')

old_msg = '''        if ($changedAssessments > 0 && $totalWeight > 100.0) {\n            $message .= \" PERINGATAN: total bobot asesmen saat ini {$totalWeight}% (>100%). Rekomendasi tetap diterapkan; Validator OBE akan menandainya sampai dosen menyesuaikan total menjadi tepat 100%.\";\n        } elseif ($changedAssessments > 0 && abs($totalWeight - 100.0) >= 0.01) {\n            $message .= \" Total bobot asesmen saat ini {$totalWeight}%; Validator OBE akan meminta penyesuaian hingga tepat 100%.\";\n        }\n'''
new_msg = '''        if ($changedAssessments > 0 && $totalWeight > 100.0) {\n            $message .= \" PERINGATAN: total bobot asesmen agregat saat ini {$totalWeight}% (>100%). Validator OBE akan menandainya sampai total tepat 100%.\";\n        } elseif ($changedAssessments > 0 && abs($totalWeight - 100.0) >= 0.01) {\n            $message .= \" Total bobot asesmen agregat saat ini {$totalWeight}%; sesuaikan hingga tepat 100%.\";\n        } elseif ($changedAssessments > 0) {\n            $message .= ' Total bobot asesmen agregat 100%. Gunakan Isi Bagian Kosong untuk membagi anggaran non-UTS/UAS ke pekan yang belum memiliki bobot.';\n        }\n'''
s = replace_once(s, old_msg, new_msg, 'AI aggregate weight message')
write(path, s)


# 7) Validator: every teaching week is measured, distribution must equal aggregate non-exam budget.
path = 'app/Services/Rps/ObeWorkspaceService.php'
s = read(path)
old_weight_block = '''        $weightTotal = round((float) $weeks->sum(\n            fn ($week) => (float) ($week->assessment_weight ?? 0)\n        ), 2);\n'''
new_weight_block = '''        $weightTotal = round((float) $weeks->sum(\n            fn ($week) => (float) ($week->assessment_weight ?? 0)\n        ), 2);\n\n        $teachingWeeks = $weeks->filter(\n            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)\n        );\n        $weightedTeachingWeeks = $teachingWeeks->filter(\n            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0\n        );\n        $teachingWeightTotal = round((float) $teachingWeeks->sum(\n            fn ($week) => (float) ($week->assessment_weight ?? 0)\n        ), 2);\n        $assessmentWeightTotal = round((float) $assessments->sum(\n            fn ($assessment) => (float) ($assessment->weight ?? 0)\n        ), 2);\n        $nonExamAssessmentWeight = round((float) $assessments\n            ->reject(fn ($assessment) => in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true))\n            ->sum(fn ($assessment) => (float) ($assessment->weight ?? 0)), 2);\n        $weightedWeeklySubCount = $weightedTeachingWeeks\n            ->pluck('rps_sub_cpmk_id')\n            ->filter()\n            ->unique()\n            ->count();\n'''
s = replace_once(s, old_weight_block, new_weight_block, 'OBE weekly weight metrics')

old_check = '''            [\n                'key' => 'assessment_weight',\n                'label' => 'Bobot Penilaian',\n                'done' => abs($weightTotal - 100.0) < 0.01,\n                'message' => \"Total bobot pada tabel RPS {$weightTotal}%.\",\n            ],\n            [\n                'key' => 'subcpmk_assessed',\n                'label' => 'Asesmen ↔ Sub-CPMK',\n                'done' => $subCpmks->isNotEmpty()\n                    && $assessments->isNotEmpty()\n                    && $allAssessmentsMapped\n                    && $assessedSubCount === $subCpmks->count(),\n                'message' => \"{$mappedAssessmentCount}/{$assessments->count()} asesmen terhubung ke minimal satu Sub-CPMK; {$assessedSubCount}/{$subCpmks->count()} Sub-CPMK tercakup asesmen.\",\n                'details' => [\n                    'assessment_total' => $assessments->count(),\n                    'assessment_mapped' => $mappedAssessmentCount,\n                    'sub_cpmk_total' => $subCpmks->count(),\n                    'sub_cpmk_assessed' => $assessedSubCount,\n                ],\n            ],\n'''
new_check = '''            [\n                'key' => 'assessment_weight',\n                'label' => 'Bobot Penilaian',\n                'done' => abs($assessmentWeightTotal - 100.0) < 0.01\n                    && $weightedTeachingWeeks->count() === 14\n                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01\n                    && abs($weightTotal - 100.0) < 0.01,\n                'message' => \"{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; distribusi pekan non-ujian {$teachingWeightTotal}% dari anggaran asesmen non-UTS/UAS {$nonExamAssessmentWeight}%; total tabel RPS {$weightTotal}% dan total asesmen agregat {$assessmentWeightTotal}%.\",\n                'details' => [\n                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),\n                    'teaching_week_total' => $teachingWeightTotal,\n                    'non_exam_assessment_budget' => $nonExamAssessmentWeight,\n                    'weekly_total' => $weightTotal,\n                    'aggregate_assessment_total' => $assessmentWeightTotal,\n                ],\n            ],\n            [\n                'key' => 'subcpmk_assessed',\n                'label' => 'Pengukuran Sub-CPMK per Pekan',\n                'done' => $subCpmks->isNotEmpty()\n                    && $weightedTeachingWeeks->count() === 14\n                    && $weightedWeeklySubCount === $subCpmks->count(),\n                'message' => \"{$weightedTeachingWeeks->count()}/14 pekan pembelajaran memiliki bobot; {$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK tercakup oleh pekan berbobot.\",\n                'details' => [\n                    'sub_cpmk_total' => $subCpmks->count(),\n                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,\n                    'assessment_mapping_count' => $mappedAssessmentCount,\n                    'assessment_total' => $assessments->count(),\n                ],\n            ],\n'''
s = replace_once(s, old_check, new_check, 'OBE weekly measurement checks')
write(path, s)


# 8) UI language + actual weekly Edit button + exam weight lock + RTM uses week share.
path = 'resources/js/pages/rps/show.tsx'
s = read(path)
s = replace_once(
    s,
    'title="Mengisi bagian RPS yang masih kosong dan menyegarkan isian otomatis tanpa menimpa edit manual atau hasil Susun AI."',
    'title="Mengisi bagian RPS yang masih kosong. Jika total asesmen agregat sudah 100%, bobot non-UTS/UAS juga dibagi ke pekan kosong berdasarkan Sub-CPMK dan jumlah pertemuannya, tanpa menimpa bobot yang sudah diisi dosen."',
    'fill-empty tooltip weekly weights',
)
s = replace_once(
    s,
    'Edit manual asesmen, bobot, cakupan Sub-CPMK, dan RTM sebelum melihat Tabel Penilaian dan Evaluasi CPL.',
    'Asesmen menyimpan bobot agregat/anggaran penilaian (total 100%). Bobot non-UTS/UAS kemudian didistribusikan ke 14 pekan pada tabel RPS; keduanya adalah dua representasi yang sama dan tidak dijumlahkan dua kali.',
    'advanced assessment explanation',
)
s = replace_once(
    s,
    '''                    Bobot hanya perlu diisi pada pekan yang benar-benar menghasilkan komponen nilai akhir.\n                    Pekan tanpa bobot ditampilkan <strong>—</strong> pada Nilai Mhs dan tidak memengaruhi nilai akhir.\n                    Nilai contoh 72–95 hanya diberikan pada pekan yang memiliki bobot.''',
    '''                    Setiap pekan pembelajaran yang memuat Sub-CPMK harus memiliki bobot sebagai bukti pengukuran.\n                    Bobot non-UTS/UAS merupakan distribusi dari anggaran asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut.\n                    UTS dan UAS tetap mengikuti bobot asesmen sistem.''',
    'simulation weekly measurement explanation',
)

# Actual DocumentWeekRow edit button (the previous patch hit a duplicate legacy component).
s = replace_once(
    s,
    'className="rounded border border-slate-200 bg-white px-1.5 py-1 text-[9px] font-bold text-slate-600"\n                    >\n                        Edit\n                    </button>',
    'className="rounded-lg border border-sky-700 bg-sky-600 px-2 py-1.5 text-[9px] font-extrabold text-white shadow-sm transition hover:bg-sky-700"\n                    >\n                        Edit Pekan\n                    </button>',
    'DocumentWeekRow actual edit styling',
)

# Make simulation weight input static for UTS/UAS and clarify teaching-week purpose.
old_sim_start = '''function SimulationWeightInput({ rpsId, week, value }: any) {\n    const numericOriginal = Number(value || 0);\n'''
new_sim_start = '''function SimulationWeightInput({ rpsId, week, value }: any) {\n    if ([8, 16].includes(Number(week))) {\n        return <span className="font-bold text-slate-700">{Number(value || 0) || '—'}</span>;\n    }\n\n    const numericOriginal = Number(value || 0);\n'''
s = replace_once(s, old_sim_start, new_sim_start, 'SimulationWeightInput exam lock')
s = replace_once(
    s,
    'title="Kosong = tidak masuk bobot nilai akhir"',
    'title="Bobot pengukuran pekan. Setiap pekan pembelajaran yang memuat Sub-CPMK sebaiknya memiliki bobot positif."',
    'SimulationWeightInput title',
)

# Inline weekly input gets explicit title.
s = replace_once(
    s,
    'className="w-full rounded border border-slate-200 bg-white px-1 py-1 text-center text-xs font-bold text-slate-700 print:hidden"\n            />',
    'className="w-full rounded border border-sky-200 bg-sky-50/40 px-1 py-1 text-center text-xs font-bold text-sky-800 print:hidden"\n                title="Bobot pengukuran Sub-CPMK pada pekan ini"\n            />',
    'InlineWeightInput visual emphasis',
)

# Pass weeks to RTM so task sheet displays the weekly share, not aggregate assessment budget.
s = replace_once(
    s,
    '''                        assessments={assessments}\n                        subCpmks={subCpmks}\n                        bibliography={bibliography}\n''',
    '''                        assessments={assessments}\n                        subCpmks={subCpmks}\n                        bibliography={bibliography}\n                        weeks={weeks}\n''',
    'pass weeks to RTM section',
)
s = replace_once(
    s,
    '''    assessments,\n    subCpmks,\n    bibliography,\n}: any) {\n    const assessmentById = new Map(assessments.map((item: any) => [item.id, item]));\n''',
    '''    assessments,\n    subCpmks,\n    bibliography,\n    weeks,\n}: any) {\n    const assessmentById = new Map(assessments.map((item: any) => [item.id, item]));\n    const weekByNumber = new Map(weeks.map((item: any) => [Number(item.week_number), item]));\n''',
    'RTM section weeks signature',
)
s = replace_once(
    s,
    '''                                                    <div><strong>Bobot:</strong> {assessment ? `${Number(assessment.weight || 0)}%` : '-'}</div>''',
    '''                                                    <div><strong>Bobot pekan:</strong> {`${Number(weekByNumber.get(Number(task.due_week))?.assessment_weight || 0)}%`}</div>\n                                                    {assessment && (\n                                                        <div className="text-[10px] text-slate-500"><strong>Asesmen agregat:</strong> {assessment.name} ({Number(assessment.weight || 0)}%)</div>\n                                                    )}''',
    'RTM weekly share display',
)

# Clarify matrix meaning; aggregate total and weekly distribution should each be 100 in their own representation.
s = replace_once(
    s,
    'Nama asesmen dan bobot pada baris Total dapat diedit langsung; perubahan memakai data yang sama dengan panel Edit Detail Asesmen, RTM & Validator OBE.',
    'Nama dan bobot pada matriks adalah rekap asesmen agregat. Bobot 14 pekan pada tabel RPS adalah distribusi dari bobot non-UTS/UAS tersebut; jangan menjumlahkan kedua tabel sebagai dua bobot terpisah.',
    'assessment matrix explanation',
)
write(path, s)

print('weekly assessment weight model patch applied')
