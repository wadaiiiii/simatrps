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
        <div className="rounded-2xl border bg-card p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="mt-2 text-3xl font-bold tracking-tight">{value}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{helper}</p>
                </div>
                <div className="rounded-xl bg-primary/10 p-2.5 text-primary">
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

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <section className="relative overflow-hidden rounded-3xl bg-slate-950 p-7 text-white md:p-9 dark:bg-slate-900">
                    <div className="absolute -right-20 -top-24 size-64 rounded-full bg-white/5" />
                    <div className="relative max-w-3xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                            <Sparkles className="size-3.5" />
                            Platform Penyusunan RPS Berbasis OBE
                        </div>

                        <h1 className="text-2xl font-bold tracking-tight md:text-4xl">
                            Selamat datang di SiMatRPS
                        </h1>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-300 md:text-base">
                            Halo, {user.name}. Anda masuk sebagai {roleLabel}. Susun RPS
                            dari master kurikulum resmi dan validasi keterkaitan OBE.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <Link href="/rps/baru" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950">
                                <FilePlus2 className="size-4" />
                                Buat RPS Baru
                            </Link>
                            <Link href="/rps" className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white">
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

                <section className="rounded-2xl border bg-card">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">RPS Terbaru</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Dokumen terakhir yang Anda kerjakan akan muncul di sini.
                        </p>
                    </div>
                    <div className="flex min-h-52 flex-col items-center justify-center p-6 text-center">
                        <Files className="size-8 text-muted-foreground/60" />
                        <p className="mt-4 font-medium">Belum ada RPS</p>
                        <p className="mt-1 max-w-md text-sm text-muted-foreground">
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
