import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Layers3,
    Network,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

export default function RpsShow(rawProps: any) {
    const {
        rps,
        cpls = [],
        cpmks = [],
        subCpmks = [],
        materials = [],
        weeks = [],
        progress = { percent: 0, checks: [] },
        patch03Ready = false,
    } = rawProps ?? {};

    const [openWeek, setOpenWeek] = useState<number | null>(null);

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

    const toggle = (cpmkId: string, cplId: string) => {
        const current = mappingForm.data.mappings[cpmkId] ?? [];
        const next = current.includes(cplId)
            ? current.filter((id: string) => id !== cplId)
            : [...current, cplId];

        mappingForm.setData('mappings', {
            ...mappingForm.data.mappings,
            [cpmkId]: next,
        });
    };

    if (!rps) {
        return (
            <>
                <Head title="Workspace RPS" />
                <div className="p-6">
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                        Data RPS belum diterima dari backend. Terapkan Patch 03A dan refresh halaman.
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`RPS ${rps.course_name}`} />

            <div className="p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-teal-700">
                            Workspace OBE
                        </div>
                        <h1 className="mt-1 text-2xl font-bold">{rps.course_name}</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {rps.official_code || rps.system_code} · {rps.credits} SKS ·{' '}
                            {rps.academic_year} {rps.academic_semester}
                        </p>
                    </div>

                    <div className="sim-surface w-full max-w-md rounded-2xl p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-xs font-bold uppercase text-slate-400">
                                    Progress OBE
                                </div>
                                <div className="mt-1 text-2xl font-extrabold">
                                    {progress?.percent ?? 0}%
                                </div>
                            </div>
                        </div>

                        <div className="mt-3 h-2 rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-teal-700 to-cyan-400"
                                style={{ width: `${progress?.percent ?? 0}%` }}
                            />
                        </div>

                        <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                            {(progress?.checks ?? []).map((item: any) => (
                                <div key={item.label} className="flex items-center gap-2">
                                    {item.done ? (
                                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                                    ) : (
                                        <CircleAlert className="size-3.5 text-amber-500" />
                                    )}
                                    <span>{item.label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {!patch03Ready && (
                    <div className="mt-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <CircleAlert className="mt-0.5 size-5 shrink-0" />
                        Struktur database Patch 03 belum lengkap. Jalankan migration Patch 03 lalu refresh.
                    </div>
                )}

                <div className="mt-6 space-y-5">
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <Network className="size-5 text-teal-700" />
                            <h2 className="font-bold">1. Pemetaan CPMK → CPL</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Hanya CPL resmi mata kuliah yang dapat dipilih.
                        </p>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                mappingForm.put(`/rps/${rps.id}/cpmk-cpl`, {
                                    preserveScroll: true,
                                });
                            }}
                            className="mt-5 space-y-3"
                        >
                            {cpmks.map((cpmk: any) => (
                                <div
                                    key={cpmk.id}
                                    className="rounded-xl border border-slate-100 bg-white/55 p-4"
                                >
                                    <div className="font-bold text-teal-800">{cpmk.code}</div>
                                    <div className="mt-1 text-sm text-slate-600">
                                        {cpmk.description}
                                    </div>

                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {cpls.map((cpl: any) => {
                                            const checked = (
                                                mappingForm.data.mappings[cpmk.id] ?? []
                                            ).includes(cpl.id);

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
                                                        disabled={!patch03Ready}
                                                        onChange={() => toggle(cpmk.id, cpl.id)}
                                                    />
                                                    {cpl.code}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}

                            {cpmks.length > 0 && (
                                <button
                                    disabled={!patch03Ready || mappingForm.processing}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45"
                                >
                                    Simpan Pemetaan
                                </button>
                            )}
                        </form>
                    </section>

                    <div className="grid gap-5 xl:grid-cols-2">
                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-bold">2. Sub-CPMK</h2>

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    subForm.post(`/rps/${rps.id}/sub-cpmk`, {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            subForm.reset('description', 'bloom_level'),
                                    });
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-[1fr_120px]">
                                    <select
                                        value={subForm.data.rps_cpmk_id}
                                        onChange={(event) =>
                                            subForm.setData(
                                                'rps_cpmk_id',
                                                event.target.value,
                                            )
                                        }
                                        className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        {cpmks.map((cpmk: any) => (
                                            <option key={cpmk.id} value={cpmk.id}>
                                                {cpmk.code}
                                            </option>
                                        ))}
                                    </select>

                                    <select
                                        value={subForm.data.bloom_level}
                                        onChange={(event) =>
                                            subForm.setData(
                                                'bloom_level',
                                                event.target.value,
                                            )
                                        }
                                        className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Bloom</option>
                                        {['C1', 'C2', 'C3', 'C4', 'C5', 'C6'].map(
                                            (level) => (
                                                <option key={level}>{level}</option>
                                            ),
                                        )}
                                    </select>
                                </div>

                                <textarea
                                    value={subForm.data.description}
                                    onChange={(event) =>
                                        subForm.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-3 min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    placeholder="Deskripsi Sub-CPMK"
                                />

                                <button
                                    disabled={!patch03Ready || !cpmks.length}
                                    className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45"
                                >
                                    <Plus className="size-4" />
                                    Tambah
                                </button>
                            </form>

                            <div className="mt-4 space-y-2">
                                {subCpmks.map((sub: any) => (
                                    <div
                                        key={sub.id}
                                        className="flex items-start justify-between rounded-xl border border-slate-100 bg-white/60 p-4"
                                    >
                                        <div>
                                            <div className="font-bold text-teal-800">
                                                {sub.code}
                                                {sub.bloom_level && (
                                                    <span className="ml-2 text-xs text-sky-700">
                                                        {sub.bloom_level}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {sub.description}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.delete(
                                                    `/rps/${rps.id}/sub-cpmk/${sub.id}`,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4 text-slate-300 hover:text-rose-600" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Layers3 className="size-5 text-teal-700" />
                                        <h2 className="font-bold">3. Bahan Kajian</h2>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Tambah manual atau ambil dari silabus.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    disabled={!patch03Ready}
                                    onClick={() =>
                                        router.post(
                                            `/rps/${rps.id}/materials/import-syllabus`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700 disabled:opacity-45"
                                >
                                    Ambil dari Silabus
                                </button>
                            </div>

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    materialForm.post(`/rps/${rps.id}/materials`, {
                                        preserveScroll: true,
                                        onSuccess: () => materialForm.reset(),
                                    });
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <select
                                    value={materialForm.data.rps_sub_cpmk_id}
                                    onChange={(event) =>
                                        materialForm.setData(
                                            'rps_sub_cpmk_id',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                >
                                    <option value="">Tanpa Sub-CPMK khusus</option>
                                    {subCpmks.map((sub: any) => (
                                        <option key={sub.id} value={sub.id}>
                                            {sub.code}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    value={materialForm.data.title}
                                    onChange={(event) =>
                                        materialForm.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-3 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    placeholder="Judul bahan kajian"
                                />

                                <button
                                    disabled={!patch03Ready}
                                    className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45"
                                >
                                    <Plus className="size-4" />
                                    Tambah
                                </button>
                            </form>

                            <div className="mt-4 max-h-72 space-y-2 overflow-y-auto">
                                {materials.map((material: any) => (
                                    <div
                                        key={material.id}
                                        className="rounded-xl border border-slate-100 bg-white/60 p-4"
                                    >
                                        <div className="font-semibold">
                                            {material.title}
                                        </div>
                                        <div className="mt-1 text-[10px] uppercase text-slate-400">
                                            {material.source_type}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <section className="sim-surface rounded-2xl p-5">
                        <h2 className="font-bold">4. Rencana 16 Pertemuan</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Klik minggu untuk edit. UTS minggu 8, UAS minggu 16.
                        </p>

                        <div className="mt-5 grid gap-2 sm:grid-cols-4 lg:grid-cols-8 xl:grid-cols-16">
                            {weeks.map((week: any) => {
                                const filled =
                                    week.is_exam ||
                                    week.rps_sub_cpmk_id ||
                                    week.material_text ||
                                    week.learning_method;

                                return (
                                    <button
                                        key={week.week_number}
                                        type="button"
                                        onClick={() =>
                                            setOpenWeek(
                                                openWeek === week.week_number
                                                    ? null
                                                    : week.week_number,
                                            )
                                        }
                                        className={`rounded-xl border p-3 text-center ${
                                            week.is_exam
                                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                : filled
                                                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                  : 'border-slate-200 bg-white/60 text-slate-600'
                                        }`}
                                    >
                                        <div className="text-xs font-bold">
                                            {week.week_number}
                                        </div>
                                        <div className="mt-1 text-[10px]">
                                            {week.exam_type ||
                                                (filled ? 'Terisi' : 'Kosong')}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        {openWeek && (
                            <WeekEditor
                                rpsId={rps.id}
                                week={weeks.find(
                                    (week: any) =>
                                        week.week_number === openWeek,
                                )}
                                subCpmks={subCpmks}
                                patch03Ready={patch03Ready}
                            />
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

function WeekEditor({
    rpsId,
    week,
    subCpmks,
    patch03Ready,
}: any) {
    const form = useForm({
        rps_sub_cpmk_id: week?.rps_sub_cpmk_id ?? '',
        material_text: week?.material_text ?? '',
        learning_method: week?.learning_method ?? '',
        learning_activity: week?.learning_activity ?? '',
        assessment_indicator: week?.assessment_indicator ?? '',
        assessment_method: week?.assessment_method ?? '',
        assessment_weight: week?.assessment_weight ?? '',
        reference_text: week?.reference_text ?? '',
    });

    if (!week) {
        return null;
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/rps/${rpsId}/weeks/${week.week_number}`, {
                    preserveScroll: true,
                });
            }}
            className="mt-5 rounded-2xl border border-teal-100 bg-teal-50/30 p-5"
        >
            <h3 className="font-bold">
                Minggu {week.week_number}{' '}
                {week.exam_type ? `· ${week.exam_type}` : ''}
            </h3>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <select
                    value={form.data.rps_sub_cpmk_id}
                    onChange={(event) =>
                        form.setData(
                            'rps_sub_cpmk_id',
                            event.target.value,
                        )
                    }
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                >
                    <option value="">Pilih Sub-CPMK</option>
                    {subCpmks.map((sub: any) => (
                        <option key={sub.id} value={sub.id}>
                            {sub.code}
                        </option>
                    ))}
                </select>

                <input
                    value={form.data.learning_method}
                    onChange={(event) =>
                        form.setData(
                            'learning_method',
                            event.target.value,
                        )
                    }
                    placeholder="Metode pembelajaran"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />

                <textarea
                    value={form.data.material_text}
                    onChange={(event) =>
                        form.setData(
                            'material_text',
                            event.target.value,
                        )
                    }
                    placeholder="Materi/Bahan Kajian"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />

                <textarea
                    value={form.data.learning_activity}
                    onChange={(event) =>
                        form.setData(
                            'learning_activity',
                            event.target.value,
                        )
                    }
                    placeholder="Aktivitas pembelajaran"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />

                <textarea
                    value={form.data.assessment_indicator}
                    onChange={(event) =>
                        form.setData(
                            'assessment_indicator',
                            event.target.value,
                        )
                    }
                    placeholder="Indikator asesmen"
                    className="min-h-20 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />

                <input
                    value={form.data.assessment_method}
                    onChange={(event) =>
                        form.setData(
                            'assessment_method',
                            event.target.value,
                        )
                    }
                    placeholder="Metode asesmen"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />

                <input
                    type="number"
                    min="0"
                    max="100"
                    value={form.data.assessment_weight}
                    onChange={(event) =>
                        form.setData(
                            'assessment_weight',
                            event.target.value,
                        )
                    }
                    placeholder="Bobot %"
                    className="rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                />

                <textarea
                    value={form.data.reference_text}
                    onChange={(event) =>
                        form.setData(
                            'reference_text',
                            event.target.value,
                        )
                    }
                    placeholder="Referensi"
                    className="min-h-16 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm md:col-span-2"
                />
            </div>

            <button
                disabled={!patch03Ready || form.processing}
                className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45"
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
