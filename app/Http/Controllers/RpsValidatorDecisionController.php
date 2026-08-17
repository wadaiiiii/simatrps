<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpsValidatorDecisionController extends Controller
{
    public function __invoke(Request $request, string $rps): RedirectResponse
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);
        abort_unless(
            $record->owner_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')->where('id', $record->current_version_id)->first();
        abort_unless($version, 404);

        abort_unless(Schema::hasTable('rps_validator_decisions'), 503, 'Penyimpanan keputusan validator belum siap.');

        $validated = $request->validate([
            'check_key' => ['required', Rule::in(['assessment_semantics', 'rtm_semantics'])],
            'subject_key' => ['required', 'string', 'max:500'],
        ]);

        DB::table('rps_validator_decisions')->updateOrInsert(
            [
                'rps_version_id' => $version->id,
                'check_key' => $validated['check_key'],
                'subject_key' => $validated['subject_key'],
            ],
            [
                'id' => (string) Str::uuid(),
                'decision' => 'keep',
                'decided_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Keputusan dosen disimpan. Rekomendasi ini tidak lagi dianggap sebagai masalah.');
    }
}
