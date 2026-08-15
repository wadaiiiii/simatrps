<?php

namespace App\Http\Controllers;

use App\Services\Rps\ObeWorkspaceService;
use App\Services\Rps\RpsSmartDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RpsAutomationController extends Controller
{
    public function smartDraft(
        Request $request,
        string $rps,
        RpsSmartDraftService $service
    ): RedirectResponse {
        [$record, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['fill_empty', 'overwrite'])],
        ]);

        $result = $service->generate(
            $record,
            $version,
            $request->user()->id,
            $validated['mode'] ?? 'fill_empty'
        );

        return back()->with(
            'success',
            "Smart Draft selesai. {$result['updated_weeks']} pertemuan diperbarui."
        );
    }

    public function copyPrevious(
        Request $request,
        string $rps,
        int $week,
        RpsSmartDraftService $service
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $service->copyPreviousWeek($version->id, $week);

        return back()->with('success', "Minggu {$week} menyalin draft minggu sebelumnya.");
    }

    public function applyMethod(
        Request $request,
        string $rps,
        RpsSmartDraftService $service
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*' => ['integer', 'min:1', 'max:16'],
            'learning_method' => ['required', 'string', 'max:2000'],
        ]);

        $count = $service->applyMethod(
            $version->id,
            $validated['weeks'],
            $validated['learning_method']
        );

        return back()->with('success', "Metode pembelajaran diterapkan pada {$count} minggu.");
    }

    public function validateObe(
        Request $request,
        string $rps,
        ObeWorkspaceService $workspace
    ): RedirectResponse {
        [, $version] = $this->context($request, $rps);

        $result = $workspace->validateAndPersist($version->id);

        return back()->with(
            'success',
            $result['is_valid']
                ? 'Validasi OBE selesai: seluruh pemeriksaan lulus.'
                : "Validasi OBE selesai: kelengkapan {$result['percent']}%."
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

        $version = DB::table('rps_versions')->where('id', $record->current_version_id)->first();
        abort_unless($version, 404);

        return [$record, $version];
    }
}
