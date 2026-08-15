<?php

namespace App\Http\Controllers;

use App\Services\Rps\GroqRpsService;
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
        GroqRpsService $groq,
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

        $context = $contextService->build($record, $version);
        $result = $groq->generate(
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
                'model' => $result['model'],
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

        $payload = json_decode($row->suggestion_payload, true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($row, $payload, $record, $version, $request): void {
            match ($row->suggestion_type) {
                'cpmk_review' => $this->applyCpmkReview($payload, $record, $version, $request->user()->id),
                'sub_cpmk' => $this->applySubCpmk($payload, $version, $request->user()->id),
                'weekly_plan' => $this->applyWeeklyPlan($payload, $version),
                'assessment_plan' => $this->applyAssessmentPlan($payload, $version, $request->user()->id),
                default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
            };

            DB::table('ai_suggestions')->where('id', $row->id)->update([
                'status' => 'accepted',
                'accepted_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with(
            'success',
            'Rekomendasi AI diterapkan. Silakan review dan edit kembali bila diperlukan.'
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

    private function applyCpmkReview(array $payload, object $rps, object $version, int $userId): void
    {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')->where('course_id', $rps->course_id)->pluck('cpl_id')->all(),
                ...DB::table('rps_additional_cpls')->where('rps_version_id', $version->id)->pluck('cpl_id')->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy('code');

        foreach (($payload['recommendations'] ?? []) as $item) {
            $action = $item['action'] ?? 'keep';

            if ($action === 'keep') {
                continue;
            }

            if ($action === 'adapt') {
                $cpmk = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $item['target_code'])
                    ->first();

                if (! $cpmk) {
                    continue;
                }

                DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                    'description' => $item['description'],
                    'bloom_level' => $item['bloom_level'] ?: null,
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings($cpmk->id, $item['cpl_codes'] ?? [], $scopeCpls);
                continue;
            }

            if ($action === 'add') {
                $code = $this->nextCpmkCode($version->id);
                $sequence = ((int) DB::table('rps_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;
                $id = (string) Str::uuid();

                DB::table('rps_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    'description' => $item['description'],
                    'bloom_level' => $item['bloom_level'] ?: null,
                    'source_type' => 'ai_added',
                    'source_cpmk_id' => null,
                    'sequence_no' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings($id, $item['cpl_codes'] ?? [], $scopeCpls);
            }
        }
    }

    private function replaceCpmkMappings(string $cpmkId, array $codes, $scopeCpls): void
    {
        $ids = collect($codes)
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

    private function applySubCpmk(array $payload, object $version, int $userId): void
    {
        foreach (($payload['items'] ?? []) as $item) {
            $parent = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $item['parent_cpmk_code'])
                ->first();

            if (! $parent) {
                continue;
            }

            $duplicate = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('description', $item['description'])
                ->exists();

            if ($duplicate) {
                continue;
            }

            $id = (string) Str::uuid();
            $sequence = ((int) DB::table('rps_sub_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;

            DB::table('rps_sub_cpmks')->insert([
                'id' => $id,
                'rps_version_id' => $version->id,
                'code' => $this->nextSubCpmkCode($version->id),
                'description' => $item['description'],
                'bloom_level' => $item['bloom_level'],
                'sequence_no' => $sequence,
                'source_type' => 'ai_accepted',
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
        }
    }

    private function applyWeeklyPlan(array $payload, object $version): void
    {
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
                'learning_method' => $item['learning_method'] ?? null,
                'learning_activity' => $item['learning_activity'] ?? null,
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

                if (! filled($week->{$key} ?? null) && filled($value)) {
                    $updates[$key] = $value;
                }
            }

            if (count($updates) > 1) {
                $updates['source_type'] = 'ai_accepted';
                DB::table('rps_weekly_plans')->where('id', $week->id)->update($updates);
            }
        }
    }

    private function applyAssessmentPlan(array $payload, object $version, int $userId): void
    {
        $existingConfigured = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->where(function ($query): void {
                $query->whereNotNull('weight')
                    ->orWhere('source_type', '!=', 'system');
            })
            ->exists();

        $existingTasks = DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->exists();

        if ($existingConfigured || $existingTasks) {
            throw ValidationException::withMessages([
                'ai' => 'Asesmen/RTM sudah pernah dikonfigurasi. Demi keamanan, rekomendasi AI tidak menimpa data tersebut. Hapus/rapikan manual terlebih dahulu atau gunakan rekomendasi sebagai referensi.',
            ]);
        }

        $assessments = $payload['assessments'] ?? [];
        $total = round(collect($assessments)->sum(fn ($a) => (float) ($a['weight'] ?? 0)), 2);

        if (abs($total - 100.0) >= 0.01) {
            throw ValidationException::withMessages([
                'ai' => "Rekomendasi asesmen AI tidak dapat diterapkan karena total bobot {$total}%, bukan 100%.",
            ]);
        }

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
