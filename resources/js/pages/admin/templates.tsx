import { Head } from '@inertiajs/react';
import { BookOpenCheck } from 'lucide-react';

export default function Page() {
    return (
        <>
            <Head title="Template RPS" />
            <div className="p-4 md:p-6">
                <h1 className="text-2xl font-bold tracking-tight">Template RPS</h1>
                <p className="mt-1 text-sm text-muted-foreground">Kelola format keluaran RPS secara terpisah dari data OBE.</p>

                <div className="mt-6 flex min-h-72 flex-col items-center justify-center rounded-2xl border bg-card p-8 text-center">
                    <div className="rounded-2xl bg-primary/10 p-4 text-primary">
                        <BookOpenCheck className="size-8" />
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
    breadcrumbs: [{ title: 'Template RPS', href: '/admin/template-rps' }],
};
