import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpenCheck, CheckCircle2, FileText, Layers3, ListChecks, Target } from 'lucide-react';

type WelcomeProps = {
    auth: {
        user: null | { name: string };
    };
};

const steps = [
    {
        title: 'Pilih MK',
        text: 'Ambil mata kuliah dari kurikulum.',
        icon: BookOpenCheck,
    },
    {
        title: 'Set Konteks',
        text: 'Terapkan standar CPL dan CPMK.',
        icon: Target,
    },
    {
        title: 'Rancang Kuliah',
        text: 'Susun rencana 16 pertemuan.',
        icon: Layers3,
    },
    {
        title: 'Cetak Dokumen',
        text: 'Validasi asesmen lalu ekspor RPS.',
        icon: FileText,
    },
];

export default function Welcome() {
    const { auth } = usePage<WelcomeProps>().props;

    return (
        <>
            <Head title="SiMatRPS" />

            <main className="min-h-screen overflow-hidden bg-transparent text-slate-950">
                <div className="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 lg:px-8">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="relative flex size-11 items-center justify-center rounded-2xl bg-[#7A0F1F] text-lg font-black text-white shadow-lg shadow-rose-900/10">
                                S
                                <span className="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-white bg-[#F2C46D]" />
                            </div>
                            <div>
                                <div className="font-extrabold tracking-tight">SiMatRPS</div>
                                <div className="text-xs font-medium text-[#A12042]/80">RPS Berbasis OBE</div>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? '/dashboard' : '/login'}
                            className="rounded-xl border border-rose-200 bg-white/85 px-4 py-2 text-sm font-bold text-[#7A0F1F] shadow-sm backdrop-blur transition hover:border-rose-300 hover:bg-white"
                        >
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </header>

                    <section className="grid flex-1 items-center gap-12 py-14 lg:grid-cols-[1.02fr_.98fr] lg:py-16">
                        <div>
                            <div className="sim-gradient-chip inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold">
                                <ListChecks className="size-3.5 text-[#A12042]" />
                                Platform RPS Berbasis OBE
                            </div>

                            <h1 className="mt-6 max-w-3xl text-4xl font-black tracking-[-0.04em] text-slate-950 sm:text-5xl lg:text-6xl">
                                <span className="text-[#7A0F1F]">SiMatRPS:</span>
                                <span className="mt-2 block leading-[1.04]">
                                    <span className="italic text-[#A12042]">Workspace</span> praktis untuk menyusun
                                    RPS berbasis OBE secara
                                    <span className="block bg-gradient-to-r from-[#7A0F1F] via-[#A12042] to-[#C15B7F] bg-clip-text text-transparent">
                                        terstruktur dan terarah.
                                    </span>
                                </span>
                            </h1>

                            <p className="mt-6 max-w-2xl text-base leading-8 text-slate-600 lg:text-lg">
                                SiMatRPS membantu dosen menyusun RPS lebih cepat dan rapi
                                dengan memanfaatkan kurikulum, CPL, CPMK, rencana
                                perkuliahan, asesmen, dan ekspor dokumen dalam satu alur
                                kerja.
                            </p>

                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Link
                                    href={auth.user ? '/dashboard' : '/login'}
                                    className="sim-button-primary inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition"
                                >
                                    {auth.user ? 'Buka Dashboard' : 'Masuk ke SiMatRPS'}
                                    <ArrowRight className="size-4" />
                                </Link>

                                <span className="text-xs font-medium text-slate-400">
                                    Kurikulum sebagai sumber akademik utama
                                </span>
                            </div>
                        </div>

                        <div className="relative">
                            <div className="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-rose-100/80 via-pink-50 to-amber-50 blur-xl" />
                            <div className="sim-surface relative rounded-[28px] p-5">
                                <div className="sim-gradient-brand rounded-2xl p-[1px]">
                                    <div className="rounded-2xl bg-white/92 p-6">
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-xl bg-rose-50 p-3 text-[#A12042]">
                                                <BookOpenCheck className="size-6" />
                                            </div>
                                            <div>
                                                <p className="font-bold text-slate-900">Alur Kerja SiMatRPS</p>
                                                <p className="text-xs text-slate-500">
                                                    Susun RPS secara bertahap hingga siap digunakan.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="mt-6 space-y-3">
                                            {steps.map((step, index) => {
                                                const Icon = step.icon;
                                                return (
                                                    <div
                                                        key={step.title}
                                                        className="sim-step-card flex items-start gap-3 rounded-xl p-3.5"
                                                    >
                                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-[#A12042]">
                                                            <Icon className="size-5" />
                                                        </div>

                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <p className="font-semibold text-slate-900">
                                                                        {step.title}
                                                                    </p>
                                                                    <p className="mt-0.5 text-sm text-slate-500">
                                                                        {step.text}
                                                                    </p>
                                                                </div>
                                                                <div className="sim-step-number flex size-7 items-center justify-center rounded-full text-[11px] font-bold">
                                                                    0{index + 1}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>

                                <div className="sim-gradient-line mt-4 h-1.5 rounded-full" />
                            </div>
                        </div>
                    </section>

                    <footer className="flex flex-col gap-2 border-t border-rose-100 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                        <span>SiMatRPS · Workspace penyusunan RPS berbasis OBE</span>
                        <span>Matematika · FMIPA UNSULBAR</span>
                    </footer>
                </div>
            </main>
        </>
    );
}
