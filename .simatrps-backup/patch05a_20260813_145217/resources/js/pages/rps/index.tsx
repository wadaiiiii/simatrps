import { Head, Link } from '@inertiajs/react';
import { FilePlus2, Files } from 'lucide-react';

type RpsRow = {
    id: string;
    academic_year: string;
    academic_semester: string;
    status: string;
    updated_at: string;
    course_name: string;
    system_code: string;
    official_code?: string | null;
    credits: number;
    semester_recommended?: number | null;
};

export default function RpsIndex({ rpsRows }: { rpsRows: RpsRow[] }) {
    return (
        <>
            <Head title="RPS Saya" />

            <div className="p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">RPS Saya</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Daftar RPS yang Anda susun di SiMatRPS.
                        </p>
                    </div>

                    <Link href="/rps/baru" className="inline-flex w-fit items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">
                        <FilePlus2 className="size-4" />
                        Buat RPS
                    </Link>
                </div>

                <div className="sim-surface mt-6 overflow-hidden rounded-2xl">
                    {rpsRows.length === 0 ? (
                        <div className="flex min-h-80 flex-col items-center justify-center p-8 text-center">
                            <Files className="size-10 text-slate-300" />
                            <p className="mt-4 font-semibold text-slate-900">Belum ada data RPS</p>
                            <p className="mt-1 text-sm text-slate-500">Pilih Buat RPS untuk membuat dokumen pertama.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50/70 text-left text-xs uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th className="px-5 py-4">Mata Kuliah</th>
                                        <th className="px-5 py-4">Tahun Akademik</th>
                                        <th className="px-5 py-4">Status</th>
                                        <th className="px-5 py-4"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rpsRows.map((row) => (
                                        <tr key={row.id} className="hover:bg-teal-50/30">
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">{row.course_name}</div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {row.official_code || row.system_code} · {row.credits} SKS
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">
                                                {row.academic_year} · {row.academic_semester}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                                    {row.status.toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link href={`/rps/${row.id}`} className="font-bold text-teal-700">
                                                    Buka →
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

RpsIndex.layout = {
    breadcrumbs: [{ title: 'RPS Saya', href: '/rps' }],
};
