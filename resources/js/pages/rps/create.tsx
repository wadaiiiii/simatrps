import { Head, useForm } from '@inertiajs/react';
import { BookOpenCheck, CheckCircle2, CircleAlert, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';

type Course = {
    id: string; curriculum_id: string; system_code: string; official_code?: string | null;
    name: string; credits: number; semester_recommended?: number | null; has_practicum: boolean;
    official_cpl_codes: string[]; official_cpmk_count: number;
    generator_readiness: 'ready_with_master_cpmk' | 'ai_cpmk_required' | 'needs_admin_review';
};

type Curriculum = { id: string; name: string };

export default function CreateRps({ curriculums, courses, defaultAcademicYear }: { curriculums: Curriculum[]; courses: Course[]; defaultAcademicYear: string }) {
    const [curriculumId, setCurriculumId] = useState(curriculums[0]?.id ?? '');
    const [search, setSearch] = useState('');
    const [semester, setSemester] = useState('all');
    const [status, setStatus] = useState('all');
    const [isDelaying, setIsDelaying] = useState(false);
    const form = useForm({ course_id: '', academic_year: defaultAcademicYear, academic_semester: 'Ganjil' });

    const filtered = useMemo(() => courses.filter((c) => {
        if (c.curriculum_id !== curriculumId) return false;
        if (semester !== 'all' && String(c.semester_recommended) !== semester) return false;
        if (status !== 'all' && c.generator_readiness !== status) return false;
        const q = search.trim().toLowerCase();
        if (!q) return true;
        return `${c.name} ${c.system_code} ${c.official_code ?? ''} ${c.official_cpl_codes.join(' ')}`.toLowerCase().includes(q);
    }), [courses, curriculumId, search, semester, status]);

    const selected = courses.find((c) => c.id === form.data.course_id);

    const submitDraft = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (isDelaying || form.processing) return;

        setIsDelaying(true);
        window.setTimeout(() => {
            setIsDelaying(false);
            form.post('/rps');
        }, 800);
    };

    return <>
        <Head title="Buat RPS" />
        <div className="p-4 md:p-6">
            <h1 className="text-2xl font-bold">Buat RPS</h1>
            <p className="mt-1 text-sm text-slate-500">Cari mata kuliah dengan cepat berdasarkan nama, kode, semester, atau status.</p>

            <form onSubmit={submitDraft} className="mt-6 grid gap-5 xl:grid-cols-[1.05fr_.95fr]">
                <section className="sim-surface rounded-2xl p-5">
                    <div className="flex items-center gap-3"><div className="rounded-xl bg-teal-50 p-3 text-teal-700"><Search className="size-5" /></div><div><h2 className="font-bold">Cari Mata Kuliah</h2><p className="text-sm text-slate-500"></p></div></div>
                    <div className="mt-5 grid gap-3 md:grid-cols-2">
                        <div className="relative md:col-span-2">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Cari nama/kode MK..." className="w-full rounded-xl border border-slate-200 bg-white/70 py-3 pl-10 pr-10 text-sm" />
                            {search && <button type="button" onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"><X className="size-4" /></button>}
                        </div>
                        <select value={semester} onChange={(e) => setSemester(e.target.value)} className="rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"><option value="all">Semua semester</option>{[1,2,3,4,5,6,7,8].map((n)=><option key={n} value={n}>Semester {n}</option>)}</select>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"><option value="all">Semua status</option><option value="ready_with_master_cpmk">Siap disusun</option><option value="ai_cpmk_required">Perlu CPMK</option><option value="needs_admin_review">Review Admin</option></select>
                    </div>
                    <div className="mt-4 text-sm text-slate-500">Ditemukan <b className="text-slate-900">{filtered.length}</b> mata kuliah</div>
                    <div className="mt-3 max-h-[500px] space-y-2 overflow-y-auto pr-1">
                        {filtered.map((c) => <button key={c.id} type="button" onClick={() => form.setData('course_id', c.id)} className={`w-full rounded-xl border p-4 text-left ${form.data.course_id===c.id?'border-teal-300 bg-teal-50':'border-slate-100 bg-white/60 hover:border-teal-200'}`}>
                            <div className="flex items-start justify-between gap-3"><div><div className="font-semibold text-slate-900">{c.name}</div><div className="mt-1 text-xs text-slate-500">{c.official_code || c.system_code} · {c.credits} SKS · Semester {c.semester_recommended ?? '-'}</div></div><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${c.generator_readiness==='ready_with_master_cpmk'?'bg-emerald-50 text-emerald-700':c.generator_readiness==='ai_cpmk_required'?'bg-amber-50 text-amber-700':'bg-rose-50 text-rose-700'}`}>{c.generator_readiness==='ready_with_master_cpmk'?'Siap':c.generator_readiness==='ai_cpmk_required'?'Perlu CPMK':'Review'}</span></div>
                        </button>)}
                    </div>
                </section>

                <section className="sim-surface rounded-2xl p-5">
                    <h2 className="font-bold">Detail & Periode RPS</h2>
                    {selected ? <>
                        <div className="mt-4 rounded-2xl border border-teal-100 bg-teal-50/35 p-5"><div className="text-xs font-semibold text-slate-500">{selected.official_code || selected.system_code}</div><h3 className="mt-1 text-xl font-bold">{selected.name}</h3><div className="mt-4 grid grid-cols-2 gap-3">{[['SKS',selected.credits],['Semester',selected.semester_recommended??'-'],['CPMK',selected.official_cpmk_count],['Praktikum',selected.has_practicum?'Ya':'Tidak']].map(([a,b])=><div key={a} className="rounded-xl bg-white/75 p-3"><div className="text-xs text-slate-500">{a}</div><div className="mt-1 font-bold">{b}</div></div>)}</div><div className="mt-4 flex flex-wrap gap-2">{selected.official_cpl_codes.map((code)=><span key={code} className="rounded-full bg-white px-3 py-1 text-xs font-bold text-teal-700">{code}</span>)}</div>{selected.generator_readiness==='ready_with_master_cpmk'?<div className="mt-4 flex gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"><CheckCircle2 className="size-4" />Siap dibuat menjadi draft.</div>:<div className="mt-4 flex gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800"><CircleAlert className="size-4" />{selected.generator_readiness==='ai_cpmk_required'?'Draft dapat dibuat, CPMK ditentukan tahap berikutnya.':'Harus direview Admin.'}</div>}</div>
                        <div className="mt-5 space-y-4"><select value={curriculumId} onChange={(e)=>{setCurriculumId(e.target.value);form.setData('course_id','')}} className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm">{curriculums.map((c)=><option key={c.id} value={c.id}>{c.name}</option>)}</select><input value={form.data.academic_year} onChange={(e)=>form.setData('academic_year',e.target.value)} className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm" /><select value={form.data.academic_semester} onChange={(e)=>form.setData('academic_semester',e.target.value)} className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"><option>Ganjil</option><option>Genap</option><option>Pendek</option></select></div>
                        <button type="submit" disabled={selected.generator_readiness==='needs_admin_review' || form.processing || isDelaying} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white disabled:opacity-45"><BookOpenCheck className="size-4" />{isDelaying || form.processing ? 'Menyiapkan Draft…' : 'Buat Draft RPS'}</button>
                    </> : <div className="mt-5 flex min-h-[430px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-8 text-center"><Search className="size-9 text-slate-300" /><p className="mt-4 font-semibold">Belum memilih mata kuliah</p><p className="mt-1 text-sm text-slate-500">Cari dan klik mata kuliah di panel kiri.</p></div>}
                </section>
            </form>
        </div>
    </>;
}

CreateRps.layout = { breadcrumbs: [{ title: 'Buat RPS', href: '/rps/baru' }] };
