import { Head, Link } from '@inertiajs/react';
import { BookOpenCheck, CalendarDays, CircleAlert, FileText, Network, Sparkles } from 'lucide-react';

type Props = {
    rps: any;
    version: any;
    cpls: Array<{ code: string; description: string }>;
    cpmks: Array<{ id: string; code: string; description: string; sequence_no: number; source_type: string }>;
    weeks: Array<{ week_number: number; is_exam: boolean; exam_type?: string | null }>;
    syllabus?: { description?: string | null } | null;
    needsAiCpmk: boolean;
};

export default function RpsShow({ rps, version, cpls, cpmks, weeks, syllabus, needsAiCpmk }: Props) {
    return (
        <>
            <Head title={`RPS ${rps.course_name}`} />

            <div className="p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Workspace RPS</div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">{rps.course_name}</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {rps.official_code || rps.system_code} · {rps.credits} SKS · Semester {rps.semester_recommended} · {rps.academic_year} {rps.academic_semester}
                        </p>
                    </div>
                    <span className="w-fit rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                        DRAFT · v{Number(version.version_no).toFixed(0)}
                    </span>
                </div>

                <div className="mt-6 grid gap-4 md:grid-cols-3">
                    <div className="sim-surface rounded-2xl p-5">
                        <Network className="size-5 text-teal-700" />
                        <div className="mt-3 text-sm text-slate-500">CPL Resmi</div>
                        <div className="mt-1 text-3xl font-bold text-slate-900">{cpls.length}</div>
                    </div>
                    <div className="sim-surface rounded-2xl p-5">
                        <BookOpenCheck className="size-5 text-teal-700" />
                        <div className="mt-3 text-sm text-slate-500">CPMK Kerja</div>
                        <div className="mt-1 text-3xl font-bold text-slate-900">{cpmks.length}</div>
                    </div>
                    <div className="sim-surface rounded-2xl p-5">
                        <CalendarDays className="size-5 text-teal-700" />
                        <div className="mt-3 text-sm text-slate-500">Rencana Mingguan</div>
                        <div className="mt-1 text-3xl font-bold text-slate-900">{weeks.length}</div>
                    </div>
                </div>

                {needsAiCpmk && (
                    <div className="mt-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        <Sparkles className="mt-0.5 size-5 shrink-0" />
                        Mata kuliah ini belum memiliki CPMK master. Pada Patch AI berikutnya, sistem dapat memberi rekomendasi CPMK dalam batas CPL resmi, tetapi dosen tetap yang menerima, mengedit, atau menolaknya.
                    </div>
                )}

                <div className="mt-6 grid gap-5 xl:grid-cols-[.85fr_1.15fr]">
                    <div className="space-y-5">
                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-bold text-slate-900">CPL yang Dibebankan pada MK</h2>
                            <div className="mt-4 space-y-3">
                                {cpls.map((cpl) => (
                                    <div key={cpl.code} className="rounded-xl bg-teal-50/55 p-4">
                                        <div className="font-bold text-teal-800">{cpl.code}</div>
                                        <div className="mt-1 text-sm leading-6 text-slate-600">{cpl.description}</div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="sim-surface rounded-2xl p-5">
                            <h2 className="font-bold text-slate-900">Deskripsi Mata Kuliah</h2>
                            <p className="mt-3 text-sm leading-6 text-slate-600">
                                {syllabus?.description || 'Deskripsi master belum tersedia.'}
                            </p>
                        </section>
                    </div>

                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="font-bold text-slate-900">CPMK</h2>
                                <p className="mt-1 text-xs text-slate-500">Disalin dari master kurikulum; belum ada mapping CPMK→CPL otomatis.</p>
                            </div>
                            <FileText className="size-5 text-teal-700" />
                        </div>

                        {cpmks.length === 0 ? (
                            <div className="mt-5 rounded-xl border border-dashed border-amber-200 bg-amber-50/50 p-5 text-sm text-amber-800">
                                CPMK belum tersedia.
                            </div>
                        ) : (
                            <div className="mt-5 space-y-3">
                                {cpmks.map((cpmk) => (
                                    <div key={cpmk.id} className="rounded-xl border border-slate-100 bg-white/65 p-4">
                                        <div className="font-bold text-teal-800">{cpmk.code}</div>
                                        <div className="mt-1 text-sm leading-6 text-slate-600">{cpmk.description}</div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>

                <section className="sim-surface mt-6 rounded-2xl p-5">
                    <div>
                        <h2 className="font-bold text-slate-900">Rencana 16 Pertemuan</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Shell minggu sudah dibuat. UTS ditempatkan pada minggu 8 dan UAS pada minggu 16.
                        </p>
                    </div>

                    <div className="mt-5 grid grid-cols-4 gap-2 sm:grid-cols-8 lg:grid-cols-16">
                        {weeks.map((week) => (
                            <div
                                key={week.week_number}
                                className={`rounded-xl border p-3 text-center ${
                                    week.is_exam
                                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                                        : 'border-slate-100 bg-white/65 text-slate-700'
                                }`}
                            >
                                <div className="text-xs font-bold">{week.week_number}</div>
                                <div className="mt-1 text-[10px]">{week.exam_type || 'Kuliah'}</div>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="mt-6 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                    <CircleAlert className="mt-0.5 size-5 shrink-0" />
                    Patch 02 berhenti pada master kurikulum, draft/versioning, CPMK kerja, dan shell 16 minggu. Editor Sub-CPMK, pemetaan CPMK→CPL, asesmen, RTM, validator OBE, dan AI akan dibangun pada tahap berikutnya.
                </div>

                <div className="mt-6">
                    <Link href="/rps" className="text-sm font-bold text-teal-700">← Kembali ke RPS Saya</Link>
                </div>
            </div>
        </>
    );
}

RpsShow.layout = {
    breadcrumbs: [{ title: 'RPS Saya', href: '/rps' }, { title: 'Workspace', href: '#' }],
};
