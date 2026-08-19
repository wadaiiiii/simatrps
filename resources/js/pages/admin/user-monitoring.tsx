import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpenCheck,
    CheckCircle2,
    Clock3,
    ExternalLink,
    FileText,
    ShieldCheck,
    UserRound,
} from 'lucide-react';

type Lecturer = {
    id: number;
    name: string;
    academic_title?: string | null;
    nidn?: string | null;
    email: string;
    is_active: boolean;
};

type Summary = {
    total: number;
    final: number;
    draft: number;
    obe_valid: number;
    review_approved: number;
};

type RpsItem = {
    id: string;
    course_code: string;
    course_name: string;
    credits: number;
    academic_year: string;
    academic_semester: string;
    status: string;
    version_status: string;
    version_no: number;
    finalized_at?: string | null;
    updated_at?: string | null;
    weeks_ready: number;
    weeks_total: number;
    completion_percent: number;
    assessment_count: number;
    assessment_weight_total: number;
    obe_percent?: number | null;
    obe_validated_at?: string | null;
    review_status?: string | null;
    review_note?: string | null;
    reviewed_at?: string | null;
    reviewer_name?: string | null;
    review_outdated: boolean;
};

export default function Page({
    lecturer,
    summary,
    rpsItems,
}: {
    lecturer: Lecturer;
    summary: Summary;
    rpsItems: RpsItem[];
}) {
    return (
        <>
            <Head title={`Monitoring RPS - ${lecturer.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <Link
                        href="/admin/pengguna"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-teal-700"
                    >
                        <ArrowLeft className="size-4" />
                        Kembali ke Kelola Pengguna
                    </Link>
                </div>

                <section className="rounded-3xl border border-cyan-100 bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-6 text-white shadow-sm">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="rounded-2xl bg-white/10 p-3 text-cyan-100 ring-1 ring-white/15">
                                <UserRound className="size-7" />
                            </div>
                            <div>
                                <div className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-200">
                                    Monitoring per Dosen
                                </div>
                                <h1 className="mt-2 text-2xl font-black tracking-tight md:text-3xl">
                                    {lecturer.name}
                                </h1>
                                <p className="mt-1 text-sm text-cyan-50/80">
                                    {lecturer.academic_title || 'Dosen'}
                                    {lecturer.nidn ? ` · NIDN ${lecturer.nidn}` : ''}
                                    {' · '}
                                    {lecturer.email}
                                </p>
                                <div className="mt-3">
                                    <span
                                        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ${
                                            lecturer.is_active
                                                ? 'bg-emerald-400/15 text-emerald-100 ring-1 ring-emerald-300/25'
                                                : 'bg-rose-400/15 text-rose-100 ring-1 ring-rose-300/25'
                                        }`}
                                    >
                                        {lecturer.is_active ? (
                                            <CheckCircle2 className="size-3.5" />
                                        ) : (
                                            <ShieldCheck className="size-3.5" />
                                        )}
                                        {lecturer.is_active ? 'Akun aktif' : 'Akun nonaktif'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                            <MiniStat label="Total RPS" value={summary.total} />
                            <MiniStat label="Final" value={summary.final} />
                            <MiniStat label="Draft" value={summary.draft} />
                            <MiniStat label="OBE 100%" value={summary.obe_valid} />
                            <MiniStat label="Disetujui" value={summary.review_approved} />
                        </div>
                    </div>
                </section>

                <section className="sim-surface rounded-2xl p-5">
                    <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-teal-700">
                                <BookOpenCheck className="size-5" />
                                <span className="text-xs font-bold uppercase tracking-[0.18em]">Rekap RPS</span>
                            </div>
                            <h2 className="mt-2 text-xl font-black text-slate-900">RPS yang disusun dosen</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Review dibuka pada tampilan khusus read-only. Keputusan dan catatan tindak lanjut tersimpan terpisah dari isi RPS.
                            </p>
                        </div>
                        <div className="text-sm font-semibold text-slate-500">{rpsItems.length} dokumen</div>
                    </div>

                    {rpsItems.length === 0 ? (
                        <div className="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center">
                            <FileText className="mx-auto size-8 text-slate-300" />
                            <h3 className="mt-3 font-bold text-slate-700">Belum ada RPS</h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Dosen ini belum memiliki RPS pada SiMatRPS.
                            </p>
                        </div>
                    ) : (
                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full min-w-[1280px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <th className="px-3 py-3">Mata Kuliah</th>
                                        <th className="px-3 py-3">Periode</th>
                                        <th className="px-3 py-3">Progres Pertemuan</th>
                                        <th className="px-3 py-3">Asesmen</th>
                                        <th className="px-3 py-3">Validator OBE</th>
                                        <th className="px-3 py-3">Status RPS</th>
                                        <th className="px-3 py-3">Tindak Lanjut</th>
                                        <th className="px-3 py-3">Diperbarui</th>
                                        <th className="px-3 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rpsItems.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-slate-100 align-top last:border-0 hover:bg-slate-50/70"
                                        >
                                            <td className="px-3 py-4">
                                                <div className="font-bold text-slate-900">{item.course_name}</div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {item.course_code} · {formatCredits(item.credits)} SKS · v{item.version_no}
                                                </div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="font-semibold text-slate-700">{item.academic_year}</div>
                                                <div className="mt-1 text-xs text-slate-500">{formatSemester(item.academic_semester)}</div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="flex items-center justify-between gap-3 text-xs">
                                                    <span className="font-bold text-slate-700">
                                                        {item.weeks_ready}/{item.weeks_total || 16} pekan
                                                    </span>
                                                    <span className="font-semibold text-slate-500">{item.completion_percent}%</span>
                                                </div>
                                                <div className="mt-2 h-2 w-40 overflow-hidden rounded-full bg-slate-100">
                                                    <div
                                                        className="h-full rounded-full bg-teal-600"
                                                        style={{ width: `${Math.min(100, Math.max(0, item.completion_percent))}%` }}
                                                    />
                                                </div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="font-bold text-slate-800">{formatWeight(item.assessment_weight_total)}%</div>
                                                <div className="mt-1 text-xs text-slate-500">{item.assessment_count} komponen</div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <ObeBadge percent={item.obe_percent} />
                                                <div className="mt-1 text-xs text-slate-400">
                                                    {item.obe_validated_at
                                                        ? `Cek ${formatDate(item.obe_validated_at)}`
                                                        : 'Belum ada validasi tersimpan'}
                                                </div>
                                            </td>
                                            <td className="px-3 py-4">
                                                <StatusBadge status={item.status} />
                                                {item.finalized_at && (
                                                    <div className="mt-1 text-xs text-slate-400">
                                                        Final {formatDate(item.finalized_at)}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-4">
                                                <ReviewBadge status={item.review_status} outdated={item.review_outdated} />
                                                {item.reviewed_at && (
                                                    <div className="mt-1 max-w-44 text-xs text-slate-400">
                                                        {item.reviewer_name || 'Admin'} · {formatDate(item.reviewed_at)}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-4">
                                                <div className="inline-flex items-center gap-1.5 text-slate-600">
                                                    <Clock3 className="size-3.5 text-slate-400" />
                                                    {formatDate(item.updated_at)}
                                                </div>
                                            </td>
                                            <td className="px-3 py-4 text-right">
                                                <Link
                                                    href={`/admin/rps/${item.id}/review`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-white px-3 py-2 text-xs font-bold text-teal-700 transition hover:bg-teal-50"
                                                >
                                                    Review RPS
                                                    <ExternalLink className="size-3.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function MiniStat({ label, value }: { label: string; value: number }) {
    return (
        <div className="min-w-24 rounded-2xl bg-white/10 px-3 py-3 ring-1 ring-white/10">
            <div className="text-xl font-black text-white">{value}</div>
            <div className="mt-0.5 text-[11px] font-semibold text-cyan-100/75">{label}</div>
        </div>
    );
}

function ObeBadge({ percent }: { percent?: number | null }) {
    if (percent === null || percent === undefined) {
        return (
            <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                Belum divalidasi
            </span>
        );
    }

    const valid = percent === 100;
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${
                valid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'
            }`}
        >
            OBE {percent}%
        </span>
    );
}

function StatusBadge({ status }: { status: string }) {
    const final = status.toLowerCase() === 'final';
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${
                final ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700'
            }`}
        >
            {final ? 'Final' : 'Draft'}
        </span>
    );
}

function ReviewBadge({ status, outdated }: { status?: string | null; outdated: boolean }) {
    if (outdated) {
        return (
            <span className="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">
                Review ulang
            </span>
        );
    }

    if (status === 'approved') {
        return (
            <span className="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                Disetujui
            </span>
        );
    }

    if (status === 'revision_required') {
        return (
            <span className="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">
                Perlu revisi
            </span>
        );
    }

    return (
        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
            Belum ditinjau
        </span>
    );
}

function formatSemester(value: string) {
    const normalized = value.toLowerCase();
    if (normalized.includes('ganjil') || normalized === 'odd') return 'Semester Ganjil';
    if (normalized.includes('genap') || normalized === 'even') return 'Semester Genap';
    return value;
}

function formatCredits(value: number) {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function formatWeight(value: number) {
    return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function formatDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}