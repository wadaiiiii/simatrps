from pathlib import Path

# Prevent Lengkapi RPS Otomatis from mutating Bahan Kajian/Pustaka.
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

old = '''        // Sinkronisasi silabus hanya memperkaya draft. Jika data master bermasalah,\n        // proses utama tetap dapat mengisi minggu dari Sub-CPMK yang tersedia.\n        try {\n            $importedExists = DB::table('rps_materials')\n                ->where('rps_version_id', $version->id)\n                ->where('source_type', 'curriculum_syllabus')\n                ->exists();\n\n            if (! $importedExists || $this->syllabus->importedMaterialsLookLikeReferences($version->id)) {\n                $this->syllabus->syncMaterials(\n                    $rps->course_id,\n                    $version->id,\n                    true\n                );\n            }\n        } catch (\\Throwable $exception) {\n            report($exception);\n        }\n\n        $this->ensureExamAssessments($version->id, $userId);\n\n        $materials = DB::table('rps_materials')\n            ->where('rps_version_id', $version->id)\n            ->orderBy('sequence_no')\n            ->get();\n'''

new = '''        // Lengkapi RPS Otomatis hanya mengisi tabel mingguan/asesmen dasar.\n        // Bahan Kajian dan Pustaka adalah data akademik yang dikelola eksplisit\n        // melalui Edit, Ambil dari Kurikulum, atau Telaah AI masing-masing.\n        // Karena itu proses otomatis TIDAK BOLEH menyinkronkan atau menulis ulang\n        // rps_materials maupun pustaka yang sudah diputuskan dosen.\n        $this->ensureExamAssessments($version->id, $userId);\n\n        $materials = DB::table('rps_materials')\n            ->where('rps_version_id', $version->id)\n            ->orderBy('sequence_no')\n            ->get();\n\n        // Jika daftar Bahan Kajian benar-benar kosong, topik silabus hanya dipakai\n        // sebagai fallback sementara untuk mengisi kolom Materi pada tabel pekan.\n        // Fallback ini TIDAK disimpan ke rps_materials sehingga daftar Bahan Kajian\n        // milik dosen tetap tidak berubah.\n        if ($materials->isEmpty()) {\n            $materials = collect($this->syllabus->topics($rps->course_id))\n                ->map(fn (string $title) => (object) [\n                    'id' => null,\n                    'rps_sub_cpmk_id' => null,\n                    'title' => $title,\n                    'source_type' => 'syllabus_fallback_readonly',\n                ])\n                ->values();\n        }\n'''

if old not in s:
    raise SystemExit('SmartDraft sync block not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

# Preserve provenance when curriculum-syllabus material is manually edited.
p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')
old = """                'source_type' => $row->source_type === 'curriculum'\n                    ? 'adapted'\n                    : 'manual',\n"""
new = """                'source_type' => in_array($row->source_type, ['curriculum', 'curriculum_syllabus'], true)\n                    ? 'adapted'\n                    : 'manual',\n"""
if old not in s:
    raise SystemExit('material source_type target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
