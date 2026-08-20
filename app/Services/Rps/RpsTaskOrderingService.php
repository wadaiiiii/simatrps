<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;

class RpsTaskOrderingService
{
    public function renumberBySchedule(string $versionId): int
    {
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'due_week', 'created_at'])
            ->sort(function ($a, $b): int {
                $aWeek = filled($a->due_week ?? null) ? (int) $a->due_week : PHP_INT_MAX;
                $bWeek = filled($b->due_week ?? null) ? (int) $b->due_week : PHP_INT_MAX;

                if ($aWeek !== $bWeek) {
                    return $aWeek <=> $bWeek;
                }

                $createdComparison = strcmp(
                    (string) ($a->created_at ?? ''),
                    (string) ($b->created_at ?? '')
                );

                if ($createdComparison !== 0) {
                    return $createdComparison;
                }

                $aNumber = $this->codeNumber((string) ($a->code ?? ''));
                $bNumber = $this->codeNumber((string) ($b->code ?? ''));

                if ($aNumber !== $bNumber) {
                    return $aNumber <=> $bNumber;
                }

                return strcmp((string) $a->id, (string) $b->id);
            })
            ->values();

        if ($tasks->isEmpty()) {
            return 0;
        }

        $changes = $tasks->filter(function ($task, int $index): bool {
            return (string) $task->code !== $this->codeForPosition($index + 1);
        })->count();

        if ($changes === 0) {
            return 0;
        }

        DB::transaction(function () use ($tasks, $versionId): void {
            // Gunakan kode sementara agar unique(rps_version_id, code) tidak
            // bentrok ketika RTM-04 harus berpindah menjadi RTM-02, sementara
            // RTM-02 lama masih ada pada versi RPS yang sama.
            foreach ($tasks as $task) {
                DB::table('rps_tasks')
                    ->where('id', $task->id)
                    ->where('rps_version_id', $versionId)
                    ->update([
                        'code' => '__RTM_TMP_'.str_replace('-', '', (string) $task->id),
                    ]);
            }

            foreach ($tasks as $index => $task) {
                DB::table('rps_tasks')
                    ->where('id', $task->id)
                    ->where('rps_version_id', $versionId)
                    ->update([
                        'code' => $this->codeForPosition($index + 1),
                    ]);
            }
        });

        return $changes;
    }

    private function codeForPosition(int $position): string
    {
        return 'RTM-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }

    private function codeNumber(string $code): int
    {
        return preg_match('/RTM-(\d+)/i', $code, $match) === 1
            ? (int) $match[1]
            : PHP_INT_MAX;
    }
}
