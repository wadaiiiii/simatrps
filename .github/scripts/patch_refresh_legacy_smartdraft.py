from pathlib import Path

p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

old = """                // Nilai 0 pada frekuensi hasil generator lama bukan estimasi
                // pembelajaran yang valid. Lengkapi RPS Otomatis boleh
                // memperbaikinya menjadi minimal 1 tanpa menyentuh angka
                // manual lain yang sudah positif.
                $sessionField = in_array($key, [
                    'face_to_face_sessions',
                    'structured_task_sessions',
                    'independent_study_sessions',
                ], true);
                if ($sessionField && (int) $existing < 1 && (int) $value >= 1) {
                    $merged[$key] = $value;
                    $changed = true;
                    continue;
                }

                if ($mode === 'overwrite') {
"""
new = """                // Data minggu yang dibuat Smart Draft versi lama boleh
                // dinormalisasi ke algoritme penyelarasan baru. Ini hanya berlaku
                // bila source_type masih smart_draft; edit manual/AI tidak disentuh.
                $refreshGeneratedField = ($current->source_type ?? null) === 'smart_draft'
                    && in_array($key, [
                        'rps_sub_cpmk_id',
                        'material_text',
                        'learning_activity',
                        'student_assignment',
                        'assessment_indicator',
                        'assessment_criteria',
                        'assessment_method',
                        'time_estimate',
                    ], true);

                if ($refreshGeneratedField && filled($value)) {
                    $merged[$key] = $value;
                    if ($existing != $value) {
                        $changed = true;
                    }
                    continue;
                }

                // Nilai 0 pada frekuensi hasil generator lama bukan estimasi
                // pembelajaran yang valid. Lengkapi RPS Otomatis boleh
                // memperbaikinya menjadi minimal 1 tanpa menyentuh angka
                // manual lain yang sudah positif.
                $sessionField = in_array($key, [
                    'face_to_face_sessions',
                    'structured_task_sessions',
                    'independent_study_sessions',
                ], true);
                if ($sessionField && (int) $existing < 1 && (int) $value >= 1) {
                    $merged[$key] = $value;
                    $changed = true;
                    continue;
                }

                if ($mode === 'overwrite') {
"""
if old not in s:
    raise SystemExit('legacy SmartDraft refresh target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')

p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
old = """            $sessionField = in_array($key, [
                'face_to_face_sessions',
                'structured_task_sessions',
                'independent_study_sessions',
            ], true);

            if (
                $overwrite
                || ! filled($weekly->{$key} ?? null)
                || ($sessionField && (int) ($weekly->{$key} ?? 0) < 1)
            ) {
"""
new = """            $sessionField = in_array($key, [
                'face_to_face_sessions',
                'structured_task_sessions',
                'independent_study_sessions',
            ], true);
            $invalidLegacyTime = $key === 'time_estimate'
                && is_string($weekly->{$key} ?? null)
                && preg_match('/(?:^|[;\\s])0\\s*[×x]\\s*\\(/u', (string) $weekly->{$key}) === 1;

            if (
                $overwrite
                || ! filled($weekly->{$key} ?? null)
                || ($sessionField && (int) ($weekly->{$key} ?? 0) < 1)
                || $invalidLegacyTime
            ) {
"""
if old not in s:
    raise SystemExit('AI legacy time guard target not found')
s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
