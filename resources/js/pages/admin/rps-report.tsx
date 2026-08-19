import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    FileCheck2,
    FileText,
    Printer,
    RefreshCcw,
    Search,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

type Stats = {
    total: number;
    lecturers: number;
    draft: number;
    final: number;
    obe_valid: number;
    approved: number;
    revision_required: number;
    outdated: number;
    unreviewed: number;
};

type Filters = {
    q: string;
    academic_year: string;
    academic_semester: string;
    status: string;
    review_status: string;
};

type RpsReportRow = {
    id: string;
    academic_year: string;
    academic_semester: string;
    status: string;
    updated_at: string;
    finalized_at?: string | null;
    owner: {
        id: number;
        name: string;
        academic_title?: string | null;
        nidn?: string | null;
        email: string;
    };
    course: {
        name: string;
        code: string;
        credits: number;
    };
    progress: {
        filled_weeks: number;
        week_total: number;
        assessment_weight: number;
        obe_percent?: number | null;
    };
    review: {
        status?: string | null;
        note?: string | null;
        reviewed_at?: string | null;
        reviewer_name?: string | null;
        outdated: boolean;
    };
};

export default function RpsReport({
    stats,
    rows,
    filters,
    academicYears,
}: {
    stats: Stats;
    rows: RpsReportRow[];
    filters: Filters;
    academicYears: string[];
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const [academicYear, setAcademicYear] = useState(filters.academic_year ?? '');
    const [academicSemester, setAcademicSemester] = useState(filters.academic_semester ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [reviewStatus, setReviewStatus] = useState(filters.review_status ?? '');

    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (filters.q) params.set('q', filters.q);
        if (filters.academic_year) params.set('academic_year', filters.academic_year);
        if (filters.academic_semester) params.set('academic_semester', filters.academic_semester);
        if (filters.status) params.set('status', filters.status);
        if (filters.review_status) params.set('review_status', filters.review_status);
        return params.toString();
    }, [filters]);

    const submitFilter = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/admin/rekap',
            {
                q: q || undefined,
                academic_year: academicYear || undefined,
                academic_semester: academicSemester || undefined,
                status: status || undefined,
                review_status: reviewStatus || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const resetFilter = () => {
        setQ('');
        setAcademicYear('');
        setAcademicSemester('');
        setStatus('');
        setReviewStatus('');
        router.get('/admin/rekap', {}, { replace: true });
    };

    const exportHref = `/admin/rekap/export.csv${queryString ? `?${queryString}` : ''}`;

    return (
        <>
            <Head title="Rekap & Ekspor RPS" />

            <style>{`
                @media print {
                    @page { size: landscape; margin: 10mm; }
                    body { background: white !important; }
                    .report-print-hide { display: none !important; }
                    .report-print-surface { border: 0 !important; box-shadow: none !important; }
                    .report-print-table { font-size: 9px !important; }
                    .report-print-table th, .report-print-table td { padding: 5px 6px !important; }
                }
            `}</style>

            <div className="space-y-6 p-4 md:p-6">
                <section className="report-print-surface rounded-3xl border border-cyan-100 bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-6 text-white shadow-sm print:bg-white print:text-slate-950">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <div className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-200 print:text-slate-500">
                                Administrasi RPS
                            </div>
                            <h1 className="mt-2 text-2xl font-black tracking-tight md:text-3xl">Rekap & Ekspor RPS</h1>
                            <p className="mt-2 text-sm leading-6 text-cyan-50/80 print:text-slate-600">
                                Rekap status penyusunan, Validator OBE, hasil review Admin, dan tindak lanjut seluruh RPS dosen.
                            </p>
                        </div>

                        <div className="report-print-hide flex flex-wrap gap-2">
                            <a
                                href={exportHref}
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-black text-teal-800 shadow-sm transition hover:bg-cyan-50"
                            >
                                <Download className="size-4" />
                                Ekspor CSV
                            </a>
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20"
                            >
                                <Printer className="size-4" />
                                Cetak / Simpan PDF
                            </button>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard icon={FileText} label="RPS Terekam" value={stats.total} helper={`${stats.lecturers} dosen pada hasil filter`} tone="slate" />
                    <StatCard icon={FileCheck2} label="RPS Final" value={stats.final} helper={`${stats.obe_valid} RPS OBE 100%`} tone="teal" />
                    <StatCard icon={CheckCircle2} label="Disetujui" value={stats.approved} helper="Review aktif dan selesai" tone="emerald" />
                    <StatCard icon={TriangleAlert} label="Perlu Tindak Lanjut" value={stats.revision_required + stats.outdated} helper={`${stats.revision_required} revisi · ${stats.outdated} review ulang`} tone="amber" />
                </section>

                <section className="report-print-hide sim-surface rounded-2xl p-5">
                    <div>
                        <h2 className="text-lg font-black text-slate-900">Filter Rekap</h2>
                        <p className="mt-1 text-sm text-slate-500">Filter yang aktif juga diterapkan pada file CSV yang diekspor.</p>
                    </div>

                    <form onSubmit={submitFilter} className="mt-5 grid gap-3 xl:grid-cols-[1.35fr_0.8fr_0.7fr_0.7fr_0.85fr_auto]">
                        <label className="relative block">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={q}
                                onChange={(event) => setQ(event.target.value)}
                                placeholder="Cari dosen, NIDN, email, atau mata kuliah..."
                                className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                            />
                        </label>

                        <select
                            value={academicYear}
                            onChange={(event) => setAcademicYear(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua tahun</option>
                            {academicYears.map((year) => <option key={year} value={year}>{year}</option>)}
                        </select>

                        <select
                            value={academicSemester}
                            onChange={(event) => setAcademicSemester(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua semester</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>

                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua status RPS</option>
                            <option value="draft">Draft</option>
                            <option value="obe_valid">OBE Valid</option>
                            <option value="final">Final</option>
                        </select>

                        <select
                            value={reviewStatus}
                            onChange={(event) => setReviewStatus(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua tindak lanjut</option>
                            <option value="unreviewed">Belum Ditinjau</option>
                            <option value="revision_required">Perlu Revisi</option>
                            <option value="approved">Disetujui</option>
                            <option value="outdated">Review Ulang</option>
                        </select>

                        <div className="flex gap-2">
                            <button type="submit" className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-teal-800">
                                Terapkan
                            </button>
                            <button
                                type="button"
                                onClick={resetFilter}
                                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                            >
                                <RefreshCcw className="size-3.5" />
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section className="report-print-surface sim-surface overflow-hidden rounded-2xl">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 className="text-lg font-black text-slate-900">Rekap RPS Dosen</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {rows.length} RPS sesuai filter · {stats.unreviewed} belum ditinjau · {stats.draft} draft.
                            </p>
                        </div>
                        <div className="hidden text-right text-xs text-slate-500 print:block">
                            SiMatRPS — Program Studi Matematika FMIPA Universitas Sulawesi Barat
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="report-print-table w-full min-w-[1280px] text-sm print:min-w-0">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/70 text-left text-[11px] font-black uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3">Dosen</th>
                                    <th className="px-4 py-3">Mata Kuliah</th>
                                    <th className="px-4 py-3">Periode</th>
                                    <th className="px-4 py-3">Progres</th>
                                    <th className="px-4 py-3">Validator OBE</th>
                                    <th className="px-4 py-3">Status RPS</th>
                                    <th className="px-4 py-3">Tindak Lanjut</th>
                                    <th className="px-4 py-3">Terakhir Diubah</th>
                                    <th className="report-print-hide px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((row) => (
                                    <tr key={row.id} className="align-top transition hover:bg-teal-50/30">
                                        <td className="px-4 py-4">
                                            <div className="font-bold text-slate-900">{row.owner.name}</div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                {row.owner.academic_title || (row.owner.nidn ? `NIDN ${row.owner.nidn}` : row.owner.email)}
                                            </div>
                                            {row.owner.academic_title && row.owner.nidn && (
                                                <div className="mt-0.5 text-xs text-slate-400">NIDN {row.owner.nidn}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="font-semibold text-slate-900">{row.course.name}</div>
                                            <div className="mt-1 text-xs text-slate-500">{row.course.code} · {formatCredits(row.course.credits)} SKS</div>
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            <div className="font-semibold">{row.academic_year}</div>
                                            <div className="mt-1 text-xs">{row.academic_semester}</div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="font-bold text-slate-800">{row.progress.filled_weeks}/16 pertemuan</div>
                                            <div className="mt-1 text-xs text-slate-500">Bobot asesmen {formatWeight(row.progress.assessment_weight)}%</div>
                                        </td>
                                        <td className="px-4 py-4"><ObeBadge percent={row.progress.obe_percent} /></td>
                                        <td className="px-4 py-4"><RpsStatusBadge status={row.status} /></td>
                                        <td className="px-4 py-4">
                                            <ReviewBadge review={row.review} />
                                            {row.review.note && (
                                                <div className="mt-2 max-w-xs text-xs leading-5 text-slate-500">{row.review.note}</div>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-xs text-slate-600">{formatDate(row.updated_at)}</td>
                                        <td className="report-print-hide px-4 py-4 text-right">
                                            <Link
                                                href={`/admin/rps/${row.id}/review`}
                                                className="inline-flex items-center rounded-xl border border-teal-200 bg-white px-3 py-2 text-xs font-black text-teal-700 transition hover:bg-teal-50"
                                            >
                                                Buka Review
                                            </Link>
                                        </td>
                                    </tr>
                                ))}

                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-6 py-14 text-center text-sm text-slate-500">
                                            Tidak ada RPS yang sesuai dengan filter rekap.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    helper,
    tone,
}: {
    icon: typeof Users;
    label: string;
    value: number;
    helper: string;
    tone: 'slate' | 'teal' | 'emerald' | 'amber';
}) {
    const tones = {
        slate: 'bg-slate-100 text-slate-700',
        teal: 'bg-teal-50 text-teal-700',
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
    };

    return (
        <div className="report-print-surface sim-surface rounded-2xl p-4">
            <div className={`inline-flex rounded-xl p-2.5 ${tones[tone]}`}><Icon className="size-5" /></div>
            <div className="mt-4 text-2xl font-black text-slate-950">{value}</div>
            <div className="mt-1 text-sm font-bold text-slate-700">{label}</div>
            <div className="mt-1 text-xs text-slate-500">{helper}</div>
        </div>
    );
}

function ObeBadge({ percent }: { percent?: number | null }) {
    if (percent === null || percent === undefined) {
        return <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-black text-slate-500">BELUM DICEK</span>;
    }

    const valid = percent === 100;
    return (
        <span className={`inline-flex min-w-20 justify-center rounded-full border px-2.5 py-1 text-xs font-black ${valid ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>
            OBE {percent}%
        </span>
    );
}

function RpsStatusBadge({ status }: { status: string }) {
    const normalized = String(status || 'draft').toLowerCase();
    const base = 'inline-flex rounded-full border px-2.5 py-1 text-xs font-black';
    if (normalized === 'final') return <span className={`${base} border-emerald-200 bg-emerald-50 text-emerald-700`}>FINAL</span>;
    if (normalized === 'obe_valid') return <span className={`${base} border-teal-200 bg-teal-50 text-teal-700`}>OBE VALID</span>;
    return <span className={`${base} border-amber-200 bg-amber-50 text-amber-700`}>DRAFT</span>;
}

function ReviewBadge({ review }: { review: RpsReportRow['review'] }) {
    const base = 'inline-flex rounded-full border px-2.5 py-1 text-xs font-black';
    if (review.outdated) {
        return <span className={`${base} border-violet-200 bg-violet-50 text-violet-700`}>REVIEW ULANG</span>;
    }
    if (review.status === 'approved') {
        return <span className={`${base} border-emerald-200 bg-emerald-50 text-emerald-700`}>DISETUJUI</span>;
    }
    if (review.status === 'revision_required') {
        return <span className={`${base} border-rose-200 bg-rose-50 text-rose-700`}>PERLU REVISI</span>;
    }
    return <span className={`${base} border-slate-200 bg-slate-50 text-slate-500`}>BELUM DITINJAU</span>;
}

function formatWeight(value: number) {
    return Number.isInteger(Number(value)) ? Number(value).toFixed(0) : Number(value).toFixed(2);
}

function formatCredits(value: number) {
    return Number.isInteger(Number(value)) ? Number(value).toFixed(0) : Number(value).toFixed(1);
}

function formatDate(value: string) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

RpsReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard Admin', href: '/dashboard' },
        { title: 'Rekap & Ekspor', href: '/admin/rekap' },
    ],
};
