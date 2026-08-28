<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Rps\RpsDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function index(RpsDraftService $service): Response
    {
        $curriculum = $this->curriculum();

        $cpls = DB::table('cpls')
            ->where('curriculum_id', $curriculum->id)
            ->orderBy('sequence_no')
            ->get();

        $courses = DB::table('courses')
            ->where('curriculum_id', $curriculum->id)
            ->orderBy('semester_recommended')
            ->orderBy('name')
            ->get()
            ->map(function ($course) use ($service): array {
                $cplRows = DB::table('course_cpls')
                    ->join('cpls', 'cpls.id', '=', 'course_cpls.cpl_id')
                    ->where('course_cpls.course_id', $course->id)
                    ->orderBy('cpls.sequence_no')
                    ->get(['cpls.id', 'cpls.code']);

                $cpmks = DB::table('curriculum_cpmks')
                    ->where('course_id', $course->id)
                    ->orderBy('sequence_no')
                    ->get(['id', 'code', 'description', 'sequence_no', 'verification_status']);

                $rpsCount = DB::table('rps')->where('course_id', $course->id)->count();

                return [
                    'id' => $course->id,
                    'system_code' => $course->system_code,
                    'official_code' => $course->official_code,
                    'name' => $course->name,
                    'credits' => (float) $course->credits,
                    'semester_recommended' => $course->semester_recommended,
                    'is_mandatory' => (bool) $course->is_mandatory,
                    'has_practicum' => (bool) $course->has_practicum,
                    'is_active' => (bool) $course->is_active,
                    'code_status' => $course->code_status,
                    'verification_status' => $course->verification_status,
                    'prerequisite_note' => $course->prerequisite_note,
                    'cpl_ids' => $cplRows->pluck('id')->all(),
                    'cpl_codes' => $cplRows->pluck('code')->all(),
                    'cpmk_count' => $cpmks->count(),
                    'cpmks' => $cpmks,
                    'has_syllabus' => DB::table('course_syllabi')->where('course_id', $course->id)->exists(),
                    'readiness' => $service->readiness($course, $cpmks->count()),
                    'rps_count' => $rpsCount,
                ];
            });

        $issues = DB::table('curriculum_data_issues')
            ->where('curriculum_id', $curriculum->id)
            ->where('status', 'open')
            ->orderBy('severity')
            ->orderBy('issue_code')
            ->get();

        return Inertia::render('admin/curriculum', [
            'curriculum' => $curriculum,
            'summary' => [
                'cpl' => $cpls->count(),
                'kbk' => DB::table('kbks')->where('curriculum_id', $curriculum->id)->count(),
                'courses' => $courses->count(),
                'courseCpl' => DB::table('course_cpls')
                    ->join('courses', 'courses.id', '=', 'course_cpls.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'cpmk' => DB::table('curriculum_cpmks')
                    ->join('courses', 'courses.id', '=', 'curriculum_cpmks.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'syllabi' => DB::table('course_syllabi')
                    ->join('courses', 'courses.id', '=', 'course_syllabi.course_id')
                    ->where('courses.curriculum_id', $curriculum->id)
                    ->count(),
                'issues' => $issues->count(),
                'rpsUsingMaster' => DB::table('rps')->where('curriculum_id', $curriculum->id)->count(),
            ],
            'cpls' => $cpls,
            'courses' => $courses,
            'issues' => $issues,
        ]);
    }

    public function updateCurriculum(Request $request): RedirectResponse
    {
        $curriculum = $this->curriculum();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'effective_academic_year' => ['nullable', 'string', 'max:30'],
            'end_academic_year' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::table('curriculums')->where('id', $curriculum->id)->update([
            'name' => trim($validated['name']),
            'effective_academic_year' => filled($validated['effective_academic_year'] ?? null)
                ? trim($validated['effective_academic_year']) : null,
            'end_academic_year' => filled($validated['end_academic_year'] ?? null)
                ? trim($validated['end_academic_year']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Identitas master kurikulum berhasil diperbarui.');
    }

    public function updateCpl(Request $request, string $cpl): RedirectResponse
    {
        $curriculum = $this->curriculum();
        $row = DB::table('cpls')->where('id', $cpl)->where('curriculum_id', $curriculum->id)->first();
        abort_unless($row, 404);

        $validated = $request->validate([
            'description' => ['required', 'string'],
            'domain' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::table('cpls')->where('id', $row->id)->update([
            'description' => trim($validated['description']),
            'domain' => filled($validated['domain'] ?? null) ? trim($validated['domain']) : null,
            'is_active' => (bool) $validated['is_active'],
            'updated_at' => now(),
        ]);

        return back()->with('success', $row->code.' berhasil diperbarui. Perubahan deskripsi CPL akan terlihat pada RPS yang menggunakan CPL tersebut.');
    }

    public function updateCourse(Request $request, string $course): RedirectResponse
    {
        $row = $this->course($course);
        $validated = $request->validate([
            'official_code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'credits' => ['required', 'numeric', 'min:0.5', 'max:30'],
            'semester_recommended' => ['nullable', 'integer', 'min:1', 'max:14'],
            'is_mandatory' => ['required', 'boolean'],
            'has_practicum' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'code_status' => ['required', Rule::in(['official', 'internal'])],
            'verification_status' => ['required', Rule::in(['source_verified', 'needs_review'])],
            'prerequisite_note' => ['nullable', 'string'],
        ]);

        DB::table('courses')->where('id', $row->id)->update([
            'official_code' => filled($validated['official_code'] ?? null) ? trim($validated['official_code']) : null,
            'name' => trim($validated['name']),
            'credits' => $validated['credits'],
            'semester_recommended' => $validated['semester_recommended'] ?? null,
            'is_mandatory' => (bool) $validated['is_mandatory'],
            'has_practicum' => (bool) $validated['has_practicum'],
            'is_active' => (bool) $validated['is_active'],
            'code_status' => $validated['code_status'],
            'verification_status' => $validated['verification_status'],
            'prerequisite_note' => filled($validated['prerequisite_note'] ?? null)
                ? trim($validated['prerequisite_note']) : null,
            'updated_at' => now(),
        ]);

        $rpsCount = DB::table('rps')->where('course_id', $row->id)->count();
        $suffix = $rpsCount > 0 ? " Metadata ini juga akan terlihat pada {$rpsCount} RPS yang sudah memakai mata kuliah ini." : '';

        return back()->with('success', 'Master mata kuliah berhasil diperbarui.'.$suffix);
    }

    public function updateCourseCpls(Request $request, string $course): RedirectResponse
    {
        $row = $this->course($course);
        $validated = $request->validate([
            'cpl_ids' => ['present', 'array'],
            'cpl_ids.*' => ['uuid'],
            'acknowledge_active_rps' => ['nullable', 'boolean'],
        ]);

        $cplIds = collect($validated['cpl_ids'] ?? [])->map(fn ($id) => (string) $id)->unique()->values();
        $validIds = DB::table('cpls')
            ->where('curriculum_id', $row->curriculum_id)
            ->whereIn('id', $cplIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($validIds->count() !== $cplIds->count()) {
            throw ValidationException::withMessages(['cpl_ids' => 'Terdapat CPL yang tidak termasuk dalam kurikulum aktif.']);
        }

        $rpsCount = DB::table('rps')->where('course_id', $row->id)->count();
        if ($rpsCount > 0 && ! (bool) ($validated['acknowledge_active_rps'] ?? false)) {
            throw ValidationException::withMessages([
                'cpl_ids' => "Mata kuliah ini sudah digunakan pada {$rpsCount} RPS. Konfirmasi dampak ke RPS aktif sebelum mengubah pemetaan CPL.",
            ]);
        }

        DB::transaction(function () use ($row, $validIds): void {
            DB::table('course_cpls')->where('course_id', $row->id)->delete();
            $now = now();
            foreach ($validIds as $cplId) {
                DB::table('course_cpls')->insert([
                    'id' => (string) Str::uuid(),
                    'course_id' => $row->id,
                    'cpl_id' => $cplId,
                    'contribution_level' => 'supporting',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return back()->with('success', 'Pemetaan mata kuliah ke CPL berhasil diperbarui. Validator RPS akan mengikuti master CPL terbaru.');
    }

    public function storeCpmk(Request $request, string $course): RedirectResponse
    {
        $row = $this->course($course);
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('curriculum_cpmks', 'code')->where(fn ($query) => $query->where('course_id', $row->id)),
            ],
            'description' => ['required', 'string'],
            'sequence_no' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        DB::table('curriculum_cpmks')->insert([
            'id' => (string) Str::uuid(),
            'course_id' => $row->id,
            'code' => trim($validated['code']),
            'description' => trim($validated['description']),
            'sequence_no' => (int) $validated['sequence_no'],
            'verification_status' => 'source_verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CPMK master berhasil ditambahkan. CPMK baru akan digunakan pada RPS yang dibuat atau diimpor setelah perubahan ini.');
    }

    public function updateCpmk(Request $request, string $course, string $cpmk): RedirectResponse
    {
        $row = $this->course($course);
        $master = DB::table('curriculum_cpmks')->where('id', $cpmk)->where('course_id', $row->id)->first();
        abort_unless($master, 404);

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('curriculum_cpmks', 'code')
                    ->where(fn ($query) => $query->where('course_id', $row->id))
                    ->ignore($master->id),
            ],
            'description' => ['required', 'string'],
            'sequence_no' => ['required', 'integer', 'min:1', 'max:99'],
            'verification_status' => ['required', Rule::in(['source_verified', 'needs_review'])],
        ]);

        DB::table('curriculum_cpmks')->where('id', $master->id)->update([
            'code' => trim($validated['code']),
            'description' => trim($validated['description']),
            'sequence_no' => (int) $validated['sequence_no'],
            'verification_status' => $validated['verification_status'],
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CPMK master berhasil diperbarui. RPS yang sudah dibuat tidak ditimpa otomatis.');
    }

    public function destroyCpmk(string $course, string $cpmk): RedirectResponse
    {
        $row = $this->course($course);
        $master = DB::table('curriculum_cpmks')->where('id', $cpmk)->where('course_id', $row->id)->first();
        abort_unless($master, 404);

        DB::table('curriculum_cpmks')->where('id', $master->id)->delete();

        return back()->with('success', 'CPMK dihapus dari master kurikulum. RPS yang sudah memiliki salinan CPMK tetap dipertahankan.');
    }

    private function curriculum(): object
    {
        $curriculum = DB::table('curriculums')->where('code', 'KUR-MAT-2025')->first();
        abort_unless($curriculum, 404);

        return $curriculum;
    }

    private function course(string $course): object
    {
        $curriculum = $this->curriculum();
        $row = DB::table('courses')->where('id', $course)->where('curriculum_id', $curriculum->id)->first();
        abort_unless($row, 404);

        return $row;
    }
}
