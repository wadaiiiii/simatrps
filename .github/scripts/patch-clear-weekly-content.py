from pathlib import Path

routes = Path('routes/web.php')
controller = Path('app/Http/Controllers/RpsAutomationController.php')
show = Path('resources/js/pages/rps/show.tsx')

# Route
text = routes.read_text()
needle = "        Route::post('{rps}/weeks/allocate-subcpmk', [RpsAutomationController::class, 'allocateSubCpmkMeetings'])->name('weeks.allocate-subcpmk');\n"
insert = needle + "        Route::post('{rps}/weeks/clear-content', [RpsAutomationController::class, 'clearWeeklyContent'])->name('weeks.clear-content');\n"
if "weeks/clear-content" not in text:
    if needle not in text:
        raise SystemExit('route anchor not found')
    text = text.replace(needle, insert, 1)
routes.write_text(text)

# Controller method
text = controller.read_text()
anchor = "    public function copyPrevious(\n"
method = '''    public function clearWeeklyContent(\n        Request $request,\n        string $rps\n    ): RedirectResponse {\n        [, $version] = $this->context($request, $rps);\n\n        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];\n\n        $updated = DB::table('rps_weekly_plans')\n            ->where('rps_version_id', $version->id)\n            ->whereIn('week_number', $teachingWeeks)\n            ->update([\n                'assessment_indicator' => null,\n                'assessment_criteria' => null,\n                'assessment_method' => null,\n                'learning_form' => null,\n                'learning_method' => null,\n                'face_to_face_sessions' => 0,\n                'learning_activity' => null,\n                'independent_study_sessions' => 0,\n                'student_assignment' => null,\n                'structured_task_sessions' => 0,\n                'online_activity' => null,\n                'material_text' => null,\n                'reference_text' => null,\n                'time_estimate' => null,\n                // Tetap anggap struktur pekan berasal dari alokasi pertemuan,\n                // sehingga Isi Bagian Kosong dapat menyusun ulang dari awal.\n                'source_type' => 'manual_allocation_auto',\n                'updated_at' => now(),\n            ]);\n\n        return back()->with(\n            'success',\n            \"Isi {$updated} pekan pembelajaran dikosongkan. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.\"\n        );\n    }\n\n'''
if 'public function clearWeeklyContent(' not in text:
    if anchor not in text:
        raise SystemExit('controller anchor not found')
    text = text.replace(anchor, method + anchor, 1)
controller.write_text(text)

# Frontend button
text = show.read_text()
anchor = '''                            <button\n                                type="button"\n                                title="Mengisi bagian RPS yang masih kosong dan menyinkronkan distribusi bobot dari Asesmen Detail. Pembagian bobot pekan yang sudah ditetapkan manual oleh dosen dipertahankan selama tetap sesuai anggaran Sub-CPMK."\n'''
button = '''                            <button\n                                type="button"\n                                onClick={() => {\n                                    if (!confirm(\n                                        'Kosongkan seluruh isi RPS pekanan?\\n\\n'\n                                        + 'Yang dikosongkan: indikator, kriteria/bentuk, metode, aktivitas, tugas, materi, pustaka, dan waktu pada 14 pekan pembelajaran.\\n\\n'\n                                        + 'Yang tetap dipertahankan: alokasi Sub-CPMK dari Atur Pertemuan, bobot penilaian, UTS/UAS, Asesmen Detail, dan RTM.'\n                                    )) return;\n\n                                    router.post(\n                                        `/rps/${rps.id}/weeks/clear-content`,\n                                        {},\n                                        actionOptions('Isi RPS pekanan berhasil dikosongkan. Anda dapat mulai mengisi ulang dari awal.'),\n                                    );\n                                }}\n                                className="rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1.5 text-[11px] font-bold text-rose-700 transition hover:bg-rose-100"\n                                title="Kosongkan isi 14 pekan pembelajaran tanpa menghapus alokasi Sub-CPMK, bobot, asesmen, atau RTM"\n                            >\n                                Kosongkan Isi Pekanan\n                            </button>\n''' + anchor
if 'Kosongkan Isi Pekanan' not in text:
    if anchor not in text:
        raise SystemExit('frontend anchor not found')
    text = text.replace(anchor, button, 1)
show.write_text(text)
