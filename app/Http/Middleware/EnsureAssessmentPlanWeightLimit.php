<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssessmentPlanWeightLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $selected = collect($request->input('selected_assessment_indices', []))
            ->map(fn ($index) => (int) $index)
            ->unique()
            ->values();

        // RTM-only application does not change assessment weights.
        if ($selected->isEmpty()) {
            return $next($request);
        }

        $rpsId = (string) $request->route('rps');
        $suggestionId = (string) $request->route('suggestion');

        $rps = DB::table('rps')
            ->where('id', $rpsId)
            ->first(['id', 'owner_id', 'current_version_id']);

        // Let the controller return the canonical 404/403 when context is invalid.
        if (! $rps || ! $request->user()) {
            return $next($request);
        }

        if ($rps->owner_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return $next($request);
        }

        $suggestion = DB::table('ai_suggestions')
            ->where('id', $suggestionId)
            ->where('rps_version_id', $rps->current_version_id)
            ->where('suggestion_type', 'assessment_plan')
            ->where('status', 'pending')
            ->first(['suggestion_payload']);

        if (! $suggestion) {
            return $next($request);
        }

        $payload = json_decode((string) $suggestion->suggestion_payload, true);
        $recommendations = is_array($payload) ? ($payload['assessments'] ?? []) : [];

        $existing = DB::table('assessments')
            ->where('rps_version_id', $rps->current_version_id)
            ->get(['id', 'name', 'type', 'week_number', 'weight']);

        $projected = $existing
            ->mapWithKeys(fn ($row) => [(string) $row->id => (float) ($row->weight ?? 0)])
            ->all();

        foreach ($selected as $index) {
            $item = $recommendations[$index] ?? null;

            if (! is_array($item)) {
                continue;
            }

            $type = strtolower(trim((string) ($item['type'] ?? 'other')));
            $week = (int) ($item['week_number'] ?? 1);

            if ($type === 'uts') {
                $week = 8;
            } elseif ($type === 'uas') {
                $week = 16;
            }

            $name = mb_strtolower(trim((string) ($item['name'] ?? '')));

            $match = $existing->first(function ($row) use ($type, $week, $name): bool {
                $rowType = strtolower((string) ($row->type ?? ''));

                if (in_array($type, ['uts', 'uas'], true)) {
                    return $rowType === $type;
                }

                if ($name !== '' && mb_strtolower(trim((string) ($row->name ?? ''))) === $name) {
                    return true;
                }

                return $rowType === $type && (int) ($row->week_number ?? 0) === $week;
            });

            $key = $match?->id ?: 'new-'.$index;
            $projected[(string) $key] = max(0, (float) ($item['weight'] ?? 0));
        }

        $total = round(array_sum($projected), 2);

        if ($total > 100.0) {
            throw ValidationException::withMessages([
                'weight' => "Rekomendasi AI ini akan membuat total bobot menjadi {$total}%. Pilih/ubah komponen asesmen agar total maksimal 100% sebelum diterapkan.",
            ]);
        }

        return $next($request);
    }
}
