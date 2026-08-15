import { Head } from '@inertiajs/react';
import { AlertTriangle, BookOpenCheck, GraduationCap, LibraryBig, Network } from 'lucide-react';
import { useMemo, useState } from 'react';

type Course = {
    id: string;
    system_code: string;
    official_code?: string | null;
    name: string;
    credits: number;
    semester_recommended?: number | null;
    is_mandatory: boolean;
    cpl_codes: string[];
    cpmk_count: number;
    has_syllabus: boolean;
    readiness: string;
};

export default function CurriculumPage({
    curriculum,
    summary,
    cpls,
    courses,
    issues,
}: {
    curriculum: any;
    summary: any;
    cpls: any[];
    courses: Course[];
    issues: any[];
}) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();
        if (!term) return courses;
        return courses.filter((course) =>
            `${course.system_code} ${course.official_code || ''} ${course.name}`
                .toLowerCase()
                .includes(term),
        );
    }, [courses, query]);

    return (
        <>
            <Head title="Kurikulum" />

            <div className="p-4 md:p-6">
                <div>
                    <div className="text-xs font-bold uppercase tracking-wider text-teal-700">Master Akademik</div>
                    <h1 className="mt-1 text-2xl font-bold tracking-tight">{curriculum.name}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Berlaku mulai {curriculum.effective_academic_year}. Data sumber dipertahankan dan konflik dicatat sebagai isu.
                    </p>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        ['CPL', summary.cpl, Network],
                        ['KBK', summary.kbk, LibraryBig],
                        ['Mata Kuliah', summary.courses, GraduationCap],
                        ['MK-CPL', summary.courseCpl, Network],
                        ['CPMK', summary.cpmk, BookOpenCheck],
                        ['Isu', summary.issues, AlertTriangle],
                    ].map(([label, value, Icon]: any) => (
                        <div key={label} className="sim-surface rounded-2xl p-4">
                            <Icon className="size-4 text-teal-700" />
                            <div className="mt-3 text-xs text-slate-500">{label}</div>
                            <div className="mt-1 text-2xl font-bold text-slate-900">{value}</div>
                        </div>
                    ))}
                </div>

                <section className="sim-surface mt-6 rounded-2xl p-5">
                    <h2 className="font-bold text-slate-900">8 CPL Resmi</h2>
                    <div className="mt-4 grid gap-3 lg:grid-cols-2">
                        {cpls.map((cpl) => (
                            <div key={cpl.id} className="rounded-xl bg-teal-50/45 p-4">
                                <div className="font-bold text-teal-800">{cpl.code}</div>
                                <div className="mt-1 text-sm leading-6 text-slate-600">{cpl.description}</div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="sim-surface mt-6 overflow-hidden rounded-2xl">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-900">Mata Kuliah</h2>
                            <p className="mt-1 text-sm text-slate-500">{filtered.length} dari {courses.length} mata kuliah.</p>
                        </div>
                        <input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari kode atau mata kuliah..."
                            className="w-full rounded-xl border border-slate-200 bg-white/70 px-4 py-2.5 text-sm md:w-80"
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50/70 text-left text-xs uppercase tracking-wider text-slate-400">
                                <tr>
                                    <th className="px-5 py-4">Mata Kuliah</th>
                                    <th className="px-5 py-4">Sem.</th>
                                    <th className="px-5 py-4">CPL</th>
                                    <th className="px-5 py-4">CPMK</th>
                                    <th className="px-5 py-4">Status Generator</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filtered.map((course) => {
                                    const label =
                                        course.readiness === 'ready_with_master_cpmk'
                                            ? 'Siap'
                                            : course.readiness === 'ai_cpmk_required'
                                                ? 'Perlu CPMK'
                                                : 'Review Admin';
                                    const cls =
                                        course.readiness === 'ready_with_master_cpmk'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : course.readiness === 'ai_cpmk_required'
                                                ? 'bg-amber-50 text-amber-700'
                                                : 'bg-rose-50 text-rose-700';

                                    return (
                                        <tr key={course.id}>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">{course.name}</div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {course.official_code || course.system_code} · {course.credits} SKS
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">{course.semester_recommended ?? '-'}</td>
                                            <td className="px-5 py-4">
                                                <div className="flex max-w-sm flex-wrap gap-1">
                                                    {course.cpl_codes.map((code) => (
                                                        <span key={code} className="rounded-full bg-teal-50 px-2 py-1 text-[10px] font-bold text-teal-700">
                                                            {code}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 font-bold text-slate-700">{course.cpmk_count}</td>
                                            <td className="px-5 py-4">
                                                <span className={`rounded-full px-3 py-1 text-xs font-bold ${cls}`}>{label}</span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="sim-surface mt-6 rounded-2xl p-5">
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="size-5 text-amber-600" />
                        <h2 className="font-bold text-slate-900">Isu Data Kurikulum</h2>
                    </div>
                    <p className="mt-1 text-sm text-slate-500">
                        Perbedaan pada dokumen sumber tidak diperbaiki diam-diam.
                    </p>

                    <div className="mt-4 space-y-3">
                        {issues.map((issue) => (
                            <details key={issue.id} className="rounded-xl border border-amber-100 bg-amber-50/45 p-4">
                                <summary className="cursor-pointer font-semibold text-slate-800">
                                    {issue.entity_key || issue.issue_code}
                                    <span className="ml-2 text-xs font-normal text-amber-700">{issue.severity}</span>
                                </summary>
                                <div className="mt-3 text-sm leading-6 text-slate-600">{issue.issue_description}</div>
                                {issue.selected_value && (
                                    <div className="mt-2 text-sm"><strong>Nilai master:</strong> {issue.selected_value}</div>
                                )}
                            </details>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

CurriculumPage.layout = {
    breadcrumbs: [{ title: 'Kurikulum', href: '/admin/kurikulum' }],
};
