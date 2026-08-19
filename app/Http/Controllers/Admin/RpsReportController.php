<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RpsReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = $this->reportRows($filters);

        return Inertia::render('admin/rps-report', [
            'stats' => $this->stats($rows),
            'rows' => $rows->values(),
            'filters' => $filters,
            'academicYears' => DB::table('rps')
                ->select('academic_year')
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year')
                ->values(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->reportRows($filters);
        $filename = 'rekap-rps-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Dosen',
                'Gelar',
                'NIDN',
                'Email',
                'Kode Mata Kuliah',
                'Mata Kuliah',
                'SKS',
                'Tahun Akademik',
                'Semester',
                'Status RPS',
                'Pertemuan Terisi',
                'Total Pertemuan',
                'Bobot Asesmen (%)',
                'Validator OBE (%)',
                'Status Review',
                'Catatan Review',
                'Reviewer',
                'Tanggal Review',
                'Review Perlu Diulang',
                'Terakhir Diubah',
            ], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['owner']['name'],
                    $row['owner']['academic_title'] ?? '',
                    $row['owner']['nidn'] ?? '',
                    $row['owner']['email'],
                    $row['course']['code'],
                    $row['course']['name'],
                    $row['course']['credits'],
                    $row['academic_year'],
                    $row['academic_semester'],
                    strtoupper($row['status']),
                    $row['progress']['filled_weeks'],
                    $row['progress']['week_total'],
                    $row['progress']['assessment_weight'],
                    $row['progress']['obe_percent'] ?? '',
                    $this->reviewLabel($row['review']['status'], $row['review']['outdated']),
                    $row['review']['note'] ?? '',
                    $row['review']['reviewer_name'] ?? '',
                    $row['review']['reviewed_at'] ?? '',
                    $row['review']['outdated'] ? 'Ya' : 'Tidak',
                    $row['updated_at'],
                ], ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param  array{q:string, academic_year:string, academic_semester:string, status:string, review_status:string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function reportRows(array $filters): Collection
    {
        $hasReviewTable = Schema::hasTable('rps_reviews');

        $rows = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->join('users', 'users.id', '=', 'rps.owner_id')
            ->leftJoin('rps_versions', 'rps_versions.id', '=', 'rps.current_version_id')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.nidn', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('courses.name', 'like', "%{$search}%")
                        ->orWhere('courses.official_code', 'like', "%{$search}%")
                        ->orWhere('courses.system_code', 'like', "%{$search}%");
                });
            })
            ->when($filters['academic_year'] !== '', fn ($query) => $query->where('rps.academic_year', $filters['academic_year']))
            ->when($filters['academic_semester'] !== '', fn ($query) => $query->where('rps.academic_semester', $filters['academic_semester']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('rps.status', $filters['status']))
            ->orderBy('users.name')
            ->orderByDesc('rps.academic_year')
            ->orderBy('courses.name')
            ->get([
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
                'courses.credits',
            ])
            ->map(fn (object $record): array => $this->transformRow($record, $hasReviewTable));

        if ($filters['review_status'] === '') {
            return $rows->values();
        }

        return $rows
            ->filter(function (array $row) use ($filters): bool {
                $status = $filters['review_status'];

                if ($status === 'outdated') {
                    return (bool) $row['review']['outdated'];
                }

                if ($status === 'unreviewed') {
                    return $row['review']['status'] === null;
                }

                return $row['review']['status'] === $status && ! $row['review']['outdated'];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(object $record, bool $hasReviewTable): array
    {
        $versionId = filled($record->current_version_id ?? null)
            ? (string) $record->current_version_id
            : null;

        $weeks = $versionId
            ? DB::table('rps_weekly_plans')
                ->where('rps_version_id', $versionId)
                ->get([
                    'is_exam',
                    'rps_sub_cpmk_id',
                    'material_text',
                    'learning_method',
                    'learning_activity',
                ])
            : collect();

        $filledWeeks = $weeks->filter(fn (object $week): bool => (bool) $week->is_exam
            || (
                filled($week->rps_sub_cpmk_id ?? null)
                && filled($week->material_text ?? null)
                && filled($week->learning_method ?? null)
                && filled($week->learning_activity ?? null)
            )
        )->count();

        $assessmentWeight = $versionId
            ? round((float) DB::table('assessments')
                ->where('rps_version_id', $versionId)
                ->sum('weight'), 2)
            : 0.0;

        $latestReview = $hasReviewTable && $versionId
            ? DB::table('rps_reviews')
                ->join('users as reviewers', 'reviewers.id', '=', 'rps_reviews.reviewer_id')
                ->where('rps_reviews.rps_version_id', $versionId)
                ->orderByDesc('rps_reviews.reviewed_at')
                ->first([
                    'rps_reviews.status',
                    'rps_reviews.note',
                    'rps_reviews.reviewed_at',
                    'reviewers.name as reviewer_name',
                ])
            : null;

        $reviewOutdated = $latestReview !== null
            && filled($record->updated_at ?? null)
            && filled($latestReview->reviewed_at ?? null)
            && Carbon::parse($record->updated_at)->greaterThan(Carbon::parse($latestReview->reviewed_at));

        return [
            'id' => (string) $record->id,
            'academic_year' => (string) $record->academic_year,
            'academic_semester' => (string) $record->academic_semester,
            'status' => (string) $record->status,
            'updated_at' => $record->updated_at,
            'finalized_at' => $record->finalized_at,
            'owner' => [
                'id' => (int) $record->owner_id,
                'name' => (string) $record->owner_name,
                'academic_title' => $record->owner_academic_title,
                'nidn' => $record->owner_nidn,
                'email' => (string) $record->owner_email,
            ],
            'course' => [
                'name' => (string) $record->course_name,
                'code' => filled($record->official_code ?? null)
                    ? (string) $record->official_code
                    : (string) $record->system_code,
                'credits' => (float) $record->credits,
            ],
            'progress' => [
                'filled_weeks' => $filledWeeks,
                'week_total' => max(16, $weeks->count()),
                'assessment_weight' => $assessmentWeight,
                'obe_percent' => $this->obePercent($versionId, (string) $record->status),
            ],
            'review' => [
                'status' => $latestReview?->status,
                'note' => $latestReview?->note,
                'reviewed_at' => $latestReview?->reviewed_at,
                'reviewer_name' => $latestReview?->reviewer_name,
                'outdated' => $reviewOutdated,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function stats(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'lecturers' => $rows->pluck('owner.id')->unique()->count(),
            'draft' => $rows->where('status', 'draft')->count(),
            'final' => $rows->where('status', 'final')->count(),
            'obe_valid' => $rows->filter(fn (array $row): bool => $row['progress']['obe_percent'] === 100)->count(),
            'approved' => $rows->filter(fn (array $row): bool => $row['review']['status'] === 'approved' && ! $row['review']['outdated'])->count(),
            'revision_required' => $rows->filter(fn (array $row): bool => $row['review']['status'] === 'revision_required' && ! $row['review']['outdated'])->count(),
            'outdated' => $rows->filter(fn (array $row): bool => (bool) $row['review']['outdated'])->count(),
            'unreviewed' => $rows->filter(fn (array $row): bool => $row['review']['status'] === null)->count(),
        ];
    }

    private function obePercent(?string $versionId, string $status): ?int
    {
        if (strtolower($status) === 'final') {
            return 100;
        }

        if (! filled($versionId)) {
            return null;
        }

        $validationRows = DB::table('obe_validation_results')
            ->where('rps_version_id', $versionId)
            ->get(['is_passed', 'severity', 'details']);

        $blockingRows = $validationRows->filter(function (object $row): bool {
            $details = json_decode((string) ($row->details ?? ''), true);
            $severity = is_array($details)
                ? (string) ($details['severity'] ?? $row->severity ?? 'required')
                : (string) ($row->severity ?? 'required');

            return strtolower($severity) !== 'advisory';
        });

        if ($blockingRows->isEmpty()) {
            return null;
        }

        $passed = $blockingRows
            ->filter(fn (object $row): bool => (bool) $row->is_passed)
            ->count();

        return (int) round(($passed / $blockingRows->count()) * 100);
    }

    /**
     * @return array{q:string, academic_year:string, academic_semester:string, status:string, review_status:string}
     */
    private function filters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $reviewStatus = trim((string) $request->query('review_status', ''));
        $academicSemester = trim((string) $request->query('academic_semester', ''));

        if (! in_array($status, ['', 'draft', 'obe_valid', 'final'], true)) {
            $status = '';
        }

        if (! in_array($reviewStatus, ['', 'unreviewed', 'revision_required', 'approved', 'outdated'], true)) {
            $reviewStatus = '';
        }

        if (! in_array($academicSemester, ['', 'Ganjil', 'Genap', 'Pendek'], true)) {
            $academicSemester = '';
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'academic_year' => trim((string) $request->query('academic_year', '')),
            'academic_semester' => $academicSemester,
            'status' => $status,
            'review_status' => $reviewStatus,
        ];
    }

    private function reviewLabel(mixed $status, bool $outdated): string
    {
        if ($outdated) {
            return 'Review Ulang';
        }

        return match ((string) $status) {
            'approved' => 'Disetujui',
            'revision_required' => 'Perlu Revisi',
            default => 'Belum Ditinjau',
        };
    }
}
