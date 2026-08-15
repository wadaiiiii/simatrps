import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpenCheck, CheckCircle2, Sparkles } from 'lucide-react';

type WelcomeProps = {
    auth: {
        user: null | { name: string };
    };
};

export default function Welcome() {
    const { auth } = usePage<WelcomeProps>().props;

    return (
        <>
            <Head title="SiMatRPS" />

            <main className="min-h-screen overflow-hidden bg-[#F7FBFC] text-slate-950">
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -left-32 -top-32 size-[28rem] rounded-full bg-teal-100/60 blur-3xl" />
                    <div className="absolute right-0 top-0 size-[24rem] rounded-full bg-sky-100/55 blur-3xl" />
                </div>

                <div className="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 lg:px-8">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="relative flex size-11 items-center justify-center rounded-2xl bg-[#008080] text-lg font-black text-white shadow-lg shadow-teal-900/10">
                                S
                                <span className="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-white bg-amber-400" />
                            </div>
                            <div>
                                <div className="font-extrabold tracking-tight">SiMatRPS</div>
                                <div className="text-xs font-medium text-teal-700/80">RPS Berbasis OBE</div>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? '/dashboard' : '/login'}
                            className="rounded-xl border border-teal-200 bg-white/80 px-4 py-2 text-sm font-bold text-teal-800 shadow-sm backdrop-blur transition hover:border-teal-300 hover:bg-white"
                        >
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </header>

                    <section className="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[1.05fr_.95fr]">
                        <div>
                            <div className="sim-badge inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold">
                                <Sparkles className="size-3.5 text-amber-500" />
                                Program Studi Matematika · FMIPA UNSULBAR
                            </div>

                            <h1 className="mt-6 max-w-3xl text-5xl font-black tracking-[-0.04em] text-slate-950 sm:text-6xl lg:text-7xl">
                                SiMatRPS
                            </h1>

                            <p className="mt-6 max-w-2xl text-base leading-7 text-slate-600 lg:text-lg">
                                Aplikasi penyusunan Rencana Pembelajaran Semester (RPS)
                                berbasis Outcome-Based Education (OBE) secara terstruktur.
                            </p>

                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <Link
                                    href={auth.user ? '/dashboard' : '/login'}
                                    className="inline-flex items-center gap-2 rounded-xl bg-[#008080] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-teal-900/10 transition hover:-translate-y-0.5 hover:bg-[#006D6D]"
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
                            <div className="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-teal-100/80 via-cyan-50 to-amber-50 blur-xl" />
                            <div className="sim-surface relative rounded-[28px] p-5">
                                <div className="rounded-2xl border border-teal-100 bg-gradient-to-br from-white to-teal-50/50 p-6">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-xl bg-teal-100 p-3 text-teal-700">
                                            <BookOpenCheck className="size-6" />
                                        </div>
                                        <div>
                                            <p className="font-bold text-slate-900">Alur Kerja SiMatRPS</p>
                                            <p className="text-xs text-slate-500">
                                                Empat langkah praktis dari kurikulum hingga dokumen
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 space-y-3">
                                        {[
                                            {
                                                title: 'Pilih MK',
                                                text: 'Ambil mata kuliah dari kurikulum.',
                                            },
                                            {
                                                title: 'Set Konteks',
                                                text: 'Terapkan standar CPL dan CPMK.',
                                            },
                                            {
                                                title: 'Rancang Kuliah',
                                                text: 'Susun rencana 16 pertemuan.',
                                            },
                                            {
                                                title: 'Cetak Dokumen',
                                                text: 'Validasi asesmen lalu ekspor RPS.',
                                            },
                                        ].map((step, index) => (
                                            <div
                                                key={step.title}
                                                className="flex items-start gap-3 rounded-xl border border-slate-100 bg-white/90 p-3 shadow-sm"
                                            >
                                                <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-teal-50">
                                                    <CheckCircle2 className="size-4 text-teal-700" />
                                                </div>
                                                <div className="flex flex-1 items-start justify-between gap-3">
                                                    <div>
                                                        <div className="text-sm font-bold text-slate-900">
                                                            {step.title}
                                                        </div>
                                                        <div className="mt-0.5 text-sm text-slate-500">
                                                            {step.text}
                                                        </div>
                                                    </div>
                                                    <span className="pt-0.5 text-[10px] font-bold text-slate-300">
                                                        0{index + 1}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="sim-accent-line mt-4 h-1.5 rounded-full" />
                            </div>
                        </div>
                    </section>

                    <footer className="flex flex-col gap-2 border-t border-teal-100 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                        <span>SiMatRPS · Platform Penyusunan RPS Berbasis OBE</span>
                        <span>Matematika · FMIPA UNSULBAR</span>
                    </footer>
                </div>
            </main>
        </>
    );
}
