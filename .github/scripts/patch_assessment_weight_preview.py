from pathlib import Path
import re

path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text(encoding='utf-8')
original = text

# 1) Make the stored total explicit in the UI badge.
if 'Total bobot:' in text:
    text = text.replace('Total bobot:', 'Total tersimpan:', 1)

# 2) Pass the persisted assessment total into every assessment editor card.
pattern = re.compile(
    r'(<AssessmentCard\s+key=\{assessment\.id\}\s+rpsId=\{rps\.id\}\s+assessment=\{assessment\}\s+subCpmks=\{subCpmks\})(\s*/>)',
    re.S,
)
text, count = pattern.subn(
    r'\1\n                                    assessmentTotal={progress.assessment_weight_total}\2',
    text,
    count=1,
)
if count != 1:
    raise SystemExit('AssessmentCard render marker not found exactly once')

# 3) Give AssessmentCard enough context to preview the projected total.
old_sig = 'function AssessmentCard({ rpsId, assessment, subCpmks }: any) {'
new_sig = 'function AssessmentCard({ rpsId, assessment, subCpmks, assessmentTotal }: any) {'
if old_sig not in text:
    raise SystemExit('AssessmentCard signature marker not found')
text = text.replace(old_sig, new_sig, 1)

old_system = "    const systemExam = ['UTS', 'UAS'].includes(assessment.code);\n"
new_system = """    const systemExam = ['UTS', 'UAS'].includes(assessment.code);\n    const storedAssessmentTotal = Number(assessmentTotal || 0);\n    const originalAssessmentWeight = Number(assessment.weight || 0);\n    const draftAssessmentWeight = form.data.weight === ''\n        ? 0\n        : Number(form.data.weight);\n    const safeDraftAssessmentWeight = Number.isFinite(draftAssessmentWeight)\n        ? draftAssessmentWeight\n        : 0;\n    const projectedAssessmentTotal = Math.round((\n        storedAssessmentTotal - originalAssessmentWeight + safeDraftAssessmentWeight\n    ) * 100) / 100;\n    const weightChanged = Math.abs(safeDraftAssessmentWeight - originalAssessmentWeight) > 0.001;\n    const weightWouldIncreaseOverLimit = projectedAssessmentTotal > 100.001\n        && projectedAssessmentTotal > storedAssessmentTotal + 0.001;\n"""
if old_system not in text:
    raise SystemExit('AssessmentCard systemExam marker not found')
text = text.replace(old_system, new_system, 1)

# 4) Stop invalid submit attempts before hitting the backend, while keeping backend protection.
start = text.index('function AssessmentCard(')
end = text.index('function TaskCard(', start)
card = text[start:end]
old_submit = """                e.preventDefault();\n\n                form.put("""
new_submit = """                e.preventDefault();\n\n                if (weightWouldIncreaseOverLimit) {\n                    notify(\n                        'error',\n                        `Jika disimpan total bobot menjadi ${projectedAssessmentTotal}%. Kurangi bobot asesmen lain terlebih dahulu.`,\n                    );\n                    return;\n                }\n\n                form.put("""
if old_submit not in card:
    raise SystemExit('AssessmentCard submit marker not found')
card = card.replace(old_submit, new_submit, 1)

# 5) Show the projected total directly under the edited weight field.
old_weight = """                <label>\n                    <span className=\"mb-1.5 block text-xs font-bold text-slate-500\">Bobot (%)</span>\n                    <input\n                        type=\"number\"\n                        min=\"0\"\n                        max=\"100\"\n                        step=\"0.01\"\n                        value={form.data.weight}\n                        onChange={(e) => form.setData('weight', e.target.value)}\n                        className=\"w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm\"\n                    />\n                </label>"""
new_weight = """                <label>\n                    <span className=\"mb-1.5 block text-xs font-bold text-slate-500\">Bobot (%)</span>\n                    <input\n                        type=\"number\"\n                        min=\"0\"\n                        max=\"100\"\n                        step=\"0.01\"\n                        value={form.data.weight}\n                        onChange={(e) => form.setData('weight', e.target.value)}\n                        className={`w-full rounded-xl border bg-white px-3 py-2.5 text-sm ${\n                            weightWouldIncreaseOverLimit\n                                ? 'border-rose-300 bg-rose-50/40 text-rose-800 focus:border-rose-400'\n                                : 'border-slate-200'\n                        }`}\n                    />\n                    {weightChanged && (\n                        <div className={`mt-1 text-[10px] font-semibold ${\n                            weightWouldIncreaseOverLimit ? 'text-rose-700' : 'text-slate-500'\n                        }`}>\n                            Jika disimpan: {projectedAssessmentTotal}%\n                            {weightWouldIncreaseOverLimit\n                                ? ` · kelebihan ${Number((projectedAssessmentTotal - 100).toFixed(2))}%`\n                                : projectedAssessmentTotal < 99.999\n                                  ? ` · sisa ${Number((100 - projectedAssessmentTotal).toFixed(2))}%`\n                                  : ' · total tepat 100%'}\n                        </div>\n                    )}\n                </label>"""
if old_weight not in card:
    raise SystemExit('AssessmentCard weight input marker not found')
card = card.replace(old_weight, new_weight, 1)

old_button = '<button disabled={form.processing} className="h-11 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white disabled:opacity-50">{form.processing ? \'Menyimpan...\' : \'Simpan\'}</button>'
new_button = '<button disabled={form.processing || weightWouldIncreaseOverLimit} title={weightWouldIncreaseOverLimit ? `Total akan menjadi ${projectedAssessmentTotal}%. Kurangi bobot asesmen lain.` : undefined} className="h-11 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{form.processing ? \'Menyimpan...\' : \'Simpan\'}</button>'
if old_button not in card:
    raise SystemExit('AssessmentCard save button marker not found')
card = card.replace(old_button, new_button, 1)

# Add a wider, explicit warning below the row so it is readable on narrow cards.
old_after_grid = """            </div>\n\n            <div className=\"mt-3\">"""
new_after_grid = """            </div>\n\n            {weightWouldIncreaseOverLimit && (\n                <div className=\"mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-semibold text-rose-700\">\n                    Total tersimpan {storedAssessmentTotal}%. Jika disimpan menjadi {projectedAssessmentTotal}%. Kurangi bobot asesmen lain terlebih dahulu.\n                </div>\n            )}\n\n            <div className=\"mt-3\">"""
if old_after_grid not in card:
    raise SystemExit('AssessmentCard post-grid marker not found')
card = card.replace(old_after_grid, new_after_grid, 1)

text = text[:start] + card + text[end:]

if text == original:
    raise SystemExit('No changes applied')

# Sanity markers.
for marker in [
    'Total tersimpan:',
    'assessmentTotal={progress.assessment_weight_total}',
    'Jika disimpan: {projectedAssessmentTotal}%',
    'weightWouldIncreaseOverLimit',
]:
    if marker not in text:
        raise SystemExit(f'Missing expected marker: {marker}')

path.write_text(text, encoding='utf-8')
print('Assessment weight preview patch applied')
