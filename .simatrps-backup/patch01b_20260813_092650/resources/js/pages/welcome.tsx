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

            <main className="min-h-screen bg-gradient-to-br from-[#EAF9FC] via-[#F7FCFE] to-white text-slate-900">
                <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 lg:px-8">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-cyan-700 text-lg font-black text-white shadow-sm">
                                S
                            </div>
                            <div>
                                <div className="font-bold text-slate-950">SiMatRPS</div>
                                <div className="text-xs text-slate-500">RPS Berbasis OBE</div>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? '/dashboard' : '/login'}
                            className="rounded-xl border border-cyan-200 bg-white px-4 py-2 text-sm font-semibold text-cyan-900 shadow-sm transition hover:bg-cyan-50"
                        >
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </header>

                    <section className="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[1.08fr_.92fr]">
                        <div>
                            <div className="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/80 px-3 py-1 text-xs font-medium text-cyan-900">
                                <Sparkles className="size-3.5" />
                                Program Studi Matematika · FMIPA UNSULBAR
                            </div>

                            <h1 className="mt-6 max-w-3xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                                Susun RPS berbasis OBE dengan alur yang terstruktur.
                            </h1>

                            <p className="mt-6 max-w-2xl text-base leading-7 text-slate-600 lg:text-lg">
                                SiMatRPS menghubungkan master kurikulum, CPL, CPMK,
                                Sub-CPMK, rencana pembelajaran dan asesmen dalam satu
                                workspace. AI memberi rekomendasi, sementara keputusan
                                akademik tetap berada pada dosen.
                            </p>

                            <div className="mt-8">
                                <Link
                                    href={auth.user ? '/dashboard' : '/login'}
                                    className="inline-flex items-center gap-2 rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-cyan-800"
                                >
                                    {auth.user ? 'Buka Dashboard' : 'Masuk ke SiMatRPS'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </div>

                        <div className="rounded-3xl border border-cyan-100 bg-white/80 p-5 shadow-xl shadow-cyan-100/60 backdrop-blur">
                            <div className="rounded-2xl border border-cyan-100 bg-[#F8FDFE] p-6">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-xl bg-cyan-100 p-3 text-cyan-800">
                                        <BookOpenCheck className="size-6" />
                                    </div>
                                    <div>
                                        <p className="font-semibold text-slate-900">Alur OBE SiMatRPS</p>
                                        <p className="text-xs text-slate-500">Kurikulum sebagai sumber akademik</p>
                                    </div>
                                </div>

                                <div className="mt-6 space-y-3">
                                    {[
                                        'Pilih mata kuliah dari kurikulum resmi',
                                        'Gunakan CPL dan CPMK master sebagai batas konteks',
                                        'Susun Sub-CPMK dan pembelajaran 16 minggu',
                                        'Validasi asesmen dan keterlacakan OBE',
                                        'Ekspor RPS ke format dokumen',
                                    ].map((item) => (
                                        <div
                                            key={item}
                                            className="flex items-start gap-3 rounded-xl border border-cyan-100 bg-white p-3 shadow-sm"
                                        >
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-cyan-700" />
                                            <span className="text-sm text-slate-700">{item}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <footer className="border-t border-cyan-100 pt-5 text-xs text-slate-500">
                        SiMatRPS · Platform Penyusunan RPS Berbasis OBE
                    </footer>
                </div>
            </main>
        </>
    );
}
