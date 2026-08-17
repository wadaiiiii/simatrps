from pathlib import Path

show_path = Path('resources/js/pages/rps/show.tsx')
controller_path = Path('app/Http/Controllers/RpsTaskController.php')
obe_path = Path('app/Services/Rps/ObeWorkspaceService.php')

show = show_path.read_text(encoding='utf-8')
controller = controller_path.read_text(encoding='utf-8')
obe = obe_path.read_text(encoding='utf-8')

# 1) Add a reusable navigator to the exact assessment card in Detail Asesmen.
anchor = """function actionOptions(message: string, afterSuccess?: () => void) {\n"""
helper = """function goToAssessmentItem(assessmentId: any) {\n    const id = String(assessmentId ?? '').trim();\n    if (!id) {\n        const section = document.getElementById('validator-target-assessment');\n        section?.scrollIntoView({ behavior: 'smooth', block: 'center' });\n        return;\n    }\n\n    const target = document.querySelector<HTMLElement>(`[data-assessment-id=\"${id}\"]`);\n    if (!target) {\n        const section = document.getElementById('validator-target-assessment');\n        section?.scrollIntoView({ behavior: 'smooth', block: 'center' });\n        notify('info', 'Detail Asesmen terkait belum ditemukan pada tampilan.');\n        return;\n    }\n\n    const parentDetails = target.closest('details') as HTMLDetailsElement | null;\n    if (parentDetails && !parentDetails.open) parentDetails.open = true;\n\n    target.scrollIntoView({ behavior: 'smooth', block: 'center' });\n    target.classList.add('ring-2', 'ring-amber-400', 'ring-offset-2');\n    window.setTimeout(() => {\n        target.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-2');\n    }, 4200);\n}\n\nfunction actionOptions(message: string, afterSuccess?: () => void) {\n"""
if 'function goToAssessmentItem(' not in show:
    if anchor not in show:
        raise SystemExit('actionOptions anchor not found')
    show = show.replace(anchor, helper, 1)

# 2) Make failed RTM deletion point to the exact assessment card.
old_delete = """                                        onError: (errors: Record<string, any>) => {\n                                            notify('error', `RTM tidak dihapus. ${firstError(errors)}`);\n                                        },\n"""
new_delete = """                                        onError: (errors: Record<string, any>) => {\n                                            const linkedAssessment = assessments.find(\n                                                (item: any) => String(item.id) === String(task.assessment_id || ''),\n                                            );\n                                            const assessmentLabel = linkedAssessment\n                                                ? `${linkedAssessment.code || 'Asesmen'} “${linkedAssessment.name}”`\n                                                : 'asesmen induk terkait';\n\n                                            notify(\n                                                'error',\n                                                `RTM tidak dihapus. ${firstError(errors)} Buka Detail Asesmen → ${assessmentLabel}; item tersebut disorot untuk diperiksa.`,\n                                            );\n                                            window.setTimeout(() => goToAssessmentItem(task.assessment_id), 80);\n                                        },\n"""
if old_delete not in show:
    raise SystemExit('RTM delete onError block not found')
show = show.replace(old_delete, new_delete, 1)

# 3) Rename validator preserve wording from relationship to content.
old_label = """                                                            if (count > 1) return `Pertahankan Semua (${count})`;\n                                                            return check.key === 'assessment_semantics'\n                                                                ? 'Pertahankan Tag'\n                                                                : 'Pertahankan Hubungan';\n"""
new_label = """                                                            if (count > 1) return `Pertahankan Semua Isi (${count})`;\n                                                            return check.key === 'assessment_semantics'\n                                                                ? 'Pertahankan Tag'\n                                                                : 'Pertahankan Isi';\n"""
if old_label not in show:
    raise SystemExit('validator preserve label block not found')
show = show.replace(old_label, new_label, 1)

# 4) Backend error names the exact Detail Asesmen item.
controller = controller.replace(
    "->first(['id', 'name', 'type', 'weight']);",
    "->first(['id', 'code', 'name', 'type', 'weight']);",
    1,
)
old_error = """                        'task' => 'RTM ini masih menjadi satu-satunya RTM untuk asesmen \"'\n                            .trim((string) $assessment->name)\n                            .'\". Jika hanya bentrok pekan, ubah Pekan Pengumpulan. Jika asesmennya tidak diperlukan, ubah atau hapus asesmen pada Detail Asesmen.',\n"""
new_error = """                        'task' => 'RTM ini masih menjadi satu-satunya RTM untuk asesmen '\n                            .trim((string) ($assessment->code ?? 'Asesmen')).' “'\n                            .trim((string) $assessment->name)\n                            .'”. Untuk menghapus RTM ini, buka Detail Asesmen → '\n                            .trim((string) ($assessment->code ?? 'Asesmen')).' “'\n                            .trim((string) $assessment->name)\n                            .'”, lalu ubah atau hapus asesmen tersebut terlebih dahulu. Jika hanya ingin memindahkan jadwal, ubah Pekan Pengumpulan RTM.',\n"""
if old_error not in controller:
    raise SystemExit('controller delete validation message not found')
controller = controller.replace(old_error, new_error, 1)

# 5) Validator message uses the same lecturer-facing wording.
old_obe = "Periksa asesmen terkait atau pertahankan hubungan jika memang disengaja."
new_obe = "Periksa asesmen terkait atau pertahankan isi jika memang disengaja."
if old_obe not in obe:
    raise SystemExit('OBE RTM semantics wording not found')
obe = obe.replace(old_obe, new_obe)

show_path.write_text(show, encoding='utf-8')
controller_path.write_text(controller, encoding='utf-8')
obe_path.write_text(obe, encoding='utf-8')
