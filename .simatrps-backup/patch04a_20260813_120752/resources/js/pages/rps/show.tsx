import { Head, router, useForm } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    Copy,
    FileText,
    Layers3,
    Network,
    Plus,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';

const TEACHING_WEEKS = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

export default function RpsShow(props: any) {
    const {
        rps,
        cpls = [],
        cpmks = [],
        subCpmks = [],
        materials = [],
        weeks = [],
        assessments = [],
        tasks = [],
        progress = { percent: 0, checks: [], assessment_weight_total: 0 },
    } = props;

    const [openWeek, setOpenWeek] = useState<number | null>(null);
    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);

    const mappingForm = useForm({
        mappings: Object.fromEntries(
            cpmks.map((c: any) => [c.id, Array.isArray(c.cpl_ids) ? c.cpl_ids : []]),
        ),
    });

    const subForm = useForm({
        rps_cpmk_id: cpmks[0]?.id ?? '',
        description: '',
        bloom_level: '',
    });

    const materialForm = useForm({
        rps_sub_cpmk_id: '',
        title: '',
        description: '',
    });

    const assessmentForm = useForm({
        name: '',
        type: 'assignment',
        week_number: '',
        weight: '',
        description: '',
        sub_cpmk_ids: [] as string[],
    });

    const taskForm = useForm({
        assessment_id: '',
        title: '',
        type: 'assignment',
        purpose: '',
        instructions: '',
        expected_output: '',
        due_week: '',
        sub_cpmk_ids: [] as string[],
    });

    const methodForm = useForm({
        weeks: TEACHING_WEEKS,
        learning_method: 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.',
    });

    const mappedCount = useMemo(
        () => cpmks.filter((cpmk: any) => (mappingForm.data.mappings[cpmk.id] ?? []).length > 0).length,
        [cpmks, mappingForm.data.mappings],
    );

    const toggleMapping = (cpmkId: string, cplId: string) => {
        const current = mappingForm.data.mappings[cpmkId] ?? [];
        mappingForm.setData('mappings', {
            ...mappingForm.data.mappings,
            [cpmkId]: current.includes(cplId)
                ? current.filter((id: string) => id !== cplId)
                : [...current, cplId],
        });
    };

    const toggleAssessmentSub = (id: string) => {
        const current = assessmentForm.data.sub_cpmk_ids;
        assessmentForm.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleTaskSub = (id: string) => {
        const current = taskForm.data.sub_cpmk_ids;
        taskForm.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleBatchWeek = (week: number) => {
        setSelectedBatchWeeks((current) =>
            current.includes(week)
                ? current.filter((value) => value !== week)
                : [...current, week].sort((a, b) => a - b),
        );
    };

    return (
        <>
            <Head title={`RPS ${rps.course_name}`} />

            <div className="p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Workspace OBE</div>
                        <h1 className="mt-1 text-2xl font-bold text-slate-900">{rps.course_name}</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {rps.official_code || rps.system_code} · {rps.credits} SKS · {rps.academic_year} {rps.academic_semester}
                        </p>
                    </div>

                    <div className="sim-surface w-full max-w-lg rounded-2xl p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Progress OBE</div>
                                <div className="mt-1 text-2xl font-extrabold text-slate-900">{progress.percent}%</div>
                            </div>
                            <button
                                type="button"
                                onClick={() => router.post(`/rps/${rps.id}/validate-obe`, {}, { preserveScroll: true })}
                                className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                            >
                                Validasi OBE
                            </button>
                        </div>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-teal-700 to-cyan-400"
                                style={{ width: `${progress.percent}%` }}
                            />
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                            {progress.checks.map((item: any) => (
                                <div key={item.key} className="flex items-center gap-2">
                                    {item.done
                                        ? <CheckCircle2 className="size-3.5 text-emerald-600" />
                                        : <CircleAlert className="size-3.5 text-amber-500" />}
                                    <span className="text-slate-600">{item.label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mt-6 space-y-5">
                    {/* 1 CPMK CPL */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Network className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">1. Pemetaan CPMK → CPL</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">Hanya CPL resmi mata kuliah yang tersedia.</p>
                            </div>
                            <span className="text-xs font-bold text-slate-500">{mappedCount}/{cpmks.length} CPMK</span>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                mappingForm.put(`/rps/${rps.id}/cpmk-cpl`, { preserveScroll: true });
                            }}
                            className="mt-4 space-y-3"
                        >
                            {cpmks.map((cpmk: any) => (
                                <div key={cpmk.id} className="rounded-xl border border-slate-100 bg-white/55 p-4">
                                    <div className="font-bold text-teal-800">{cpmk.code}</div>
                                    <p className="mt-1 text-sm text-slate-600">{cpmk.description}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {cpls.map((cpl: any) => {
                                            const checked = (mappingForm.data.mappings[cpmk.id] ?? []).includes(cpl.id);
                                            return (
                                                <label
                                                    key={cpl.id}
                                                    className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                                        checked
                                                            ? 'border-teal-300 bg-teal-100 text-teal-800'
                                                            : 'border-slate-200 bg-white/70 text-slate-500'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="sr-only"
                                                        checked={checked}
                                                        onChange={() => toggleMapping(cpmk.id, cpl.id)}
                                                    />
                                                    {cpl.code}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                            <button className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                Simpan Pemetaan
                            </button>
                        </form>
                    </section>

                    <div className="grid gap-5 xl:grid-cols-2">
                        {/* 2 Sub CPMK */}
                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-bold text-slate-900">2. Sub-CPMK</h2>
                            <p className="mt-1 text-sm text-slate-500">Turunkan CPMK menjadi capaian yang lebih spesifik.</p>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    subForm.post(`/rps/${rps.id}/sub-cpmk`, {
                                        preserveScroll: true,
                                        onSuccess: () => subForm.reset('description', 'bloom_level'),
                                    });
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-[1fr_150px]">
                                    <label>
                                        <span className="mb-1.5 block text-xs font-bold text-slate-500">CPMK Induk</span>
                                        <select
                                            value={subForm.data.rps_cpmk_id}
                                            onChange={(e) => subForm.setData('rps_cpmk_id', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        >
                                            {cpmks.map((cpmk: any) => <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>)}
                                        </select>
                                    </label>
                                    <label>
                                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Level Kognitif</span>
                                        <select
                                            value={subForm.data.bloom_level}
                                            onChange={(e) => subForm.setData('bloom_level', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        >
                                            <option value="">Bloom</option>
                                            {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                        </select>
                                    </label>
                                </div>
                                <textarea
                                    value={subForm.data.description}
                                    onChange={(e) => subForm.setData('description', e.target.value)}
                                    placeholder="Rumusan Sub-CPMK"
                                    className="mt-3 min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                    <Plus className="size-4" /> Tambah Sub-CPMK
                                </button>
                            </form>

                            <div className="mt-4 space-y-2">
                                {subCpmks.map((sub: any) => {
                                    const parent = cpmks.find((c: any) => sub.cpmk_ids?.includes(c.id));
                                    return (
                                        <div key={sub.id} className="rounded-xl border border-slate-100 bg-white/60 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-bold text-teal-800">
                                                        {sub.code}
                                                        {sub.bloom_level && <span className="ml-2 text-xs text-sky-700">{sub.bloom_level}</span>}
                                                    </div>
                                                    <div className="mt-1 text-[11px] font-semibold text-slate-400">
                                                        Turunan dari: {parent?.code || '-'}
                                                    </div>
                                                    <p className="mt-2 text-sm text-slate-600">{sub.description}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => router.delete(`/rps/${rps.id}/sub-cpmk/${sub.id}`, { preserveScroll: true })}
                                                >
                                                    <Trash2 className="size-4 text-slate-300 hover:text-rose-600" />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>

                        {/* 3 Materials */}
                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Layers3 className="size-5 text-teal-700" />
                                        <h2 className="font-bold text-slate-900">3. Bahan Kajian</h2>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">Ambil dari silabus atau tambah manual.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/materials/import-syllabus`, {}, { preserveScroll: true })}
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700"
                                >
                                    Ambil dari Silabus
                                </button>
                            </div>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    materialForm.post(`/rps/${rps.id}/materials`, {
                                        preserveScroll: true,
                                        onSuccess: () => materialForm.reset(),
                                    });
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <select
                                    value={materialForm.data.rps_sub_cpmk_id}
                                    onChange={(e) => materialForm.setData('rps_sub_cpmk_id', e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                >
                                    <option value="">Tanpa Sub-CPMK khusus</option>
                                    {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code}</option>)}
                                </select>
                                <input
                                    value={materialForm.data.title}
                                    onChange={(e) => materialForm.setData('title', e.target.value)}
                                    placeholder="Judul bahan kajian"
                                    className="mt-3 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                    <Plus className="size-4" /> Tambah
                                </button>
                            </form>

                            <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
                                {materials.map((material: any) => (
                                    <div key={material.id} className="rounded-xl border border-slate-100 bg-white/60 p-3">
                                        <div className="font-semibold text-slate-900">{material.title}</div>
                                        <div className="mt-1 text-[10px] uppercase text-slate-400">{material.source_type}</div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    {/* 4 Smart weekly */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">4. Rencana 16 Pertemuan</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Susun draft otomatis dari Sub-CPMK dan silabus, lalu dosen tinggal mereview.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/smart-draft`, { mode: 'fill_empty' }, { preserveScroll: true })}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-bold text-white"
                                >
                                    Susun Otomatis · Isi Kosong
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('Susun ulang akan mengganti draft minggu kuliah yang sudah ada. Lanjutkan?')) {
                                            router.post(`/rps/${rps.id}/smart-draft`, { mode: 'overwrite' }, { preserveScroll: true });
                                        }
                                    }}
                                    className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700"
                                >
                                    Susun Ulang Draft
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 rounded-xl border border-slate-100 bg-white/45 p-4">
                            <div className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Terapkan metode ke banyak minggu
                            </div>
                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {TEACHING_WEEKS.map((week) => (
                                    <button
                                        key={week}
                                        type="button"
                                        onClick={() => toggleBatchWeek(week)}
                                        className={`size-8 rounded-lg text-xs font-bold ${
                                            selectedBatchWeeks.includes(week)
                                                ? 'bg-teal-700 text-white'
                                                : 'border border-slate-200 bg-white text-slate-500'
                                        }`}
                                    >
                                        {week}
                                    </button>
                                ))}
                            </div>
                            <div className="mt-3 flex flex-col gap-2 md:flex-row">
                                <input
                                    value={methodForm.data.learning_method}
                                    onChange={(e) => methodForm.setData('learning_method', e.target.value)}
                                    className="flex-1 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        methodForm.setData('weeks', selectedBatchWeeks);
                                        router.post(
                                            `/rps/${rps.id}/weeks/apply-method`,
                                            {
                                                weeks: selectedBatchWeeks,
                                                learning_method: methodForm.data.learning_method,
                                            },
                                            { preserveScroll: true },
                                        );
                                    }}
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-bold text-teal-700"
                                >
                                    Terapkan
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-2 sm:grid-cols-4 lg:grid-cols-8 xl:grid-cols-16">
                            {weeks.map((week: any) => {
                                const filled = week.is_exam || week.rps_sub_cpmk_id || week.material_text || week.learning_method;
                                return (
                                    <button
                                        key={week.week_number}
                                        type="button"
                                        onClick={() => setOpenWeek(openWeek === week.week_number ? null : week.week_number)}
                                        className={`rounded-xl border p-3 text-center ${
                                            week.is_exam
                                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                : filled
                                                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                  : 'border-slate-200 bg-white/60 text-slate-600'
                                        }`}
                                    >
                                        <div className="text-xs font-bold">{week.week_number}</div>
                                        <div className="mt-1 text-[10px]">{week.exam_type || (filled ? 'Terisi' : 'Kosong')}</div>
                                    </button>
                                );
                            })}
                        </div>

                        {openWeek && (
                            <WeekEditor
                                key={openWeek}
                                rpsId={rps.id}
                                week={weeks.find((w: any) => w.week_number === openWeek)}
                                subCpmks={subCpmks}
                            />
                        )}
                    </section>

                    {/* 5 Assessment */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <BarChart3 className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">5. Asesmen & Bobot</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Total bobot harus 100%. Hubungkan asesmen dengan Sub-CPMK yang diukur.
                                </p>
                            </div>
                            <div className={`rounded-xl px-4 py-2 text-sm font-extrabold ${
                                Math.abs(Number(progress.assessment_weight_total) - 100) < 0.01
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'
                            }`}>
                                {progress.assessment_weight_total}% / 100%
                            </div>
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                assessmentForm.post(`/rps/${rps.id}/assessments`, {
                                    preserveScroll: true,
                                    onSuccess: () => assessmentForm.reset(),
                                });
                            }}
                            className="mt-5 rounded-xl border border-teal-100 bg-teal-50/30 p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-4">
                                <input
                                    value={assessmentForm.data.name}
                                    onChange={(e) => assessmentForm.setData('name', e.target.value)}
                                    placeholder="Nama asesmen"
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                                />
                                <select
                                    value={assessmentForm.data.type}
                                    onChange={(e) => assessmentForm.setData('type', e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                >
                                    <option value="quiz">Kuis</option>
                                    <option value="assignment">Tugas</option>
                                    <option value="project">Proyek</option>
                                    <option value="presentation">Presentasi</option>
                                    <option value="practicum">Praktikum</option>
                                    <option value="uts">UTS</option>
                                    <option value="uas">UAS</option>
                                    <option value="other">Lainnya</option>
                                </select>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={assessmentForm.data.weight}
                                    onChange={(e) => assessmentForm.setData('weight', e.target.value)}
                                    placeholder="Bobot %"
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <input
                                    type="number"
                                    min="1"
                                    max="16"
                                    value={assessmentForm.data.week_number}
                                    onChange={(e) => assessmentForm.setData('week_number', e.target.value)}
                                    placeholder="Minggu"
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                            </div>

                            <div className="mt-3">
                                <div className="text-xs font-bold text-slate-500">Sub-CPMK yang diukur</div>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {subCpmks.map((sub: any) => (
                                        <label
                                            key={sub.id}
                                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                                assessmentForm.data.sub_cpmk_ids.includes(sub.id)
                                                    ? 'border-teal-300 bg-teal-100 text-teal-800'
                                                    : 'border-slate-200 bg-white text-slate-500'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                className="sr-only"
                                                checked={assessmentForm.data.sub_cpmk_ids.includes(sub.id)}
                                                onChange={() => toggleAssessmentSub(sub.id)}
                                            />
                                            {sub.code}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                <Plus className="size-4" /> Tambah Asesmen
                            </button>
                        </form>

                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="text-left text-xs uppercase text-slate-400">
                                    <tr>
                                        <th className="px-3 py-2">Asesmen</th>
                                        <th className="px-3 py-2">Minggu</th>
                                        <th className="px-3 py-2">Bobot</th>
                                        <th className="px-3 py-2">Sub-CPMK</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {assessments.map((assessment: any) => (
                                        <tr key={assessment.id}>
                                            <td className="px-3 py-3">
                                                <div className="font-semibold text-slate-900">{assessment.name}</div>
                                                <div className="text-xs text-slate-400">{assessment.type}</div>
                                            </td>
                                            <td className="px-3 py-3">{assessment.week_number ?? '-'}</td>
                                            <td className="px-3 py-3 font-bold">{assessment.weight ?? '-'}%</td>
                                            <td className="px-3 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {(assessment.sub_cpmk_ids ?? []).map((id: string) => {
                                                        const sub = subCpmks.find((item: any) => item.id === id);
                                                        return sub ? (
                                                            <span key={id} className="rounded-full bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700">
                                                                {sub.code}
                                                            </span>
                                                        ) : null;
                                                    })}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => router.delete(`/rps/${rps.id}/assessments/${assessment.id}`, { preserveScroll: true })}
                                                >
                                                    <Trash2 className="size-4 text-slate-300 hover:text-rose-600" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {/* 6 RTM */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">6. Rencana Tugas Mahasiswa (RTM)</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            RTM digunakan untuk tugas, proyek, praktikum, atau keluaran mahasiswa yang perlu instruksi terstruktur.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                taskForm.post(`/rps/${rps.id}/tasks`, {
                                    preserveScroll: true,
                                    onSuccess: () => taskForm.reset(),
                                });
                            }}
                            className="mt-5 rounded-xl border border-teal-100 bg-teal-50/30 p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-3">
                                <input
                                    value={taskForm.data.title}
                                    onChange={(e) => taskForm.setData('title', e.target.value)}
                                    placeholder="Judul tugas/proyek"
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                                />
                                <select
                                    value={taskForm.data.type}
                                    onChange={(e) => taskForm.setData('type', e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                >
                                    <option value="assignment">Tugas</option>
                                    <option value="project">Proyek</option>
                                    <option value="practicum">Praktikum</option>
                                    <option value="presentation">Presentasi</option>
                                    <option value="other">Lainnya</option>
                                </select>
                                <select
                                    value={taskForm.data.assessment_id}
                                    onChange={(e) => taskForm.setData('assessment_id', e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                >
                                    <option value="">Hubungkan ke asesmen (opsional)</option>
                                    {assessments.map((assessment: any) => (
                                        <option key={assessment.id} value={assessment.id}>{assessment.name}</option>
                                    ))}
                                </select>
                                <input
                                    type="number"
                                    min="1"
                                    max="16"
                                    value={taskForm.data.due_week}
                                    onChange={(e) => taskForm.setData('due_week', e.target.value)}
                                    placeholder="Minggu pengumpulan"
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <input
                                    value={taskForm.data.expected_output}
                                    onChange={(e) => taskForm.setData('expected_output', e.target.value)}
                                    placeholder="Luaran: laporan, kode program..."
                                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <textarea
                                    value={taskForm.data.instructions}
                                    onChange={(e) => taskForm.setData('instructions', e.target.value)}
                                    placeholder="Instruksi tugas"
                                    className="min-h-24 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-3"
                                />
                            </div>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {subCpmks.map((sub: any) => (
                                    <label
                                        key={sub.id}
                                        className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                            taskForm.data.sub_cpmk_ids.includes(sub.id)
                                                ? 'border-teal-300 bg-teal-100 text-teal-800'
                                                : 'border-slate-200 bg-white text-slate-500'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={taskForm.data.sub_cpmk_ids.includes(sub.id)}
                                            onChange={() => toggleTaskSub(sub.id)}
                                        />
                                        {sub.code}
                                    </label>
                                ))}
                            </div>

                            <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                <Plus className="size-4" /> Tambah RTM
                            </button>
                        </form>

                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                            {tasks.map((task: any) => (
                                <div key={task.id} className="rounded-xl border border-slate-100 bg-white/60 p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="text-xs font-bold text-teal-700">{task.code}</div>
                                            <div className="mt-1 font-semibold text-slate-900">{task.title}</div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                {task.type} · Minggu {task.due_week ?? '-'}
                                            </div>
                                            {task.expected_output && (
                                                <div className="mt-2 text-sm text-slate-600">Luaran: {task.expected_output}</div>
                                            )}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => router.delete(`/rps/${rps.id}/tasks/${task.id}`, { preserveScroll: true })}
                                        >
                                            <Trash2 className="size-4 text-slate-300 hover:text-rose-600" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* 7 Validator */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <FileText className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">7. Validator OBE</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Pemeriksaan ini belum mengesahkan RPS; fungsinya menunjukkan bagian yang masih perlu diperbaiki.
                        </p>

                        <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {progress.checks.map((check: any) => (
                                <div
                                    key={check.key}
                                    className={`rounded-xl border p-4 ${
                                        check.done
                                            ? 'border-emerald-100 bg-emerald-50/70'
                                            : 'border-amber-100 bg-amber-50/70'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        {check.done
                                            ? <CheckCircle2 className="size-4 text-emerald-600" />
                                            : <CircleAlert className="size-4 text-amber-600" />}
                                        <div className="font-bold text-slate-900">{check.label}</div>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function WeekEditor({ rpsId, week, subCpmks }: any) {
    const form = useForm({
        rps_sub_cpmk_id: week?.rps_sub_cpmk_id ?? '',
        material_text: week?.material_text ?? '',
        learning_method: week?.learning_method ?? '',
        learning_activity: week?.learning_activity ?? '',
        assessment_indicator: week?.assessment_indicator ?? '',
        assessment_criteria: week?.assessment_criteria ?? '',
        assessment_method: week?.assessment_method ?? '',
        assessment_weight: week?.assessment_weight ?? '',
        reference_text: week?.reference_text ?? '',
    });

    if (!week) return null;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/rps/${rpsId}/weeks/${week.week_number}`, { preserveScroll: true });
            }}
            className="mt-5 rounded-2xl border border-teal-100 bg-teal-50/30 p-5"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-bold text-slate-900">
                        Minggu {week.week_number} {week.exam_type ? `· ${week.exam_type}` : ''}
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">Review hasil Smart Draft atau edit manual.</p>
                </div>
                {!week.is_exam && week.week_number > 1 && (
                    <button
                        type="button"
                        onClick={() => router.post(`/rps/${rpsId}/weeks/${week.week_number}/copy-previous`, {}, { preserveScroll: true })}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                    >
                        <Copy className="size-3.5" /> Salin Minggu Sebelumnya
                    </button>
                )}
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <select
                    value={form.data.rps_sub_cpmk_id}
                    onChange={(e) => form.setData('rps_sub_cpmk_id', e.target.value)}
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                >
                    <option value="">Pilih Sub-CPMK</option>
                    {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code}</option>)}
                </select>
                <input
                    value={form.data.learning_method}
                    onChange={(e) => form.setData('learning_method', e.target.value)}
                    placeholder="Metode pembelajaran"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />
                <textarea
                    value={form.data.material_text}
                    onChange={(e) => form.setData('material_text', e.target.value)}
                    placeholder="Materi/Bahan Kajian"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />
                <textarea
                    value={form.data.learning_activity}
                    onChange={(e) => form.setData('learning_activity', e.target.value)}
                    placeholder="Aktivitas pembelajaran"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />
                <textarea
                    value={form.data.assessment_indicator}
                    onChange={(e) => form.setData('assessment_indicator', e.target.value)}
                    placeholder="Indikator asesmen"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />
                <textarea
                    value={form.data.assessment_criteria}
                    onChange={(e) => form.setData('assessment_criteria', e.target.value)}
                    placeholder="Kriteria asesmen"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />
                <input
                    value={form.data.assessment_method}
                    onChange={(e) => form.setData('assessment_method', e.target.value)}
                    placeholder="Metode asesmen"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />
                <input
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={form.data.assessment_weight}
                    onChange={(e) => form.setData('assessment_weight', e.target.value)}
                    placeholder="Bobot mingguan (opsional)"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />
                <textarea
                    value={form.data.reference_text}
                    onChange={(e) => form.setData('reference_text', e.target.value)}
                    placeholder="Referensi"
                    className="min-h-16 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />
            </div>

            <button
                disabled={form.processing}
                className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
            >
                Simpan Minggu {week.week_number}
            </button>
        </form>
    );
}

RpsShow.layout = {
    breadcrumbs: [
        { title: 'RPS Saya', href: '/rps' },
        { title: 'Workspace OBE', href: '#' },
    ],
};
