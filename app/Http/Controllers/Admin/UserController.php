<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'academic_title',
                'nidn',
                'email',
                'role',
                'is_active',
                'created_at',
            ])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'academic_title' => $user->academic_title,
                'nidn' => $user->nidn,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
                'rps_count' => DB::table('rps')->where('owner_id', $user->id)->count(),
            ]);

        return Inertia::render('admin/users', ['users' => $users]);
    }

    public function monitoring(User $user): Response
    {
        $this->assertLecturer($user);

        $rpsItems = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->leftJoin('rps_versions', 'rps_versions.id', '=', 'rps.current_version_id')
            ->where('rps.owner_id', $user->id)
            ->orderByDesc('rps.updated_at')
            ->get([
                'rps.id',
                'rps.status',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.updated_at',
                'rps.current_version_id',
                'courses.system_code',
                'courses.official_code',
                'courses.name as course_name',
                'courses.credits',
                'rps_versions.status as version_status',
                'rps_versions.version_no',
                'rps_versions.finalized_at',
            ])
            ->map(function (object $record): array {
                $versionId = filled($record->current_version_id ?? null)
                    ? (string) $record->current_version_id
                    : null;

                $weeks = $versionId
                    ? DB::table('rps_weekly_plans')
                        ->where('rps_version_id', $versionId)
                        ->orderBy('week_number')
                        ->get([
                            'week_number',
                            'is_exam',
                            'rps_sub_cpmk_id',
                            'material_text',
                            'learning_method',
                            'learning_activity',
                        ])
                    : collect();

                $readyWeeks = $weeks->filter(fn (object $week): bool =>
                    (bool) $week->is_exam
                    || (
                        filled($week->rps_sub_cpmk_id ?? null)
                        && filled($week->material_text ?? null)
                        && filled($week->learning_method ?? null)
                        && filled($week->learning_activity ?? null)
                    )
                )->count();

                $weekTotal = $weeks->count();
                $completionPercent = $weekTotal > 0
                    ? (int) round(($readyWeeks / $weekTotal) * 100)
                    : 0;

                $assessmentCount = $versionId
                    ? DB::table('assessments')->where('rps_version_id', $versionId)->count()
                    : 0;
                $assessmentWeight = $versionId
                    ? round((float) DB::table('assessments')
                        ->where('rps_version_id', $versionId)
                        ->sum('weight'), 2)
                    : 0.0;

                $validationRows = $versionId
                    ? DB::table('obe_validation_results')
                        ->where('rps_version_id', $versionId)
                        ->get(['is_passed', 'details', 'validated_at'])
                    : collect();

                $blockingValidationRows = $validationRows->filter(function (object $row): bool {
                    $details = json_decode((string) ($row->details ?? ''), true);
                    $severity = is_array($details)
                        ? (string) ($details['severity'] ?? 'required')
                        : 'required';

                    return $severity !== 'advisory';
                });

                $obePercent = null;
                if (strtolower((string) $record->status) === 'final') {
                    $obePercent = 100;
                } elseif ($blockingValidationRows->isNotEmpty()) {
                    $passed = $blockingValidationRows
                        ->filter(fn (object $row): bool => (bool) $row->is_passed)
                        ->count();
                    $obePercent = (int) round(($passed / $blockingValidationRows->count()) * 100);
                }

                return [
                    'id' => (string) $record->id,
                    'course_code' => filled($record->official_code ?? null)
                        ? (string) $record->official_code
                        : (string) $record->system_code,
                    'course_name' => (string) $record->course_name,
                    'credits' => (float) $record->credits,
                    'academic_year' => (string) $record->academic_year,
                    'academic_semester' => (string) $record->academic_semester,
                    'status' => (string) $record->status,
                    'version_status' => (string) ($record->version_status ?? 'draft'),
                    'version_no' => (float) ($record->version_no ?? 1),
                    'finalized_at' => $record->finalized_at,
                    'updated_at' => $record->updated_at,
                    'weeks_ready' => $readyWeeks,
                    'weeks_total' => $weekTotal,
                    'completion_percent' => $completionPercent,
                    'assessment_count' => $assessmentCount,
                    'assessment_weight_total' => $assessmentWeight,
                    'obe_percent' => $obePercent,
                    'obe_validated_at' => $validationRows->max('validated_at'),
                ];
            })
            ->values();

        return Inertia::render('admin/user-monitoring', [
            'lecturer' => [
                'id' => $user->id,
                'name' => $user->name,
                'academic_title' => $user->academic_title,
                'nidn' => $user->nidn,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
            ],
            'summary' => [
                'total' => $rpsItems->count(),
                'final' => $rpsItems->where('status', 'final')->count(),
                'draft' => $rpsItems->where('status', '!=', 'final')->count(),
                'obe_valid' => $rpsItems->where('obe_percent', 100)->count(),
            ],
            'rpsItems' => $rpsItems,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_title' => ['nullable', 'string', 'max:100'],
            'nidn' => ['nullable', 'string', 'max:50', 'unique:users,nidn'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User();
        $user->name = trim($validated['name']);
        $user->academic_title = filled($validated['academic_title'] ?? null)
            ? trim($validated['academic_title'])
            : null;
        $user->nidn = filled($validated['nidn'] ?? null)
            ? trim($validated['nidn'])
            : null;
        $user->email = strtolower(trim($validated['email']));
        $user->password = $validated['password'];
        $user->role = 'dosen';
        $user->is_active = true;
        $user->email_verified_at = Carbon::now();
        $user->save();

        return back()->with('success', 'Akun dosen berhasil dibuat dan sudah dapat digunakan untuk login.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_title' => ['nullable', 'string', 'max:100'],
            'nidn' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nidn')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->name = trim($validated['name']);
        $user->academic_title = filled($validated['academic_title'] ?? null)
            ? trim($validated['academic_title'])
            : null;
        $user->nidn = filled($validated['nidn'] ?? null)
            ? trim($validated['nidn'])
            : null;
        $user->email = strtolower(trim($validated['email']));
        $user->save();

        return back()->with('success', 'Data akun dosen berhasil diperbarui.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = (bool) $validated['is_active'];
        $user->save();

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with(
            'success',
            $user->is_active
                ? 'Akun dosen diaktifkan kembali.'
                : 'Akun dosen dinonaktifkan dan sesi login aktif dihentikan.'
        );
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->password = $validated['password'];
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('success', 'Password dosen berhasil direset. Semua sesi login lama telah dihentikan.');
    }

    private function assertLecturer(User $user): void
    {
        abort_unless($user->role === 'dosen', 403, 'Akun administrator tidak dapat diubah dari pengelolaan dosen.');
    }
}
