from pathlib import Path

controller = Path('app/Http/Controllers/RpsAutomationController.php')
show = Path('resources/js/pages/rps/show.tsx')

# Backend: Kosongkan Isi harus benar-benar membersihkan kolom (5) dan (6),
# termasuk data teknis pembelajaran. Struktur penilaian/Sub-CPMK tetap.
text = controller.read_text()
old = """                'assessment_method' => null,
                'learning_method' => null,
                'learning_activity' => null,
                'online_activity' => null,
                'material_text' => null,
                'reference_text' => null,"""
new = """                'assessment_method' => null,
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
                'time_estimate' => null,"""
if old not in text:
    raise SystemExit('backend clear array marker not found')
text = text.replace(old, new, 1)

old = """            \"Isi {$updated} pekan pembelajaran dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.\""""
new = """            \"Isi {$updated} pekan pembelajaran dikosongkan, termasuk Tatap Muka/Luring, Belajar Mandiri, Tugas Mandiri/Terstruktur, dan Daring/LMS. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.\""""
if old not in text:
    raise SystemExit('backend success message marker not found')
text = text.replace(old, new, 1)
controller.write_text(text)

# Frontend global trash button: perjelas bahwa data teknis pembelajaran ikut dihapus.
text = show.read_text()
old = """if (!confirm('Hapus isi seluruh 14 pekan pembelajaran? Isi akademik semua pekan akan dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;"""
new = """if (!confirm('Hapus isi seluruh 14 pekan pembelajaran? Indikator, kriteria/bentuk, Tatap Muka/Luring, metode, aktivitas kelas, Belajar Mandiri, Tugas Mandiri/Terstruktur, Daring/LMS, materi, dan pustaka akan dikosongkan. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;"""
if old not in text:
    raise SystemExit('global confirm marker not found')
text = text.replace(old, new, 1)

old = """actionOptions('Isi seluruh pekan berhasil dihapus. Data teknis, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),"""
new = """actionOptions('Isi seluruh pekan berhasil dihapus, termasuk Tatap Muka/Luring dan Daring. Alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),"""
if old not in text:
    raise SystemExit('global success marker not found')
text = text.replace(old, new, 1)

old = """title={meetingPlanReady ? 'Hapus isi seluruh pekan tanpa mereset data teknis atau struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}"""
new = """title={meetingPlanReady ? 'Hapus isi seluruh pekan termasuk Tatap Muka/Luring dan Daring, tanpa menghapus alokasi Sub-CPMK atau struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}"""
if old not in text:
    raise SystemExit('global title marker not found')
text = text.replace(old, new, 1)

# Clear per pekan dibuat konsisten dengan clear global.
old = """if (!confirm(`Kosongkan isi Pekan ${week.week_number}? Tatap muka, belajar mandiri, tugas mandiri/terstruktur, Sub-CPMK, dan bobot penilaian tetap dipertahankan.`)) {"""
new = """if (!confirm(`Kosongkan isi Pekan ${week.week_number}? Tatap Muka/Luring, Belajar Mandiri, Tugas Mandiri/Terstruktur, dan Daring/LMS juga akan dikosongkan. Sub-CPMK dan bobot penilaian tetap dipertahankan.`)) {"""
if old not in text:
    raise SystemExit('single-week confirm marker not found')
text = text.replace(old, new, 1)

old = """            learning_form: form.data.learning_form,
            learning_method: '',
            face_to_face_sessions: form.data.face_to_face_sessions,
            learning_activity: '',
            independent_study_sessions: form.data.independent_study_sessions,
            student_assignment: form.data.student_assignment,
            structured_task_sessions: form.data.structured_task_sessions,
            online_activity: '',
            material_text: '',
            reference_text: '',
            time_estimate: form.data.time_estimate,"""
new = """            learning_form: '',
            learning_method: '',
            face_to_face_sessions: 0,
            learning_activity: '',
            independent_study_sessions: 0,
            student_assignment: '',
            structured_task_sessions: 0,
            online_activity: '',
            material_text: '',
            reference_text: '',
            time_estimate: '',"""
if old not in text:
    raise SystemExit('single-week clear payload marker not found')
text = text.replace(old, new, 1)

old = """`Isi akademik Pekan ${week.week_number} dikosongkan di editor. Data tatap muka, belajar mandiri, dan tugas mandiri/terstruktur tetap. Klik Simpan untuk menyimpan perubahan.`,"""
new = """`Isi Pekan ${week.week_number} dikosongkan di editor, termasuk Tatap Muka/Luring, Belajar Mandiri, Tugas Mandiri/Terstruktur, dan Daring/LMS. Sub-CPMK dan bobot tetap. Klik Simpan untuk menyimpan perubahan.`,"""
if old not in text:
    raise SystemExit('single-week notice marker not found')
text = text.replace(old, new, 1)

# Ketika data teknis sudah 0/null, jangan tampilkan waktu 0x atau fallback Tatap Muka.
text = text.replace(
    '<div className="font-bold">{week.learning_form || \'Tatap Muka\'}</div>\n                <div>{formatFaceToFaceTime(week, c)}</div>',
    '<div className="font-bold">{week.learning_form || \'-\'}</div>\n                {Number(week.face_to_face_sessions || 0) > 0 && (\n                    <div>{formatFaceToFaceTime(week, c)}</div>\n                )}',
    1,
)
text = text.replace(
    '<div className="mt-2 font-bold">Belajar Mandiri</div>\n                <div>{formatIndependentTime(week, c)}</div>',
    '{Number(week.independent_study_sessions || 0) > 0 && (\n                    <>\n                        <div className="mt-2 font-bold">Belajar Mandiri</div>\n                        <div>{formatIndependentTime(week, c)}</div>\n                    </>\n                )}',
    1,
)
text = text.replace(
    '<div>{formatStructuredTime(week, c)}</div>',
    '{Number(week.structured_task_sessions || 0) > 0 && (\n                    <div>{formatStructuredTime(week, c)}</div>\n                )}',
    1,
)

# Tampilan inline/editor non-print juga jangan memunculkan waktu teknis 0x.
text = text.replace(
    '<div className="font-bold text-slate-800">{week.learning_form || \'Tatap Muka\'}</div>\n                    <div className="mt-1">{formatFaceToFaceTime(week, c)}</div>',
    '<div className="font-bold text-slate-800">{week.learning_form || \'-\'}</div>\n                    {Number(week.face_to_face_sessions || 0) > 0 && (\n                        <div className="mt-1">{formatFaceToFaceTime(week, c)}</div>\n                    )}',
    1,
)
text = text.replace(
    '<div className="mt-2"><strong>Belajar Mandiri:</strong></div>\n                    <div className="mt-1 text-sky-700">{formatIndependentTime(week, c)}</div>',
    '{Number(week.independent_study_sessions || 0) > 0 && (\n                        <>\n                            <div className="mt-2"><strong>Belajar Mandiri:</strong></div>\n                            <div className="mt-1 text-sky-700">{formatIndependentTime(week, c)}</div>\n                        </>\n                    )}',
    1,
)
text = text.replace(
    '<div className="mt-1 text-sky-700">{formatStructuredTime(week, c)}</div>',
    '{Number(week.structured_task_sessions || 0) > 0 && (\n                        <div className="mt-1 text-sky-700">{formatStructuredTime(week, c)}</div>\n                    )}',
    1,
)

show.write_text(text)

# Trigger marker: clear-weekly-learning-columns-v1
