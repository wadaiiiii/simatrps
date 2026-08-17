from pathlib import Path

p = Path('app/Http/Controllers/RpsController.php')
s = p.read_text()

old = """        $tasks = Schema::hasTable('rps_tasks')
            ? DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->orderBy('code')
                ->get()
                ->map(function ($task) use ($weekSubByNumber): object {
                    $dueWeek = (int) ($task->due_week ?? 0);
                    $weekSubId = filled($weekSubByNumber->get($dueWeek))
                        ? (string) $weekSubByNumber->get($dueWeek)
                        : null;

                    // RTM pada pekan pembelajaran adalah bukti spesifik pekan.
                    // Untuk tampilan/PDF gunakan Sub-CPMK pekan sebagai sumber
                    // efektif tanpa menulis database saat halaman dibuka.
                    if (in_array($dueWeek, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true) && $weekSubId) {
                        $task->sub_cpmk_ids = [$weekSubId];
                    } else {
                        $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')
                            ->where('rps_task_id', $task->id)
                            ->pluck('rps_sub_cpmk_id')
                            ->all();
                    }

                    return $task;
                })
            : collect();
"""

new = """        $assessmentByIdForTasks = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);

        $tasks = Schema::hasTable('rps_tasks')
            ? DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->orderBy('code')
                ->get()
                ->filter(function ($task) use ($assessmentByIdForTasks, $weekSubByNumber): bool {
                    $source = strtolower(trim((string) ($task->source_type ?? '')));
                    $purpose = trim((string) ($task->purpose ?? ''));
                    $expectedOutput = trim((string) ($task->expected_output ?? ''));
                    $generated = in_array($source, ['assessment_sync', 'smart_draft', 'automation', 'ai_generated'], true)
                        || (
                            str_starts_with($purpose, 'Mengukur ketercapaian Sub-CPMK melalui ')
                            && str_starts_with($expectedOutput, 'Luaran ')
                        );

                    // RTM manual dosen selalu dipertahankan. Filter ketat hanya
                    // diterapkan pada RTM hasil otomatis/legacy generator.
                    if (! $generated) return true;

                    $assessmentId = filled($task->assessment_id ?? null)
                        ? (string) $task->assessment_id
                        : null;
                    if (! $assessmentId) return false;

                    $assessment = $assessmentByIdForTasks->get($assessmentId);
                    if (! $assessment) return false;

                    $normalize = static function (mixed $value): string {
                        $text = mb_strtolower(trim((string) $value));
                        $text = preg_replace('/[^\\pL\\pN]+/u', ' ', $text) ?? $text;
                        return trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);
                    };

                    if ($normalize($task->title ?? '') !== $normalize($assessment->name ?? '')) {
                        return false;
                    }

                    $dueWeek = (int) ($task->due_week ?? 0);
                    if (in_array($dueWeek, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true)) {
                        $weekSubId = filled($weekSubByNumber->get($dueWeek))
                            ? (string) $weekSubByNumber->get($dueWeek)
                            : null;
                        $assessmentSubIds = collect($assessment->sub_cpmk_ids ?? [])
                            ->map(fn ($id) => (string) $id);

                        if (! $weekSubId || ! $assessmentSubIds->contains($weekSubId)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->map(function ($task) use ($weekSubByNumber): object {
                    $dueWeek = (int) ($task->due_week ?? 0);
                    $weekSubId = filled($weekSubByNumber->get($dueWeek))
                        ? (string) $weekSubByNumber->get($dueWeek)
                        : null;

                    // RTM pada pekan pembelajaran adalah bukti spesifik pekan.
                    // Untuk tampilan/PDF gunakan Sub-CPMK pekan sebagai sumber
                    // efektif tanpa menulis database saat halaman dibuka.
                    if (in_array($dueWeek, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true) && $weekSubId) {
                        $task->sub_cpmk_ids = [$weekSubId];
                    } else {
                        $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')
                            ->where('rps_task_id', $task->id)
                            ->pluck('rps_sub_cpmk_id')
                            ->all();
                    }

                    return $task;
                })
                ->values()
            : collect();
"""

if old not in s:
    raise SystemExit('tasks block marker not found')
s = s.replace(old, new, 1)

marker = """            $week->assessment_weight_manual = $isTeachingWeek
                && $weightOverrides->has($weekNumber);

            return $week;
"""
replacement = """            $week->assessment_weight_manual = $isTeachingWeek
                && $weightOverrides->has($weekNumber);

            // Data lama dapat masih menyebut kode Sub-CPMK sebelumnya pada
            // narasi pekan walaupun relasi pekan sudah dipindahkan. Untuk
            // tampilan/PDF, selaraskan hanya referensi kode mekanisnya; isi
            // akademik lain tetap utuh dan data manual tidak ditulis ulang.
            if ($isTeachingWeek && $sub?->code) {
                $pattern = '/Sub[\\s\\-‐‑‒–—]*CPMK[\\s\\-‐‑‒–—]*\\d+/iu';
                foreach (['assessment_criteria', 'learning_activity', 'student_assignment', 'online_activity'] as $field) {
                    $value = trim((string) ($week->{$field} ?? ''));
                    if ($value === '') continue;
                    preg_match_all($pattern, $value, $matches);
                    $found = collect($matches[0] ?? [])
                        ->map(fn ($code) => mb_strtolower(preg_replace('/[^\\pL\\pN]+/u', '', (string) $code) ?? (string) $code))
                        ->filter()->unique()->values();
                    if ($found->count() !== 1) continue;
                    $target = mb_strtolower(preg_replace('/[^\\pL\\pN]+/u', '', (string) $sub->code) ?? (string) $sub->code);
                    if ($found->first() === $target) continue;
                    $week->{$field} = preg_replace($pattern, (string) $sub->code, $value) ?? $value;
                }
            }

            return $week;
"""
if marker not in s:
    raise SystemExit('week map marker not found')
s = s.replace(marker, replacement, 1)

p.write_text(s)
# trigger
