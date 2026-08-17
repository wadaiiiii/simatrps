from pathlib import Path

repo = Path('.')

# 1) Do not mutate/create RTM during a normal page GET.
controller = repo / 'app/Http/Controllers/RpsController.php'
text = controller.read_text(encoding='utf-8')
old = """        // RPS lama dapat memiliki asesmen wajib yang belum mempunyai RTM.\n        // Heal hanya ketika rantainya memang belum selaras; RPS yang sudah\n        // valid tidak melakukan write pada setiap GET.\n        $initialTaskAlignment = $assessmentSync->taskAlignment($version->id);\n        if (! (bool) ($initialTaskAlignment['is_aligned'] ?? false)) {\n            $assessmentSync->syncVersion($version->id);\n        }\n\n"""
if old not in text:
    raise SystemExit('RpsController auto-heal block not found')
text = text.replace(old, """        // Halaman RPS bersifat read-only terhadap RTM. RTM yang hilang/duplikat\n        // diperbaiki hanya melalui aksi eksplisit (asesmen, RTM, atau sinkronisasi),\n        // bukan saat halaman sekadar dibuka. Ini mencegah RTM yang dihapus\n        // muncul kembali secara otomatis.\n\n""", 1)
controller.write_text(text, encoding='utf-8')

# 2) Guard deleting the sole RTM of a required assessment; allow true duplicates.
task_controller = repo / 'app/Http/Controllers/RpsTaskController.php'
text = task_controller.read_text(encoding='utf-8')
old = """    public function destroy(Request $request, string $rps, string $task): RedirectResponse\n    {\n        [, $version] = $this->context($request, $rps);\n\n        DB::table('rps_tasks')\n            ->where('id', $task)\n            ->where('rps_version_id', $version->id)\n            ->delete();\n\n        return back()->with('success', 'RTM dihapus.');\n    }\n"""
new = """    public function destroy(Request $request, string $rps, string $task): RedirectResponse\n    {\n        [, $version] = $this->context($request, $rps);\n\n        $existing = DB::table('rps_tasks')\n            ->where('id', $task)\n            ->where('rps_version_id', $version->id)\n            ->first();\n\n        abort_unless($existing, 404);\n\n        if (filled($existing->assessment_id ?? null)) {\n            $assessment = DB::table('assessments')\n                ->where('id', $existing->assessment_id)\n                ->where('rps_version_id', $version->id)\n                ->first(['id', 'name', 'type', 'weight']);\n\n            $requiresRtm = $assessment\n                && in_array(strtolower((string) $assessment->type), [\n                    'assignment', 'project', 'practicum', 'presentation',\n                ], true)\n                && (float) ($assessment->weight ?? 0) > 0;\n\n            if ($requiresRtm) {\n                $otherRtmCount = DB::table('rps_tasks')\n                    ->where('rps_version_id', $version->id)\n                    ->where('assessment_id', $assessment->id)\n                    ->where('id', '!=', $task)\n                    ->count();\n\n                if ($otherRtmCount === 0) {\n                    throw \\Illuminate\\Validation\\ValidationException::withMessages([\n                        'task' => 'RTM ini masih menjadi satu-satunya RTM untuk asesmen "'\n                            .trim((string) $assessment->name)\n                            .'". Jika hanya bentrok pekan, ubah Pekan Pengumpulan. Jika asesmennya tidak diperlukan, ubah atau hapus asesmen pada Detail Asesmen.',\n                    ]);\n                }\n            }\n        }\n\n        DB::table('rps_tasks')\n            ->where('id', $task)\n            ->where('rps_version_id', $version->id)\n            ->delete();\n\n        return back()->with('success', 'RTM berhasil dihapus.');\n    }\n"""
if old not in text:
    raise SystemExit('RpsTaskController destroy block not found')
text = text.replace(old, new, 1)
task_controller.write_text(text, encoding='utf-8')

print('RTM delete/recreation patch applied')
