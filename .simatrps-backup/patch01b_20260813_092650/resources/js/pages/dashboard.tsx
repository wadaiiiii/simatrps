import { Head, Link, usePage } from '@inertiajs/react';
import {
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
};

function StatCard({
    label,
    value,
    helper,
    icon: Icon,
}: {
    label: string;
    value: string;
    helper: string;
    icon: typeof Files;
}) {
    return (
        <div className="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm shadow-sky-100/60">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-slate-500">{label}</p>
                    <p className="mt-2 text-3xl font-bold tracking-tight text-slate-900">{value}</p>
                    <p className="mt-1 text-xs text-slate-500">{helper}</p>
                </div>
                <div className="rounded-xl bg-cyan-50 p-2.5 text-cyan-700">
                    <Icon className="size-5" />
                </div>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { auth } = usePage<DashboardProps>().props;
    const user = auth.user;
    const roleLabel = user.role === 'admin' ? 'Admin' : 'Dosen';

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-[#F4FBFD] p-4 md:p-6">
                <section className="relative overflow-hidden rounded-3xl border border-cyan-100 bg-gradient-to-br from-[#CDEFF7] via-[#E2F7FB] to-[#F7FCFE] p-7 text-slate-900 shadow-sm md:p-9">
                    <div className="absolute -right-20 -top-24 size-64 rounded-full bg-white/50" />
                    <div className="absolute -bottom-28 right-28 size-56 rounded-full bg-cyan-200/30" />

                    <div className="relative max-w-3xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/60 px-3 py-1 text-xs font-medium text-cyan-900 backdrop-blur">
                            <Sparkles className="size-3.5" />
                            Platform Penyusunan RPS Berbasis OBE
                        </div>

                        <h1 className="text-2xl font-bold tracking-tight md:text-4xl">
                            Selamat datang di SiMatRPS
                        </h1>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-700 md:text-base">
                            Halo, {user.name}. Anda masuk sebagai {roleLabel}. Susun RPS
                            dari master kurikulum resmi dan validasi keterkaitan OBE.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <Link
                                href="/rps/baru"
                                className="inline-flex items-center gap-2 rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-800"
                            >
                                <FilePlus2 className="size-4" />
                                Buat RPS Baru
                            </Link>

                            <Link
                                href="/rps"
                                className="inline-flex items-center gap-2 rounded-xl border border-cyan-200 bg-white/70 px-4 py-2.5 text-sm font-semibold text-cyan-900 transition hover:bg-white"
                            >
                                <Files className="size-4" />
                                RPS Saya
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="RPS Saya" value="0" helper="Belum ada dokumen" icon={Files} />
                    <StatCard label="Draft" value="0" helper="Sedang disusun" icon={BookOpenCheck} />
                    <StatCard label="Valid OBE" value="0" helper="Lolos validasi" icon={CheckCircle2} />
                    <StatCard label="Kurikulum Aktif" value="2025" helper="Master pada Patch 02" icon={LibraryBig} />
                </section>

                <section className="rounded-2xl border border-sky-100 bg-white shadow-sm shadow-sky-100/50">
                    <div className="border-b border-sky-100 p-5">
                        <h2 className="font-semibold text-slate-900">RPS Terbaru</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Dokumen terakhir yang Anda kerjakan akan muncul di sini.
                        </p>
                    </div>

                    <div className="flex min-h-52 flex-col items-center justify-center p-6 text-center">
                        <div className="rounded-2xl bg-cyan-50 p-3">
                            <Files className="size-7 text-cyan-600" />
                        </div>
                        <p className="mt-4 font-medium text-slate-900">Belum ada RPS</p>
                        <p className="mt-1 max-w-md text-sm text-slate-500">
                            Master kurikulum dan engine RPS akan diaktifkan pada tahap berikutnya.
                        </p>
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
