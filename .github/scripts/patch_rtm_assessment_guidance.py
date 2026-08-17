from pathlib import Path
import re

root = Path('.')

# -----------------------------------------------------------------------------
# Frontend: make RTM assessment choice explicit and synchronize defaults.
# -----------------------------------------------------------------------------
p = root / 'resources/js/pages/rps/show.tsx'
s = p.read_text(encoding='utf-8')

if "const RTM_ASSESSMENT_TYPES" not in s:
    marker = "const TEACHING_WEEKS = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];\n"
    if marker not in s:
        raise SystemExit('missing TEACHING_WEEKS marker')
    s = s.replace(
        marker,
        marker + "const RTM_ASSESSMENT_TYPES = ['assignment', 'project', 'practicum', 'presentation'];\n",
        1,
    )

# Both RTM create and RTM edit assessment selectors use form.data.assessment_id.
# Selecting an assessment now sets the canonical type, due week and Sub-CPMK tags.
select_pattern = re.compile(
    r"value=\{form\.data\.assessment_id\}\s*\n\s*onChange=\{\(e\) => \{.*?\n\s*\}\}\s*\n\s*className=",
    re.S,
)
select_replacement = '''value={form.data.assessment_id}
                        onChange={(e) => {
                            const assessmentId = e.target.value;
                            const selectedAssessment = assessments.find(
                                (item: any) => item.id === assessmentId,
                            );

                            if (!selectedAssessment) {
                                form.setData('assessment_id', '');
                                return;
                            }

                            form.setData({
                                ...form.data,
                                assessment_id: assessmentId,
                                type: RTM_ASSESSMENT_TYPES.includes(String(selectedAssessment.type))
                                    ? selectedAssessment.type
                                    : form.data.type,
                                due_week: selectedAssessment.week_number
                                    ? String(selectedAssessment.week_number)
                                    : form.data.due_week,
                                sub_cpmk_ids: safeList(selectedAssessment.sub_cpmk_ids).map(String),
                            });
                        }}
                        className='''
s, count = select_pattern.subn(select_replacement, s)
if count != 2:
    raise SystemExit(f'expected 2 RTM assessment selectors, patched {count}')

# Only show assessment types that can legitimately have an RTM, and make the
# scheduled week visible in the dropdown.
s = s.replace(
    "{assessments.map((assessment: any) => (\n                                <option key={assessment.id} value={assessment.id}>{assessment.code} | {assessment.name}</option>\n                            ))}",
    "{assessments\n                                .filter((assessment: any) => RTM_ASSESSMENT_TYPES.includes(String(assessment.type)))\n                                .map((assessment: any) => (\n                                    <option key={assessment.id} value={assessment.id}>\n                                        {assessment.code} | {assessment.name} | Pekan {assessment.week_number || '-'}\n                                    </option>\n                                ))}",
    1,
)
s = s.replace(
    "{assessments.map((assessment: any) => (\n                            <option key={assessment.id} value={assessment.id}>\n                                {assessment.code} | {assessment.name}\n                            </option>\n                        ))}",
    "{assessments\n                            .filter((assessment: any) => RTM_ASSESSMENT_TYPES.includes(String(assessment.type)))\n                            .map((assessment: any) => (\n                                <option key={assessment.id} value={assessment.id}>\n                                    {assessment.code} | {assessment.name} | Pekan {assessment.week_number || '-'}\n                                </option>\n                            ))}",
    1,
)

# RTM create: confirm when lecturer deliberately moves the due week away from
# the assessment schedule.
old_create = '''onClick={() => form.post(
                            `/rps/${rpsId}/tasks`,
                            actionOptions('RTM berhasil ditambahkan.', () => {
                                form.reset();
                                setOpen(false);
                            }),
                        )}'''
new_create = '''onClick={() => {
                            const selectedAssessment = assessments.find(
                                (item: any) => item.id === form.data.assessment_id,
                            );
                            const assessmentWeek = Number(selectedAssessment?.week_number || 0);
                            const dueWeek = Number(form.data.due_week || 0);

                            if (
                                assessmentWeek > 0
                                && dueWeek > 0
                                && assessmentWeek !== dueWeek
                                && !confirm(`Pekan Pengumpulan RTM (${dueWeek}) berbeda dari jadwal asesmen (${assessmentWeek}). Tetap simpan?`)
                            ) {
                                return;
                            }

                            form.post(
                                `/rps/${rpsId}/tasks`,
                                actionOptions('RTM berhasil ditambahkan.', () => {
                                    form.reset();
                                    setOpen(false);
                                }),
                            );
                        }}'''
if old_create not in s:
    raise SystemExit('missing RTM create submit marker')
s = s.replace(old_create, new_create, 1)

# RTM edit: same confirmation rule.
old_edit = '''event.preventDefault();
                form.put(
                    `/rps/${rpsId}/tasks/${task.id}`,
                    actionOptions('RTM berhasil diperbarui.', () => { setEditing(false); onDone?.(); }),
                );'''
new_edit = '''event.preventDefault();
                const selectedAssessment = assessments.find(
                    (item: any) => item.id === form.data.assessment_id,
                );
                const assessmentWeek = Number(selectedAssessment?.week_number || 0);
                const dueWeek = Number(form.data.due_week || 0);

                if (
                    assessmentWeek > 0
                    && dueWeek > 0
                    && assessmentWeek !== dueWeek
                    && !confirm(`Pekan Pengumpulan RTM (${dueWeek}) berbeda dari jadwal asesmen (${assessmentWeek}). Tetap simpan?`)
                ) {
                    return;
                }

                form.put(
                    `/rps/${rpsId}/tasks/${task.id}`,
                    actionOptions('RTM berhasil diperbarui.', () => { setEditing(false); onDone?.(); }),
                );'''
if old_edit not in s:
    raise SystemExit('missing RTM edit submit marker')
s = s.replace(old_edit, new_edit, 1)

p.write_text(s, encoding='utf-8')

# -----------------------------------------------------------------------------
# Backend: selected assessment is authoritative for RTM type and Sub-CPMK tags.
# Due week defaults to the assessment week only when omitted; an explicit
# lecturer override remains allowed after the frontend confirmation.
# -----------------------------------------------------------------------------
p = root / 'app/Http/Controllers/RpsTaskController.php'
s = p.read_text(encoding='utf-8')

# Replace store assessment existence check with canonicalization.
old_store = '''        if ($validated['assessment_id'] ?? null) {
            $assessmentOk = DB::table('assessments')
                ->where('id', $validated['assessment_id'])
                ->where('rps_version_id', $version->id)
                ->exists();

            abort_unless($assessmentOk, 422);
        }
'''
new_store = '''        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }
'''
if old_store not in s:
    raise SystemExit('missing store assessment validation block')
s = s.replace(old_store, new_store, 1)

# Replace update assessment existence check with canonicalization.
old_update = '''        if ($validated['assessment_id'] ?? null) {
            $assessmentOk = DB::table('assessments')
                ->where('id', $validated['assessment_id'])
                ->where('rps_version_id', $version->id)
                ->exists();

            if (! $assessmentOk) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages([
                    'assessment_id' => 'Asesmen RTM tidak valid untuk RPS ini.',
                ]);
            }
        }
'''
new_update = '''        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }
'''
if old_update not in s:
    raise SystemExit('missing update assessment validation block')
s = s.replace(old_update, new_update, 1)

# Add one helper before context().
helper_marker = '''    private function context(Request $request, string $rps): array
'''
helper = '''    private function applyAssessmentDefaults(array $validated, string $versionId): array
    {
        $assessment = DB::table('assessments')
            ->where('id', $validated['assessment_id'])
            ->where('rps_version_id', $versionId)
            ->first(['id', 'name', 'type', 'week_number']);

        if (! $assessment) {
            throw \\Illuminate\\Validation\\ValidationException::withMessages([
                'assessment_id' => 'Asesmen RTM tidak valid untuk RPS ini.',
            ]);
        }

        $type = strtolower((string) $assessment->type);
        $rtmTypes = ['assignment', 'project', 'practicum', 'presentation'];

        if (! in_array($type, $rtmTypes, true)) {
            throw \\Illuminate\\Validation\\ValidationException::withMessages([
                'assessment_id' => 'Pilih asesmen tugas, proyek, praktikum, atau presentasi untuk RTM.',
            ]);
        }

        $validated['type'] = $type;
        $validated['sub_cpmk_ids'] = DB::table('assessment_subcpmks')
            ->where('assessment_id', $assessment->id)
            ->pluck('rps_sub_cpmk_id')
            ->map('strval')
            ->unique()
            ->values()
            ->all();

        if (empty($validated['due_week']) && filled($assessment->week_number)) {
            $validated['due_week'] = (int) $assessment->week_number;
        }

        return $validated;
    }

'''
if helper_marker not in s:
    raise SystemExit('missing context helper marker')
s = s.replace(helper_marker, helper + helper_marker, 1)

p.write_text(s, encoding='utf-8')

print('RTM assessment guidance patch applied')
