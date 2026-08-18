import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FilePlus2, Files, Trash2 } from 'lucide-react';
import { useState } from 'react';

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
    const [notice, setNotice] = useState<string | null>(null);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    const destroyRps = (row: RpsRow) => {
        const ok = window.confirm(
            `Hapus RPS "${row.course_name}" untuk ${row.academic_year} ${row.academic_semester}?\n\nSemua versi dan data kerja pada RPS ini ikut dihapus.`
        );

        if (!ok) return;

        setDeletingId(row.id);

        router.delete(`/rps/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setNotice(`RPS ${row.course_name} berhasil dihapus.`);
            },
            onError: (errors) => {
                const first = Object.values(errors ?? {}).flat()[0];
                setNotice(first ? String(first) : 'RPS gagal dihapus.');
            },
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <>
            <Head title="RPS Saya" />

            <div className="p-4 md:p-6">
                {notice && (
                    <div className="mb-4 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-800">
                        {notice}
                    </div>
                )}

                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">RPS Saya</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Daftar RPS yang Anda susun di SiMatRPS.
                        </p>
                    </div>

                    <Link
                        href="/rps/baru"
                        className="inline-flex w-fit items-center gap-2 rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white"
                    >
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
                                        <th className="px-5 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rpsRows.map((row) => {
                                        const status = String(row.status ?? 'draft').toLowerCase();
                                        const statusClass = status === 'final'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : status === 'draft'
                                                ? 'border-amber-200 bg-amber-50 text-amber-700'
                                                : 'border-slate-200 bg-slate-50 text-slate-600';

                                        return (
                                            <tr key={row.id} className="hover:bg-teal-50/30">
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">{row.course_name}</div>
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {row.official_code || row.system_code} | {row.credits} SKS
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 text-slate-600">
                                                    {row.academic_year} | {row.academic_semester}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className={`rounded-full border px-3 py-1 text-xs font-bold ${statusClass}`}>
                                                        {row.status.toUpperCase()}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/rps/${row.id}`}
                                                            className="inline-flex items-center gap-1.5 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-teal-800 hover:shadow-md"
                                                        >
                                                            Buka RPS
                                                            <ArrowRight className="size-3.5" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            disabled={deletingId === row.id}
                                                            onClick={() => destroyRps(row)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50 disabled:opacity-50"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                            {deletingId === row.id ? 'Menghapus...' : 'Hapus'}
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
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