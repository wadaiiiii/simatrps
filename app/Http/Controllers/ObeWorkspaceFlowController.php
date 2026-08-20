<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObeWorkspaceFlowController extends ObeWorkspaceController
{
    public function storeCpmk(Request $request, string $rps): RedirectResponse
    {
        $versionId = $this->versionId($rps);
        $before = $versionId
            ? DB::table('rps_cpmks')->where('rps_version_id', $versionId)->count()
            : 0;

        $response = parent::storeCpmk($request, $rps);

        if ($versionId) {
            $after = DB::table('rps_cpmks')->where('rps_version_id', $versionId)->count();
            if ($after > $before) {
                $this->invalidateDownstreamAi($versionId, (string) $request->user()->id);
                $response->with(
                    'success',
                    'CPMK RPS berhasil ditambahkan. Karena struktur CPMK berubah, Pemetaan Bloom AI dan Pemetaan CPMK → CPL AI perlu dijalankan setelah rumusan CPMK final.'
                );
            }
        }

        return $response;
    }

    public function importCurriculumCpmks(Request $request, string $rps): RedirectResponse
    {
        $versionId = $this->versionId($rps);
        $before = $versionId
            ? DB::table('rps_cpmks')->where('rps_version_id', $versionId)->count()
            : 0;

        $response = parent::importCurriculumCpmks($request, $rps);

        if ($versionId) {
            $after = DB::table('rps_cpmks')->where('rps_version_id', $versionId)->count();
            if ($after > $before) {
                $this->invalidateDownstreamAi($versionId, (string) $request->user()->id);
            }
        }

        return $response;
    }

    public function updateCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        $before = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->first(['rps_version_id', 'description']);

        $response = parent::updateCpmk($request, $rps, $cpmk);

        if (! $before) {
            return $response;
        }

        $after = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->first(['description']);

        if (
            $after
            && $this->comparableText((string) $before->description)
                !== $this->comparableText((string) $after->description)
        ) {
            DB::table('rps_cpmks')
                ->where('id', $cpmk)
                ->update([
                    'bloom_level' => null,
                    'updated_at' => now(),
                ]);

            $this->invalidateDownstreamAi(
                (string) $before->rps_version_id,
                (string) $request->user()->id
            );

            $response->with(
                'success',
                'CPMK RPS berhasil diperbarui. Rumusan CPMK berubah, sehingga level Bloom dikosongkan dan perlu ditelaah ulang sebelum pemetaan CPMK → CPL diteruskan.'
            );
        }

        return $response;
    }

    public function resetCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        $row = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->first(['rps_version_id']);

        $response = parent::resetCpmk($request, $rps, $cpmk);

        if ($row) {
            $this->invalidateDownstreamAi(
                (string) $row->rps_version_id,
                (string) $request->user()->id
            );
        }

        return $response;
    }

    public function destroyCpmk(Request $request, string $rps, string $cpmk): RedirectResponse
    {
        $row = DB::table('rps_cpmks')
            ->where('id', $cpmk)
            ->first(['rps_version_id']);

        $response = parent::destroyCpmk($request, $rps, $cpmk);

        if ($row) {
            $this->invalidateDownstreamAi(
                (string) $row->rps_version_id,
                (string) $request->user()->id
            );
        }

        return $response;
    }

    private function versionId(string $rps): ?string
    {
        $value = DB::table('rps')->where('id', $rps)->value('current_version_id');

        return filled($value) ? (string) $value : null;
    }

    private function invalidateDownstreamAi(string $versionId, string $userId): void
    {
        DB::table('ai_suggestions')
            ->where('rps_version_id', $versionId)
            ->whereIn('suggestion_type', ['bloom_mapping', 'cpl_mapping', 'sub_cpmk'])
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'decided_by' => $userId,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function comparableText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
