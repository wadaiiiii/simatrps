<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            /* Bobot pada tabel turunan hanya display hasil sinkronisasi asesmen. */
            input[data-rps-synced-weight='true'] {
                pointer-events: none !important;
                border-color: transparent !important;
                background: transparent !important;
                box-shadow: none !important;
                color: #334155 !important;
                opacity: 1 !important;
                -webkit-text-fill-color: #334155 !important;
                cursor: default !important;
            }

            /* Presentasi dokumen RPS pada layar dan preview. */
            [data-rps-cpl-related-label='true'] {
                color: #111827 !important;
                text-transform: none !important;
            }

            [data-rps-cpmk-description='true'] {
                display: inline-block;
            }

            [data-rps-cpmk-description='true']::first-letter {
                text-transform: uppercase;
            }

            /* Matriks langsung memakai kode Sub-CPMK, tanpa alias S1/S2/S3. */
            table[data-rps-sub-matrix='true'] thead th:not(:first-child) > div:first-child {
                display: none !important;
            }

            table[data-rps-sub-matrix='true'] thead th:not(:first-child) > div:last-child {
                color: #334155 !important;
                font-size: 10px !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
            }

            /*
             * Final pagination guard for RPS Preview.
             * This selector intentionally has higher specificity than the
             * runtime preview style injected by app-sidebar-header.tsx.
             */
            @media print {
                /*
                 * Header -> identity -> CP -> description -> materials ->
                 * references -> lecturer -> prerequisite stays one continuous
                 * table, but long rows may naturally continue when the page
                 * really runs out of space. This removes large blank areas.
                 */
                html.rps-print-mode body .rps-print-main-table,
                html.rps-print-mode body .rps-print-main-table tbody,
                html.rps-print-mode body .rps-print-main-table tr,
                html.rps-print-mode body .rps-print-main-table th,
                html.rps-print-mode body .rps-print-main-table td {
                    break-inside: auto !important;
                    page-break-inside: auto !important;
                    break-before: auto !important;
                    break-after: auto !important;
                    page-break-before: auto !important;
                    page-break-after: auto !important;
                }

                /* MK prasyarat mengikuti ukuran isi standar yang lebih kecil. */
                html.rps-print-mode body tr[data-rps-prerequisite-row='true'],
                html.rps-print-mode body tr[data-rps-prerequisite-row='true'] td,
                html.rps-print-mode body tr[data-rps-prerequisite-row='true'] th,
                html.rps-print-mode body tr[data-rps-prerequisite-row='true'] * {
                    font-size: 9.5px !important;
                    line-height: 1.2 !important;
                }

                html.rps-print-mode body tr[data-rps-prerequisite-row='true'] td,
                html.rps-print-mode body tr[data-rps-prerequisite-row='true'] th {
                    padding-top: 2px !important;
                    padding-bottom: 2px !important;
                }

                /* Overflow containers must not interfere with print fragmentation. */
                html.rps-print-mode body .overflow-x-auto {
                    overflow: visible !important;
                    overflow-x: visible !important;
                    overflow-y: visible !important;
                }

                /* Dua baris ruang setelah MK prasyarat sebelum tabel mingguan. */
                html.rps-print-mode body .rps-print-weekly {
                    overflow: visible !important;
                    break-inside: auto !important;
                    page-break-inside: auto !important;
                    margin-top: 6mm !important;
                }

                html.rps-print-mode body .rps-print-weekly thead {
                    display: table-header-group !important;
                }

                /* Kop tabel dibuat tegas, termasuk saat diulang pada halaman lanjut. */
                html.rps-print-mode body .rps-print-weekly thead th {
                    border: 1.4px solid #000 !important;
                    box-shadow: none !important;
                }

                html.rps-print-mode body .rps-print-weekly thead tr:first-child th {
                    border-top-width: 1.8px !important;
                }

                html.rps-print-mode body .rps-print-weekly thead tr:last-child th {
                    border-bottom-width: 1.8px !important;
                }

                html.rps-print-mode body .rps-print-weekly tbody {
                    display: table-row-group !important;
                    break-inside: auto !important;
                    page-break-inside: auto !important;
                }

                /*
                 * Chromium is more reliable when the ROW is atomic and the
                 * cells themselves are allowed to fragment internally.
                 */
                html.rps-print-mode body .rps-print-weekly tbody tr {
                    display: table-row !important;
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                    break-before: auto !important;
                    break-after: auto !important;
                    page-break-before: auto !important;
                    page-break-after: auto !important;
                }

                html.rps-print-mode body .rps-print-weekly tbody td {
                    break-inside: auto !important;
                    page-break-inside: auto !important;
                }

                /*
                 * Typography benchmark = the native weekly RPS table.
                 * Body/header cells use 11px. Only major document/table titles
                 * use 12px. This intentionally overrides the older 11pt rules.
                 */
                html.rps-print-mode.rps-print-mode body table.rps-print-main-table.rps-print-main-table *,
                html.rps-print-mode.rps-print-mode body table.rps-print-weekly.rps-print-weekly *,
                html.rps-print-mode.rps-print-mode body .rps-print-evaluation.rps-print-evaluation *,
                html.rps-print-mode.rps-print-mode body .rps-print-grade-scale.rps-print-grade-scale *,
                html.rps-print-mode.rps-print-mode body .rps-print-rtm.rps-print-rtm * {
                    font-size: 11px !important;
                    line-height: 1.22 !important;
                }

                html.rps-print-mode.rps-print-mode body table.rps-print-main-table.rps-print-main-table > tbody > tr:nth-child(2) th,
                html.rps-print-mode.rps-print-mode body .rps-print-evaluation.rps-print-evaluation > div > div:first-child > div:first-child > div:first-child,
                html.rps-print-mode.rps-print-mode body .rps-print-simulation-title.rps-print-simulation-title,
                html.rps-print-mode.rps-print-mode body .rps-print-rtm.rps-print-rtm > div > div:first-child,
                html.rps-print-mode.rps-print-mode body table.rps-print-rtm-table.rps-print-rtm-table > tbody > tr:nth-child(2) th {
                    font-size: 12px !important;
                    line-height: 1.18 !important;
                }

                /*
                 * Setelah Tabel Penilaian & Evaluasi CPL, Simulasi langsung
                 * mengisi ruang halaman yang masih tersedia. Jangan paksa page baru.
                 */
                html.rps-print-mode.rps-print-mode body .rps-print-simulation-title.rps-print-simulation-title {
                    break-before: auto !important;
                    page-break-before: auto !important;
                    break-after: avoid !important;
                    page-break-after: avoid !important;
                    margin-top: 5mm !important;
                    padding-top: 0 !important;
                }

                html.rps-print-mode body .rps-print-simulation-table {
                    break-before: auto !important;
                    page-break-before: auto !important;
                }

                /* RTM tetap selalu dimulai pada lembar baru. */
                html.rps-print-mode.rps-print-mode body .rps-print-rtm.rps-print-rtm,
                html.rps-print-mode.rps-print-mode body .rps-print-rtm-card.rps-print-page-break {
                    break-before: page !important;
                    page-break-before: always !important;
                }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        <script>
            (function () {
                let scheduled = false;

                const normalizedText = (value) => String(value || '').replace(/\s+/g, ' ').trim();

                const lockInput = (input) => {
                    if (!(input instanceof HTMLInputElement)) return;
                    if (input.dataset.rpsSyncedWeight === 'true') return;

                    input.dataset.rpsSyncedWeight = 'true';
                    input.disabled = true;
                    input.readOnly = true;
                    input.tabIndex = -1;
                    input.setAttribute('aria-readonly', 'true');
                    input.title = 'Bobot tersinkron otomatis dari Edit Detail Asesmen.';

                    const display = Array.from(input.parentElement?.querySelectorAll('span') ?? [])
                        .find((span) => span instanceof HTMLElement);

                    if (display instanceof HTMLElement) {
                        const raw = String(input.value ?? '').trim();
                        const numeric = raw === '' ? null : Number(raw);
                        const text = numeric === null || Number.isNaN(numeric)
                            ? '—'
                            : String(Number(numeric.toFixed(2)));

                        display.dataset.rpsSyncedWeightDisplay = 'true';
                        if (display.textContent !== text) {
                            display.textContent = text;
                        }
                    }

                    input.style.display = 'none';
                };

                const syncAssessmentPresentation = (tables) => {
                    const evaluationTable = tables.find((table) =>
                        normalizedText(table.textContent).includes('Bobot per Bentuk Penilaian')
                    );

                    if (evaluationTable) {
                        /*
                         * Header evaluasi memakai nama asesmen resmi yang sama
                         * dengan Edit Detail Asesmen, termasuk saat print.
                         */
                        evaluationTable.querySelectorAll('thead tr:nth-child(2) th').forEach((th) => {
                            const input = th.querySelector('input:not([type="number"])');
                            if (!(input instanceof HTMLInputElement)) return;

                            const actualName = input.value.trim();
                            const printSpan = Array.from(th.querySelectorAll('span')).find((span) =>
                                String(span.className || '').includes('print:inline')
                            );

                            if (printSpan instanceof HTMLElement && actualName && printSpan.textContent !== actualName) {
                                printSpan.textContent = actualName;
                            }

                            const compactLabel = input.parentElement?.querySelector('div.mt-1');
                            if (compactLabel instanceof HTMLElement) {
                                compactLabel.style.display = 'none';
                            }
                        });

                        const totalRow = evaluationTable.querySelector('tbody tr:last-child');
                        const totalCell = totalRow?.querySelector('td:last-child');
                        const total = Number(normalizedText(totalCell?.textContent).replace(',', '.'));

                        if (!Number.isNaN(total)) {
                            const editor = document.querySelector('section > details.group');
                            const aiButton = Array.from(editor?.querySelectorAll('button') ?? []).find((button) =>
                                normalizedText(button.textContent).includes('Telaah Asesmen + RTM AI')
                            );

                            if (aiButton instanceof HTMLButtonElement && aiButton.parentElement) {
                                let badge = aiButton.parentElement.querySelector('[data-rps-assessment-total-badge="true"]');

                                if (!(badge instanceof HTMLElement)) {
                                    badge = document.createElement('span');
                                    badge.dataset.rpsAssessmentTotalBadge = 'true';
                                    aiButton.parentElement.insertBefore(badge, aiButton);
                                }

                                const rounded = Number(total.toFixed(2));
                                const text = `Total bobot: ${rounded}%`;
                                const tone = Math.abs(rounded - 100) < 0.01
                                    ? 'ok'
                                    : rounded > 100
                                        ? 'error'
                                        : 'warn';

                                if (badge.textContent !== text) badge.textContent = text;
                                badge.dataset.tone = tone;
                            }
                        }
                    }

                    const simulationTable = tables.find((table) => {
                        const text = normalizedText(table.textContent);
                        return text.includes('TOTAL NILAI AKHIR') && text.includes('Nilai Mhs');
                    });

                    if (simulationTable) {
                        const questionHeader = simulationTable.querySelector('thead th:nth-child(5)');
                        const label = 'Nama Asesmen / Bentuk Penilaian';
                        if (questionHeader instanceof HTMLElement && questionHeader.textContent !== label) {
                            questionHeader.textContent = label;
                        }
                    }

                    const fillEmptyButton = Array.from(document.querySelectorAll('button')).find((button) =>
                        normalizedText(button.textContent) === 'Isi Kosong'
                    );

                    if (fillEmptyButton instanceof HTMLButtonElement) {
                        fillEmptyButton.title = 'Mengisi field kosong dari Sub-CPMK, silabus/bahan kajian, SKS/praktikum, dan pustaka RPS. Tidak mengubah bobot asesmen.';
                    }
                };

                const markDocumentPresentation = () => {
                    const tables = Array.from(document.querySelectorAll('table'));
                    const mainTable = tables.find((table) =>
                        normalizedText(table.textContent).includes('RENCANA PEMBELAJARAN SEMESTER (RPS)')
                    );

                    if (mainTable) {
                        /* CPL terkait: hitam, kapitalisasi normal. */
                        mainTable.querySelectorAll('span').forEach((span) => {
                            if (normalizedText(span.textContent).toLowerCase() === 'cpl terkait:') {
                                span.dataset.rpsCplRelatedLabel = 'true';
                            }
                        });

                        /* Huruf pertama rumusan CPMK dibuat kapital tanpa mengubah data server. */
                        mainTable.querySelectorAll('tbody > tr').forEach((row) => {
                            const firstCell = row.querySelector('td:first-child');
                            const firstText = normalizedText(firstCell?.textContent);

                            if (!/^CPMK\s*\d+/i.test(firstText)) return;

                            const description = row.querySelector('td[colspan="4"] > div > div:first-child > span:first-child');
                            if (description instanceof HTMLElement) {
                                description.dataset.rpsCpmkDescription = 'true';
                            }
                        });

                        /* Tandai baris MK prasyarat untuk ukuran print yang lebih kecil. */
                        mainTable.querySelectorAll('tbody > tr').forEach((row) => {
                            const text = normalizedText(row.textContent).toLowerCase();
                            if (text.startsWith('matakuliah syarat') || text.startsWith('mk prasyarat')) {
                                row.dataset.rpsPrerequisiteRow = 'true';
                            }
                        });
                    }

                    /* Hilangkan alias S1/S2 dst; tampilkan langsung Sub-CPMK-n. */
                    tables.forEach((table) => {
                        const text = normalizedText(table.textContent);
                        if (text.includes('Baris ↓ CPMK') && text.includes('Kolom → Sub-CPMK')) {
                            table.dataset.rpsSubMatrix = 'true';
                        }
                    });

                    syncAssessmentPresentation(tables);
                };

                const lockSyncedWeights = () => {
                    scheduled = false;

                    if (!/^\/rps\/[^/]+$/.test(window.location.pathname)) return;

                    document.querySelectorAll('table').forEach((table) => {
                        const text = normalizedText(table.textContent);

                        /* Tabel RPS mingguan: kolom terakhir = Bobot Penilaian. */
                        if (
                            text.includes('Sub-CPMK')
                            && text.includes('Bobot Penilaian')
                            && text.includes('Bentuk Pembelajaran')
                        ) {
                            table.querySelectorAll('tbody tr td:last-child input[type="number"]')
                                .forEach(lockInput);
                        }

                        /* Tabel Penilaian & Evaluasi CPL: semua input angka = bobot asesmen. */
                        if (text.includes('Bobot per Bentuk Penilaian')) {
                            table.querySelectorAll('input[type="number"]')
                                .forEach(lockInput);
                        }

                        /* Simulasi: kolom ke-6 = Bobot (%); Nilai Mhs tetap editable. */
                        if (text.includes('TOTAL NILAI AKHIR') && text.includes('Nilai Mhs')) {
                            table.querySelectorAll('tbody tr td:nth-child(6) input[type="number"]')
                                .forEach(lockInput);
                        }
                    });

                    markDocumentPresentation();
                };

                const scheduleLock = () => {
                    if (scheduled) return;
                    scheduled = true;
                    window.requestAnimationFrame(lockSyncedWeights);
                };

                document.addEventListener('DOMContentLoaded', scheduleLock);
                document.addEventListener('inertia:finish', scheduleLock);

                const observer = new MutationObserver(scheduleLock);
                observer.observe(document.documentElement, {
                    childList: true,
                    subtree: true,
                });

                scheduleLock();
            })();
        </script>
    </body>
</html>
