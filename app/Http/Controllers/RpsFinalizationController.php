<?php

namespace App\Http\Controllers;

use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RpsFinalizationController extends Controller
{
    public function finalize(
        Request $request,
        string $rps,
        ObeWorkspaceService $workspace,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $assessmentSync->syncVersion($version->id);
        $result = $workspace->validateAndPersist($version->id);

        if (! ($result['is_valid'] ?? false)) {
            $blocking = collect($result['checks'] ?? [])
                ->filter(fn ($check) => ! ($check['done'] ?? false))
                ->reject(fn ($check) => ($check['severity'] ?? 'required') === 'advisory')
                ->pluck('label')
                ->filter()
                ->take(3)
                ->implode(', ');

            throw ValidationException::withMessages([
                'finalization' => 'RPS belum dapat difinalisasi. Selesaikan Validator OBE hingga 100%'
                    .($blocking !== '' ? ' (periksa: '.$blocking.')' : '.')
            ]);
        }

        DB::transaction(function () use ($record, $version): void {
            DB::table('rps_versions')
                ->where('id', $version->id)
                ->update([
                    'status' => 'final',
                    'finalized_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('rps')
                ->where('id', $record->id)
                ->update([
                    'status' => 'final',
                    'updated_at' => now(),
                ]);
        });

        return back()->with(
            'success',
            'RPS berhasil difinalisasi. Status dokumen sekarang Final dan siap digunakan.'
        );
    }

    public function reopen(Request $request, string $rps): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);

        DB::transaction(function () use ($record, $version): void {
            DB::table('rps_versions')
                ->where('id', $version->id)
                ->update([
                    'status' => 'draft',
                    'finalized_at' => null,
                    'updated_at' => now(),
                ]);

            DB::table('rps')
                ->where('id', $record->id)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);
        });

        return back()->with(
            'success',
            'RPS dibuka kembali untuk diedit. Setelah perubahan selesai, lakukan Validasi OBE dan Finalisasi RPS kembali.'
        );
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();
        abort_unless($version, 404);

        return [$record, $version];
    }
}
