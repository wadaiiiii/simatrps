from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"Missing marker: {label}")
    return text.replace(old, new, 1)


def insert_after_context(text: str, method_marker: str, line: str, label: str) -> str:
    start = text.find(method_marker)
    if start < 0:
        raise SystemExit(f"Missing method: {label}")
    ctx = text.find("$this->context($request, $rps);", start)
    if ctx < 0:
        raise SystemExit(f"Missing context: {label}")
    end = text.find("\n", ctx)
    if end < 0:
        raise SystemExit(f"Missing context newline: {label}")
    end += 1
    if line.strip() in text[end:end + 180]:
        return text
    return text[:end] + line + text[end:]


required_fields = [
    "course_cluster",
    "prepared_date",
    "published_date",
    "developer_name",
    "coordinator_name",
    "head_program_name",
    "lecturer_names",
    "software_media",
    "hardware_media",
    "prerequisite_text",
    "description_short",
]
required_php = "[\n" + "\n".join(f"            '{field}'," for field in required_fields) + "\n        ]"

helper = f"""    private function assertDocumentInfoReady(string $versionId): void
    {{
        $meta = DB::table('rps_document_meta')
            ->where('rps_version_id', $versionId)
            ->first();

        $required = {required_php};
        $missing = collect($required)
            ->filter(fn (string $field) => ! filled($meta->{{$field}} ?? null))
            ->values();

        if (! $meta || $missing->isNotEmpty()) {{
            throw ValidationException::withMessages([
                'document_info' => 'Lengkapi dan simpan Edit Informasi RPS terlebih dahulu sebelum mengatur Scope CPL atau menyusun CPMK.',
            ]);
        }}
    }}

"""

# -----------------------------------------------------------------------------
# RpsController: calculate readiness only from persisted document metadata.
# -----------------------------------------------------------------------------
p = Path("app/Http/Controllers/RpsController.php")
s = p.read_text(encoding="utf-8")
meta_start = s.find("        $documentMeta = [")
if meta_start < 0:
    raise SystemExit("Missing documentMeta array")
meta_end = s.find("\n        ];", meta_start)
if meta_end < 0:
    raise SystemExit("Missing documentMeta array end")
meta_end += len("\n        ];")
readiness = f"""

        $documentInfoRequiredFields = {required_php};
        $documentInfoReady = $storedMeta !== null
            && collect($documentInfoRequiredFields)->every(
                fn (string $field) => filled($storedMeta->{{$field}} ?? null)
            );"""
if "$documentInfoReady =" not in s:
    s = s[:meta_end] + readiness + s[meta_end:]
s = replace_once(
    s,
    """            'documentMeta' => $documentMeta,
            'masterSyllabus' => $masterSyllabus,
""",
    """            'documentMeta' => $documentMeta,
            'documentInfoReady' => $documentInfoReady,
            'masterSyllabus' => $masterSyllabus,
""",
    "RpsController documentInfoReady prop",
)
p.write_text(s, encoding="utf-8")

# -----------------------------------------------------------------------------
# RpsDocumentController: require core document information before it can save.
# References remain optional because they are authored later in the RPS flow.
# -----------------------------------------------------------------------------
p = Path("app/Http/Controllers/RpsDocumentController.php")
s = p.read_text(encoding="utf-8")
validations = {
    "'course_cluster' => ['nullable', 'string', 'max:255']": "'course_cluster' => ['required', 'string', 'max:255']",
    "'prepared_date' => ['nullable', 'date']": "'prepared_date' => ['required', 'date']",
    "'published_date' => ['nullable', 'date']": "'published_date' => ['required', 'date']",
    "'developer_name' => ['nullable', 'string', 'max:500']": "'developer_name' => ['required', 'string', 'max:500']",
    "'coordinator_name' => ['nullable', 'string', 'max:500']": "'coordinator_name' => ['required', 'string', 'max:500']",
    "'head_program_name' => ['nullable', 'string', 'max:500']": "'head_program_name' => ['required', 'string', 'max:500']",
    "'lecturer_names' => ['nullable', 'string', 'max:4000']": "'lecturer_names' => ['required', 'string', 'max:4000']",
    "'software_media' => ['nullable', 'string', 'max:4000']": "'software_media' => ['required', 'string', 'max:4000']",
    "'hardware_media' => ['nullable', 'string', 'max:4000']": "'hardware_media' => ['required', 'string', 'max:4000']",
    "'prerequisite_text' => ['nullable', 'string', 'max:4000']": "'prerequisite_text' => ['required', 'string', 'max:4000']",
    "'description_short' => ['nullable', 'string', 'max:8000']": "'description_short' => ['required', 'string', 'max:8000']",
}
for old, new in validations.items():
    s = replace_once(s, old, new, f"document validation {old}")
p.write_text(s, encoding="utf-8")

# -----------------------------------------------------------------------------
# ObeWorkspaceController: server-side gate for CPMK authoring and mapping.
# -----------------------------------------------------------------------------
p = Path("app/Http/Controllers/ObeWorkspaceController.php")
s = p.read_text(encoding="utf-8")
for method in [
    "public function storeCpmk(Request $request, string $rps): RedirectResponse",
    "public function importCurriculumCpmks(Request $request, string $rps): RedirectResponse",
    "public function saveCpmkCpl(Request $request, string $rps): RedirectResponse",
]:
    s = insert_after_context(
        s,
        method,
        "        $this->assertDocumentInfoReady($version->id);\n",
        method,
    )
meeting_helper = "    private function assertMeetingAllocationConfigured(string $versionId): void\n"
if meeting_helper not in s:
    raise SystemExit("Missing ObeWorkspace meeting helper")
if "private function assertDocumentInfoReady" not in s:
    s = s.replace(meeting_helper, helper + meeting_helper, 1)
p.write_text(s, encoding="utf-8")

# -----------------------------------------------------------------------------
# RpsCplScopeController: server-side gate for Scope CPL changes.
# -----------------------------------------------------------------------------
p = Path("app/Http/Controllers/RpsCplScopeController.php")
s = p.read_text(encoding="utf-8")
for method in [
    "public function store(Request $request, string $rps): RedirectResponse",
    "public function destroy(",
]:
    s = insert_after_context(
        s,
        method,
        "        $this->assertDocumentInfoReady($version->id);\n",
        method,
    )
context_helper = "    private function context(Request $request, string $rps): array\n"
if context_helper not in s:
    raise SystemExit("Missing RpsCplScope context helper")
if "private function assertDocumentInfoReady" not in s:
    s = s.replace(context_helper, helper + context_helper, 1)
p.write_text(s, encoding="utf-8")

# -----------------------------------------------------------------------------
# RpsAiController: CPMK-related AI requests/applications require document info.
# -----------------------------------------------------------------------------
p = Path("app/Http/Controllers/RpsAiController.php")
s = p.read_text(encoding="utf-8")
weekly_generate = """        if ($data['suggestion_type'] === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }
"""
if "in_array($data['suggestion_type'], ['cpmk_review', 'bloom_mapping', 'cpl_mapping']" not in s:
    s = replace_once(
        s,
        weekly_generate,
        """        if (in_array($data['suggestion_type'], ['cpmk_review', 'bloom_mapping', 'cpl_mapping'], true)) {
            $this->assertDocumentInfoReady($version->id);
        }

""" + weekly_generate,
        "AI generate document gate",
    )
weekly_apply = """        if ($row->suggestion_type === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }
"""
if "in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping']" not in s:
    s = replace_once(
        s,
        weekly_apply,
        """        if (in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping'], true)) {
            $this->assertDocumentInfoReady($version->id);
        }

""" + weekly_apply,
        "AI apply document gate",
    )
if meeting_helper not in s:
    raise SystemExit("Missing AI meeting helper")
if "private function assertDocumentInfoReady" not in s:
    s = s.replace(meeting_helper, helper + meeting_helper, 1)
p.write_text(s, encoding="utf-8")

# -----------------------------------------------------------------------------
# React UI.
# -----------------------------------------------------------------------------
p = Path("resources/js/pages/rps/show.tsx")
s = p.read_text(encoding="utf-8")
s = replace_once(
    s,
    """        documentMeta = {},
        masterSyllabus = { description: '', reference_text: '', supporting_reference_text: '', prerequisite_text: '' },
""",
    """        documentMeta = {},
        documentInfoReady = false,
        masterSyllabus = { description: '', reference_text: '', supporting_reference_text: '', prerequisite_text: '' },
""",
    "UI documentInfoReady prop",
)
s = replace_once(
    s,
    """                    meta={documentMeta}
                    master={masterSyllabus}
                    aiInstruction={aiInstruction}
""",
    """                    meta={documentMeta}
                    ready={documentInfoReady}
                    master={masterSyllabus}
                    aiInstruction={aiInstruction}
""",
    "Meta editor ready prop",
)
s = replace_once(
    s,
    "function DocumentMetaEditor({ rpsId, meta, master, aiInstruction }: any)",
    "function DocumentMetaEditor({ rpsId, meta, ready, master, aiInstruction }: any)",
    "Meta editor signature",
)
s = replace_once(
    s,
    'className="inline-flex items-center gap-2 rounded-xl border border-teal-700 bg-teal-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-teal-700"',
    "className={`inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold text-white shadow-sm transition ${ready ? 'border-teal-700 bg-teal-600 hover:bg-teal-700' : 'border-amber-600 bg-amber-600 hover:bg-amber-700 ring-2 ring-amber-200'}`} title={ready ? 'Informasi RPS sudah lengkap' : 'Wajib dilengkapi sebelum Scope CPL dan CPMK dapat diatur'}",
    "Meta button ready style",
)

# Scope CPL editor.
s = replace_once(
    s,
    """                                            officialCplIds={officialCplIds}
                                            additionalCplIds={additionalCplIds}
""",
    """                                            officialCplIds={officialCplIds}
                                            additionalCplIds={additionalCplIds}
                                            disabled={!documentInfoReady}
""",
    "Scope editor disabled prop",
)
s = replace_once(
    s,
    """    officialCplIds,
    additionalCplIds,
}: any) {
    return (
""",
    """    officialCplIds,
    additionalCplIds,
    disabled = false,
}: any) {
    if (disabled) {
        return (
            <span
                className="cursor-not-allowed text-[10px] font-bold text-slate-300"
                title="Lengkapi dan simpan Edit Informasi RPS terlebih dahulu."
            >
                Atur Scope CPL RPS
            </span>
        );
    }

    return (
""",
    "Scope editor disabled state",
)

# CPMK import/add editor.
s = replace_once(
    s,
    '<DocumentCpmkAdd rpsId={rps.id} />',
    '<DocumentCpmkAdd rpsId={rps.id} disabled={!documentInfoReady} />',
    "CPMK add disabled prop",
)
s = replace_once(
    s,
    "function DocumentCpmkAdd({ rpsId }: any)",
    "function DocumentCpmkAdd({ rpsId, disabled = false }: any)",
    "CPMK add signature",
)
func_start = s.find("function DocumentCpmkAdd(")
func_end = s.find("\nfunction ", func_start + 10)
if func_start < 0 or func_end < 0:
    raise SystemExit("Cannot isolate DocumentCpmkAdd")
region = s[func_start:func_end]
form_marker = """    const form = useForm({
        description: '',
        bloom_level: '',
    });

    if (!open) {
"""
form_new = """    const form = useForm({
        description: '',
        bloom_level: '',
    });

    if (disabled) {
        return (
            <div className="flex flex-wrap items-center gap-1.5">
                <button type="button" disabled className="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-300" title="Lengkapi Edit Informasi RPS terlebih dahulu.">
                    <RotateCcw className="size-3" /> Ambil CPMK Kurikulum
                </button>
                <button type="button" disabled className="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-300" title="Lengkapi Edit Informasi RPS terlebih dahulu.">
                    <Plus className="size-3" /> Tambah CPMK
                </button>
            </div>
        );
    }

    if (!open) {
"""
region = replace_once(region, form_marker, form_new, "CPMK disabled early return")
s = s[:func_start] + region + s[func_end:]

# Gate the three CPMK AI buttons.
for label in ["Telaah CPMK AI", "Pemetaan Bloom AI", "Pemetaan CPMK → CPL AI"]:
    idx = s.find(f'label="{label}"')
    if idx < 0:
        raise SystemExit(f"Missing AI label: {label}")
    disabled_idx = s.find("disabled={", idx, idx + 700)
    if disabled_idx < 0:
        raise SystemExit(f"Missing disabled condition for {label}")
    disabled_end = s.find("}", disabled_idx)
    current = s[disabled_idx:disabled_end + 1]
    if "documentInfoReady" not in current:
        inner = current[len("disabled={"):-1]
        replacement = f"disabled={{!documentInfoReady || {inner}}}"
        s = s[:disabled_idx] + replacement + s[disabled_end + 1:]

# Rename meeting button.
s = s.replace("1. Atur Pertemuan", "Atur Pertemuan")

# Global clear button: icon only, with explicit whole-week confirmation.
s = replace_once(
    s,
    "if (!confirm('Kosongkan isi akademik 14 pekan pembelajaran? Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;",
    "if (!confirm('Hapus isi seluruh 14 pekan pembelajaran? Isi akademik semua pekan akan dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.')) return;",
    "Global clear confirmation",
)
s = replace_once(
    s,
    "actionOptions('Isi pekanan dikosongkan. Tatap muka, belajar mandiri, tugas mandiri/terstruktur, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),",
    "actionOptions('Isi seluruh pekan berhasil dihapus. Data teknis, alokasi Sub-CPMK, bobot, UTS/UAS, Asesmen Detail, dan RTM tetap dipertahankan.'),",
    "Global clear notice",
)
s = replace_once(
    s,
    'className="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35"',
    'className="inline-flex size-8 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-35"',
    "Global clear icon class",
)
s = replace_once(
    s,
    "title={meetingPlanReady ? 'Kosongkan isi akademik tanpa mereset tatap muka, belajar mandiri, tugas mandiri/terstruktur, atau struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}",
    "title={meetingPlanReady ? 'Hapus isi seluruh pekan tanpa mereset data teknis atau struktur penilaian' : 'Selesaikan Atur Pertemuan terlebih dahulu.'}",
    "Global clear title",
)
s = replace_once(
    s,
    ">\n                                Kosongkan Isi\n                            </button>",
    ">\n                                <Trash2 className=\"size-3.5\" />\n                            </button>",
    "Global clear icon only",
)
p.write_text(s, encoding="utf-8")
