import { Head, router, useForm } from '@inertiajs/react';
import { SidebarTrigger } from '@/components/ui/sidebar';
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
    const message = first ? String(first) : 'Aksi gagal diproses. Periksa kembali isian.';

    if (/Semua provider AI aktif gagal|Semua provider AI gagal/i.test(message)) {
        return message;
    }

    if (/tokens per day|TPD|rate limit reached|daily quota/i.test(message)) {
        return 'Provider utama sedang mencapai kuota. SiMatRPS akan mencoba provider backup aktif secara otomatis; jika pesan ini tetap muncul berarti provider yang tersedia juga belum berhasil.';
    }

    if (/tokens per minute|TPM/i.test(message)) {
        return 'Batas token per menit provider AI sedang tercapai. SiMatRPS akan mencoba provider backup atau melewati provider yang sedang cooldown.';
    }

    return message;
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

function stripMaterialListPrefix(value: any) {
    return String(value ?? '')
        .replace(/^\s*(?:(?:[a-z]|\d{1,2})[.)]\s*)+/iu, '')
        .trim();
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
        documentMeta = {},
        masterSyllabus = { description: '', reference_text: '', supporting_reference_text: '', prerequisite_text: '' },
        weeks = [],
        assessments = [],
        tasks = [],
        simulationScores = {},
        progress = { percent: 0, checks: [], assessment_weight_total: 0 },
        ai = { configured: false, provider: 'groq', model: 'openai/gpt-oss-20b', fallbacks: [] },
        aiSuggestions = [],
    } = props;

    const [aiInstruction, setAiInstruction] = useState('');
    const [aiPreferenceReady, setAiPreferenceReady] = useState(false);
    const [aiBusyType, setAiBusyType] = useState<string | null>(null);
    const [aiBusyWeek, setAiBusyWeek] = useState<number | null>(null);
    const [selectedBatchWeeks, setSelectedBatchWeeks] = useState<number[]>(TEACHING_WEEKS);
    const [meetingPlannerOpen, setMeetingPlannerOpen] = useState(false);

    const aiPreferenceKey = useMemo(
        () => `simatrps:ai-preference:${rps.id}`,
        [rps.id],
    );

    useEffect(() => {
        try {
            const stored = window.localStorage.getItem(aiPreferenceKey);
            if (stored !== null) {
                setAiInstruction(stored);
            }
        } catch {
            // Browser privacy mode may block localStorage; AI still works.
        } finally {
            setAiPreferenceReady(true);
        }
    }, [aiPreferenceKey]);

    useEffect(() => {
        if (!aiPreferenceReady) return;

        try {
            window.localStorage.setItem(aiPreferenceKey, aiInstruction);
        } catch {
            // Ignore storage errors; do not block AI requests.
        }
    }, [aiInstruction, aiPreferenceKey, aiPreferenceReady]);

    const clearAiPreference = () => {
        setAiInstruction('');

        try {
            window.localStorage.removeItem(aiPreferenceKey);
        } catch {
            // Ignore storage errors.
        }

        notify('info', 'Preferensi AI untuk RPS ini dikosongkan.');
    };

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

    const cpmkReviewSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'cpmk_review',
    );
    const bloomMappingSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'bloom_mapping',
    );
    const cplMappingSuggestions = aiSuggestions.filter(
        (item: any) => item.suggestion_type === 'cpl_mapping',
    );
    const cpmkAiSuggestions = [...cpmkReviewSuggestions, ...bloomMappingSuggestions, ...cplMappingSuggestions];
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
        if (overwrite && !confirm(`Susun ulang pekan ${weekNumber} dengan AI? Isian pekan ini dapat diganti oleh hasil AI.`)) {
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
                        ? `Pekan ${weekNumber} berhasil disusun ulang dengan AI.`
                        : `Pekan ${weekNumber} berhasil dilengkapi dengan AI.`,
                ),
                onError: (errors) => notify('error', firstError(errors)),
                onFinish: () => setAiBusyWeek(null),
            },
        );
    };

    const cpRowSpan = 3 + cpls.length + cpmks.length + subCpmks.length;
    const primaryReferences = bibliography.filter((item: any) => item.category !== 'pendukung');
    const supportingReferences = bibliography.filter((item: any) => item.category === 'pendukung');
    const totalWeeklyWeight = weeks.reduce(
        (sum: number, week: any) => sum + Number(week.assessment_weight || 0),
        0,
    );

    return (
        <>
            <Head title={`RPS ${rps.course_name}`} />
            <ActionNotifications />
            {meetingPlannerOpen && (
                <SubCpmkMeetingPlanner
                    rpsId={rps.id}
                    subCpmks={subCpmks}
                    weeks={weeks}
                    onClose={() => setMeetingPlannerOpen(false)}
                />
            )}

            <div className="p-3 md:p-5">
                {/* Workspace toolbar: compact, non-print */}
                <div className="mb-4 flex flex-col gap-3 print:hidden xl:flex-row xl:items-center xl:justify-between">
                    <div className="flex items-start gap-2">
                        <SidebarTrigger
                            className="mt-0.5 size-9 shrink-0 rounded-xl border border-slate-200 bg-white shadow-sm"
                            title="Minimalkan / tampilkan menu"
                        />
                        <div>
                            <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Editor RPS</div>
                        <h1 className="mt-1 text-xl font-bold text-slate-900">{rps.course_name}</h1>
                        <p className="mt-1 text-xs text-slate-500">
                            {rps.official_code || rps.system_code} | {rps.credits} SKS | Semester {rps.semester_recommended || '-'} | {rps.academic_year} {rps.academic_semester}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                            Math.abs(totalWeeklyWeight - 100) < 0.01
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-amber-50 text-amber-700'
                        }`}>
                            Bobot {totalWeeklyWeight}% / 100%
                        </div>

                        <div className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600">
                            OBE {progress.percent}%
                        </div>

                        <button
                            type="button"
                            onClick={() => router.post(
                                `/rps/${rps.id}/validate-obe`,
                                {},
                                actionOptions('Validasi OBE selesai.'),
                            )}
                            className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white"
                        >
                            Validasi OBE
                        </button>
                    </div>
                </div>

                {/* AI compact toolbar */}
                <div className="mb-4 rounded-xl border border-sky-100 bg-white p-3 print:hidden">
                    <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 text-sm font-bold text-slate-800">
                                <Sparkles className="size-4 text-violet-600" />
                                AI SiMatRPS
                            </span>
                            <span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${
                                ai.configured
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'
                            }`}>
                                {ai.configured ? 'AI aktif' : 'AI belum aktif'}
                            </span>
                            <span className="text-xs text-slate-400">
                                Rekomendasi AI ditempatkan tepat di bagian yang sedang dikerjakan.
                            </span>
                        </div>

                        <details className="group">
                            <summary className="cursor-pointer rounded-lg border border-violet-200 bg-violet-100 px-3 py-2 text-xs font-bold text-violet-800 transition hover:bg-violet-200">
                                Preferensi AI
                            </summary>
                            <div className="mt-2 w-full rounded-xl border border-slate-200 bg-white p-3 lg:absolute lg:right-5 lg:z-40 lg:w-[560px] lg:shadow-xl">
                                <textarea
                                    value={aiInstruction}
                                    onChange={(e) => setAiInstruction(e.target.value)}
                                    placeholder="Opsional. Contoh: gunakan PBL; prioritaskan praktikum; pilih CPL hanya yang benar-benar relevan."
                                    className="min-h-24 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                />
                                <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <div className="text-[11px] leading-5 text-slate-400">
                                        Tersimpan otomatis khusus untuk RPS ini dan dipakai pada permintaan AI berikutnya yang relevan.
                                    </div>
                                    <button
                                        type="button"
                                        onClick={clearAiPreference}
                                        className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-500 hover:bg-slate-50"
                                    >
                                        Kosongkan Preferensi
                                    </button>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                {/* Meta editor, simple and hidden in print */}
                <DocumentMetaEditor
                    rpsId={rps.id}
                    meta={documentMeta}
                    master={masterSyllabus}
                    aiInstruction={aiInstruction}
                />

                {/* Printable RPS document */}
                <section className="mx-auto max-w-[1500px] rounded-2xl border border-slate-300 bg-white font-sans text-[11px] leading-[1.45] shadow-[0_16px_50px_rgba(15,23,42,0.08)] print:max-w-none print:rounded-none print:border-0 print:shadow-none">
                    <div className="overflow-x-auto overflow-y-visible">
                        <table className="min-w-[1080px] w-full border-collapse text-[11px] leading-[1.45] text-slate-800">
                            <tbody>
                                <tr>
                                    <td colSpan={6} className="border border-slate-300 bg-gradient-to-r from-teal-50 via-cyan-50 to-sky-50 p-0">
                                        <div className="grid min-h-[108px] grid-cols-[110px_1fr_110px] items-center px-4 py-3">
                                            <div className="flex items-center justify-center">
                                                <img
                                                    src="/logo-unsulbar.png"
                                                    alt="Logo Universitas Sulawesi Barat"
                                                    className="h-[82px] w-[82px] object-contain"
                                                />
                                            </div>

                                            <div className="text-center text-slate-900">
                                                <div className="text-[15px] font-black leading-5">UNIVERSITAS SULAWESI BARAT</div>
                                                <div className="text-[14px] font-black leading-5">FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM</div>
                                                <div className="text-[14px] font-black leading-5">JURUSAN MATEMATIKA</div>
                                                <div className="text-[14px] font-black leading-5">PROGRAM STUDI MATEMATIKA</div>
                                            </div>

                                            <div aria-hidden="true" />
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th colSpan={6} className="border border-slate-300 bg-teal-50 px-3 py-1.5 text-center text-sm font-extrabold">
                                        RENCANA PEMBELAJARAN SEMESTER (RPS)
                                    </th>
                                </tr>
                                <tr className="bg-slate-50 text-center text-[11px] font-bold">
                                    <th className="border border-slate-300 px-2 py-1">NAMA MATA KULIAH</th>
                                    <th className="border border-slate-300 px-2 py-1">KODE MK</th>
                                    <th className="border border-slate-300 px-2 py-1">BOBOT (SKS)</th>
                                    <th className="border border-slate-300 px-2 py-1">SEMESTER</th>
                                    <th className="border border-slate-300 px-2 py-1">Tgl. Penyusunan</th>
                                    <th className="border border-slate-300 px-2 py-1">Tgl. Terbit</th>
                                </tr>
                                <tr className="text-center">
                                    <td className="border border-slate-300 px-2 py-1 font-semibold">{rps.course_name}</td>
                                    <td className="border border-slate-300 px-2 py-1">{rps.official_code || rps.system_code}</td>
                                    <td className="border border-slate-300 px-2 py-1">{rps.credits}</td>
                                    <td className="border border-slate-300 px-2 py-1">{rps.semester_recommended || '-'}</td>
                                    <td className="border border-slate-300 px-2 py-1">{formatIndonesianDate(documentMeta.prepared_date)}</td>
                                    <td className="border border-slate-300 px-2 py-1">{formatIndonesianDate(documentMeta.published_date)}</td>
                                </tr>
                                <tr className="bg-slate-50 text-center text-[11px] font-bold">
                                    <th rowSpan={2} className="border border-slate-300 px-2 py-1 align-middle">OTORISASI</th>
                                    <th colSpan={2} className="border border-slate-300 px-2 py-1">Nama Koordinator Pengembang RPS</th>
                                    <th className="border border-slate-300 px-2 py-1">Koordinator Mata Kuliah</th>
                                    <th colSpan={2} className="border border-slate-300 px-2 py-1">Koord. Program Studi</th>
                                </tr>
                                <tr className="text-center align-top">
                                    <td colSpan={2} className="border border-slate-300 px-2 py-1 whitespace-pre-line">{documentMeta.developer_name || '-'}</td>
                                    <td className="border border-slate-300 px-2 py-1 whitespace-pre-line">{documentMeta.coordinator_name || '-'}</td>
                                    <td colSpan={2} className="border border-slate-300 px-2 py-1 whitespace-pre-line">{documentMeta.head_program_name || '-'}</td>
                                </tr>

                                {/* Capaian Pembelajaran */}
                                <tr>
                                    <td
                                        rowSpan={cpRowSpan}
                                        className="w-[130px] border border-slate-400 px-2 py-1.5 align-top font-bold"
                                    >
                                        Capaian Pembelajaran
                                    </td>
                                    <td colSpan={2} className="border border-slate-400 bg-slate-50 px-2 py-1.5 font-bold">
                                        <div className="flex items-center justify-between gap-2">
                                            <span>CPL-PRODI yang dibebankan pada MK</span>
                                        </div>
                                    </td>
                                    <td colSpan={3} className="border border-slate-400 px-2 py-1.5 print:hidden">
                                        <CplScopeQuickEditor
                                            rpsId={rps.id}
                                            allCpls={allCpls}
                                            officialCplIds={officialCplIds}
                                            additionalCplIds={additionalCplIds}
                                        />
                                    </td>
                                </tr>

                                {cpls.map((cpl: any) => (
                                    <tr key={cpl.id}>
                                        <td className="w-[90px] border border-slate-400 px-2 py-1 align-top font-semibold">
                                            {displayCplCode(cpl.code)}
                                        </td>
                                        <td colSpan={4} className="border border-slate-300 px-2 py-0.5 leading-4">
                                            {cpl.description}
                                        </td>
                                    </tr>
                                ))}

                                <tr>
                                    <td colSpan={5} className="border border-slate-400 bg-slate-50 px-2 py-1.5 font-bold">
                                        <div className="flex items-center justify-between gap-2">
                                            <span>Capaian Pembelajaran Mata Kuliah (CPMK)</span>
                                            <div className="flex flex-wrap items-center justify-end gap-1.5 print:hidden">
                                                <DocumentCpmkAdd rpsId={rps.id} />
                                                <SectionAiButton
                                                    label="Telaah CPMK AI"
                                                    busy={aiBusyType === 'cpmk_review'}
                                                    disabled={!ai.configured || cpmks.length === 0}
                                                    onClick={() => generateAi('cpmk_review')}
                                                    suggestions={cpmkReviewSuggestions}
                                                    rpsId={rps.id}
                                                />
                                                <SectionAiButton
                                                    label="Pemetaan Bloom AI"
                                                    busy={aiBusyType === 'bloom_mapping'}
                                                    disabled={!ai.configured || cpmks.length === 0}
                                                    onClick={() => generateAi('bloom_mapping')}
                                                    suggestions={bloomMappingSuggestions}
                                                    rpsId={rps.id}
                                                />
                                                <SectionAiButton
                                                    label="Pemetaan CPMK → CPL AI"
                                                    busy={aiBusyType === 'cpl_mapping'}
                                                    disabled={!ai.configured || cpmks.length === 0}
                                                    onClick={() => generateAi('cpl_mapping')}
                                                    suggestions={cplMappingSuggestions}
                                                    rpsId={rps.id}
                                                />
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {cpmks.map((cpmk: any) => (
                                    <DocumentCpmkRow
                                        key={cpmk.id}
                                        rpsId={rps.id}
                                        cpmk={cpmk}
                                        cpls={cpls}
                                        selectedCplIds={mappingForm.data.mappings[cpmk.id] ?? []}
                                        onToggle={(cplId: string) => toggleMapping(cpmk.id, cplId)}
                                        onSaveMapping={(afterSuccess: () => void) => mappingForm.put(
                                            `/rps/${rps.id}/cpmk-cpl`,
                                            actionOptions('Pemetaan CPMK → CPL berhasil disimpan.', afterSuccess),
                                        )}
                                    />
                                ))}

                                <tr>
                                    <td colSpan={5} className="border border-slate-400 bg-slate-50 px-2 py-1.5 font-bold">
                                        <div className="flex items-center justify-between gap-2">
                                            <span>Kemampuan akhir tiap tahapan belajar (Sub-CPMK)</span>
                                            <div className="flex flex-wrap items-center justify-end gap-1.5 print:hidden">
                                                <DocumentSubCpmkAdd rpsId={rps.id} cpmks={cpmks} />
                                                <SectionAiButton
                                                    label="Telaah Sub-CPMK AI"
                                                    busy={aiBusyType === 'sub_cpmk'}
                                                    disabled={!ai.configured || cpmks.length === 0}
                                                    onClick={() => generateAi('sub_cpmk')}
                                                    suggestions={subCpmkAiSuggestions}
                                                    rpsId={rps.id}
                                                />
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {subCpmks.map((sub: any) => (
                                    <DocumentSubCpmkRow
                                        key={sub.id}
                                        rpsId={rps.id}
                                        sub={sub}
                                        cpmks={cpmks}
                                    />
                                ))}

                                {/* Matrix */}
                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 font-bold">
                                        Korelasi CPMK terhadap Sub-CPMK
                                    </td>
                                    <td colSpan={5} className="border border-slate-400 p-0">
                                        <SubCpmkMatrix cpmks={cpmks} subCpmks={subCpmks} />
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">
                                        Deskripsi Singkat MK
                                    </td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5">
                                        {documentMeta.description_short || '-'}
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">
                                        Bahan Kajian:
                                        <div className="font-normal">Materi Pembelajaran</div>
                                    </td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5">
                                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2 print:hidden">
                                            <span className="text-[10px] font-semibold text-slate-400">
                                                Materi RPS
                                            </span>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <DocumentMaterialsManager
                                                    rpsId={rps.id}
                                                    materials={materials}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(
                                                        `/rps/${rps.id}/materials/import-syllabus`,
                                                        {},
                                                        actionOptions('Bahan kajian disinkronkan dari master kurikulum.'),
                                                    )}
                                                    className="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700"
                                                >
                                                    Ambil dari Kurikulum
                                                </button>
                                                <SectionAiButton
                                                    label="Telaah Bahan Kajian AI"
                                                    busy={aiBusyType === 'material_plan'}
                                                    disabled={!ai.configured || subCpmks.length === 0}
                                                    onClick={() => generateAi('material_plan')}
                                                    suggestions={materialAiSuggestions}
                                                    rpsId={rps.id}
                                                />
                                            </div>
                                        </div>
                                        

                                        <ol className="list-[lower-alpha] space-y-0.5 pl-5">
                                            {materials.map((item: any) => (
                                                <li key={item.id}>{stripMaterialListPrefix(item.title)}</li>
                                            ))}
                                        </ol>
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">Pustaka</td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5 align-top">
                                        <div className="mb-1 flex items-start gap-3">
                                            <div className="shrink-0 pt-1 font-bold">Utama:</div>
                                            <div className="min-w-0 flex-1">
                                                <PustakaInlineTools
                                                    rpsId={rps.id}
                                                    meta={documentMeta}
                                                    master={masterSyllabus}
                                                    aiInstruction={aiInstruction}
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-0.5">
                                            {(primaryReferences.length ? primaryReferences : bibliography).map((item: any) => (
                                                <div key={item.number}>
                                                    {item.number}. {item.text}
                                                </div>
                                            ))}
                                        </div>

                                        {supportingReferences.length > 0 && (
                                            <>
                                                <div className="mt-2 font-bold">Pendukung:</div>
                                                <div className="space-y-0.5">
                                                    {supportingReferences.map((item: any) => (
                                                        <div key={item.number}>
                                                            {item.number}. {item.text}
                                                        </div>
                                                    ))}
                                                </div>
                                            </>
                                        )}
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 align-top font-bold">Media Pembelajaran</td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5">
                                        <div><strong>1. Perangkat Lunak:</strong> {documentMeta.software_media || '-'}</div>
                                        <div><strong>2. Perangkat Keras:</strong> {documentMeta.hardware_media || '-'}</div>
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 font-bold">Dosen Pengampu</td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5 whitespace-pre-line">
                                        {formatLecturerNames(documentMeta.lecturer_names)}
                                    </td>
                                </tr>

                                <tr>
                                    <td className="border border-slate-400 px-2 py-1.5 font-bold">Matakuliah Syarat</td>
                                    <td colSpan={5} className="border border-slate-400 px-2 py-1.5">
                                        {documentMeta.prerequisite_text || '-'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {/* Spacer nyata agar tabel identitas dan tabel pekan tidak menempel, termasuk di Chrome print preview. */}
                    <div aria-hidden="true" className="w-full shrink-0" style={{ height: '32px' }} />

                    {/* Weekly toolbar */}
                    <div className="flex flex-wrap items-center justify-between gap-2 border-x border-t border-slate-300 bg-slate-50 px-3 py-2 print:hidden">
                        <div className="text-xs font-bold text-slate-600">Rencana Pembelajaran Semester</div>
                        <div className="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                onClick={() => setMeetingPlannerOpen(true)}
                                className="rounded-lg border border-emerald-600 bg-emerald-600 px-2.5 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-emerald-700"
                                title="Tetapkan jumlah pertemuan untuk setiap Sub-CPMK"
                            >
                                Atur Pertemuan
                            </button>
                            <button
                                type="button"
                                title="Mengisi bagian RPS yang masih kosong. Jika total asesmen agregat sudah 100%, bobot non-UTS/UAS juga dibagi ke pekan kosong berdasarkan Sub-CPMK dan jumlah pertemuannya, tanpa menimpa bobot yang sudah diisi dosen."
                                onClick={() => router.post(
                                    `/rps/${rps.id}/smart-draft`,
                                    { mode: 'fill_empty' },
                                    actionOptions('Bagian RPS yang masih kosong berhasil diisi tanpa menimpa edit manual atau hasil Susun AI.'),
                                )}
                                className="rounded-lg bg-teal-700 px-2.5 py-1.5 text-[11px] font-bold text-white"
                            >
                                Isi Bagian Kosong
                            </button>
                        </div>
                    </div>

                    {/* Weekly table, exact print columns */}
                    <div className="overflow-x-auto">
                        <table className="min-w-[1180px] w-full border-separate border-spacing-0 text-[11px] leading-[1.45]">
                            <thead className="sticky top-0 z-10 bg-gradient-to-r from-sky-100 via-cyan-50 to-teal-50 text-center font-bold text-slate-800 shadow-sm">
                                <tr>
                                    <th rowSpan={2} className="w-[70px] border border-slate-400 px-2 py-2">Pekan<br />Ke-</th>
                                    <th rowSpan={2} className="w-[210px] border border-slate-400 px-2 py-2">Sub-CPMK<br />(Kemampuan akhir yang diharapkan)</th>
                                    <th colSpan={2} className="border border-slate-300 px-2 py-1.5">Penilaian</th>
                                    <th colSpan={2} className="border border-slate-300 px-2 py-1.5">
                                        Bentuk Pembelajaran; Metode Pembelajaran; Penugasan;<br />[Estimasi Waktu]
                                    </th>
                                    <th rowSpan={2} className="w-[200px] border border-slate-400 px-2 py-2">Materi Pembelajaran<br />[Pustaka]</th>
                                    <th rowSpan={2} className="w-[90px] border border-slate-400 px-2 py-2">Bobot<br />Penilaian<br />(%)</th>
                                </tr>
                                <tr>
                                    <th className="w-[190px] border border-slate-400 px-2 py-2">Indikator</th>
                                    <th className="w-[180px] border border-slate-400 px-2 py-2">Kriteria & Bentuk</th>
                                    <th className="w-[225px] border border-slate-400 px-2 py-2">Tatap muka / Luring</th>
                                    <th className="w-[225px] border border-slate-400 px-2 py-2">Daring</th>
                                </tr>
                                <tr className="text-[11px]">
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(1)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(2)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(3)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(4)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(5)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(6)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(7)</th>
                                    <th className="border border-slate-300 px-2 py-0.5 leading-4">(8)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {weeks.map((week: any) => (
                                    <DocumentWeekRow
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

                    {/* Advanced OBE tools kept, but out of the main document */}
                <details className="group mx-auto mt-4 max-w-[1500px] overflow-hidden rounded-xl border border-cyan-200 bg-white shadow-sm print:hidden">
                    <summary className="list-none cursor-pointer bg-gradient-to-r from-slate-50 via-cyan-50 to-teal-50 px-4 py-3 text-sm font-extrabold text-slate-800 hover:from-cyan-50 hover:to-teal-100 [&::-webkit-details-marker]:hidden">
                        <span className="flex items-center justify-between gap-2">
                            <span className="inline-flex items-center gap-2">
                                <Pencil className="size-4 text-teal-800" />
                                <span>Edit Detail Asesmen, RTM & Validator OBE</span>
                            </span>
                            <span className="rounded-full border border-teal-200 bg-white px-2.5 py-1 text-[9px] font-bold text-teal-700">
                                Buka Editor
                            </span>
                        </span>
                    </summary>
                    <div className="border-t border-cyan-200 bg-white p-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div className="font-bold text-slate-900">Asesmen Detail & RTM</div>
                                <p className="mt-1 text-xs text-slate-500">
                                    Asesmen menyimpan bobot agregat/anggaran penilaian (total 100%). Bobot non-UTS/UAS kemudian didistribusikan ke 14 pekan pada tabel RPS; keduanya adalah dua representasi yang sama dan tidak dijumlahkan dua kali.
                                </p>
                            </div>

                            <SectionAiButton
                                label="Telaah Asesmen + RTM AI"
                                busy={aiBusyType === 'assessment_plan'}
                                disabled={!ai.configured || subCpmks.length === 0}
                                onClick={() => generateAi('assessment_plan')}
                                suggestions={assessmentAiSuggestions}
                                rpsId={rps.id}
                            />
                        </div>

                        <div className="mt-4 grid gap-3 xl:grid-cols-2">
                            <AssessmentQuickAdd
                                rpsId={rps.id}
                                subCpmks={subCpmks}
                                assessments={assessments}
                            />
                            <TaskQuickAdd
                                rpsId={rps.id}
                                subCpmks={subCpmks}
                                assessments={assessments}
                            />
                        </div>

                        <div className="mt-4 grid gap-3 lg:grid-cols-2">
                            {assessments.map((assessment: any) => (
                                <AssessmentCard
                                    key={assessment.id}
                                    rpsId={rps.id}
                                    assessment={assessment}
                                    subCpmks={subCpmks}
                                />
                            ))}
                        </div>

                        <div className="mt-5 border-t border-slate-100 pt-4">
                            <div className="font-bold text-slate-900">RTM</div>
                            <div className="mt-3 grid gap-3 lg:grid-cols-2">
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
                        </div>

                        <div className="mt-5 border-t border-slate-100 pt-4">
                            <div className="font-bold text-slate-900">Validator OBE</div>
                            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                {progress.checks.map((check: any) => (
                                    <div
                                        key={check.key}
                                        className={`rounded-xl border p-3 ${
                                            check.done
                                                ? 'border-emerald-100 bg-emerald-50'
                                                : 'border-amber-100 bg-amber-50'
                                        }`}
                                    >
                                        <div className="flex items-center gap-2">
                                            {check.done
                                                ? <CheckCircle2 className="size-4 text-emerald-600" />
                                                : <CircleAlert className="size-4 text-amber-600" />}
                                            <div className="font-bold text-slate-800">{check.label}</div>
                                        </div>
                                        <p className="mt-2 text-xs leading-5 text-slate-600">{check.message}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </details>

                    <AssessmentEvaluationSection
                        rps={rps}
                        weeks={weeks}
                        cpls={cpls}
                        cpmks={cpmks}
                        subCpmks={subCpmks}
                        assessments={assessments}
                        simulationScores={simulationScores}
                        aiConfigured={ai.configured}
                        aiBusy={aiBusyType === 'assessment_plan'}
                        onGenerateAi={() => generateAi('assessment_plan')}
                        suggestions={assessmentAiSuggestions}
                    />

                    <RtmDocumentSection
                        rps={rps}
                        documentMeta={documentMeta}
                        tasks={tasks}
                        assessments={assessments}
                        subCpmks={subCpmks}
                        bibliography={bibliography}
                        weeks={weeks}
                    />
                </section>


            </div>
        </>
    );
}

function displayCplCode(code: string) {
    const match = String(code || '').match(/(\d+)/);
    return match ? `CPL ${Number(match[1])}` : code;
}

function formatLecturerNames(value: any) {
    const text = safeText(value, '');
    if (!text) return '-';

    return text
        .split(/\s*;\s*|\r?\n/)
        .map((item: string) => item.trim())
        .filter(Boolean)
        .join('\n');
}

function formatIndonesianDate(value: any) {
    if (!value) return '-';

    try {
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(new Date(`${value}T00:00:00`));
    } catch {
        return safeText(value);
    }
}

function normalizeAcademicTerm(value: any) {
    const text = String(value ?? '').trim();

    if (!text) return '';

    return text
        .replace(/^siswa\b/iu, 'Mahasiswa')
        .replace(/\bSiswa\b/g, 'Mahasiswa')
        .replace(/^peserta didik\b/iu, 'Mahasiswa');
}

function PustakaInlineTools({ rpsId, meta, master, aiInstruction }: any) {
    const [open, setOpen] = useState(false);
    const [aiLoading, setAiLoading] = useState(false);

    const form = useForm({
        reference_text: meta?.reference_text ?? '',
        supporting_reference_text: meta?.supporting_reference_text ?? '',
    });

    useEffect(() => {
        form.setData({
            reference_text: meta?.reference_text ?? '',
            supporting_reference_text: meta?.supporting_reference_text ?? '',
        });
    }, [meta?.reference_text, meta?.supporting_reference_text]);

    const save = () => {
        form.put(
            `/rps/${rpsId}/document-meta`,
            actionOptions('Pustaka berhasil diperbarui.', () => setOpen(false)),
        );
    };

    const takeMaster = () => {
        if (!master?.reference_text) {
            notify('info', 'Pustaka master kurikulum belum tersedia.');
            return;
        }

        form.setData({
            reference_text: master.reference_text ?? '',
            supporting_reference_text: master.supporting_reference_text ?? '',
        });

        setOpen(true);
        notify('info', 'Pustaka kurikulum dimasukkan ke editor. Periksa lalu klik Simpan.');
    };

    const aiReview = () => {
        setAiLoading(true);

        router.post(
            `/rps/${rpsId}/document-meta/ai-references`,
            { instruction: aiInstruction },
            {
                preserveScroll: true,
                onSuccess: () => notify(
                    'success',
                    'Pustaka berhasil ditelaah AI berdasarkan Bahan Kajian aktif.',
                ),
                onError: (errors: any) => notify('error', firstError(errors)),
                onFinish: () => setAiLoading(false),
            },
        );
    };

    return (
        <div className="w-full print:hidden">
            <div className="flex flex-wrap items-center justify-end gap-1.5">
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className={`inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-[10px] font-bold transition ${
                        open
                            ? 'border-teal-300 bg-teal-50 text-teal-800'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    }`}
                >
                    <Pencil className="size-3" />
                    {open ? 'Tutup Editor' : 'Edit Pustaka'}
                </button>

                <button
                    type="button"
                    onClick={takeMaster}
                    className="inline-flex items-center rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-[10px] font-bold text-teal-700 transition hover:bg-teal-100"
                >
                    Ambil dari Kurikulum
                </button>

                <button
                    type="button"
                    onClick={aiReview}
                    disabled={aiLoading}
                    className="inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2.5 py-1.5 text-[10px] font-bold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Sparkles className="size-3" />
                    {aiLoading ? 'Menelaah...' : 'Telaah Pustaka AI'}
                </button>
            </div>

            {open && (
                <div className="mt-2 w-full overflow-hidden rounded-lg border border-teal-200 bg-slate-50/80 shadow-sm">
                    <div className="grid grid-cols-1 divide-y divide-slate-200 xl:grid-cols-2 xl:divide-x xl:divide-y-0">
                        <label className="block p-3">
                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                <span className="text-[10px] font-bold text-slate-700">
                                    Pustaka Utama
                                </span>
                                <span className="text-[9px] text-slate-400">
                                    Satu referensi per baris
                                </span>
                            </div>
                            <textarea
                                value={form.data.reference_text}
                                onChange={(e) => form.setData('reference_text', e.target.value)}
                                placeholder="Contoh: Penulis. Tahun. Judul. Penerbit/Jurnal."
                                className="h-36 w-full resize-y rounded-md border border-slate-200 bg-white px-3 py-2 text-[11px] leading-5 text-slate-700 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                            />
                        </label>

                        <label className="block p-3">
                            <div className="mb-1.5 flex items-center justify-between gap-2">
                                <span className="text-[10px] font-bold text-slate-700">
                                    Pustaka Pendukung
                                </span>
                                <span className="text-[9px] text-slate-400">
                                    Opsional
                                </span>
                            </div>
                            <textarea
                                value={form.data.supporting_reference_text}
                                onChange={(e) => form.setData('supporting_reference_text', e.target.value)}
                                placeholder="Tambahkan pustaka pendukung bila diperlukan."
                                className="h-36 w-full resize-y rounded-md border border-slate-200 bg-white px-3 py-2 text-[11px] leading-5 text-slate-700 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                            />
                        </label>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-white px-3 py-2">
                        <div className="text-[9px] leading-4 text-slate-500">
                            AI menelaah pustaka berdasarkan Bahan Kajian, Sub-CPMK, dan Preferensi AI aktif.
                        </div>

                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-600 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                disabled={form.processing}
                                onClick={save}
                                className="rounded-md bg-teal-700 px-3.5 py-1.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {form.processing ? 'Menyimpan...' : 'Simpan Pustaka'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function DocumentMetaEditor({ rpsId, meta, master, aiInstruction }: any) {
    const [open, setOpen] = useState(false);
    const [aiLoading, setAiLoading] = useState(false);

    const form = useForm({
        course_cluster: meta?.course_cluster ?? '',
        prepared_date: meta?.prepared_date ?? '',
        published_date: meta?.published_date ?? '',
        developer_name: meta?.developer_name ?? '',
        coordinator_name: meta?.coordinator_name ?? '',
        head_program_name: meta?.head_program_name ?? '',
        lecturer_names: meta?.lecturer_names ?? '',
        software_media: meta?.software_media ?? '',
        hardware_media: meta?.hardware_media ?? '',
        prerequisite_text: meta?.prerequisite_text || master?.prerequisite_text || '',
        description_short: meta?.description_short || master?.description || '',
        reference_text: meta?.reference_text ?? '',
        supporting_reference_text: meta?.supporting_reference_text ?? '',
    });

    const useMasterDescription = () => {
        if (!master?.description) {
            notify('info', 'Deskripsi master kurikulum belum tersedia untuk mata kuliah ini.');
            return;
        }

        form.setData('description_short', master.description);
        notify('info', 'Deskripsi master kurikulum dimasukkan ke form. Klik Simpan Informasi.');
    };

    const useMasterReferences = () => {
        if (!master?.reference_text) {
            notify('info', 'Pustaka master kurikulum belum tersedia untuk mata kuliah ini.');
            return;
        }

        form.setData('reference_text', master.reference_text);
        form.setData('supporting_reference_text', master.supporting_reference_text || '');
        notify('info', 'Pustaka master kurikulum dimasukkan ke Pustaka Utama/Pendukung. Klik Simpan Informasi.');
    };

    const useAiReferences = () => {
        setAiLoading(true);

        router.post(
            `/rps/${rpsId}/document-meta/ai-references`,
            { instruction: aiInstruction },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    const refreshed = page?.props?.documentMeta ?? {};
                    form.setData('reference_text', refreshed.reference_text || '');
                    form.setData('supporting_reference_text', refreshed.supporting_reference_text || '');
                    notify('success', 'Pustaka telah ditelaah AI dan disesuaikan dengan bahan kajian.');
                },
                onError: (errors: any) => notify('error', firstError(errors)),
                onFinish: () => setAiLoading(false),
            },
        );
    };

    return (
        <div className="mx-auto mb-3 max-w-[1500px] print:hidden">
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="inline-flex items-center gap-2 rounded-xl border border-teal-700 bg-teal-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-teal-700"
                >
                    <Pencil className="size-3.5" />
                    {open ? 'Tutup Informasi RPS' : 'Edit Informasi RPS'}
                </button>
            </div>

            {open && (
                <div className="mt-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_14px_40px_rgba(15,23,42,0.08)]">
                    <div className="grid gap-3 md:grid-cols-3">
                        <MetaInput label="Rumpun MK" value={form.data.course_cluster} onChange={(v: string) => form.setData('course_cluster', v)} />
                        <MetaInput label="Tanggal Penyusunan" type="date" value={form.data.prepared_date} onChange={(v: string) => form.setData('prepared_date', v)} />
                        <MetaInput label="Tanggal Terbit" type="date" value={form.data.published_date} onChange={(v: string) => form.setData('published_date', v)} />
                        <MetaInput label="Koordinator Pengembang RPS" value={form.data.developer_name} onChange={(v: string) => form.setData('developer_name', v)} />
                        <MetaInput label="Koordinator Mata Kuliah" value={form.data.coordinator_name} onChange={(v: string) => form.setData('coordinator_name', v)} />
                        <MetaInput label="Koordinator Program Studi" value={form.data.head_program_name} onChange={(v: string) => form.setData('head_program_name', v)} />
                        <label className="md:col-span-1">
                            <span className="mb-1 block text-xs font-bold text-slate-500">Dosen Pengampu</span>
                            <textarea
                                value={form.data.lecturer_names}
                                onChange={(e) => form.setData('lecturer_names', e.target.value)}
                                placeholder={"Satu dosen per baris\nNama Dosen 1\nNama Dosen 2"}
                                className="min-h-20 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                            <span className="mt-1 block text-[10px] text-slate-400">Bisa lebih dari satu; tulis satu nama per baris.</span>
                        </label>
                        <MetaInput label="Perangkat Lunak" value={form.data.software_media} onChange={(v: string) => form.setData('software_media', v)} />
                        <MetaInput label="Perangkat Keras" value={form.data.hardware_media} onChange={(v: string) => form.setData('hardware_media', v)} />
                        <MetaInput label="Mata Kuliah Syarat" value={form.data.prerequisite_text} onChange={(v: string) => form.setData('prerequisite_text', v)} />
                    </div>

                    <label className="mt-4 block">
                        <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                            <span className="text-xs font-bold text-slate-500">Deskripsi Singkat MK</span>
                            <button
                                type="button"
                                onClick={useMasterDescription}
                                className="rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700"
                            >
                                Ambil dari Master Kurikulum
                            </button>
                        </div>
                        <textarea
                            value={form.data.description_short}
                            onChange={(e) => form.setData('description_short', e.target.value)}
                            placeholder="Deskripsi singkat mata kuliah..."
                            className="min-h-28 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-100"
                        />
                    </label>

                    <div className="mt-4 grid gap-3 lg:grid-cols-2">
                        <label className="block">
                            <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span className="text-xs font-bold text-slate-500">Pustaka Utama</span>
                                    <div className="text-[10px] text-slate-400">Satu pustaka per baris.</div>
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <button
                                        type="button"
                                        onClick={useMasterReferences}
                                        className="rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700"
                                    >
                                        Ambil dari Kurikulum
                                    </button>
                                    <button
                                        type="button"
                                        onClick={useAiReferences}
                                        disabled={aiLoading}
                                        className="rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700 disabled:opacity-50"
                                    >
                                        {aiLoading ? 'AI...' : 'Telaah Pustaka AI'}
                                    </button>
                                </div>
                            </div>
                            <textarea
                                value={form.data.reference_text}
                                onChange={(e) => form.setData('reference_text', e.target.value)}
                                placeholder={"Pustaka utama 1\nPustaka utama 2"}
                                className="min-h-36 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-xs leading-5 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-100"
                            />
                        </label>

                        <label className="block">
                            <div className="mb-1">
                                <span className="text-xs font-bold text-slate-500">Pustaka Pendukung</span>
                                <div className="text-[10px] text-slate-400">
                                    Opsional. Penomoran melanjutkan Pustaka Utama.
                                </div>
                            </div>
                            <textarea
                                value={form.data.supporting_reference_text}
                                onChange={(e) => form.setData('supporting_reference_text', e.target.value)}
                                placeholder={"Pustaka pendukung 1\nPustaka pendukung 2"}
                                className="min-h-36 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-xs leading-5 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-100"
                            />
                        </label>
                    </div>

                    <div className="mt-4 flex justify-end">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() => form.put(
                                `/rps/${rpsId}/document-meta`,
                                actionOptions('Informasi RPS berhasil diperbarui.', () => setOpen(false)),
                            )}
                            className="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Informasi'}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function MetaInput({ label, value, onChange, type = 'text' }: any) {
    return (
        <label>
            <span className="mb-1 block text-xs font-bold text-slate-500">{label}</span>
            <input
                type={type}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            />
        </label>
    );
}

function SectionAiButton({
    label,
    busy,
    disabled,
    onClick,
    suggestions = [],
    rpsId,
}: any) {
    return (
        <div className="print:hidden">
            <div className="flex flex-wrap items-center justify-end gap-1.5">
                <button
                    type="button"
                    disabled={disabled || busy}
                    onClick={onClick}
                    className="inline-flex items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <Sparkles className="size-3" />
                    {busy ? 'AI...' : label}
                </button>

                {suggestions.length > 0 && (
                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9px] font-bold text-emerald-700">
                        {suggestions.length} usulan
                    </span>
                )}
            </div>

            {suggestions.length > 0 && (
                <details className="mt-2 rounded-xl border border-emerald-100 bg-emerald-50/30 p-2">
                    <summary className="cursor-pointer text-[10px] font-bold text-emerald-700">
                        Lihat rekomendasi AI
                    </summary>
                    <div className="mt-2 space-y-2">
                        {suggestions.map((suggestion: any) => (
                            <AiSuggestionCard
                                key={suggestion.id}
                                suggestion={suggestion}
                                rpsId={rpsId}
                            />
                        ))}
                    </div>
                </details>
            )}
        </div>
    );
}

function CplScopeQuickEditor({
    rpsId,
    allCpls,
    officialCplIds,
    additionalCplIds,
}: any) {
    return (
        <details>
            <summary className="cursor-pointer text-[10px] font-bold text-sky-700">
                Atur Scope CPL RPS
            </summary>
            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
                {allCpls.map((cpl: any) => {
                    const official = officialCplIds.includes(cpl.id);
                    const added = additionalCplIds.includes(cpl.id);

                    return (
                        <div
                            key={cpl.id}
                            className="group rounded-xl border border-slate-100 bg-white px-2.5 py-2 transition hover:border-sky-200 hover:bg-sky-50/40"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-semibold text-slate-700">{cpl.code}</span>
                                {official ? (
                                    <span className="text-[9px] font-bold text-emerald-700">Kurikulum</span>
                                ) : added ? (
                                    <button
                                        type="button"
                                        onClick={() => router.delete(
                                            `/rps/${rpsId}/cpl-scope/${cpl.id}`,
                                            actionOptions(`${cpl.code} dihapus dari scope RPS.`),
                                        )}
                                        className="text-[9px] font-bold text-rose-600"
                                    >
                                        Hapus
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => router.post(
                                            `/rps/${rpsId}/cpl-scope`,
                                            { cpl_id: cpl.id },
                                            actionOptions(`${cpl.code} ditambahkan ke scope RPS.`),
                                        )}
                                        className="text-[9px] font-bold text-sky-700"
                                    >
                                        + Tambah
                                    </button>
                                )}
                            </div>

                            <div className="max-h-0 overflow-hidden text-[10px] leading-4 text-slate-500 opacity-0 transition-all duration-200 group-hover:mt-1.5 group-hover:max-h-32 group-hover:opacity-100">
                                {cpl.description}
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="mt-1 text-[9px] text-slate-400">
                Arahkan kursor ke CPL untuk melihat narasi lengkap.
            </div>
        </details>
    );
}



function assessmentShortLabel(assessment: any, position: number) {
    const code = String(assessment?.code || '').toUpperCase();

    if (code === 'UTS' || assessment?.type === 'uts') return 'UTS';
    if (code === 'UAS' || assessment?.type === 'uas') return 'UAS';

    const type = String(assessment?.type || '').toLowerCase();

    if (type === 'quiz') return `Kuis ${position}`;
    if (type === 'project') return `Proyek ${position}`;
    if (type === 'practicum') return `Praktikum ${position}`;
    if (type === 'presentation') return `Presentasi ${position}`;

    return `Tugas ${position}`;
}

function allocatedAssessmentWeight(assessment: any, subId: string) {
    const ids = safeList(assessment?.sub_cpmk_ids);

    if (!ids.includes(subId) || ids.length === 0) return 0;

    return Number(assessment?.weight || 0) / ids.length;
}

function gradeLetter(score: number) {
    if (score >= 85) return 'A';
    if (score >= 80) return 'A-';
    if (score >= 75) return 'B+';
    if (score >= 70) return 'B';
    if (score >= 65) return 'B-';
    if (score >= 50) return 'C';
    if (score >= 40) return 'D';
    return 'E';
}

function defaultSimulationScore(week: number) {
    // Nilai contoh yang stabil (tidak berubah setiap render),
    // tetapi tampak bervariasi seperti data simulasi.
    return 72 + ((week * 11 + 7) % 24); // 72..95
}

function SimulationScoreInput({
    rpsId,
    week,
    value,
    disabled = false,
}: any) {
    if (disabled) {
        return (
            <span className="inline-flex min-w-16 items-center justify-center rounded-md bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-400">
                —
            </span>
        );
    }

    const effective = value === null || value === undefined
        ? defaultSimulationScore(Number(week))
        : Number(value);

    const original = String(effective);

    const form = useForm({
        score: original,
    });

    const save = () => {
        if (String(form.data.score) === original) return;

        form.put(
            `/rps/${rpsId}/simulation/${week}`,
            {
                preserveScroll: true,
                onSuccess: () => notify('success', `Nilai simulasi pekan ${week} disimpan.`),
                onError: (errors) => {
                    form.setData('score', original);
                    notify('error', firstError(errors));
                },
            },
        );
    };

    return (
        <>
            <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={form.data.score}
                onChange={(e) => form.setData('score', e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        (e.currentTarget as HTMLInputElement).blur();
                    }
                }}
                className="w-20 rounded border border-slate-200 bg-white px-1.5 py-1 text-center text-[10px] font-semibold print:hidden"
                placeholder="0-100"
            />
            <span className="hidden print:inline">
                {form.data.score === '' ? '-' : form.data.score}
            </span>
        </>
    );
}

function AssessmentMatrixLinkCell({ rpsId, assessment, subId }: any) {
    const [busy, setBusy] = useState(false);
    const linkedIds = safeList(assessment.sub_cpmk_ids);
    const linked = linkedIds.includes(subId);
    const linkedCount = Math.max(1, linkedIds.length);
    const value = linked ? Number(assessment.weight || 0) / linkedCount : 0;

    const toggle = () => {
        if (busy) return;

        const next = linked
            ? linkedIds.filter((id: string) => id !== subId)
            : [...linkedIds, subId];

        setBusy(true);
        router.put(
            `/rps/${rpsId}/assessments/${assessment.id}/matrix`,
            { sub_cpmk_ids: next },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => notify('success', `${assessment.name}: cakupan Sub-CPMK diperbarui.`),
                onError: (errors: any) => notify('error', firstError(errors)),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <button
                type="button"
                onClick={toggle}
                disabled={busy}
                title={linked ? 'Klik untuk melepas keterkaitan Sub-CPMK' : 'Klik untuk menghubungkan Sub-CPMK'}
                className={`mx-auto flex min-h-8 w-full items-center justify-center rounded-md px-1 py-1 font-bold print:hidden ${
                    linked
                        ? 'bg-cyan-100 text-cyan-800 hover:bg-cyan-200'
                        : 'bg-white text-slate-300 hover:bg-slate-50 hover:text-slate-500'
                } disabled:opacity-50`}
            >
                {busy ? '…' : linked ? Number(value.toFixed(2)) : '+'}
            </button>
            <span className="hidden print:inline">{linked ? Number(value.toFixed(2)) : ''}</span>
        </>
    );
}

function AssessmentMatrixWeightInput({ rpsId, assessment }: any) {
    const original = String(Number(assessment.weight || 0));
    const [value, setValue] = useState(original);
    const [busy, setBusy] = useState(false);

    useEffect(() => setValue(original), [original]);

    const save = () => {
        if (busy || value === original || value === '') return;

        setBusy(true);
        router.put(
            `/rps/${rpsId}/assessments/${assessment.id}/matrix`,
            { weight: Number(value) },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => notify('success', `Bobot ${assessment.name} disimpan.`),
                onError: (errors: any) => {
                    setValue(original);
                    notify('error', firstError(errors));
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                onBlur={save}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        (event.currentTarget as HTMLInputElement).blur();
                    }
                }}
                className="mx-auto w-16 rounded-md border border-slate-200 bg-white px-1.5 py-1 text-center text-[11px] font-bold text-slate-800 print:hidden"
                title="Edit bobot asesmen"
            />
            <span className="hidden print:inline">{Number(assessment.weight || 0) || ''}</span>
        </>
    );
}

function AssessmentMatrixNameInput({ rpsId, assessment, compactLabel }: any) {
    const original = String(assessment.name || '');
    const [value, setValue] = useState(original);
    const [busy, setBusy] = useState(false);

    useEffect(() => setValue(original), [original]);

    const save = () => {
        const clean = value.trim();
        if (busy || clean === '' || clean === original) return;

        setBusy(true);
        router.put(
            `/rps/${rpsId}/assessments/${assessment.id}/matrix`,
            { name: clean },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => notify('success', 'Nama asesmen diperbarui.'),
                onError: (errors: any) => {
                    setValue(original);
                    notify('error', firstError(errors));
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <div className="print:hidden">
                <input
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onBlur={save}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            (event.currentTarget as HTMLInputElement).blur();
                        }
                    }}
                    className="w-28 rounded-md border border-teal-200 bg-white/90 px-1.5 py-1 text-center text-[10px] font-bold text-slate-800"
                    title="Edit nama asesmen"
                />
                <div className="mt-1 text-[9px] font-semibold text-teal-700">{compactLabel}</div>
            </div>
            <span className="hidden print:inline">{compactLabel}</span>
        </>
    );
}

function SimulationWeightInput({ rpsId, week, value }: any) {
    if ([8, 16].includes(Number(week))) {
        return <span className="font-bold text-slate-700">{Number(value || 0) || '—'}</span>;
    }

    const numericOriginal = Number(value || 0);
    const original = numericOriginal > 0 ? String(numericOriginal) : '';
    const [weight, setWeight] = useState(original);
    const [busy, setBusy] = useState(false);

    useEffect(() => setWeight(original), [original]);

    const save = () => {
        if (busy || weight === original) return;

        const nextWeight = weight === '' ? 0 : Number(weight);

        setBusy(true);
        router.put(
            `/rps/${rpsId}/weeks/${week}/weight`,
            { weight: nextWeight },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => notify('success', `Bobot simulasi pekan ${week} disimpan.`),
                onError: (errors: any) => {
                    setWeight(original);
                    notify('error', firstError(errors));
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={weight}
                onChange={(event) => setWeight(event.target.value)}
                onBlur={save}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        (event.currentTarget as HTMLInputElement).blur();
                    }
                }}
                placeholder="—"
                className="mx-auto w-16 rounded-md border border-slate-200 bg-white px-1.5 py-1 text-center text-[11px] font-bold print:hidden"
                title="Bobot pengukuran pekan. Setiap pekan pembelajaran yang memuat Sub-CPMK sebaiknya memiliki bobot positif."
            />
            <span className="hidden print:inline">{Number(value || 0) > 0 ? Number(value) : ''}</span>
        </>
    );
}

function AssessmentEvaluationSection({
    rps,
    weeks,
    cpls,
    cpmks,
    subCpmks,
    assessments,
    simulationScores,
    aiConfigured,
    aiBusy,
    onGenerateAi,
    suggestions,
}: any) {
    const orderedAssessments = [...assessments].sort((a: any, b: any) => {
        const aw = Number(a.week_number || 99);
        const bw = Number(b.week_number || 99);

        if (aw !== bw) return aw - bw;
        return String(a.code || '').localeCompare(String(b.code || ''));
    });

    const nonExam = orderedAssessments.filter(
        (item: any) => !['UTS', 'UAS'].includes(String(item.code || '').toUpperCase())
            && !['uts', 'uas'].includes(String(item.type || '').toLowerCase()),
    );

    const labelById = new Map<string, string>();
    let taskNo = 0;

    orderedAssessments.forEach((assessment: any) => {
        const isExam = ['UTS', 'UAS'].includes(String(assessment.code || '').toUpperCase())
            || ['uts', 'uas'].includes(String(assessment.type || '').toLowerCase());

        if (!isExam) taskNo += 1;

        labelById.set(
            assessment.id,
            assessmentShortLabel(assessment, isExam ? 0 : taskNo),
        );
    });

    const totalAssessmentWeight = orderedAssessments.reduce(
        (sum: number, item: any) => sum + Number(item.weight || 0),
        0,
    );

    const subById = new Map(subCpmks.map((sub: any) => [sub.id, sub]));
    const cpmkById = new Map(cpmks.map((cpmk: any) => [cpmk.id, cpmk]));
    const cplById = new Map(cpls.map((cpl: any) => [cpl.id, cpl]));

    const totalWeeklyWeight = weeks.reduce(
        (sum: number, week: any) => sum + Number(week.assessment_weight || 0),
        0,
    );

    const totalSimulationScore = weeks.reduce((sum: number, week: any) => {
        const weight = Number(week.assessment_weight || 0);

        if (weight <= 0) return sum;

        const score = simulationScores?.[week.week_number] === null
            || simulationScores?.[week.week_number] === undefined
                ? defaultSimulationScore(Number(week.week_number))
                : Number(simulationScores?.[week.week_number]);

        return sum + ((score * weight) / 100);
    }, 0);

    return (
        <div className="border-x border-b border-slate-300 bg-white">
            <div className="border-t-2 border-slate-300 px-3 py-4">
                <div className="mb-3">
                    <div className="text-center">
                        <div className="text-sm font-black uppercase tracking-wide text-slate-900">
                            Tabel Penilaian dan Evaluasi CPL
                        </div>
                        <div className="text-xs font-bold uppercase text-slate-700">
                            Mata Kuliah {rps.course_name}
                        </div>
                    </div>


                </div>

                {orderedAssessments.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-[850px] w-full border-collapse text-[11px]">
                            <thead className="bg-teal-100 text-slate-900">
                                <tr>
                                    <th rowSpan={2} className="border border-slate-300 px-2 py-2">CPMK</th>
                                    <th rowSpan={2} className="border border-slate-300 px-2 py-2">SUB-CPMK</th>
                                    <th colSpan={orderedAssessments.length} className="border border-slate-300 px-2 py-2 text-center">
                                        Bobot per Bentuk Penilaian
                                    </th>
                                    <th rowSpan={2} className="border border-slate-300 px-2 py-2">Total Bobot<br />Per Sub-CPMK</th>
                                </tr>
                                <tr>
                                    {orderedAssessments.map((assessment: any) => (
                                        <th
                                            key={assessment.id}
                                            title={assessment.name}
                                            className="min-w-[76px] border border-slate-300 px-2 py-2 text-center"
                                        >
                                            <AssessmentMatrixNameInput
                                                rpsId={rps.id}
                                                assessment={assessment}
                                                compactLabel={labelById.get(assessment.id)}
                                            />
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {subCpmks.map((sub: any) => {
                                    const parentId = safeList(sub.cpmk_ids)[0];
                                    const parent = parentId ? cpmkById.get(parentId) : null;

                                    const rowTotal = orderedAssessments.reduce(
                                        (sum: number, assessment: any) =>
                                            sum + allocatedAssessmentWeight(assessment, sub.id),
                                        0,
                                    );

                                    return (
                                        <tr key={sub.id}>
                                            <td className="border border-slate-300 px-2 py-1.5 text-center font-semibold">
                                                {parent?.code || '-'}
                                            </td>
                                            <td className="border border-slate-300 px-2 py-1.5">
                                                {sub.code}
                                            </td>
                                            {orderedAssessments.map((assessment: any) => {
                                                const value = allocatedAssessmentWeight(assessment, sub.id);

                                                return (
                                                    <td key={assessment.id} className="border border-slate-300 px-1 py-1 text-center">
                                                        <AssessmentMatrixLinkCell
                                                            rpsId={rps.id}
                                                            assessment={assessment}
                                                            subId={sub.id}
                                                        />
                                                    </td>
                                                );
                                            })}
                                            <td className="border border-slate-300 bg-slate-50 px-2 py-1.5 text-center font-bold">
                                                {Number(rowTotal.toFixed(2))}
                                            </td>
                                        </tr>
                                    );
                                })}
                                <tr className="font-bold">
                                    <td colSpan={2} className="border border-slate-300 bg-slate-50 px-2 py-1.5 text-center">Total</td>
                                    {orderedAssessments.map((assessment: any) => (
                                        <td key={assessment.id} className="border border-slate-300 bg-slate-50 px-1 py-1 text-center">
                                            <AssessmentMatrixWeightInput rpsId={rps.id} assessment={assessment} />
                                        </td>
                                    ))}
                                    <td className={`border border-slate-300 px-2 py-1.5 text-center ${
                                        Math.abs(totalAssessmentWeight - 100) < 0.01
                                            ? 'bg-emerald-50 text-emerald-800'
                                            : 'bg-amber-50 text-amber-800'
                                    }`}>
                                        {Number(totalAssessmentWeight.toFixed(2))}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div className="mt-1 text-[9px] text-slate-400 print:hidden">
                            Klik sel matriks untuk menghubungkan/melepas Sub-CPMK. Nama dan bobot pada matriks adalah rekap asesmen agregat. Bobot 14 pekan pada tabel RPS adalah distribusi dari bobot non-UTS/UAS tersebut; jangan menjumlahkan kedua tabel sebagai dua bobot terpisah.
                        </div>
                    </div>
                ) : (
                    <div className="rounded-lg border border-dashed border-amber-200 bg-amber-50 px-4 py-5 text-center text-xs text-amber-700">
                        Belum ada asesmen. Tambahkan asesmen pada panel Edit Detail Asesmen & RTM atau gunakan rekomendasi AI.
                    </div>
                )}

                <div className="mt-5 text-center text-xs font-black text-slate-900">Simulasi</div>
                <div className="mx-auto mt-1 max-w-4xl text-center text-[9px] leading-4 text-slate-500 print:hidden">
                    Setiap pekan pembelajaran yang memuat Sub-CPMK harus memiliki bobot sebagai bukti pengukuran.
                    Bobot non-UTS/UAS merupakan distribusi dari tag Sub-CPMK pada asesmen agregat; bila satu Sub-CPMK digunakan beberapa pekan, anggarannya dibagi ke pekan-pekan tersebut. Nama asesmen pada simulasi mengikuti tag yang sama.
                    UTS dan UAS tetap mengikuti bobot asesmen sistem.
                </div>

                <div className="mt-2 overflow-x-auto">
                    <table className="min-w-[900px] w-full border-collapse text-[11px]">
                        <thead className="bg-cyan-50">
                            <tr>
                                <th className="border border-slate-300 px-2 py-2">Pekan</th>
                                <th className="border border-slate-300 px-2 py-2">CPL</th>
                                <th className="border border-slate-300 px-2 py-2">CPMK</th>
                                <th className="border border-slate-300 px-2 py-2">Sub-CPMK</th>
                                <th className="border border-slate-300 px-2 py-2">Soal / Bentuk Penilaian</th>
                                <th className="border border-slate-300 px-2 py-2">Bobot (%)</th>
                                <th className="border border-slate-300 px-2 py-2">Nilai Mhs<br />(0-100)</th>
                                <th className="border border-slate-300 px-2 py-2">(Nilai Mhs) × (Bobot%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {weeks.map((week: any) => {
                                const sub = week.rps_sub_cpmk_id
                                    ? subById.get(week.rps_sub_cpmk_id)
                                    : null;

                                const parentIds = safeList(sub?.cpmk_ids);
                                const parents = parentIds
                                    .map((id: string) => cpmkById.get(id))
                                    .filter(Boolean);

                                const cplCodes = Array.from(new Set(
                                    parents.flatMap((parent: any) =>
                                        safeList(parent.cpl_ids)
                                            .map((id: string) => cplById.get(id)?.code)
                                            .filter(Boolean),
                                    ),
                                ));

                                const weekWeight = Number(week.assessment_weight || 0);
                                const savedScore = simulationScores?.[week.week_number];
                                const numericScore = weekWeight > 0
                                    ? (
                                        savedScore === null || savedScore === undefined
                                            ? defaultSimulationScore(Number(week.week_number))
                                            : Number(savedScore)
                                    )
                                    : null;

                                const weighted = weekWeight > 0 && numericScore !== null
                                    ? (numericScore * weekWeight) / 100
                                    : null;

                                const question = week.is_exam
                                    ? (week.exam_type === 'UTS' ? 'UJIAN TENGAH SEMESTER' : 'UJIAN AKHIR SEMESTER')
                                    : (week.assessment_names || week.student_assignment || '-');

                                return (
                                    <tr key={week.week_number} className={week.is_exam ? 'bg-teal-50 font-bold' : ''}>
                                        <td className="border border-slate-300 px-2 py-1 text-center">{week.week_number}</td>
                                        <td className="border border-slate-300 px-2 py-1 text-center">{cplCodes.join(', ') || '-'}</td>
                                        <td className="border border-slate-300 px-2 py-1 text-center">{parents.map((p: any) => p.code).join(', ') || '-'}</td>
                                        <td className="border border-slate-300 px-2 py-1">{sub?.code || '-'}</td>
                                        <td className="border border-slate-300 px-2 py-1">{question}</td>
                                        <td className="border border-slate-300 px-2 py-1 text-center">
                                            <SimulationWeightInput
                                                rpsId={rps.id}
                                                week={week.week_number}
                                                value={week.assessment_weight}
                                            />
                                        </td>
                                        <td className="border border-slate-300 px-2 py-1 text-center">
                                            <SimulationScoreInput
                                                rpsId={rps.id}
                                                week={week.week_number}
                                                value={savedScore}
                                                disabled={weekWeight <= 0}
                                            />
                                        </td>
                                        <td className="border border-slate-300 px-2 py-1 text-center">
                                            {weighted === null ? '' : Number(weighted.toFixed(2))}
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr className="bg-cyan-50 font-black">
                                <td colSpan={5} className="border border-slate-300 px-2 py-1.5 text-right">TOTAL NILAI AKHIR</td>
                                <td className="border border-slate-300 px-2 py-1.5 text-center">
                                    {Number(totalWeeklyWeight.toFixed(2))}
                                </td>
                                <td className="border border-slate-300 bg-slate-900 px-2 py-1.5" />
                                <td className="border border-slate-300 px-2 py-1.5 text-center">
                                    {Number(totalSimulationScore.toFixed(2))}
                                </td>
                            </tr>
                            <tr className="bg-cyan-50 font-black">
                                <td colSpan={7} className="border border-slate-300 px-2 py-1.5 text-right">HURUF MUTU</td>
                                <td className="border border-slate-300 px-2 py-1.5 text-center">
                                    {Math.abs(totalWeeklyWeight - 100) < 0.01
                                        ? gradeLetter(totalSimulationScore)
                                        : `Menunggu 100% (${Number(totalWeeklyWeight.toFixed(2))}%)`}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div className="mt-5 rounded-lg border border-slate-200 bg-white p-4">
                    <p className="text-[10px] leading-5 text-slate-700">
                        Penilaian pembelajaran mengikuti standar mutu penilaian pembelajaran yang digunakan pada template RPS:
                    </p>

                    <table className="mx-auto mt-3 border-collapse text-[11px]">
                        <thead>
                            <tr>
                                <th className="px-4 py-1 text-center">Nilai Angka</th>
                                <th className="px-4 py-1 text-center">Nilai Huruf</th>
                                <th className="px-4 py-1 text-center">Nilai Mutu</th>
                            </tr>
                        </thead>
                        <tbody>
                            {[
                                ['85 – 100', 'A', '4,00'],
                                ['80 - < 85', 'A-', '3,75'],
                                ['75 - < 80', 'B+', '3,50'],
                                ['70 - < 75', 'B', '3,00'],
                                ['65 - < 70', 'B-', '2,75'],
                                ['50 - < 65', 'C', '2,00'],
                                ['40 - < 50', 'D', '1,00'],
                                ['< 40', 'E', '0'],
                            ].map((row) => (
                                <tr key={row[1]}>
                                    <td className="px-4 py-0.5 text-center">{row[0]}</td>
                                    <td className="px-4 py-0.5 text-center">{row[1]}</td>
                                    <td className="px-4 py-0.5 text-center">{row[2]}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <p className="mt-4 text-[10px] leading-5 text-slate-700">
                        Nilai A, A-, B+, B, B-, C, D adalah nilai lulus, sedangkan nilai E adalah nilai tidak lulus.
                    </p>
                </div>
            </div>
        </div>
    );
}

function taskTypeLabel(type: string) {
    const map: Record<string, string> = {
        assignment: 'Tugas',
        project: 'Proyek',
        practicum: 'Praktikum',
        presentation: 'Presentasi',
        other: 'Tugas',
    };

    return map[type] || 'Tugas';
}

function RtmDocumentSection({
    rps,
    documentMeta,
    tasks,
    assessments,
    subCpmks,
    bibliography,
    weeks,
}: any) {
    const assessmentById = new Map(assessments.map((item: any) => [item.id, item]));
    const weekByNumber = new Map(weeks.map((item: any) => [Number(item.week_number), item]));
    const subById = new Map(subCpmks.map((item: any) => [item.id, item]));
    const [editingTaskId, setEditingTaskId] = useState<string | null>(null);

    return (
        <div className="border-x border-b border-slate-300 bg-white px-3 pb-5">
            <div className="border-t-2 border-slate-400 pt-4">
                <div className="text-center text-base font-black uppercase text-slate-900">
                    Lembar Rencana Tugas Mahasiswa
                </div>
                <div className="text-center text-sm font-bold uppercase text-slate-700">
                    Mata Kuliah {rps.course_name}
                </div>

                {tasks.length === 0 ? (
                    <div className="mt-3 rounded-lg border border-dashed border-amber-200 bg-slate-50 px-4 py-5 text-center text-xs text-amber-700">
                        Belum ada RTM. Tambahkan RTM pada panel Edit Detail Asesmen & RTM atau gunakan Telaah Asesmen + RTM AI pada panel Edit Detail Asesmen, RTM & Validator OBE.
                    </div>
                ) : (
                    <div className="mt-4 space-y-6">
                        {tasks.map((task: any, index: number) => {
                            const assessment = task.assessment_id
                                ? assessmentById.get(task.assessment_id)
                                : null;

                            const linkedSubs = safeList(task.sub_cpmk_ids)
                                .map((id: string) => subById.get(id))
                                .filter(Boolean)
                                .sort((a: any, b: any) => {
                                    const seqA = Number(a?.sequence_no ?? 9999);
                                    const seqB = Number(b?.sequence_no ?? 9999);

                                    if (seqA !== seqB) return seqA - seqB;

                                    return String(a?.code ?? '').localeCompare(
                                        String(b?.code ?? ''),
                                        undefined,
                                        { numeric: true },
                                    );
                                });

                            return (
                                <div
                                    key={task.id}
                                    className="break-inside-avoid overflow-hidden rounded-lg border border-slate-300 bg-white"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-cyan-200 bg-gradient-to-r from-cyan-100 via-sky-50 to-teal-100 px-3 py-2.5 print:hidden">
                                        <div>
                                            <div className="text-[11px] font-black text-slate-800">
                                                {task.code || `RTM-${index + 1}`} · {task.title}
                                            </div>
                                            <div className="text-[10px] text-slate-500">
                                                Semua isi lembar RTM dapat diubah melalui editor ini.
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setEditingTaskId(editingTaskId === task.id ? null : task.id)}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-3.5 py-2 text-[11px] font-extrabold text-white shadow-sm hover:bg-teal-800"
                                        >
                                            <Pencil className="size-3.5" />
                                            {editingTaskId === task.id ? 'Tutup Editor RTM' : 'Edit Isi RTM'}
                                        </button>
                                    </div>

                                    {editingTaskId === task.id && (
                                        <div className="border-b border-teal-100 bg-teal-50/30 p-3 print:hidden">
                                            <TaskCard
                                                rpsId={rps.id}
                                                task={task}
                                                assessments={assessments}
                                                subCpmks={subCpmks}
                                                initialEditing
                                                onDone={() => setEditingTaskId(null)}
                                            />
                                        </div>
                                    )}

                                    <table className="w-full border-collapse font-sans text-[11px] leading-[1.45]">
                                        <tbody>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 bg-gradient-to-r from-teal-50 via-cyan-50 to-sky-50 p-0">
                                                    <div className="grid min-h-[92px] grid-cols-[95px_1fr_95px] items-center px-3 py-2">
                                                        <div className="flex items-center justify-center">
                                                            <img
                                                                src="/logo-unsulbar.png"
                                                                alt="Logo Universitas Sulawesi Barat"
                                                                className="h-14 w-14 object-contain"
                                                            />
                                                        </div>
                                                        <div className="text-center text-slate-900">
                                                            <div className="text-[12px] font-black leading-5">UNIVERSITAS SULAWESI BARAT</div>
                                                            <div className="text-[12px] font-black leading-5">FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM</div>
                                                            <div className="text-[12px] font-black leading-5">JURUSAN MATEMATIKA</div>
                                                            <div className="text-[12px] font-black leading-5">PROGRAM STUDI MATEMATIKA</div>
                                                        </div>
                                                        <div aria-hidden="true" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-center text-[13px]">
                                                    RENCANA TUGAS MAHASISWA
                                                </th>
                                            </tr>
                                            <tr>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">MATA KULIAH</td>
                                                <td colSpan={3} className="border border-slate-300 px-2 py-1">{rps.course_name}</td>
                                            </tr>
                                            <tr>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">KODE</td>
                                                <td className="border border-slate-300 px-2 py-1">{rps.official_code || rps.system_code}</td>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">SKS</td>
                                                <td className="border border-slate-300 px-2 py-1">{rps.credits}</td>
                                            </tr>
                                            <tr>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">SEMESTER</td>
                                                <td className="border border-slate-300 px-2 py-1">{rps.semester_recommended || '-'}</td>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">PEKAN KE-</td>
                                                <td className="border border-slate-300 px-2 py-1">{task.due_week || '-'}</td>
                                            </tr>
                                            <tr>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">DOSEN PENGAMPU</td>
                                                <td colSpan={3} className="border border-slate-300 px-2 py-1 whitespace-pre-line">{formatLecturerNames(documentMeta.lecturer_names)}</td>
                                            </tr>
                                            <tr>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">BENTUK TUGAS</td>
                                                <td className="border border-slate-300 px-2 py-1">{taskTypeLabel(task.type)}</td>
                                                <td className="border border-slate-300 bg-slate-50 px-2 py-1 font-bold">KODE RTM</td>
                                                <td className="border border-slate-300 px-2 py-1">{task.code || `RTM-${index + 1}`}</td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">JUDUL TUGAS</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5 font-semibold">{task.title}</td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">SUB CAPAIAN PEMBELAJARAN MATA KULIAH</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5">
                                                    {linkedSubs.length > 0 ? linkedSubs.map((sub: any) => (
                                                        <div key={sub.id}>
                                                            <strong>{sub.code}</strong> {sub.description}
                                                        </div>
                                                    )) : '-'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">DESKRIPSI / TUJUAN TUGAS</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5 whitespace-pre-line">
                                                    {task.purpose || '-'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">METODE PENGERJAAN TUGAS</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5 whitespace-pre-line">
                                                    {task.instructions || '-'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">BENTUK DAN FORMAT LUARAN</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5 whitespace-pre-line">
                                                    {task.expected_output || '-'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">INDIKATOR, KRITERIA DAN BOBOT PENILAIAN</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5">
                                                    <div><strong>Bentuk penilaian pekan:</strong> {task.title}</div>
                                                    <div><strong>Kriteria:</strong> {assessment?.description || '-'}</div>
                                                    <div><strong>Bobot pekan:</strong> {`${Number(weekByNumber.get(Number(task.due_week))?.assessment_weight || 0)}%`}</div>
                                                    {assessment && (
                                                        <div className="text-[10px] text-slate-500"><strong>Asesmen agregat:</strong> {assessment.name} ({Number(assessment.weight || 0)}%)</div>
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">JADWAL PELAKSANAAN</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5">
                                                    Pekan {task.due_week || '-'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th colSpan={4} className="border border-slate-300 bg-teal-50 px-2 py-1 text-left">DAFTAR RUJUKAN</th>
                                            </tr>
                                            <tr>
                                                <td colSpan={4} className="border border-slate-300 px-2 py-1.5">
                                                    {bibliography.length > 0
                                                        ? bibliography.map((item: any) => (
                                                            <div key={item.number}>{item.number}. {item.text}</div>
                                                        ))
                                                        : '-'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

function DocumentCpmkAdd({ rpsId }: any) {
    const [open, setOpen] = useState(false);
    const [restoringMaster, setRestoringMaster] = useState(false);
    const form = useForm({
        description: '',
        bloom_level: '',
    });

    if (!open) {
        const restoreFromCurriculum = () => {
            if (restoringMaster) return;

            const confirmed = confirm(
                'Pulihkan CPMK resmi dari master kurikulum yang belum ada di RPS? CPMK yang sudah ada tidak akan diubah.',
            );

            if (!confirmed) return;

            setRestoringMaster(true);
            router.post(
                `/rps/${rpsId}/cpmk/import-curriculum`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: (page: any) => {
                        const message = page?.props?.flash?.success
                            || 'Sinkronisasi CPMK dengan master kurikulum selesai.';
                        notify('success', message);
                    },
                    onError: (errors: any) => notify('error', firstError(errors)),
                    onFinish: () => setRestoringMaster(false),
                },
            );
        };

        return (
            <div className="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    disabled={restoringMaster}
                    onClick={restoreFromCurriculum}
                    title="Pulihkan CPMK resmi yang hilang dari master kurikulum tanpa menimpa CPMK yang sudah ada"
                    className="inline-flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <RotateCcw className={`size-3 ${restoringMaster ? 'animate-spin' : ''}`} />
                    {restoringMaster ? 'Mengambil...' : 'Ambil CPMK Kurikulum'}
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700"
                >
                    <Plus className="size-3" /> Tambah CPMK
                </button>
            </div>
        );
    }

    return (
        <div className="w-full min-w-[420px] rounded-xl border border-teal-100 bg-teal-50/40 p-2">
            <textarea
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                placeholder="Rumusan CPMK baru..."
                className="min-h-20 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
            />
            <div className="mt-1 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => form.post(
                        `/rps/${rpsId}/cpmk`,
                        actionOptions('CPMK baru berhasil ditambahkan.', () => {
                            form.reset();
                            setOpen(false);
                        }),
                    )}
                    className="rounded-lg bg-teal-700 px-2 py-1 text-[10px] font-bold text-white"
                >
                    Simpan
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-500"
                >
                    Batal
                </button>
            </div>
        </div>
    );
}

function DocumentSubCpmkAdd({ rpsId, cpmks }: any) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        rps_cpmk_id: cpmks[0]?.id ?? '',
        description: '',
        bloom_level: '',
    });

    useEffect(() => {
        if (!form.data.rps_cpmk_id && cpmks[0]?.id) {
            form.setData('rps_cpmk_id', cpmks[0].id);
        }
    }, [cpmks]);

    if (!open) {
        return (
            <button
                type="button"
                disabled={cpmks.length === 0}
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700 disabled:opacity-40"
            >
                <Plus className="size-3" /> Tambah Sub-CPMK
            </button>
        );
    }

    return (
        <div className="w-full min-w-[460px] rounded-xl border border-teal-100 bg-teal-50/40 p-2">
            <textarea
                value={form.data.description}
                onChange={(e) => form.setData('description', e.target.value)}
                placeholder="Rumusan Sub-CPMK..."
                className="min-h-20 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
            />
            <div className="mt-1 flex flex-wrap items-center gap-2">
                <select
                    value={form.data.rps_cpmk_id}
                    onChange={(e) => form.setData('rps_cpmk_id', e.target.value)}
                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                >
                    {cpmks.map((cpmk: any) => (
                        <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>
                    ))}
                </select>
                <select
                    value={form.data.bloom_level}
                    onChange={(e) => form.setData('bloom_level', e.target.value)}
                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                >
                    <option value="">Bloom</option>
                    {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                </select>
                <button
                    type="button"
                    disabled={form.processing}
                    onClick={() => form.post(
                        `/rps/${rpsId}/sub-cpmk`,
                        actionOptions('Sub-CPMK berhasil ditambahkan.', () => {
                            form.reset();
                            setOpen(false);
                        }),
                    )}
                    className="rounded-lg bg-teal-700 px-2 py-1 text-[10px] font-bold text-white"
                >
                    Simpan
                </button>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-500"
                >
                    Batal
                </button>
            </div>
        </div>
    );
}

function DocumentMaterialsManager({ rpsId, materials }: any) {
    const [open, setOpen] = useState(false);
    const addForm = useForm({
        title: '',
        description: '',
    });

    return (
        <div>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600"
            >
                <Pencil className="size-3" /> {open ? 'Tutup Edit Materi' : 'Edit Materi'}
            </button>

            {open && (
                <div className="mt-2 w-full min-w-[520px] rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                    <div className="space-y-2">
                        {materials.map((material: any) => (
                            <MaterialEditRow
                                key={material.id}
                                rpsId={rpsId}
                                material={material}
                            />
                        ))}
                    </div>

                    <div className="mt-3 grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-[1fr_auto]">
                        <input
                            value={addForm.data.title}
                            onChange={(e) => addForm.setData('title', e.target.value)}
                            placeholder="Tambah bahan kajian..."
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                        <button
                            type="button"
                            disabled={addForm.processing || !addForm.data.title.trim()}
                            onClick={() => addForm.post(
                                `/rps/${rpsId}/materials`,
                                actionOptions('Bahan kajian ditambahkan.', () => addForm.reset()),
                            )}
                            className="rounded-lg bg-teal-700 px-3 py-1.5 text-[10px] font-bold text-white disabled:opacity-40"
                        >
                            + Tambah
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function MaterialEditRow({ rpsId, material }: any) {
    const form = useForm({
        title: stripMaterialListPrefix(material.title),
    });

    return (
        <div className="grid gap-2 rounded-lg border border-slate-100 bg-slate-50/60 p-2 sm:grid-cols-[1fr_auto_auto]">
            <input
                value={form.data.title}
                onChange={(e) => form.setData('title', e.target.value)}
                className="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
            />
            <button
                type="button"
                disabled={form.processing}
                onClick={() => form.put(
                    `/rps/${rpsId}/materials/${material.id}`,
                    actionOptions('Bahan kajian diperbarui.'),
                )}
                className="rounded-lg bg-teal-700 px-2.5 py-1.5 text-[10px] font-bold text-white"
            >
                Simpan
            </button>
            <button
                type="button"
                onClick={() => {
                    if (confirm(`Hapus bahan kajian "${material.title}"?`)) {
                        router.delete(
                            `/rps/${rpsId}/materials/${material.id}`,
                            actionOptions('Bahan kajian dihapus.'),
                        );
                    }
                }}
                className="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-700"
            >
                Hapus
            </button>
        </div>
    );
}

function AssessmentQuickAdd({ rpsId, subCpmks }: any) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: '',
        type: 'assignment',
        week_number: '',
        weight: '',
        description: '',
        sub_cpmk_ids: [] as string[],
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

    return (
        <div className="rounded-xl border border-sky-100 bg-sky-50/30 p-3">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <div className="font-bold text-slate-800">Tambah Asesmen</div>
                    <div className="text-[10px] text-slate-400">Kuis, tugas, proyek, praktikum, presentasi.</div>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="rounded-lg border border-sky-200 bg-white px-2 py-1 text-[10px] font-bold text-sky-700"
                >
                    {open ? 'Tutup' : '+ Isi'}
                </button>
            </div>

            {open && (
                <div className="mt-3 space-y-2">
                    <input
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Nama asesmen"
                        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />
                    <div className="grid gap-2 sm:grid-cols-3">
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        >
                            <option value="quiz">Kuis</option>
                            <option value="assignment">Tugas</option>
                            <option value="project">Proyek</option>
                            <option value="presentation">Presentasi</option>
                            <option value="practicum">Praktikum</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input
                            type="number"
                            min="1"
                            max="16"
                            value={form.data.week_number}
                            onChange={(e) => form.setData('week_number', e.target.value)}
                            placeholder="Pekan"
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={form.data.weight}
                            onChange={(e) => form.setData('weight', e.target.value)}
                            placeholder="Bobot %"
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                    </div>

                    <textarea
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        placeholder="Indikator / kriteria penilaian (opsional)"
                        className="min-h-16 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />

                    <div>
                        <div className="mb-1 text-[10px] font-bold text-slate-400">Sub-CPMK yang diukur</div>
                        <div className="flex flex-wrap gap-1">
                            {subCpmks.map((sub: any) => (
                                <button
                                    key={sub.id}
                                    type="button"
                                    onClick={() => toggleSub(sub.id)}
                                    className={`rounded-full px-2 py-1 text-[9px] font-bold ${
                                        form.data.sub_cpmk_ids.includes(sub.id)
                                            ? 'bg-sky-700 text-white'
                                            : 'border border-sky-200 bg-white text-sky-700'
                                    }`}
                                >
                                    {sub.code}
                                </button>
                            ))}
                        </div>
                    </div>

                    <button
                        type="button"
                        disabled={form.processing || !form.data.name.trim()}
                        onClick={() => form.post(
                            `/rps/${rpsId}/assessments`,
                            actionOptions('Asesmen berhasil ditambahkan.', () => {
                                form.reset();
                                setOpen(false);
                            }),
                        )}
                        className="rounded-lg bg-sky-700 px-3 py-1.5 text-[10px] font-bold text-white disabled:opacity-40"
                    >
                        Simpan Asesmen
                    </button>
                </div>
            )}
        </div>
    );
}

function TaskQuickAdd({ rpsId, subCpmks, assessments }: any) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        assessment_id: '',
        title: '',
        type: 'assignment',
        purpose: '',
        instructions: '',
        expected_output: '',
        due_week: '',
        sub_cpmk_ids: [] as string[],
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

    return (
        <div className="rounded-xl border border-teal-100 bg-teal-50/30 p-3">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <div className="font-bold text-slate-800">Tambah RTM</div>
                    <div className="text-[10px] text-slate-400">Rencana tugas mahasiswa yang terhubung dengan asesmen.</div>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="rounded-lg border border-teal-200 bg-white px-2 py-1 text-[10px] font-bold text-teal-700"
                >
                    {open ? 'Tutup' : '+ Isi'}
                </button>
            </div>

            {open && (
                <div className="mt-3 space-y-2">
                    <input
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        placeholder="Judul RTM"
                        className="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />
                    <div className="grid gap-2 sm:grid-cols-3">
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        >
                            <option value="assignment">Tugas</option>
                            <option value="project">Proyek</option>
                            <option value="practicum">Praktikum</option>
                            <option value="presentation">Presentasi</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <select
                            value={form.data.assessment_id}
                            onChange={(e) => {
                                const assessmentId = e.target.value;
                                form.setData('assessment_id', assessmentId);
                                const selectedAssessment = assessments.find(
                                    (item: any) => item.id === assessmentId,
                                );
                                if (selectedAssessment) {
                                    form.setData(
                                        'sub_cpmk_ids',
                                        safeList(selectedAssessment.sub_cpmk_ids),
                                    );
                                }
                            }}
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        >
                            <option value="">Tanpa asesmen khusus</option>
                            {assessments.map((assessment: any) => (
                                <option key={assessment.id} value={assessment.id}>{assessment.code} | {assessment.name}</option>
                            ))}
                        </select>
                        <input
                            type="number"
                            min="1"
                            max="16"
                            value={form.data.due_week}
                            onChange={(e) => form.setData('due_week', e.target.value)}
                            placeholder="Pekan"
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                    </div>
                    <textarea
                        value={form.data.purpose}
                        onChange={(e) => form.setData('purpose', e.target.value)}
                        placeholder="Tujuan tugas"
                        className="min-h-16 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />
                    <textarea
                        value={form.data.instructions}
                        onChange={(e) => form.setData('instructions', e.target.value)}
                        placeholder="Deskripsi / metode pengerjaan tugas"
                        className="min-h-24 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />
                    <textarea
                        value={form.data.expected_output}
                        onChange={(e) => form.setData('expected_output', e.target.value)}
                        placeholder="Bentuk dan format luaran"
                        className="min-h-20 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                    />
                    <div className="flex flex-wrap gap-1">
                        {subCpmks.map((sub: any) => (
                            <button
                                key={sub.id}
                                type="button"
                                onClick={() => toggleSub(sub.id)}
                                className={`rounded-full px-2 py-1 text-[9px] font-bold ${
                                    form.data.sub_cpmk_ids.includes(sub.id)
                                        ? 'bg-teal-700 text-white'
                                        : 'border border-teal-200 bg-white text-teal-700'
                                }`}
                            >
                                {sub.code}
                            </button>
                        ))}
                    </div>

                    <button
                        type="button"
                        disabled={form.processing || !form.data.title.trim()}
                        onClick={() => form.post(
                            `/rps/${rpsId}/tasks`,
                            actionOptions('RTM berhasil ditambahkan.', () => {
                                form.reset();
                                setOpen(false);
                            }),
                        )}
                        className="rounded-lg bg-teal-700 px-3 py-1.5 text-[10px] font-bold text-white disabled:opacity-40"
                    >
                        Simpan RTM
                    </button>
                </div>
            )}
        </div>
    );
}

function DocumentCpmkRow({
    rpsId,
    cpmk,
    cpls,
    selectedCplIds,
    onToggle,
    onSaveMapping,
}: any) {
    const [editing, setEditing] = useState(false);
    const [mappingOpen, setMappingOpen] = useState(false);

    const form = useForm({
        description: cpmk.description ?? '',
        bloom_level: cpmk.bloom_level ?? '',
    });

    const selectedCodes = cpls
        .filter((cpl: any) => selectedCplIds.includes(cpl.id))
        .map((cpl: any) => cpl.code);

    return (
        <tr className="transition hover:bg-sky-50/40">
            <td className="w-[175px] min-w-[175px] border border-slate-300 px-2 py-0.5 align-middle font-semibold leading-4">
                <div className="flex items-center gap-1 whitespace-nowrap">
                    <span className="text-[10px]">{cpmk.code.replace('-', ' ')}</span>
                    <span className={`inline-flex rounded-full px-1.5 py-[1px] text-[8px] font-extrabold ${
                    cpmk.bloom_level
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'bg-slate-100 text-slate-400'
                }`}>
                        {cpmk.bloom_level || 'Bloom —'}
                    </span>
                </div>
            </td>
            <td colSpan={4} className="border border-slate-300 px-2 py-0.5 leading-4">
                {editing ? (
                    <div className="print:hidden">
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            className="min-h-16 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <select
                                value={form.data.bloom_level}
                                onChange={(e) => form.setData('bloom_level', e.target.value)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-xs"
                            >
                                <option value="">Pilih Bloom</option>
                                {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                            </select>
                            <button
                                type="button"
                                onClick={() => form.put(
                                    `/rps/${rpsId}/cpmk/${cpmk.id}`,
                                    actionOptions(`${cpmk.code} berhasil diperbarui.`, () => setEditing(false)),
                                )}
                                className="rounded-lg bg-teal-700 px-2 py-1 text-[10px] font-bold text-white"
                            >
                                Simpan
                            </button>
                            <button
                                type="button"
                                onClick={() => setEditing(false)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-500"
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                ) : (
                    <div>
                        <div className="flex items-start justify-between gap-2">
                            <span>{cpmk.description}</span>
                            <div className="flex shrink-0 gap-1 print:hidden">
                                <button
                                    type="button"
                                    onClick={() => setEditing(true)}
                                    className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-teal-700"
                                    title="Edit CPMK"
                                >
                                    <Pencil className="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setMappingOpen((value) => !value)}
                                    className="rounded p-1 text-violet-600 hover:bg-violet-50"
                                    title="Atur keterkaitan CPL"
                                >
                                    <Network className="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm(`Hapus ${cpmk.code} dari RPS? CPMK master kurikulum tidak akan berubah.`)) {
                                            router.delete(
                                                `/rps/${rpsId}/cpmk/${cpmk.id}`,
                                                actionOptions(`${cpmk.code} dihapus dari RPS.`),
                                            );
                                        }
                                    }}
                                    className="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                    title="Hapus CPMK"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div className="mt-1 flex flex-wrap items-center gap-1">
                            <span className="text-[9px] font-bold uppercase text-slate-400">CPL terkait:</span>
                            {selectedCodes.length > 0 ? selectedCodes.map((code: string) => (
                                <span key={code} className="rounded-full bg-teal-50 px-1.5 py-[1px] text-[8px] font-bold text-teal-700">
                                    {code}
                                </span>
                            )) : (
                                <span className="text-[9px] text-amber-600">belum dipetakan</span>
                            )}
                        </div>
                    </div>
                )}

                {mappingOpen && !editing && (
                    <div className="mt-2 rounded-lg border border-violet-100 bg-violet-50/40 p-2 print:hidden">
                        <div className="flex flex-wrap gap-1">
                            {cpls.map((cpl: any) => (
                                <button
                                    key={cpl.id}
                                    type="button"
                                    title={cpl.description}
                                    onClick={() => onToggle(cpl.id)}
                                    className={`rounded-full px-2 py-1 text-[9px] font-bold ${
                                        selectedCplIds.includes(cpl.id)
                                            ? 'bg-violet-700 text-white'
                                            : 'border border-violet-200 bg-white text-violet-700'
                                    }`}
                                >
                                    {cpl.code}
                                </button>
                            ))}
                        </div>
                        <div className="mt-2 flex justify-end">
                            <button
                                type="button"
                                onClick={() => onSaveMapping(() => setMappingOpen(false))}
                                className="rounded-lg bg-violet-700 px-2 py-1 text-[10px] font-bold text-white"
                            >
                                Simpan Pemetaan CPL
                            </button>
                        </div>
                    </div>
                )}
            </td>
        </tr>
    );
}

function DocumentSubCpmkRow({ rpsId, sub, cpmks }: any) {
    const [editing, setEditing] = useState(false);
    const parentId = safeList(sub.cpmk_ids)[0] ?? cpmks[0]?.id ?? '';

    const form = useForm({
        rps_cpmk_id: parentId,
        description: sub.description ?? '',
        bloom_level: sub.bloom_level ?? '',
    });

    const parentCode = cpmks.find((cpmk: any) => cpmk.id === parentId)?.code;

    return (
        <tr className="transition hover:bg-sky-50/40">
            <td className="w-[210px] min-w-[210px] border border-slate-300 px-2 py-0.5 align-middle font-semibold leading-4">
                <div className="flex items-center gap-1 whitespace-nowrap">
                    <span className="text-[10px]">{sub.code.replace('-', ' ')}</span>
                    <span className={`rounded-full px-1.5 py-[1px] text-[8px] font-extrabold ${
                        sub.bloom_level
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'bg-slate-100 text-slate-400'
                    }`}>
                        {sub.bloom_level || 'Bloom —'}
                    </span>
                    {parentCode && (
                        <span className="rounded-full bg-teal-50 px-1.5 py-[1px] text-[8px] font-bold text-teal-700 print:hidden">
                            {parentCode}
                        </span>
                    )}
                </div>
            </td>
            <td colSpan={4} className="border border-slate-300 px-2 py-0.5 leading-4">
                {editing ? (
                    <div className="print:hidden">
                        <textarea
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            className="min-h-16 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                        />
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <select
                                value={form.data.rps_cpmk_id}
                                onChange={(e) => form.setData('rps_cpmk_id', e.target.value)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-xs"
                            >
                                {cpmks.map((cpmk: any) => (
                                    <option key={cpmk.id} value={cpmk.id}>{cpmk.code}</option>
                                ))}
                            </select>
                            <select
                                value={form.data.bloom_level}
                                onChange={(e) => form.setData('bloom_level', e.target.value)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-xs"
                            >
                                <option value="">Pilih Bloom</option>
                                {['C1','C2','C3','C4','C5','C6'].map((level) => <option key={level}>{level}</option>)}
                            </select>
                            <button
                                type="button"
                                onClick={() => form.put(
                                    `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                    actionOptions(`${sub.code} berhasil diperbarui.`, () => setEditing(false)),
                                )}
                                className="rounded-lg bg-teal-700 px-2 py-1 text-[10px] font-bold text-white"
                            >
                                Simpan
                            </button>
                            <button
                                type="button"
                                onClick={() => setEditing(false)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-500"
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-start justify-between gap-2">
                        <span>{sub.description}</span>
                        <div className="flex shrink-0 gap-1 print:hidden">
                            <button
                                type="button"
                                onClick={() => setEditing(true)}
                                className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-teal-700"
                                title="Edit Sub-CPMK"
                            >
                                <Pencil className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    if (confirm(`Hapus ${sub.code}? Nomor kosong akan dapat dipakai kembali.`)) {
                                        router.delete(
                                            `/rps/${rpsId}/sub-cpmk/${sub.id}`,
                                            actionOptions(`${sub.code} dihapus.`),
                                        );
                                    }
                                }}
                                className="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                                title="Hapus Sub-CPMK"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </div>
                    </div>
                )}
            </td>
        </tr>
    );
}

function SubCpmkMatrix({ cpmks, subCpmks }: any) {
    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-2 py-1.5 print:hidden">
                <div className="text-[9px] font-semibold text-slate-500">
                    Baris = CPMK • Kolom = Sub-CPMK
                </div>
                <div className="flex items-center gap-1.5 text-[9px] text-slate-400">
                    <span className="inline-block size-3 rounded-sm bg-sky-300" />
                    terkait
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-[720px] w-full border-collapse text-center text-[11px]">
                    <thead>
                        <tr className="bg-sky-50">
                            <th className="sticky left-0 z-10 w-[140px] border border-slate-300 bg-sky-50 px-2 py-1 text-left">
                                <div className="font-extrabold text-slate-700">Baris ↓ CPMK</div>
                                <div className="text-[9px] font-semibold text-sky-700">Kolom → Sub-CPMK</div>
                            </th>
                            {subCpmks.map((sub: any, index: number) => (
                                <th
                                    key={sub.id}
                                    title={`${sub.code}: ${sub.description}`}
                                    className="min-w-[62px] border border-slate-300 px-1 py-1"
                                >
                                    <div className="font-extrabold">S{index + 1}</div>
                                    <div className="text-[8px] font-normal text-slate-400">{sub.code}</div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {cpmks.map((cpmk: any) => (
                            <tr key={cpmk.id}>
                                <th
                                    title={cpmk.description}
                                    className="sticky left-0 z-10 border border-slate-300 bg-white px-2 py-1 text-left"
                                >
                                    <div className="font-extrabold text-slate-700">{cpmk.code}</div>
                                    <div className="text-[8px] font-normal text-slate-400">{cpmk.bloom_level || 'Bloom —'}</div>
                                </th>
                                {subCpmks.map((sub: any) => {
                                    const linked = safeList(sub.cpmk_ids).includes(cpmk.id);

                                    return (
                                        <td
                                            key={sub.id}
                                            title={`${cpmk.code} ↔ ${sub.code}${linked ? ' (terkait)' : ''}`}
                                            className={`h-6 border border-slate-300 ${
                                                linked
                                                    ? 'bg-sky-300'
                                                    : 'bg-white hover:bg-slate-50'
                                            }`}
                                        >
                                            {linked ? <span className="font-black text-sky-900">✓</span> : ''}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function InlineWeightInput({ rpsId, week }: any) {
    const original = String(Number(week.assessment_weight || 0));

    const form = useForm({
        weight: original,
    });

    // Inertia mempertahankan state komponen setelah PUT/partial reload.
    // Tanpa sinkronisasi ini, server sudah mengirim bobot terbaru tetapi
    // input masih menampilkan nilai lama (mis. 0).
    useEffect(() => {
        form.setData('weight', original);
        form.clearErrors();
    }, [original]);

    const save = () => {
        if (String(form.data.weight) === original) return;

        form.put(
            `/rps/${rpsId}/weeks/${week.week_number}/weight`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    notify('success', `Bobot pekan ${week.week_number} disimpan dan disinkronkan.`);

                    router.reload({
                        only: ['weeks', 'assessments', 'progress', 'simulationScores'],
                        preserveScroll: true,
                    });
                },
                onError: (errors) => {
                    form.setData('weight', original);
                    notify('error', firstError(errors));
                },
            },
        );
    };

    return (
        <>
            <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={form.data.weight}
                onChange={(e) => form.setData('weight', e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        (e.currentTarget as HTMLInputElement).blur();
                    }
                }}
                className="w-full rounded border border-sky-200 bg-sky-50/40 px-1 py-1 text-center text-xs font-bold text-sky-800 print:hidden"
                title="Bobot pengukuran Sub-CPMK pada pekan ini"
            />
            <span className="hidden font-bold print:inline">
                {Number(form.data.weight || 0) || '-'}
            </span>
        </>
    );
}

function SubCpmkMeetingPlanner({ rpsId, subCpmks, weeks, onClose }: any) {
    const currentAllocations = useMemo(() => {
        const counts: Record<string, number> = {};
        subCpmks.forEach((sub: any) => { counts[sub.id] = 0; });

        weeks
            .filter((week: any) => TEACHING_WEEKS.includes(Number(week.week_number)))
            .forEach((week: any) => {
                if (week.rps_sub_cpmk_id && counts[week.rps_sub_cpmk_id] !== undefined) {
                    counts[week.rps_sub_cpmk_id] += 1;
                }
            });

        // Untuk RPS yang belum pernah dialokasikan, setiap Sub-CPMK diberi
        // nilai awal 1 agar dosen tinggal membagi sisa pertemuan.
        subCpmks.forEach((sub: any) => {
            if (counts[sub.id] < 1) counts[sub.id] = 1;
        });

        return counts;
    }, [subCpmks, weeks]);

    const [allocations, setAllocations] = useState<Record<string, number>>(currentAllocations);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setAllocations(currentAllocations);
    }, [currentAllocations]);

    const total = Object.values(allocations).reduce((sum, value) => sum + Number(value || 0), 0);
    const remaining = 14 - total;

    const save = () => {
        if (total !== 14 || saving) return;
        setSaving(true);

        router.post(
            `/rps/${rpsId}/weeks/allocate-subcpmk`,
            { allocations },
            {
                preserveScroll: true,
                onSuccess: () => {
                    notify('success', 'Jumlah pertemuan per Sub-CPMK berhasil disimpan.');
                    onClose();
                },
                onError: (errors) => notify('error', firstError(errors)),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/45 p-4 print:hidden">
            <div className="max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div className="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-5 py-4">
                    <div>
                        <h3 className="text-base font-extrabold text-slate-900">Atur Pertemuan Sub-CPMK</h3>
                        <p className="mt-1 text-xs text-slate-500">
                            Tetapkan jumlah pertemuan pembelajaran untuk setiap Sub-CPMK. UTS dan UAS tidak dihitung.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                    >
                        Tutup
                    </button>
                </div>

                <div className="p-5">
                    <div className="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                        Jumlah yang ditetapkan dosen menjadi acuan utama. <strong>Lengkapi RPS Otomatis</strong> dan <strong>Susun AI</strong> akan mengikuti alokasi ini, bukan membagi ulang Sub-CPMK.
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <table className="w-full border-collapse text-xs">
                            <thead className="bg-slate-50 text-slate-700">
                                <tr>
                                    <th className="border-b border-slate-200 px-3 py-2 text-left">Sub-CPMK</th>
                                    <th className="border-b border-slate-200 px-3 py-2 text-left">Rumusan</th>
                                    <th className="w-36 border-b border-slate-200 px-3 py-2 text-center">Pertemuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subCpmks.map((sub: any) => (
                                    <tr key={sub.id} className="align-top">
                                        <td className="border-b border-slate-100 px-3 py-2 font-bold text-slate-800">{sub.code}</td>
                                        <td className="border-b border-slate-100 px-3 py-2 text-slate-600">{sub.description}</td>
                                        <td className="border-b border-slate-100 px-3 py-2 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                max="14"
                                                value={allocations[sub.id] ?? 1}
                                                onChange={(e) => setAllocations((current) => ({
                                                    ...current,
                                                    [sub.id]: Math.max(1, Math.min(14, Number(e.target.value || 1))),
                                                }))}
                                                className="w-20 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-center font-bold text-slate-800"
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className={`mt-4 flex items-center justify-between rounded-xl border px-4 py-3 ${total === 14 ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
                        <div>
                            <div className="text-xs font-bold text-slate-700">Total pertemuan</div>
                            <div className="mt-0.5 text-[11px] text-slate-500">
                                {total === 14
                                    ? 'Sudah tepat 14 pertemuan efektif.'
                                    : remaining > 0
                                      ? `Masih perlu dialokasikan ${remaining} pertemuan.`
                                      : `Kelebihan ${Math.abs(remaining)} pertemuan.`}
                            </div>
                        </div>
                        <div className={`text-2xl font-extrabold ${total === 14 ? 'text-emerald-700' : 'text-amber-700'}`}>
                            {total}/14
                        </div>
                    </div>

                    <div className="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            disabled={total !== 14 || saving}
                            onClick={save}
                            className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {saving ? 'Menyimpan...' : 'Simpan Alokasi'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function DocumentWeekRow({
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
    const [saving, setSaving] = useState(false);
    const c = Math.max(1, Number(credits || 1));

    const form = useForm({
        rps_sub_cpmk_id: week?.rps_sub_cpmk_id ?? '',
        assessment_indicator: normalizeAcademicTerm(week?.assessment_indicator ?? ''),
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

    const info = weekCompletion(week);

    if (week.is_exam) {
        const examTitle = week.exam_type === 'UTS'
            ? 'Ujian Tengah Semester'
            : 'Ujian Akhir Semester';

        return (
            <tr className="bg-sky-100">
                <td className="border border-slate-400 px-2 py-2 text-center font-extrabold">{week.week_number}</td>
                <td colSpan={6} className="border border-slate-400 px-3 py-2 text-center font-extrabold">
                    {examTitle}
                </td>
                <td className="border border-slate-300 px-1.5 py-1.5 text-center">
                    <InlineWeightInput key={`weight-${week.week_number}-${Number(week.assessment_weight || 0)}`} rpsId={rpsId} week={week} />
                </td>
            </tr>
        );
    }

    const save = () => {
        if (saving) return;

        const payload = {
            ...form.data,
            face_to_face_sessions: Number(form.data.face_to_face_sessions || 0),
            structured_task_sessions: Number(form.data.structured_task_sessions || 0),
            independent_study_sessions: Number(form.data.independent_study_sessions || 0),
            time_estimate: `Tatap muka: ${Number(form.data.face_to_face_sessions || 0)} × (${c} × 50 menit); Tugas terstruktur: ${Number(form.data.structured_task_sessions || 0)} × (${c} × 60 menit); Belajar mandiri: ${Number(form.data.independent_study_sessions || 0)} × (${c} × 60 menit)`,
        };

        setSaving(true);

        router.put(
            `/rps/${rpsId}/weeks/${week.week_number}`,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    notify('success', `Pekan ${week.week_number} berhasil disimpan.`);
                    setEditing(false);
                },
                onError: (errors: any) => {
                    notify(
                        'error',
                        `Pekan ${week.week_number} belum tersimpan. ${firstError(errors)}`,
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const clearWeekFields = () => {
        if (!confirm(`Kosongkan isi Pekan ${week.week_number}? Sub-CPMK dan bobot penilaian tetap dipertahankan.`)) {
            return;
        }

        form.setData({
            rps_sub_cpmk_id: form.data.rps_sub_cpmk_id,
            assessment_indicator: '',
            assessment_criteria: '',
            assessment_method: '',
            learning_form: '',
            learning_method: '',
            face_to_face_sessions: '0',
            learning_activity: '',
            independent_study_sessions: '0',
            student_assignment: '',
            structured_task_sessions: '0',
            online_activity: '',
            material_text: '',
            reference_text: '',
            time_estimate: '',
        });

        notify(
            'info',
            `Isian Pekan ${week.week_number} dikosongkan di editor. Klik Simpan untuk menyimpan perubahan.`,
        );
    };

    const input = "w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-[11px]";
    const area = `${input} min-h-24 resize-y`;

    if (editing) {
        return (
            <tr className="align-top bg-amber-50/40">
                <td className="border border-slate-400 p-2 text-center">
                    <div className="font-bold">{week.week_number}</div>
                    <div className="mt-2 flex flex-col gap-1 print:hidden">
                        <button
                            type="button"
                            disabled={saving}
                            onClick={save}
                            className="rounded bg-teal-700 px-2 py-1 text-[9px] font-bold text-white disabled:opacity-50"
                        >
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </button>
                        <button
                            type="button"
                            onClick={clearWeekFields}
                            className="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-bold text-rose-700 hover:bg-rose-100"
                        >
                            Kosongkan
                        </button>
                        <button
                            type="button"
                            onClick={() => setEditing(false)}
                            className="rounded border border-slate-200 bg-white px-2 py-1 text-[9px] font-bold text-slate-500"
                        >
                            Batal
                        </button>
                    </div>
                </td>
                <td className="border border-slate-400 p-2">
                    <select
                        value={form.data.rps_sub_cpmk_id}
                        onChange={(e) => form.setData('rps_sub_cpmk_id', e.target.value)}
                        className={input}
                    >
                        <option value="">Pilih Sub-CPMK</option>
                        {subCpmks.map((sub: any) => (
                            <option key={sub.id} value={sub.id}>{sub.code} | {sub.description}</option>
                        ))}
                    </select>
                </td>
                <td className="border border-slate-400 p-2">
                    <textarea value={form.data.assessment_indicator} onChange={(e) => form.setData('assessment_indicator', e.target.value)} className={area} />
                </td>
                <td className="border border-slate-400 p-2">
                    <textarea value={form.data.assessment_criteria} onChange={(e) => form.setData('assessment_criteria', e.target.value)} className={area} placeholder="Kriteria" />
                    <input value={form.data.assessment_method} onChange={(e) => form.setData('assessment_method', e.target.value)} className={`${input} mt-1`} placeholder="Bentuk / teknik" />
                </td>
                <td className="border border-slate-400 p-2">
                    <input value={form.data.learning_form} onChange={(e) => form.setData('learning_form', e.target.value)} className={input} placeholder="Tatap Muka / Praktikum" />
                    <input value={form.data.learning_method} onChange={(e) => form.setData('learning_method', e.target.value)} className={`${input} mt-1`} placeholder="Metode Pembelajaran" />
                    <textarea
                        value={form.data.learning_activity}
                        onChange={(e) => form.setData('learning_activity', e.target.value)}
                        className={`${area} mt-1`}
                        placeholder="Rincian aktivitas/strategi dalam metode pembelajaran"
                    />
                    <div className="mt-1 grid grid-cols-[1fr_52px] items-center gap-1">
                        <span className="text-[9px] text-sky-700">
                            Tatap Muka: {Number(form.data.face_to_face_sessions || 0)} × ({c} × 50')
                        </span>
                        <input type="number" min="0" max="10" value={form.data.face_to_face_sessions} onChange={(e) => form.setData('face_to_face_sessions', e.target.value)} className={input} />
                    </div>
                    <div className="mt-1 grid grid-cols-[1fr_52px] items-center gap-1">
                        <span className="text-[9px] font-semibold text-sky-700">
                            Belajar Mandiri: {Number(form.data.independent_study_sessions || 0)} × ({c} × 60')
                        </span>
                        <input type="number" min="0" max="10" value={form.data.independent_study_sessions} onChange={(e) => form.setData('independent_study_sessions', e.target.value)} className={input} />
                    </div>
                </td>
                <td className="border border-slate-400 p-2">
                    <textarea value={form.data.student_assignment} onChange={(e) => form.setData('student_assignment', e.target.value)} className={area} placeholder="Tugas mandiri / terstruktur" />
                    <div className="mt-1 grid grid-cols-[1fr_52px] items-center gap-1">
                        <span className="text-[9px] text-sky-700">
                            {Number(form.data.structured_task_sessions || 0)} × ({c} × 60')
                        </span>
                        <input type="number" min="0" max="10" value={form.data.structured_task_sessions} onChange={(e) => form.setData('structured_task_sessions', e.target.value)} className={input} />
                    </div>
                    <textarea value={form.data.online_activity} onChange={(e) => form.setData('online_activity', e.target.value)} className={`${area} mt-1`} placeholder="Daring / LMS" />
                </td>
                <td className="border border-slate-400 p-2">
                    <textarea value={form.data.material_text} onChange={(e) => form.setData('material_text', e.target.value)} className={area} placeholder="Materi Pembelajaran" />
                    <input
                        value={form.data.reference_text}
                        onChange={(e) => form.setData('reference_text', e.target.value)}
                        className={`${input} mt-1 font-bold text-sky-700`}
                        placeholder="[1], [2], [4]"
                        title={`Gunakan nomor pustaka 1-${bibliography.length}.`}
                    />
                </td>
                <td className="border border-slate-400 p-2 text-center">
                    <InlineWeightInput key={`weight-${week.week_number}-${Number(week.assessment_weight || 0)}`} rpsId={rpsId} week={week} />
                </td>
            </tr>
        );
    }

    return (
        <tr className={`align-top transition ${week.week_number % 2 === 0 ? "bg-slate-50/50" : "bg-white"} hover:bg-teal-50/30`}>
            <td className="border border-slate-300 px-1.5 py-1.5 text-center">
                <div className="font-bold">{week.week_number}</div>
                <div className="mt-2 flex flex-col gap-1 print:hidden">
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="rounded-lg border border-sky-700 bg-sky-600 px-2 py-1.5 text-[9px] font-extrabold text-white shadow-sm transition hover:bg-sky-700"
                    >
                        Edit Pekan
                    </button>
                    <button
                        type="button"
                        disabled={!aiConfigured || aiBusy}
                        onClick={() => onGenerateAi(info.count >= 7)}
                        title="Susun rekomendasi AI untuk pekan ini"
                        className="rounded-lg border border-violet-700 bg-violet-600 px-2 py-1.5 text-[10px] font-extrabold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {aiBusy ? 'AI...' : '✨ Susun AI'}
                    </button>
                </div>
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-semibold">{week.sub_cpmk_code || '-'}</div>
                <div className="mt-1">{week.sub_cpmk_description || '-'}</div>
            </td>
            <td className="border border-slate-300 px-2 py-1.5">{normalizeAcademicTerm(week.assessment_indicator) || '-'}</td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div><strong>Kriteria:</strong> {week.assessment_criteria || '-'}</div>
                <div className="mt-2"><strong>Bentuk:</strong> {week.assessment_method || '-'}</div>
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-bold">{week.learning_form || 'Tatap Muka'}</div>
                <div>{formatFaceToFaceTime(week, c)}</div>
                <div className="mt-2"><strong>Metode Pembelajaran</strong></div>
                <div>{normalizeAcademicTerm(week.learning_method) || '-'}</div>
                {week.learning_activity && (
                    <div className="mt-1">{normalizeAcademicTerm(week.learning_activity)}</div>
                )}
                <div className="mt-2 font-bold">Belajar Mandiri</div>
                <div>{formatIndependentTime(week, c)}</div>
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div className="font-bold">Tugas mandiri / terstruktur</div>
                <div>{normalizeAcademicTerm(week.student_assignment) || '-'}</div>
                <div>{formatStructuredTime(week, c)}</div>
                <div className="mt-2">{normalizeAcademicTerm(week.online_activity) || '-'}</div>
            </td>
            <td className="border border-slate-300 px-2 py-1.5">
                <div>{week.material_text || '-'}</div>
                {week.reference_text && (
                    <div className="mt-1 font-semibold">{week.reference_text}</div>
                )}
            </td>
            <td className="border border-slate-300 px-1.5 py-1.5 text-center">
                <InlineWeightInput key={`weight-${week.week_number}-${Number(week.assessment_weight || 0)}`} rpsId={rpsId} week={week} />
            </td>
        </tr>
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
                                if (confirm(`Hapus ${sub.code}? Relasi pada pekan/asesmen yang menggunakan Sub-CPMK ini akan dilepas.`)) {
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

    useEffect(() => {
        form.setData({
            name: assessment.name ?? '',
            type: assessment.type ?? 'assignment',
            week_number: assessment.week_number ?? '',
            weight: assessment.weight ?? '',
            description: assessment.description ?? '',
            sub_cpmk_ids: Array.isArray(assessment.sub_cpmk_ids)
                ? assessment.sub_cpmk_ids
                : [],
        });
    }, [
        assessment.id,
        assessment.name,
        assessment.type,
        assessment.week_number,
        assessment.weight,
        JSON.stringify(assessment.sub_cpmk_ids ?? []),
    ]);

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();

                form.put(
                    `/rps/${rpsId}/assessments/${assessment.id}`,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            notify(
                                'success',
                                `${assessment.name} berhasil disimpan. Bobot RPS ikut disinkronkan.`,
                            );

                            router.reload({
                                only: ['weeks', 'assessments', 'progress', 'simulationScores'],
                                preserveScroll: true,
                                preserveState: true,
                            });
                        },
                        onError: (errors: Record<string, any>) => {
                            notify('error', firstError(errors));
                        },
                    },
                );
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
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Pekan</span>
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


function TaskCard({ rpsId, task, assessments, subCpmks, initialEditing = false, onDone }: any) {
    const [editing, setEditing] = useState(Boolean(initialEditing));

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
                        <div className="mt-1 text-xs text-slate-500">{task.type} | Pekan {task.due_week ?? '-'}</div>
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
                    actionOptions('RTM berhasil diperbarui.', () => { setEditing(false); onDone?.(); }),
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
                    onClick={() => { setEditing(false); onDone?.(); }}
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
                        onChange={(e) => {
                                const assessmentId = e.target.value;
                                form.setData('assessment_id', assessmentId);
                                const selectedAssessment = assessments.find(
                                    (item: any) => item.id === assessmentId,
                                );
                                if (selectedAssessment) {
                                    form.setData(
                                        'sub_cpmk_ids',
                                        safeList(selectedAssessment.sub_cpmk_ids),
                                    );
                                }
                            }}
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
                    <span className="mb-1.5 block text-xs font-bold text-slate-500">Pekan Pengumpulan</span>
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
            actionOptions(`Pekan ${week.week_number} berhasil disimpan.`, () => setEditing(false)),
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
                <td className="border border-slate-200 px-3 py-3 leading-5 text-slate-600">{normalizeAcademicTerm(week.assessment_indicator) || '-'}</td>
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
                            className="inline-flex items-center justify-center gap-1 rounded-lg border border-sky-700 bg-sky-600 px-2.5 py-1.5 text-[10px] font-extrabold text-white shadow-sm transition hover:bg-sky-700"
                        >
                            <Pencil className="size-3.5" /> Edit Pekan
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
                    <button type="button" onClick={() => setEditing(false)} className="rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px] font-bold text-slate-600">
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
                            Pada tabel cetak, baris ini otomatis digabung seperti format RPS contoh. Tidak perlu mengisi Sub-CPMK, metode, materi, atau penilaian pekanan di sini.
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
                    actionOptions(`Pekan ${week.week_number} berhasil disimpan.`),
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
                            onClick={() => router.post(`/rps/${rpsId}/weeks/${week.week_number}/copy-previous`, {}, actionOptions(`Isi pekan ${week.week_number - 1} berhasil disalin ke pekan ${week.week_number}.`))}
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600"
                        >
                            <Copy className="size-3.5" /> Salin Pekan Sebelumnya
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

                    <label className="mt-3 block">
                        <span className="mb-1.5 block text-xs font-bold text-slate-500">
                            Rincian Aktivitas / Strategi Pembelajaran
                        </span>
                        <textarea
                            value={form.data.learning_activity}
                            onChange={(e) => form.setData('learning_activity', e.target.value)}
                            placeholder="Langkah diskusi, demonstrasi, latihan terbimbing, presentasi, studi kasus, dll."
                            className="min-h-20 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        />
                    </label>

                    <div className="mt-3 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2">
                        <div className="grid items-center gap-3 md:grid-cols-[1fr_150px]">
                            <div>
                                <div className="text-xs font-bold text-slate-700">Belajar Mandiri</div>
                                <div className="mt-1 text-xs font-semibold text-sky-700">
                                    {independentSessions} × ({c} × 60 menit)
                                </div>
                                <div className="mt-1 text-[11px] text-slate-500">
                                    Bagian ini hanya menyimpan estimasi waktu belajar mandiri.
                                </div>
                            </div>
                            <label>
                                <span className="mb-1.5 block text-xs font-bold text-slate-500">Frekuensi</span>
                                <input
                                    type="number" min="0" max="10"
                                    value={form.data.independent_study_sessions}
                                    onChange={(e) => form.setData('independent_study_sessions', e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                />
                            </label>
                        </div>
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
        ['Indikator', normalizeAcademicTerm(week?.assessment_indicator)],
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
        'bloom_mapping',
        'cpl_mapping',
        'material_plan',
        'sub_cpmk',
    ].includes(suggestion.suggestion_type);

    const sourceItems = ['cpmk_review', 'bloom_mapping'].includes(suggestion.suggestion_type)
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
        bloom_mapping: 'Pemetaan Bloom CPMK',
        cpl_mapping: 'Rekomendasi Pemetaan CPMK ↔ CPL',
        material_plan: 'Bahan Kajian',
        sub_cpmk: 'Sub-CPMK',
        weekly_plan: 'Rencana 14 Pekan',
        assessment_plan: 'Asesmen + RTM',
    };

    const countText = suggestion.suggestion_type === 'cpmk_review'
        ? `${safeList(payload.recommendations).length} rekomendasi CPMK`
        : suggestion.suggestion_type === 'bloom_mapping'
          ? `${safeList(payload.recommendations).length} rekomendasi Bloom`
        : suggestion.suggestion_type === 'cpl_mapping'
          ? `${safeList(payload.mappings).length} rekomendasi pemetaan`
          : suggestion.suggestion_type === 'material_plan'
          ? `${safeList(payload.items).length} rekomendasi bahan kajian`
          : suggestion.suggestion_type === 'sub_cpmk'
            ? `${safeList(payload.items).length} Sub-CPMK`
            : suggestion.suggestion_type === 'weekly_plan'
              ? `${safeList(payload.weeks).length} pekan kuliah`
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
            notify('error', 'Rencana AI ini tidak lengkap. Buat rekomendasi 14 pekan yang baru.');
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
                            Tidak lengkap: {actualTeachingWeeks.length}/14 pekan. Buat ulang rekomendasi sebelum diterapkan.
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
    if (type === 'bloom_mapping') {
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
                                    className="mt-1 size-4 accent-indigo-700"
                                    disabled={!actionable}
                                    checked={actionable && selectedIndices.includes(index)}
                                    onChange={() => actionable && onToggle?.(index)}
                                />
                                <div className="min-w-0 flex-1">
                                    <div className="font-bold text-slate-800">
                                        {safeText(item?.target_code)} → {safeText(item?.bloom_level)}
                                    </div>
                                    <div className="mt-1 leading-5 text-slate-600">{safeText(item?.description)}</div>
                                    <div className="mt-1 text-slate-400">{safeText(item?.rationale, '')}</div>
                                    {!actionable && <div className="mt-1 text-[10px] font-bold text-emerald-600">Level Bloom sudah sesuai.</div>}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }

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
                        <div className="font-bold text-slate-800">Pekan {safeText(week?.week_number)} | {safeText(week?.sub_cpmk_code)}</div>
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
                                {' | '}Pekan {safeText(item?.week_number)}
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
                                    {' | '}Pekan {safeText(task?.due_week)}
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
