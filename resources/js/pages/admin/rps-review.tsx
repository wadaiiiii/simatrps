import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenCheck,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    Clock3,
    FileCheck2,
    FileText,
    ListChecks,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';

type Rps = {
    id: string;
    academic_year: string;
    academic_semester: string;
    status: string;
    version_status: string;
    version_no: number;
    finalized_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    course: {
        name: string;
        code: string;
        credits: number;
    };
    owner: {
        id: number;
        name: string;
        academic_title?: string | null;
        nidn?: string | null;
        email: string;
    };
};

type Summary = {
    cpmk_count: number;
    sub_cpmk_count: number;
    week_count: number;
    assessment_count: number;
    assessment_weight_total: number;
    task_count: number;
    obe_percent?: number | null;
    obe_validated_at?: string | null;
};

type LearningOutcome = {
    id: string;
    code: string;
    description: string;
    bloom_level?: string | null;
    sequence_no: number;
};

type Week = {
    week_number: number;
    is_exam: boolean;
    exam_type?: string | null;
    material_text?: string | null;
    learning_method?: string | null;
    learning_activity?: string | null;
    assessment_indicator?: string | null;
    assessment_criteria?: string | null;
    assessment_weight?: number | null;
    sub_cpmk_code?: string | null;
    sub_cpmk_description?: string | null;
};

type Assessment = {
    id: string;
    code: string;
    name: string;
    type: string;
    week_number?: number | null;
    description?: string | null;
    weight?: number | null;
};

type Task = {
    id: string;
    code: string;
    title: string;
    type: string;
    purpose?: string | null;
    instructions?: string | null;
    expected_output?: string | null;
    due_week?: number | null;
    assessment_code?: string | null;
    assessment_name?: string | null;
};

type ValidationRow = {
    rule_code: string;
    severity: string;
    is_passed: boolean;
    message: string;
    validated_at?: string | null;
};

type ReviewItem = {
    id: string;
    status: string;
    note?: string | null;
    reviewed_at?: string | null;
    reviewer_id: number;
    reviewer_name: string;
};

type ReviewState = {
    latest?: ReviewItem | null;
    outdated: boolean;
    history: ReviewItem[];
};

export default function RpsReview({
    rps,
    summary,
    cpmks,
    subCpmks,
    weeks,
    assessments,
    tasks,
    validationRows,
    review,
}: {
    rps: Rps;
    summary: Summary;
    cpmks: LearningOutcome[];
    subCpmks: LearningOutcome[];
    weeks: Week[];
    assessments: Assessment[];
    tasks: Task[];
    validationRows: ValidationRow[];
    review: ReviewState;
}) {
    const reviewForm = useForm({
        status: review.latest?.status === 'approved' ? 'approved' : 'revision_required',
        note: '',
    });

    const submitReview = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        reviewForm.post(`/admin/rps/${rps.id}/review`, {
            preserveScroll: true,
            onSuccess: () => reviewForm.setData('note', ''),
        });
    };

    return (
        <>
            <Head title={`Review RPS - ${rps.course.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <Link
                        href={`/admin/pengguna/${rps.owner.id}/monitoring`}
                        className="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-teal-700"
                    >
                        <ArrowLeft className="size-4" />
                        Monitoring {rps.owner.name}
                    </Link>
                    <span className="text-slate-300">/</span>
                    <Link href="/dashboard" className="text-sm font-bold text-slate-500 transition hover:text-teal-700">
                        Dashboard Admin
                    </Link>
                </div>

                <section className="rounded-3xl border border-cyan-100 bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-6 text-white shadow-sm">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-bold uppercase tracking-[0.2em] text-cyan-200">Review RPS · Read-only</span>
                                <RpsStatus status={rps.status} />
                            </div>
                            <h1 className="mt-3 text-2xl font-black tracking-tight md:text-3xl">{rps.course.name}</h1>
                            <p className="mt-2 text-sm text-cyan-50/80">
                                {rps.course.code} · {formatNumber(rps.course.credits)} SKS · {rps.academic_year} · {rps.academic_semester} · v{formatNumber(rps.version_no)}
                            </p>
                            <p className="mt-2 text-sm font-semibold text-cyan-100">
                                {rps.owner.name}
                                {rps.owner.academic_title ? `, ${rps.owner.academic_title}` : ''}
                                {rps.owner.nidn ? ` · NIDN ${rps.owner.nidn}` : ''}
                            </p>
                            <p className="mt-1 text-xs text-cyan-100/70">{rps.owner.email}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <MiniStat label="Pertemuan" value={`${summary.week_count}/16`} />
                            <MiniStat label="Bobot" value={`${formatNumber(summary.assessment_weight_total)}%`} />
                            <MiniStat label="RTM" value={summary.task_count} />
                            <MiniStat label="OBE" value={summary.obe_percent == null ? '-' : `${summary.obe_percent}%`} />
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <main className="space-y-6">
                        <section className="sim-surface rounded-2xl p-5">
                            <SectionHeading
                                icon={BookOpenCheck}
                                eyebrow="Capaian Pembelajaran"
                                title="CPMK dan Sub-CPMK"
                                helper={`${summary.cpmk_count} CPMK · ${summary.sub_cpmk_count} Sub-CPMK`}
                            />
                            <div className="mt-5 grid gap-5 lg:grid-cols-2">
                                <OutcomeList title="CPMK" items={cpmks} />
                                <OutcomeList title="Sub-CPMK" items={subCpmks} />
                            </div>
                        </section>

                        <section className="sim-surface rounded-2xl p-5">
                            <SectionHeading
                                icon={ListChecks}
                                eyebrow="Rencana Mingguan"
                                title="Distribusi 16 pertemuan"
                                helper="Admin hanya membaca data tersimpan; tidak ada kontrol edit pada halaman ini."
                            />
                            <div className="mt-5 overflow-x-auto">
                                <table className="w-full min-w-[1100px] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                            <th className="px-3 py-3">Pekan</th>
                                            <th className="px-3 py-3">Sub-CPMK</th>
                                            <th className="px-3 py-3">Bahan Kajian</th>
                                            <th className="px-3 py-3">Metode & Aktivitas</th>
                                            <th className="px-3 py-3">Penilaian</th>
                                            <th className="px-3 py-3 text-right">Bobot</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {weeks.map((week) => (
                                            <tr key={week.week_number} className="align-top">
                                                <td className="px-3 py-4 font-black text-slate-900">{week.week_number}</td>
                                                <td className="px-3 py-4">
                                                    {week.is_exam ? (
                                                        <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                                                            {week.exam_type || 'Ujian'}
                                                        </span>
                                                    ) : (
                                                        <>
                                                            <div className="font-bold text-teal-700">{week.sub_cpmk_code || '-'}</div>
                                                            <div className="mt-1 max-w-64 text-xs leading-5 text-slate-500">{week.sub_cpmk_description || '-'}</div>
                                                        </>
                                                    )}
                                                </td>
                                                <td className="px-3 py-4 max-w-72 leading-6 text-slate-700">{week.material_text || '-'}</td>
                                                <td className="px-3 py-4 max-w-80">
                                                    <div className="font-semibold text-slate-700">{week.learning_method || '-'}</div>
                                                    <div className="mt-1 text-xs leading-5 text-slate-500">{week.learning_activity || '-'}</div>
                                                </td>
                                                <td className="px-3 py-4 max-w-72">
                                                    <div className="text-xs leading-5 text-slate-600">{week.assessment_indicator || '-'}</div>
                                                    {week.assessment_criteria && (
                                                        <div className="mt-1 text-xs leading-5 text-slate-400">{week.assessment_criteria}</div>
                                                    )}
                                                </td>
                                                <td className="px-3 py-4 text-right font-black text-slate-700">
                                                    {formatNumber(week.assessment_weight ?? 0)}%
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section className="grid gap-6 lg:grid-cols-2">
                            <div className="sim-surface rounded-2xl p-5">
                                <SectionHeading
                                    icon={ClipboardCheck}
                                    eyebrow="Asesmen"
                                    title={`${summary.assessment_count} komponen penilaian`}
                                    helper={`Total bobot ${formatNumber(summary.assessment_weight_total)}%`}
                                />
                                <div className="mt-4 space-y-3">
                                    {assessments.length === 0 ? (
                                        <Empty text="Belum ada komponen asesmen." />
                                    ) : assessments.map((assessment) => (
                                        <div key={assessment.id} className="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-black text-slate-900">{assessment.code} · {assessment.name}</div>
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {assessment.type} · {assessment.week_number ? `pekan ${assessment.week_number}` : 'tanpa pekan khusus'}
                                                    </div>
                                                </div>
                                                <span className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-black text-sky-700">
                                                    {formatNumber(assessment.weight ?? 0)}%
                                                </span>
                                            </div>
                                            {assessment.description && <p className="mt-2 text-xs leading-5 text-slate-600">{assessment.description}</p>}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="sim-surface rounded-2xl p-5">
                                <SectionHeading
                                    icon={FileText}
                                    eyebrow="RTM"
                                    title={`${summary.task_count} rencana tugas mahasiswa`}
                                    helper="Relasi RTM ditampilkan tanpa menjalankan sinkronisasi otomatis."
                                />
                                <div className="mt-4 space-y-3">
                                    {tasks.length === 0 ? (
                                        <Empty text="Belum ada RTM." />
                                    ) : tasks.map((task) => (
                                        <div key={task.id} className="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                            <div className="font-black text-slate-900">{task.code} · {task.title}</div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                {task.type} · {task.due_week ? `pekan ${task.due_week}` : 'pekan belum ditetapkan'}
                                            </div>
                                            <div className="mt-2 text-xs font-semibold text-teal-700">
                                                Induk: {task.assessment_code ? `${task.assessment_code} · ${task.assessment_name || ''}` : 'Belum terhubung asesmen'}
                                            </div>
                                            {task.purpose && <p className="mt-2 text-xs leading-5 text-slate-600">{task.purpose}</p>}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section className="sim-surface rounded-2xl p-5">
                            <SectionHeading
                                icon={ShieldCheck}
                                eyebrow="Validator OBE"
                                title={summary.obe_percent == null ? 'Belum ada hasil validasi' : `Ketercapaian ${summary.obe_percent}%`}
                                helper={summary.obe_validated_at ? `Validasi terakhir ${formatDateTime(summary.obe_validated_at)}` : 'Jalankan Validator OBE dari akun dosen untuk menghasilkan pemeriksaan.'}
                            />
                            <div className="mt-4 grid gap-3 md:grid-cols-2">
                                {validationRows.length === 0 ? (
                                    <div className="md:col-span-2"><Empty text="Belum ada hasil Validator OBE yang tersimpan." /></div>
                                ) : validationRows.map((item) => (
                                    <div key={item.rule_code} className="rounded-xl border border-slate-100 p-4">
                                        <div className="flex items-start gap-3">
                                            {item.is_passed ? (
                                                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            ) : (
                                                <CircleAlert className="mt-0.5 size-5 shrink-0 text-amber-600" />
                                            )}
                                            <div>
                                                <div className="font-bold text-slate-800">{item.rule_code}</div>
                                                <div className="mt-1 text-xs uppercase tracking-wide text-slate-400">{item.severity}</div>
                                                <p className="mt-2 text-sm leading-6 text-slate-600">{item.message}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </main>

                    <aside className="space-y-5 xl:sticky xl:top-6 xl:self-start">
                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex items-center gap-3">
                                <div className="rounded-xl bg-teal-50 p-2.5 text-teal-700">
                                    <FileCheck2 className="size-5" />
                                </div>
                                <div>
                                    <h2 className="font-black text-slate-900">Keputusan Review</h2>
                                    <p className="text-xs text-slate-500">Simpan tindak lanjut tanpa mengubah isi RPS.</p>
                                </div>
                            </div>

                            <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <div className="text-[11px] font-bold uppercase tracking-wide text-slate-400">Status terakhir</div>
                                <div className="mt-2"><ReviewBadge review={review.latest} outdated={review.outdated} /></div>
                                {review.latest?.note && (
                                    <p className="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{review.latest.note}</p>
                                )}
                                {review.latest?.reviewed_at && (
                                    <div className="mt-3 flex items-center gap-1.5 text-xs text-slate-400">
                                        <Clock3 className="size-3.5" />
                                        {review.latest.reviewer_name} · {formatDateTime(review.latest.reviewed_at)}
                                    </div>
                                )}
                            </div>

                            {review.outdated && (
                                <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-800">
                                    RPS berubah setelah keputusan review terakhir. Lakukan review ulang sebelum menetapkan keputusan terbaru.
                                </div>
                            )}

                            <form onSubmit={submitReview} className="mt-5 space-y-4">
                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Keputusan</span>
                                    <select
                                        value={reviewForm.data.status}
                                        onChange={(event) => reviewForm.setData('status', event.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                                    >
                                        <option value="revision_required">Perlu Revisi</option>
                                        <option value="approved" disabled={rps.status.toLowerCase() !== 'final'}>Disetujui</option>
                                    </select>
                                    {reviewForm.errors.status && <p className="mt-1 text-xs font-semibold text-rose-600">{reviewForm.errors.status}</p>}
                                    {rps.status.toLowerCase() !== 'final' && (
                                        <p className="mt-1.5 text-xs leading-5 text-slate-400">Persetujuan tersedia setelah RPS berstatus Final.</p>
                                    )}
                                </label>

                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Catatan</span>
                                    <textarea
                                        value={reviewForm.data.note}
                                        onChange={(event) => reviewForm.setData('note', event.target.value)}
                                        rows={6}
                                        placeholder="Tuliskan bagian yang perlu diperbaiki, atau catatan persetujuan..."
                                        className="w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm leading-6 outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                                    />
                                    {reviewForm.errors.note && <p className="mt-1 text-xs font-semibold text-rose-600">{reviewForm.errors.note}</p>}
                                </label>

                                <button
                                    type="submit"
                                    disabled={reviewForm.processing}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-4 py-3 text-sm font-black text-white transition hover:bg-teal-800 disabled:opacity-50"
                                >
                                    <ClipboardCheck className="size-4" />
                                    {reviewForm.processing ? 'Menyimpan…' : 'Simpan Keputusan Review'}
                                </button>
                            </form>
                        </section>

                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-black text-slate-900">Riwayat Tindak Lanjut</h2>
                            <p className="mt-1 text-xs text-slate-500">20 keputusan terbaru untuk versi RPS ini.</p>
                            <div className="mt-4 space-y-3">
                                {review.history.length === 0 ? (
                                    <Empty text="Belum pernah direview." />
                                ) : review.history.map((item) => (
                                    <div key={item.id} className="rounded-xl border border-slate-100 p-3">
                                        <ReviewBadge review={item} outdated={false} />
                                        {item.note && <p className="mt-2 whitespace-pre-line text-xs leading-5 text-slate-600">{item.note}</p>}
                                        <div className="mt-2 text-[11px] text-slate-400">
                                            {item.reviewer_name} · {formatDateTime(item.reviewed_at)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

function SectionHeading({
    icon: Icon,
    eyebrow,
    title,
    helper,
}: {
    icon: typeof BookOpenCheck;
    eyebrow: string;
    title: string;
    helper: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <div className="rounded-xl bg-teal-50 p-2.5 text-teal-700"><Icon className="size-5" /></div>
            <div>
                <div className="text-[11px] font-bold uppercase tracking-[0.15em] text-teal-700">{eyebrow}</div>
                <h2 className="mt-1 text-lg font-black text-slate-900">{title}</h2>
                <p className="mt-1 text-sm text-slate-500">{helper}</p>
            </div>
        </div>
    );
}

function OutcomeList({ title, items }: { title: string; items: LearningOutcome[] }) {
    return (
        <div>
            <div className="mb-3 text-xs font-bold uppercase tracking-wide text-slate-400">{title}</div>
            <div className="space-y-3">
                {items.length === 0 ? <Empty text={`Belum ada ${title}.`} /> : items.map((item) => (
                    <div key={item.id} className="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                        <div className="flex items-start gap-3">
                            <span className="rounded-lg bg-white px-2 py-1 text-xs font-black text-teal-700 ring-1 ring-slate-100">{item.code}</span>
                            <div>
                                <p className="text-sm leading-6 text-slate-700">{item.description}</p>
                                {item.bloom_level && <p className="mt-1 text-xs font-semibold text-slate-400">Bloom {item.bloom_level}</p>}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function MiniStat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="min-w-24 rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10">
            <div className="text-xl font-black text-white">{value}</div>
            <div className="mt-0.5 text-[11px] font-semibold text-cyan-100/75">{label}</div>
        </div>
    );
}

function RpsStatus({ status }: { status: string }) {
    const final = status.toLowerCase() === 'final';
    return (
        <span className={`rounded-full px-2.5 py-1 text-[11px] font-black ${final ? 'bg-emerald-400/15 text-emerald-100 ring-1 ring-emerald-300/25' : 'bg-amber-400/15 text-amber-100 ring-1 ring-amber-300/25'}`}>
            {final ? 'FINAL' : status.toUpperCase()}
        </span>
    );
}

function ReviewBadge({ review, outdated }: { review?: ReviewItem | null; outdated: boolean }) {
    if (!review) {
        return <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">Belum Ditinjau</span>;
    }
    if (outdated) {
        return <span className="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">Menunggu Review Ulang</span>;
    }
    if (review.status === 'approved') {
        return <span className="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">Disetujui</span>;
    }
    return <span className="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-black text-rose-700">Perlu Revisi</span>;
}

function Empty({ text }: { text: string }) {
    return <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-400">{text}</div>;
}

function formatNumber(value: number) {
    return Number.isInteger(Number(value)) ? String(Number(value)) : Number(value).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

RpsReview.layout = {
    breadcrumbs: [{ title: 'Review RPS', href: '/dashboard' }],
};