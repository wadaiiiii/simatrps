import { Head, router, useForm } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    Copy,
    FilePenLine,
    FileText,
    Layers3,
    Network,
    Pencil,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';


const TEACHING_WEEKS = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

type NoticeKind = 'success' | 'error' | 'info';

function notify(kind: NoticeKind, message: string) {
    window.dispatchEvent(new CustomEvent('simatrps:notify', {
        detail: { kind, message },
    }));
}

function firstError(errors: Record<string, any>) {
    const first = Object.values(errors ?? {}).flat()[0];
    return first ? String(first) : 'Aksi gagal diproses. Periksa kembali isian.';
}

function actionOptions(message: string, afterSuccess?: () => void) {
    return {
        preserveScroll: true,
        onSuccess: () => {
            notify('success', message);
            afterSuccess?.();
        },
        onError: (errors: Record<string, any>) => {
            notify('error', firstError(errors));
        },
    };
}

function ActionNotifications() {
    const [notice, setNotice] = useState<{ kind: NoticeKind; message: string } | null>(null);

    useEffect(() => {
        let timer: number | undefined;

        const handler = (event: Event) => {
            const custom = event as CustomEvent<{ kind: NoticeKind; message: string }>;
            setNotice(custom.detail);

            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(() => setNotice(null), 3200);
        };

        window.addEventListener('simatrps:notify', handler);

        return () => {
            window.removeEventListener('simatrps:notify', handler);
            if (timer) window.clearTimeout(timer);
        };
    }, []);

    if (!notice) return null;

    const tone = notice.kind === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : notice.kind === 'error'
          ? 'border-rose-200 bg-rose-50 text-rose-800'
          : 'border-sky-200 bg-sky-50 text-sky-800';

    return (
        <div className={`fixed right-5 top-5 z-[100] max-w-md rounded-2xl border px-4 py-3 text-sm font-semibold shadow-xl ${tone}`}>
            {notice.message}
        </div>
    );
}

export default function RpsShow(props: any) {
    const {
        rps,
        cpls = [],
        allCpls = [],
        officialCplIds = [],
        additionalCplIds = [],
        cplScopeStats = { curriculum: 0, additional: 0, available: 8, scope_total: 0 },
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

    const newCpmkForm = useForm({
        description: '',
        bloom_level: '',
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
            <ActionNotifications />

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
                                onClick={() => router.post(`/rps/${rps.id}/validate-obe`, {}, actionOptions('Validasi OBE selesai.'))}
                                className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                            >
                                Validasi OBE
                            </button>
                        </div>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div className="h-full rounded-full bg-gradient-to-r from-teal-700 to-cyan-400" style={{ width: `${progress.percent}%` }} />
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
                    {/* CPMK Adaptif */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Network className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">1. CPMK RPS & Pemetaan CPL</h2>
                                </div>
                                <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                    CPL Prodi tetap berasal dari kurikulum. CPL resmi mata kuliah dipertahankan,
                                    sedangkan dosen dapat menambah CPL lain dari 8 CPL Prodi ke scope RPS bila memang relevan.
                                    CPMK tetap dapat diadaptasi atau ditambah pada level RPS.
                                </p>
                            </div>
                            <span className="text-xs font-bold text-slate-500">{mappedCount}/{cpmks.length} CPMK terpetakan</span>
                        </div>

                        <div className="mt-4 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-sm text-sky-800">
                            <strong>Prinsip SiMatRPS:</strong> CPL resmi mata kuliah tidak dihapus dari master.
                            Dosen dapat memperluas scope RPS dengan CPL lain dari 8 CPL Prodi. CPL tambahan diberi jejak
                            <strong> Tambahan Dosen</strong> dan hanya berlaku pada RPS ini.
                        </div>

                        <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <div className="font-bold text-slate-900">Scope CPL RPS</div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {cplScopeStats.curriculum} CPL dari kurikulum · {cplScopeStats.additional} tambahan dosen · {cplScopeStats.scope_total}/{cplScopeStats.available} CPL digunakan pada RPS.
                                    </p>
                                </div>
                                <div className="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-slate-600 shadow-sm">
                                    Total CPL Prodi: {cplScopeStats.available}
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 lg:grid-cols-2">
                                {allCpls.map((cpl: any) => {
                                    const official = officialCplIds.includes(cpl.id);
                                    const additional = additionalCplIds.includes(cpl.id);

                                    return (
                                        <div
                                            key={cpl.id}
                                            className={`rounded-xl border p-4 ${
                                                official
                                                    ? 'border-emerald-200 bg-emerald-50/65'
                                                    : additional
                                                      ? 'border-sky-200 bg-sky-50/65'
                                                      : 'border-slate-200 bg-white/75'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-extrabold text-slate-900">{cpl.code}</span>
                                                        {official && (
                                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                                Kurikulum MK
                                                            </span>
                                                        )}
                                                        {additional && (
                                                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                                                Tambahan Dosen
                                                            </span>
                                                        )}
                                                        {!official && !additional && (
                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                                                Tersedia
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-2 text-sm leading-6 text-slate-600">{cpl.description}</p>
                                                </div>

                                                {!official && !additional && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/rps/${rps.id}/cpl-scope`,
                                                                { cpl_id: cpl.id },
                                                                actionOptions(`${cpl.code} ditambahkan ke scope RPS.`),
                                                            )
                                                        }
                                                        className="shrink-0 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100"
                                                    >
                                                        + Tambah
                                                    </button>
                                                )}

                                                {additional && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm(`Hapus ${cpl.code} dari tambahan RPS? Mapping CPMK ke CPL ini juga akan dilepas.`)) {
                                                                router.delete(
                                                                    `/rps/${rps.id}/cpl-scope/${cpl.id}`,
                                                                    actionOptions(`${cpl.code} dihapus dari tambahan RPS.`),
                                                                );
                                                            }
                                                        }}
                                                        className="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100"
                                                    >
                                                        Hapus
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-5 space-y-3">
                            {cpmks.map((cpmk: any) => (
                                <CpmkCard
                                    key={cpmk.id}
                                    rpsId={rps.id}
                                    cpmk={cpmk}
                                    cpls={cpls}
                                    selectedCplIds={mappingForm.data.mappings[cpmk.id] ?? []}
                                    onToggle={(cplId: string) => toggleMapping(cpmk.id, cplId)}
                                />
                            ))}
                        </div>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                newCpmkForm.post(
                                    `/rps/${rps.id}/cpmk`,
                                    actionOptions('CPMK baru berhasil ditambahkan.', () => newCpmkForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-dashed border-teal-200 bg-teal-50/30 p-4"
                        >
                            <div className="flex items-center gap-2">
                                <Plus className="size-4 text-teal-700" />
                                <div className="font-bold text-slate-800">Tambah CPMK Dosen</div>
                            </div>
                            <div className="mt-3 grid gap-3 md:grid-cols-[1fr_150px]">
                                <textarea
                                    value={newCpmkForm.data.description}
                                    onChange={(e) => newCpmkForm.setData('description', e.target.value)}
                                    placeholder="Rumusan CPMK tambahan"
                                    className="min-h-20 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                />
                                <select
                                    value={newCpmkForm.data.bloom_level}
                                    onChange={(e) => newCpmkForm.setData('bloom_level', e.target.value)}
                                    className="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Bloom (opsional)</option>
                                    {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                </select>
                            </div>
                            <button className="mt-3 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                Tambah CPMK
                            </button>
                        </form>

                        <button
                            type="button"
                            onClick={() => mappingForm.put(`/rps/${rps.id}/cpmk-cpl`, actionOptions('Pemetaan CPMK → CPL berhasil disimpan.'))}
                            disabled={mappingForm.processing}
                            className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                        >
                            Simpan Semua Pemetaan CPL
                        </button>
                    </section>

                    <div className="grid gap-5 xl:grid-cols-2">
                        {/* Sub CPMK */}
                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-bold text-slate-900">2. Sub-CPMK</h2>
                            <p className="mt-1 text-sm text-slate-500">Turunkan CPMK menjadi capaian yang lebih spesifik.</p>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    subForm.post(
                                        `/rps/${rps.id}/sub-cpmk`,
                                        actionOptions('Sub-CPMK berhasil ditambahkan.', () => subForm.reset('description', 'bloom_level')),
                                    );
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-[1fr_170px]">
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
                                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Level Kognitif (Bloom)</span>
                                        <select
                                            value={subForm.data.bloom_level}
                                            onChange={(e) => subForm.setData('bloom_level', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        >
                                            <option value="">Pilih level</option>
                                            {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                        </select>
                                    </label>
                                </div>
                                <label className="mt-3 block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Rumusan Sub-CPMK</span>
                                    <textarea
                                        value={subForm.data.description}
                                        onChange={(e) => subForm.setData('description', e.target.value)}
                                        placeholder="Contoh: Mahasiswa mampu menerapkan struktur percabangan untuk menyelesaikan masalah sederhana."
                                        className="min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                    <Plus className="size-4" /> Tambah Sub-CPMK
                                </button>
                            </form>

                            <div className="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                                Gunakan tombol <strong>Edit</strong> untuk memperbaiki rumusan, CPMK induk, atau level Bloom tanpa menghapus data.
                                Jika Sub-CPMK benar-benar dihapus, nomor yang kosong akan dipakai kembali saat menambah Sub-CPMK berikutnya.
                            </div>

                            <div className="mt-4 space-y-2">
                                {subCpmks.map((sub: any) => (
                                    <SubCpmkCard
                                        key={sub.id}
                                        rpsId={rps.id}
                                        sub={sub}
                                        cpmks={cpmks}
                                    />
                                ))}
                            </div>
                        </section>

                        {/* Materials */}
                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Layers3 className="size-5 text-teal-700" />
                                        <h2 className="font-bold text-slate-900">3. Bahan Kajian</h2>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Materi berasal dari bagian <strong>Silabus</strong>, bukan daftar pustaka.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/materials/import-syllabus`, {}, actionOptions('Bahan kajian berhasil disinkronkan dari silabus.'))}
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700"
                                >
                                    Sinkronkan Silabus
                                </button>
                            </div>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    materialForm.post(
                                        `/rps/${rps.id}/materials`,
                                        actionOptions('Bahan kajian berhasil ditambahkan.', () => materialForm.reset()),
                                    );
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Kaitkan ke Sub-CPMK (opsional)</span>
                                    <select
                                        value={materialForm.data.rps_sub_cpmk_id}
                                        onChange={(e) => materialForm.setData('rps_sub_cpmk_id', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Tanpa Sub-CPMK khusus</option>
                                        {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code}</option>)}
                                    </select>
                                </label>
                                <label className="mt-3 block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Judul Bahan Kajian</span>
                                    <input
                                        value={materialForm.data.title}
                                        onChange={(e) => materialForm.setData('title', e.target.value)}
                                        placeholder="Judul bahan kajian"
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
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

                    {/* Weekly */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">4. Rencana 16 Pertemuan</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Smart Draft mengisi beberapa komponen sekaligus. Ringkasan di bawah menunjukkan persis bagian yang sudah terisi.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/smart-draft`, { mode: 'fill_empty' }, actionOptions('Smart Draft selesai mengisi bagian yang masih kosong.'))}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-bold text-white"
                                >
                                    Susun Otomatis · Isi Kosong
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('Susun ulang akan mengganti draft minggu kuliah yang sudah ada. Lanjutkan?')) {
                                            router.post(`/rps/${rps.id}/smart-draft`, { mode: 'overwrite' }, actionOptions('Smart Draft berhasil disusun ulang.'));
                                        }
                                    }}
                                    className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700"
                                >
                                    Susun Ulang Draft
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 rounded-xl border border-slate-100 bg-white/45 p-4">
                            <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Terapkan metode ke banyak minggu</div>
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
                                    onClick={() => router.post(
                                        `/rps/${rps.id}/weeks/apply-method`,
                                        { weeks: selectedBatchWeeks, learning_method: methodForm.data.learning_method },
                                        actionOptions('Metode pembelajaran berhasil diterapkan ke minggu terpilih.'),
                                    )}
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-bold text-teal-700"
                                >
                                    Terapkan
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-2 sm:grid-cols-4 lg:grid-cols-8 xl:grid-cols-16">
                            {weeks.map((week: any) => {
                                const info = weekCompletion(week);
                                return (
                                    <button
                                        key={week.week_number}
                                        type="button"
                                        onClick={() => setOpenWeek(openWeek === week.week_number ? null : week.week_number)}
                                        className={`rounded-xl border p-3 text-center ${
                                            week.is_exam
                                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                : info.count >= 5
                                                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                  : info.count > 0
                                                    ? 'border-sky-200 bg-sky-50 text-sky-800'
                                                    : 'border-slate-200 bg-white/60 text-slate-600'
                                        }`}
                                    >
                                        <div className="text-xs font-bold">{week.week_number}</div>
                                        <div className="mt-1 text-[10px]">
                                            {week.exam_type || `${info.count}/7`}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-100">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th className="px-3 py-3">Minggu</th>
                                        <th className="px-3 py-3">Sub-CPMK</th>
                                        <th className="px-3 py-3">Materi/Bahan Kajian</th>
                                        <th className="px-3 py-3">Metode</th>
                                        <th className="px-3 py-3">Asesmen</th>
                                        <th className="px-3 py-3">Kelengkapan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {weeks.map((week: any) => {
                                        const sub = subCpmks.find((item: any) => item.id === week.rps_sub_cpmk_id);
                                        const info = weekCompletion(week);
                                        return (
                                            <tr
                                                key={week.week_number}
                                                onClick={() => setOpenWeek(week.week_number)}
                                                className="cursor-pointer hover:bg-teal-50/30"
                                            >
                                                <td className="px-3 py-3 font-bold">{week.week_number}{week.exam_type ? ` · ${week.exam_type}` : ''}</td>
                                                <td className="px-3 py-3">{sub?.code || '-'}</td>
                                                <td className="max-w-[280px] px-3 py-3 text-slate-600">
                                                    <div className="line-clamp-2">{week.material_text || '-'}</div>
                                                </td>
                                                <td className="max-w-[240px] px-3 py-3 text-slate-600">
                                                    <div className="line-clamp-2">{week.learning_method || '-'}</div>
                                                </td>
                                                <td className="max-w-[220px] px-3 py-3 text-slate-600">
                                                    <div className="line-clamp-2">{week.assessment_method || '-'}</div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${
                                                        week.is_exam || info.count >= 5
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-amber-50 text-amber-700'
                                                    }`}>
                                                        {week.is_exam ? 'Ujian' : `${info.count}/7`}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
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

                    {/* Assessment */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <BarChart3 className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">5. Asesmen & Bobot Nilai Akhir</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Bagian ini menentukan komponen nilai akhir dan membuktikan Sub-CPMK benar-benar diukur.
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

                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                            {[
                                ['1', 'Tentukan komponen', 'Misalnya kuis, tugas, proyek/praktikum, UTS, dan UAS.'],
                                ['2', 'Atur bobot', 'Total seluruh komponen harus tepat 100%.'],
                                ['3', 'Pilih Sub-CPMK', 'Tandai Sub-CPMK mana yang diukur oleh setiap asesmen.'],
                            ].map(([num, title, text]) => (
                                <div key={num} className="rounded-xl border border-sky-100 bg-sky-50/55 p-4">
                                    <div className="flex items-center gap-2">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">{num}</span>
                                        <div className="font-bold text-slate-800">{title}</div>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-slate-600">{text}</p>
                                </div>
                            ))}
                        </div>

                        <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-sm text-amber-800">
                            UTS dan UAS sudah dibuat oleh sistem pada minggu 8 dan 16. Anda cukup mengisi
                            <strong> bobot </strong> dan memilih <strong>Sub-CPMK yang diukur</strong>.
                            Tambahkan kuis/tugas/proyek sesuai rancangan dosen.
                        </div>

                        <div className="mt-5 space-y-3">
                            {assessments.map((assessment: any) => (
                                <AssessmentCard
                                    key={assessment.id}
                                    rpsId={rps.id}
                                    assessment={assessment}
                                    subCpmks={subCpmks}
                                />
                            ))}
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                assessmentForm.post(
                                    `/rps/${rps.id}/assessments`,
                                    actionOptions('Komponen asesmen berhasil ditambahkan.', () => assessmentForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-dashed border-teal-200 bg-teal-50/30 p-4"
                        >
                            <div className="font-bold text-slate-800">Tambah Komponen Asesmen</div>
                            <div className="mt-3 grid gap-3 md:grid-cols-4">
                                <label className="md:col-span-2">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Nama Asesmen</span>
                                    <input
                                        value={assessmentForm.data.name}
                                        onChange={(e) => assessmentForm.setData('name', e.target.value)}
                                        placeholder="Contoh: Tugas Pemrograman 1"
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                                    <select
                                        value={assessmentForm.data.type}
                                        onChange={(e) => assessmentForm.setData('type', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    >
                                        <option value="quiz">Kuis</option>
                                        <option value="assignment">Tugas</option>
                                        <option value="project">Proyek</option>
                                        <option value="presentation">Presentasi</option>
                                        <option value="practicum">Praktikum</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Bobot (%)</span>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={assessmentForm.data.weight}
                                        onChange={(e) => assessmentForm.setData('weight', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="16"
                                        value={assessmentForm.data.week_number}
                                        onChange={(e) => assessmentForm.setData('week_number', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
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
                    </section>

                    {/* RTM unchanged */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">6. Rencana Tugas Mahasiswa (RTM)</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Buat RTM jika ada tugas, proyek, praktikum, atau keluaran mahasiswa yang memerlukan instruksi terstruktur.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                taskForm.post(
                                    `/rps/${rps.id}/tasks`,
                                    actionOptions('RTM berhasil ditambahkan.', () => taskForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-teal-100 bg-teal-50/30 p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-3">
                                <label className="md:col-span-2">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Judul Tugas/Proyek</span>
                                    <input
                                        value={taskForm.data.title}
                                        onChange={(e) => taskForm.setData('title', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                                    <select
                                        value={taskForm.data.type}
                                        onChange={(e) => taskForm.setData('type', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="assignment">Tugas</option>
                                        <option value="project">Proyek</option>
                                        <option value="practicum">Praktikum</option>
                                        <option value="presentation">Presentasi</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Asesmen Terkait</span>
                                    <select
                                        value={taskForm.data.assessment_id}
                                        onChange={(e) => taskForm.setData('assessment_id', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Opsional</option>
                                        {assessments.map((assessment: any) => (
                                            <option key={assessment.id} value={assessment.id}>{assessment.name}</option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu Pengumpulan</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="16"
                                        value={taskForm.data.due_week}
                                        onChange={(e) => taskForm.setData('due_week', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Luaran</span>
                                    <input
                                        value={taskForm.data.expected_output}
                                        onChange={(e) => taskForm.setData('expected_output', e.target.value)}
                                        placeholder="Laporan, kode program, presentasi..."
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label className="md:col-span-3">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Instruksi Tugas</span>
                                    <textarea
                                        value={taskForm.data.instructions}
                                        onChange={(e) => taskForm.setData('instructions', e.target.value)}
                                        className="min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
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
                                            <div className="mt-1 text-xs text-slate-500">{task.type} · Minggu {task.due_week ?? '-'}</div>
                                            {task.expected_output && <div className="mt-2 text-sm text-slate-600">Luaran: {task.expected_output}</div>}
                                        </div>
                                        <button type="button" onClick={() => router.delete(`/rps/${rps.id}/tasks/${task.id}`, actionOptions('RTM berhasil dihapus.'))}>
                                            <Trash2 className="size-4 text-slate-300 hover:text-rose-600" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Validator */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <FileText className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">7. Validator OBE</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Validator menunjukkan bagian yang masih perlu diperbaiki sebelum RPS difinalkan.
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


function SubCpmkCard({ rpsId, sub, cpmks }: any) {
    const [editing, setEditing] = useState(false);
    const parentId = Array.isArray(sub.cpmk_ids) ? (sub.cpmk_ids[0] ?? '') : '';

    const form = useForm({
        rps_cpmk_id: parentId,
        description: sub.description ?? '',
        bloom_level: sub.bloom_level ?? '',
    });

    const parent = cpmks.find((c: any) => c.id === parentId);

    return (
        <div className="rounded-xl border border-slate-100 bg-white/60 p-4">
            {editing ? (
                <div>
                    <div className="grid gap-3 sm:grid-cols-[1fr_170px]">
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">CPMK Induk</span>
                            <select
                                value={form.data.rps_cpmk_id}
                                onChange={(e) => form.setData('rps_cpmk_id', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            >
                                {cpmks.map((cpmk: any) => (
                                    <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Level Kognitif (Bloom)</span>
                            <select
                                value={form.data.bloom_level}
                                onChange={(e) => form.setData('bloom_level', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            >
                                <option value="">Pilih level</option>
                                {['C1','C2','C3','C4','C5','C6'].map((level) => (
                                    <option key={level}>{level}</option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Rumusan Sub-CPMK</span>
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>

                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => form.put(
                                `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                actionOptions(`${sub.code} berhasil diperbarui.`, () => setEditing(false)),
                            )}
                            className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                form.setData({
                                    rps_cpmk_id: parentId,
                                    description: sub.description ?? '',
                                    bloom_level: sub.bloom_level ?? '',
                                });
                                setEditing(false);
                            }}
                            className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            ) : (
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="font-bold text-teal-800">{sub.code}</div>
                            {sub.bloom_level && (
                                <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                    {sub.bloom_level}
                                </span>
                            )}
                        </div>
                        <div className="mt-1 text-[11px] font-semibold text-slate-400">
                            Turunan dari: {parent?.code || '-'}
                        </div>
                        <p className="mt-2 text-sm leading-6 text-slate-600">{sub.description}</p>
                    </div>

                    <div className="flex shrink-0 gap-1">
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            title="Edit Sub-CPMK"
                            className="rounded-lg p-2 text-slate-400 hover:bg-teal-50 hover:text-teal-700"
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                if (confirm(`Hapus ${sub.code}? Relasi pada minggu/asesmen yang menggunakan Sub-CPMK ini akan dilepas.`)) {
                                    router.delete(
                                        `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                        actionOptions(`${sub.code} berhasil dihapus. Nomornya dapat dipakai kembali.`),
                                    );
                                }
                            }}
                            title="Hapus Sub-CPMK"
                            className="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function CpmkCard({ rpsId, cpmk, cpls, selectedCplIds, onToggle }: any) {
    const [editing, setEditing] = useState(false);

    const form = useForm({
        description: cpmk.description ?? '',
        bloom_level: cpmk.bloom_level ?? '',
    });

    const sourceLabel =
        cpmk.source_type === 'adapted'
            ? 'Diadaptasi dosen'
            : cpmk.source_type === 'manual'
              ? 'Ditambah dosen'
              : 'Master kurikulum';

    const sourceClass =
        cpmk.source_type === 'adapted'
            ? 'bg-amber-50 text-amber-700'
            : cpmk.source_type === 'manual'
              ? 'bg-sky-50 text-sky-700'
              : 'bg-emerald-50 text-emerald-700';

    return (
        <div className="rounded-xl border border-slate-100 bg-white/55 p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="font-bold text-teal-800">{cpmk.code}</div>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${sourceClass}`}>{sourceLabel}</span>
                        {cpmk.bloom_level && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{cpmk.bloom_level}</span>}
                    </div>

                    {editing ? (
                        <div className="mt-3">
                            <textarea
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                            <div className="mt-2 flex flex-wrap gap-2">
                                <select
                                    value={form.data.bloom_level}
                                    onChange={(e) => form.setData('bloom_level', e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">Bloom (opsional)</option>
                                    {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => form.put(
                                        `/rps/${rpsId}/cpmk/${cpmk.id}`,
                                        actionOptions(`${cpmk.code} berhasil diperbarui.`, () => setEditing(false)),
                                    )}
                                    className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                                >
                                    Simpan CPMK
                                </button>
                                <button type="button" onClick={() => setEditing(false)} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">
                                    Batal
                                </button>
                            </div>
                        </div>
                    ) : (
                        <p className="mt-2 text-sm leading-6 text-slate-600">{cpmk.description}</p>
                    )}

                    <div className="mt-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                Keterkaitan CPL
                            </div>
                            <div className="text-[10px] text-slate-400">
                                Narasi CPL tersedia pada Scope CPL RPS di atas.
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {cpls.map((cpl: any) => {
                                const checked = selectedCplIds.includes(cpl.id);
                                const curriculum = cpl.scope_source === 'curriculum';

                                return (
                                    <label
                                        key={cpl.id}
                                        title={cpl.description}
                                        className={`cursor-pointer rounded-xl border px-3 py-2 transition ${
                                            checked
                                                ? 'border-teal-300 bg-teal-50 text-teal-900 shadow-sm'
                                                : 'border-slate-200 bg-white/80 text-slate-500 hover:border-teal-200'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={checked}
                                            onChange={() => onToggle(cpl.id)}
                                        />
                                        <div className="flex items-center gap-2">
                                            <span className="font-extrabold">{cpl.code}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-[9px] font-bold ${
                                                curriculum
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-sky-100 text-sky-700'
                                            }`}>
                                                {curriculum ? 'Kurikulum' : 'Tambahan'}
                                            </span>
                                            <span className={`size-4 rounded-full border ${
                                                checked
                                                    ? 'border-teal-600 bg-teal-600 shadow-[inset_0_0_0_3px_white]'
                                                    : 'border-slate-300 bg-white'
                                            }`} />
                                        </div>
                                    </label>
                                );
                            })}
                        </div>

                        {cpls.length === 0 && (
                            <div className="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">
                                Belum ada CPL pada scope RPS.
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 gap-1">
                    <button type="button" onClick={() => setEditing(!editing)} title="Edit CPMK" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-teal-700">
                        <Pencil className="size-4" />
                    </button>
                    {cpmk.source_cpmk_id && cpmk.source_type === 'adapted' && (
                        <button
                            type="button"
                            onClick={() => router.post(`/rps/${rpsId}/cpmk/${cpmk.id}/reset`, {}, actionOptions(`${cpmk.code} dikembalikan ke master kurikulum.`))}
                            title="Kembalikan ke master"
                            className="rounded-lg p-2 text-slate-400 hover:bg-amber-50 hover:text-amber-700"
                        >
                            <RotateCcw className="size-4" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => {
                            if (confirm('Hapus CPMK dari RPS ini? Master kurikulum tidak akan berubah.')) {
                                router.delete(`/rps/${rpsId}/cpmk/${cpmk.id}`, actionOptions(`${cpmk.code} berhasil dihapus dari RPS.`));
                            }
                        }}
                        title="Hapus CPMK dari RPS"
                        className="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

function AssessmentCard({ rpsId, assessment, subCpmks }: any) {
    const form = useForm({
        name: assessment.name ?? '',
        type: assessment.type ?? 'assignment',
        week_number: assessment.week_number ?? '',
        weight: assessment.weight ?? '',
        description: assessment.description ?? '',
        sub_cpmk_ids: Array.isArray(assessment.sub_cpmk_ids) ? assessment.sub_cpmk_ids : [],
    });

    const toggleSub = (id: string) => {
        const current = form.data.sub_cpmk_ids;
        form.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x: string) => x !== id) : [...current, id],
        );
    };

    const systemExam = ['UTS', 'UAS'].includes(assessment.code);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/rps/${rpsId}/assessments/${assessment.id}`, actionOptions(`${assessment.name} berhasil disimpan.`));
            }}
            className="rounded-xl border border-slate-100 bg-white/60 p-4"
        >
            <div className="grid gap-3 md:grid-cols-[1.6fr_.8fr_.55fr_.55fr_auto]">
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Nama Asesmen</span>
                    <input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                    <select
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}
                        disabled={systemExam}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:bg-slate-50"
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
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu</span>
                    <input
                        type="number"
                        min="1"
                        max="16"
                        value={form.data.week_number}
                        onChange={(e) => form.setData('week_number', e.target.value)}
                        disabled={systemExam}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:bg-slate-50"
                    />
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Bobot (%)</span>
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={form.data.weight}
                        onChange={(e) => form.setData('weight', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>
                <div className="flex items-end gap-1">
                    <button disabled={form.processing} className="h-11 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white disabled:opacity-50">{form.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    {!systemExam && (
                        <button
                            type="button"
                            onClick={() => router.delete(`/rps/${rpsId}/assessments/${assessment.id}`, actionOptions('Komponen asesmen berhasil dihapus.'))}
                            className="flex size-11 items-center justify-center rounded-xl border border-slate-200 text-slate-400 hover:text-rose-600"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-3">
                <div className="text-xs font-bold text-slate-500">Sub-CPMK yang diukur</div>
                <div className="mt-2 flex flex-wrap gap-2">
                    {subCpmks.map((sub: any) => (
                        <label
                            key={sub.id}
                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                form.data.sub_cpmk_ids.includes(sub.id)
                                    ? 'border-teal-300 bg-teal-100 text-teal-800'
                                    : 'border-slate-200 bg-white text-slate-500'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="sr-only"
                                checked={form.data.sub_cpmk_ids.includes(sub.id)}
                                onChange={() => toggleSub(sub.id)}
                            />
                            {sub.code}
                        </label>
                    ))}
                </div>
            </div>
        </form>
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
        reference_text: week?.reference_text ?? '',
    });

    if (!week) return null;

    const info = weekCompletion(week);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/rps/${rpsId}/weeks/${week.week_number}`, actionOptions(`Minggu ${week.week_number}${week.exam_type ? ` (${week.exam_type})` : ''} berhasil disimpan.`));
            }}
            className="mt-5 rounded-2xl border border-teal-100 bg-teal-50/30 p-5"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-bold text-slate-900">Minggu {week.week_number} {week.exam_type ? `· ${week.exam_type}` : ''}</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Terisi {info.count}/7 komponen utama: {info.filled.join(', ') || 'belum ada'}.
                    </p>
                </div>
                {!week.is_exam && week.week_number > 1 && (
                    <button
                        type="button"
                        onClick={() => router.post(`/rps/${rpsId}/weeks/${week.week_number}/copy-previous`, {}, actionOptions(`Isi minggu ${week.week_number - 1} berhasil disalin ke minggu ${week.week_number}.`))}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                    >
                        <Copy className="size-3.5" /> Salin Minggu Sebelumnya
                    </button>
                )}
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Sub-CPMK Pertemuan</span>
                    <select
                        value={form.data.rps_sub_cpmk_id}
                        onChange={(e) => form.setData('rps_sub_cpmk_id', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    >
                        <option value="">Pilih Sub-CPMK</option>
                        {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code}</option>)}
                    </select>
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Metode Pembelajaran</span>
                    <input
                        value={form.data.learning_method}
                        onChange={(e) => form.setData('learning_method', e.target.value)}
                        placeholder="Ceramah interaktif, diskusi, PBL, praktikum..."
                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-2">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Materi / Bahan Kajian</span>
                    <textarea
                        value={form.data.material_text}
                        onChange={(e) => form.setData('material_text', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-2">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Aktivitas Pembelajaran Mahasiswa</span>
                    <textarea
                        value={form.data.learning_activity}
                        onChange={(e) => form.setData('learning_activity', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Indikator Asesmen</span>
                    <textarea
                        value={form.data.assessment_indicator}
                        onChange={(e) => form.setData('assessment_indicator', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Kriteria Asesmen</span>
                    <textarea
                        value={form.data.assessment_criteria}
                        onChange={(e) => form.setData('assessment_criteria', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-2">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Metode / Bentuk Asesmen Mingguan</span>
                    <input
                        value={form.data.assessment_method}
                        onChange={(e) => form.setData('assessment_method', e.target.value)}
                        placeholder="Latihan, kuis formatif, observasi kinerja..."
                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-2">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Referensi Pertemuan</span>
                    <textarea
                        value={form.data.reference_text}
                        onChange={(e) => form.setData('reference_text', e.target.value)}
                        className="min-h-16 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                    />
                </label>
            </div>

            <div className="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                Bobot nilai akhir tidak diatur di sini. Gunakan bagian <strong>5. Asesmen & Bobot Nilai Akhir</strong> agar total penilaian tidak ganda.
            </div>

            <button disabled={form.processing} className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                {form.processing ? 'Menyimpan...' : `Simpan Minggu ${week.week_number}${week.exam_type ? ` · ${week.exam_type}` : ''}`}
            </button>
        </form>
    );
}

function weekCompletion(week: any) {
    if (week?.is_exam) {
        return { count: 7, filled: ['Ujian'] };
    }

    const fields = [
        ['Sub-CPMK', week?.rps_sub_cpmk_id],
        ['Materi', week?.material_text],
        ['Metode', week?.learning_method],
        ['Aktivitas', week?.learning_activity],
        ['Indikator', week?.assessment_indicator],
        ['Asesmen', week?.assessment_method],
        ['Referensi', week?.reference_text],
    ];

    const filled = fields.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');

    return {
        count: filled.length,
        filled: filled.map(([label]) => label),
    };
}

RpsShow.layout = {
    breadcrumbs: [
        { title: 'RPS Saya', href: '/rps' },
        { title: 'Workspace OBE', href: '#' },
    ],
};
