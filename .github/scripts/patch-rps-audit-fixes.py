from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f'Missing marker: {label}')
    return text.replace(old, new, 1)

# ---------------------------------------------------------------------------
# resources/js/pages/rps/show.tsx
# ---------------------------------------------------------------------------
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text()

s = replace_once(
    s,
    "    material_quality: { label: 'Rapikan Bahan Kajian', target: 'validator-target-materials' },\n    weekly_material_semantics: { label: 'Periksa Materi Pekan', target: 'validator-target-weeks' },",
    "    material_quality: { label: 'Rapikan Bahan Kajian', target: 'validator-target-materials' },\n    material_coverage: { label: 'Lengkapi Bahan Kajian', target: 'validator-target-materials' },\n    weekly_material_semantics: { label: 'Periksa Materi Pekan', target: 'validator-target-weeks' },\n    concept_accuracy: { label: 'Periksa Konsep Pekan', target: 'validator-target-weeks' },",
    'validator material/concept meta',
)

s = replace_once(
    s,
    "                        <TaskQuickAdd\n                                rpsId={rps.id}\n                                subCpmks={subCpmks}\n                                assessments={assessments}\n                            />",
    "                        <TaskQuickAdd\n                                rpsId={rps.id}\n                                subCpmks={subCpmks}\n                                assessments={assessments}\n                                weeks={weeks}\n                            />",
    'TaskQuickAdd weeks prop',
)

s = replace_once(
    s,
    "function TaskQuickAdd({ rpsId, subCpmks, assessments }: any) {\n    const [open, setOpen] = useState(false);",
    "function TaskQuickAdd({ rpsId, subCpmks, assessments, weeks = [] }: any) {\n    const [open, setOpen] = useState(false);\n\n    const latestWeekForSubIds = (ids: string[]) => {\n        const wanted = new Set(safeList(ids).map(String));\n        const covered = safeList(weeks)\n            .filter((week: any) =>\n                TEACHING_WEEKS.includes(Number(week.week_number))\n                && wanted.has(String(week.rps_sub_cpmk_id || ''))\n            )\n            .map((week: any) => Number(week.week_number))\n            .filter((week: number) => Number.isFinite(week));\n\n        return covered.length > 0 ? Math.max(...covered) : 0;\n    };",
    'TaskQuickAdd latest week helper',
)

s = replace_once(
    s,
    "                                due_week: selectedAssessment.week_number\n                                    ? String(selectedAssessment.week_number)\n                                    : form.data.due_week,\n                                sub_cpmk_ids: safeList(selectedAssessment.sub_cpmk_ids).map(String),",
    "                                due_week: String(\n                                    latestWeekForSubIds(safeList(selectedAssessment.sub_cpmk_ids).map(String))\n                                    || Number(selectedAssessment.week_number || 0)\n                                    || form.data.due_week\n                                    || ''\n                                ),\n                                sub_cpmk_ids: safeList(selectedAssessment.sub_cpmk_ids).map(String),",
    'TaskQuickAdd due default',
)

s = replace_once(
    s,
    "                            const assessmentWeek = Number(selectedAssessment?.week_number || 0);\n                            const dueWeek = Number(form.data.due_week || 0);\n\n                            if (\n                                assessmentWeek > 0\n                                && dueWeek > 0\n                                && assessmentWeek !== dueWeek\n                                && !confirm(`Pekan Pengumpulan RTM (${dueWeek}) berbeda dari jadwal asesmen (${assessmentWeek}). Tetap simpan?`)\n                            ) {\n                                return;\n                            }",
    "                            const dueWeek = Number(form.data.due_week || 0);\n                            const latestCoverageWeek = latestWeekForSubIds(form.data.sub_cpmk_ids);\n\n                            if (\n                                latestCoverageWeek > 0\n                                && dueWeek > 0\n                                && dueWeek < latestCoverageWeek\n                                && !confirm(`RTM mengukur Sub-CPMK yang dipelajari sampai Pekan ${latestCoverageWeek}, tetapi pengumpulan dipilih Pekan ${dueWeek}. Tetap simpan sebagai keputusan dosen?`)\n                            ) {\n                                return;\n                            }",
    'TaskQuickAdd due warning',
)

s = replace_once(
    s,
    "            <div className=\"border-t-2 border-slate-400 pt-4\">\n                <div className=\"text-center text-base font-black uppercase text-slate-900\">\n                    Lembar Rencana Tugas Mahasiswa\n                </div>\n                <div className=\"text-center text-sm font-bold uppercase text-slate-700\">\n                    Mata Kuliah {rps.course_name}\n                </div>",
    "            <div className=\"border-t-2 border-slate-400 pt-4\">\n                <div className=\"rps-print-rtm-heading\">\n                    <div className=\"text-center text-base font-black uppercase text-slate-900\">\n                        Lembar Rencana Tugas Mahasiswa\n                    </div>\n                    <div className=\"text-center text-sm font-bold uppercase text-slate-700\">\n                        Mata Kuliah {rps.course_name}\n                    </div>\n                </div>",
    'RTM heading wrapper',
)

s = replace_once(
    s,
    "                            const linkedSubs = safeList(task.sub_cpmk_ids)\n                                .map((id: string) => subById.get(id))\n                                .filter(Boolean)\n                                .sort((a: any, b: any) => {",
    "                            const linkedSubIds = new Set(safeList(task.sub_cpmk_ids).map(String));\n                            const coverageWeeks = safeList(weeks)\n                                .filter((week: any) =>\n                                    TEACHING_WEEKS.includes(Number(week.week_number))\n                                    && linkedSubIds.has(String(week.rps_sub_cpmk_id || ''))\n                                )\n                                .sort((a: any, b: any) => Number(a.week_number) - Number(b.week_number));\n                            const coverageStart = coverageWeeks.length > 0 ? Number(coverageWeeks[0].week_number) : 0;\n                            const coverageEnd = coverageWeeks.length > 0 ? Number(coverageWeeks[coverageWeeks.length - 1].week_number) : 0;\n                            const assessmentWeeks = assessment\n                                ? safeList(weeks)\n                                    .filter((week: any) =>\n                                        TEACHING_WEEKS.includes(Number(week.week_number))\n                                        && String(week.assessment_owner_id || '') === String(assessment.id)\n                                        && Number(week.assessment_weight || 0) > 0\n                                    )\n                                    .sort((a: any, b: any) => Number(a.week_number) - Number(b.week_number))\n                                : [];\n                            const distributionLabel = assessmentWeeks\n                                .map((week: any) => `P${Number(week.week_number)}=${Number(week.assessment_weight || 0)}%`)\n                                .join('; ');\n                            const referenceNumbers = new Set<number>();\n                            [...coverageWeeks, ...assessmentWeeks].forEach((week: any) => {\n                                const matches = String(week.reference_text || '').matchAll(/\\[\\s*(\\d+)\\s*\\]/g);\n                                for (const match of matches) referenceNumbers.add(Number(match[1]));\n                            });\n                            const taskBibliography = safeList(bibliography).filter((item: any) =>\n                                referenceNumbers.has(Number(item.number))\n                            );\n\n                            const linkedSubs = safeList(task.sub_cpmk_ids)\n                                .map((id: string) => subById.get(id))\n                                .filter(Boolean)\n                                .sort((a: any, b: any) => {",
    'RTM computed metadata',
)

s = replace_once(
    s,
    "                                                    <div><strong>Bentuk penilaian pekan:</strong> {task.title}</div>\n                                                    <div><strong>Kriteria:</strong> {assessment?.description || '-'}</div>\n                                                    <div><strong>Bobot pekan:</strong> {`${Number(weekByNumber.get(Number(task.due_week))?.assessment_weight || 0)}%`}</div>\n                                                    {assessment && (\n                                                        <div className=\"text-[10px] text-slate-500\"><strong>Asesmen agregat:</strong> {assessment.name} ({Number(assessment.weight || 0)}%)</div>\n                                                    )}",
    "                                                    <div><strong>Bentuk penilaian:</strong> {task.title}</div>\n                                                    <div><strong>Kriteria:</strong> {assessment?.description || '-'}</div>\n                                                    <div><strong>Bobot RTM/Asesmen:</strong> {assessment ? `${Number(assessment.weight || 0)}%` : `${Number(weekByNumber.get(Number(task.due_week))?.assessment_weight || 0)}%`}</div>\n                                                    {distributionLabel && (\n                                                        <div className=\"text-[10px] text-slate-500\"><strong>Distribusi bobot pekan:</strong> {distributionLabel}</div>\n                                                    )}\n                                                    {assessment && (\n                                                        <div className=\"text-[10px] text-slate-500\"><strong>Asesmen agregat:</strong> {assessment.name} ({Number(assessment.weight || 0)}%)</div>\n                                                    )}",
    'RTM aggregate weight display',
)

s = replace_once(
    s,
    "                                                    Pekan {task.due_week || '-'}",
    "                                                    {coverageStart > 0 && coverageEnd > 0\n                                                        ? (coverageStart === coverageEnd\n                                                            ? `Pelaksanaan: Pekan ${coverageStart} · Pengumpulan: Pekan ${task.due_week || coverageEnd}`\n                                                            : `Pelaksanaan: Pekan ${coverageStart}–${coverageEnd} · Pengumpulan: Pekan ${task.due_week || coverageEnd}`)\n                                                        : `Pengumpulan: Pekan ${task.due_week || '-'}`}",
    'RTM schedule display',
)

s = replace_once(
    s,
    "                                                    {bibliography.length > 0\n                                                        ? bibliography.map((item: any) => (\n                                                            <div key={item.number}>{item.number}. {item.text}</div>\n                                                        ))\n                                                        : '-'}",
    "                                                    {taskBibliography.length > 0\n                                                        ? taskBibliography.map((item: any) => (\n                                                            <div key={item.number}>{item.number}. {item.text}</div>\n                                                        ))\n                                                        : '-'}",
    'RTM relevant bibliography',
)

p.write_text(s)

# ---------------------------------------------------------------------------
# resources/css/app.css - final print overrides.
# ---------------------------------------------------------------------------
p = Path('resources/css/app.css')
s = p.read_text()
patch = r'''

/* Patch: audited RPS/RTM print flow */
@media print {
    /* Chrome may postpone the whole weekly table when the first very-tall row
       must be kept with a repeating table-header-group. Treat the header as a
       normal row group so the table can begin immediately after MK Prasyarat. */
    html.rps-print-mode .rps-print-weekly thead {
        display: table-row-group !important;
    }

    html.rps-print-mode .rps-print-weekly,
    html.rps-print-mode .rps-print-weekly tbody,
    html.rps-print-mode .rps-print-weekly tr,
    html.rps-print-mode .rps-print-weekly th,
    html.rps-print-mode .rps-print-weekly td {
        break-inside: auto !important;
        page-break-inside: auto !important;
        break-before: auto !important;
        page-break-before: auto !important;
        break-after: auto !important;
        page-break-after: auto !important;
        orphans: 1 !important;
        widows: 1 !important;
    }

    /* The RTM sheet already contains its own official institutional heading.
       Hide the standalone section title in print so it cannot consume a page. */
    html.rps-print-mode .rps-print-rtm-heading {
        display: none !important;
    }

    html.rps-print-mode .rps-print-rtm-sheet {
        break-inside: auto !important;
        page-break-inside: auto !important;
    }
}
'''
if 'Patch: audited RPS/RTM print flow' not in s:
    s = s.rstrip() + patch + '\n'
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsAssessmentSyncService.php
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/RpsAssessmentSyncService.php')
s = p.read_text()

old_due = r'''                $preferred = (int) ($assessment->week_number ?? 0);
                $candidates = $weeks
                    ->filter(fn ($week) => $subIds->contains((string) $week->rps_sub_cpmk_id))
                    ->sort(function ($a, $b) use ($preferred, $usedWeeks): int {
                        $aWeek = (int) $a->week_number;
                        $bWeek = (int) $b->week_number;
                        $aDistance = $preferred > 0 ? abs($aWeek - $preferred) : $aWeek;
                        $bDistance = $preferred > 0 ? abs($bWeek - $preferred) : $bWeek;

                        if ($aDistance !== $bDistance) return $aDistance <=> $bDistance;

                        $aUsed = in_array($aWeek, $usedWeeks, true) ? 1 : 0;
                        $bUsed = in_array($bWeek, $usedWeeks, true) ? 1 : 0;
                        if ($aUsed !== $bUsed) return $aUsed <=> $bUsed;

                        return $aWeek <=> $bWeek;
                    })
                    ->values();

                $dueWeek = $candidates->isNotEmpty()
                    ? (int) $candidates->first()->week_number
                    : ($preferred > 0 ? $preferred : null);
'''
new_due = r'''                $preferred = (int) ($assessment->week_number ?? 0);
                $coverageWeeks = $weeks
                    ->filter(fn ($week) => $subIds->contains((string) $week->rps_sub_cpmk_id))
                    ->pluck('week_number')
                    ->map(fn ($week) => (int) $week)
                    ->filter()
                    ->values();

                // RTM multi-Sub-CPMK tidak boleh dikumpulkan sebelum seluruh
                // Sub-CPMK yang diukurnya sudah memperoleh pertemuan. Jadwal
                // default adalah pekan terakhir cakupan; asesmen boleh berada
                // lebih akhir, tetapi tidak boleh memajukan pengumpulan.
                $latestCoverageWeek = $coverageWeeks->isNotEmpty()
                    ? (int) $coverageWeeks->max()
                    : 0;
                $dueWeek = max($latestCoverageWeek, $preferred) ?: null;
'''
s = replace_once(s, old_due, new_due, 'generated RTM due week')

s = replace_once(
    s,
    "        $this->syncTaskMappings($versionId);\n        $createdTasks = $this->ensureRequiredTasks($versionId);\n        $linkedTasks = $this->syncTaskMappings($versionId);",
    "        $this->syncTaskMappings($versionId);\n        $createdTasks = $this->ensureRequiredTasks($versionId);\n        $linkedTasks = $this->syncTaskMappings($versionId);\n        $this->repairGeneratedTaskDueWeeks($versionId);",
    'syncVersion due repair',
)

s = replace_once(
    s,
    "    public function repairGeneratedArtifacts(string $versionId): array\n    {\n        $scopeFixes = $this->syncGeneratedAssessmentScopes($versionId);\n        $linkedTasks = $this->syncTaskMappings($versionId);\n\n        return [\n            'assessment_scope_fixes' => $scopeFixes,\n            'linked_generated_tasks' => $linkedTasks,\n        ];\n    }",
    r'''    public function repairGeneratedArtifacts(string $versionId): array
    {
        $scopeFixes = $this->syncGeneratedAssessmentScopes($versionId);
        $linkedTasks = $this->syncTaskMappings($versionId);
        $dueWeekFixes = $this->repairGeneratedTaskDueWeeks($versionId);

        return [
            'assessment_scope_fixes' => $scopeFixes,
            'linked_generated_tasks' => $linkedTasks,
            'generated_due_week_fixes' => $dueWeekFixes,
        ];
    }

    private function repairGeneratedTaskDueWeeks(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'due_week', 'source_type', 'purpose', 'instructions', 'expected_output'])
            ->filter(fn ($task) => $this->isGeneratedTask($task))
            ->values();

        if ($tasks->isEmpty()) return 0;

        $taskLinks = DB::table('rps_task_subcpmks')
            ->whereIn('rps_task_id', $tasks->pluck('id')->all())
            ->get(['rps_task_id', 'rps_sub_cpmk_id'])
            ->groupBy('rps_task_id');

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->whereNotNull('rps_sub_cpmk_id')
            ->get(['week_number', 'rps_sub_cpmk_id']);

        $fixed = 0;
        foreach ($tasks as $task) {
            $subIds = collect($taskLinks->get($task->id, []))
                ->pluck('rps_sub_cpmk_id')->map('strval')->unique()->values();
            if ($subIds->isEmpty()) continue;

            $latest = $weeks
                ->filter(fn ($week) => $subIds->contains((string) $week->rps_sub_cpmk_id))
                ->max('week_number');
            $latest = $latest ? (int) $latest : 0;
            if ($latest <= 0) continue;

            $current = (int) ($task->due_week ?? 0);
            if ($current >= $latest) continue;

            DB::table('rps_tasks')->where('id', $task->id)->update([
                'due_week' => $latest,
                'updated_at' => now(),
            ]);
            $fixed++;
        }

        return $fixed;
    }''',
    'repairGeneratedArtifacts due repair method',
)

s = replace_once(
    s,
    "        $weekRows = DB::table('rps_weekly_plans')\n            ->where('rps_version_id', $versionId)\n            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))\n            ->get(['week_number', 'assessment_weight'])\n            ->keyBy('week_number');",
    "        $weekRows = DB::table('rps_weekly_plans')\n            ->where('rps_version_id', $versionId)\n            ->whereIn('week_number', array_merge(self::TEACHING_WEEKS, [8, 16]))\n            ->get(['week_number', 'rps_sub_cpmk_id', 'assessment_weight'])\n            ->keyBy('week_number');",
    'taskAlignment week sub ids',
)

s = replace_once(
    s,
    "            if ($dueWeek < 1 || $dueWeek > 16) {\n                $invalidDueWeekCount++;\n            }",
    "            if ($dueWeek < 1 || $dueWeek > 16) {\n                $invalidDueWeekCount++;\n            } else {\n                $latestCoverageWeek = $weekRows\n                    ->filter(fn ($row, $number) =>\n                        in_array((int) $number, self::TEACHING_WEEKS, true)\n                        && filled($row->rps_sub_cpmk_id ?? null)\n                        && $actual->contains((string) $row->rps_sub_cpmk_id)\n                    )\n                    ->keys()->map(fn ($number) => (int) $number)->max();\n\n                if ($latestCoverageWeek && $dueWeek < (int) $latestCoverageWeek) {\n                    $invalidDueWeekCount++;\n                }\n            }",
    'taskAlignment due coverage',
)

p.write_text(s)

# ---------------------------------------------------------------------------
# RpsTaskController.php - intelligent default due week for manual/add forms.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsTaskController.php')
s = p.read_text()

s = replace_once(
    s,
    "        if (empty($validated['due_week']) && filled($assessment->week_number)) {\n            $validated['due_week'] = (int) $assessment->week_number;\n        }\n\n        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])",
    "        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])",
    'remove early RTM due default',
)

old_scope = r'''        if ($requestedSubIds->isEmpty()) {
            // Default aman: satu RTM mewarisi seluruh cakupan asesmen induk.
            // Dosen tetap dapat memilih sebagian Sub-CPMK melalui editor RTM.
            $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
            return $validated;
        }

        $outsideAssessment = $requestedSubIds
            ->reject(fn ($id) => $assessmentSubIds->contains($id))
            ->values();

        if ($outsideAssessment->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sub_cpmk_ids' => 'RTM hanya boleh mengukur Sub-CPMK yang termasuk dalam cakupan asesmen induk. Tambahkan Sub-CPMK tersebut pada asesmen terlebih dahulu atau ubah pilihan RTM.',
            ]);
        }

        // Jangan mempersempit cakupan berdasarkan pekan pengumpulan. RTM
        // integratif dapat mengukur beberapa Sub-CPMK dan dikumpulkan pada
        // satu pekan tertentu.
        $validated['sub_cpmk_ids'] = $requestedSubIds->all();

        return $validated;
'''
new_scope = r'''        if ($requestedSubIds->isEmpty()) {
            // Default aman: satu RTM mewarisi seluruh cakupan asesmen induk.
            // Dosen tetap dapat memilih sebagian Sub-CPMK melalui editor RTM.
            $validated['sub_cpmk_ids'] = $assessmentSubIds->all();
        } else {
            $outsideAssessment = $requestedSubIds
                ->reject(fn ($id) => $assessmentSubIds->contains($id))
                ->values();

            if ($outsideAssessment->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sub_cpmk_ids' => 'RTM hanya boleh mengukur Sub-CPMK yang termasuk dalam cakupan asesmen induk. Tambahkan Sub-CPMK tersebut pada asesmen terlebih dahulu atau ubah pilihan RTM.',
                ]);
            }

            // Jangan mempersempit cakupan berdasarkan pekan pengumpulan. RTM
            // integratif dapat mengukur beberapa Sub-CPMK dan dikumpulkan pada
            // satu pekan tertentu.
            $validated['sub_cpmk_ids'] = $requestedSubIds->all();
        }

        if (empty($validated['due_week'])) {
            $latestCoverageWeek = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->whereIn('week_number', [1,2,3,4,5,6,7,9,10,11,12,13,14,15])
                ->whereIn('rps_sub_cpmk_id', $validated['sub_cpmk_ids'])
                ->max('week_number');

            $validated['due_week'] = max(
                (int) ($latestCoverageWeek ?? 0),
                (int) ($assessment->week_number ?? 0)
            ) ?: null;
        }

        return $validated;
'''
s = replace_once(s, old_scope, new_scope, 'RTM assessment defaults scope/due')
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsAiController.php - future AI RTM due-week guard and cache version.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text()
s = replace_once(
    s,
    "2. `tasks[*].sub_cpmk_codes` tidak boleh dipaksa sama dengan Sub-CPMK pada pekan pengumpulan. `due_week` hanya menunjukkan jadwal/pengumpulan; cakupan akademik RTM ditentukan oleh kemampuan yang benar-benar diukur tugas tersebut.\n3. Seluruh `sub_cpmk_codes` sebuah RTM harus merupakan bagian dari `sub_cpmk_codes` asesmen induknya.",
    "2. `tasks[*].sub_cpmk_codes` tidak boleh dipaksa sama dengan Sub-CPMK pada pekan pengumpulan. `due_week` hanya menunjukkan jadwal/pengumpulan; cakupan akademik RTM ditentukan oleh kemampuan yang benar-benar diukur tugas tersebut. Untuk RTM multi-Sub-CPMK, `due_week` WAJIB berada pada atau setelah PEKAN TERAKHIR di `weekly_plan` yang memuat salah satu Sub-CPMK RTM; jangan mengumpulkan tugas sebelum seluruh capaian yang diukur selesai dipelajari.\n3. Seluruh `sub_cpmk_codes` sebuah RTM harus merupakan bagian dari `sub_cpmk_codes` asesmen induknya.",
    'AI RTM due week rule',
)
s = s.replace("'rtm-integrative-v1'", "'rtm-integrative-v2'", 1)
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsAiContextService.php - explicit conceptual guard for weekly generation.
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/RpsAiContextService.php')
s = p.read_text()
s = replace_once(
    s,
    "                'must_use_target_sub_cpmk' => true,\n                'do_not_move_backward_to_earlier_sub_cpmk' => true,",
    "                'must_use_target_sub_cpmk' => true,\n                'do_not_move_backward_to_earlier_sub_cpmk' => true,\n                'concept_guard_bfs' => 'BFS untuk jalur terpendek hanya pada graf tak berbobot atau bobot seragam. Untuk graf berbobot positif gunakan Dijkstra; A* digunakan bila heuristik relevan.',",
    'weekly BFS concept guard',
)
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsDocumentController.php - normalize weekly [n] after Pustaka changes.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsDocumentController.php')
s = p.read_text()

s = replace_once(
    s,
    "        return back()->with('success', 'Pustaka berhasil diperbarui.');\n    }\n\n    public function generateAiReferences(",
    "        $normalized = $this->normalizeWeeklyReferenceCodes(\n            $version->id,\n            $this->referenceEntryCount($values['reference_text'], $values['supporting_reference_text'])\n        );\n\n        return back()->with('success', 'Pustaka berhasil diperbarui. '.$normalized.' pustaka pekan dinormalisasi.');\n    }\n\n    public function generateAiReferences(",
    'manual reference normalization',
)

s = replace_once(
    s,
    "        $providerUsed = strtoupper((string) ($result['provider'] ?? 'AI'));",
    "        $normalized = $this->normalizeWeeklyReferenceCodes(\n            $version->id,\n            $this->referenceEntryCount($values['reference_text'], $values['supporting_reference_text'])\n        );\n\n        $providerUsed = strtoupper((string) ($result['provider'] ?? 'AI'));",
    'AI reference normalization call',
)

s = replace_once(
    s,
    "            'Pustaka berhasil ditelaah AI dan disesuaikan dengan bahan kajian aktif.'\n                .$fallbackNote",
    "            'Pustaka berhasil ditelaah AI dan disesuaikan dengan bahan kajian aktif. '\n                .$normalized.' pustaka pekan dinormalisasi.'\n                .$fallbackNote",
    'AI reference normalization message',
)

marker = "    private function context(Request $request, string $rps): array\n"
helpers = r'''    private function referenceEntryCount(string $main, string $supporting): int
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($main."\n".$supporting)) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->reject(fn ($line) => preg_match('/^(utama|pendukung|tambahan)\s*:?$/i', $line) === 1)
            ->count();
    }

    private function normalizeWeeklyReferenceCodes(string $versionId, int $entryCount): int
    {
        if ($entryCount <= 0) return 0;

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereNotNull('reference_text')
            ->get(['id', 'reference_text']);
        $changed = 0;

        foreach ($weeks as $week) {
            $value = trim((string) $week->reference_text);
            if ($value === '') continue;

            preg_match_all('/\[\s*(\d+)\s*\]/', $value, $matches);
            if (($matches[1] ?? []) === [] && preg_match('/^\s*\d+(?:\s*[,;]\s*\d+)*\s*$/', $value) !== 1) {
                continue;
            }
            if (($matches[1] ?? []) === []) preg_match_all('/\d+/', $value, $matches);

            $normalized = collect($matches[1] ?? $matches[0] ?? [])
                ->map(fn ($number) => (int) $number)
                ->filter(fn ($number) => $number >= 1 && $number <= $entryCount)
                ->unique()->sort()->map(fn ($number) => '['.$number.']')->implode(', ');

            if ($normalized === $value) continue;

            DB::table('rps_weekly_plans')->where('id', $week->id)->update([
                'reference_text' => $normalized !== '' ? $normalized : null,
                'updated_at' => now(),
            ]);
            $changed++;
        }

        return $changed;
    }

'''
s = replace_once(s, marker, helpers + marker, 'reference normalization helpers')
p.write_text(s)

# ---------------------------------------------------------------------------
# RpsController.php - print/view safety for stale reference numbers.
# ---------------------------------------------------------------------------
p = Path('app/Http/Controllers/RpsController.php')
s = p.read_text()
s = replace_once(
    s,
    "        $bibliography = $this->parseBibliography($combinedReferenceText);\n\n        $documentMeta = [",
    "        $bibliography = $this->parseBibliography($combinedReferenceText);\n        $bibliographyCount = count($bibliography);\n        $weeks = $weeks->map(function ($week) use ($bibliographyCount): object {\n            $week->reference_text = $this->filterWeeklyReferenceCodesForDisplay(\n                (string) ($week->reference_text ?? ''),\n                $bibliographyCount\n            );\n            return $week;\n        });\n\n        $documentMeta = [",
    'read-side reference filter',
)

marker = "    private function splitReferenceGroups(string $text): array\n"
helper = r'''    private function filterWeeklyReferenceCodesForDisplay(string $value, int $entryCount): ?string
    {
        if ($entryCount <= 0 || trim($value) === '') return null;
        preg_match_all('/\[\s*(\d+)\s*\]/', $value, $matches);
        if (($matches[1] ?? []) === []) return $value;

        $codes = collect($matches[1])
            ->map(fn ($number) => (int) $number)
            ->filter(fn ($number) => $number >= 1 && $number <= $entryCount)
            ->unique()->sort()->map(fn ($number) => '['.$number.']')->implode(', ');

        return $codes !== '' ? $codes : null;
    }

'''
s = replace_once(s, marker, helper + marker, 'RpsController display reference helper')
p.write_text(s)

# ---------------------------------------------------------------------------
# ObeWorkspaceService.php - material coverage + known concept risk validator.
# ---------------------------------------------------------------------------
p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text()

insert_after = "        $materialQualityAligned = $duplicateMaterials->isEmpty();\n"
addition = r'''

        $materialCoverageIssues = collect();
        foreach ($subCpmks as $sub) {
            $scores = $materials->map(fn ($material) =>
                $this->semanticSimilarity((string) $material->title, (string) $sub->description)
            );
            $bestScore = $scores->isNotEmpty() ? (float) $scores->max() : 0.0;
            if ($bestScore < 0.10) {
                $materialCoverageIssues->push([
                    'sub_cpmk_id' => (string) $sub->id,
                    'sub_cpmk_code' => (string) $sub->code,
                    'best_score' => round($bestScore, 3),
                ]);
            }
        }
        $materialCoverageAligned = $materialCoverageIssues->isEmpty();

        $conceptAccuracyIssues = collect();
        foreach ($teachingWeeks as $week) {
            $number = (int) $week->week_number;
            $fields = [
                'assessment_indicator' => (string) ($week->assessment_indicator ?? ''),
                'learning_activity' => (string) ($week->learning_activity ?? ''),
                'student_assignment' => (string) ($week->student_assignment ?? ''),
                'online_activity' => (string) ($week->online_activity ?? ''),
            ];
            foreach ($fields as $field => $text) {
                $normalized = mb_strtolower($text);
                $mentionsBfsWeighted = str_contains($normalized, 'bfs')
                    && preg_match('/graf\s+berbobot/u', $normalized) === 1
                    && preg_match('/jalur\s+terpendek|shortest\s+path/u', $normalized) === 1
                    && preg_match('/tak\s+berbobot|tidak\s+berbobot|bobot\s+seragam/u', $normalized) !== 1;
                if ($mentionsBfsWeighted) {
                    $conceptAccuracyIssues->push([
                        'week' => $number,
                        'field' => $field,
                        'issue' => 'BFS shortest path pada graf berbobot perlu diperiksa; BFS tepat untuk graf tak berbobot/bobot seragam, sedangkan bobot positif umumnya memakai Dijkstra.',
                    ]);
                    break;
                }
            }
        }
        $conceptAccuracyAligned = $conceptAccuracyIssues->isEmpty();
'''
s = replace_once(s, insert_after, insert_after + addition, 'material coverage and concept checks')

s = replace_once(
    s,
    "            [\n                'key' => 'weekly_material_semantics',",
    "            [\n                'key' => 'material_coverage',\n                'label' => 'Cakupan Bahan Kajian',\n                'severity' => 'advisory',\n                'done' => $materialCoverageAligned,\n                'message' => $materialCoverageAligned\n                    ? 'Bahan Kajian memiliki keterkaitan minimum dengan seluruh Sub-CPMK.'\n                    : (($issue = $materialCoverageIssues->first())\n                        ? $issue['sub_cpmk_code'].' belum memiliki Bahan Kajian yang cukup dekat. Telaah/tambah Bahan Kajian sebelum menyusun pekan terkait.'\n                        : 'Ada Sub-CPMK yang belum ditopang Bahan Kajian.'),\n                'details' => ['issues' => $materialCoverageIssues->all()],\n            ],\n            [\n                'key' => 'weekly_material_semantics',",
    'material coverage validator card',
)

s = replace_once(
    s,
    "            [\n                'key' => 'weeks',\n                'label' => '16 Pertemuan',",
    "            [\n                'key' => 'concept_accuracy',\n                'label' => 'Ketepatan Konsep Pekanan',\n                'severity' => 'advisory',\n                'done' => $conceptAccuracyAligned,\n                'message' => $conceptAccuracyAligned\n                    ? 'Tidak ditemukan risiko konsep yang dikenali rule-engine.'\n                    : (($issue = $conceptAccuracyIssues->first())\n                        ? 'Pekan '.$issue['week'].': '.$issue['issue']\n                        : 'Ada konsep pekanan yang perlu diperiksa.'),\n                'details' => ['issues' => $conceptAccuracyIssues->all()],\n            ],\n            [\n                'key' => 'weeks',\n                'label' => '16 Pertemuan',",
    'concept accuracy validator card',
)

p.write_text(s)
