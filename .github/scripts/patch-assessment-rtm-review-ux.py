from pathlib import Path
import re


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one literal match, got {count}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')


def regex_once(path: str, pattern: str, replacement: str, flags: int = 0) -> None:
    p = Path(path)
    text = p.read_text(encoding='utf-8')
    matches = list(re.finditer(pattern, text, flags))
    if len(matches) != 1:
        raise SystemExit(f'{path}: expected exactly one regex match, got {len(matches)} for {pattern!r}')
    text = re.sub(pattern, lambda _: replacement, text, count=1, flags=flags)
    p.write_text(text, encoding='utf-8')


# ---------------------------------------------------------------------------
# 1. RTM manual: infer assessment, adopt generated placeholder, never duplicate.
# ---------------------------------------------------------------------------
task_controller = 'app/Http/Controllers/RpsTaskController.php'

new_store = r'''    public function store(Request $request, string $rps, RpsAssessmentSyncService $sync): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);

        $validated = $request->validate([
            'assessment_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in([
                'assignment', 'project', 'practicum', 'presentation', 'other',
            ])],
            'purpose' => ['nullable', 'string', 'max:3000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'expected_output' => ['nullable', 'string', 'max:3000'],
            'due_week' => ['nullable', 'integer', 'min:1', 'max:16'],
            'sub_cpmk_ids' => ['nullable', 'array'],
            'sub_cpmk_ids.*' => ['uuid'],
        ]);

        if (empty($validated['assessment_id'])) {
            $validated['assessment_id'] = $this->inferAssessmentId($validated, $version->id);
        }

        if ($validated['assessment_id'] ?? null) {
            $validated = $this->applyAssessmentDefaults($validated, $version->id);
        }

        // Bila sinkronisasi asesmen sudah membuat RTM minimum tetapi browser
        // masih menampilkan state lama, simpan manual harus MEMPERBARUI placeholder
        // tersebut, bukan menambah RTM kedua untuk asesmen yang sama.
        $placeholder = $this->generatedPlaceholder(
            $version->id,
            $validated['assessment_id'] ?? null
        );

        $id = $placeholder?->id ?: (string) Str::uuid();
        $code = $placeholder?->code ?: $this->nextTaskCode($version->id);

        DB::transaction(function () use ($id, $code, $placeholder, $version, $validated, $request): void {
            $values = [
                'assessment_id' => ($validated['assessment_id'] ?? null) ?: null,
                'title' => $validated['title'],
                'type' => $validated['type'],
                'purpose' => ($validated['purpose'] ?? null) ?: null,
                'instructions' => ($validated['instructions'] ?? null) ?: null,
                'expected_output' => ($validated['expected_output'] ?? null) ?: null,
                'due_week' => ($validated['due_week'] ?? null) ?: null,
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
                'updated_at' => now(),
            ];

            if ($placeholder) {
                DB::table('rps_tasks')
                    ->where('id', $id)
                    ->where('rps_version_id', $version->id)
                    ->update($values);
            } else {
                DB::table('rps_tasks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    ...$values,
                    'created_at' => now(),
                ]);
            }

            $this->syncTaskSubCpmks(
                $id,
                $version->id,
                $validated['sub_cpmk_ids'] ?? []
            );
        });

        $sync->syncVersion($version->id);

        return back()->with(
            'success',
            $placeholder
                ? 'RTM berhasil disimpan. RTM otomatis untuk asesmen yang sama diperbarui menjadi RTM manual sehingga tidak terbentuk duplikat.'
                : 'RTM berhasil ditambahkan. Satu RTM dapat mengukur satu atau lebih Sub-CPMK dalam cakupan asesmen induk; urutan tampilan mengikuti pekan pengumpulan.'
        );
    }
'''

regex_once(
    task_controller,
    r'    public function store\(Request \$request, string \$rps, RpsAssessmentSyncService \$sync\): RedirectResponse\n    \{.*?\n    \}\n\n\n    public function update',
    new_store + '\n    public function update',
    re.S,
)

replace_once(
    task_controller,
    """        if ($validated['assessment_id'] ?? null) {\n            $validated = $this->applyAssessmentDefaults($validated, $version->id);\n        }\n\n        $allowedSubIds = DB::table('rps_sub_cpmks')""",
    """        if (empty($validated['assessment_id'])) {\n            $validated['assessment_id'] = $this->inferAssessmentId($validated, $version->id);\n        }\n\n        if ($validated['assessment_id'] ?? null) {\n            $validated = $this->applyAssessmentDefaults($validated, $version->id);\n        }\n\n        $allowedSubIds = DB::table('rps_sub_cpmks')""",
)

# Add helpers before applyAssessmentDefaults.
helpers = r'''    private function inferAssessmentId(array $validated, string $versionId): ?string
    {
        $title = $this->normalizeLabel((string) ($validated['title'] ?? ''));
        $type = strtolower(trim((string) ($validated['type'] ?? '')));

        if ($title === '' || ! in_array($type, ['assignment', 'project', 'practicum', 'presentation'], true)) {
            return null;
        }

        $requestedSubIds = collect($validated['sub_cpmk_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $candidates = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->where('type', $type)
            ->get(['id', 'name'])
            ->filter(function ($assessment) use ($title, $requestedSubIds): bool {
                if ($this->normalizeLabel((string) $assessment->name) !== $title) {
                    return false;
                }

                if ($requestedSubIds->isEmpty()) {
                    return true;
                }

                $assessmentSubIds = DB::table('assessment_subcpmks')
                    ->where('assessment_id', $assessment->id)
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id);

                return $requestedSubIds->diff($assessmentSubIds)->isEmpty();
            })
            ->values();

        return $candidates->count() === 1
            ? (string) $candidates->first()->id
            : null;
    }

    private function generatedPlaceholder(string $versionId, mixed $assessmentId): ?object
    {
        if (! filled($assessmentId)) {
            return null;
        }

        return DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->where('assessment_id', (string) $assessmentId)
            ->where('source_type', 'assessment_sync')
            ->orderBy('created_at')
            ->first(['id', 'code']);
    }

    private function nextTaskCode(string $versionId): string
    {
        $next = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                return preg_match('/RTM-(\\d+)/i', (string) $code, $match) === 1
                    ? (int) $match[1]
                    : 0;
            })
            ->max() + 1;

        return 'RTM-'.str_pad((string) max(1, $next), 2, '0', STR_PAD_LEFT);
    }

    private function syncTaskSubCpmks(string $taskId, string $versionId, array $subIds): void
    {
        $allowed = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        DB::table('rps_task_subcpmks')
            ->where('rps_task_id', $taskId)
            ->delete();

        foreach (array_unique(array_map('strval', $subIds)) as $subId) {
            if (! in_array($subId, $allowed, true)) {
                continue;
            }

            DB::table('rps_task_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_task_id' => $taskId,
                'rps_sub_cpmk_id' => $subId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
    }

'''
replace_once(
    task_controller,
    '    private function applyAssessmentDefaults(array $validated, string $versionId): array\n',
    helpers + '    private function applyAssessmentDefaults(array $validated, string $versionId): array\n',
)

# ---------------------------------------------------------------------------
# 2. Existing production duplicates: conservative one-time repair.
# ---------------------------------------------------------------------------
migration = r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rps_tasks', 'assessments', 'assessment_subcpmks', 'rps_task_subcpmks'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/[^\\pL\\pN]+/u', ' ', $value) ?? $value;

            return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
        };

        $manualTasks = DB::table('rps_tasks')
            ->whereNull('assessment_id')
            ->where('source_type', 'manual')
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation'])
            ->get(['id', 'rps_version_id', 'title', 'type']);

        foreach ($manualTasks as $task) {
            $title = $normalize((string) $task->title);
            if ($title === '') {
                continue;
            }

            $candidates = DB::table('assessments')
                ->where('rps_version_id', $task->rps_version_id)
                ->where('type', $task->type)
                ->get(['id', 'name'])
                ->filter(fn ($assessment) => $normalize((string) $assessment->name) === $title)
                ->values();

            if ($candidates->count() !== 1) {
                continue;
            }

            $assessment = $candidates->first();
            $taskSubIds = DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $task->id)
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id);
            $assessmentSubIds = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id);

            if ($taskSubIds->isNotEmpty() && $taskSubIds->diff($assessmentSubIds)->isNotEmpty()) {
                continue;
            }

            DB::transaction(function () use ($task, $assessment): void {
                DB::table('rps_tasks')
                    ->where('id', $task->id)
                    ->update([
                        'assessment_id' => $assessment->id,
                        'updated_at' => now(),
                    ]);

                $generatedIds = DB::table('rps_tasks')
                    ->where('rps_version_id', $task->rps_version_id)
                    ->where('assessment_id', $assessment->id)
                    ->where('source_type', 'assessment_sync')
                    ->where('id', '!=', $task->id)
                    ->pluck('id')
                    ->all();

                if ($generatedIds === []) {
                    return;
                }

                DB::table('rps_task_subcpmks')
                    ->whereIn('rps_task_id', $generatedIds)
                    ->delete();
                DB::table('rps_tasks')
                    ->whereIn('id', $generatedIds)
                    ->delete();
            });
        }
    }

    public function down(): void
    {
        // Data repair is intentionally irreversible: manual RTM content is preserved.
    }
};
'''
Path('database/migrations/2026_08_20_080000_repair_manual_rtm_assessment_duplicates.php').write_text(migration, encoding='utf-8')

# ---------------------------------------------------------------------------
# 3. Lecturer editor: review feedback + RTM display sorted by due week.
# ---------------------------------------------------------------------------
rps_controller = 'app/Http/Controllers/RpsController.php'
review_backend = r'''        $latestReview = Schema::hasTable('rps_reviews')
            ? DB::table('rps_reviews')
                ->join('users as reviewers', 'reviewers.id', '=', 'rps_reviews.reviewer_id')
                ->where('rps_reviews.rps_version_id', $version->id)
                ->orderByDesc('rps_reviews.reviewed_at')
                ->first([
                    'rps_reviews.status',
                    'rps_reviews.note',
                    'rps_reviews.reviewed_at',
                    'reviewers.name as reviewer_name',
                ])
            : null;

        $lecturerReview = [
            'status' => $latestReview?->status,
            'note' => $latestReview?->note,
            'reviewed_at' => $latestReview?->reviewed_at,
            'reviewer_name' => $latestReview?->reviewer_name,
            'outdated' => $latestReview !== null
                && filled($record->updated_at ?? null)
                && filled($latestReview->reviewed_at ?? null)
                && \Illuminate\Support\Carbon::parse($record->updated_at)
                    ->greaterThan(\Illuminate\Support\Carbon::parse($latestReview->reviewed_at)),
        ];

'''
replace_once(
    rps_controller,
    """        $assessmentSync->repairGeneratedArtifacts($version->id);\n\n        $allCpls = DB::table('cpls')""",
    """        $assessmentSync->repairGeneratedArtifacts($version->id);\n\n""" + review_backend + """        $allCpls = DB::table('cpls')""",
)

replace_once(
    rps_controller,
    """        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderBy('code')""",
    """        $tasks = Schema::hasTable('rps_tasks')\n            ? DB::table('rps_tasks')\n                ->where('rps_version_id', $version->id)\n                ->orderByRaw('COALESCE(due_week, 99)')\n                ->orderBy('code')""",
)

replace_once(
    rps_controller,
    """            'assessments' => $assessments,\n            'tasks' => $tasks,\n            'simulationScores' => $simulationScores,""",
    """            'assessments' => $assessments,\n            'tasks' => $tasks,\n            'lecturerReview' => $lecturerReview,\n            'simulationScores' => $simulationScores,""",
)

# ---------------------------------------------------------------------------
# 4. Lecturer editor UI + assessment AI precision.
# ---------------------------------------------------------------------------
show = 'resources/js/pages/rps/show.tsx'
replace_once(
    show,
    """        aiSuggestions = [],\n    } = props;""",
    """        aiSuggestions = [],\n        lecturerReview = null,\n    } = props;""",
)

review_banner = r'''                {lecturerReview?.status && (
                    <div className={`mb-4 rounded-2xl border px-4 py-3 print:hidden ${
                        lecturerReview.outdated
                            ? 'border-amber-200 bg-amber-50 text-amber-950'
                            : lecturerReview.status === 'revision_required'
                              ? 'border-rose-200 bg-rose-50 text-rose-950'
                              : 'border-emerald-200 bg-emerald-50 text-emerald-950'
                    }`}>
                        <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs font-black uppercase tracking-[0.12em]">
                                        {lecturerReview.outdated
                                            ? 'Review Ulang'
                                            : lecturerReview.status === 'revision_required'
                                              ? 'Perlu Revisi'
                                              : 'Disetujui'}
                                    </span>
                                    <span className="text-xs font-semibold opacity-70">
                                        Hasil review oleh {safeText(lecturerReview.reviewer_name, 'Admin')}
                                    </span>
                                </div>
                                {lecturerReview.note && (
                                    <p className="mt-1.5 text-sm font-semibold leading-6">{lecturerReview.note}</p>
                                )}
                                {lecturerReview.outdated && (
                                    <p className="mt-1.5 text-xs leading-5 opacity-80">
                                        RPS telah berubah setelah review terakhir. Catatan sebelumnya tetap ditampilkan sebagai acuan dan status kini menunggu review ulang.
                                    </p>
                                )}
                            </div>
                            {lecturerReview.reviewed_at && (
                                <div className="shrink-0 text-xs font-semibold opacity-60">
                                    {new Date(lecturerReview.reviewed_at).toLocaleString('id-ID')}
                                </div>
                            )}
                        </div>
                    </div>
                )}

'''
replace_once(
    show,
    '                {/* AI compact toolbar */}\n',
    review_banner + '                {/* AI compact toolbar */}\n',
)

# Assessment header becomes full-width, so recommendations no longer occupy a narrow right column.
regex_once(
    show,
    r'<div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">\s*(<div id="validator-target-assessment")',
    '<div className="space-y-3">\n                            \\1',
    re.S,
)
# The replacement above deliberately used a backreference marker; resolve it literally.
replace_once(show, '\\1', '<div id="validator-target-assessment"')

replace_once(
    show,
    '                <details className="mt-2 rounded-xl border border-emerald-100 bg-emerald-50/30 p-2">',
    '                <details className="mt-3 w-full rounded-2xl border border-emerald-100 bg-emerald-50/30 p-3">',
)

replace_once(
    show,
    """                                    ['Dipilih sekarang', selectedAssessmentWeight, assessmentProjectionReady ? 'Siap menjadi 100%' : 'Bisa diubah sebelum terapkan'],""",
    """                                    ['Total setelah dipilih', projectedAssessmentWeight, assessmentProjectionReady ? 'Siap diterapkan' : projectedAssessmentWeight > 100 ? 'Kurangi pilihan' : 'Belum mencapai 100%'],""",
)

replace_once(
    show,
    """                    {meta.fallback_used && (\n                        <div className=\"mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-700\">\n                            Provider utama gagal; SiMatRPS memakai backup AI.\n                            {meta.primary_error ? ` Penyebab: ${safeText(meta.primary_error)}` : ''}\n                        </div>\n                    )}""",
    """                    {meta.fallback_used && (\n                        <div className=\"mt-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-700\">\n                            AI utama tidak merespons. Rekomendasi ini sudah diselesaikan menggunakan jalur cadangan SiMatRPS.\n                        </div>\n                    )}""",
)

replace_once(
    show,
    '                        className="rounded-xl bg-teal-700 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"',
    '                        className="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-40"',
)
replace_once(
    show,
    '                                <label key={index} className={`relative flex items-start gap-3 rounded-xl border p-3.5 transition ${actionable ? \'cursor-pointer\' : \'cursor-default\'} ${selected ? \'border-teal-300 bg-teal-50/70 shadow-sm ring-1 ring-teal-100\' : action === \'keep\' ? \'border-slate-200 bg-slate-50/70\' : \'border-slate-200 bg-white hover:border-teal-200\'}`}>',
    '                                <label key={index} className={`relative flex items-start gap-3 rounded-xl border p-4 transition ${actionable ? \'cursor-pointer\' : \'cursor-default\'} ${selected ? \'border-teal-300 bg-teal-50/70 shadow-sm ring-1 ring-teal-100\' : action === \'keep\' ? \'border-slate-200 bg-slate-50/70\' : \'border-slate-200 bg-white hover:border-teal-200\'}`}>',
)

# ---------------------------------------------------------------------------
# 5. Admin review: full-width reading area, decision panel below content.
# ---------------------------------------------------------------------------
admin_review = 'resources/js/pages/admin/rps-review.tsx'
replace_once(
    admin_review,
    '                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">',
    '                <div className="space-y-6">',
)
replace_once(
    admin_review,
    '                    <aside className="space-y-5 xl:sticky xl:top-6 xl:self-start">',
    '                    <aside className="space-y-5">',
)

# ---------------------------------------------------------------------------
# 6. Feature coverage for duplicate prevention, ordering, and lecturer feedback.
# ---------------------------------------------------------------------------
rtm_test = r'''<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createRpsTaskSyncFixture(User $lecturer): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $sub1 = (string) Str::uuid();
    $sub2 = (string) Str::uuid();
    $timestamp = now()->subMinute();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-RTM-'.Str::lower(Str::random(6)),
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-RTM-'.Str::lower(Str::random(6)),
        'name' => 'Kurikulum RTM',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'SYS-RTM-'.Str::lower(Str::random(6)),
        'official_code' => 'MAT-RTM',
        'name' => 'Algoritma dan Dasar Pemrograman',
        'credits' => 3,
        'semester_recommended' => 2,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->insert([
        'id' => $rpsId,
        'curriculum_id' => $curriculumId,
        'course_id' => $courseId,
        'owner_id' => $lecturer->id,
        'academic_year' => '2026/2027',
        'academic_semester' => 'Ganjil',
        'status' => 'draft',
        'current_version_id' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_versions')->insert([
        'id' => $versionId,
        'rps_id' => $rpsId,
        'version_no' => 1,
        'status' => 'draft',
        'created_by' => $lecturer->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->where('id', $rpsId)->update([
        'current_version_id' => $versionId,
        'updated_at' => $timestamp,
    ]);

    foreach ([[$sub1, 'Sub-CPMK-1', 1], [$sub2, 'Sub-CPMK-2', 2]] as [$id, $code, $sequence]) {
        DB::table('rps_sub_cpmks')->insert([
            'id' => $id,
            'rps_version_id' => $versionId,
            'code' => $code,
            'description' => "Rumusan {$code}",
            'bloom_level' => 'C3',
            'sequence_no' => $sequence,
            'source_type' => 'manual',
            'created_by' => $lecturer->id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    return compact('rpsId', 'versionId', 'sub1', 'sub2', 'timestamp');
}

function insertAssessmentForRtm(array $fixture, User $lecturer, string $name, int $week, float $weight, string $subId): string
{
    $assessmentId = (string) Str::uuid();

    DB::table('assessments')->insert([
        'id' => $assessmentId,
        'rps_version_id' => $fixture['versionId'],
        'code' => 'ASM-'.Str::upper(Str::random(5)),
        'name' => $name,
        'type' => 'assignment',
        'week_number' => $week,
        'description' => $name,
        'weight' => $weight,
        'source_type' => 'manual',
        'created_by' => $lecturer->id,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    DB::table('assessment_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'assessment_id' => $assessmentId,
        'rps_sub_cpmk_id' => $subId,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    return $assessmentId;
}

test('manual RTM adopts generated placeholder instead of creating a duplicate', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createRpsTaskSyncFixture($lecturer);
    $assessmentId = insertAssessmentForRtm($fixture, $lecturer, 'Tugas Terstruktur 2', 4, 10, $fixture['sub2']);
    $generatedId = (string) Str::uuid();

    DB::table('rps_tasks')->insert([
        'id' => $generatedId,
        'rps_version_id' => $fixture['versionId'],
        'assessment_id' => $assessmentId,
        'code' => 'RTM-01',
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Mengukur ketercapaian Sub-CPMK melalui Tugas Terstruktur 2.',
        'instructions' => 'Kerjakan Tugas Terstruktur 2 sesuai arahan dosen.',
        'expected_output' => 'Luaran Tugas Terstruktur 2 sesuai ketentuan asesmen.',
        'due_week' => 4,
        'source_type' => 'assessment_sync',
        'created_by' => $lecturer->id,
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    DB::table('rps_task_subcpmks')->insert([
        'id' => (string) Str::uuid(),
        'rps_task_id' => $generatedId,
        'rps_sub_cpmk_id' => $fixture['sub2'],
        'created_at' => $fixture['timestamp'],
        'updated_at' => $fixture['timestamp'],
    ]);

    $response = $this->actingAs($lecturer)->post(route('rps.tasks.store', $fixture['rpsId']), [
        'assessment_id' => '',
        'title' => 'Tugas Terstruktur 2',
        'type' => 'assignment',
        'purpose' => 'Tujuan RTM manual untuk Sub-CPMK-2.',
        'instructions' => 'Kerjakan tugas sesuai instruksi manual.',
        'expected_output' => 'Laporan tugas terstruktur.',
        'due_week' => 4,
        'sub_cpmk_ids' => [$fixture['sub2']],
    ]);

    $response->assertSessionHasNoErrors();

    $tasks = DB::table('rps_tasks')
        ->where('rps_version_id', $fixture['versionId'])
        ->where('assessment_id', $assessmentId)
        ->get();

    expect($tasks)->toHaveCount(1)
        ->and((string) $tasks->first()->id)->toBe($generatedId)
        ->and($tasks->first()->source_type)->toBe('manual')
        ->and($tasks->first()->purpose)->toBe('Tujuan RTM manual untuk Sub-CPMK-2.')
        ->and(DB::table('rps_task_subcpmks')->where('rps_task_id', $generatedId)->count())->toBe(1);
});

test('RTM cards are displayed by due week instead of creation code', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createRpsTaskSyncFixture($lecturer);

    foreach ([
        ['RTM-01', 'RTM pekan akhir', 15],
        ['RTM-02', 'RTM tambahan pekan 4', 4],
    ] as [$code, $title, $week]) {
        DB::table('rps_tasks')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $fixture['versionId'],
            'assessment_id' => null,
            'code' => $code,
            'title' => $title,
            'type' => 'assignment',
            'due_week' => $week,
            'source_type' => 'manual',
            'created_by' => $lecturer->id,
            'created_at' => $fixture['timestamp'],
            'updated_at' => $fixture['timestamp'],
        ]);
    }

    $this->actingAs($lecturer)
        ->get(route('rps.show', $fixture['rpsId']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.due_week', 4)
            ->where('tasks.0.title', 'RTM tambahan pekan 4')
            ->where('tasks.1.due_week', 15)
        );
});
'''
Path('tests/Feature/RpsTaskSyncTest.php').write_text(rtm_test, encoding='utf-8')

# Add lecturer editor review feedback test to existing review tests.
review_test = 'tests/Feature/Admin/RpsReviewTest.php'
editor_review_test = r'''

test('lecturer RPS editor exposes the latest review feedback', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $lecturer = User::factory()->create([
        'role' => 'dosen',
        'is_active' => true,
    ]);
    $fixture = createRpsForAdminReview($lecturer);

    $this->actingAs($admin)
        ->post(route('admin.rps.review.store', $fixture['rps_id']), [
            'status' => 'revision_required',
            'note' => 'Pastikan RTM sesuai dengan Sub-CPMK.',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($lecturer)
        ->get(route('rps.show', $fixture['rps_id']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lecturerReview.status', 'revision_required')
            ->where('lecturerReview.note', 'Pastikan RTM sesuai dengan Sub-CPMK.')
            ->where('lecturerReview.reviewer_name', $admin->name)
            ->where('lecturerReview.outdated', false)
        );
});
'''
Path(review_test).write_text(Path(review_test).read_text(encoding='utf-8').rstrip() + editor_review_test + '\n', encoding='utf-8')

# Extend explicit CI coverage.
tests_workflow = '.github/workflows/tests.yml'
replace_once(
    tests_workflow,
    """      - name: RPS recap feature tests\n        run: php artisan test tests/Feature/Admin/RpsReportTest.php\n""",
    """      - name: RPS recap feature tests\n        run: php artisan test tests/Feature/Admin/RpsReportTest.php\n\n      - name: RTM sync PHP syntax\n        run: |\n          php -l app/Http/Controllers/RpsTaskController.php\n          php -l app/Http/Controllers/RpsController.php\n          php -l database/migrations/2026_08_20_080000_repair_manual_rtm_assessment_duplicates.php\n          php -l tests/Feature/RpsTaskSyncTest.php\n\n      - name: RTM sync PHP formatting\n        run: vendor/bin/pint --test app/Http/Controllers/RpsTaskController.php app/Http/Controllers/RpsController.php database/migrations/2026_08_20_080000_repair_manual_rtm_assessment_duplicates.php tests/Feature/RpsTaskSyncTest.php tests/Feature/Admin/RpsReviewTest.php\n\n      - name: RTM sync static analysis\n        run: vendor/bin/phpstan analyse app/Http/Controllers/RpsTaskController.php --no-progress\n\n      - name: RTM sync feature tests\n        run: php artisan test tests/Feature/RpsTaskSyncTest.php tests/Feature/Admin/RpsReviewTest.php\n""",
)
