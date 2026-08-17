from pathlib import Path

SHOW = Path('resources/js/pages/rps/show.tsx')
CSS = Path('resources/css/app.css')

show = SHOW.read_text(encoding='utf-8')

weekly_old = '''                    {/* Weekly table, exact print columns */}\n                    <div className="overflow-x-auto">\n                        <table className="rps-print-weekly min-w-[1180px] w-full border-separate border-spacing-0 text-[11px] leading-[1.45]">'''
weekly_new = '''                    {/* Weekly table, exact print columns */}\n                    <div className="rps-print-weekly-wrap overflow-x-auto">\n                        <table className="rps-print-weekly min-w-[1180px] w-full border-separate border-spacing-0 text-[11px] leading-[1.45]">'''
if weekly_old not in show:
    raise SystemExit('weekly wrapper marker not found')
show = show.replace(weekly_old, weekly_new, 1)

function_marker = 'function AssessmentEvaluationSection({' 
function_pos = show.find(function_marker)
if function_pos < 0:
    raise SystemExit('AssessmentEvaluationSection not found')

return_marker = '    return (\n        <div className="border-x border-b border-slate-300 bg-white">'
return_pos = show.find(return_marker, function_pos)
if return_pos < 0:
    raise SystemExit('assessment evaluation return wrapper not found')
show = show[:return_pos] + show[return_pos:].replace(
    return_marker,
    '    return (\n        <div className="rps-print-evaluation-break border-x border-b border-slate-300 bg-white">',
    1,
)

SHOW.write_text(show, encoding='utf-8')

css = CSS.read_text(encoding='utf-8-sig')
marker = 'Patch: final weekly continuous flow; evaluation starts a new page'
if marker not in css:
    css = css.rstrip() + r'''

/* Patch: final weekly continuous flow; evaluation starts a new page */
@media print {
    /* Setelah MK Prasyarat, tabel pekan langsung dimulai tanpa spacer cetak. */
    html.rps-print-mode .rps-print-week-gap {
        display: none !important;
        height: 0 !important;
        min-height: 0 !important;
        max-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Jangan pernah membuat page break buatan sebelum tabel pekan. */
    html.rps-print-mode .rps-print-weekly-wrap,
    html.rps-print-mode .rps-print-weekly {
        break-before: auto !important;
        page-break-before: auto !important;
        break-after: auto !important;
        page-break-after: auto !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
    }

    /* Tabel pekan adalah satu rangkaian kontinu. Browser boleh memotong isi
       pada batas halaman; header tabel tetap dapat diulang pada halaman lanjut. */
    html.rps-print-mode .rps-print-weekly tbody,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr > td {
        break-inside: auto !important;
        page-break-inside: auto !important;
        break-before: auto !important;
        page-break-before: auto !important;
        break-after: auto !important;
        page-break-after: auto !important;
    }

    html.rps-print-mode .rps-print-weekly thead {
        display: table-header-group !important;
        break-after: auto !important;
        page-break-after: auto !important;
    }

    /* Satu-satunya pemisah halaman setelah rangkaian RPS pekanan adalah
       TABEL PENILAIAN DAN EVALUASI CPL. */
    html.rps-print-mode .rps-print-evaluation-break {
        break-before: page !important;
        page-break-before: always !important;
        break-after: auto !important;
        page-break-after: auto !important;
    }
}
''' + '\n'

CSS.write_text(css, encoding='utf-8')
print('Final continuous weekly print patch applied.')
