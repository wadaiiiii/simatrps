import { Head, router, useForm } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    Copy,
    FilePenLine,
    FileText,
    Layers3,
    Network,
    Pencil,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';


const TEACHING_WEEKS = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];


const LEARNING_FORM_OPTIONS = [
    'Kuliah tatap muka',
    'Kuliah tatap muka (Lab)',
    'Praktikum/Laboratorium',
    'Tutorial',
    'Seminar/Diskusi',
    'Daring sinkron',
    'Daring asinkron',
    'Blended Learning',
];

const LEARNING_METHOD_OPTIONS = [
    'Small Group Discussion',
    'Problem-Based Learning',
    'Project-Based Learning',
    'Discovery Learning',
    'Self-Directed Learning',
    'Case Method / Case Study',
    'Ceramah interaktif',
    'Praktik terbimbing',
    'Demonstrasi',
    'Simulasi',
    'Live Coding',
    'Praktik mandiri',
];


type NoticeKind = 'success' | 'error' | 'info';

function notify(kind: NoticeKind, message: string) {
    window.dispatchEvent(new CustomEvent('simatrps:notify', {
        detail: { kind, message },
    }));
}

function firstError(errors: Record<string, any>) {
    const first = Object.values(errors ?? {}).flat()[0];
    return first ? String(first) : 'Aksi gagal diproses. Periksa kembali isian.';
}

function safeText(value: any, fallback = '-') {
    if (value === null || value === undefined || value === '') return fallback;
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return String(value);
    if (Array.isArray(value)) return value.map((item) => safeText(item, '')).filter(Boolean).join(', ') || fallback;
    try {
        return JSON.stringify(value);
    } catch {
        return fallback;
    }
}

function safeList(value: any): any[] {
    return Array.isArray(value) ? value : [];
}

function actionOptions(message: string, afterSuccess?: () => void) {
    return {
        preserveScroll: true,
        onSuccess: () => {
            notify('success', message);
            afterSuccess?.();
        },
        onError: (errors: Record<string, any>) => {
            notify('error', firstError(errors));
        },
    };
}

function ActionNotifications() {
    const [notice, setNotice] = useState<{ kind: NoticeKind; message: string } | null>(null);

    useEffect(() => {
        let timer: number | undefined;

        const handler = (event: Event) => {
            const custom = event as CustomEvent<{ kind: NoticeKind; message: string }>;
            setNotice(custom.detail);

            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(() => setNotice(null), 3200);
        };

        window.addEventListener('simatrps:notify', handler);

        return () => {
            window.removeEventListener('simatrps:notify', handler);
            if (timer) window.clearTimeout(timer);
        };
    }, []);

    if (!notice) return null;

    const tone = notice.kind === 'success'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : notice.kind === 'error'
          ? 'border-rose-200 bg-rose-50 text-rose-800'
          : 'border-sky-200 bg-sky-50 text-sky-800';

    return (
        <div className={`fixed right-5 top-5 z-[100] max-w-md rounded-2xl border px-4 py-3 text-sm font-semibold shadow-xl ${tone}`}>
            {notice.message}
        </div>
    );
}

export default function RpsShow(props: any) {
    const {
        rps,
        cpls = [],
        allCpls = [],
        officialCplIds = [],
        additionalCplIds = [],
        cplScopeStats = { curriculum: 0, additional: 0, available: 8, scope_total: 0 },
        cpmks = [],
        subCpmks = [],
        materials = [],
        bibliography = [],
        courseSummary = { description: '', prerequisite: '', lecturer: '' },
        weeks = [],
        assessments = [],
        tasks = [],
        progress = { percent: 0, checks: [], assessment_weight_total: 0 },
        ai = { configured: false, provider: 'groq', model: 'openai/gpt-oss-20b', fallbacks: [] },
        aiSuggestions = [],
    } = props;

    const [aiInstruction, setAiInstruction] = useState('');
    const [aiBusyType, setAiBusyType] = useState<string | null>(null);
    const [aiBusyWeek, setAiBusyWeek] = useState<number | null>(null);
    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);

    const serverMappings = useMemo(
        () => Object.fromEntries(
            cpmks.map((c: any) => [
                c.id,
                Array.isArray(c.cpl_ids) ? [...c.cpl_ids].sort() : [],
            ]),
        ),
        [cpmks],
    );

    const serverMappingSignature = useMemo(
        () => JSON.stringify(serverMappings),
        [serverMappings],
    );

    const mappingForm = useForm({
        mappings: serverMappings,
    });

    useEffect(() => {
        // POST/PUT Inertia visits preserve component state by default.
        // Without this synchronization, the database mapping can be correct
        // while the CPL circles still display the old local form state.
        mappingForm.setData('mappings', serverMappings);
        mappingForm.clearErrors();
    }, [serverMappingSignature]);

    const newCpmkForm = useForm({
        description: '',
        bloom_level: '',
    });

    const subForm = useForm({
        rps_cpmk_id: cpmks[0]?.id ?? '',
        description: '',
        bloom_level: '',
    });

    const materialForm = useForm({
        rps_sub_cpmk_id: '',
        title: '',
        description: '',
    });

    const assessmentForm = useForm({
        name: '',
        type: 'assignment',
        week_number: '',
        weight: '',
        description: '',
        sub_cpmk_ids: [] as string[],
    });

    const taskForm = useForm({
        assessment_id: '',
        title: '',
        type: 'assignment',
        purpose: '',
        instructions: '',
        expected_output: '',
        due_week: '',
        sub_cpmk_ids: [] as string[],
    });

    const methodForm = useForm({
        weeks: TEACHING_WEEKS,
        learning_method: 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.',
    });

    const mappedCount = useMemo(
        () => cpmks.filter((cpmk: any) => (mappingForm.data.mappings[cpmk.id] ?? []).length > 0).length,
        [cpmks, mappingForm.data.mappings],
    );

    const cpmkAiSuggestions = aiSuggestions.filter(
        (item: any) => ['cpmk_review', 'cpl_mapping'].includes(item.suggestion_type),
    );
    const subCpmkAiSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'sub_cpmk',
    );
    const materialAiSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'material_plan',
    );
    const assessmentAiSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'assessment_plan',
    );

    const toggleMapping = (cpmkId: string, cplId: string) => {
        const current = mappingForm.data.mappings[cpmkId] ?? [];
        mappingForm.setData('mappings', {
            ...mappingForm.data.mappings,
            [cpmkId]: current.includes(cplId)
                ? current.filter((id: string) => id !== cplId)
                : [...current, cplId],
        });
    };

    const toggleAssessmentSub = (id: string) => {
        const current = assessmentForm.data.sub_cpmk_ids;
        assessmentForm.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleTaskSub = (id: string) => {
        const current = taskForm.data.sub_cpmk_ids;
        taskForm.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleBatchWeek = (week: number) => {
        setSelectedBatchWeeks((current) =>
            current.includes(week)
                ? current.filter((value) => value !== week)
                : [...current, week].sort((a, b) => a - b),
        );
    };

    const generateAi = (suggestionType: string) => {
        setAiBusyType(suggestionType);
        router.post(
            `/rps/${rps.id}/ai/suggestions`,
            { suggestion_type: suggestionType, instruction: aiInstruction },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    const freshSuggestions = safeList(page?.props?.aiSuggestions);
                    const hasPending = freshSuggestions.some(
                        (item: any) => item?.suggestion_type === suggestionType,
                    );
                    const flashMessage = safeText(page?.props?.flash?.success, '');

                    if (suggestionType === 'cpmk_review' && !hasPending) {
                        notify(
                            'success',
                            flashMessage || 'Telaah CPMK selesai. CPMK saat ini sudah memadai; tidak ada perubahan substantif yang perlu diterapkan.',
                        );
                        return;
                    }

                    notify(
                        'success',
                        flashMessage || 'Rekomendasi AI berhasil dibuat dan tampil pada bagian yang sesuai.',
                    );
                },
                onError: (errors) => notify('error', firstError(errors)),
                onFinish: () => setAiBusyType(null),
            },
        );
    };

    const generateWeekAi = (weekNumber: number, overwrite = false) => {
        if (overwrite && !confirm(`Susun ulang minggu ${weekNumber} dengan AI? Isian minggu ini dapat diganti oleh hasil AI.`)) {
            return;
        }

        setAiBusyWeek(weekNumber);

        router.post(
            `/rps/${rps.id}/ai/weeks/${weekNumber}`,
            {
                instruction: aiInstruction,
                overwrite,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => notify(
                    'success',
                    overwrite
                        ? `Minggu ${weekNumber} berhasil disusun ulang dengan AI.`
                        : `Minggu ${weekNumber} berhasil dilengkapi dengan AI.`,
                ),
                onError: (errors) => notify('error', firstError(errors)),
                onFinish: () => setAiBusyWeek(null),
            },
        );
    };

    return (
        <>
            <Head title={`RPS ${rps.course_name}`} />
            <ActionNotifications />

            <div className="p-4 md:p-6">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Workspace OBE</div>
                        <h1 className="mt-1 text-2xl font-bold text-slate-900">{rps.course_name}</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {rps.official_code || rps.system_code} | {rps.credits} SKS | {rps.academic_year} {rps.academic_semester}
                        </p>
                    </div>

                    <div className="sim-surface w-full max-w-lg rounded-2xl p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Progress OBE</div>
                                <div className="mt-1 text-2xl font-extrabold text-slate-900">{progress.percent}%</div>
                            </div>
                            <button
                                type="button"
                                onClick={() => router.post(`/rps/${rps.id}/validate-obe`, {}, actionOptions('Validasi OBE selesai.'))}
                                className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                            >
                                Validasi OBE
                            </button>
                        </div>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div className="h-full rounded-full bg-gradient-to-r from-teal-700 to-cyan-400" style={{ width: `${progress.percent}%` }} />
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                            {progress.checks.map((item: any) => (
                                <div key={item.key} className="flex items-center gap-2">
                                    {item.done
                                        ? <CheckCircle2 className="size-3.5 text-emerald-600" />
                                        : <CircleAlert className="size-3.5 text-amber-500" />}
                                    <span className="text-slate-600">{item.label}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mt-6 space-y-5">
                    {/* Struktur RPS seperti dokumen */}
                    <section className="sim-surface overflow-hidden rounded-2xl border border-slate-200">
                        <div className="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 className="font-bold text-slate-900">Struktur RPS</h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    Ringkasan mengikuti susunan dokumen RPS dan menjadi dasar export PDF.
                                </p>
                            </div>
                            <div className="text-xs font-semibold text-slate-400">
                                {rps.official_code || rps.system_code} | {rps.credits} SKS
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-[980px] w-full border-collapse text-xs">
                                <tbody>
                                    <tr>
                                        <td rowSpan={4} className="w-[150px] border border-slate-300 bg-slate-50 px-3 py-3 align-top font-bold text-slate-700">
                                            Capaian Pembelajaran
                                        </td>
                                        <td className="w-[235px] border border-slate-300 px-3 py-2 font-bold text-slate-700">
                                            CPL-PRODI yang dibebankan pada MK
                                        </td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <div className="space-y-1">
                                                {cpls.map((item: any) => (
                                                    <div key={item.id}><strong>{item.code}</strong> {item.description}</div>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-300 px-3 py-2 font-bold text-slate-700">CPMK</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <div className="space-y-1">
                                                {cpmks.map((item: any) => (
                                                    <div key={item.id}><strong>{item.code}</strong> {item.description}</div>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-300 px-3 py-2 font-bold text-slate-700">Sub-CPMK</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <div className="space-y-1">
                                                {subCpmks.map((item: any) => (
                                                    <div key={item.id}><strong>{item.code}</strong> {item.description}</div>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-300 px-3 py-2 font-bold text-slate-700">Matriks Sub-CPMK → CPMK</td>
                                        <td className="border border-slate-300 p-0">
                                            <table className="w-full border-collapse text-center">
                                                <thead>
                                                    <tr className="bg-sky-50">
                                                        <th className="border border-slate-200 px-2 py-1.5 text-left">Sub-CPMK</th>
                                                        {cpmks.map((cpmk: any) => (
                                                            <th key={cpmk.id} className="border border-slate-200 px-2 py-1.5">{cpmk.code}</th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {subCpmks.map((sub: any) => (
                                                        <tr key={sub.id}>
                                                            <td className="border border-slate-200 px-2 py-1.5 text-left font-semibold">{sub.code}</td>
                                                            {cpmks.map((cpmk: any) => (
                                                                <td key={cpmk.id} className="border border-slate-200 px-2 py-1.5">
                                                                    {safeList(sub.cpmk_ids).includes(cpmk.id) ? '✓' : ''}
                                                                </td>
                                                            ))}
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-slate-300 bg-slate-50 px-3 py-2 font-bold text-slate-700">Deskripsi Singkat MK</td>
                                        <td className="border border-slate-300 px-3 py-2 leading-5">{courseSummary.description || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-slate-300 bg-slate-50 px-3 py-2 font-bold text-slate-700">Bahan Kajian / Materi Pembelajaran</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            <ol className="list-decimal space-y-0.5 pl-5">
                                                {materials.map((item: any) => <li key={item.id}>{item.title}</li>)}
                                            </ol>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-slate-300 bg-slate-50 px-3 py-2 font-bold text-slate-700">Pustaka</td>
                                        <td className="border border-slate-300 px-3 py-2">
                                            {bibliography.length > 0 ? (
                                                <div className="space-y-1">
                                                    {bibliography.map((item: any) => (
                                                        <div key={item.number}><strong>[{item.number}]</strong> {item.text}</div>
                                                    ))}
                                                </div>
                                            ) : '-'}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-slate-300 bg-slate-50 px-3 py-2 font-bold text-slate-700">Dosen Pengampu</td>
                                        <td className="border border-slate-300 px-3 py-2">{courseSummary.lecturer || '-'}</td>
                                    </tr>
                                    <tr>
                                        <td colSpan={2} className="border border-slate-300 bg-slate-50 px-3 py-2 font-bold text-slate-700">Mata Kuliah Syarat</td>
                                        <td className="border border-slate-300 px-3 py-2">{courseSummary.prerequisite || '-'}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {/* AI Assistant */}
                    <section className="sim-surface overflow-hidden rounded-2xl border border-sky-100">
                        <div className="bg-gradient-to-r from-slate-950 via-sky-950 to-teal-900 p-5 text-white">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="size-5 text-cyan-300" />
                                        <h2 className="font-bold">AI Assistant SiMatRPS</h2>
                                    </div>
                                    <p className="mt-1 max-w-3xl text-sm leading-6 text-sky-100/80">
                                        AI membaca konteks RPS yang sedang aktif dan hanya membuat rekomendasi. Data baru diterapkan setelah dosen menekan tombol Terapkan.
                                    </p>
                                </div>
                                <div className={`w-fit rounded-full px-3 py-1.5 text-xs font-bold ${
                                    ai.configured
                                        ? 'bg-emerald-400/15 text-emerald-200 ring-1 ring-emerald-300/30'
                                        : 'bg-amber-400/15 text-amber-200 ring-1 ring-amber-300/30'
                                }`}>
                                    {ai.configured
                                        ? `${String(ai.provider).toUpperCase()} | ${ai.model} | Aktif${safeList(ai.fallbacks).length ? ` | Backup: ${safeList(ai.fallbacks).map((name: any) => String(name).toUpperCase()).join(' → ')}` : ''}`
                                        : 'AI belum dikonfigurasi'}
                                </div>
                            </div>
                        </div>

                        <div className="p-5">
                            {!ai.configured ? (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    <div className="font-bold">AI belum aktif pada server.</div>
                                    <p className="mt-1 leading-6">
                                        Jalankan <code className="rounded bg-white px-1.5 py-0.5">herd php artisan simatrps:ai-config</code>, lalu tes dengan <code className="rounded bg-white px-1.5 py-0.5">herd php artisan simatrps:ai-test</code>.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <label className="block">
                                        <span className="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-400">
                                            Preferensi / Arahan untuk AI (opsional)
                                        </span>
                                        <textarea
                                            value={aiInstruction}
                                            onChange={(e) => setAiInstruction(e.target.value)}
                                            placeholder="Contoh: utamakan PBL dan praktikum Python; untuk pemetaan CPL pilih hanya yang benar-benar relevan; gunakan bahasa ringkas."
                                            className="min-h-20 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        />
                                    </label>

                                    <div className="mt-2 flex flex-col gap-2 rounded-xl border border-sky-100 bg-sky-50/60 px-3 py-2.5 text-xs leading-5 text-sky-800 sm:flex-row sm:items-center sm:justify-between">
                                        <span>
                                            Arahan ini hanya menjadi <strong>preferensi untuk permintaan AI berikutnya</strong>.
                                            Ia tidak mengubah data master dan tidak diterapkan sendiri. Kosongkan jika ingin AI bekerja dari konteks RPS tanpa instruksi tambahan.
                                        </span>
                                        {aiInstruction.trim() !== '' && (
                                            <button
                                                type="button"
                                                onClick={() => setAiInstruction('')}
                                                className="shrink-0 rounded-lg border border-sky-200 bg-white px-2.5 py-1.5 font-bold text-sky-700"
                                            >
                                                Bersihkan
                                            </button>
                                        )}
                                    </div>

                                    <div className="mt-4 rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-xs leading-5 text-slate-600">
                                        <strong>AI sekarang bersifat kontekstual.</strong> Gunakan tombol AI di bagian CPMK,
                                        Sub-CPMK, setiap minggu pembelajaran, serta Asesmen & RTM. Rencana 14 minggu tidak lagi
                                        diproses dalam satu request agar tidak memicu timeout.
                                    </div>
                                </>
                            )}

                        </div>
                    </section>

                    {/* CPMK Adaptif */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Network className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">1. CPMK RPS & Pemetaan CPL</h2>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-bold text-slate-500">{mappedCount}/{cpmks.length} CPMK terpetakan</span>
                                <button
                                    type="button"
                                    disabled={aiBusyType !== null || !ai.configured || cpmks.length === 0}
                                    onClick={() => generateAi('cpmk_review')}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100 disabled:opacity-40"
                                >
                                    <Sparkles className="size-3.5" />
                                    {aiBusyType === 'cpmk_review' ? 'Menelaah...' : 'Telaah CPMK AI'}
                                </button>
                                <button
                                    type="button"
                                    disabled={aiBusyType !== null || !ai.configured || cpmks.length === 0}
                                    onClick={() => generateAi('cpl_mapping')}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100 disabled:opacity-40"
                                >
                                    <Sparkles className="size-3.5" />
                                    {aiBusyType === 'cpl_mapping' ? 'Menganalisis...' : 'Rekomendasi Pemetaan AI'}
                                </button>
                            </div>
                        </div>


                        {cpmkAiSuggestions.length > 0 && (
                            <div className="mt-4 space-y-3">
                                {cpmkAiSuggestions.map((suggestion: any) => (
                                    <AiSuggestionCard key={suggestion.id} suggestion={suggestion} rpsId={rps.id} />
                                ))}
                            </div>
                        )}

                        <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <div className="font-bold text-slate-900">Scope CPL RPS</div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {cplScopeStats.curriculum} CPL dari kurikulum | {cplScopeStats.additional} tambahan dosen | {cplScopeStats.scope_total}/{cplScopeStats.available} CPL digunakan pada RPS.
                                    </p>
                                </div>
                                <div className="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-slate-600 shadow-sm">
                                    Total CPL Prodi: {cplScopeStats.available}
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 lg:grid-cols-2">
                                {allCpls.map((cpl: any) => {
                                    const official = officialCplIds.includes(cpl.id);
                                    const additional = additionalCplIds.includes(cpl.id);

                                    return (
                                        <div
                                            key={cpl.id}
                                            className={`rounded-xl border p-4 ${
                                                official
                                                    ? 'border-emerald-200 bg-emerald-50/65'
                                                    : additional
                                                      ? 'border-sky-200 bg-sky-50/65'
                                                      : 'border-slate-200 bg-white/75'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-extrabold text-slate-900">{cpl.code}</span>
                                                        {official && (
                                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                                Kurikulum MK
                                                            </span>
                                                        )}
                                                        {additional && (
                                                            <span className="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                                                Tambahan Dosen
                                                            </span>
                                                        )}
                                                        {!official && !additional && (
                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                                                Tersedia
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-2 text-sm leading-6 text-slate-600">{cpl.description}</p>
                                                </div>

                                                {!official && !additional && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/rps/${rps.id}/cpl-scope`,
                                                                { cpl_id: cpl.id },
                                                                actionOptions(`${cpl.code} ditambahkan ke scope RPS.`),
                                                            )
                                                        }
                                                        className="shrink-0 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100"
                                                    >
                                                        + Tambah
                                                    </button>
                                                )}

                                                {additional && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm(`Hapus ${cpl.code} dari tambahan RPS? Mapping CPMK ke CPL ini juga akan dilepas.`)) {
                                                                router.delete(
                                                                    `/rps/${rps.id}/cpl-scope/${cpl.id}`,
                                                                    actionOptions(`${cpl.code} dihapus dari tambahan RPS.`),
                                                                );
                                                            }
                                                        }}
                                                        className="shrink-0 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100"
                                                    >
                                                        Hapus
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-5 space-y-3">
                            {cpmks.map((cpmk: any) => (
                                <CpmkCard
                                    key={cpmk.id}
                                    rpsId={rps.id}
                                    cpmk={cpmk}
                                    cpls={cpls}
                                    selectedCplIds={mappingForm.data.mappings[cpmk.id] ?? []}
                                    onToggle={(cplId: string) => toggleMapping(cpmk.id, cplId)}
                                />
                            ))}
                        </div>

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                newCpmkForm.post(
                                    `/rps/${rps.id}/cpmk`,
                                    actionOptions('CPMK baru berhasil ditambahkan.', () => newCpmkForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-dashed border-teal-200 bg-teal-50/30 p-4"
                        >
                            <div className="flex items-center gap-2">
                                <Plus className="size-4 text-teal-700" />
                                <div className="font-bold text-slate-800">Tambah CPMK Dosen</div>
                            </div>
                            <div className="mt-3 grid gap-3 md:grid-cols-[1fr_150px]">
                                <textarea
                                    value={newCpmkForm.data.description}
                                    onChange={(e) => newCpmkForm.setData('description', e.target.value)}
                                    placeholder="Rumusan CPMK tambahan"
                                    className="min-h-20 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                />
                                <select
                                    value={newCpmkForm.data.bloom_level}
                                    onChange={(e) => newCpmkForm.setData('bloom_level', e.target.value)}
                                    className="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                                >
                                    <option value="">Bloom (opsional)</option>
                                    {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                </select>
                            </div>
                            <button className="mt-3 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                Tambah CPMK
                            </button>
                        </form>

                        <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <button
                                type="button"
                                onClick={() => mappingForm.put(
                                    `/rps/${rps.id}/cpmk-cpl`,
                                    actionOptions('Pemetaan CPMK → CPL berhasil disimpan.'),
                                )}
                                disabled={mappingForm.processing}
                                className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                            >
                                Simpan Semua Pemetaan CPL
                            </button>
                            <span className="text-xs leading-5 text-slate-400">
                                Gunakan tombol ini untuk perubahan manual. Pemetaan yang diterapkan dari rekomendasi AI tersimpan otomatis.
                            </span>
                        </div>
                    </section>

                    <div className="grid gap-5 xl:grid-cols-2">
                        {/* Sub CPMK */}
                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="font-bold text-slate-900">2. Sub-CPMK</h2>
                                    <p className="mt-1 text-sm text-slate-500">Turunkan CPMK menjadi capaian yang lebih spesifik.</p>
                                </div>
                                <button
                                    type="button"
                                    disabled={aiBusyType !== null || !ai.configured || cpmks.length === 0}
                                    onClick={() => generateAi('sub_cpmk')}
                                    className="inline-flex w-fit items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100 disabled:opacity-40"
                                >
                                    <Sparkles className="size-3.5" />
                                    {aiBusyType === 'sub_cpmk' ? 'Menelaah...' : 'Telaah Sub-CPMK AI'}
                                </button>
                            </div>


                        {subCpmkAiSuggestions.length > 0 && (
                            <div className="mt-4 space-y-3">
                                {subCpmkAiSuggestions.map((suggestion: any) => (
                                    <AiSuggestionCard key={suggestion.id} suggestion={suggestion} rpsId={rps.id} />
                                ))}
                            </div>
                        )}

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    subForm.post(
                                        `/rps/${rps.id}/sub-cpmk`,
                                        actionOptions('Sub-CPMK berhasil ditambahkan.', () => subForm.reset('description', 'bloom_level')),
                                    );
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-[1fr_170px]">
                                    <label>
                                        <span className="mb-1.5 block text-xs font-bold text-slate-500">CPMK Induk</span>
                                        <select
                                            value={subForm.data.rps_cpmk_id}
                                            onChange={(e) => subForm.setData('rps_cpmk_id', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        >
                                            {cpmks.map((cpmk: any) => <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>)}
                                        </select>
                                    </label>
                                    <label>
                                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Level Kognitif (Bloom)</span>
                                        <select
                                            value={subForm.data.bloom_level}
                                            onChange={(e) => subForm.setData('bloom_level', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                        >
                                            <option value="">Pilih level</option>
                                            {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                        </select>
                                    </label>
                                </div>
                                <label className="mt-3 block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Rumusan Sub-CPMK</span>
                                    <textarea
                                        value={subForm.data.description}
                                        onChange={(e) => subForm.setData('description', e.target.value)}
                                        placeholder="Contoh: Mahasiswa mampu menerapkan struktur percabangan untuk menyelesaikan masalah sederhana."
                                        className="min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                    <Plus className="size-4" /> Tambah Sub-CPMK
                                </button>
                            </form>

                            <div className="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                                Gunakan tombol <strong>Edit</strong> untuk memperbaiki rumusan, CPMK induk, atau level Bloom tanpa menghapus data.
                                Jika Sub-CPMK benar-benar dihapus, nomor yang kosong akan dipakai kembali saat menambah Sub-CPMK berikutnya.
                            </div>

                            <div className="mt-4 space-y-2">
                                {subCpmks.map((sub: any) => (
                                    <SubCpmkCard
                                        key={sub.id}
                                        rpsId={rps.id}
                                        sub={sub}
                                        cpmks={cpmks}
                                    />
                                ))}
                            </div>
                        </section>

                        {/* Materials */}
                        <section className="sim-surface rounded-2xl p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Layers3 className="size-5 text-teal-700" />
                                        <h2 className="font-bold text-slate-900">3. Bahan Kajian</h2>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Materi berasal dari bagian <strong>Silabus</strong>, bukan daftar pustaka.
                                    </p>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        disabled={aiBusyType !== null || !ai.configured || subCpmks.length === 0}
                                        onClick={() => generateAi('material_plan')}
                                        className="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100 disabled:opacity-40"
                                    >
                                        <Sparkles className="size-3.5" />
                                        {aiBusyType === 'material_plan' ? 'Menelaah...' : 'Telaah Bahan Kajian AI'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/rps/${rps.id}/materials/import-syllabus`, {}, actionOptions('Bahan kajian berhasil disinkronkan dari silabus.'))}
                                        className="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700"
                                    >
                                        Sinkronkan Silabus
                                    </button>
                                </div>
                            </div>


                        {materialAiSuggestions.length > 0 && (
                            <div className="mt-4 space-y-3">
                                {materialAiSuggestions.map((suggestion: any) => (
                                    <AiSuggestionCard key={suggestion.id} suggestion={suggestion} rpsId={rps.id} />
                                ))}
                            </div>
                        )}

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    materialForm.post(
                                        `/rps/${rps.id}/materials`,
                                        actionOptions('Bahan kajian berhasil ditambahkan.', () => materialForm.reset()),
                                    );
                                }}
                                className="mt-4 rounded-xl border border-teal-100 bg-teal-50/35 p-4"
                            >
                                <label className="block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Kaitkan ke Sub-CPMK (opsional)</span>
                                    <select
                                        value={materialForm.data.rps_sub_cpmk_id}
                                        onChange={(e) => materialForm.setData('rps_sub_cpmk_id', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Tanpa Sub-CPMK khusus</option>
                                        {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code}</option>)}
                                    </select>
                                </label>
                                <label className="mt-3 block">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Judul Bahan Kajian</span>
                                    <input
                                        value={materialForm.data.title}
                                        onChange={(e) => materialForm.setData('title', e.target.value)}
                                        placeholder="Judul bahan kajian"
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                    <Plus className="size-4" /> Tambah
                                </button>
                            </form>

                            <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
                                {materials.map((material: any) => (
                                    <div key={material.id} className="rounded-xl border border-slate-100 bg-white/60 p-3">
                                        <div className="font-semibold text-slate-900">{material.title}</div>
                                        <div className="mt-1 text-[10px] uppercase text-slate-400">{material.source_type}</div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    {/* Weekly */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">4. Rencana 16 Pertemuan</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Smart Draft mengisi beberapa komponen sekaligus. Ringkasan di bawah menunjukkan persis bagian yang sudah terisi.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/weeks/align-subcpmk`, {}, actionOptions('Alur Sub-CPMK berhasil dirapikan.'))}
                                    className="rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-xs font-bold text-violet-700"
                                >
                                    Rapikan Alur Sub-CPMK
                                </button>
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/weeks/apply-time-standard`, {}, actionOptions('Estimasi waktu sesuai SKS diterapkan.'))}
                                    className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-xs font-bold text-sky-700"
                                >
                                    Terapkan Waktu {rps.credits} SKS
                                </button>
                                <button
                                    type="button"
                                    onClick={() => router.post(
                                        `/rps/${rps.id}/weeks/normalize-references`,
                                        {},
                                        actionOptions('Pustaka mingguan dinormalisasi menjadi nomor [n].'),
                                    )}
                                    className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700"
                                >
                                    Normalisasi Pustaka [n]
                                </button>
                                <button
                                    type="button"
                                    onClick={() => router.post(`/rps/${rps.id}/smart-draft`, { mode: 'fill_empty' }, actionOptions('Smart Draft selesai mengisi bagian yang masih kosong.'))}
                                    className="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-bold text-white"
                                >
                                    Susun Otomatis | Isi Kosong
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('Susun ulang akan mengganti draft minggu kuliah yang sudah ada. Lanjutkan?')) {
                                            router.post(`/rps/${rps.id}/smart-draft`, { mode: 'overwrite' }, actionOptions('Smart Draft berhasil disusun ulang.'));
                                        }
                                    }}
                                    className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700"
                                >
                                    Susun Ulang Draft
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 rounded-xl border border-slate-100 bg-white/45 p-4">
                            <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Terapkan metode ke banyak minggu</div>
                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {TEACHING_WEEKS.map((week) => (
                                    <button
                                        key={week}
                                        type="button"
                                        onClick={() => toggleBatchWeek(week)}
                                        className={`size-8 rounded-lg text-xs font-bold ${
                                            selectedBatchWeeks.includes(week)
                                                ? 'bg-teal-700 text-white'
                                                : 'border border-slate-200 bg-white text-slate-500'
                                        }`}
                                    >
                                        {week}
                                    </button>
                                ))}
                            </div>
                            <div className="mt-3 flex flex-col gap-2 md:flex-row">
                                <input
                                    value={methodForm.data.learning_method}
                                    onChange={(e) => methodForm.setData('learning_method', e.target.value)}
                                    className="flex-1 rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                />
                                <button
                                    type="button"
                                    onClick={() => router.post(
                                        `/rps/${rps.id}/weeks/apply-method`,
                                        { weeks: selectedBatchWeeks, learning_method: methodForm.data.learning_method },
                                        actionOptions('Metode pembelajaran berhasil diterapkan ke minggu terpilih.'),
                                    )}
                                    className="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-bold text-teal-700"
                                >
                                    Terapkan
                                </button>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-2 sm:grid-cols-4 lg:grid-cols-8 xl:grid-cols-16">
                            {weeks.map((week: any) => {
                                const info = weekCompletion(week);
                                return (
                                    <button
                                        key={week.week_number}
                                        type="button"
                                        onClick={() => setOpenWeek(openWeek === week.week_number ? null : week.week_number)}
                                        className={`rounded-xl border p-3 text-center ${
                                            week.is_exam
                                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                : info.count >= 5
                                                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                  : info.count > 0
                                                    ? 'border-sky-200 bg-sky-50 text-sky-800'
                                                    : 'border-slate-200 bg-white/60 text-slate-600'
                                        }`}
                                    >
                                        <div className="text-xs font-bold">{week.week_number}</div>
                                        <div className="mt-1 text-[10px]">
                                            {week.exam_type || `${info.count}/7`}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table className="min-w-[1500px] w-full border-collapse text-xs">
                                <thead className="bg-sky-100/80 text-center font-bold text-slate-700">
                                    <tr>
                                        <th rowSpan={2} className="w-[72px] border border-slate-300 px-2 py-3">Pekan<br/>Ke-</th>
                                        <th rowSpan={2} className="w-[220px] border border-slate-300 px-2 py-3">Sub-CPMK<br/><span className="font-medium">(Kemampuan akhir yang diharapkan)</span></th>
                                        <th colSpan={2} className="border border-slate-300 px-2 py-2">Penilaian</th>
                                        <th colSpan={2} className="border border-slate-300 px-2 py-2">Bentuk Pembelajaran; Metode Pembelajaran; Penugasan; <span className="font-medium">[Estimasi Waktu]</span></th>
                                        <th rowSpan={2} className="w-[230px] border border-slate-300 px-2 py-3">Materi Pembelajaran<br/>[Pustaka]</th>
                                        <th rowSpan={2} className="w-[90px] border border-slate-300 px-2 py-3">Bobot<br/>Penilaian (%)</th>
                                        <th rowSpan={2} className="w-[90px] border border-slate-300 px-2 py-3 print:hidden">AI</th>
                                    </tr>
                                    <tr>
                                        <th className="w-[220px] border border-slate-300 px-2 py-2">Indikator</th>
                                        <th className="w-[220px] border border-slate-300 px-2 py-2">Kriteria & Bentuk</th>
                                        <th className="w-[260px] border border-slate-300 px-2 py-2">Tatap muka / Luring</th>
                                        <th className="w-[260px] border border-slate-300 px-2 py-2">Daring</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {weeks.map((week: any) => (
                                        <InlineWeekRow
                                            key={week.week_number}
                                            rpsId={rps.id}
                                            week={week}
                                            subCpmks={subCpmks}
                                            credits={rps.credits}
                                            bibliography={bibliography}
                                            aiConfigured={ai.configured}
                                            aiBusy={aiBusyWeek === week.week_number}
                                            onGenerateAi={(overwrite: boolean) => generateWeekAi(week.week_number, overwrite)}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {/* Assessment */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <BarChart3 className="size-5 text-teal-700" />
                                    <h2 className="font-bold text-slate-900">5. Asesmen & Bobot Nilai Akhir</h2>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    Bagian ini menentukan komponen nilai akhir dan membuktikan Sub-CPMK benar-benar diukur.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <div className={`rounded-xl px-4 py-2 text-sm font-extrabold ${
                                    Math.abs(Number(progress.assessment_weight_total) - 100) < 0.01
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-amber-50 text-amber-700'
                                }`}>
                                    {progress.assessment_weight_total}% / 100%
                                </div>
                                <button
                                    type="button"
                                    disabled={aiBusyType !== null || !ai.configured || subCpmks.length === 0}
                                    onClick={() => generateAi('assessment_plan')}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100 disabled:opacity-40"
                                >
                                    <Sparkles className="size-3.5" />
                                    {aiBusyType === 'assessment_plan' ? 'Menelaah...' : 'Telaah Asesmen + RTM AI'}
                                </button>
                            </div>
                        </div>


                        {assessmentAiSuggestions.length > 0 && (
                            <div className="mt-4 space-y-3">
                                {assessmentAiSuggestions.map((suggestion: any) => (
                                    <AiSuggestionCard key={suggestion.id} suggestion={suggestion} rpsId={rps.id} />
                                ))}
                            </div>
                        )}

                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                            {[
                                ['1', 'Tentukan komponen', 'Misalnya kuis, tugas, proyek/praktikum, UTS, dan UAS.'],
                                ['2', 'Atur bobot', 'Total seluruh komponen harus tepat 100%.'],
                                ['3', 'Pilih Sub-CPMK', 'Tandai Sub-CPMK mana yang diukur oleh setiap asesmen.'],
                            ].map(([num, title, text]) => (
                                <div key={num} className="rounded-xl border border-sky-100 bg-sky-50/55 p-4">
                                    <div className="flex items-center gap-2">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">{num}</span>
                                        <div className="font-bold text-slate-800">{title}</div>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-slate-600">{text}</p>
                                </div>
                            ))}
                        </div>

                        <div className="mt-4 rounded-xl border border-amber-100 bg-amber-50/60 p-4 text-sm text-amber-800">
                            UTS dan UAS sudah dibuat oleh sistem pada minggu 8 dan 16. Anda cukup mengisi
                            <strong> bobot </strong> dan memilih <strong>Sub-CPMK yang diukur</strong>.
                            Tambahkan kuis/tugas/proyek sesuai rancangan dosen.
                        </div>

                        <div className="mt-5 space-y-3">
                            {assessments.map((assessment: any) => (
                                <AssessmentCard
                                    key={assessment.id}
                                    rpsId={rps.id}
                                    assessment={assessment}
                                    subCpmks={subCpmks}
                                />
                            ))}
                        </div>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                assessmentForm.post(
                                    `/rps/${rps.id}/assessments`,
                                    actionOptions('Komponen asesmen berhasil ditambahkan.', () => assessmentForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-dashed border-teal-200 bg-teal-50/30 p-4"
                        >
                            <div className="font-bold text-slate-800">Tambah Komponen Asesmen</div>
                            <div className="mt-3 grid gap-3 md:grid-cols-4">
                                <label className="md:col-span-2">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Nama Asesmen</span>
                                    <input
                                        value={assessmentForm.data.name}
                                        onChange={(e) => assessmentForm.setData('name', e.target.value)}
                                        placeholder="Contoh: Tugas Pemrograman 1"
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                                    <select
                                        value={assessmentForm.data.type}
                                        onChange={(e) => assessmentForm.setData('type', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    >
                                        <option value="quiz">Kuis</option>
                                        <option value="assignment">Tugas</option>
                                        <option value="project">Proyek</option>
                                        <option value="presentation">Presentasi</option>
                                        <option value="practicum">Praktikum</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Bobot (%)</span>
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={assessmentForm.data.weight}
                                        onChange={(e) => assessmentForm.setData('weight', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="16"
                                        value={assessmentForm.data.week_number}
                                        onChange={(e) => assessmentForm.setData('week_number', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                    />
                                </label>
                            </div>
                            <div className="mt-3">
                                <div className="text-xs font-bold text-slate-500">Sub-CPMK yang diukur</div>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {subCpmks.map((sub: any) => (
                                        <label
                                            key={sub.id}
                                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                                assessmentForm.data.sub_cpmk_ids.includes(sub.id)
                                                    ? 'border-teal-300 bg-teal-100 text-teal-800'
                                                    : 'border-slate-200 bg-white text-slate-500'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                className="sr-only"
                                                checked={assessmentForm.data.sub_cpmk_ids.includes(sub.id)}
                                                onChange={() => toggleAssessmentSub(sub.id)}
                                            />
                                            {sub.code}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                <Plus className="size-4" /> Tambah Asesmen
                            </button>
                        </form>
                    </section>

                    {/* RTM unchanged */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">6. Rencana Tugas Mahasiswa (RTM)</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Buat RTM jika ada tugas, proyek, praktikum, atau keluaran mahasiswa yang memerlukan instruksi terstruktur.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                taskForm.post(
                                    `/rps/${rps.id}/tasks`,
                                    actionOptions('RTM berhasil ditambahkan.', () => taskForm.reset()),
                                );
                            }}
                            className="mt-5 rounded-xl border border-teal-100 bg-teal-50/30 p-4"
                        >
                            <div className="grid gap-3 md:grid-cols-3">
                                <label className="md:col-span-2">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Judul Tugas/Proyek</span>
                                    <input
                                        value={taskForm.data.title}
                                        onChange={(e) => taskForm.setData('title', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                                    <select
                                        value={taskForm.data.type}
                                        onChange={(e) => taskForm.setData('type', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="assignment">Tugas</option>
                                        <option value="project">Proyek</option>
                                        <option value="practicum">Praktikum</option>
                                        <option value="presentation">Presentasi</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Asesmen Terkait</span>
                                    <select
                                        value={taskForm.data.assessment_id}
                                        onChange={(e) => taskForm.setData('assessment_id', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Opsional</option>
                                        {assessments.map((assessment: any) => (
                                            <option key={assessment.id} value={assessment.id}>{assessment.name}</option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu Pengumpulan</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max="16"
                                        value={taskForm.data.due_week}
                                        onChange={(e) => taskForm.setData('due_week', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label>
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Luaran</span>
                                    <input
                                        value={taskForm.data.expected_output}
                                        onChange={(e) => taskForm.setData('expected_output', e.target.value)}
                                        placeholder="Laporan, kode program, presentasi..."
                                        className="w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                                <label className="md:col-span-3">
                                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Instruksi Tugas</span>
                                    <textarea
                                        value={taskForm.data.instructions}
                                        onChange={(e) => taskForm.setData('instructions', e.target.value)}
                                        className="min-h-24 w-full rounded-xl border border-slate-200 bg-white/80 px-3 py-2.5 text-sm"
                                    />
                                </label>
                            </div>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {subCpmks.map((sub: any) => (
                                    <label
                                        key={sub.id}
                                        className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                            taskForm.data.sub_cpmk_ids.includes(sub.id)
                                                ? 'border-teal-300 bg-teal-100 text-teal-800'
                                                : 'border-slate-200 bg-white text-slate-500'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={taskForm.data.sub_cpmk_ids.includes(sub.id)}
                                            onChange={() => toggleTaskSub(sub.id)}
                                        />
                                        {sub.code}
                                    </label>
                                ))}
                            </div>

                            <button className="mt-3 inline-flex items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white">
                                <Plus className="size-4" /> Tambah RTM
                            </button>
                        </form>

                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                            {tasks.map((task: any) => (
                                <TaskCard
                                    key={task.id}
                                    rpsId={rps.id}
                                    task={task}
                                    assessments={assessments}
                                    subCpmks={subCpmks}
                                />
                            ))}
                        </div>
                    </section>

                    {/* Validator */}
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-2">
                            <FileText className="size-5 text-teal-700" />
                            <h2 className="font-bold text-slate-900">7. Validator OBE</h2>
                        </div>
                        <p className="mt-1 text-sm text-slate-500">
                            Validator menunjukkan bagian yang masih perlu diperbaiki sebelum RPS difinalkan.
                        </p>

                        <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {progress.checks.map((check: any) => (
                                <div
                                    key={check.key}
                                    className={`rounded-xl border p-4 ${
                                        check.done
                                            ? 'border-emerald-100 bg-emerald-50/70'
                                            : 'border-amber-100 bg-amber-50/70'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        {check.done
                                            ? <CheckCircle2 className="size-4 text-emerald-600" />
                                            : <CircleAlert className="size-4 text-amber-600" />}
                                        <div className="font-bold text-slate-900">{check.label}</div>
                                    </div>
                                    <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}


function SubCpmkCard({ rpsId, sub, cpmks }: any) {
    const [editing, setEditing] = useState(false);
    const parentId = Array.isArray(sub.cpmk_ids) ? (sub.cpmk_ids[0] ?? '') : '';

    const form = useForm({
        rps_cpmk_id: parentId,
        description: sub.description ?? '',
        bloom_level: sub.bloom_level ?? '',
    });

    const parent = cpmks.find((c: any) => c.id === parentId);

    return (
        <div className="rounded-xl border border-slate-100 bg-white/60 p-4">
            {editing ? (
                <div>
                    <div className="grid gap-3 sm:grid-cols-[1fr_170px]">
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">CPMK Induk</span>
                            <select
                                value={form.data.rps_cpmk_id}
                                onChange={(e) => form.setData('rps_cpmk_id', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            >
                                {cpmks.map((cpmk: any) => (
                                    <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Level Kognitif (Bloom)</span>
                            <select
                                value={form.data.bloom_level}
                                onChange={(e) => form.setData('bloom_level', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            >
                                <option value="">Pilih level</option>
                                {['C1','C2','C3','C4','C5','C6'].map((level) => (
                                    <option key={level}>{level}</option>
                                ))}
                            </select>
                        </label>
                    </div>

                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Rumusan Sub-CPMK</span>
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>

                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => form.put(
                                `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                actionOptions(`${sub.code} berhasil diperbarui.`, () => setEditing(false)),
                            )}
                            className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                form.setData({
                                    rps_cpmk_id: parentId,
                                    description: sub.description ?? '',
                                    bloom_level: sub.bloom_level ?? '',
                                });
                                setEditing(false);
                            }}
                            className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            ) : (
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="font-bold text-teal-800">{sub.code}</div>
                            {sub.bloom_level && (
                                <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                    {sub.bloom_level}
                                </span>
                            )}
                        </div>
                        <div className="mt-1 text-[11px] font-semibold text-slate-400">
                            Turunan dari: {parent?.code || '-'}
                        </div>
                        <p className="mt-2 text-sm leading-6 text-slate-600">{sub.description}</p>
                    </div>

                    <div className="flex shrink-0 gap-1">
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            title="Edit Sub-CPMK"
                            className="rounded-lg p-2 text-slate-400 hover:bg-teal-50 hover:text-teal-700"
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                if (confirm(`Hapus ${sub.code}? Relasi pada minggu/asesmen yang menggunakan Sub-CPMK ini akan dilepas.`)) {
                                    router.delete(
                                        `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                        actionOptions(`${sub.code} berhasil dihapus. Nomornya dapat dipakai kembali.`),
                                    );
                                }
                            }}
                            title="Hapus Sub-CPMK"
                            className="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function CpmkCard({ rpsId, cpmk, cpls, selectedCplIds, onToggle }: any) {
    const [editing, setEditing] = useState(false);

    const form = useForm({
        description: cpmk.description ?? '',
        bloom_level: cpmk.bloom_level ?? '',
    });

    const sourceLabel =
        cpmk.source_type === 'adapted'
            ? 'Diadaptasi dosen'
            : cpmk.source_type === 'manual'
              ? 'Ditambah dosen'
              : 'Master kurikulum';

    const sourceClass =
        cpmk.source_type === 'adapted'
            ? 'bg-amber-50 text-amber-700'
            : cpmk.source_type === 'manual'
              ? 'bg-sky-50 text-sky-700'
              : 'bg-emerald-50 text-emerald-700';

    return (
        <div className="rounded-xl border border-slate-100 bg-white/55 p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="font-bold text-teal-800">{cpmk.code}</div>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${sourceClass}`}>{sourceLabel}</span>
                        {cpmk.bloom_level && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{cpmk.bloom_level}</span>}
                    </div>

                    {editing ? (
                        <div className="mt-3">
                            <textarea
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                            <div className="mt-2 flex flex-wrap gap-2">
                                <select
                                    value={form.data.bloom_level}
                                    onChange={(e) => form.setData('bloom_level', e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">Bloom (opsional)</option>
                                    {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                                </select>
                                <button
                                    type="button"
                                    onClick={() => form.put(
                                        `/rps/${rpsId}/cpmk/${cpmk.id}`,
                                        actionOptions(`${cpmk.code} berhasil diperbarui.`, () => setEditing(false)),
                                    )}
                                    className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                                >
                                    Simpan CPMK
                                </button>
                                <button type="button" onClick={() => setEditing(false)} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">
                                    Batal
                                </button>
                            </div>
                        </div>
                    ) : (
                        <p className="mt-2 text-sm leading-6 text-slate-600">{cpmk.description}</p>
                    )}

                    <div className="mt-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                Keterkaitan CPL
                            </div>
                            <div className="text-[10px] text-slate-400">
                                Narasi CPL tersedia pada Scope CPL RPS di atas.
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {cpls.map((cpl: any) => {
                                const checked = selectedCplIds.includes(cpl.id);
                                const curriculum = cpl.scope_source === 'curriculum';

                                return (
                                    <label
                                        key={cpl.id}
                                        title={cpl.description}
                                        className={`cursor-pointer rounded-xl border px-3 py-2 transition ${
                                            checked
                                                ? 'border-teal-300 bg-teal-50 text-teal-900 shadow-sm'
                                                : 'border-slate-200 bg-white/80 text-slate-500 hover:border-teal-200'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="sr-only"
                                            checked={checked}
                                            onChange={() => onToggle(cpl.id)}
                                        />
                                        <div className="flex items-center gap-2">
                                            <span className="font-extrabold">{cpl.code}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-[9px] font-bold ${
                                                curriculum
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-sky-100 text-sky-700'
                                            }`}>
                                                {curriculum ? 'Kurikulum' : 'Tambahan'}
                                            </span>
                                            <span className={`size-4 rounded-full border ${
                                                checked
                                                    ? 'border-teal-600 bg-teal-600 shadow-[inset_0_0_0_3px_white]'
                                                    : 'border-slate-300 bg-white'
                                            }`} />
                                        </div>
                                    </label>
                                );
                            })}
                        </div>

                        {cpls.length === 0 && (
                            <div className="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">
                                Belum ada CPL pada scope RPS.
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex shrink-0 gap-1">
                    <button type="button" onClick={() => setEditing(!editing)} title="Edit CPMK" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-teal-700">
                        <Pencil className="size-4" />
                    </button>
                    {cpmk.source_cpmk_id && cpmk.source_type === 'adapted' && (
                        <button
                            type="button"
                            onClick={() => router.post(`/rps/${rpsId}/cpmk/${cpmk.id}/reset`, {}, actionOptions(`${cpmk.code} dikembalikan ke master kurikulum.`))}
                            title="Kembalikan ke master"
                            className="rounded-lg p-2 text-slate-400 hover:bg-amber-50 hover:text-amber-700"
                        >
                            <RotateCcw className="size-4" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => {
                            if (confirm('Hapus CPMK dari RPS ini? Master kurikulum tidak akan berubah.')) {
                                router.delete(`/rps/${rpsId}/cpmk/${cpmk.id}`, actionOptions(`${cpmk.code} berhasil dihapus dari RPS.`));
                            }
                        }}
                        title="Hapus CPMK dari RPS"
                        className="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

function AssessmentCard({ rpsId, assessment, subCpmks }: any) {
    const form = useForm({
        name: assessment.name ?? '',
        type: assessment.type ?? 'assignment',
        week_number: assessment.week_number ?? '',
        weight: assessment.weight ?? '',
        description: assessment.description ?? '',
        sub_cpmk_ids: Array.isArray(assessment.sub_cpmk_ids) ? assessment.sub_cpmk_ids : [],
    });

    const toggleSub = (id: string) => {
        const current = form.data.sub_cpmk_ids;
        form.setData(
            'sub_cpmk_ids',
            current.includes(id) ? current.filter((x: string) => x !== id) : [...current, id],
        );
    };

    const systemExam = ['UTS', 'UAS'].includes(assessment.code);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/rps/${rpsId}/assessments/${assessment.id}`, actionOptions(`${assessment.name} berhasil disimpan.`));
            }}
            className="rounded-xl border border-slate-100 bg-white/60 p-4"
        >
            <div className="grid gap-3 md:grid-cols-[1.6fr_.8fr_.55fr_.55fr_auto]">
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Nama Asesmen</span>
                    <input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                    <select
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}
                        disabled={systemExam}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:bg-slate-50"
                    >
                        <option value="quiz">Kuis</option>
                        <option value="assignment">Tugas</option>
                        <option value="project">Proyek</option>
                        <option value="presentation">Presentasi</option>
                        <option value="practicum">Praktikum</option>
                        <option value="uts">UTS</option>
                        <option value="uas">UAS</option>
                        <option value="other">Lainnya</option>
                    </select>
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu</span>
                    <input
                        type="number"
                        min="1"
                        max="16"
                        value={form.data.week_number}
                        onChange={(e) => form.setData('week_number', e.target.value)}
                        disabled={systemExam}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm disabled:bg-slate-50"
                    />
                </label>
                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Bobot (%)</span>
                    <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={form.data.weight}
                        onChange={(e) => form.setData('weight', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>
                <div className="flex items-end gap-1">
                    <button disabled={form.processing} className="h-11 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white disabled:opacity-50">{form.processing ? 'Menyimpan...' : 'Simpan'}</button>
                    {!systemExam && (
                        <button
                            type="button"
                            onClick={() => router.delete(`/rps/${rpsId}/assessments/${assessment.id}`, actionOptions('Komponen asesmen berhasil dihapus.'))}
                            className="flex size-11 items-center justify-center rounded-xl border border-slate-200 text-slate-400 hover:text-rose-600"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-3">
                <div className="text-xs font-bold text-slate-500">Sub-CPMK yang diukur</div>
                <div className="mt-2 flex flex-wrap gap-2">
                    {subCpmks.map((sub: any) => (
                        <label
                            key={sub.id}
                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                form.data.sub_cpmk_ids.includes(sub.id)
                                    ? 'border-teal-300 bg-teal-100 text-teal-800'
                                    : 'border-slate-200 bg-white text-slate-500'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="sr-only"
                                checked={form.data.sub_cpmk_ids.includes(sub.id)}
                                onChange={() => toggleSub(sub.id)}
                            />
                            {sub.code}
                        </label>
                    ))}
                </div>
            </div>
        </form>
    );
}


function TaskCard({ rpsId, task, assessments, subCpmks }: any) {
    const [editing, setEditing] = useState(false);

    const form = useForm({
        assessment_id: task.assessment_id ?? '',
        title: task.title ?? '',
        type: task.type ?? 'assignment',
        purpose: task.purpose ?? '',
        instructions: task.instructions ?? '',
        expected_output: task.expected_output ?? '',
        due_week: task.due_week ? String(task.due_week) : '',
        sub_cpmk_ids: Array.isArray(task.sub_cpmk_ids) ? task.sub_cpmk_ids : [],
    });

    const toggleSub = (id: string) => {
        const current = form.data.sub_cpmk_ids;
        form.setData(
            'sub_cpmk_ids',
            current.includes(id)
                ? current.filter((value: string) => value !== id)
                : [...current, id],
        );
    };

    if (!editing) {
        return (
            <div className="rounded-xl border border-slate-100 bg-white/60 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="text-xs font-bold text-teal-700">{task.code}</div>
                        <div className="mt-1 font-semibold text-slate-900">{task.title}</div>
                        <div className="mt-1 text-xs text-slate-500">{task.type} | Minggu {task.due_week ?? '-'}</div>
                        {task.purpose && <div className="mt-2 text-sm text-slate-600"><strong>Tujuan:</strong> {task.purpose}</div>}
                        {task.expected_output && <div className="mt-2 text-sm text-slate-600"><strong>Luaran:</strong> {task.expected_output}</div>}
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <button
                            type="button"
                            title="Edit RTM"
                            onClick={() => setEditing(true)}
                            className="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 hover:text-teal-700"
                        >
                            <Pencil className="size-4" />
                        </button>
                        <button
                            type="button"
                            title="Hapus RTM"
                            onClick={() => {
                                if (confirm(`Hapus ${task.code} - ${task.title}?`)) {
                                    router.delete(`/rps/${rpsId}/tasks/${task.id}`, actionOptions('RTM berhasil dihapus.'));
                                }
                            }}
                            className="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 hover:text-rose-600"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(
                    `/rps/${rpsId}/tasks/${task.id}`,
                    actionOptions('RTM berhasil diperbarui.', () => setEditing(false)),
                );
            }}
            className="rounded-xl border border-teal-200 bg-teal-50/40 p-4 md:col-span-2"
        >
            <div className="flex items-center justify-between gap-3">
                <div>
                    <div className="text-xs font-bold text-teal-700">{task.code}</div>
                    <div className="font-bold text-slate-900">Edit Rencana Tugas Mahasiswa</div>
                </div>
                <button
                    type="button"
                    onClick={() => setEditing(false)}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                >
                    Batal
                </button>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
                <label className="md:col-span-2">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Judul Tugas</span>
                    <input
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Jenis</span>
                    <select
                        value={form.data.type}
                        onChange={(e) => form.setData('type', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="assignment">Tugas</option>
                        <option value="project">Proyek</option>
                        <option value="practicum">Praktikum</option>
                        <option value="presentation">Presentasi</option>
                        <option value="other">Lainnya</option>
                    </select>
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Asesmen Terkait</span>
                    <select
                        value={form.data.assessment_id}
                        onChange={(e) => form.setData('assessment_id', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="">Tanpa asesmen khusus</option>
                        {assessments.map((assessment: any) => (
                            <option key={assessment.id} value={assessment.id}>
                                {assessment.code} | {assessment.name}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Minggu Pengumpulan</span>
                    <input
                        type="number"
                        min="1"
                        max="16"
                        value={form.data.due_week}
                        onChange={(e) => form.setData('due_week', e.target.value)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-3">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Tujuan Tugas</span>
                    <textarea
                        value={form.data.purpose}
                        onChange={(e) => form.setData('purpose', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-3">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Instruksi Tugas</span>
                    <textarea
                        value={form.data.instructions}
                        onChange={(e) => form.setData('instructions', e.target.value)}
                        className="min-h-28 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>

                <label className="md:col-span-3">
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Luaran</span>
                    <textarea
                        value={form.data.expected_output}
                        onChange={(e) => form.setData('expected_output', e.target.value)}
                        className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </label>
            </div>

            <div className="mt-4">
                <div className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Sub-CPMK yang diukur</div>
                <div className="flex flex-wrap gap-2">
                    {subCpmks.map((sub: any) => (
                        <label
                            key={sub.id}
                            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                                form.data.sub_cpmk_ids.includes(sub.id)
                                    ? 'border-teal-300 bg-teal-100 text-teal-800'
                                    : 'border-slate-200 bg-white text-slate-500'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="sr-only"
                                checked={form.data.sub_cpmk_ids.includes(sub.id)}
                                onChange={() => toggleSub(sub.id)}
                            />
                            {sub.code}
                        </label>
                    ))}
                </div>
            </div>

            <button
                disabled={form.processing}
                className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
            >
                {form.processing ? 'Menyimpan...' : 'Simpan Perubahan RTM'}
            </button>
        </form>
    );
}


function InlineWeekRow({
    rpsId,
    week,
    subCpmks,
    credits,
    bibliography,
    aiConfigured,
    aiBusy,
    onGenerateAi,
}: any) {
    const [editing, setEditing] = useState(false);
    const c = Math.max(1, Number(credits || 1));

    const form = useForm({
        rps_sub_cpmk_id: week?.rps_sub_cpmk_id ?? '',
        assessment_indicator: week?.assessment_indicator ?? '',
        assessment_criteria: week?.assessment_criteria ?? '',
        assessment_method: week?.assessment_method ?? '',
        learning_form: week?.learning_form ?? 'Tatap Muka',
        learning_method: week?.learning_method ?? '',
        face_to_face_sessions: String(week?.face_to_face_sessions ?? 1),
        learning_activity: week?.learning_activity ?? '',
        independent_study_sessions: String(week?.independent_study_sessions ?? 1),
        student_assignment: week?.student_assignment ?? '',
        structured_task_sessions: String(week?.structured_task_sessions ?? 1),
        online_activity: week?.online_activity ?? '',
        material_text: week?.material_text ?? '',
        reference_text: week?.reference_text ?? '',
        time_estimate: week?.time_estimate ?? '',
    });

    if (week.is_exam) {
        const examTitle = week.exam_type === 'UTS'
            ? 'Ujian Tengah Semester'
            : 'Ujian Akhir Semester';

        return (
            <tr className="bg-sky-100/90">
                <td className="border border-slate-300 px-2 py-3 text-center font-extrabold text-slate-800">{week.week_number}</td>
                <td colSpan={6} className="border border-slate-300 px-3 py-3 text-center font-extrabold text-slate-800">{examTitle}</td>
                <td className="border border-slate-300 px-2 py-3 text-center font-extrabold text-slate-800">
                    {Number(week.assessment_weight || 0) || '-'}
                </td>
                <td className="border border-slate-300 px-2 py-3 text-center text-slate-300 print:hidden">-</td>
            </tr>
        );
    }

    const info = weekCompletion(week);
    const face = Math.max(0, Number(form.data.face_to_face_sessions || 0));
    const structured = Math.max(0, Number(form.data.structured_task_sessions || 0));
    const independent = Math.max(0, Number(form.data.independent_study_sessions || 0));

    const save = () => {
        form.transform((data: any) => ({
            ...data,
            face_to_face_sessions: Number(data.face_to_face_sessions || 0),
            structured_task_sessions: Number(data.structured_task_sessions || 0),
            independent_study_sessions: Number(data.independent_study_sessions || 0),
            time_estimate: `Tatap muka: ${Number(data.face_to_face_sessions || 0)} × (${c} × 50 menit); Tugas terstruktur: ${Number(data.structured_task_sessions || 0)} × (${c} × 60 menit); Belajar mandiri: ${Number(data.independent_study_sessions || 0)} × (${c} × 60 menit)`,
        })).put(
            `/rps/${rpsId}/weeks/${week.week_number}`,
            actionOptions(`Minggu ${week.week_number} berhasil disimpan.`, () => setEditing(false)),
        );
    };

    if (!editing) {
        return (
            <tr className="align-top hover:bg-teal-50/20">
                <td className="border border-slate-200 px-2 py-3 text-center text-sm font-bold">{week.week_number}</td>
                <td className="border border-slate-200 px-3 py-3 leading-5">
                    <div className="font-bold text-slate-800">{week.sub_cpmk_code || '-'}</div>
                    <div className="mt-1 text-slate-600">{week.sub_cpmk_description || '-'}</div>
                </td>
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">{week.assessment_indicator || '-'}</td>
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">
                    <div><strong>Kriteria:</strong> {week.assessment_criteria || '-'}</div>
                    <div className="mt-2"><strong>Bentuk:</strong> {week.assessment_method || '-'}</div>
                </td>
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">
                    <div className="font-bold text-slate-800">{week.learning_form || 'Tatap Muka'}</div>
                    <div className="mt-1">{formatFaceToFaceTime(week, c)}</div>
                    <div className="mt-2"><strong>Metode:</strong> {week.learning_method || '-'}</div>
                    <div className="mt-2"><strong>Belajar Mandiri:</strong> {week.learning_activity || '-'}</div>
                    <div className="mt-1 text-sky-700">{formatIndependentTime(week, c)}</div>
                </td>
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">
                    <div><strong>Penugasan:</strong> {week.student_assignment || '-'}</div>
                    <div className="mt-1 text-sky-700">{formatStructuredTime(week, c)}</div>
                    <div className="mt-2"><strong>Daring/LMS:</strong> {week.online_activity || '-'}</div>
                </td>
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">
                    <div>{week.material_text || '-'}</div>
                    {week.reference_text && (
                        <div className="mt-2 font-semibold text-sky-700">{week.reference_text}</div>
                    )}
                </td>
                <td className="border border-slate-200 px-2 py-3 text-center font-bold text-slate-700">
                    {Number(week.assessment_weight || 0) || '-'}
                </td>
                <td className="border border-slate-200 px-2 py-3 text-center print:hidden">
                    <div className="flex flex-col gap-2">
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="inline-flex items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-600"
                        >
                            <Pencil className="size-3.5" /> Edit
                        </button>
                        <button
                            type="button"
                            disabled={!aiConfigured || aiBusy}
                            onClick={() => onGenerateAi(info.count >= 7)}
                            className="inline-flex items-center justify-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1.5 text-[11px] font-bold text-violet-700 disabled:opacity-40"
                        >
                            <Sparkles className="size-3.5" />
                            {aiBusy ? 'AI...' : (info.count >= 7 ? 'Cek AI' : 'Lengkapi AI')}
                        </button>
                    </div>
                </td>
            </tr>
        );
    }

    const inputClass = "w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs";
    const areaClass = `${inputClass} min-h-24 resize-y`;

    return (
        <tr className="align-top bg-amber-50/25">
            <td className="border border-slate-200 px-2 py-3 text-center text-sm font-bold">{week.week_number}</td>
            <td className="border border-slate-200 p-2">
                <select
                    value={form.data.rps_sub_cpmk_id}
                    onChange={(e) => form.setData('rps_sub_cpmk_id', e.target.value)}
                    className={inputClass}
                >
                    <option value="">Pilih Sub-CPMK</option>
                    {subCpmks.map((sub: any) => (
                        <option key={sub.id} value={sub.id}>{sub.code} | {sub.description}</option>
                    ))}
                </select>
            </td>
            <td className="border border-slate-200 p-2">
                <textarea value={form.data.assessment_indicator} onChange={(e) => form.setData('assessment_indicator', e.target.value)} className={areaClass} />
            </td>
            <td className="border border-slate-200 p-2">
                <textarea value={form.data.assessment_criteria} onChange={(e) => form.setData('assessment_criteria', e.target.value)} className={areaClass} placeholder="Kriteria" />
                <input value={form.data.assessment_method} onChange={(e) => form.setData('assessment_method', e.target.value)} className={`${inputClass} mt-2`} placeholder="Bentuk / teknik" />
            </td>
            <td className="border border-slate-200 p-2">
                <input value={form.data.learning_form} onChange={(e) => form.setData('learning_form', e.target.value)} className={inputClass} placeholder="Tatap Muka / Praktikum" />
                <input value={form.data.learning_method} onChange={(e) => form.setData('learning_method', e.target.value)} className={`${inputClass} mt-2`} placeholder="Metode pembelajaran" />
                <div className="mt-2 grid grid-cols-[1fr_62px] items-center gap-2">
                    <span className="text-[10px] text-sky-700">{face} × ({c} × 50 menit)</span>
                    <input type="number" min="0" max="10" value={form.data.face_to_face_sessions} onChange={(e) => form.setData('face_to_face_sessions', e.target.value)} className={inputClass} />
                </div>
                <textarea value={form.data.learning_activity} onChange={(e) => form.setData('learning_activity', e.target.value)} className={`${areaClass} mt-2`} placeholder="Belajar mandiri" />
                <div className="mt-2 grid grid-cols-[1fr_62px] items-center gap-2">
                    <span className="text-[10px] text-sky-700">{independent} × ({c} × 60 menit)</span>
                    <input type="number" min="0" max="10" value={form.data.independent_study_sessions} onChange={(e) => form.setData('independent_study_sessions', e.target.value)} className={inputClass} />
                </div>
            </td>
            <td className="border border-slate-200 p-2">
                <textarea value={form.data.student_assignment} onChange={(e) => form.setData('student_assignment', e.target.value)} className={areaClass} placeholder="Penugasan mahasiswa" />
                <div className="mt-2 grid grid-cols-[1fr_62px] items-center gap-2">
                    <span className="text-[10px] text-sky-700">{structured} × ({c} × 60 menit)</span>
                    <input type="number" min="0" max="10" value={form.data.structured_task_sessions} onChange={(e) => form.setData('structured_task_sessions', e.target.value)} className={inputClass} />
                </div>
                <textarea value={form.data.online_activity} onChange={(e) => form.setData('online_activity', e.target.value)} className={`${areaClass} mt-2`} placeholder="Daring / LMS" />
            </td>
            <td className="border border-slate-200 p-2">
                <textarea value={form.data.material_text} onChange={(e) => form.setData('material_text', e.target.value)} className={areaClass} placeholder="Materi pembelajaran" />
                <input
                    value={form.data.reference_text}
                    onChange={(e) => form.setData('reference_text', e.target.value)}
                    className={`${inputClass} mt-2 font-semibold text-sky-700`}
                    placeholder="[1], [2], [4]"
                    title={`Gunakan nomor pustaka 1-${bibliography.length}.`}
                />
                <div className="mt-1 text-[10px] text-slate-400">Pustaka nomor saja, mis. [1], [3].</div>
            </td>
            <td className="border border-slate-200 px-2 py-3 text-center font-bold text-slate-700">
                {Number(week.assessment_weight || 0) || '-'}
            </td>
            <td className="border border-slate-200 p-2 text-center print:hidden">
                <div className="flex flex-col gap-2">
                    <button type="button" disabled={form.processing} onClick={save} className="rounded-lg bg-teal-700 px-2 py-2 text-[11px] font-bold text-white disabled:opacity-50">
                        {form.processing ? 'Simpan...' : 'Simpan'}
                    </button>
                    <button type="button" onClick={() => setEditing(false)} className="rounded-lg border border-slate-200 bg-white px-2 py-2 text-[11px] font-bold text-slate-600">
                        Batal
                    </button>
                </div>
            </td>
        </tr>
    );
}

function WeekEditor({ rpsId, week, subCpmks, credits, aiConfigured, aiBusy, onGenerateAi }: any) {
    const form = useForm({
        rps_sub_cpmk_id: week?.rps_sub_cpmk_id ?? '',
        assessment_indicator: week?.assessment_indicator ?? '',
        assessment_criteria: week?.assessment_criteria ?? '',
        assessment_method: week?.assessment_method ?? '',
        learning_form: week?.learning_form ?? '',
        learning_method: week?.learning_method ?? '',
        face_to_face_sessions: String(week?.face_to_face_sessions ?? 1),
        learning_activity: week?.learning_activity ?? '',
        independent_study_sessions: String(week?.independent_study_sessions ?? 1),
        student_assignment: week?.student_assignment ?? '',
        structured_task_sessions: String(week?.structured_task_sessions ?? 1),
        online_activity: week?.online_activity ?? '',
        material_text: week?.material_text ?? '',
        reference_text: week?.reference_text ?? '',
        time_estimate: week?.time_estimate ?? '',
    });

    if (!week) return null;

    if (week.is_exam) {
        const examTitle = week.exam_type === 'UTS' ? 'Ujian Tengah Semester' : 'Ujian Akhir Semester';

        return (
            <div className="mt-5 rounded-2xl border border-sky-200 bg-sky-50/70 p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-sky-600">Pekan {week.week_number}</div>
                        <h3 className="mt-1 text-lg font-extrabold text-slate-900">{examTitle}</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Pada tabel cetak, baris ini otomatis digabung seperti format RPS contoh. Tidak perlu mengisi Sub-CPMK, metode, materi, atau penilaian mingguan di sini.
                        </p>
                    </div>
                    <div className="rounded-xl border border-sky-200 bg-white px-4 py-3 text-center">
                        <div className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bobot</div>
                        <div className="mt-1 text-xl font-extrabold text-sky-700">{Number(week.assessment_weight || 0)}%</div>
                    </div>
                </div>
                <div className="mt-3 text-xs leading-5 text-slate-500">
                    Bobot UTS/UAS diubah pada <strong>Bagian 5. Asesmen & Bobot Nilai Akhir</strong>. Baris cetak akan menjadi: <strong>{week.week_number} | {examTitle} | {Number(week.assessment_weight || 0)}%</strong>.
                </div>
            </div>
        );
    }

    const info = weekCompletion(week);
    const c = Math.max(1, Number(credits || 1));
    const faceSessions = Math.max(0, Number(form.data.face_to_face_sessions || 0));
    const structuredSessions = Math.max(0, Number(form.data.structured_task_sessions || 0));
    const independentSessions = Math.max(0, Number(form.data.independent_study_sessions || 0));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data: any) => ({
                    ...data,
                    face_to_face_sessions: Number(data.face_to_face_sessions || 0),
                    structured_task_sessions: Number(data.structured_task_sessions || 0),
                    independent_study_sessions: Number(data.independent_study_sessions || 0),
                    time_estimate: `Tatap muka: ${Number(data.face_to_face_sessions || 0)} × (${c} × 50 menit); Tugas terstruktur: ${Number(data.structured_task_sessions || 0)} × (${c} × 60 menit); Belajar mandiri: ${Number(data.independent_study_sessions || 0)} × (${c} × 60 menit)`,
                })).put(
                    `/rps/${rpsId}/weeks/${week.week_number}`,
                    actionOptions(`Minggu ${week.week_number} berhasil disimpan.`),
                );
            }}
            className="mt-5 rounded-2xl border border-teal-100 bg-teal-50/30 p-5"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-bold text-slate-900">Pekan {week.week_number} | Isian Sesuai Kolom Tabel RPS</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Terisi {info.count}/7 komponen utama. Bobot penilaian pekan ini: <strong>{Number(week.assessment_weight || 0)}%</strong> dari Bagian 5.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        disabled={!aiConfigured || aiBusy}
                        onClick={() => onGenerateAi(info.count >= 7)}
                        className="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-100 disabled:opacity-40"
                    >
                        <Sparkles className="size-3.5" />
                        {aiBusy ? 'AI memproses...' : (info.count >= 7 ? 'Susun Ulang dengan AI' : 'Lengkapi dengan AI')}
                    </button>
                    {week.week_number > 1 && (
                        <button
                            type="button"
                            onClick={() => router.post(`/rps/${rpsId}/weeks/${week.week_number}/copy-previous`, {}, actionOptions(`Isi minggu ${week.week_number - 1} berhasil disalin ke minggu ${week.week_number}.`))}
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                        >
                            <Copy className="size-3.5" /> Salin Minggu Sebelumnya
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-2">
                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(2) Sub-CPMK</legend>
                    <label>
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Kemampuan akhir yang diharapkan</span>
                        <select
                            value={form.data.rps_sub_cpmk_id}
                            onChange={(e) => form.setData('rps_sub_cpmk_id', e.target.value)}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        >
                            <option value="">Pilih Sub-CPMK</option>
                            {subCpmks.map((sub: any) => <option key={sub.id} value={sub.id}>{sub.code} | {sub.description}</option>)}
                        </select>
                    </label>
                </fieldset>

                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(3) Penilaian | Indikator</legend>
                    <textarea
                        value={form.data.assessment_indicator}
                        onChange={(e) => form.setData('assessment_indicator', e.target.value)}
                        placeholder="Contoh: Ketepatan menganalisis konsep..."
                        className="min-h-28 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    />
                </fieldset>

                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(4) Penilaian | Kriteria & Bentuk</legend>
                    <label>
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Kriteria</span>
                        <textarea
                            value={form.data.assessment_criteria}
                            onChange={(e) => form.setData('assessment_criteria', e.target.value)}
                            placeholder="Rubrik penilaian, ketepatan, kelengkapan..."
                            className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>
                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Bentuk / Teknik</span>
                        <input
                            value={form.data.assessment_method}
                            onChange={(e) => form.setData('assessment_method', e.target.value)}
                            placeholder="Non-test (tanya jawab), tugas individu, kuis..."
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>
                </fieldset>

                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(5) Tatap Muka / Luring</legend>
                    <div className="grid gap-3 md:grid-cols-2">
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Bentuk Pembelajaran</span>
                            <input
                                list={`learning-form-options-${week.week_number}`}
                                value={form.data.learning_form}
                                onChange={(e) => form.setData('learning_form', e.target.value)}
                                placeholder="Tatap Muka / Praktikum / Tutorial"
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                            <datalist id={`learning-form-options-${week.week_number}`}>
                                {LEARNING_FORM_OPTIONS.map((item) => <option key={item} value={item} />)}
                            </datalist>
                        </label>
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Frekuensi Tatap Muka</span>
                            <input
                                type="number" min="0" max="10"
                                value={form.data.face_to_face_sessions}
                                onChange={(e) => form.setData('face_to_face_sessions', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                        </label>
                    </div>
                    <div className="mt-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700">
                        Tatap Muka: {faceSessions} × ({c} × 50 menit)
                    </div>
                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Metode Pembelajaran</span>
                        <input
                            list={`learning-method-options-${week.week_number}`}
                            value={form.data.learning_method}
                            onChange={(e) => form.setData('learning_method', e.target.value)}
                            placeholder="Small Group Discussion, PBL, PjBL, Discovery Learning..."
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                        <datalist id={`learning-method-options-${week.week_number}`}>
                            {LEARNING_METHOD_OPTIONS.map((item) => <option key={item} value={item} />)}
                        </datalist>
                    </label>
                    <div className="mt-3 grid gap-3 md:grid-cols-[1fr_150px]">
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Belajar Mandiri / Aktivitas Mahasiswa</span>
                            <textarea
                                value={form.data.learning_activity}
                                onChange={(e) => form.setData('learning_activity', e.target.value)}
                                className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                        </label>
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Frekuensi</span>
                            <input
                                type="number" min="0" max="10"
                                value={form.data.independent_study_sessions}
                                onChange={(e) => form.setData('independent_study_sessions', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                            <div className="mt-2 text-xs font-semibold text-sky-700">{independentSessions} × ({c} × 60 menit)</div>
                        </label>
                    </div>
                </fieldset>

                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(6) Daring</legend>
                    <div className="grid gap-3 md:grid-cols-[1fr_150px]">
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Penugasan Mahasiswa / Tugas Terstruktur</span>
                            <textarea
                                value={form.data.student_assignment}
                                onChange={(e) => form.setData('student_assignment', e.target.value)}
                                placeholder="Tugas individu/kelompok, latihan, proyek..."
                                className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                        </label>
                        <label>
                            <span className="mb-1.5 block text-xs font-bold text-slate-500">Frekuensi</span>
                            <input
                                type="number" min="0" max="10"
                                value={form.data.structured_task_sessions}
                                onChange={(e) => form.setData('structured_task_sessions', e.target.value)}
                                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                            />
                            <div className="mt-2 text-xs font-semibold text-sky-700">{structuredSessions} × ({c} × 60 menit)</div>
                        </label>
                    </div>
                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Media / Aktivitas Daring</span>
                        <textarea
                            value={form.data.online_activity}
                            onChange={(e) => form.setData('online_activity', e.target.value)}
                            placeholder="melalui LMS/e-learning, Google Classroom, forum diskusi..."
                            className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>
                </fieldset>

                <fieldset className="rounded-xl border border-slate-200 bg-white/80 p-4">
                    <legend className="px-2 text-xs font-extrabold text-slate-700">(7) Materi Pembelajaran [Pustaka]</legend>
                    <label>
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Materi Pembelajaran</span>
                        <textarea
                            value={form.data.material_text}
                            onChange={(e) => form.setData('material_text', e.target.value)}
                            className="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>
                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">Pustaka / Referensi</span>
                        <textarea
                            value={form.data.reference_text}
                            onChange={(e) => form.setData('reference_text', e.target.value)}
                            placeholder="Contoh: [1], [2], [3] atau rujukan ringkas"
                            className="min-h-16 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>
                </fieldset>

                <fieldset className="rounded-xl border border-amber-200 bg-amber-50/60 p-4 xl:col-span-2">
                    <legend className="px-2 text-xs font-extrabold text-amber-800">(8) Bobot Penilaian (%)</legend>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-2xl font-extrabold text-amber-800">{Number(week.assessment_weight || 0)}%</div>
                            <div className="mt-1 text-xs text-amber-700">{week.assessment_names || 'Belum ada asesmen berbobot pada pekan ini.'}</div>
                        </div>
                        <div className="max-w-xl text-xs leading-5 text-amber-700">
                            Bobot tidak diinput dua kali di sini. Nilainya otomatis diambil dari <strong>Bagian 5. Asesmen & Bobot Nilai Akhir</strong>, sehingga tabel cetak tetap konsisten dan total tidak ganda.
                        </div>
                    </div>
                </fieldset>
            </div>

            <button disabled={form.processing} className="mt-4 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                {form.processing ? 'Menyimpan...' : `Simpan Pekan ${week.week_number}`}
            </button>
        </form>
    );
}

function formatFaceToFaceTime(week: any, credits: any) {
    const sessions = Math.max(0, Number(week?.face_to_face_sessions ?? 1));
    const sks = Math.max(1, Number(credits || 1));
    return `Tatap Muka: ${sessions} × (${sks} × 50 menit)`;
}

function formatStructuredTime(week: any, credits: any) {
    const sessions = Math.max(0, Number(week?.structured_task_sessions ?? 1));
    const sks = Math.max(1, Number(credits || 1));
    return `Tugas Terstruktur: ${sessions} × (${sks} × 60 menit)`;
}

function formatIndependentTime(week: any, credits: any) {
    const sessions = Math.max(0, Number(week?.independent_study_sessions ?? 1));
    const sks = Math.max(1, Number(credits || 1));
    return `Belajar Mandiri: ${sessions} × (${sks} × 60 menit)`;
}

function weekCompletion(week: any) {
    if (week?.is_exam) {
        return { count: 7, filled: ['Ujian'] };
    }

    const fields = [
        ['Sub-CPMK', week?.rps_sub_cpmk_id],
        ['Indikator', week?.assessment_indicator],
        ['Kriteria/Bentuk', week?.assessment_criteria || week?.assessment_method],
        ['Luring', week?.learning_form || week?.learning_method],
        ['Daring', week?.student_assignment || week?.online_activity],
        ['Materi', week?.material_text],
        ['Pustaka', week?.reference_text],
    ];

    const filled = fields.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');

    return {
        count: filled.length,
        filled: filled.map(([label]) => label),
    };
}


function AiSuggestionCard({ suggestion, rpsId }: any) {
    const payload = suggestion.payload ?? {};
    const meta = suggestion.context_meta ?? {};

    const standardSelectable = [
        'cpmk_review',
        'cpl_mapping',
        'material_plan',
        'sub_cpmk',
    ].includes(suggestion.suggestion_type);

    const sourceItems = suggestion.suggestion_type === 'cpmk_review'
        ? safeList(payload.recommendations)
        : suggestion.suggestion_type === 'cpl_mapping'
          ? safeList(payload.mappings)
          : suggestion.suggestion_type === 'material_plan'
          ? safeList(payload.items)
          : suggestion.suggestion_type === 'sub_cpmk'
            ? safeList(payload.items)
            : [];

    const initialSelected = sourceItems
        .map((item: any, index: number) => ({ item, index }))
        .filter(({ item }) =>
            suggestion.suggestion_type === 'cpl_mapping'
                || String(item?.action ?? 'keep').toLowerCase() !== 'keep'
        )
        .map(({ index }) => index);

    const [selectedIndices, setSelectedIndices] = useState<number[]>(initialSelected);
    const [selectedAssessmentIndices, setSelectedAssessmentIndices] = useState<number[]>(
        safeList(payload.assessments).map((_: any, index: number) => index),
    );
    const [selectedTaskIndices, setSelectedTaskIndices] = useState<number[]>(
        safeList(payload.tasks).map((_: any, index: number) => index),
    );

    const labels: Record<string, string> = {
        cpmk_review: 'Telaah CPMK',
        cpl_mapping: 'Rekomendasi Pemetaan CPMK ↔ CPL',
        material_plan: 'Bahan Kajian',
        sub_cpmk: 'Sub-CPMK',
        weekly_plan: 'Rencana 14 Minggu',
        assessment_plan: 'Asesmen + RTM',
    };

    const countText = suggestion.suggestion_type === 'cpmk_review'
        ? `${safeList(payload.recommendations).length} rekomendasi CPMK`
        : suggestion.suggestion_type === 'cpl_mapping'
          ? `${safeList(payload.mappings).length} rekomendasi pemetaan`
          : suggestion.suggestion_type === 'material_plan'
          ? `${safeList(payload.items).length} rekomendasi bahan kajian`
          : suggestion.suggestion_type === 'sub_cpmk'
            ? `${safeList(payload.items).length} Sub-CPMK`
            : suggestion.suggestion_type === 'weekly_plan'
              ? `${safeList(payload.weeks).length} minggu kuliah`
              : `${safeList(payload.assessments).length} asesmen | ${safeList(payload.tasks).length} RTM`;

    const expectedTeachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
    const actualTeachingWeeks = suggestion.suggestion_type === 'weekly_plan'
        ? Array.from(new Set(
            safeList(payload.weeks)
                .map((week: any) => Number(week?.week_number))
        )).sort((a: number, b: number) => a - b)
        : [];

    const weeklyComplete = suggestion.suggestion_type !== 'weekly_plan'
        || JSON.stringify(actualTeachingWeeks) === JSON.stringify(expectedTeachingWeeks);

    const toggleSelected = (index: number) => {
        setSelectedIndices((current) =>
            current.includes(index)
                ? current.filter((value) => value !== index)
                : [...current, index].sort((a, b) => a - b)
        );
    };

    const toggleAssessment = (index: number) => {
        setSelectedAssessmentIndices((current) =>
            current.includes(index)
                ? current.filter((value) => value !== index)
                : [...current, index].sort((a, b) => a - b)
        );
    };

    const toggleTask = (index: number) => {
        setSelectedTaskIndices((current) =>
            current.includes(index)
                ? current.filter((value) => value !== index)
                : [...current, index].sort((a, b) => a - b)
        );
    };

    const selectedAssessmentTotal =
        selectedAssessmentIndices.length + selectedTaskIndices.length;

    const apply = () => {
        if (!weeklyComplete) {
            notify('error', 'Rencana AI ini tidak lengkap. Buat rekomendasi 14 minggu yang baru.');
            return;
        }

        if (standardSelectable && selectedIndices.length === 0) {
            notify('error', 'Pilih minimal satu rekomendasi yang akan diterapkan.');
            return;
        }

        if (
            suggestion.suggestion_type === 'assessment_plan'
            && selectedAssessmentTotal === 0
        ) {
            notify('error', 'Pilih minimal satu asesmen atau RTM yang akan diterapkan.');
            return;
        }

        const message = suggestion.suggestion_type === 'assessment_plan'
            ? `Terapkan ${selectedAssessmentIndices.length} asesmen dan ${selectedTaskIndices.length} RTM yang dipilih? Data yang tidak dipilih tidak akan diubah.`
            : standardSelectable
              ? `Terapkan ${selectedIndices.length} rekomendasi yang dipilih ke RPS?`
              : 'Terapkan rekomendasi AI ini ke RPS?';

        if (!confirm(message)) return;

        const data = suggestion.suggestion_type === 'assessment_plan'
            ? {
                selected_assessment_indices: selectedAssessmentIndices,
                selected_task_indices: selectedTaskIndices,
            }
            : standardSelectable
              ? { selected_indices: selectedIndices }
              : {};

        const successMessage = suggestion.suggestion_type === 'cpl_mapping'
            ? 'Pemetaan CPMK-CPL terpilih berhasil diterapkan dan langsung disimpan.'
            : 'Rekomendasi terpilih berhasil diterapkan ke RPS.';

        router.post(
            `/rps/${rpsId}/ai/suggestions/${suggestion.id}/apply`,
            data,
            {
                ...actionOptions(successMessage),
                preserveState: false,
            },
        );
    };

    const applyCount = suggestion.suggestion_type === 'assessment_plan'
        ? selectedAssessmentTotal
        : selectedIndices.length;

    return (
        <div className="rounded-2xl border border-slate-100 bg-white/70 p-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-bold text-slate-900">
                            {labels[suggestion.suggestion_type] ?? safeText(suggestion.suggestion_type)}
                        </span>
                        <span className="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">
                            pending
                        </span>
                        <span className="rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-sky-700">
                            {safeText(meta.model, 'AI')}
                        </span>
                    </div>

                    <p className="mt-2 text-sm leading-6 text-slate-600">
                        {safeText(payload.summary, 'Rekomendasi AI siap direview.')}
                    </p>

                    <div className="mt-2 text-xs font-semibold text-slate-400">
                        {countText}
                    </div>

                    {meta.fallback_used && (
                        <div className="mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
                            Provider utama gagal; SiMatRPS memakai backup AI.
                            {meta.primary_error ? ` Penyebab: ${safeText(meta.primary_error)}` : ''}
                        </div>
                    )}

                    {!weeklyComplete && (
                        <div className="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">
                            Tidak lengkap: {actualTeachingWeeks.length}/14 minggu. Buat ulang rekomendasi sebelum diterapkan.
                        </div>
                    )}
                </div>

                <div className="flex shrink-0 gap-2">
                    <button
                        type="button"
                        onClick={apply}
                        disabled={
                            !weeklyComplete
                            || (standardSelectable && selectedIndices.length === 0)
                            || (
                                suggestion.suggestion_type === 'assessment_plan'
                                && selectedAssessmentTotal === 0
                            )
                        }
                        className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {standardSelectable || suggestion.suggestion_type === 'assessment_plan'
                            ? `Terapkan Terpilih (${applyCount})`
                            : (weeklyComplete ? 'Terapkan' : 'Tidak Lengkap')}
                    </button>

                    <button
                        type="button"
                        onClick={() => router.post(
                            `/rps/${rpsId}/ai/suggestions/${suggestion.id}/reject`,
                            {},
                            actionOptions('Rekomendasi AI ditutup tanpa mengubah RPS.'),
                        )}
                        className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                    >
                        Tolak
                    </button>
                </div>
            </div>

            <details className="mt-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                <summary className="cursor-pointer text-xs font-bold text-sky-700">
                    Lihat detail rekomendasi
                </summary>

                <AiPayloadPreview
                    type={suggestion.suggestion_type}
                    payload={payload}
                    selectedIndices={selectedIndices}
                    selectedAssessmentIndices={selectedAssessmentIndices}
                    selectedTaskIndices={selectedTaskIndices}
                    onToggle={toggleSelected}
                    onToggleAssessment={toggleAssessment}
                    onToggleTask={toggleTask}
                />
            </details>
        </div>
    );
}

function AiPayloadPreview({
    type,
    payload,
    selectedIndices = [],
    selectedAssessmentIndices = [],
    selectedTaskIndices = [],
    onToggle,
    onToggleAssessment,
    onToggleTask,
}: any) {
    if (type === 'cpmk_review') {
        return (
            <div className="mt-3 space-y-2">
                {safeList(payload.recommendations).map((item: any, index: number) => {
                    const action = safeText(item?.action, 'keep').toLowerCase();
                    const actionable = action !== 'keep';
                    return (
                        <div key={index} className="rounded-lg bg-white p-3 text-xs">
                            <div className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-teal-700"
                                    disabled={!actionable}
                                    checked={actionable && selectedIndices.includes(index)}
                                    onChange={() => actionable && onToggle?.(index)}
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="font-bold text-slate-800">{action.toUpperCase()} | {safeText(item?.target_code, 'CPMK baru')}</div>
                                    <div className="mt-1 leading-5 text-slate-600">{safeText(item?.description)}</div>
                                    <div className="mt-1 text-slate-400">{safeText(item?.rationale, '')}</div>
                                    {!actionable && <div className="mt-1 text-[10px] font-bold text-emerald-600">KEEP = tidak mengubah data, jadi tidak perlu diterapkan.</div>}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }


    if (type === 'cpl_mapping') {
        return (
            <div className="mt-3 space-y-2">
                {safeList(payload.mappings).map((item: any, index: number) => (
                    <label
                        key={index}
                        className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-xs ${
                            selectedIndices.includes(index)
                                ? 'border-violet-200 bg-violet-50'
                                : 'border-slate-100 bg-white'
                        }`}
                    >
                        <input
                            type="checkbox"
                            className="mt-1 size-4 accent-violet-700"
                            checked={selectedIndices.includes(index)}
                            onChange={() => onToggle?.(index)}
                        />
                        <div className="min-w-0 flex-1">
                            <div className="font-bold text-slate-800">
                                {safeText(item?.cpmk_code)} → {safeText(item?.cpl_codes)}
                            </div>
                            <div className="mt-1 text-slate-500">
                                Keyakinan: <strong>{safeText(item?.confidence)}</strong>
                            </div>
                            <div className="mt-1 leading-5 text-slate-600">
                                {safeText(item?.rationale)}
                            </div>
                        </div>
                    </label>
                ))}
            </div>
        );
    }

    if (type === 'material_plan') {
        return (
            <div className="mt-3 space-y-2">
                {safeList(payload.items).map((item: any, index: number) => (
                    <label
                        key={index}
                        className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-xs ${
                            selectedIndices.includes(index)
                                ? 'border-violet-200 bg-violet-50'
                                : 'border-slate-100 bg-white'
                        }`}
                    >
                        <input
                            type="checkbox"
                            className="mt-1 size-4 accent-violet-700"
                            checked={selectedIndices.includes(index)}
                            onChange={() => onToggle?.(index)}
                        />
                        <div className="min-w-0 flex-1">
                            <div className="font-bold text-slate-800">
                                {safeText(item?.action).toUpperCase()} | {safeText(item?.title)}
                            </div>
                            <div className="mt-1 text-sky-700">
                                Sub-CPMK: {safeText(item?.sub_cpmk_code, '-')}
                            </div>
                            <div className="mt-1 leading-5 text-slate-500">
                                {safeText(item?.rationale)}
                            </div>
                        </div>
                    </label>
                ))}
            </div>
        );
    }

    if (type === 'sub_cpmk') {
        return (
            <div className="mt-3 space-y-2">
                {safeList(payload.items).map((item: any, index: number) => {
                    const action = safeText(item?.action, 'add').toLowerCase();
                    const actionable = action !== 'keep';
                    return (
                        <div key={index} className="rounded-lg bg-white p-3 text-xs">
                            <div className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-teal-700"
                                    disabled={!actionable}
                                    checked={actionable && selectedIndices.includes(index)}
                                    onChange={() => actionable && onToggle?.(index)}
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="font-bold text-slate-800">
                                        {action.toUpperCase()} | {safeText(item?.target_code, 'Sub-CPMK baru')} | {safeText(item?.parent_cpmk_code)} | {safeText(item?.bloom_level)}
                                    </div>
                                    <div className="mt-1 leading-5 text-slate-600">{safeText(item?.description)}</div>
                                    <div className="mt-1 text-slate-400">{safeText(item?.rationale, '')}</div>
                                    {!actionable && <div className="mt-1 text-[10px] font-bold text-emerald-600">KEEP = tidak mengubah data.</div>}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }

    if (type === 'weekly_plan') {
        return (
            <div className="mt-3 grid gap-2 md:grid-cols-2">
                {safeList(payload.weeks).map((week: any, index: number) => (
                    <div key={`${safeText(week?.week_number, String(index))}-${index}`} className="rounded-lg bg-white p-3 text-xs">
                        <div className="font-bold text-slate-800">Minggu {safeText(week?.week_number)} | {safeText(week?.sub_cpmk_code)}</div>
                        <div className="mt-1 leading-5 text-slate-600"><strong>Materi:</strong> {safeText(week?.material)}</div>
                        <div className="mt-1 leading-5 text-slate-600"><strong>Bentuk:</strong> {safeText(week?.learning_form)}</div>
                        <div className="mt-1 leading-5 text-slate-600"><strong>Metode:</strong> {safeText(week?.learning_method)}</div>
                        <div className="mt-1 leading-5 text-slate-600"><strong>Waktu:</strong> {safeText(week?.time_estimate)}</div>
                        <div className="mt-1 leading-5 text-slate-600"><strong>Penugasan:</strong> {safeText(week?.student_assignment)}</div>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="mt-3 space-y-4 text-xs">
            <div>
                <div className="mb-2 flex items-center justify-between gap-3">
                    <div className="font-bold text-slate-700">Asesmen</div>
                    <div className="text-[11px] font-semibold text-slate-400">
                        Total rekomendasi: {safeList(payload.assessments).reduce(
                            (sum: number, item: any) => sum + Number(item?.weight || 0),
                            0,
                        )}% | Dipilih: {safeList(payload.assessments).reduce(
                            (sum: number, item: any, index: number) =>
                                selectedAssessmentIndices.includes(index)
                                    ? sum + Number(item?.weight || 0)
                                    : sum,
                            0,
                        )}%
                    </div>
                </div>

                <div className="space-y-2">
                    {safeList(payload.assessments).map((item: any, index: number) => (
                        <label
                            key={index}
                            className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                                selectedAssessmentIndices.includes(index)
                                    ? 'border-teal-200 bg-teal-50'
                                    : 'border-slate-100 bg-white'
                            }`}
                        >
                            <input
                                type="checkbox"
                                className="mt-1 size-4 accent-teal-700"
                                checked={selectedAssessmentIndices.includes(index)}
                                onChange={() => onToggleAssessment?.(index)}
                            />
                            <div className="min-w-0 flex-1">
                                <strong>{safeText(item?.name)}</strong>
                                {' | '}Minggu {safeText(item?.week_number)}
                                {' | '}{safeText(item?.weight)}%
                                <div className="mt-1 text-slate-500">
                                    Mengukur: {safeText(item?.sub_cpmk_codes)}
                                </div>
                                <div className="mt-1 leading-5 text-slate-500">
                                    {safeText(item?.description, '')}
                                </div>
                            </div>
                        </label>
                    ))}
                </div>
            </div>

            {safeList(payload.tasks).length > 0 && (
                <div>
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <div className="font-bold text-slate-700">RTM</div>
                        <div className="text-[11px] font-semibold text-slate-400">
                            RTM yang tidak dipilih tidak akan diubah
                        </div>
                    </div>

                    <div className="space-y-2">
                        {safeList(payload.tasks).map((task: any, index: number) => (
                            <label
                                key={index}
                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                                    selectedTaskIndices.includes(index)
                                        ? 'border-teal-200 bg-teal-50'
                                        : 'border-slate-100 bg-white'
                                }`}
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-teal-700"
                                    checked={selectedTaskIndices.includes(index)}
                                    onChange={() => onToggleTask?.(index)}
                                />
                                <div className="min-w-0 flex-1">
                                    <strong>{safeText(task?.title)}</strong>
                                    {' | '}Minggu {safeText(task?.due_week)}
                                    <div className="mt-1 text-slate-500">
                                        Luaran: {safeText(task?.expected_output)}
                                    </div>
                                </div>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

RpsShow.layout = {
    breadcrumbs: [
        { title: 'RPS Saya', href: '/rps' },
        { title: 'Workspace OBE', href: '#' },
    ],
};
