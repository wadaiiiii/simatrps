import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    BookOpenCheck,
    ChevronDown,
    GraduationCap,
    LibraryBig,
    Network,
    PencilLine,
    Plus,
    Save,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Curriculum = {
    id: string;
    code: string;
    name: string;
    effective_academic_year?: string | null;
    end_academic_year?: string | null;
    notes?: string | null;
    status: string;
};

type Cpl = {
    id: string;
    code: string;
    description: string;
    domain?: string | null;
    sequence_no: number;
    is_active: boolean;
};

type Cpmk = {
    id: string;
    code: string;
    description: string;
    sequence_no: number;
    verification_status: string;
};

type Course = {
    id: string;
    system_code: string;
    official_code?: string | null;
    name: string;
    credits: number;
    semester_recommended?: number | null;
    is_mandatory: boolean;
    has_practicum: boolean;
    is_active: boolean;
    code_status: string;
    verification_status: string;
    prerequisite_note?: string | null;
    cpl_ids: string[];
    cpl_codes: string[];
    cpmk_count: number;
    cpmks: Cpmk[];
    has_syllabus: boolean;
    readiness: string;
    rps_count: number;
};

type Summary = {
    cpl: number;
    kbk: number;
    courses: number;
    courseCpl: number;
    cpmk: number;
    syllabi: number;
    issues: number;
    rpsUsingMaster: number;
};

type Issue = {
    id: string;
    issue_code: string;
    entity_key?: string | null;
    severity: string;
    issue_description: string;
    selected_value?: string | null;
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100';
const labelClass = 'mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500';

export default function CurriculumPage({ curriculum, summary, cpls, courses, issues }: {
    curriculum: Curriculum;
    summary: Summary;
    cpls: Cpl[];
    courses: Course[];
    issues: Issue[];
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();
        if (!term) return courses;
        return courses.filter((course) =>
            `${course.system_code} ${course.official_code || ''} ${course.name}`.toLowerCase().includes(term),
        );
    }, [courses, query]);

    return (
        <>
            <Head title="Kelola Kurikulum" />
            <div className="p-4 md:p-6">
                <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Master Akademik</div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">Kelola Kurikulum</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Koreksi master kurikulum dari panel admin tanpa mengubah data langsung melalui database.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 xl:max-w-xl">
                        <div className="flex gap-2">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <b>Perhatikan dampaknya.</b> CPMK master yang diubah tidak menimpa CPMK pada RPS yang sudah dibuat. Namun metadata mata kuliah, deskripsi CPL, dan pemetaan MK↔CPL dapat terlihat pada RPS yang sedang aktif.
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                    {[
                        ['CPL', summary.cpl, Network],
                        ['KBK', summary.kbk, LibraryBig],
                        ['Mata Kuliah', summary.courses, GraduationCap],
                        ['MK-CPL', summary.courseCpl, Network],
                        ['CPMK', summary.cpmk, BookOpenCheck],
                        ['RPS Aktif', summary.rpsUsingMaster, PencilLine],
                        ['Isu', summary.issues, AlertTriangle],
                    ].map(([label, value, Icon]: any) => (
                        <div key={label} className="sim-surface rounded-2xl p-4">
                            <Icon className="size-4 text-teal-700" />
                            <div className="mt-3 text-xs text-slate-500">{label}</div>
                            <div className="mt-1 text-2xl font-bold text-slate-900">{value}</div>
                        </div>
                    ))}
                </div>

                <CurriculumIdentityForm curriculum={curriculum} />

                <section className="sim-surface mt-6 rounded-2xl p-5">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="font-bold text-slate-900">CPL Kurikulum</h2>
                            <p className="mt-1 text-sm text-slate-500">Kode CPL dipertahankan; Admin dapat memperbaiki rumusan, domain, dan status aktif.</p>
                        </div>
                        <span className="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{cpls.length} CPL</span>
                    </div>
                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                        {cpls.map((cpl) => <CplEditor key={cpl.id} cpl={cpl} />)}
                    </div>
                </section>

                <section className="sim-surface mt-6 overflow-hidden rounded-2xl">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-900">Master Mata Kuliah</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Klik <b>Kelola</b> untuk mengubah metadata, CPL, dan CPMK master. {filtered.length} dari {courses.length} mata kuliah ditampilkan.
                            </p>
                        </div>
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari kode atau mata kuliah..."
                            className={`${inputClass} md:w-80`}
                        />
                    </div>

                    <div className="divide-y divide-slate-100">
                        {filtered.map((course) => <CourseManager key={course.id} course={course} cpls={cpls} />)}
                    </div>
                </section>

                <section className="sim-surface mt-6 rounded-2xl p-5">
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="size-5 text-amber-600" />
                        <h2 className="font-bold text-slate-900">Isu Data Kurikulum</h2>
                    </div>
                    <p className="mt-1 text-sm text-slate-500">Perbedaan pada dokumen sumber tetap dicatat agar koreksi master dapat ditelusuri secara akademik.</p>
                    <div className="mt-4 space-y-3">
                        {issues.length === 0 && (
                            <div className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">Tidak ada isu kurikulum terbuka.</div>
                        )}
                        {issues.map((issue) => (
                            <details key={issue.id} className="rounded-xl border border-amber-100 bg-amber-50/45 p-4">
                                <summary className="cursor-pointer font-semibold text-slate-800">
                                    {issue.entity_key || issue.issue_code}
                                    <span className="ml-2 text-xs font-normal text-amber-700">{issue.severity}</span>
                                </summary>
                                <div className="mt-3 text-sm leading-6 text-slate-600">{issue.issue_description}</div>
                                {issue.selected_value && <div className="mt-2 text-sm"><strong>Nilai master:</strong> {issue.selected_value}</div>}
                            </details>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

function CurriculumIdentityForm({ curriculum }: { curriculum: Curriculum }) {
    const form = useForm({
        name: curriculum.name || '',
        effective_academic_year: curriculum.effective_academic_year || '',
        end_academic_year: curriculum.end_academic_year || '',
        notes: curriculum.notes || '',
    });

    return (
        <section className="sim-surface mt-6 rounded-2xl p-5">
            <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 className="font-bold text-slate-900">Identitas Kurikulum</h2>
                    <p className="mt-1 text-sm text-slate-500">Kode master <b>{curriculum.code}</b> dikunci agar relasi data tetap stabil.</p>
                </div>
                <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase text-emerald-700">{curriculum.status}</span>
            </div>
            <form
                className="mt-5 grid gap-4 lg:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put('/admin/kurikulum', { preserveScroll: true });
                }}
            >
                <Field label="Nama Kurikulum" error={form.errors.name}>
                    <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label="Tahun Akademik Mulai" error={form.errors.effective_academic_year}>
                    <input className={inputClass} value={form.data.effective_academic_year} onChange={(e) => form.setData('effective_academic_year', e.target.value)} placeholder="2025/2026" />
                </Field>
                <Field label="Tahun Akademik Berakhir" error={form.errors.end_academic_year}>
                    <input className={inputClass} value={form.data.end_academic_year} onChange={(e) => form.setData('end_academic_year', e.target.value)} placeholder="Opsional" />
                </Field>
                <Field label="Catatan Master" error={form.errors.notes}>
                    <textarea className={`${inputClass} min-h-24`} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Catatan administratif kurikulum" />
                </Field>
                <div className="lg:col-span-2 flex justify-end">
                    <SaveButton processing={form.processing} label="Simpan Identitas Kurikulum" />
                </div>
            </form>
        </section>
    );
}

function CplEditor({ cpl }: { cpl: Cpl }) {
    const form = useForm({
        description: cpl.description,
        domain: cpl.domain || '',
        is_active: cpl.is_active,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/admin/kurikulum/cpl/${cpl.id}`, { preserveScroll: true });
            }}
            className="rounded-2xl border border-slate-200 bg-white p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="font-extrabold text-teal-800">{cpl.code}</div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                    Aktif
                </label>
            </div>
            <div className="mt-3">
                <Field label="Rumusan CPL" error={form.errors.description}>
                    <textarea className={`${inputClass} min-h-28`} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
            </div>
            <div className="mt-3">
                <Field label="Domain" error={form.errors.domain}>
                    <input className={inputClass} value={form.data.domain} onChange={(e) => form.setData('domain', e.target.value)} placeholder="Contoh: Pengetahuan / Keterampilan" />
                </Field>
            </div>
            <div className="mt-4 flex justify-end">
                <SaveButton processing={form.processing} label={`Simpan ${cpl.code}`} compact />
            </div>
        </form>
    );
}

function CourseManager({ course, cpls }: { course: Course; cpls: Cpl[] }) {
    const readinessLabel = course.readiness === 'ready_with_master_cpmk'
        ? 'Siap'
        : course.readiness === 'ai_cpmk_required'
            ? 'Perlu CPMK'
            : 'Review Admin';
    const readinessClass = course.readiness === 'ready_with_master_cpmk'
        ? 'bg-emerald-50 text-emerald-700'
        : course.readiness === 'ai_cpmk_required'
            ? 'bg-amber-50 text-amber-700'
            : 'bg-rose-50 text-rose-700';

    return (
        <details className="group">
            <summary className="flex cursor-pointer list-none flex-col gap-3 px-5 py-4 hover:bg-teal-50/30 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0">
                    <div className="font-semibold text-slate-900">{course.name}</div>
                    <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                        <span>{course.official_code || course.system_code}</span>
                        <span>{course.credits} SKS</span>
                        <span>Semester {course.semester_recommended ?? '-'}</span>
                        <span>{course.cpmk_count} CPMK</span>
                        <span>{course.rps_count} RPS menggunakan master</span>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {course.cpl_codes.map((code) => (
                        <span key={code} className="rounded-full bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700">{code}</span>
                    ))}
                    <span className={`rounded-full px-3 py-1 text-xs font-bold ${readinessClass}`}>{readinessLabel}</span>
                    {!course.is_active && <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Nonaktif</span>}
                    <span className="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">
                        Kelola <ChevronDown className="size-3.5 transition group-open:rotate-180" />
                    </span>
                </div>
            </summary>

            <div className="border-t border-slate-100 bg-slate-50/45 p-5">
                {course.rps_count > 0 && (
                    <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        Mata kuliah ini sudah dipakai pada <b>{course.rps_count} RPS</b>. Koreksi metadata resmi akan terlihat pada RPS tersebut. Perubahan CPMK master tidak menimpa CPMK RPS yang sudah dibuat.
                    </div>
                )}
                <div className="grid gap-5 2xl:grid-cols-2">
                    <CourseEditor course={course} />
                    <CourseCplEditor course={course} cpls={cpls} />
                </div>
                <CpmkManager course={course} />
            </div>
        </details>
    );
}

function CourseEditor({ course }: { course: Course }) {
    const form = useForm({
        official_code: course.official_code || '',
        name: course.name,
        credits: course.credits,
        semester_recommended: course.semester_recommended || 1,
        is_mandatory: course.is_mandatory,
        has_practicum: course.has_practicum,
        is_active: course.is_active,
        code_status: course.code_status,
        verification_status: course.verification_status,
        prerequisite_note: course.prerequisite_note || '',
    });

    return (
        <form
            className="rounded-2xl border border-slate-200 bg-white p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/admin/kurikulum/mata-kuliah/${course.id}`, { preserveScroll: true });
            }}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-bold text-slate-900">Identitas Mata Kuliah</h3>
                    <p className="mt-1 text-xs text-slate-500">System code: {course.system_code}</p>
                </div>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> Aktif
                </label>
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
                <Field label="Kode Resmi" error={form.errors.official_code}>
                    <input className={inputClass} value={form.data.official_code} onChange={(e) => form.setData('official_code', e.target.value)} />
                </Field>
                <Field label="Nama Mata Kuliah" error={form.errors.name}>
                    <input className={inputClass} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label="SKS" error={form.errors.credits}>
                    <input type="number" step="0.5" className={inputClass} value={form.data.credits} onChange={(e) => form.setData('credits', Number(e.target.value))} />
                </Field>
                <Field label="Semester Rekomendasi" error={form.errors.semester_recommended}>
                    <input type="number" min="1" max="14" className={inputClass} value={form.data.semester_recommended} onChange={(e) => form.setData('semester_recommended', Number(e.target.value))} />
                </Field>
                <Field label="Status Kode" error={form.errors.code_status}>
                    <select className={inputClass} value={form.data.code_status} onChange={(e) => form.setData('code_status', e.target.value)}>
                        <option value="official">Official</option>
                        <option value="internal">Internal / perlu koreksi</option>
                    </select>
                </Field>
                <Field label="Verifikasi Master" error={form.errors.verification_status}>
                    <select className={inputClass} value={form.data.verification_status} onChange={(e) => form.setData('verification_status', e.target.value)}>
                        <option value="source_verified">Terverifikasi sumber</option>
                        <option value="needs_review">Perlu review Admin</option>
                    </select>
                </Field>
                <label className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.is_mandatory} onChange={(e) => form.setData('is_mandatory', e.target.checked)} /> Mata kuliah wajib
                </label>
                <label className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                    <input type="checkbox" checked={form.data.has_practicum} onChange={(e) => form.setData('has_practicum', e.target.checked)} /> Memiliki praktikum
                </label>
                <div className="md:col-span-2">
                    <Field label="Prasyarat / Catatan" error={form.errors.prerequisite_note}>
                        <textarea className={`${inputClass} min-h-20`} value={form.data.prerequisite_note} onChange={(e) => form.setData('prerequisite_note', e.target.value)} />
                    </Field>
                </div>
            </div>
            <div className="mt-4 flex justify-end"><SaveButton processing={form.processing} label="Simpan Mata Kuliah" compact /></div>
        </form>
    );
}

function CourseCplEditor({ course, cpls }: { course: Course; cpls: Cpl[] }) {
    const [selected, setSelected] = useState<string[]>(course.cpl_ids);
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        let acknowledged = false;
        if (course.rps_count > 0) {
            acknowledged = window.confirm(
                `Mata kuliah ini sudah dipakai pada ${course.rps_count} RPS. Mengubah CPL dapat memengaruhi scope CPL dan validator RPS aktif. Tetap lanjutkan?`,
            );
            if (!acknowledged) return;
        }
        setProcessing(true);
        router.put(
            `/admin/kurikulum/mata-kuliah/${course.id}/cpl`,
            { cpl_ids: selected, acknowledge_active_rps: acknowledged },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-4">
            <h3 className="font-bold text-slate-900">Pemetaan Mata Kuliah ↔ CPL</h3>
            <p className="mt-1 text-xs leading-5 text-slate-500">Pilih CPL resmi yang menjadi scope mata kuliah. Perubahan pada MK yang sudah dipakai RPS membutuhkan konfirmasi.</p>
            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                {cpls.map((cpl) => {
                    const checked = selected.includes(cpl.id);
                    return (
                        <label key={cpl.id} className={`flex cursor-pointer items-start gap-2 rounded-xl border p-3 text-sm ${checked ? 'border-teal-200 bg-teal-50' : 'border-slate-200 bg-white'}`}>
                            <input
                                className="mt-1"
                                type="checkbox"
                                checked={checked}
                                onChange={() => setSelected((current) => checked ? current.filter((id) => id !== cpl.id) : [...current, cpl.id])}
                            />
                            <span><b className="text-teal-800">{cpl.code}</b><span className="mt-1 block text-xs leading-5 text-slate-500">{cpl.description}</span></span>
                        </label>
                    );
                })}
            </div>
            <div className="mt-4 flex justify-end">
                <button type="button" disabled={processing} onClick={submit} className="inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">
                    <Save className="size-3.5" /> {processing ? 'Menyimpan...' : 'Simpan Pemetaan CPL'}
                </button>
            </div>
        </section>
    );
}

function CpmkManager({ course }: { course: Course }) {
    return (
        <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-4">
            <div>
                <h3 className="font-bold text-slate-900">CPMK Master</h3>
                <p className="mt-1 text-xs leading-5 text-slate-500">Perubahan di sini menjadi sumber untuk RPS baru/import berikutnya. RPS yang sudah memiliki salinan CPMK tidak ditimpa otomatis.</p>
            </div>
            <div className="mt-4 space-y-3">
                {course.cpmks.map((cpmk) => <CpmkEditor key={cpmk.id} courseId={course.id} cpmk={cpmk} />)}
                {course.cpmks.length === 0 && <div className="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Belum ada CPMK master. Dosen akan membutuhkan bantuan AI atau input manual saat membuat RPS.</div>}
            </div>
            <AddCpmkForm course={course} />
        </section>
    );
}

function CpmkEditor({ courseId, cpmk }: { courseId: string; cpmk: Cpmk }) {
    const form = useForm({
        code: cpmk.code,
        description: cpmk.description,
        sequence_no: cpmk.sequence_no,
        verification_status: cpmk.verification_status || 'source_verified',
    });

    const destroy = () => {
        if (!window.confirm(`Hapus ${cpmk.code} dari master kurikulum? RPS yang sudah dibuat tetap menyimpan salinan CPMK-nya.`)) return;
        router.delete(`/admin/kurikulum/mata-kuliah/${courseId}/cpmk/${cpmk.id}`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/admin/kurikulum/mata-kuliah/${courseId}/cpmk/${cpmk.id}`, { preserveScroll: true });
            }}
            className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 lg:grid-cols-[110px_1fr_90px_170px_auto] lg:items-start"
        >
            <Field label="Kode" error={form.errors.code}><input className={inputClass} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /></Field>
            <Field label="Rumusan CPMK" error={form.errors.description}><textarea className={`${inputClass} min-h-20`} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
            <Field label="Urutan" error={form.errors.sequence_no}><input type="number" min="1" className={inputClass} value={form.data.sequence_no} onChange={(e) => form.setData('sequence_no', Number(e.target.value))} /></Field>
            <Field label="Verifikasi" error={form.errors.verification_status}>
                <select className={inputClass} value={form.data.verification_status} onChange={(e) => form.setData('verification_status', e.target.value)}>
                    <option value="source_verified">Terverifikasi</option>
                    <option value="needs_review">Perlu review</option>
                </select>
            </Field>
            <div className="flex gap-2 lg:pt-6">
                <button type="submit" disabled={form.processing} title="Simpan CPMK" className="inline-flex size-10 items-center justify-center rounded-xl bg-teal-700 text-white disabled:opacity-50"><Save className="size-4" /></button>
                <button type="button" onClick={destroy} title="Hapus CPMK master" className="inline-flex size-10 items-center justify-center rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50"><Trash2 className="size-4" /></button>
            </div>
        </form>
    );
}

function AddCpmkForm({ course }: { course: Course }) {
    const nextSequence = Math.max(0, ...course.cpmks.map((item) => Number(item.sequence_no) || 0)) + 1;
    const form = useForm({ code: `CPMK-${String(nextSequence).padStart(2, '0')}`, description: '', sequence_no: nextSequence });

    return (
        <form
            className="mt-4 rounded-xl border border-dashed border-teal-300 bg-teal-50/40 p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/kurikulum/mata-kuliah/${course.id}/cpmk`, {
                    preserveScroll: true,
                    onSuccess: () => form.reset('description'),
                });
            }}
        >
            <div className="flex items-center gap-2 font-bold text-teal-800"><Plus className="size-4" /> Tambah CPMK Master</div>
            <div className="mt-3 grid gap-3 lg:grid-cols-[140px_1fr_100px_auto] lg:items-end">
                <Field label="Kode" error={form.errors.code}><input className={inputClass} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /></Field>
                <Field label="Rumusan CPMK" error={form.errors.description}><textarea className={`${inputClass} min-h-20`} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Rumusan CPMK sesuai dokumen kurikulum" /></Field>
                <Field label="Urutan" error={form.errors.sequence_no}><input type="number" min="1" className={inputClass} value={form.data.sequence_no} onChange={(e) => form.setData('sequence_no', Number(e.target.value))} /></Field>
                <SaveButton processing={form.processing} label="Tambah CPMK" compact />
            </div>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className={labelClass}>{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
        </label>
    );
}

function SaveButton({ processing, label, compact = false }: { processing: boolean; label: string; compact?: boolean }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className={`inline-flex items-center justify-center gap-2 rounded-xl bg-teal-700 font-bold text-white shadow-sm transition hover:bg-teal-800 disabled:opacity-50 ${compact ? 'px-4 py-2.5 text-xs' : 'px-5 py-3 text-sm'}`}
        >
            <Save className="size-4" /> {processing ? 'Menyimpan...' : label}
        </button>
    );
}

CurriculumPage.layout = {
    breadcrumbs: [{ title: 'Kelola Kurikulum', href: '/admin/kurikulum' }],
};
