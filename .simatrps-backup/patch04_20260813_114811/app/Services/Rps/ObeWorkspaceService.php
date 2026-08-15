<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;

class ObeWorkspaceService
{
    public function progress(string $versionId): array
    {
        $cpmkIds = DB::table('rps_cpmks')->where('rps_version_id', $versionId)->pluck('id');
        $cpmkTotal = $cpmkIds->count();
        $mapped = $cpmkTotal ? DB::table('rps_cpmk_cpls')->whereIn('rps_cpmk_id', $cpmkIds)->distinct()->count('rps_cpmk_id') : 0;
        $subTotal = DB::table('rps_sub_cpmks')->where('rps_version_id', $versionId)->count();
        $covered = $cpmkTotal ? DB::table('rps_cpmk_subcpmks')->whereIn('rps_cpmk_id', $cpmkIds)->distinct()->count('rps_cpmk_id') : 0;
        $materials = DB::table('rps_materials')->where('rps_version_id', $versionId)->count();
        $weeks = DB::table('rps_weekly_plans')->where('rps_version_id', $versionId)->orderBy('week_number')->get();
        $filledWeeks = $weeks->filter(fn ($w) => $w->is_exam || filled($w->rps_sub_cpmk_id) || filled($w->material_text) || filled($w->learning_method))->count();

        $checks = [
            ['label' => 'CPMK → CPL', 'done' => $cpmkTotal > 0 && $mapped === $cpmkTotal],
            ['label' => 'Sub-CPMK', 'done' => $subTotal > 0 && ($cpmkTotal === 0 || $covered === $cpmkTotal)],
            ['label' => 'Bahan Kajian', 'done' => $materials > 0],
            ['label' => '16 Pertemuan', 'done' => $weeks->count() === 16 && $filledWeeks === 16],
        ];

        $done = collect($checks)->where('done', true)->count();
        return ['checks' => $checks, 'percent' => (int) round($done / count($checks) * 100)];
    }
}
