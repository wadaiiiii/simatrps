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

    const previewRps = async () => {
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
        printStyle.setAttribute('data-rps-print-overrides', 'preview-v3');
        printStyle.textContent = `
            @page rpsLandscape { size: A4 landscape; margin: 7mm 7mm; }
            @page rpsPortrait { size: A4 portrait; margin: 10mm 10mm; }

            @media print {
                html.rps-print-mode,
                html.rps-print-mode body {
                    background: #fff !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                html.rps-print-mode table,
                html.rps-print-mode th,
                html.rps-print-mode td {
                    border-color: #000 !important;
                    box-sizing: border-box !important;
                }

                html.rps-print-mode table {
                    border-collapse: collapse !important;
                    border-spacing: 0 !important;
                }

                html.rps-print-mode th,
                html.rps-print-mode td {
                    border-width: 1px !important;
                    border-style: solid !important;
                }

                html.rps-print-mode .rps-print-label::after {
                    font-size: 11pt !important;
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

                /* Preview v3 - isi utama RPS dibuat besar dan terbaca. */
                html.rps-print-mode .rps-print-main-table {
                    width: 100% !important;
                    table-layout: fixed !important;
                    font-size: 12pt !important;
                    line-height: 1.26 !important;
                    color: #111827 !important;
                }

                html.rps-print-mode .rps-print-main-table th,
                html.rps-print-mode .rps-print-main-table td {
                    padding: 4px 6px !important;
                    vertical-align: top !important;
                }

                html.rps-print-mode .rps-print-main-table > tbody > tr:first-child + tr th {
                    font-size: 12pt !important;
                    line-height: 1.15 !important;
                    padding: 5px !important;
                }

                html.rps-print-mode .rps-print-main-table tr {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                /* Tabel rencana pembelajaran mingguan: 12 pt, A4 landscape, border hitam. */
                html.rps-print-mode .rps-print-weekly {
                    width: 100% !important;
                    table-layout: fixed !important;
                    font-size: 12pt !important;
                    line-height: 1.25 !important;
                    color: #111827 !important;
                }

                html.rps-print-mode .rps-print-weekly thead {
                    display: table-header-group !important;
                }

                html.rps-print-mode .rps-print-weekly thead th {
                    font-size: 12pt !important;
                    line-height: 1.16 !important;
                    padding: 5px 4px !important;
                    vertical-align: middle !important;
                }

                html.rps-print-mode .rps-print-weekly tbody td {
                    padding: 5px 5px !important;
                    line-height: 1.28 !important;
                    vertical-align: top !important;
                    overflow-wrap: anywhere !important;
                }

                html.rps-print-mode .rps-print-weekly tbody tr,
                html.rps-print-mode .rps-print-weekly tbody td {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                /* Penilaian & evaluasi dibuat halaman landscape sendiri. */
                html.rps-print-mode .rps-print-evaluation {
                    width: 100% !important;
                    max-width: none !important;
                    margin: 0 !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    background: #fff !important;
                }

                html.rps-print-mode .rps-print-evaluation > div {
                    border: 0 !important;
                    padding: 0 !important;
                }

                html.rps-print-mode .rps-print-assessment-table,
                html.rps-print-mode .rps-print-simulation-table {
                    width: 100% !important;
                    min-width: 0 !important;
                    table-layout: fixed !important;
                    font-size: 12pt !important;
                    line-height: 1.22 !important;
                    color: #111827 !important;
                }

                html.rps-print-mode .rps-print-assessment-table th,
                html.rps-print-mode .rps-print-assessment-table td,
                html.rps-print-mode .rps-print-simulation-table th,
                html.rps-print-mode .rps-print-simulation-table td {
                    padding: 5px 5px !important;
                    vertical-align: middle !important;
                    overflow-wrap: anywhere !important;
                }

                html.rps-print-mode .rps-print-assessment-table tr,
                html.rps-print-mode .rps-print-simulation-table tr {
                    break-inside: avoid-page !important;
                    page-break-inside: avoid !important;
                }

                html.rps-print-mode .rps-print-assessment-table thead,
                html.rps-print-mode .rps-print-simulation-table thead {
                    display: table-header-group !important;
                }

                html.rps-print-mode .rps-print-simulation-title {
                    page: rpsLandscape;
                    break-before: page !important;
                    page-break-before: always !important;
                    margin-top: 0 !important;
                    padding-top: 0 !important;
                    font-size: 14pt !important;
                    line-height: 1.15 !important;
                }

                /* Skala nilai tetap portrait. */
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

                /* Preview v3 RTM: lebih kecil dari halaman RPS agar proporsional. */
                html.rps-print-mode .rps-print-rtm {
                    width: 100% !important;
                    max-width: 190mm !important;
                    margin-left: auto !important;
                    margin-right: auto !important;
                }

                html.rps-print-mode .rps-print-rtm-table {
                    width: 100% !important;
                    table-layout: fixed !important;
                    font-size: 9.5pt !important;
                    line-height: 1.24 !important;
                }

                html.rps-print-mode .rps-print-rtm-table th {
                    font-size: 9pt !important;
                    line-height: 1.18 !important;
                    font-weight: 700 !important;
                }

                html.rps-print-mode .rps-print-rtm-table th,
                html.rps-print-mode .rps-print-rtm-table td {
                    padding: 3px 5px !important;
                    vertical-align: top !important;
                }

                html.rps-print-mode .rps-print-rtm-table > tbody > tr:nth-child(2) th,
                html.rps-print-mode .rps-print-rtm-table > tbody > tr:nth-child(2) td {
                    font-size: 10pt !important;
                    line-height: 1.15 !important;
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
                    min-height: 22mm !important;
                }

                html.rps-print-mode .rps-print-rtm-card .rps-print-institution-grid img {
                    width: 16mm !important;
                    height: 16mm !important;
                    max-width: 16mm !important;
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
        addClass(assessmentContainer, 'rps-print-landscape');
        addClass(assessmentContainer, 'rps-print-evaluation');
        addClass(assessmentContainer, 'rps-print-page-break');
        addClass(assessmentTable, 'rps-print-assessment-table');
        addClass(simulationTable, 'rps-print-simulation-table');

        const simulationTitle = Array.from(document.querySelectorAll<HTMLElement>('div'))
            .find((element) => normalizedText(element.textContent) === 'Simulasi');
        addClass(simulationTitle, 'rps-print-simulation-title');

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
            image.setAttribute('src', `${originalSrc}${separator}preview=${Date.now()}`);
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
                    onClick={previewRps}
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
