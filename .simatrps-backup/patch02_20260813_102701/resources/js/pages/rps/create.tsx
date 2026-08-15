import { Head } from '@inertiajs/react';
import { BookOpenCheck, FilePlus2 } from 'lucide-react';

export default function CreateRps() {
    return (
        <>
            <Head title="Buat RPS" />
            <div className="p-4 md:p-6">
                <h1 className="text-2xl font-bold tracking-tight">Buat RPS</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Mata kuliah akan dipilih dari master kurikulum resmi.
                </p>

                <div className="mt-6 max-w-4xl rounded-2xl border bg-card p-6 md:p-8">
                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-primary/10 p-3 text-primary">
                            <FilePlus2 className="size-6" />
                        </div>
                        <div>
                            <p className="font-semibold">Generator RPS SiMatRPS</p>
                            <p className="text-sm text-muted-foreground">
                                Modul siap menerima master kurikulum pada tahap berikutnya.
                            </p>
                        </div>
                    </div>

                    <div className="mt-8 grid gap-4 md:grid-cols-3">
                        {[
                            ['1', 'Pilih Kurikulum', 'Kurikulum aktif Prodi Matematika'],
                            ['2', 'Pilih Mata Kuliah', 'Identitas, SKS, CPL dan CPMK otomatis'],
                            ['3', 'Buka Workspace', 'Susun Sub-CPMK, minggu, asesmen dan RTM'],
                        ].map(([number, title, text]) => (
                            <div key={number} className="rounded-xl border p-4">
                                <div className="flex size-8 items-center justify-center rounded-lg bg-muted text-sm font-bold">
                                    {number}
                                </div>
                                <p className="mt-4 font-semibold">{title}</p>
                                <p className="mt-1 text-sm leading-5 text-muted-foreground">{text}</p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-8 flex items-center gap-3 rounded-xl bg-muted/60 p-4">
                        <BookOpenCheck className="size-5 shrink-0 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Dropdown kurikulum dan mata kuliah akan aktif pada Patch 02.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

CreateRps.layout = {
    breadcrumbs: [{ title: 'Buat RPS', href: '/rps/baru' }],
};
