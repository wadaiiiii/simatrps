from pathlib import Path

# Patch RpsController: use canonical snapshot weights for display/PDF and
# derive teaching-week RTM tags from the Sub-CPMK assigned to that week.
p = Path('app/Http/Controllers/RpsController.php')
s = p.read_text()

old = """        $assessmentSyncSnapshot = $assessmentSync->snapshot($version->id);\n        $assessmentNamesByWeek = collect(\n            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []\n        );\n"""
new = """        $assessmentSyncSnapshot = $assessmentSync->snapshot($version->id);\n        $expectedWeeklyWeights = collect(\n            $assessmentSyncSnapshot['expected_weekly_weights'] ?? []\n        );\n        $assessmentNamesByWeek = collect(\n            $assessmentSyncSnapshot['assessment_names_by_week'] ?? []\n        );\n"""
assert old in s, 'snapshot marker not found in RpsController'
s = s.replace(old, new, 1)

old = """            $assessmentWeightsByWeek,\n            $assessmentNamesByWeek,\n            $assessmentOwnerByWeek,\n"""
new = """            $assessmentWeightsByWeek,\n            $expectedWeeklyWeights,\n            $assessmentNamesByWeek,\n            $assessmentOwnerByWeek,\n"""
assert old in s, 'closure use marker not found'
s = s.replace(old, new, 1)

old = """            $week->assessment_weight = in_array($weekNumber, [8, 16], true)\n                && $assessmentWeightsByWeek->has($weekNumber)\n                    ? (float) $assessmentWeightsByWeek->get($weekNumber, 0)\n                    : (float) ($storedWeight ?? 0);\n"""
new = """            $week->assessment_weight = in_array($weekNumber, [8, 16], true)\n                && $assessmentWeightsByWeek->has($weekNumber)\n                    ? (float) $assessmentWeightsByWeek->get($weekNumber, 0)\n                    : ($expectedWeeklyWeights->has($weekNumber)\n                        ? (float) $expectedWeeklyWeights->get($weekNumber, 0)\n                        : (float) ($storedWeight ?? 0));\n"""
assert old in s, 'weight assignment marker not found'
s = s.replace(old, new, 1)

old = """        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->map(function ($task): object {\n                    $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')\n                        ->where('rps_task_id', $task->id)\n                        ->pluck('rps_sub_cpmk_id')\n                        ->all();\n\n                    return $task;\n                })\n            : collect();\n"""
new = """        $weekSubByNumber = $weeks\n            ->pluck('rps_sub_cpmk_id', 'week_number');\n\n        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')\n                ->get()\n                ->map(function ($task) use ($weekSubByNumber): object {\n                    $dueWeek = (int) ($task->due_week ?? 0);\n                    $weekSubId = filled($weekSubByNumber->get($dueWeek))\n                        ? (string) $weekSubByNumber->get($dueWeek)\n                        : null;\n\n                    // RTM pada pekan pembelajaran adalah bukti spesifik pekan.\n                    // Untuk tampilan/PDF gunakan Sub-CPMK pekan sebagai sumber\n                    // efektif tanpa menulis database saat halaman dibuka.\n                    if (in_array($dueWeek, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true) && $weekSubId) {\n                        $task->sub_cpmk_ids = [$weekSubId];\n                    } else {\n                        $task->sub_cpmk_ids = DB::table('rps_task_subcpmks')\n                            ->where('rps_task_id', $task->id)\n                            ->pluck('rps_sub_cpmk_id')\n                            ->all();\n                    }\n\n                    return $task;\n                })\n            : collect();\n"""
assert old in s, 'task mapping block not found'
s = s.replace(old, new, 1)
p.write_text(s)

# Patch validator: calculate effective weekly totals from canonical snapshot,
# so stale persisted weights do not make the UI/PDF contradict the new rules.
p = Path('app/Services/Rps/ObeWorkspaceService.php')
s = p.read_text()
old = """        $assessmentSync = app(RpsAssessmentSyncService::class);\n        $taskAlignment = $assessmentSync->taskAlignment($versionId);\n        $assessmentSnapshot = $assessmentSync->snapshot($versionId);\n        $tasks = (int) $taskAlignment['task_total'];\n"""
new = """        $assessmentSync = app(RpsAssessmentSyncService::class);\n        $taskAlignment = $assessmentSync->taskAlignment($versionId);\n        $assessmentSnapshot = $assessmentSync->snapshot($versionId);\n\n        // Gunakan bobot efektif hasil snapshot sebagai sumber kebenaran untuk\n        // tampilan/validator. Database lama boleh belum tersinkron sampai aksi\n        // tulis berikutnya, tetapi pengguna tidak lagi melihat dua versi bobot.\n        $expectedWeeklyWeights = collect($assessmentSnapshot['expected_weekly_weights'] ?? []);\n        $effectiveWeeks = $weeks->map(function ($week) use ($expectedWeeklyWeights) {\n            $copy = clone $week;\n            $number = (int) $copy->week_number;\n            if ($expectedWeeklyWeights->has($number)) {\n                $copy->assessment_weight = (float) $expectedWeeklyWeights->get($number, 0);\n            }\n            return $copy;\n        });\n        $weightTotal = round((float) $effectiveWeeks->sum(\n            fn ($week) => (float) ($week->assessment_weight ?? 0)\n        ), 2);\n        $teachingWeeks = $effectiveWeeks->filter(\n            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)\n        );\n        $weightedTeachingWeeks = $teachingWeeks->filter(\n            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0\n        );\n        $teachingWeightTotal = round((float) $teachingWeeks->sum(\n            fn ($week) => (float) ($week->assessment_weight ?? 0)\n        ), 2);\n        $weightedWeeklySubCount = $weightedTeachingWeeks\n            ->pluck('rps_sub_cpmk_id')->filter()->unique()->count();\n        $weeklySubBudgets = $teachingWeeks\n            ->filter(fn ($week) => filled($week->rps_sub_cpmk_id ?? null))\n            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)\n            ->map(fn ($items) => round((float) $items->sum(\n                fn ($week) => (float) ($week->assessment_weight ?? 0)\n            ), 2));\n        $subBudgetAligned = $subCpmkIds->isNotEmpty()\n            && $subCpmkIds->every(function ($subId) use ($weeklySubBudgets, $aggregateSubBudgets): bool {\n                $weekly = (float) $weeklySubBudgets->get((string) $subId, 0);\n                $aggregate = (float) $aggregateSubBudgets->get((string) $subId, 0);\n                return $aggregate > 0 && abs($weekly - $aggregate) < 0.011;\n            });\n\n        $tasks = (int) $taskAlignment['task_total'];\n"""
assert old in s, 'validator snapshot block not found'
s = s.replace(old, new, 1)
p.write_text(s)

# Patch taskAlignment: for teaching weeks, semantic alignment is the due-week
# Sub-CPMK. Old pivot rows are an implementation detail and will be persisted on
# the next explicit write/sync action.
p = Path('app/Services/Rps/RpsAssessmentSyncService.php')
s = p.read_text()
old = """            sort($expected);\n            if ($expected !== $actual) $mismatchCount++;\n"""
new = """            sort($expected);\n            if (in_array($dueWeek, self::TEACHING_WEEKS, true)) {\n                // Tampilan efektif RTM selalu mengikuti Sub-CPMK pekan. Pivot\n                // lama akan dirapikan pada aksi sinkronisasi berikutnya, jadi\n                // jangan menandai inkonsisten hanya karena data legacy itu.\n                if ($expected === []) $mismatchCount++;\n            } elseif ($expected !== $actual) {\n                $mismatchCount++;\n            }\n"""
assert old in s, 'taskAlignment compare marker not found'
s = s.replace(old, new, 1)
p.write_text(s)
