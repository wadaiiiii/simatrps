from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    if old not in text:
        raise SystemExit(f"Pattern not found in {path}: {old!r}")
    p.write_text(text.replace(old, new, 1))

controller = "app/Http/Controllers/RpsAiController.php"
workspace = "app/Services/Rps/ObeWorkspaceService.php"
show = "resources/js/pages/rps/show.tsx"

# REVIEW-TIME: lecturer/manual assessment is never offered as ADAPT.
replace_once(
    controller,
    "                $assessmentItems[$index]['action'] = $same ? 'keep' : 'adapt';",
    """                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array($targetSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true);
                $assessmentItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';""",
)
replace_once(
    controller,
    "                $taskItems[$index]['action'] = $same ? 'keep' : 'adapt';",
    """                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array($targetSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true);
                $taskItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';""",
)

# APPLY-TIME: protect against old/stale pending suggestions that still say ADAPT.
assessment_missing = """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali agar konteks diperbarui.',
                    ]);
                }
"""
assessment_guard = assessment_missing + """                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array($existingSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true)) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen manual/dosen tidak boleh ditimpa oleh AI. Item tetap dipertahankan; ubah dari Edit Detail Asesmen bila diperlukan.',
                    ]);
                }
"""
replace_once(controller, assessment_missing, assessment_guard)

rtm_missing = """                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
"""
rtm_guard = rtm_missing + """                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array($existingSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true)) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM manual/dosen tidak boleh ditimpa oleh AI. Item tetap dipertahankan; ubah dari editor RTM bila diperlukan.',
                    ]);
                }
"""
replace_once(controller, rtm_missing, rtm_guard)

# Short validator explanations. Frontend only shows these while unresolved.
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
    "                                        : 'Masih ada data penilaian yang belum konsisten.'))))),",
    "                                        : 'Rantai asesmen–Sub-CPMK–bobot pekan–RTM belum sepenuhnya selaras.'))))),",
)
replace_once(
    workspace,
    """                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'message' => $taskAssessments->isEmpty()
""",
    """                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'hint' => 'Memeriksa asesmen induk, cakupan Sub-CPMK, dan pekan pengumpulan setiap RTM.',
                'message' => $taskAssessments->isEmpty()
""",
)
replace_once(
    workspace,
    "                                : ' · semua asesmen wajib sudah memiliki RTM')",
    "                                : ' · seluruh asesmen yang memerlukan RTM sudah memiliki RTM')",
)
replace_once(
    workspace,
    "                                ? ' · perlu sinkronisasi: '.$rtmProblemLabels->implode(', ').'.'",
    "                                ? ' · periksa hubungan asesmen/Sub-CPMK/jadwal: '.$rtmProblemLabels->implode(', ').'.'",
)

# Hint disappears automatically when check.done becomes true.
replace_once(
    show,
    '                                        <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>',
    """                                        {!check.done && check.hint && (
                                            <p className=\"mt-2 text-[10px] font-semibold leading-4 text-slate-500\">{check.hint}</p>
                                        )}
                                        <p className={`${!check.done && check.hint ? 'mt-1' : 'mt-2'} text-xs leading-5 text-slate-600`}>{check.message}</p>""",
)

print('manual AI protection and validator clarity patched')
