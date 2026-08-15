import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BookOpenCheck,
    CheckCircle2,
    FilePlus2,
    Files,
    LibraryBig,
    Sparkles,
} from 'lucide-react';

type DashboardProps = {
    auth: {
        user: {
            name: string;
            email: string;
            role?: string;
        };
    };
    stats: {
        rps: number;
        draft: number;
        valid_obe: number;
        curriculum_year: number | null;
    };
    recentRps: Array<{
        id: string;
        academic_year: string;
        academic_semester: string;
        status: string;
        course_name: string;
        system_code: string;
        official_code?: string | null;
    }>;
};

export default function Dashboard() {
    const { auth, stats, recentRps } = usePage<DashboardProps>().props;
    const user = auth.user;
    const roleLabel = user.role === 'admin' ? 'Admin' : 'Dosen';

    const statItems = [
        { label: 'RPS Saya', value: stats.rps, helper: 'Dokumen yang Anda kelola', icon: Files, tone: 'bg-sky-50 text-sky-700 border-sky-100' },
        { label: 'Draft', value: stats.draft, helper: 'Sedang disusun', icon: BookOpenCheck, tone: 'bg-amber-50 text-amber-700 border-amber-100' },
        { label: 'Valid OBE', value: stats.valid_obe, helper: 'Lolos validasi', icon: CheckCircle2, tone: 'bg-emerald-50 text-emerald-700 border-emerald-100' },
        { label: 'Kurikulum Aktif', value: stats.curriculum_year ?? '-', helper: 'Master akademik aktif', icon: LibraryBig, tone: 'bg-teal-50 text-teal-700 border-teal-100' },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-transparent p-4 md:p-6">
                <section className="sim-gradient-brand relative overflow-hidden rounded-[28px] p-7 text-white shadow-xl shadow-teal-900/10 md:p-9">
                    <div className="absolute -right-16 -top-20 size-64 rounded-full border border-white/15 bg-white/5" />
                    <div className="absolute inset-y-0 left-0 w-1.5 bg-amber-300" />

                    <div className="relative max-w-3xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur">
                            <Sparkles className="size-3.5 text-amber-300" />
                            Platform Penyusunan RPS Berbasis OBE
                        </div>

                        <h1 className="text-2xl font-extrabold tracking-tight md:text-4xl">
                            Selamat datang di SiMatRPS
                        </h1>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-teal-50 md:text-base">
                            Halo, {user.name}. Anda masuk sebagai {roleLabel}. Master
                            Kurikulum Matematika 2025 sudah terhubung ke generator RPS.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <Link href="/rps/baru" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-teal-800 shadow-sm">
                                <FilePlus2 className="size-4" />
                                Buat RPS Baru
                            </Link>
                            <Link href="/rps" className="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white">
                                <Files className="size-4" />
                                RPS Saya
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statItems.map((item) => {
                        const Icon = item.icon;
                        return (
                            <div key={item.label} className="sim-surface sim-card-hover rounded-2xl p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-medium text-slate-500">{item.label}</p>
                                        <p className="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">{item.value}</p>
                                        <p className="mt-1 text-xs text-slate-500">{item.helper}</p>
                                    </div>
                                    <div className={`rounded-xl border p-2.5 ${item.tone}`}>
                                        <Icon className="size-5" />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </section>

                <section className="sim-surface overflow-hidden rounded-2xl">
                    <div className="flex items-center justify-between border-b border-slate-100 p-5">
                        <div>
                            <h2 className="font-bold text-slate-900">RPS Terbaru</h2>
                            <p className="mt-1 text-sm text-slate-500">Dokumen terakhir yang Anda kerjakan.</p>
                        </div>
                        <Link href="/rps" className="inline-flex items-center gap-1 text-xs font-bold text-teal-700">
                            Lihat semua <ArrowUpRight className="size-3.5" />
                        </Link>
                    </div>

                    {recentRps.length === 0 ? (
                        <div className="flex min-h-52 flex-col items-center justify-center p-6 text-center">
                            <div className="rounded-2xl bg-teal-50 p-3 text-teal-700"><Files className="size-7" /></div>
                            <p className="mt-4 font-semibold text-slate-900">Belum ada RPS</p>
                            <p className="mt-1 text-sm text-slate-500">Mulai dari menu Buat RPS.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {recentRps.map((item) => (
                                <Link key={item.id} href={`/rps/${item.id}`} className="flex items-center justify-between gap-4 p-5 transition hover:bg-teal-50/40">
                                    <div>
                                        <div className="font-semibold text-slate-900">{item.course_name}</div>
                                        <div className="mt-1 text-xs text-slate-500">
                                            {item.official_code || item.system_code} · {item.academic_year} · {item.academic_semester}
                                        </div>
                                    </div>
                                    <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                        {item.status.toUpperCase()}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
