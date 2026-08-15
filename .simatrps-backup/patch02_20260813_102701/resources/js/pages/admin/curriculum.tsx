import { Head } from '@inertiajs/react';
import { LibraryBig } from 'lucide-react';

export default function Page() {
    return (
        <>
            <Head title="Kurikulum" />
            <div className="p-4 md:p-6">
                <h1 className="text-2xl font-bold tracking-tight">Kurikulum</h1>
                <p className="mt-1 text-sm text-muted-foreground">Kelola master kurikulum, CPL, KBK, mata kuliah dan relasi OBE.</p>

                <div className="mt-6 flex min-h-72 flex-col items-center justify-center rounded-2xl border bg-card p-8 text-center">
                    <div className="rounded-2xl bg-primary/10 p-4 text-primary">
                        <LibraryBig className="size-8" />
                    </div>
                    <p className="mt-4 font-semibold">Modul Admin SiMatRPS</p>
                    <p className="mt-1 max-w-md text-sm text-muted-foreground">
                        Fondasi halaman sudah aktif. Fungsi CRUD dan data master akan ditambahkan pada tahap berikutnya.
                    </p>
                </div>
            </div>
        </>
    );
}

Page.layout = {
    breadcrumbs: [{ title: 'Kurikulum', href: '/admin/kurikulum' }],
};
