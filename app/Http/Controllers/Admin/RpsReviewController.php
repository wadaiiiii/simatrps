<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RpsReviewController extends Controller
{
    public function show(string $rps): Response
    {
        $record = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->join('users', 'users.id', '=', 'rps.owner_id')
            ->leftJoin('rps_versions', 'rps_versions.id', '=', 'rps.current_version_id')
            ->where('rps.id', $rps)
            ->first([
                'rps.id',
                'rps.current_version_id',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.status',
                'rps.created_at',
                'rps.updated_at',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
                'courses.credits',
                'users.id as owner_id',
                'users.name as owner_name',
                'users.academic_title as owner_academic_title',
                'users.nidn as owner_nidn',
                'users.email as owner_email',
                'rps_versions.version_no',
                'rps_versions.status as version_status',
                'rps_versions.finalized_at',
            ]);

        abort_unless($record !== null, 404);
        abort_unless(filled($record->current_version_id), 404);

        $this->ensureReviewTable();

        $versionId = (string) $record->current_version_id;

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level', 'sequence_no']);

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level', 'sequence_no']);

        $weeks = DB::table('rps_weekly_plans')
            ->leftJoin('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'rps_weekly_plans.rps_sub_cpmk_id')
            ->where('rps_weekly_plans.rps_version_id', $versionId)
            ->orderBy('rps_weekly_plans.week_number')
            ->get([
                'rps_weekly_plans.week_number',
                'rps_weekly_plans.is_exam',
                'rps_weekly_plans.exam_type',
                'rps_weekly_plans.material_text',
                'rps_weekly_plans.learning_method',
                'rps_weekly_plans.learning_activity',
                'rps_weekly_plans.assessment_indicator',
                'rps_weekly_plans.assessment_criteria',
                'rps_weekly_plans.assessment_method',
                'rps_weekly_plans.assessment_weight',
                'rps_sub_cpmks.code as sub_cpmk_code',
                'rps_sub_cpmks.description as sub_cpmk_description',
            ]);

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'description', 'weight']);

        $tasks = DB::table('rps_tasks')
            ->leftJoin('assessments', 'assessments.id', '=', 'rps_tasks.assessment_id')
            ->where('rps_tasks.rps_version_id', $versionId)
            ->orderByRaw('COALESCE(rps_tasks.due_week, 99)')
            ->orderBy('rps_tasks.code')
            ->get([
                'rps_tasks.id',
                'rps_tasks.code',
                'rps_tasks.title',
                'rps_tasks.type',
                'rps_tasks.purpose',
                'rps_tasks.instructions',
                'rps_tasks.expected_output',
                'rps_tasks.due_week',
                'assessments.code as assessment_code',
                'assessments.name as assessment_name',
            ]);

        $latestValidationAt = DB::table('obe_validation_results')
            ->where('rps_version_id', $versionId)
            ->max('validated_at');

        $validationRows = $latestValidationAt
            ? DB::table('obe_validation_results')
                ->where('rps_version_id', $versionId)
                ->where('validated_at', $latestValidationAt)
                ->orderBy('rule_code')
                ->get(['rule_code', 'severity', 'is_passed', 'message', 'validated_at'])
            : collect();

        $blockingValidationRows = $validationRows
            ->filter(fn (object $row): bool => strtolower((string) $row->severity) !== 'advisory');

        $obePercent = null;
        if (strtolower((string) $record->status) === 'final') {
            $obePercent = 100;
        } elseif ($blockingValidationRows->isNotEmpty()) {
            $passed = $blockingValidationRows
                ->filter(fn (object $row): bool => (bool) $row->is_passed)
                ->count();
            $obePercent = (int) round(($passed / $blockingValidationRows->count()) * 100);
        }

        $reviewHistory = DB::table('rps_reviews')
            ->join('users as reviewers', 'reviewers.id', '=', 'rps_reviews.reviewer_id')
            ->where('rps_reviews.rps_version_id', $versionId)
            ->orderByDesc('rps_reviews.reviewed_at')
            ->limit(20)
            ->get([
                'rps_reviews.id',
                'rps_reviews.status',
                'rps_reviews.note',
                'rps_reviews.reviewed_at',
                'reviewers.id as reviewer_id',
                'reviewers.name as reviewer_name',
            ]);

        $latestReview = $reviewHistory->first();
        $reviewOutdated = $latestReview !== null
            && filled($record->updated_at)
            && filled($latestReview->reviewed_at)
            && strtotime((string) $record->updated_at) > strtotime((string) $latestReview->reviewed_at);

        return Inertia::render('admin/rps-review', [
            'rps' => [
                'id' => (string) $record->id,
                'academic_year' => (string) $record->academic_year,
                'academic_semester' => (string) $record->academic_semester,
                'status' => (string) $record->status,
                'version_status' => (string) ($record->version_status ?? 'draft'),
                'version_no' => (float) ($record->version_no ?? 1),
                'finalized_at' => $record->finalized_at,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'course' => [
                    'name' => (string) $record->course_name,
                    'code' => filled($record->official_code ?? null)
                        ? (string) $record->official_code
                        : (string) $record->system_code,
                    'credits' => (float) $record->credits,
                ],
                'owner' => [
                    'id' => (int) $record->owner_id,
                    'name' => (string) $record->owner_name,
                    'academic_title' => $record->owner_academic_title,
                    'nidn' => $record->owner_nidn,
                    'email' => (string) $record->owner_email,
                ],
            ],
            'summary' => [
                'cpmk_count' => $cpmks->count(),
                'sub_cpmk_count' => $subCpmks->count(),
                'week_count' => $weeks->count(),
                'assessment_count' => $assessments->count(),
                'assessment_weight_total' => round((float) $assessments->sum('weight'), 2),
                'task_count' => $tasks->count(),
                'obe_percent' => $obePercent,
                'obe_validated_at' => $latestValidationAt,
            ],
            'cpmks' => $cpmks,
            'subCpmks' => $subCpmks,
            'weeks' => $weeks,
            'assessments' => $assessments,
            'tasks' => $tasks,
            'validationRows' => $validationRows,
            'review' => [
                'latest' => $latestReview,
                'outdated' => $reviewOutdated,
                'history' => $reviewHistory,
            ],
        ]);
    }

    public function store(Request $request, string $rps): RedirectResponse
    {
        $record = DB::table('rps')
            ->where('id', $rps)
            ->first(['id', 'status', 'current_version_id']);

        abort_unless($record !== null, 404);
        abort_unless(filled($record->current_version_id), 404);

        $this->ensureReviewTable();

        $validated = $request->validate([
            'status' => ['required', Rule::in(['revision_required', 'approved'])],
            'note' => ['nullable', 'string', 'max:5000', 'required_if:status,revision_required'],
        ]);

        if ($validated['status'] === 'approved' && strtolower((string) $record->status) !== 'final') {
            return back()->withErrors([
                'status' => 'RPS hanya dapat disetujui setelah berstatus Final dan lolos Validator OBE.',
            ]);
        }

        DB::table('rps_reviews')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => (string) $record->current_version_id,
            'reviewer_id' => $request->user()->id,
            'status' => $validated['status'],
            'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            $validated['status'] === 'approved'
                ? 'RPS disetujui. Status tindak lanjut sudah diperbarui.'
                : 'Catatan revisi disimpan dan dapat ditindaklanjuti oleh dosen.'
        );
    }

    private function ensureReviewTable(): void
    {
        if (Schema::hasTable('rps_reviews')) {
            return;
        }

        Schema::create('rps_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->index();
            $table->timestamps();

            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();
            $table->index(['rps_version_id', 'reviewed_at']);
        });
    }
}
