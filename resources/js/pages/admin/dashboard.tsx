import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    BookOpenCheck,
    ChartNoAxesCombined,
    CheckCircle2,
    Clock3,
    FileText,
    Search,
    Users,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

type Stats = {
    lecturers_active: number;
    lecturers_started: number;
    lecturers_not_started: number;
    rps_total: number;
    rps_draft: number;
    rps_obe_valid: number;
    rps_final: number;
};

type RpsRow = {
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
        system_code: string;
        official_code?: string | null;
        credits: number;
        semester_recommended?: number | null;
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

type PaginatedRows = {
    data: RpsRow[];
    current_page: number;
    last_page: number;
    from?: number | null;
    to?: number | null;
    total: number;
    prev_page_url?: string | null;
    next_page_url?: string | null;
};

type Filters = {
    q: string;
    status: string;
    academic_year: string;
    academic_semester: string;
};

export default function AdminDashboard({
    stats,
    rpsRows,
    filters,
    academicYears,
}: {
    stats: Stats;
    rpsRows: PaginatedRows;
    filters: Filters;
    academicYears: string[];
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [academicYear, setAcademicYear] = useState(filters.academic_year ?? '');
    const [academicSemester, setAcademicSemester] = useState(filters.academic_semester ?? '');

    const submitFilter = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/dashboard',
            {
                q: q || undefined,
                status: status || undefined,
                academic_year: academicYear || undefined,
                academic_semester: academicSemester || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const resetFilter = () => {
        setQ('');
        setStatus('');
        setAcademicYear('');
        setAcademicSemester('');
        router.get('/dashboard', {}, { preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Dashboard Admin" />

            <div className="space-y-6 p-4 md:p-6">
                <section className="rounded-3xl border border-cyan-100 bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-6 text-white shadow-sm">
                    <div className="max-w-3xl">
                        <div className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-200">Dashboard Admin</div>
                        <h1 className="mt-2 text-2xl font-black tracking-tight md:text-3xl">Monitoring Penyusunan RPS</h1>
                        <p className="mt-2 text-sm leading-6 text-cyan-50/80">
                            Pantau aktivitas dosen, progres pengisian, Validator OBE, status final, dan tindak lanjut review RPS dari satu halaman.
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        icon={Users}
                        label="Dosen Aktif"
                        value={stats.lecturers_active}
                        helper={`${stats.lecturers_started} sudah mulai RPS`}
                        tone="sky"
                    />
                    <StatCard
                        icon={BookOpenCheck}
                        label="Dosen Mulai Mengisi"
                        value={stats.lecturers_started}
                        helper={`${stats.lecturers_not_started} belum membuat RPS`}
                        tone="teal"
                    />
                    <StatCard
                        icon={FileText}
                        label="Total RPS"
                        value={stats.rps_total}
                        helper={`${stats.rps_draft} masih draft`}
                        tone="slate"
                    />
                    <StatCard
                        icon={CheckCircle2}
                        label="OBE Valid"
                        value={stats.rps_obe_valid}
                        helper="Termasuk RPS Final"
                        tone="cyan"
                    />
                    <StatCard
                        icon={CheckCircle2}
                        label="RPS Final"
                        value={stats.rps_final}
                        helper="Siap digunakan"
                        tone="emerald"
                    />
                </section>

                <section className="sim-surface rounded-2xl p-5">
                    <div className="flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 className="text-lg font-black text-slate-900">Daftar RPS Dosen</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {rpsRows.total} RPS tercatat. Buka Review RPS untuk meninjau dokumen secara read-only dan menyimpan tindak lanjut.
                            </p>
                        </div>
                    </div>

                    <form onSubmit={submitFilter} className="mt-5 grid gap-3 lg:grid-cols-[1.4fr_0.7fr_0.8fr_0.7fr_auto]">
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
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="obe_valid">OBE Valid</option>
                            <option value="final">Final</option>
                        </select>

                        <select
                            value={academicYear}
                            onChange={(event) => setAcademicYear(event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-teal-400"
                        >
                            <option value="">Semua tahun</option>
                            {academicYears.map((year) => (
                                <option key={year} value={year}>{year}</option>
                            ))}
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

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-teal-800"
                            >
                                Terapkan
                            </button>
                            <button
                                type="button"
                                onClick={resetFilter}
                                className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                            >
                                Reset
                            </button>
                        </div>
                    </form>

                    <div className="mt-5 overflow-x-auto">
                        <table className="w-full min-w-[1420px] text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th className="px-3 py-3">Dosen</th>
                                    <th className="px-3 py-3">Mata Kuliah</th>
                                    <th className="px-3 py-3">Periode</th>
                                    <th className="px-3 py-3">Pertemuan Terisi</th>
                                    <th className="px-3 py-3">Bobot Asesmen</th>
                                    <th className="px-3 py-3">Validator OBE</th>
                                    <th className="px-3 py-3">Status</th>
                                    <th className="px-3 py-3">Tindak Lanjut</th>
                                    <th className="px-3 py-3">Terakhir Diubah</th>
                                    <th className="px-3 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rpsRows.data.map((row) => {
                                    const weekPercent = Math.min(100, Math.round((row.progress.filled_weeks / 16) * 100));
                                    return (
                                        <tr key={row.id} className="align-top transition hover:bg-teal-50/30">
                                            <td className="px-3 py-4">
                                                <Link
                                                    href={`/admin/pengguna/${row.owner.id}/monitoring`}
                                                    className="font-bold text-slate-900 transition hover:text-teal-700"
                                                >
                                                    {row.owner.name}
                                                </Link>
                                                <div className="mt-0.5 text-xs text-slate-500">
                                                    {row.owner.academic_title || row.owner.nidn || row.owner.email}
                                                </div>
                                                {row.owner.academic_title && row.owner.nidn && (
                                                    <div className="mt-0.5 text-xs text-slate-400">NIDN {row.owner.nidn}</div>
                                                )}
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="font-semibold text-slate-900">{row.course.name}</div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {row.course.official_code || row.course.system_code} · {row.course.credits} SKS
                                                </div>
                                            </td>
                                            <td className="px-3 py-4 text-slate-600">
                                                <div className="font-semibold">{row.academic_year}</div>
                                                <div className="mt-1 text-xs">{row.academic_semester}</div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="flex items-center justify-between gap-3 text-xs font-bold text-slate-700">
                                                    <span>{row.progress.filled_weeks}/16</span>
                                                    <span>{weekPercent}%</span>
                                                </div>
                                                <div className="mt-2 h-2 w-28 overflow-hidden rounded-full bg-slate-100">
                                                    <div
                                                        className="h-full rounded-full bg-teal-600"
                                                        style={{ width: `${weekPercent}%` }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <span className={weightClass(row.progress.assessment_weight)}>
                                                    {formatWeight(row.progress.assessment_weight)}%
                                                </span>
                                            </td>
                                            <td className="px-3 py-4">
                                                <ObeBadge percent={row.progress.obe_percent} />
                                            </td>
                                            <td className="px-3 py-4">
                                                <span className={statusClass(row.status)}>{statusLabel(row.status)}</span>
                                            </td>
                                            <td className="px-3 py-4">
                                                <ReviewBadge status={row.review?.status} outdated={Boolean(row.review?.outdated)} />
                                                {row.review?.reviewed_at && (
                                                    <div className="mt-1 text-xs text-slate-400">{formatDate(row.review.reviewed_at)}</div>
                                                )}
                                            </td>
                                            <td className="px-3 py-4 text-slate-600">
                                                <div className="inline-flex items-center gap-1.5">
                                                    <Clock3 className="size-3.5 text-slate-400" />
                                                    {formatDate(row.updated_at)}
                                                </div>
                                            </td>
                                            <td className="px-3 py-4 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={`/admin/pengguna/${row.owner.id}/monitoring`}
                                                        className="inline-flex items-center gap-1.5 rounded-xl border border-teal-200 bg-white px-3 py-2 text-xs font-bold text-teal-700 transition hover:bg-teal-50"
                                                    >
                                                        <ChartNoAxesCombined className="size-3.5" />
                                                        Pantau Dosen
                                                    </Link>
                                                    <Link
                                                        href={`/admin/rps/${row.id}/review`}
                                                        className="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-teal-800"
                                                    >
                                                        Review RPS
                                                        <ArrowUpRight className="size-3.5" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}

                                {rpsRows.data.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-12 text-center text-sm text-slate-500">
                                            Tidak ada RPS yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-slate-500">
                            Menampilkan {rpsRows.from ?? 0}–{rpsRows.to ?? 0} dari {rpsRows.total} RPS
                        </div>
                        <div className="flex items-center gap-2">
                            {rpsRows.prev_page_url ? (
                                <Link
                                    href={rpsRows.prev_page_url}
                                    preserveScroll
                                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Sebelumnya
                                </Link>
                            ) : (
                                <span className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 font-semibold text-slate-300">Sebelumnya</span>
                            )}
                            <span className="px-2 text-xs font-bold text-slate-500">
                                {rpsRows.current_page}/{rpsRows.last_page}
                            </span>
                            {rpsRows.next_page_url ? (
                                <Link
                                    href={rpsRows.next_page_url}
                                    preserveScroll
                                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Berikutnya
                                </Link>
                            ) : (
                                <span className="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 font-semibold text-slate-300">Berikutnya</span>
                            )}
                        </div>
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
    tone: 'sky' | 'teal' | 'slate' | 'cyan' | 'emerald';
}) {
    const tones = {
        sky: 'bg-sky-50 text-sky-700',
        teal: 'bg-teal-50 text-teal-700',
        slate: 'bg-slate-100 text-slate-700',
        cyan: 'bg-cyan-50 text-cyan-700',
        emerald: 'bg-emerald-50 text-emerald-700',
    };

    return (
        <div className="sim-surface rounded-2xl p-4">
            <div className={`inline-flex rounded-xl p-2.5 ${tones[tone]}`}>
                <Icon className="size-5" />
            </div>
            <div className="mt-4 text-2xl font-black text-slate-950">{value}</div>
            <div className="mt-1 text-sm font-bold text-slate-700">{label}</div>
            <div className="mt-1 text-xs text-slate-500">{helper}</div>
        </div>
    );
}

function ObeBadge({ percent }: { percent?: number | null }) {
    if (percent === null || percent === undefined) {
        return (
            <span className="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-black text-slate-500">
                BELUM DICEK
            </span>
        );
    }

    const valid = percent === 100;
    return (
        <span
            className={`inline-flex min-w-20 justify-center rounded-full border px-2.5 py-1 text-xs font-black ${
                valid
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : 'border-amber-200 bg-amber-50 text-amber-700'
            }`}
        >
            OBE {percent}%
        </span>
    );
}

function ReviewBadge({ status, outdated }: { status?: string | null; outdated: boolean }) {
    const base = 'inline-flex rounded-full border px-2.5 py-1 text-xs font-black';
    if (outdated) return <span className={`${base} border-amber-200 bg-amber-50 text-amber-700`}>REVIEW ULANG</span>;
    if (status === 'approved') return <span className={`${base} border-emerald-200 bg-emerald-50 text-emerald-700`}>DISETUJUI</span>;
    if (status === 'revision_required') return <span className={`${base} border-rose-200 bg-rose-50 text-rose-700`}>PERLU REVISI</span>;
    return <span className={`${base} border-slate-200 bg-slate-50 text-slate-500`}>BELUM DITINJAU</span>;
}

function statusLabel(status: string) {
    const normalized = String(status || 'draft').toLowerCase();
    if (normalized === 'final') return 'FINAL';
    if (normalized === 'obe_valid') return 'OBE VALID';
    return 'DRAFT';
}

function statusClass(status: string) {
    const base = 'inline-flex rounded-full border px-2.5 py-1 text-xs font-black';
    const normalized = String(status || 'draft').toLowerCase();
    if (normalized === 'final') return `${base} border-emerald-200 bg-emerald-50 text-emerald-700`;
    if (normalized === 'obe_valid') return `${base} border-teal-200 bg-teal-50 text-teal-700`;
    return `${base} border-amber-200 bg-amber-50 text-amber-700`;
}

function weightClass(weight: number) {
    const base = 'inline-flex min-w-16 justify-center rounded-full border px-2.5 py-1 text-xs font-black';
    if (Math.abs(Number(weight) - 100) < 0.01) {
        return `${base} border-emerald-200 bg-emerald-50 text-emerald-700`;
    }
    if (Number(weight) > 100) {
        return `${base} border-rose-200 bg-rose-50 text-rose-700`;
    }
    return `${base} border-amber-200 bg-amber-50 text-amber-700`;
}

function formatWeight(weight: number) {
    return Number.isInteger(Number(weight)) ? Number(weight).toFixed(0) : Number(weight).toFixed(2);
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

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard Admin', href: '/dashboard' }],
};