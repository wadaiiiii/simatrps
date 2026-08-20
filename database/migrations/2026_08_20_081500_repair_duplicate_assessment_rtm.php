<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rps_tasks', 'assessments', 'assessment_subcpmks', 'rps_task_subcpmks'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;

            return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
        };

        $manualTasks = DB::table('rps_tasks')
            ->whereNull('assessment_id')
            ->where('source_type', 'manual')
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->get(['id', 'rps_version_id', 'title', 'type']);

        foreach ($manualTasks as $task) {
            $title = $normalize((string) $task->title);
            if ($title === '') {
                continue;
            }

            $candidates = DB::table('assessments')
                ->where('rps_version_id', $task->rps_version_id)
                ->where('type', $task->type)
                ->get(['id', 'name'])
                ->filter(fn ($assessment) => $normalize((string) $assessment->name) === $title)
                ->values();

            if ($candidates->count() !== 1) {
                continue;
            }

            $assessment = $candidates->first();
            $taskSubIds = DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $task->id)
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id);
            $assessmentSubIds = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id);

            if ($taskSubIds->isNotEmpty() && $taskSubIds->diff($assessmentSubIds)->isNotEmpty()) {
                continue;
            }

            DB::transaction(function () use ($task, $assessment): void {
                DB::table('rps_tasks')->where('id', $task->id)->update([
                    'assessment_id' => $assessment->id,
                    'updated_at' => now(),
                ]);

                $generatedIds = DB::table('rps_tasks')
                    ->where('rps_version_id', $task->rps_version_id)
                    ->where('assessment_id', $assessment->id)
                    ->where('source_type', 'assessment_sync')
                    ->where('id', '!=', $task->id)
                    ->pluck('id')
                    ->all();

                if ($generatedIds === []) {
                    return;
                }

                DB::table('rps_task_subcpmks')->whereIn('rps_task_id', $generatedIds)->delete();
                DB::table('rps_tasks')->whereIn('id', $generatedIds)->delete();
            });
        }
    }

    public function down(): void
    {
        // Data repair intentionally keeps the lecturer-authored RTM content.
    }
};
