from pathlib import Path
import re

# 1) Deleting an assessment must delete RTMs that belong to it.
path = Path('app/Http/Controllers/RpsAssessmentController.php')
text = path.read_text()
old = """        $oldWeek = $row->week_number ? (int) $row->week_number : null;\n\n        DB::table('assessments')->where('id', $assessment)->delete();\n\n        $sync->syncVersion($version->id);\n\n        return back()->with('success', 'Asesmen dihapus dan distribusi bobot pekan serta RTM terkait disinkronkan ulang.');\n"""
new = """        $linkedTaskIds = DB::table('rps_tasks')\n            ->where('rps_version_id', $version->id)\n            ->where('assessment_id', $assessment)\n            ->pluck('id')\n            ->all();\n\n        DB::transaction(function () use ($assessment, $linkedTaskIds): void {\n            if ($linkedTaskIds !== []) {\n                DB::table('rps_task_subcpmks')\n                    ->whereIn('rps_task_id', $linkedTaskIds)\n                    ->delete();\n\n                DB::table('rps_tasks')\n                    ->whereIn('id', $linkedTaskIds)\n                    ->delete();\n            }\n\n            DB::table('assessments')->where('id', $assessment)->delete();\n        });\n\n        $sync->syncVersion($version->id);\n\n        $rtmCount = count($linkedTaskIds);\n        $rtmMessage = $rtmCount > 0\n            ? \" {$rtmCount} RTM terkait ikut dihapus.\"\n            : '';\n\n        return back()->with(\n            'success',\n            'Asesmen dihapus.' . $rtmMessage . ' Distribusi bobot dan validator disinkronkan ulang.'\n        );\n"""
if old not in text:
    raise SystemExit('RpsAssessmentController destroy marker not found')
path.write_text(text.replace(old, new, 1))

# 2) Never let stale auto-generated RTMs attach themselves to another assessment by guessing.
path = Path('app/Services/Rps/RpsAssessmentSyncService.php')
text = path.read_text()
text = text.replace(
    "->get(['id', 'assessment_id', 'title', 'type', 'due_week']);",
    "->get(['id', 'assessment_id', 'title', 'type', 'due_week', 'source_type']);",
    2,
)

marker = """                if ($assessmentId && $weekSubId && in_array($dueWeek, self::TEACHING_WEEKS, true)) {\n                    $currentLinks = collect($assessmentLinks->get($assessmentId, []))\n                        ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique();\n                    if (! $currentLinks->contains($weekSubId)) $assessmentId = null;\n                }\n\n                if (! $assessmentId) {\n                    $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);\n"""
replacement = """                if ($assessmentId && $weekSubId && in_array($dueWeek, self::TEACHING_WEEKS, true)) {\n                    $currentLinks = collect($assessmentLinks->get($assessmentId, []))\n                        ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->unique();\n                    if (! $currentLinks->contains($weekSubId)) $assessmentId = null;\n                }\n\n                $sourceType = strtolower((string) ($task->source_type ?? 'manual'));\n                $normalizedTaskTitle = $this->normalizeLabel((string) $task->title);\n\n                // RTM yang dibuat otomatis dari asesmen tidak boleh dipindahkan\n                // ke asesmen lain hanya karena asesmen asalnya sudah dihapus.\n                // Jika relasi aktif tidak lagi memiliki nama yang sama, cari hanya\n                // pasangan exact yang masih valid; bila tidak ada, RTM auto stale\n                // dibuang agar tidak menjadi bukti palsu pada validator.\n                if ($sourceType === 'assessment_sync' && $assessmentId) {\n                    $currentAssessment = $assessments->first(\n                        fn ($item) => (string) $item->id === $assessmentId\n                    );\n                    if (! $currentAssessment\n                        || $this->normalizeLabel((string) $currentAssessment->name) !== $normalizedTaskTitle\n                    ) {\n                        $assessmentId = null;\n                    }\n                }\n\n                if (! $assessmentId && $sourceType === 'assessment_sync') {\n                    $exactAuto = $assessments->first(function ($assessment) use ($normalizedTaskTitle, $weekSubId, $dueWeek, $assessmentLinks): bool {\n                        if ($this->normalizeLabel((string) $assessment->name) !== $normalizedTaskTitle) return false;\n                        $type = strtolower((string) $assessment->type);\n                        if ($dueWeek === 8) return $type === 'uts';\n                        if ($dueWeek === 16) return $type === 'uas';\n                        if (! $weekSubId || in_array($type, ['uts', 'uas'], true)) return false;\n                        return collect($assessmentLinks->get($assessment->id, []))\n                            ->pluck('rps_sub_cpmk_id')->map(fn ($id) => (string) $id)->contains($weekSubId);\n                    });\n\n                    if ($exactAuto) {\n                        $assessmentId = (string) $exactAuto->id;\n                        DB::table('rps_tasks')->where('id', $task->id)->update([\n                            'assessment_id' => $assessmentId,\n                            'updated_at' => now(),\n                        ]);\n                    } else {\n                        DB::table('rps_tasks')->where('id', $task->id)->delete();\n                        continue;\n                    }\n                }\n\n                if (! $assessmentId) {\n"""
if marker not in text:
    raise SystemExit('syncTaskMappings marker not found')
text = text.replace(marker, replacement, 1)

# Snapshot: ignore stale auto RTM immediately even on read-only page loads.
snapshot_marker = """        $taskIds = $tasks->pluck('id')->all();\n        $taskLinks = $taskIds === []\n"""
snapshot_replacement = """        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);\n        $tasks = $tasks->filter(function ($task) use ($assessmentById): bool {\n            if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                return true;\n            }\n\n            $assessmentId = filled($task->assessment_id ?? null)\n                ? (string) $task->assessment_id\n                : null;\n            if (! $assessmentId) return false;\n\n            $assessment = $assessmentById->get($assessmentId);\n            if (! $assessment) return false;\n\n            return $this->normalizeLabel((string) $assessment->name)\n                === $this->normalizeLabel((string) $task->title);\n        })->values();\n\n        $taskIds = $tasks->pluck('id')->all();\n        $taskLinks = $taskIds === []\n"""
if snapshot_marker not in text:
    raise SystemExit('snapshot task filter marker not found')
text = text.replace(snapshot_marker, snapshot_replacement, 1)
path.write_text(text)

# 3) Hide stale auto-generated RTMs from the page immediately, without writing on GET.
path = Path('app/Http/Controllers/RpsController.php')
text = path.read_text()
task_marker = """        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->map(function ($task) use ($weekSubByNumber): object {\n"""
task_replacement = """        $assessmentById = $assessments->keyBy(fn ($assessment) => (string) $assessment->id);\n        $normalizeAssessmentLabel = static function (string $value): string {\n            $value = mb_strtolower(trim($value));\n            $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;\n            return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);\n        };\n\n        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->filter(function ($task) use ($assessmentById, $normalizeAssessmentLabel): bool {\n                    if (strtolower((string) ($task->source_type ?? 'manual')) !== 'assessment_sync') {\n                        return true;\n                    }\n\n                    $assessmentId = filled($task->assessment_id ?? null)\n                        ? (string) $task->assessment_id\n                        : null;\n                    if (! $assessmentId) return false;\n\n                    $assessment = $assessmentById->get($assessmentId);\n                    if (! $assessment) return false;\n\n                    return $normalizeAssessmentLabel((string) $assessment->name)\n                        === $normalizeAssessmentLabel((string) $task->title);\n                })\n                ->values()\n                ->map(function ($task) use ($weekSubByNumber): object {\n"""
if task_marker not in text:
    raise SystemExit('RpsController task marker not found')
text = text.replace(task_marker, task_replacement, 1)
path.write_text(text)

# 4) Make assessment delete confirmation explicit that linked RTMs are removed too.
path = Path('resources/js/pages/rps/show.tsx')
text = path.read_text()
pattern = re.compile(r"if \(confirm\((['\"`])Hapus asesmen[^\n]*?\1\)\) \{")
if pattern.search(text):
    text = pattern.sub("if (confirm(`Hapus asesmen ${assessment.name}? RTM yang terhubung ke asesmen ini akan ikut dihapus.`)) {", text, count=1)
else:
    # Fallback: locate assessment delete route and replace a nearby generic confirm when present.
    idx = text.find('`/rps/${rpsId}/assessments/${assessment.id}`')
    if idx != -1:
        start = max(0, idx - 900)
        chunk = text[start:idx]
        chunk2 = re.sub(
            r"confirm\(([^\n]+)\)",
            "confirm(`Hapus asesmen ${assessment.name}? RTM yang terhubung ke asesmen ini akan ikut dihapus.`)",
            chunk,
            count=1,
        )
        text = text[:start] + chunk2 + text[idx:]
path.write_text(text)

print('Assessment delete / stale RTM patch applied')
