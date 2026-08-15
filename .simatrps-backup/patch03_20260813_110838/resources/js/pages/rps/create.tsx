import { Head, useForm } from '@inertiajs/react';
import { BookOpenCheck, CheckCircle2, CircleAlert, FilePlus2 } from 'lucide-react';
import { useMemo, useState } from 'react';

type Curriculum = {
    id: string;
    code: string;
    name: string;
    year: number;
    effective_academic_year?: string | null;
};

type Course = {
    id: string;
    curriculum_id: string;
    system_code: string;
    official_code?: string | null;
    name: string;
    credits: number;
    semester_recommended?: number | null;
    has_practicum: boolean;
    official_cpl_codes: string[];
    official_cpmk_count: number;
    has_master_syllabus: boolean;
    generator_readiness: 'ready_with_master_cpmk' | 'ai_cpmk_required' | 'needs_admin_review';
};

export default function CreateRps({
    curriculums,
    courses,
    defaultAcademicYear,
}: {
    curriculums: Curriculum[];
    courses: Course[];
    defaultAcademicYear: string;
}) {
    const [curriculumId, setCurriculumId] = useState(curriculums[0]?.id ?? '');

    const form = useForm({
        course_id: '',
        academic_year: defaultAcademicYear,
        academic_semester: 'Ganjil',
    });

    const filteredCourses = useMemo(
        () => courses.filter((course) => course.curriculum_id === curriculumId),
        [courses, curriculumId],
    );

    const selected = useMemo(
        () => courses.find((course) => course.id === form.data.course_id),
        [courses, form.data.course_id],
    );

    const readiness = selected?.generator_readiness;

    const readinessLabel = readiness === 'ready_with_master_cpmk'
        ? 'Siap disusun'
        : readiness === 'ai_cpmk_required'
            ? 'Perlu rekomendasi CPMK'
            : 'Perlu review Admin';

    const readinessClass = readiness === 'ready_with_master_cpmk'
        ? 'bg-emerald-50 text-emerald-700'
        : readiness === 'ai_cpmk_required'
            ? 'bg-amber-50 text-amber-700'
            : 'bg-rose-50 text-rose-700';

    return (
        <>
            <Head title="Buat RPS" />

            <div className="p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Buat RPS</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Pilih mata kuliah dari master Kurikulum Matematika 2025.
                    </p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/rps');
                    }}
                    className="sim-surface mt-6 max-w-5xl rounded-2xl p-6 md:p-8"
                >
                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-teal-50 p-3 text-teal-700">
                            <FilePlus2 className="size-6" />
                        </div>
                        <div>
                            <p className="font-bold text-slate-900">Generator RPS SiMatRPS</p>
                            <p className="text-sm text-slate-500">
                                Identitas, CPL, CPMK dan silabus dibaca dari master kurikulum.
                            </p>
                        </div>
                    </div>

                    <div className="mt-7 grid gap-5 md:grid-cols-2">
                        <label className="block">
                            <span className="mb-2 block text-sm font-semibold text-slate-700">Kurikulum</span>
                            <select
                                value={curriculumId}
                                onChange={(event) => {
                                    setCurriculumId(event.target.value);
                                    form.setData('course_id', '');
                                }}
                                className="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-sm outline-none focus:border-teal-400"
                            >
                                {curriculums.map((curriculum) => (
                                    <option key={curriculum.id} value={curriculum.id}>
                                        {curriculum.name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-2 block text-sm font-semibold text-slate-700">Mata Kuliah</span>
                            <select
                                value={form.data.course_id}
                                onChange={(event) => form.setData('course_id', event.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-sm outline-none focus:border-teal-400"
                            >
                                <option value="">Pilih mata kuliah</option>
                                {filteredCourses.map((course) => (
                                    <option key={course.id} value={course.id}>
                                        Semester {course.semester_recommended ?? '-'} — {course.name} ({course.credits} SKS)
                                    </option>
                                ))}
                            </select>
                            {form.errors.course_id && (
                                <div className="mt-2 text-xs font-medium text-rose-600">{form.errors.course_id}</div>
                            )}
                        </label>
                    </div>

                    {selected && (
                        <div className="mt-7 rounded-2xl border border-teal-100 bg-teal-50/35 p-5">
                            <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                                <div>
                                    <div className="text-xs font-semibold text-slate-500">
                                        {selected.official_code || selected.system_code}
                                    </div>
                                    <h2 className="mt-1 text-xl font-bold text-slate-900">{selected.name}</h2>
                                </div>
                                <span className={`w-fit rounded-full px-3 py-1 text-xs font-bold ${readinessClass}`}>
                                    {readinessLabel}
                                </span>
                            </div>

                            <div className="mt-5 grid gap-3 sm:grid-cols-4">
                                {[
                                    ['SKS', selected.credits],
                                    ['Semester', selected.semester_recommended ?? '-'],
                                    ['CPMK Master', selected.official_cpmk_count],
                                    ['Praktikum', selected.has_practicum ? 'Ya' : 'Tidak'],
                                ].map(([label, value]) => (
                                    <div key={label} className="rounded-xl border border-slate-100 bg-white/75 p-4">
                                        <div className="text-xs text-slate-500">{label}</div>
                                        <div className="mt-1 font-bold text-slate-900">{value}</div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-5">
                                <div className="text-xs font-bold uppercase tracking-wider text-slate-400">CPL Resmi Mata Kuliah</div>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {selected.official_cpl_codes.map((code) => (
                                        <span key={code} className="rounded-full bg-white px-3 py-1 text-xs font-bold text-teal-700 shadow-sm">
                                            {code}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            {readiness === 'ai_cpmk_required' && (
                                <div className="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                    CPMK master belum tersedia. Draft tetap dapat dibuat, tetapi CPMK akan memerlukan rekomendasi AI dan keputusan dosen pada tahap berikutnya.
                                </div>
                            )}

                            {readiness === 'needs_admin_review' && (
                                <div className="mt-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                    Identitas akademik mata kuliah masih perlu diverifikasi Admin. Generator dinonaktifkan untuk mencegah data resmi yang belum pasti digunakan.
                                </div>
                            )}

                            {readiness === 'ready_with_master_cpmk' && (
                                <div className="mt-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                    CPL, CPMK dan sumber silabus tersedia. Mata kuliah siap masuk workspace RPS.
                                </div>
                            )}
                        </div>
                    )}

                    <div className="mt-7 grid gap-5 border-t border-slate-100 pt-7 md:grid-cols-2">
                        <label>
                            <span className="mb-2 block text-sm font-semibold text-slate-700">Tahun Akademik</span>
                            <input
                                value={form.data.academic_year}
                                onChange={(event) => form.setData('academic_year', event.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-sm"
                                placeholder="2026/2027"
                            />
                            {form.errors.academic_year && <div className="mt-2 text-xs text-rose-600">{form.errors.academic_year}</div>}
                        </label>

                        <label>
                            <span className="mb-2 block text-sm font-semibold text-slate-700">Semester Pelaksanaan</span>
                            <select
                                value={form.data.academic_semester}
                                onChange={(event) => form.setData('academic_semester', event.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-3 text-sm"
                            >
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                                <option value="Pendek">Pendek</option>
                            </select>
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={!selected || readiness === 'needs_admin_review' || form.processing}
                        className="mt-6 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        <BookOpenCheck className="size-4" />
                        {form.processing ? 'Membuat draft...' : 'Buat Draft RPS'}
                    </button>
                </form>
            </div>
        </>
    );
}

CreateRps.layout = {
    breadcrumbs: [{ title: 'Buat RPS', href: '/rps/baru' }],
};
