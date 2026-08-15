import { Head, Link } from '@inertiajs/react';
import { FilePlus2, Files } from 'lucide-react';

export default function RpsIndex() {
    return (
        <>
            <Head title="RPS Saya" />
            <div className="p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">RPS Saya</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Daftar seluruh RPS yang Anda susun di SiMatRPS.
                        </p>
                    </div>
                    <Link href="/rps/baru" className="inline-flex w-fit items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground">
                        <FilePlus2 className="size-4" />
                        Buat RPS
                    </Link>
                </div>

                <div className="mt-6 flex min-h-80 flex-col items-center justify-center rounded-2xl border bg-card p-8 text-center">
                    <Files className="size-10 text-muted-foreground/60" />
                    <p className="mt-4 font-semibold">Belum ada data RPS</p>
                    <p className="mt-1 max-w-md text-sm text-muted-foreground">
                        Modul data RPS dan versioning akan diaktifkan setelah master kurikulum dipindahkan ke Laravel.
                    </p>
                </div>
            </div>
        </>
    );
}

RpsIndex.layout = {
    breadcrumbs: [{ title: 'RPS Saya', href: '/rps' }],
};
