<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->user()->role === 'admin') {
            return $this->adminDashboard($request);
        }

        $ownerId = $request->user()->id;
        $hasReviewTable = Schema::hasTable('rps_reviews');

        $rps = DB::table('rps')->where('owner_id', $ownerId);

        $recent = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->where('rps.owner_id', $ownerId)
            ->orderByDesc('rps.updated_at')
            ->limit(5)
            ->get([
                'rps.id',
                'rps.current_version_id',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.status',
                'rps.updated_at',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
            ])
            ->map(function (object $row) use ($hasReviewTable): object {
                $latestReview = $hasReviewTable && filled($row->current_version_id ?? null)
                    ? DB::table('rps_reviews')
                        ->join('users as reviewers', 'reviewers.id', '=', 'rps_reviews.reviewer_id')
                        ->where('rps_reviews.rps_version_id', $row->current_version_id)
                        ->orderByDesc('rps_reviews.reviewed_at')
                        ->first([
                            'rps_reviews.status',
                            'rps_reviews.note',
                            'rps_reviews.reviewed_at',
                            'reviewers.name as reviewer_name',
                        ])
                    : null;

                $row->review_status = $latestReview?->status;
                $row->review_note = $latestReview?->note;
                $row->reviewed_at = $latestReview?->reviewed_at;
                $row->reviewer_name = $latestReview?->reviewer_name;
                $row->review_outdated = $latestReview !== null
                    && filled($row->updated_at ?? null)
                    && filled($latestReview->reviewed_at ?? null)
                    && Carbon::parse($row->updated_at)->greaterThan(Carbon::parse($latestReview->reviewed_at));

                return $row;
            });

        return Inertia::render('dashboard', [
            'stats' => [
                'rps' => (clone $rps)->count(),
                'draft' => (clone $rps)->where('status', 'draft')->count(),
                'valid_obe' => (clone $rps)->where('status', 'obe_valid')->count(),
                'curriculum_year' => DB::table('curriculums')->where('status', 'active')->max('year'),
            ],
            'recentRps' => $recent,
        ]);
    }

    private function adminDashboard(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $academicYear = trim((string) $request->query('academic_year', ''));
        $academicSemester = trim((string) $request->query('academic_semester', ''));
        $hasReviewTable = Schema::hasTable('rps_reviews');

        $allowedStatuses = ['draft', 'obe_valid', 'final'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $allowedSemesters = ['Ganjil', 'Genap', 'Pendek'];
        if (! in_array($academicSemester, $allowedSemesters, true)) {
            $academicSemester = '';
        }

        $query = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->join('users', 'users.id', '=', 'rps.owner_id')
            ->leftJoin('rps_versions', 'rps_versions.id', '=', 'rps.current_version_id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.nidn', 'like', "%{$search}%")
                        ->orWhere('courses.name', 'like', "%{$search}%")
                        ->orWhere('courses.official_code', 'like', "%{$search}%")
                        ->orWhere('courses.system_code', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('rps.status', $status))
            ->when($academicYear !== '', fn ($query) => $query->where('rps.academic_year', $academicYear))
            ->when($academicSemester !== '', fn ($query) => $query->where('rps.academic_semester', $academicSemester))
            ->orderByDesc('rps.updated_at')
            ->select([
                'rps.id',
                'rps.current_version_id',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.status',
                'rps.updated_at',
                'rps_versions.finalized_at',
                'users.id as owner_id',
                'users.name as owner_name',
                'users.academic_title as owner_academic_title',
                'users.nidn as owner_nidn',
                'users.email as owner_email',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
                DB::raw('CAST(courses.credits AS INTEGER) as credits'),
                'courses.semester_recommended',
            ]);

        $rows = $query->paginate(15)->withQueryString();

        $rows->through(function ($row) use ($hasReviewTable): array {
            $weeklyPlans = $row->current_version_id
                ? DB::table('rps_weekly_plans')
                    ->where('rps_version_id', $row->current_version_id)
                    ->get([
                        'is_exam',
                        'rps_sub_cpmk_id',
                        'material_text',
                        'learning_method',
                        'learning_activity',
                    ])
                : collect();

            $filledWeeks = $weeklyPlans->filter(fn ($week) =>
                (bool) $week->is_exam
                || (
                    filled($week->rps_sub_cpmk_id)
                    && filled($week->material_text)
                    && filled($week->learning_method)
                    && filled($week->learning_activity)
                )
            )->count();

            $assessmentWeight = $row->current_version_id
                ? round((float) DB::table('assessments')
                    ->where('rps_version_id', $row->current_version_id)
                    ->sum('weight'), 2)
                : 0.0;

            $latestReview = $hasReviewTable && filled($row->current_version_id ?? null)
                ? DB::table('rps_reviews')
                    ->join('users as reviewers', 'reviewers.id', '=', 'rps_reviews.reviewer_id')
                    ->where('rps_reviews.rps_version_id', $row->current_version_id)
                    ->orderByDesc('rps_reviews.reviewed_at')
                    ->first([
                        'rps_reviews.status',
                        'rps_reviews.note',
                        'rps_reviews.reviewed_at',
                        'reviewers.name as reviewer_name',
                    ])
                : null;

            $reviewOutdated = $latestReview !== null
                && filled($row->updated_at ?? null)
                && filled($latestReview->reviewed_at ?? null)
                && Carbon::parse($row->updated_at)->greaterThan(Carbon::parse($latestReview->reviewed_at));

            return [
                'id' => $row->id,
                'academic_year' => $row->academic_year,
                'academic_semester' => $row->academic_semester,
                'status' => $row->status,
                'updated_at' => $row->updated_at,
                'finalized_at' => $row->finalized_at,
                'owner' => [
                    'id' => $row->owner_id,
                    'name' => $row->owner_name,
                    'academic_title' => $row->owner_academic_title,
                    'nidn' => $row->owner_nidn,
                    'email' => $row->owner_email,
                ],
                'course' => [
                    'name' => $row->course_name,
                    'system_code' => $row->system_code,
                    'official_code' => $row->official_code,
                    'credits' => $row->credits,
                    'semester_recommended' => $row->semester_recommended,
                ],
                'progress' => [
                    'filled_weeks' => $filledWeeks,
                    'week_total' => max(16, $weeklyPlans->count()),
                    'assessment_weight' => $assessmentWeight,
                    'obe_percent' => $this->obePercent($row->current_version_id, $row->status),
                ],
                'review' => [
                    'status' => $latestReview?->status,
                    'note' => $latestReview?->note,
                    'reviewed_at' => $latestReview?->reviewed_at,
                    'reviewer_name' => $latestReview?->reviewer_name,
                    'outdated' => $reviewOutdated,
                ],
            ];
        });

        $lecturerBase = DB::table('users')->where('role', 'dosen');
        $activeLecturers = (clone $lecturerBase)->where('is_active', true)->count();
        $lecturersStarted = DB::table('rps')
            ->join('users', 'users.id', '=', 'rps.owner_id')
            ->where('users.role', 'dosen')
            ->where('users.is_active', true)
            ->distinct()
            ->count('rps.owner_id');

        $rpsBase = DB::table('rps');

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'lecturers_active' => $activeLecturers,
                'lecturers_started' => $lecturersStarted,
                'lecturers_not_started' => max(0, $activeLecturers - $lecturersStarted),
                'rps_total' => (clone $rpsBase)->count(),
                'rps_draft' => (clone $rpsBase)->where('status', 'draft')->count(),
                'rps_obe_valid' => (clone $rpsBase)->whereIn('status', ['obe_valid', 'final'])->count(),
                'rps_final' => (clone $rpsBase)->where('status', 'final')->count(),
            ],
            'rpsRows' => $rows,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'academic_year' => $academicYear,
                'academic_semester' => $academicSemester,
            ],
            'academicYears' => DB::table('rps')
                ->select('academic_year')
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year')
                ->values(),
        ]);
    }

    private function obePercent(mixed $versionId, mixed $status): ?int
    {
        if (strtolower((string) $status) === 'final') {
            return 100;
        }

        if (! filled($versionId)) {
            return null;
        }

        $validationRows = DB::table('obe_validation_results')
            ->where('rps_version_id', $versionId)
            ->get(['is_passed', 'details']);

        $blockingRows = $validationRows->filter(function ($row): bool {
            $details = json_decode((string) ($row->details ?? ''), true);
            $severity = is_array($details)
                ? (string) ($details['severity'] ?? 'required')
                : 'required';

            return $severity !== 'advisory';
        });

        if ($blockingRows->isEmpty()) {
            return null;
        }

        $passed = $blockingRows
            ->filter(fn ($row): bool => (bool) $row->is_passed)
            ->count();

        return (int) round(($passed / $blockingRows->count()) * 100);
    }
}