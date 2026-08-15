import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';

type SimatRpsHeaderPageProps = {
    auth?: {
        user?: {
            role?: string;
        };
    };
};

function normalizedText(value: string | null | undefined) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage<SimatRpsHeaderPageProps>();
    const isAdmin = page.props.auth?.user?.role === 'admin';
    const isRpsDetail = /^\/rps\/(?!baru(?:\/|$))[^/?#]+(?:[?#].*)?$/.test(page.url);

    const printRps = async () => {
        const root = document.documentElement;
        const cleanupActions: Array<() => void> = [];

        const addClass = (element: Element | null | undefined, className: string) => {
            if (!(element instanceof HTMLElement)) return;
            element.classList.add(className);
            cleanupActions.push(() => element.classList.remove(className));
        };

        const hideForPrint = (element: Element | null | undefined) => {
            if (!(element instanceof HTMLElement)) return;
            element.setAttribute('data-rps-print-hidden', 'true');
            cleanupActions.push(() => element.removeAttribute('data-rps-print-hidden'));
        };

        const relabelForPrint = (
            selector: string,
            currentText: string,
            printText: string,
            all = false,
        ) => {
            const matches = Array.from(document.querySelectorAll<HTMLElement>(selector))
                .filter((element) => normalizedText(element.textContent) === currentText);

            const selected = all ? matches : matches.slice(0, 1);
            selected.forEach((element) => {
                element.classList.add('rps-print-label');
                element.setAttribute('data-rps-print-label', printText);
                cleanupActions.push(() => {
                    element.classList.remove('rps-print-label');
                    element.removeAttribute('data-rps-print-label');
                });
            });
        };

        const tables = Array.from(document.querySelectorAll<HTMLTableElement>('table'));
        const findTable = (needle: string) => tables.find((table) =>
            normalizedText(table.textContent).includes(needle),
        );

        root.classList.add('rps-print-mode');

        const printStyle = document.createElement('style');
        printStyle.setAttribute('data-rps-print-overrides', 'true');
        printStyle.textContent = `
            @page rpsLandscape { size: A4 landscape; margin: 7mm 7mm; }
            @page rpsPortrait { size: A4 portrait; margin: 10mm 10mm; }

            @media print {
                html.rps-print-mode .rps-print-label::after {
                    font-size: 9.5pt !important;
                    line-height: 1.2 !important;
                }

                html.rps-print-mode .rps-print-institution-grid {
                    grid-template-columns: 27mm 1fr !important;
                    min-height: 25mm !important;
                }

                html.rps-print-mode .rps-print-institution-grid img {
                    width: 19mm !important;
                    height: 19mm !important;
                    max-width: 19mm !important;
                    object-fit: contain !important;
                }

                html.rps-print-mode .rps-print-main-table {
                    font-size: 9pt !important;
                    line-height: 1.28 !important;
                }

                html.rps-print-mode .rps-print-main-table th,
                html.rps-print-mode .rps-print-main-table td {
                    padding: 3.5px 5px !important;
                }

                html.rps-print-mode .rps-print-main-table > tbody > tr:first-child + tr th {
                    font-size: 11pt !important;
                    padding: 4px !important;
                }

                html.rps-print-mode .rps-print-main-table tr {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                html.rps-print-mode .rps-print-weekly {
                    table-layout: fixed !important;
                    font-size: 9pt !important;
                    line-height: 1.28 !important;
                }

                html.rps-print-mode .rps-print-weekly thead th {
                    font-size: 9pt !important;
                    line-height: 1.2 !important;
                    padding: 5px 4px !important;
                }

                html.rps-print-mode .rps-print-weekly tbody td {
                    padding: 5px 5px !important;
                    line-height: 1.3 !important;
                }

                html.rps-print-mode .rps-print-weekly tbody tr {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                html.rps-print-mode .rps-print-weekly tbody td {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                html.rps-print-mode .rps-print-assessment-table,
                html.rps-print-mode .rps-print-simulation-table {
                    font-size: 9.2pt !important;
                    line-height: 1.24 !important;
                }

                html.rps-print-mode .rps-print-assessment-table th,
                html.rps-print-mode .rps-print-assessment-table td,
                html.rps-print-mode .rps-print-simulation-table th,
                html.rps-print-mode .rps-print-simulation-table td {
                    padding: 4px 5px !important;
                }

                html.rps-print-mode .rps-print-assessment-table tr,
                html.rps-print-mode .rps-print-simulation-table tr {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                html.rps-print-mode .rps-print-grade-scale {
                    font-size: 11pt !important;
                    line-height: 1.45 !important;
                    padding-top: 5mm !important;
                }

                html.rps-print-mode .rps-print-grade-scale table {
                    width: 115mm !important;
                    min-width: 115mm !important;
                    margin: 5mm auto 0 !important;
                    font-size: 11pt !important;
                    line-height: 1.4 !important;
                }

                html.rps-print-mode .rps-print-grade-scale table th,
                html.rps-print-mode .rps-print-grade-scale table td {
                    padding: 4px 8px !important;
                }

                html.rps-print-mode .rps-print-rtm-table {
                    font-size: 10pt !important;
                    line-height: 1.32 !important;
                }

                html.rps-print-mode .rps-print-rtm-table th,
                html.rps-print-mode .rps-print-rtm-table td {
                    padding: 4px 6px !important;
                }

                html.rps-print-mode .rps-print-rtm-card {
                    margin: 0 !important;
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                    border-radius: 0 !important;
                }

                html.rps-print-mode .rps-print-rtm-card.rps-print-page-break {
                    break-before: page !important;
                    page-break-before: always !important;
                }

                html.rps-print-mode .rps-print-rtm-card .rps-print-institution-grid {
                    min-height: 24mm !important;
                }

                html.rps-print-mode .rps-print-rtm-card .rps-print-institution-grid img {
                    width: 18mm !important;
                    height: 18mm !important;
                    max-width: 18mm !important;
                }
            }
        `;
        document.head.appendChild(printStyle);
        cleanupActions.push(() => printStyle.remove());

        const mainRpsTable = findTable('RENCANA PEMBELAJARAN SEMESTER (RPS)');
        const weeklyTable = tables.find((table) => {
            const text = normalizedText(table.textContent);
            return text.includes('Sub-CPMK')
                && text.includes('Bentuk Pembelajaran; Metode Pembelajaran; Penugasan;')
                && text.includes('Bobot Penilaian');
        });
        const assessmentTable = findTable('Bobot per Bentuk Penilaian');
        const simulationTable = tables.find((table) => {
            const text = normalizedText(table.textContent);
            return text.includes('Nilai Mhs') && text.includes('TOTAL NILAI AKHIR');
        });
        const gradingTable = tables.find((table) => {
            const text = normalizedText(table.textContent);
            return text.includes('Nilai Angka') && text.includes('Nilai Huruf') && text.includes('Nilai Mutu');
        });
        const rtmTables = tables.filter((table) =>
            normalizedText(table.textContent).includes('RENCANA TUGAS MAHASISWA'),
        );

        const printableSection = mainRpsTable?.closest('section');
        addClass(printableSection, 'rps-print-landscape');
        addClass(mainRpsTable, 'rps-print-main-table');
        addClass(weeklyTable, 'rps-print-weekly');

        const assessmentContainer = assessmentTable?.closest('div.border-x');
        addClass(assessmentContainer, 'rps-print-portrait');
        addClass(assessmentContainer, 'rps-print-evaluation');
        addClass(assessmentContainer, 'rps-print-page-break');
        addClass(assessmentTable, 'rps-print-assessment-table');
        addClass(simulationTable, 'rps-print-simulation-table');

        const gradingContainer = gradingTable?.closest('div.mt-5');
        addClass(gradingContainer, 'rps-print-portrait');
        addClass(gradingContainer, 'rps-print-grade-scale');
        addClass(gradingContainer, 'rps-print-page-break');

        const rtmContainer = rtmTables[0]?.closest('div.border-x');
        addClass(rtmContainer, 'rps-print-portrait');
        addClass(rtmContainer, 'rps-print-rtm');
        addClass(rtmContainer, 'rps-print-page-break');

        rtmTables.forEach((table, index) => {
            addClass(table, 'rps-print-rtm-table');

            const card = table.closest('div.break-inside-avoid');
            addClass(card, 'rps-print-rtm-card');

            if (index > 0) {
                addClass(card, 'rps-print-page-break');
            }
        });

        const institutionTables = [mainRpsTable, ...rtmTables].filter(Boolean) as HTMLTableElement[];
        institutionTables.forEach((table) => {
            const firstGrid = table.querySelector<HTMLElement>('tbody > tr:first-child td > div.grid');
            addClass(firstGrid, 'rps-print-institution-grid');
        });

        Array.from(document.querySelectorAll<HTMLElement>('div'))
            .filter((element) => normalizedText(element.textContent) === 'JURUSAN MATEMATIKA')
            .forEach(hideForPrint);

        const mediaCell = Array.from(document.querySelectorAll<HTMLTableCellElement>('td'))
            .find((cell) => normalizedText(cell.textContent) === 'Media Pembelajaran');
        hideForPrint(mediaCell?.closest('tr'));

        const materialCell = Array.from(document.querySelectorAll<HTMLTableCellElement>('td'))
            .find((cell) => normalizedText(cell.textContent) === 'Bahan Kajian: Materi Pembelajaran');
        const materialList = materialCell?.nextElementSibling?.querySelector('ol');
        addClass(materialList, 'rps-print-decimal-list');

        relabelForPrint('td', 'Capaian Pembelajaran', 'Capaian Pembelajaran\n(CP)');
        relabelForPrint('td', 'Deskripsi Singkat MK', 'Diskripsi Singkat\nMata Kuliah');
        relabelForPrint('td', 'Bahan Kajian: Materi Pembelajaran', 'Bahan Kajian /\nMateri Pembelajaran');
        relabelForPrint('td', 'Pustaka', 'Daftar Referensi');
        relabelForPrint('td', 'Matakuliah Syarat', 'MK prasyarat');

        relabelForPrint('th', 'Pekan Ke-', 'Mg\nKe-');
        relabelForPrint('th', 'Kriteria & Bentuk', 'Kriteria &\nTeknik');
        relabelForPrint('th', 'Tatap muka / Luring', 'Luring (5)');
        relabelForPrint('th', 'Daring', 'Daring (6)');
        relabelForPrint(
            'th',
            'Bentuk Pembelajaran; Metode Pembelajaran; Penugasan; [Estimasi Waktu]',
            'Bentuk Pembelajaran;\nMetode Pembelajaran;\nPenugasan Mahasiswa;\n[Estimasi Waktu]',
        );

        const logoImages = Array.from(
            document.querySelectorAll<HTMLImageElement>('img[src*="logo-unsulbar"]'),
        );

        logoImages.forEach((image) => {
            const originalSrc = image.getAttribute('src') || '/logo-unsulbar.png';
            const separator = originalSrc.includes('?') ? '&' : '?';
            image.setAttribute('src', `${originalSrc}${separator}print=${Date.now()}`);
            cleanupActions.push(() => image.setAttribute('src', originalSrc));
        });

        await Promise.all(
            logoImages.map((image) => {
                if (image.complete && image.naturalWidth > 0) {
                    return Promise.resolve();
                }

                return new Promise<void>((resolve) => {
                    let finished = false;
                    const finish = () => {
                        if (finished) return;
                        finished = true;
                        resolve();
                    };

                    image.addEventListener('load', finish, { once: true });
                    image.addEventListener('error', finish, { once: true });
                    window.setTimeout(finish, 4000);
                });
            }),
        );

        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

        const cleanup = () => {
            [...cleanupActions].reverse().forEach((action) => action());
            root.classList.remove('rps-print-mode');
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
                    title="Preview RPS sebelum dicetak"
                >
                    <Eye className="size-4" />
                    Preview RPS
                </button>
            )}
        </header>
    );
}
