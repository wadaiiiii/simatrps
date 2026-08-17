from pathlib import Path


def replace_if_present(text: str, old: str, new: str) -> str:
    return text.replace(old, new, 1) if old in text else text


# Preserve technical workload when clearing all teaching weeks.
p = Path('app/Http/Controllers/RpsAutomationController.php')
s = p.read_text(encoding='utf-8')
old = """                'assessment_indicator' => null,
                'assessment_criteria' => null,
                'assessment_method' => null,
                'learning_form' => null,
                'learning_method' => null,
                'face_to_face_sessions' => 0,
                'learning_activity' => null,
                'independent_study_sessions' => 0,
                'student_assignment' => null,
                'structured_task_sessions' => 0,
                'online_activity' => null,
                'material_text' => null,
                'reference_text' => null,
                'time_estimate' => null,
"""
new = """                'assessment_indicator' => null,
                'assessment_criteria' => null,
                'assessment_method' => null,
                'learning_method' => null,
                'learning_activity' => null,
                'online_activity' => null,
                'material_text' => null,
                'reference_text' => null,
"""
s = replace_if_present(s, old, new)
s = s.replace(
    '"Isi {$updated} pekan pembelajaran dikosongkan. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan."',
    '"Isi {$updated} pekan pembelajaran dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan."',
)
section = s[s.find('public function clearWeeklyContent'):s.find('public function copyPrevious')]
for forbidden in [
    "'face_to_face_sessions' => 0",
    "'structured_task_sessions' => 0",
    "'independent_study_sessions' => 0",
    "'student_assignment' => null",
    "'learning_form' => null",
    "'time_estimate' => null",
]:
    if forbidden in section:
        raise SystemExit(f'clearWeeklyContent still resets technical workload: {forbidden}')
p.write_text(s, encoding='utf-8')


# UI labels, toolbar copy, and per-week clear behavior.
p = Path('resources/js/pages/rps/show.tsx')
s = p.read_text(encoding='utf-8')
s = s.replace('Lengkapi Data Teknis', 'Isi Data Teknis')
s = s.replace('Isi Bagian Kosong', 'Isi Data Teknis')
s = s.replace('Kosongkan Isi Pekanan', 'Kosongkan Isi')
s = s.replace(
    "if (!confirm('Kosongkan seluruh isi akademik 14 pekan pembelajaran? Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;",
    "if (!confirm('Kosongkan isi akademik 14 pekan pembelajaran? Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;",
)
s = s.replace(
    "actionOptions('Isi pekanan dikosongkan. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),",
    "actionOptions('Isi pekanan dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),",
)
s = s.replace(
    "title={meetingPlanReady ? 'Reset isi akademik pekanan tanpa menghapus struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}",
    "title={meetingPlanReady ? 'Kosongkan isi akademik tanpa mereset tatap muka, belajar mandiri, tugas mandiri/terstruktur, atau struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}",
)
s = s.replace(
    "if (!confirm(`Kosongkan isi Pekan ${week.week_number}? Sub-CPMK dan bobot penilaian tetap dipertahankan.`)) {",
    "if (!confirm(`Kosongkan isi Pekan ${week.week_number}? Tatap muka, belajar mandiri, tugas mandiri/terstruktur, Sub-CPMK, dan bobot penilaian tetap dipertahankan.`)) {",
)
old_form = """            rps_sub_cpmk_id: form.data.rps_sub_cpmk_id,
            assessment_indicator: '',
            assessment_criteria: '',
            assessment_method: '',
            learning_form: '',
            learning_method: '',
            face_to_face_sessions: '0',
            learning_activity: '',
            independent_study_sessions: '0',
            student_assignment: '',
            structured_task_sessions: '0',
            online_activity: '',
            material_text: '',
            reference_text: '',
            time_estimate: '',
"""
new_form = """            rps_sub_cpmk_id: form.data.rps_sub_cpmk_id,
            assessment_indicator: '',
            assessment_criteria: '',
            assessment_method: '',
            learning_form: form.data.learning_form,
            learning_method: '',
            face_to_face_sessions: form.data.face_to_face_sessions,
            learning_activity: '',
            independent_study_sessions: form.data.independent_study_sessions,
            student_assignment: form.data.student_assignment,
            structured_task_sessions: form.data.structured_task_sessions,
            online_activity: '',
            material_text: '',
            reference_text: '',
            time_estimate: form.data.time_estimate,
"""
s = replace_if_present(s, old_form, new_form)
s = s.replace(
    "`Isian Pekan ${week.week_number} dikosongkan di editor. Klik Simpan untuk menyimpan perubahan.`,",
    "`Isi akademik Pekan ${week.week_number} dikosongkan di editor. Data tatap muka, belajar mandiri, dan tugas mandiri/terstruktur tetap. Klik Simpan untuk menyimpan perubahan.`,",
)
if 'Isi Data Teknis' not in s or 'Kosongkan Isi' not in s:
    raise SystemExit('Expected new weekly toolbar labels are missing')
if 'Kosongkan Isi Pekanan' in s or 'Lengkapi Data Teknis' in s or 'Isi Bagian Kosong' in s:
    raise SystemExit('Old weekly labels are still present')
for required in [
    'learning_form: form.data.learning_form',
    'face_to_face_sessions: form.data.face_to_face_sessions',
    'independent_study_sessions: form.data.independent_study_sessions',
    'student_assignment: form.data.student_assignment',
    'structured_task_sessions: form.data.structured_task_sessions',
    'time_estimate: form.data.time_estimate',
]:
    if required not in s:
        raise SystemExit(f'Per-week clear does not preserve: {required}')
p.write_text(s, encoding='utf-8')
