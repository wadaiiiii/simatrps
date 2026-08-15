import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpenCheck, FileText, Layers3, ListChecks, Target } from 'lucide-react';

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
                            <div className="relative flex size-11 items-center justify-center rounded-2xl bg-[#163A63] text-lg font-black text-white shadow-lg shadow-blue-900/10">
                                S
                                <span className="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-[#eef5fc] bg-[#8fd0ff]" />
                            </div>
                            <div>
                                <div className="font-extrabold tracking-tight text-[#0f172a]">SiMatRPS</div>
                                <div className="text-xs font-medium text-[#1F5D99]/85">RPS Berbasis OBE</div>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? '/dashboard' : '/login'}
                            className="rounded-xl border border-blue-200 bg-[#eef5fc]/80 px-4 py-2 text-sm font-bold text-[#163A63] shadow-sm backdrop-blur transition hover:border-blue-300 hover:bg-[#f7fbff]"
                        >
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </header>

                    <section className="grid flex-1 items-center gap-14 py-14 lg:grid-cols-[1fr_.98fr] lg:py-16">
                        <div>
                            <div className="sim-gradient-chip inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold">
                                <ListChecks className="size-3.5 text-[#1F5D99]" />
                                Platform RPS Berbasis OBE
                            </div>

                            <h1 className="mt-6 max-w-3xl text-5xl font-black tracking-[-0.05em] text-[#0b132b] sm:text-6xl lg:text-7xl">
                                SiMatRPS
                            </h1>

                            <div className="mt-4 max-w-3xl rounded-3xl border border-blue-100/70 bg-gradient-to-br from-[#edf4fb]/90 to-[#e4eef9]/90 p-6 shadow-[0_18px_40px_rgba(31,93,153,.08)] backdrop-blur">
                                <p className="text-lg font-medium leading-8 text-slate-700 md:text-xl">
                                    <span className="font-extrabold text-[#163A63]">SiMatRPS:</span>{' '}
                                    Aplikasi penyusunan Rencana Pembelajaran Semester (RPS)
                                    berbasis Outcome-Based Education (OBE) secara terstruktur.
                                </p>
                            </div>

                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Link
                                    href={auth.user ? '/dashboard' : '/login'}
                                    className="sim-button-primary inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition"
                                >
                                    {auth.user ? 'Buka Dashboard' : 'Masuk ke SiMatRPS'}
                                    <ArrowRight className="size-4" />
                                </Link>

                                <span className="text-xs font-medium text-slate-500">
                                    Kurikulum sebagai sumber akademik utama
                                </span>
                            </div>
                        </div>

                        <div className="relative">
                            <div className="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-blue-100/70 via-sky-50 to-cyan-100/55 blur-2xl" />
                            <div className="sim-3d-shell p-7 pt-8">
                                <div className="sim-3d-panel p-6">
                                    <div className="flex items-center gap-4">
                                        <div className="sim-icon-3d flex size-16 items-center justify-center rounded-2xl text-[#1F5D99]">
                                            <BookOpenCheck className="size-8" />
                                        </div>
                                        <div>
                                            <p className="text-2xl font-extrabold tracking-tight text-slate-900">
                                                Alur Kerja SiMatRPS
                                            </p>
                                            <p className="mt-1 text-sm text-slate-500">
                                                Susun RPS secara bertahap hingga siap digunakan.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-7 space-y-4">
                                        {steps.map((step, index) => {
                                            const Icon = step.icon;
                                            return (
                                                <div
                                                    key={step.title}
                                                    className="sim-step-3d flex items-start gap-4 rounded-2xl p-4"
                                                >
                                                    <div className="sim-icon-3d flex size-14 shrink-0 items-center justify-center rounded-2xl text-[#1F5D99]">
                                                        <Icon className="size-6" />
                                                    </div>

                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start justify-between gap-4">
                                                            <div>
                                                                <p className="text-2xl font-bold tracking-tight text-slate-900">
                                                                    {step.title}
                                                                </p>
                                                                <p className="mt-1.5 text-lg text-slate-500">
                                                                    {step.text}
                                                                </p>
                                                            </div>
                                                            <div className="sim-num-3d flex size-10 items-center justify-center rounded-full text-sm font-bold">
                                                                0{index + 1}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="sim-gradient-line mt-6 h-2 rounded-full" />
                            </div>
                        </div>
                    </section>

                    <footer className="flex flex-col gap-2 border-t border-blue-100 py-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <span>SiMatRPS · Aplikasi penyusunan RPS berbasis OBE</span>
                        <span>Matematika · FMIPA UNSULBAR</span>
                    </footer>
                </div>
            </main>
        </>
    );
}
