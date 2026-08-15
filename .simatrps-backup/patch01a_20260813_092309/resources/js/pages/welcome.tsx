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
            <main className="min-h-screen bg-slate-950 text-white">
                <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-6 lg:px-8">
                    <header className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-white text-lg font-black text-slate-950">
                                S
                            </div>
                            <div>
                                <div className="font-bold">SiMatRPS</div>
                                <div className="text-xs text-slate-400">RPS Berbasis OBE</div>
                            </div>
                        </div>

                        <Link href={auth.user ? '/dashboard' : '/login'} className="rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm font-semibold">
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </header>

                    <section className="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[1.08fr_.92fr]">
                        <div>
                            <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                <Sparkles className="size-3.5" />
                                Program Studi Matematika · FMIPA UNSULBAR
                            </div>

                            <h1 className="mt-6 max-w-3xl text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                                Susun RPS berbasis OBE dengan alur yang terstruktur.
                            </h1>

                            <p className="mt-6 max-w-2xl text-base leading-7 text-slate-300 lg:text-lg">
                                SiMatRPS menghubungkan master kurikulum, CPL, CPMK,
                                Sub-CPMK, rencana pembelajaran dan asesmen dalam satu
                                workspace. AI memberi rekomendasi, sementara keputusan
                                akademik tetap berada pada dosen.
                            </p>

                            <div className="mt-8">
                                <Link href={auth.user ? '/dashboard' : '/login'} className="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-slate-950">
                                    {auth.user ? 'Buka Dashboard' : 'Masuk ke SiMatRPS'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </div>

                        <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-5">
                            <div className="rounded-2xl border border-white/10 bg-slate-900/80 p-6">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-xl bg-emerald-400/10 p-3 text-emerald-300">
                                        <BookOpenCheck className="size-6" />
                                    </div>
                                    <div>
                                        <p className="font-semibold">Alur OBE SiMatRPS</p>
                                        <p className="text-xs text-slate-400">Kurikulum sebagai sumber akademik</p>
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
                                        <div key={item} className="flex items-start gap-3 rounded-xl border border-white/5 bg-white/[0.03] p-3">
                                            <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-400" />
                                            <span className="text-sm text-slate-300">{item}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <footer className="border-t border-white/10 pt-5 text-xs text-slate-500">
                        SiMatRPS · Platform Penyusunan RPS Berbasis OBE
                    </footer>
                </div>
            </main>
        </>
    );
}
