import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';

type SimatRpsHeaderPageProps = {
    auth?: {
        user?: {
            role?: string;
        };
    };
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<SimatRpsHeaderPageProps>();
    const isAdmin = page.props.auth?.user?.role === 'admin';
    const isRpsDetail = /^\/rps\/(?!baru(?:\/|$))[^/?#]+(?:[?#].*)?$/.test(page.url);

    const printRps = () => {
        const root = document.documentElement;
        const style = document.createElement('style');

        root.classList.add('rps-print-mode');
        style.setAttribute('data-rps-print-page', 'true');
        style.textContent = '@page { size: A4 landscape; margin: 7mm; }';
        document.head.appendChild(style);

        const cleanup = () => {
            root.classList.remove('rps-print-mode');
            style.remove();
            window.removeEventListener('afterprint', cleanup);
        };

        window.addEventListener('afterprint', cleanup);
        window.print();
    };

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 print:hidden">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            {isAdmin && isRpsDetail && (
                <button
                    type="button"
                    onClick={printRps}
                    className="inline-flex items-center gap-2 rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-teal-800"
                    title="Uji cetak RPS sebagai Admin"
                >
                    <Printer className="size-4" />
                    Cetak / Simpan PDF
                </button>
            )}
        </header>
    );
}