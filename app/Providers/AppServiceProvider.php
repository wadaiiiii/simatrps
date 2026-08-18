<?php

namespace App\Providers;

use App\Services\Rps\RpsAssessmentSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            RpsAssessmentSyncService::class,
            StrictRpsAssessmentSyncService::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

final class StrictRpsAssessmentSyncService extends RpsAssessmentSyncService
{
    private const RTM_ASSESSMENT_TYPES = [
        'assignment',
        'project',
        'practicum',
        'presentation',
    ];

    public function syncVersion(string $versionId): array
    {
        $result = parent::syncVersion($versionId);
        $result['rtm_scope_fixes'] = $this->enforceExactTaskScopes($versionId);

        return $result;
    }

    public function repairGeneratedArtifacts(string $versionId): array
    {
        $result = parent::repairGeneratedArtifacts($versionId);
        $result['rtm_scope_fixes'] = $this->enforceExactTaskScopes($versionId);

        return $result;
    }

    public function taskAlignment(string $versionId): array
    {
        $result = parent::taskAlignment($versionId);
        $issues = $this->exactScopeIssues($versionId);

        if ($issues === []) {
            return $result;
        }

        $problemIds = collect($result['problem_task_ids'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();
        $mappingIssues = collect($result['mapping_mismatches'] ?? []);
        $additional = 0;

        foreach ($issues as $issue) {
            $taskId = (string) ($issue['id'] ?? '');
            if ($taskId === '' || $problemIds->contains($taskId)) {
                continue;
            }

            $mappingIssues->push($issue);
            $problemIds->push($taskId);
            $additional++;
        }

        if ($additional === 0) {
            return $result;
        }

        $result['mapping_mismatch_count'] = (int) ($result['mapping_mismatch_count'] ?? 0) + $additional;
        $result['mapping_mismatches'] = $mappingIssues->values()->all();
        $result['problem_task_ids'] = $problemIds->unique()->values()->all();
        $result['is_aligned'] = false;

        return $result;
    }

    private function enforceExactTaskScopes(string $versionId): int
    {
        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', self::RTM_ASSESSMENT_TYPES)
            ->get(['id']);

        if ($assessments->isEmpty()) {
            return 0;
        }

        $assessmentIds = $assessments
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();
        $assessmentLinks = DB::table('assessment_subcpmks')
            ->whereIn('assessment_id', $assessmentIds->all())
            ->get(['assessment_id', 'rps_sub_cpmk_id'])
            ->groupBy(fn ($row) => (string) $row->assessment_id);
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereIn('assessment_id', $assessmentIds->all())
            ->get(['id', 'assessment_id']);

        if ($tasks->isEmpty()) {
            return 0;
        }

        $taskLinks = DB::table('rps_task_subcpmks')
            ->whereIn('rps_task_id', $tasks->pluck('id')->all())
            ->get(['rps_task_id', 'rps_sub_cpmk_id'])
            ->groupBy(fn ($row) => (string) $row->rps_task_id);
        $fixed = 0;

        DB::transaction(function () use ($tasks, $assessmentLinks, $taskLinks, &$fixed): void {
            foreach ($tasks as $task) {
                $expected = collect($assessmentLinks->get((string) $task->assessment_id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($expected->isEmpty()) {
                    continue;
                }

                $actual = collect($taskLinks->get((string) $task->id, []))
                    ->pluck('rps_sub_cpmk_id')
                    ->map(fn ($id) => (string) $id)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($actual->all() === $expected->all()) {
                    continue;
                }

                DB::table('rps_task_subcpmks')
                    ->where('rps_task_id', $task->id)
                    ->delete();

                foreach ($expected as $subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $task->id,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('rps_tasks')
                    ->where('id', $task->id)
                    ->update(['updated_at' => now()]);

                $fixed++;
            }
        });

        return $fixed;
    }

    private function exactScopeIssues(string $versionId): array
    {
        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->whereIn('type', self::RTM_ASSESSMENT_TYPES)
            ->get(['id', 'code', 'name'])
            ->keyBy(fn ($row) => (string) $row->id);

        if ($assessments->isEmpty()) {
            return [];
        }

        $assessmentLinks = DB::table('assessment_subcpmks')
            ->whereIn('assessment_id', $assessments->keys()->all())
            ->get(['assessment_id', 'rps_sub_cpmk_id'])
            ->groupBy(fn ($row) => (string) $row->assessment_id);
        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->whereIn('assessment_id', $assessments->keys()->all())
            ->get(['id', 'code', 'title', 'assessment_id', 'due_week']);

        if ($tasks->isEmpty()) {
            return [];
        }

        $taskLinks = DB::table('rps_task_subcpmks')
            ->whereIn('rps_task_id', $tasks->pluck('id')->all())
            ->get(['rps_task_id', 'rps_sub_cpmk_id'])
            ->groupBy(fn ($row) => (string) $row->rps_task_id);
        $issues = [];

        foreach ($tasks as $task) {
            $assessmentId = (string) $task->assessment_id;
            $assessment = $assessments->get($assessmentId);
            $expected = collect($assessmentLinks->get($assessmentId, []))
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            if (! $assessment || $expected->isEmpty()) {
                continue;
            }

            $actual = collect($taskLinks->get((string) $task->id, []))
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            if ($actual->all() === $expected->all()) {
                continue;
            }

            $issues[] = [
                'id' => (string) $task->id,
                'code' => trim((string) ($task->code ?? 'RTM')),
                'title' => trim((string) ($task->title ?? '')),
                'week' => filled($task->due_week ?? null) ? (int) $task->due_week : null,
                'assessment_id' => $assessmentId,
                'assessment_code' => trim((string) ($assessment->code ?? 'Asesmen')),
                'assessment_name' => trim((string) ($assessment->name ?? '')),
                'reason' => 'Cakupan Sub-CPMK RTM harus sama persis dengan Detail Asesmen induk.',
            ];
        }

        return $issues;
    }
}
