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
};

const statItems = [
    {
        label: 'RPS Saya',
        value: '0',
        helper: 'Belum ada dokumen',
        icon: Files,
        tone: 'bg-rose-50 text-rose-700 border-rose-100',
    },
    {
        label: 'Draft',
        value: '0',
        helper: 'Sedang disusun',
        icon: BookOpenCheck,
        tone: 'bg-amber-50 text-amber-700 border-amber-100',
    },
    {
        label: 'Valid OBE',
        value: '0',
        helper: 'Lolos validasi',
        icon: CheckCircle2,
        tone: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    },
    {
        label: 'Kurikulum Aktif',
        value: '2025',
        helper: 'Master pada Patch 02',
        icon: LibraryBig,
        tone: 'bg-pink-50 text-pink-700 border-pink-100',
    },
];

export default function Dashboard() {
    const { auth } = usePage<DashboardProps>().props;
    const user = auth.user;
    const roleLabel = user.role === 'admin' ? 'Admin' : 'Dosen';

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto bg-transparent p-4 md:p-6">
                <section className="sim-gradient-brand relative overflow-hidden rounded-[28px] p-7 text-white shadow-xl shadow-rose-900/10 md:p-9">
                    <div className="absolute -right-16 -top-20 size-64 rounded-full border border-white/15 bg-white/5" />
                    <div className="absolute -bottom-24 right-24 size-52 rounded-full border border-amber-100/15 bg-amber-200/10" />
                    <div className="absolute inset-y-0 left-0 w-1.5 bg-[#F2C46D]" />

                    <div className="relative max-w-3xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white/90 backdrop-blur">
                            <Sparkles className="size-3.5 text-[#F2C46D]" />
                            Workspace RPS Berbasis OBE
                        </div>

                        <h1 className="text-2xl font-extrabold tracking-tight md:text-4xl">
                            Selamat datang di SiMatRPS
                        </h1>

                        <p className="mt-3 max-w-2xl text-sm leading-6 text-rose-50 md:text-base">
                            Halo, {user.name}. Anda masuk sebagai {roleLabel}. Gunakan
                            workspace ini untuk menyusun RPS berbasis OBE secara
                            terstruktur dan terarah.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <Link
                                href="/rps/baru"
                                className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#7A0F1F] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <FilePlus2 className="size-4" />
                                Buat RPS Baru
                            </Link>

                            <Link
                                href="/rps"
                                className="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/15"
                            >
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
                                        <p className="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">
                                            {item.value}
                                        </p>
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

                <section className="grid gap-4 xl:grid-cols-[1.35fr_.65fr]">
                    <div className="sim-surface overflow-hidden rounded-2xl">
                        <div className="flex items-center justify-between border-b border-slate-100 p-5">
                            <div>
                                <h2 className="font-bold text-slate-900">RPS Terbaru</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Dokumen yang terakhir Anda kerjakan akan muncul di sini.
                                </p>
                            </div>
                            <Link href="/rps" className="inline-flex items-center gap-1 text-xs font-bold text-[#A12042] hover:text-[#7A0F1F]">
                                Lihat semua
                                <ArrowUpRight className="size-3.5" />
                            </Link>
                        </div>

                        <div className="flex min-h-56 flex-col items-center justify-center p-6 text-center">
                            <div className="rounded-2xl bg-rose-50 p-3 text-[#A12042]">
                                <Files className="size-7" />
                            </div>
                            <p className="mt-4 font-semibold text-slate-900">Belum ada RPS</p>
                            <p className="mt-1 max-w-md text-sm text-slate-500">
                                Master kurikulum dan engine RPS akan diaktifkan pada tahap berikutnya.
                            </p>
                        </div>
                    </div>

                    <div className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="font-bold text-slate-900">Status Sistem</h2>
                                <p className="mt-1 text-xs text-slate-500">Fondasi SiMatRPS</p>
                            </div>
                            <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                Online Lokal
                            </span>
                        </div>

                        <div className="mt-5 space-y-4 text-sm">
                            {[
                                ['Laravel 13', 'Aktif', 'text-emerald-600'],
                                ['React / Inertia', 'Aktif', 'text-emerald-600'],
                                ['Role', roleLabel, 'text-[#A12042]'],
                                ['Engine OBE', 'Tahap berikutnya', 'text-amber-600'],
                            ].map(([label, value, color]) => (
                                <div key={label} className="flex items-center justify-between gap-4">
                                    <span className="text-slate-500">{label}</span>
                                    <span className={`font-semibold ${color}`}>{value}</span>
                                </div>
                            ))}
                        </div>

                        <div className="sim-gradient-line mt-6 h-1.5 rounded-full" />
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
