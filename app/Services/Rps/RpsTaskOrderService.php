<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;

class RpsTaskOrderService
{
    /**
     * Keep the existing code-based UI order aligned with the academic schedule.
     * RTM codes are therefore presentation sequence numbers, not immutable IDs.
     */
    public function renumber(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('CASE WHEN due_week IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_week')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'code']);

        $changed = 0;

        DB::transaction(function () use ($tasks, &$changed): void {
            foreach ($tasks as $index => $task) {
                $code = 'RTM-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

                if ((string) $task->code === $code) {
                    continue;
                }

                DB::table('rps_tasks')
                    ->where('id', $task->id)
                    ->update([
                        'code' => $code,
                        'updated_at' => now(),
                    ]);

                $changed++;
            }
        });

        return $changed;
    }
}
