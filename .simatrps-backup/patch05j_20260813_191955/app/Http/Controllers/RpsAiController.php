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
                'cpl_mapping',
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
            'selected_indices' => ['nullable', 'array'],
            'selected_indices.*' => ['integer', 'min:0', 'max:100'],
            'selected_assessment_indices' => ['nullable', 'array'],
            'selected_assessment_indices.*' => ['integer', 'min:0', 'max:100'],
            'selected_task_indices' => ['nullable', 'array'],
            'selected_task_indices.*' => ['integer', 'min:0', 'max:100'],
        ]);

        $payload = json_decode($row->suggestion_payload, true, 512, JSON_THROW_ON_ERROR);
        $selectedIndices = array_values(array_unique(array_map('intval', $data['selected_indices'] ?? [])));
        $selectedAssessmentIndices = array_values(array_unique(array_map('intval', $data['selected_assessment_indices'] ?? [])));
        $selectedTaskIndices = array_values(array_unique(array_map('intval', $data['selected_task_indices'] ?? [])));

        if (in_array($row->suggestion_type, ['cpmk_review', 'cpl_mapping', 'sub_cpmk'], true) && $selectedIndices === []) {
            throw ValidationException::withMessages([
                'ai' => 'Pilih minimal satu rekomendasi yang akan diterapkan.',
            ]);
        }

        if (
            $row->suggestion_type === 'assessment_plan'
            && $selectedAssessmentIndices === []
            && $selectedTaskIndices === []
        ) {
            throw ValidationException::withMessages([
                'ai' => 'Pilih minimal satu asesmen atau RTM yang akan diterapkan.',
            ]);
        }

        $result = DB::transaction(function () use (
            $row,
            $payload,
            $selectedIndices,
            $selectedAssessmentIndices,
            $selectedTaskIndices,
            $record,
            $version,
            $request
        ): array {
            $result = match ($row->suggestion_type) {
                'cpmk_review' => $this->applyCpmkReview($payload, $selectedIndices, $record, $version, $request->user()->id),
                'cpl_mapping' => $this->applyCplMapping($payload, $selectedIndices, $record, $version),
                'sub_cpmk' => $this->applySubCpmk($payload, $selectedIndices, $version, $request->user()->id),
                'weekly_plan' => $this->applyWeeklyPlan($payload, $version),
                'assessment_plan' => $this->applyAssessmentPlanSelective(
                    $payload,
                    $selectedAssessmentIndices,
                    $selectedTaskIndices,
                    $version,
                    $request->user()->id
                ),
                default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
            };

            if (($result['changed'] ?? 0) < 1 && in_array($row->suggestion_type, ['cpmk_review', 'cpl_mapping', 'sub_cpmk', 'assessment_plan'], true)) {
                throw ValidationException::withMessages([
                    'ai' => 'Tidak ada perubahan yang diterapkan. Pilih rekomendasi ADAPT atau ADD yang valid.',
                ]);
            }

            $acceptedPayload = $payload;
            if ($selectedIndices !== []) {
                $acceptedPayload['_selected_indices'] = $selectedIndices;
            }
            if ($selectedAssessmentIndices !== []) {
                $acceptedPayload['_selected_assessment_indices'] = $selectedAssessmentIndices;
            }
            if ($selectedTaskIndices !== []) {
                $acceptedPayload['_selected_task_indices'] = $selectedTaskIndices;
            }

            DB::table('ai_suggestions')->where('id', $row->id)->update([
                'status' => 'accepted',
                'accepted_payload' => json_encode($acceptedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

            return $result;
        });

        return back()->with(
            'success',
            ($result['message'] ?? 'Rekomendasi AI diterapkan ke RPS.').' Rekomendasi ini sudah dikeluarkan dari daftar aktif.'
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

    private function applyCplMapping(
        array $payload,
        array $selectedIndices,
        object $rps,
        object $version
    ): array {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')
                    ->where('course_id', $rps->course_id)
                    ->pluck('cpl_id')
                    ->all(),
                ...DB::table('rps_additional_cpls')
                    ->where('rps_version_id', $version->id)
                    ->pluck('cpl_id')
                    ->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy('code');

        $mappings = $payload['mappings'] ?? [];
        $changed = 0;

        foreach ($selectedIndices as $index) {
            $item = $mappings[$index] ?? null;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan pemetaan CPMK-CPL AI tidak valid.',
                ]);
            }

            $cpmkCode = $this->normalizeCpmkCode(
                (string) ($item['cpmk_code'] ?? '')
            );

            $cpmk = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $cpmkCode)
                ->first();

            if (! $cpmk) {
                throw ValidationException::withMessages([
                    'ai' => "CPMK {$cpmkCode} tidak ditemukan pada RPS.",
                ]);
            }

            $validCodes = collect($item['cpl_codes'] ?? [])
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->filter(fn ($code) => $scopeCpls->has($code))
                ->unique()
                ->values()
                ->all();

            if ($validCodes === []) {
                throw ValidationException::withMessages([
                    'ai' => "Rekomendasi {$cpmkCode} tidak memiliki CPL yang valid dalam Scope CPL RPS.",
                ]);
            }

            $this->replaceCpmkMappings(
                $cpmk->id,
                $validCodes,
                $scopeCpls
            );

            $changed++;
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} pemetaan CPMK-CPL terpilih diterapkan.",
        ];
    }

    private function applyCpmkReview(
        array $payload,
        array $selectedIndices,
        object $rps,
        object $version,
        int $userId
    ): array {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')->where('course_id', $rps->course_id)->pluck('cpl_id')->all(),
                ...DB::table('rps_additional_cpls')->where('rps_version_id', $version->id)->pluck('cpl_id')->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy('code');

        $recommendations = $payload['recommendations'] ?? [];
        $changed = 0;
        $adapted = 0;
        $added = 0;

        foreach ($selectedIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan CPMK AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));

            if ($action === 'keep') {
                continue;
            }

            if ($action === 'adapt') {
                $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
                $cpmk = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $target)
                    ->first();

                if (! $cpmk) {
                    throw ValidationException::withMessages([
                        'ai' => 'CPMK target '.$target.' tidak ditemukan. Buat rekomendasi AI baru agar sesuai data RPS terbaru.',
                    ]);
                }

                DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                    'description' => trim((string) ($item['description'] ?? $cpmk->description)),
                    'bloom_level' => ($item['bloom_level'] ?? null) ?: null,
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings($cpmk->id, $item['cpl_codes'] ?? [], $scopeCpls);
                $changed++;
                $adapted++;
                continue;
            }

            if ($action === 'add') {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages(['ai' => 'Rumusan CPMK AI kosong dan tidak dapat ditambahkan.']);
                }

                $duplicate = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['ai' => 'CPMK yang dipilih sudah ada pada RPS.']);
                }

                $code = $this->nextCpmkCode($version->id);
                $sequence = ((int) DB::table('rps_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;
                $id = (string) Str::uuid();

                DB::table('rps_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    'description' => $description,
                    'bloom_level' => ($item['bloom_level'] ?? null) ?: null,
                    'source_type' => 'ai_added',
                    'source_cpmk_id' => null,
                    'sequence_no' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->replaceCpmkMappings($id, $item['cpl_codes'] ?? [], $scopeCpls);
                $changed++;
                $added++;
                continue;
            }

            throw ValidationException::withMessages(['ai' => 'Aksi CPMK AI tidak dikenali: '.$action]);
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} rekomendasi CPMK diterapkan ({$adapted} adaptasi, {$added} tambahan).",
        ];
    }

    private function normalizeCpmkCode(string $code): string
    {
        $code = strtoupper(trim($code));

        if (preg_match('/^CPMK[- ]?0*(\\d+)$/', $code, $match)) {
            return 'CPMK-'.str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
        }

        return $code;
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

    private function applySubCpmk(
        array $payload,
        array $selectedIndices,
        object $version,
        int $userId
    ): array {
        $items = $payload['items'] ?? [];
        $changed = 0;
        $adapted = 0;
        $added = 0;

        foreach ($selectedIndices as $index) {
            $item = $items[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan Sub-CPMK AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') {
                continue;
            }

            $parentCode = $this->normalizeCpmkCode((string) ($item['parent_cpmk_code'] ?? ''));
            $parent = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $parentCode)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'ai' => 'CPMK induk '.$parentCode.' tidak ditemukan untuk Sub-CPMK terpilih.',
                ]);
            }

            if ($action === 'adapt') {
                $target = trim((string) ($item['target_code'] ?? ''));
                $sub = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(code) = ?', [mb_strtolower($target)])
                    ->first();

                if (! $sub) {
                    throw ValidationException::withMessages([
                        'ai' => 'Sub-CPMK target '.$target.' tidak ditemukan. Buat rekomendasi AI baru.',
                    ]);
                }

                DB::table('rps_sub_cpmks')->where('id', $sub->id)->update([
                    'description' => trim((string) ($item['description'] ?? $sub->description)),
                    'bloom_level' => $item['bloom_level'],
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                DB::table('rps_cpmk_subcpmks')->where('rps_sub_cpmk_id', $sub->id)->delete();
                DB::table('rps_cpmk_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_cpmk_id' => $parent->id,
                    'rps_sub_cpmk_id' => $sub->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed++;
                $adapted++;
                continue;
            }

            if ($action === 'add') {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages(['ai' => 'Rumusan Sub-CPMK AI kosong.']);
                }

                $duplicate = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['ai' => 'Sub-CPMK yang dipilih sudah ada pada RPS.']);
                }

                $id = (string) Str::uuid();
                $sequence = ((int) DB::table('rps_sub_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;

                DB::table('rps_sub_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextSubCpmkCode($version->id),
                    'description' => $description,
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
                $changed++;
                $added++;
                continue;
            }

            throw ValidationException::withMessages(['ai' => 'Aksi Sub-CPMK AI tidak dikenali: '.$action]);
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} rekomendasi Sub-CPMK diterapkan ({$adapted} adaptasi, {$added} tambahan).",
        ];
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

        return [
            'changed' => 14,
            'message' => 'Rencana 14 minggu AI diterapkan ke workspace.',
        ];
    }

    private function applyAssessmentPlanSelective(
        array $payload,
        array $selectedAssessmentIndices,
        array $selectedTaskIndices,
        object $version,
        int $userId
    ): array {
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
        $changedTasks = 0;

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan asesmen AI tidak valid.',
                ]);
            }

            $type = (string) ($item['type'] ?? 'other');
            $week = (int) ($item['week_number'] ?? 1);

            if ($type === 'uts') {
                $week = 8;
            } elseif ($type === 'uas') {
                $week = 16;
            }

            $query = DB::table('assessments')
                ->where('rps_version_id', $version->id);

            if (in_array($type, ['uts', 'uas'], true)) {
                $existing = $query->where('type', $type)->first();
            } else {
                $existing = $query
                    ->whereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower(trim((string) ($item['name'] ?? '')))]
                    )
                    ->first();
            }

            $assessmentId = $existing?->id ?: (string) Str::uuid();

            $values = [
                'name' => trim((string) ($item['name'] ?? 'Asesmen AI')),
                'type' => $type,
                'week_number' => $week,
                'description' => (string) ($item['description'] ?? ''),
                'weight' => (float) ($item['weight'] ?? 0),
                'source_type' => 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('assessments')
                    ->where('id', $assessmentId)
                    ->update($values);
            } else {
                DB::table('assessments')->insert($values + [
                    'id' => $assessmentId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextAssessmentCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessmentId)
                ->delete();

            foreach (array_unique($item['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');

                if ($subId) {
                    DB::table('assessment_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'assessment_id' => $assessmentId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $changedAssessments++;
        }

        foreach ($selectedTaskIndices as $index) {
            $task = $tasks[$index] ?? null;

            if (! is_array($task)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan RTM AI tidak valid.',
                ]);
            }

            $title = trim((string) ($task['title'] ?? 'RTM AI'));

            $existing = DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->first();

            $assessmentId = null;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));

            if ($assessmentName !== '') {
                $assessmentId = DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($assessmentName)])
                    ->value('id');
            }

            $taskId = $existing?->id ?: (string) Str::uuid();

            $values = [
                'assessment_id' => $assessmentId,
                'title' => $title,
                'type' => (string) ($task['type'] ?? 'assignment'),
                'purpose' => (string) ($task['purpose'] ?? ''),
                'instructions' => (string) ($task['instructions'] ?? ''),
                'expected_output' => (string) ($task['expected_output'] ?? ''),
                'due_week' => (int) ($task['due_week'] ?? 1),
                'source_type' => 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('rps_tasks')
                    ->where('id', $taskId)
                    ->update($values);
            } else {
                DB::table('rps_tasks')->insert($values + [
                    'id' => $taskId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextTaskCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $taskId)
                ->delete();

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

            $changedTasks++;
        }

        $totalWeight = round(
            (float) DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->sum('weight'),
            2
        );

        $message = "{$changedAssessments} asesmen dan {$changedTasks} RTM terpilih diterapkan.";

        if (abs($totalWeight - 100.0) >= 0.01) {
            $message .= " Total bobot asesmen saat ini {$totalWeight}%; sesuaikan hingga 100% sebelum finalisasi.";
        }

        return [
            'changed' => $changedAssessments + $changedTasks,
            'message' => $message,
        ];
    }

    private function nextAssessmentCode(string $versionId): string
    {
        $numbers = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                preg_match('/(\d+)$/', (string) $code, $match);
                return (int) ($match[1] ?? 0);
            });

        return 'ASM-'.str_pad(
            (string) (($numbers->max() ?? 0) + 1),
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    private function nextTaskCode(string $versionId): string
    {
        $numbers = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                preg_match('/(\d+)$/', (string) $code, $match);
                return (int) ($match[1] ?? 0);
            });

        return 'RTM-'.str_pad(
            (string) (($numbers->max() ?? 0) + 1),
            2,
            '0',
            STR_PAD_LEFT
        );
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
