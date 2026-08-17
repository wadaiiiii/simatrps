<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
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

        $this->ensureDecisionTable();

        $validated = $request->validate([
            'check_key' => ['required', Rule::in(['assessment_semantics', 'rtm_semantics'])],
            'subject_key' => ['required', 'string', 'max:500'],
        ]);

        $key = [
            'rps_version_id' => $version->id,
            'check_key' => $validated['check_key'],
            'subject_key' => $validated['subject_key'],
        ];

        $existing = DB::table('rps_validator_decisions')->where($key)->first();

        if ($existing) {
            DB::table('rps_validator_decisions')
                ->where('id', $existing->id)
                ->update([
                    'decision' => 'keep',
                    'decided_by' => $request->user()->id,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('rps_validator_decisions')->insert([
                'id' => (string) Str::uuid(),
                ...$key,
                'decision' => 'keep',
                'decided_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Keputusan dosen disimpan. Rekomendasi ini tidak lagi dianggap sebagai masalah.');
    }

    private function ensureDecisionTable(): void
    {
        if (Schema::hasTable('rps_validator_decisions')) {
            return;
        }

        Schema::create('rps_validator_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('check_key', 80);
            $table->string('subject_key', 500);
            $table->string('decision', 30)->default('keep');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['rps_version_id', 'check_key', 'subject_key'],
                'rps_validator_decision_unique'
            );
            $table->index(['rps_version_id', 'check_key']);
            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();
        });
    }
}
