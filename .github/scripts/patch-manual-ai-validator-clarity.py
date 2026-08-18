from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f"Pattern not found in {path}: {old[:120]!r}")
    text = text.replace(old, new, 1)
    p.write_text(text)


controller = "app/Http/Controllers/RpsAiController.php"
workspace = "app/Services/Rps/ObeWorkspaceService.php"
show = "resources/js/pages/rps/show.tsx"

# 1) Never let AI overwrite lecturer-owned/manual assessments during review.
replace_once(
    controller,
    """                $assessmentItems[$index]['action'] = $same ? 'keep' : 'adapt';
                $assessmentItems[$index]['target_code'] = (string) $match->code;
                $assessmentItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $assessmentItems[$index]['rationale'] = $same
                    ? 'Asesmen yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'Asesmen yang sudah ada dikenali sebagai target perbaikan terutama dari kesamaan cakupan Sub-CPMK; tipe, jadwal, dan nama menjadi penguat.';
""",
    """                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array(
                    $targetSourceType,
                    ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'],
                    true
                );

                $assessmentItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';
                $assessmentItems[$index]['target_code'] = (string) $match->code;
                $assessmentItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $assessmentItems[$index]['rationale'] = $lecturerOwnedTarget
                    ? 'Asesmen manual/dosen dipertahankan. Telaah AI tidak menimpa item ini; perubahannya tetap dilakukan dari editor oleh dosen.'
                    : ($same
                        ? 'Asesmen yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                        : 'Asesmen AI sebelumnya dikenali sebagai target perbaikan berdasarkan cakupan Sub-CPMK, tipe, jadwal, dan nama.');
""",
)

# 2) Same protection for lecturer-owned/manual RTM.
replace_once(
    controller,
    """                $taskItems[$index]['action'] = $same ? 'keep' : 'adapt';
                $taskItems[$index]['target_code'] = (string) $match->code;
                $taskItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $taskItems[$index]['rationale'] = $same
                    ? 'RTM yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'RTM yang sudah ada dikenali sebagai target perbaikan berdasarkan asesmen induk, jadwal, dan cakupan Sub-CPMK.';
""",
    """                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array(
                    $targetSourceType,
                    ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'],
                    true
                );

                $taskItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';
                $taskItems[$index]['target_code'] = (string) $match->code;
                $taskItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $taskItems[$index]['rationale'] = $lecturerOwnedTarget
                    ? 'RTM manual/dosen dipertahankan. Telaah AI tidak menimpa item ini; perubahannya tetap dilakukan dari editor oleh dosen.'
                    : ($same
                        ? 'RTM yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                        : 'RTM AI sebelumnya dikenali sebagai target perbaikan berdasarkan asesmen induk, jadwal, dan cakupan Sub-CPMK.');
""",
)

# 3) Apply-time hard guard for stale/pending suggestions that might still target manual data.
replace_once(
    controller,
    """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali agar konteks diperbarui.',
                    ]);
                }
            } else {
""",
    """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali agar konteks diperbarui.',
                    ]);
                }

                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array(
                    $existingSourceType,
                    ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen manual/dosen tidak boleh ditimpa oleh AI. Item tersebut tetap dipertahankan; ubah dari Edit Detail Asesmen bila memang diperlukan.',
                    ]);
                }
            } else {
""",
)

replace_once(
    controller,
    """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
            } else {
""",
    """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }

                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array(
                    $existingSourceType,
                    ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM manual/dosen tidak boleh ditimpa oleh AI. Item tersebut tetap dipertahankan; ubah dari editor RTM bila memang diperlukan.',
                    ]);
                }
            } else {
""",
)

# 4) Build a concise RTM issue summary using the actual validator reason.
replace_once(
    workspace,
    """        $rtmProblemLabels = $rtmProblemTasks
            ->map(fn ($item) => trim((string) ($item['code'] ?? 'RTM')))
            ->filter()->unique()->values();
        $missingRtmAssessments = collect($taskAlignment['missing_required_assessments'] ?? [])
""",
    """        $rtmProblemLabels = $rtmProblemTasks
            ->map(fn ($item) => trim((string) ($item['code'] ?? 'RTM')))
            ->filter()->unique()->values();
        $rtmProblemSummary = $rtmProblemTasks
            ->map(function ($item) {
                $code = trim((string) ($item['code'] ?? 'RTM')) ?: 'RTM';
                $reason = rtrim(trim((string) ($item['reason'] ?? 'perlu disinkronkan dengan asesmen induk')), '.');
                return $code.': '.$reason;
            })
            ->filter()->take(2)->implode(' · ');
        $missingRtmAssessments = collect($taskAlignment['missing_required_assessments'] ?? [])
""",
)

# 5) Short explanations only matter while the check is unresolved.
replace_once(
    workspace,
    """                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'message' => $assessmentChainAligned
""",
    """                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'hint' => 'Memeriksa hubungan asesmen, Sub-CPMK, bobot 14 pekan, bukti penilaian, dan RTM.',
                'message' => $assessmentChainAligned
""",
)

replace_once(
    workspace,
    """                                    : ($taskAlignment['missing_required_assessment_count'] > 0
                                        ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                        : 'Masih ada data penilaian yang belum konsisten.'))))),
""",
    """                                    : ($taskAlignment['missing_required_assessment_count'] > 0
                                        ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                        : ($taskAlignment['mapping_mismatch_count'] > 0
                                            ? 'Ada RTM yang cakupan Sub-CPMK-nya belum sesuai dengan asesmen induk.'
                                            : ($taskAlignment['unlinked_weighted_task_count'] > 0
                                                ? 'Ada RTM pada pekan berbobot yang belum terhubung ke asesmen induk.'
                                                : ($taskAlignment['due_week_subcpmk_mismatch_count'] > 0
                                                    ? 'Ada RTM yang dijadwalkan sebelum seluruh Sub-CPMK terkait selesai dipelajari.'
                                                    : 'Rantai asesmen–Sub-CPMK–bobot pekan–RTM belum sepenuhnya selaras.')))))),
""",
)

replace_once(
    workspace,
    """                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'message' => $taskAssessments->isEmpty()
""",
    """                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'hint' => 'RTM harus terhubung ke asesmen induk yang sesuai, memakai cakupan Sub-CPMK yang benar, dan dikumpulkan setelah capaian terkait dipelajari.',
                'message' => $taskAssessments->isEmpty()
""",
)

replace_once(
    workspace,
    """                    : ((bool) $taskAlignment['is_aligned']
                        ? \"{$tasks} RTM tersedia · Semua sinkron dengan asesmen induk.\"
                        : \"{$tasks} RTM tersedia\"
                            .($missingRtmAssessments->isNotEmpty()
                                ? ' · asesmen belum memiliki RTM: '.$missingRtmAssessments->implode(', ')
                                : ' · semua asesmen wajib sudah memiliki RTM')
                            .($rtmProblemLabels->isNotEmpty()
                                ? ' · perlu sinkronisasi: '.$rtmProblemLabels->implode(', ').'.'
                                : '.')),
""",
    """                    : ((bool) $taskAlignment['is_aligned']
                        ? \"{$tasks} RTM tersedia · Semua sinkron dengan asesmen induk.\"
                        : \"{$tasks} RTM tersedia. \"
                            .($missingRtmAssessments->isNotEmpty()
                                ? 'Belum ada RTM untuk: '.$missingRtmAssessments->implode(', ').'. '
                                : '')
                            .($rtmProblemSummary !== ''
                                ? 'Perbaiki '.$rtmProblemSummary.'.'
                                : ($missingRtmAssessments->isEmpty()
                                    ? 'Periksa hubungan RTM dengan asesmen induk.'
                                    : ''))),
""",
)

# 6) Show the explanation only while a validator check has not passed.
replace_once(
    show,
    """                                        </div>
                                        <p className=\"mt-2 text-xs leading-5 text-slate-600\">{check.message}</p>
                                        {!check.done && (
""",
    """                                        </div>
                                        {!check.done && check.hint && (
                                            <p className=\"mt-2 text-[10px] font-semibold leading-4 text-slate-500\">{check.hint}</p>
                                        )}
                                        <p className={`${!check.done && check.hint ? 'mt-1' : 'mt-2'} text-xs leading-5 text-slate-600`}>{check.message}</p>
                                        {!check.done && (
""",
)

print('Patched manual AI protection and validator clarity.')
