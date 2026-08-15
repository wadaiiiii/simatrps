<?php

namespace App\Http\Controllers;

use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RpsAiController extends Controller
{
    public function generate(
        Request $request,
        string $rps,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'suggestion_type' => ['required', Rule::in([
                'cpmk_review',
                'sub_cpmk',
                'weekly_plan',
                'assessment_plan',
            ])],
            'instruction' => ['nullable', 'string', 'max:3000'],
        ]);

        $context = $contextService->build($record, $version, $data['suggestion_type']);
        $result = $aiProvider->generate(
            $data['suggestion_type'],
            $context,
            $data['instruction'] ?? null
        );

        DB::table('ai_suggestions')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'suggestion_type' => $data['suggestion_type'],
            'status' => 'pending',
            'input_context' => json_encode([
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'],
                'fallback_used' => (bool) ($result['fallback_used'] ?? false),
                'primary_error' => $result['primary_error'] ?? null,
                'response_id' => $result['response_id'],
                'usage' => $result['usage'],
                'instruction' => $data['instruction'] ?? null,
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'suggestion_payload' => json_encode(
                $result['payload'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'requested_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            'Rekomendasi AI berhasil dibuat. Review hasilnya sebelum diterapkan.'
        );
    }

    public function apply(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $row = $this->suggestion($version->id, $suggestion);

        if ($row->status !== 'pending') {
            throw ValidationException::withMessages([
                'ai' => 'Rekomendasi AI ini sudah pernah diputuskan.',
            ]);
        }

        $data = $request->validate([
            'selected_indexes' => ['nullable', 'array', 'max:100'],
            'selected_indexes.*' => ['integer', 'min:0', 'max:99'],
        ]);

        $payload = json_decode($row->suggestion_payload, true, 512, JSON_THROW_ON_ERROR);
        $effectivePayload = $payload;

        if (in_array($row->suggestion_type, ['cpmk_review', 'sub_cpmk'], true)) {
            $key = $row->suggestion_type === 'cpmk_review' ? 'recommendations' : 'items';
            $allItems = is_array($payload[$key] ?? null) ? array_values($payload[$key]) : [];
            $selectedIndexes = array_values(array_unique($data['selected_indexes'] ?? []));

            if ($selectedIndexes === []) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilih minimal satu usulan AI yang ingin diterapkan.',
                ]);
            }

            $selectedItems = [];
            foreach ($selectedIndexes as $index) {
                if (array_key_exists($index, $allItems)) {
                    $selectedItems[] = $allItems[$index];
                }
            }

            if ($selectedItems === []) {
                throw ValidationException::withMessages([
                    'ai' => 'Usulan yang dipilih tidak ditemukan. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            $effectivePayload[$key] = $selectedItems;
        }

        $applyResult = [];

        DB::transaction(function () use (
            &$applyResult,
            $row,
            $effectivePayload,
            $record,
            $version,
            $request
        ): void {
            $applyResult = match ($row->suggestion_type) {
                'cpmk_review' => $this->applyCpmkReview($effectivePayload, $record, $version, $request->user()->id),
                'sub_cpmk' => $this->applySubCpmk($effectivePayload, $version, $request->user()->id),
                'weekly_plan' => $this->applyWeeklyPlan($effectivePayload, $version),
                'assessment_plan' => $this->applyAssessmentPlan($effectivePayload, $version, $request->user()->id),
                default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
            };

            DB::table('ai_suggestions')->where('id', $row->id)->update([
                'status' => 'accepted',
                'accepted_payload' => json_encode(
                    $effectivePayload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with(
            'success',
            $this->applySuccessMessage($row->suggestion_type, $applyResult)
        );
    }

    public function reject(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        $row = $this->suggestion($version->id, $suggestion);

        DB::table('ai_suggestions')->where('id', $row->id)->update([
            'status' => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Rekomendasi AI ditolak. Data RPS tidak diubah.');
    }

    private function applyCpmkReview(array $payload, object $rps, object $version, int $userId): array
    {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')->where('course_id', $rps->course_id)->pluck('cpl_id')->all(),
                ...DB::table('rps_additional_cpls')->where('rps_version_id', $version->id)->pluck('cpl_id')->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy(fn ($cpl) => strtoupper(trim((string) $cpl->code)));

        $stats = ['added' => 0, 'adapted' => 0, 'kept' => 0, 'skipped' => 0];
        $items = is_array($payload['recommendations'] ?? null) ? $payload['recommendations'] : [];

        foreach ($items as $item) {
            $action = strtolower(trim((string) ($item['action'] ?? 'keep')));

            if ($action === 'keep') {
                $stats['kept']++;
                continue;
            }

            if ($action === 'adapt') {
                $targetCode = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));

                $cpmk = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $targetCode)
                    ->first();

                if (! $cpmk) {
                    $stats['skipped']++;
                    continue;
                }

                DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                    'description' => trim((string) ($item['description'] ?? $cpmk->description)),
                    'bloom_level' => filled($item['bloom_level'] ?? null)
                        ? $item['bloom_level']
                        : $cpmk->bloom_level,
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings(
                    $cpmk->id,
                    is_array($item['cpl_codes'] ?? null) ? $item['cpl_codes'] : [],
                    $scopeCpls
                );

                $stats['adapted']++;
                continue;
            }

            if ($action === 'add') {
                $description = trim((string) ($item['description'] ?? ''));

                if ($description === '') {
                    $stats['skipped']++;
                    continue;
                }

                $duplicate = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                    ->exists();

                if ($duplicate) {
                    $stats['skipped']++;
                    continue;
                }

                $code = $this->nextCpmkCode($version->id);
                $sequence = ((int) DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->max('sequence_no')) + 1;
                $id = (string) Str::uuid();

                DB::table('rps_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    'description' => $description,
                    'bloom_level' => filled($item['bloom_level'] ?? null) ? $item['bloom_level'] : null,
                    'source_type' => 'ai_added',
                    'source_cpmk_id' => null,
                    'sequence_no' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings(
                    $id,
                    is_array($item['cpl_codes'] ?? null) ? $item['cpl_codes'] : [],
                    $scopeCpls
                );

                $stats['added']++;
                continue;
            }

            $stats['skipped']++;
        }

        if (
            $stats['added'] === 0
            && $stats['adapted'] === 0
            && $stats['kept'] === 0
            && $stats['skipped'] > 0
        ) {
            throw ValidationException::withMessages([
                'ai' => 'Usulan CPMK terpilih tidak dapat diterapkan karena target CPMK tidak cocok dengan data RPS. Buat rekomendasi AI baru.',
            ]);
        }

        return $stats;
    }

    private function replaceCpmkMappings(string $cpmkId, array $codes, $scopeCpls): void
    {
        $ids = collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->unique()
            ->map(fn ($code) => $scopeCpls->get($code)?->id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('rps_cpmk_cpls')->where('rps_cpmk_id', $cpmkId)->delete();

        foreach ($ids as $cplId) {
            DB::table('rps_cpmk_cpls')->insert([
                'id' => (string) Str::uuid(),
                'rps_cpmk_id' => $cpmkId,
                'cpl_id' => $cplId,
                'source_type' => 'ai_accepted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function applySubCpmk(array $payload, object $version, int $userId): array
    {
        $stats = ['added' => 0, 'adapted' => 0, 'kept' => 0, 'skipped' => 0];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        foreach ($items as $item) {
            $action = strtolower(trim((string) ($item['action'] ?? 'add')));

            if ($action === 'keep') {
                $stats['kept']++;
                continue;
            }

            $parentCode = $this->normalizeCpmkCode((string) ($item['parent_cpmk_code'] ?? ''));
            $parent = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $parentCode)
                ->first();

            if (! $parent) {
                $stats['skipped']++;
                continue;
            }

            if ($action === 'adapt') {
                $targetCode = $this->normalizeSubCpmkCode((string) ($item['target_code'] ?? ''));

                $sub = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $targetCode)
                    ->first();

                if (! $sub) {
                    $stats['skipped']++;
                    continue;
                }

                DB::table('rps_sub_cpmks')->where('id', $sub->id)->update([
                    'description' => trim((string) ($item['description'] ?? $sub->description)),
                    'bloom_level' => filled($item['bloom_level'] ?? null)
                        ? $item['bloom_level']
                        : $sub->bloom_level,
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                DB::table('rps_cpmk_subcpmks')
                    ->where('rps_sub_cpmk_id', $sub->id)
                    ->delete();

                DB::table('rps_cpmk_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_cpmk_id' => $parent->id,
                    'rps_sub_cpmk_id' => $sub->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stats['adapted']++;
                continue;
            }

            if ($action !== 'add') {
                $stats['skipped']++;
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '') {
                $stats['skipped']++;
                continue;
            }

            $duplicate = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                ->exists();

            if ($duplicate) {
                $stats['skipped']++;
                continue;
            }

            $id = (string) Str::uuid();
            $sequence = ((int) DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->max('sequence_no')) + 1;

            DB::table('rps_sub_cpmks')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'code' => $this->nextSubCpmkCode($version->id),
                'description' => $description,
                'bloom_level' => filled($item['bloom_level'] ?? null) ? $item['bloom_level'] : null,
                'source_type' => 'ai_added',
                'sequence_no' => $sequence,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rps_cpmk_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_cpmk_id' => $parent->id,
                'rps_sub_cpmk_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stats['added']++;
        }

        if (
            $stats['added'] === 0
            && $stats['adapted'] === 0
            && $stats['kept'] === 0
            && $stats['skipped'] > 0
        ) {
            throw ValidationException::withMessages([
                'ai' => 'Usulan Sub-CPMK terpilih tidak dapat diterapkan karena CPMK induk/target tidak cocok dengan RPS. Buat rekomendasi AI baru.',
            ]);
        }

        return $stats;
    }

    private function applyWeeklyPlan(array $payload, object $version): array
    {
        $expectedWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $actualWeeks = collect($payload['weeks'] ?? [])
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($actualWeeks !== $expectedWeeks) {
            throw ValidationException::withMessages([
                'ai' => 'Rencana mingguan AI tidak lengkap. Harus tepat 14 minggu pembelajaran (1-7 dan 9-15). Buat rekomendasi baru.',
            ]);
        }

        foreach (($payload['weeks'] ?? []) as $item) {
            $weekNumber = (int) ($item['week_number'] ?? 0);

            if (! in_array($weekNumber, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true)) {
                continue;
            }

            $week = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $weekNumber)
                ->first();

            if (! $week) {
                continue;
            }

            $subId = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $item['sub_cpmk_code'])
                ->value('id');

            $updates = ['updated_at' => now()];

            $candidate = [
                'rps_sub_cpmk_id' => $subId,
                'material_text' => $item['material'] ?? null,
                'learning_form' => $item['learning_form'] ?? null,
                'learning_method' => $item['learning_method'] ?? null,
                'time_estimate' => $item['time_estimate'] ?? null,
                'student_assignment' => $item['student_assignment'] ?? null,
                'online_activity' => $item['online_activity'] ?? null,
                'learning_activity' => $item['learning_activity'] ?? ($item['student_assignment'] ?? null),
                'assessment_indicator' => $item['assessment_indicator'] ?? null,
                'assessment_criteria' => $item['assessment_criteria'] ?? null,
                'assessment_method' => $item['assessment_method'] ?? null,
                'reference_text' => $item['references'] ?? null,
                'source_type' => 'ai_accepted',
            ];

            foreach ($candidate as $key => $value) {
                if ($key === 'source_type') {
                    continue;
                }

                $currentValue = $week->{$key} ?? null;
                $formatField = in_array($key, [
                    'learning_form',
                    'learning_method',
                    'time_estimate',
                    'student_assignment',
                    'online_activity',
                ], true);

                $canRefreshGenerated = $formatField
                    && ($week->source_type ?? null) !== 'manual';

                if (filled($value) && (! filled($currentValue) || $canRefreshGenerated)) {
                    $updates[$key] = $value;
                }
            }

            if (count($updates) > 1) {
                $updates['source_type'] = 'ai_accepted';
                DB::table('rps_weekly_plans')->where('id', $week->id)->update($updates);
            }
        }

        return ['weeks' => 14];
    }

    private function applyAssessmentPlan(array $payload, object $version, int $userId): array
    {
        $assessments = $payload['assessments'] ?? [];
        $total = round(collect($assessments)->sum(fn ($a) => (float) ($a['weight'] ?? 0)), 2);

        if (abs($total - 100.0) >= 0.01) {
            throw ValidationException::withMessages([
                'ai' => "Rekomendasi asesmen AI tidak dapat diterapkan karena total bobot {$total}%, bukan 100%.",
            ]);
        }

        $taskIds = DB::table('rps_tasks')->where('rps_version_id', $version->id)->pluck('id');
        if ($taskIds->isNotEmpty()) {
            DB::table('rps_task_subcpmks')->whereIn('rps_task_id', $taskIds)->delete();
        }

        $assessmentIds = DB::table('assessments')->where('rps_version_id', $version->id)->pluck('id');
        if ($assessmentIds->isNotEmpty()) {
            DB::table('assessment_subcpmks')->whereIn('assessment_id', $assessmentIds)->delete();
        }

        DB::table('rps_tasks')->where('rps_version_id', $version->id)->delete();
        DB::table('assessments')->where('rps_version_id', $version->id)->delete();

        $assessmentIdsByName = [];
        $sequence = 1;

        foreach ($assessments as $item) {
            $type = $item['type'];
            $week = (int) $item['week_number'];

            if ($type === 'uts') {
                $week = 8;
            } elseif ($type === 'uas') {
                $week = 16;
            }

            $id = (string) Str::uuid();

            DB::table('assessments')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'code' => 'ASM-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
                'name' => $item['name'],
                'type' => $type,
                'week_number' => $week,
                'description' => $item['description'],
                'weight' => $item['weight'],
                'source_type' => 'ai_accepted',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $assessmentIdsByName[$item['name']] = $id;
            $sequence++;

            foreach (array_unique($item['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');

                if ($subId) {
                    DB::table('assessment_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'assessment_id' => $id,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $taskSequence = 1;

        foreach (($payload['tasks'] ?? []) as $task) {
            $taskId = (string) Str::uuid();
            $assessmentId = $assessmentIdsByName[$task['assessment_name']] ?? null;

            DB::table('rps_tasks')->insert([
                'id' => $taskId,
                'rps_version_id' => $version->id,
                'assessment_id' => $assessmentId,
                'code' => 'RTM-'.str_pad((string) $taskSequence, 2, '0', STR_PAD_LEFT),
                'title' => $task['title'],
                'type' => $task['type'],
                'purpose' => $task['purpose'],
                'instructions' => $task['instructions'],
                'expected_output' => $task['expected_output'],
                'due_week' => $task['due_week'],
                'source_type' => 'ai_accepted',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_unique($task['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');

                if ($subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $taskId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $taskSequence++;
        }

        return [
            'assessments' => count($assessments),
            'tasks' => count($payload['tasks'] ?? []),
        ];
    }

    private function applySuccessMessage(string $type, array $stats): string
    {
        return match ($type) {
            'cpmk_review' => sprintf(
                'Usulan CPMK diterapkan: %d ditambah, %d diadaptasi, %d dipertahankan%s.',
                (int) ($stats['added'] ?? 0),
                (int) ($stats['adapted'] ?? 0),
                (int) ($stats['kept'] ?? 0),
                ((int) ($stats['skipped'] ?? 0)) > 0
                    ? ', '.((int) $stats['skipped']).' dilewati'
                    : ''
            ),
            'sub_cpmk' => sprintf(
                'Usulan Sub-CPMK diterapkan: %d ditambah, %d diadaptasi, %d dipertahankan%s.',
                (int) ($stats['added'] ?? 0),
                (int) ($stats['adapted'] ?? 0),
                (int) ($stats['kept'] ?? 0),
                ((int) ($stats['skipped'] ?? 0)) > 0
                    ? ', '.((int) $stats['skipped']).' dilewati'
                    : ''
            ),
            'weekly_plan' => 'Rencana AI 14 minggu diterapkan ke workspace. Silakan review setiap minggu.',
            'assessment_plan' => sprintf(
                'Rencana asesmen dan RTM diterapkan: %d asesmen dan %d RTM.',
                (int) ($stats['assessments'] ?? 0),
                (int) ($stats['tasks'] ?? 0)
            ),
            default => 'Rekomendasi AI diterapkan ke RPS.',
        };
    }

    private function normalizeCpmkCode(string $code): string
    {
        $code = strtoupper(trim($code));

        if (preg_match('/CPMK\s*[- ]?\s*(\d+)/i', $code, $match)) {
            return 'CPMK-'.str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    private function normalizeSubCpmkCode(string $code): string
    {
        $code = trim($code);

        if (preg_match('/SUB\s*[- ]?\s*CPMK\s*[- ]?\s*(\d+)/i', $code, $match)) {
            return 'Sub-CPMK-'.((int) $match[1]);
        }

        return $code;
    }

    private function nextCpmkCode(string $versionId): string
    {
        $used = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->all();

        for ($i = 1; $i <= 99; $i++) {
            $code = 'CPMK-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        return 'CPMK-'.str_pad((string) (count($used) + 1), 2, '0', STR_PAD_LEFT);
    }

    private function nextSubCpmkCode(string $versionId): string
    {
        $used = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->all();

        for ($i = 1; $i <= 99; $i++) {
            $code = 'Sub-CPMK-'.$i;
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        return 'Sub-CPMK-'.(count($used) + 1);
    }

    private function suggestion(string $versionId, string $id): object
    {
        $row = DB::table('ai_suggestions')
            ->where('id', $id)
            ->where('rps_version_id', $versionId)
            ->first();

        abort_unless($row, 404);

        return $row;
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id
                || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();

        abort_unless($version, 404);

        return [$record, $version];
    }
}
