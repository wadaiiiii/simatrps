from pathlib import Path

p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

sync_old = """        $importedExists = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->where('source_type', 'curriculum_syllabus')
            ->exists();

        if (! $importedExists || $this->syllabus->importedMaterialsLookLikeReferences($version->id)) {
            $this->syllabus->syncMaterials(
                $rps->course_id,
                $version->id,
                true
            );
        }

        $this->ensureExamAssessments($version->id, $userId);
"""

sync_new = """        $weeklyColumns = array_flip(Schema::getColumnListing('rps_weekly_plans'));

        // Sinkronisasi silabus hanya memperkaya draft. Jika data master bermasalah,
        // proses utama tetap dapat mengisi minggu dari Sub-CPMK yang tersedia.
        try {
            $importedExists = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->where('source_type', 'curriculum_syllabus')
                ->exists();

            if (! $importedExists || $this->syllabus->importedMaterialsLookLikeReferences($version->id)) {
                $this->syllabus->syncMaterials(
                    $rps->course_id,
                    $version->id,
                    true
                );
            }
        } catch (\\Throwable $exception) {
            report($exception);
        }

        $this->ensureExamAssessments($version->id, $userId);
"""

if '$weeklyColumns = array_flip(' not in s:
    if sync_old not in s:
        raise SystemExit('sync target not found')
    s = s.replace(sync_old, sync_new, 1)

payload_marker = """                'source_type' => 'smart_draft',
            ];

            $merged = [
"""
payload_new = """                'source_type' => 'smart_draft',
            ];

            // Deployment lama mungkin belum memiliki seluruh kolom tambahan.
            // Jangan biarkan satu kolom opsional membuat seluruh proses gagal.
            $payload = array_intersect_key($payload, $weeklyColumns);

            $merged = [
"""
if '$payload = array_intersect_key($payload, $weeklyColumns);' not in s:
    if payload_marker not in s:
        raise SystemExit('payload marker not found')
    s = s.replace(payload_marker, payload_new, 1)

s = s.replace("DB::table('assessments')->insert($rows);", "DB::table('assessments')->insertOrIgnore($rows);", 1)

p.write_text(s, encoding='utf-8')
