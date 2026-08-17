<?php

namespace App\Services\Rps;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ObeWorkspaceService
{
    public function progress(string $versionId): array
    {
        $context = DB::table('rps_versions')
            ->join('rps', 'rps.id', '=', 'rps_versions.rps_id')
            ->where('rps_versions.id', $versionId)
            ->first([
                'rps.course_id',
            ]);

        $courseId = $context?->course_id;

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'description', 'bloom_level']);

        $cpmkIds = $cpmks->pluck('id');

        $mappedCpmkCount = $cpmkIds->isEmpty()
            ? 0
            : DB::table('rps_cpmk_cpls')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->distinct()
                ->count('rps_cpmk_id');

        $officialCplIds = $courseId
            ? DB::table('course_cpls')
                ->where('course_id', $courseId)
                ->pluck('cpl_id')
            : collect();

        $additionalCplIds = Schema::hasTable('rps_additional_cpls')
            ? DB::table('rps_additional_cpls')
                ->where('rps_version_id', $versionId)
                ->pluck('cpl_id')
            : collect();

        $scopeCplIds = $officialCplIds
            ->merge($additionalCplIds)
            ->unique()
            ->values();

        $mappedCplIds = $cpmkIds->isEmpty() || $scopeCplIds->isEmpty()
            ? collect()
            : DB::table('rps_cpmk_cpls')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->whereIn('cpl_id', $scopeCplIds)
                ->distinct()
                ->pluck('cpl_id');

        $mappedScopeCplCount = $mappedCplIds->count();
        $scopeCplCount = $scopeCplIds->count();
        $officialCplCount = $officialCplIds->unique()->count();
        $additionalCplCount = $additionalCplIds->unique()->count();

        $allCpmksMapped = $cpmks->isNotEmpty()
            && $mappedCpmkCount === $cpmks->count();

        $allScopeCplsMapped = $scopeCplCount > 0
            && $mappedScopeCplCount === $scopeCplCount;

        $subCpmks = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'description', 'bloom_level']);

        $subCpmkIds = $subCpmks->pluck('id');

        $coveredCpmkCount = $cpmkIds->isEmpty()
            ? 0
            : DB::table('rps_cpmk_subcpmks')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->distinct()
                ->count('rps_cpmk_id');

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'title']);
        $materialCount = $materials->count();

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->orderBy('week_number')
            ->get();

        $filledWeeks = $weeks
            ->filter(fn ($week) =>
                $week->is_exam
                || (
                    filled($week->rps_sub_cpmk_id)
                    && filled($week->material_text)
                    && filled($week->learning_method)
                    && filled($week->learning_activity)
                )
            )
            ->count();

        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->get();

        $assessmentIds = $assessments->pluck('id');

        $weightTotal = round((float) $weeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);

        $teachingWeeks = $weeks->filter(
            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)
        );
        $weightedTeachingWeeks = $teachingWeeks->filter(
            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0
        );
        $teachingWeightTotal = round((float) $teachingWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $assessmentWeightTotal = round((float) $assessments->sum(
            fn ($assessment) => (float) ($assessment->weight ?? 0)
        ), 2);
        $nonExamAssessmentWeight = round((float) $assessments
            ->reject(fn ($assessment) => in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true))
            ->sum(fn ($assessment) => (float) ($assessment->weight ?? 0)), 2);
        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')
            ->filter()
            ->unique()
            ->count();

        $weeklySubBudgets = $teachingWeeks
            ->filter(fn ($week) => filled($week->rps_sub_cpmk_id ?? null))
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)
            ->map(fn ($items) => round((float) $items->sum(
                fn ($week) => (float) ($week->assessment_weight ?? 0)
            ), 2));

        $aggregateSubBudgets = collect();
        $nonExamAssessments = $assessments->reject(
            fn ($assessment) => in_array(strtolower((string) $assessment->type), ['uts', 'uas'], true)
        );

        foreach ($nonExamAssessments as $assessment) {
            $linked = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->orderBy('rps_sub_cpmk_id')
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if ($linked->isEmpty() || (float) ($assessment->weight ?? 0) <= 0) {
                continue;
            }

            $cents = (int) round((float) $assessment->weight * 100);
            $base = intdiv($cents, $linked->count());
            $remainder = $cents % $linked->count();

            foreach ($linked as $index => $subId) {
                $share = ($base + ($index < $remainder ? 1 : 0)) / 100;
                $aggregateSubBudgets->put(
                    $subId,
                    round((float) $aggregateSubBudgets->get($subId, 0) + $share, 2)
                );
            }
        }

        $subBudgetAligned = $subCpmkIds->isNotEmpty()
            && $subCpmkIds->every(function ($subId) use ($weeklySubBudgets, $aggregateSubBudgets): bool {
                $weekly = (float) $weeklySubBudgets->get((string) $subId, 0);
                $aggregate = (float) $aggregateSubBudgets->get((string) $subId, 0);

                return $aggregate > 0 && abs($weekly - $aggregate) < 0.011;
            });

        $assessedSubCount = ($subCpmkIds->isEmpty() || $assessmentIds->isEmpty())
            ? 0
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('rps_sub_cpmk_id');

        $mappedAssessmentCount = ($subCpmkIds->isEmpty() || $assessmentIds->isEmpty())
            ? 0
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->distinct()
                ->count('assessment_id');

        $allAssessmentsMapped = $assessments->isNotEmpty()
            && $mappedAssessmentCount === $assessments->count();

        $taskAssessments = $assessments
            ->whereIn('type', ['assignment', 'project', 'practicum', 'presentation']);

        $assessmentSync = app(RpsAssessmentSyncService::class);
        $taskAlignment = $assessmentSync->taskAlignment($versionId);
        $assessmentSnapshot = $assessmentSync->snapshot($versionId);
        $validatorDecisions = Schema::hasTable('rps_validator_decisions')
            ? DB::table('rps_validator_decisions')
                ->where('rps_version_id', $versionId)
                ->where('decision', 'keep')
                ->get(['check_key', 'subject_key'])
            : collect();
        $keptDecisionKeys = $validatorDecisions
            ->pluck('subject_key')
            ->mapWithKeys(fn ($key) => [(string) $key => true]);
        $assessmentBudgetMismatches = collect($assessmentSnapshot['assessment_budget_mismatches'] ?? []);
        $assessmentBudgetAligned = $assessmentBudgetMismatches->isEmpty();

        // Gunakan bobot efektif hasil snapshot sebagai sumber kebenaran untuk
        // tampilan/validator. Database lama boleh belum tersinkron sampai aksi
        // tulis berikutnya, tetapi pengguna tidak lagi melihat dua versi bobot.
        $expectedWeeklyWeights = collect($assessmentSnapshot['expected_weekly_weights'] ?? []);
        $effectiveWeeks = $weeks->map(function ($week) use ($expectedWeeklyWeights) {
            $copy = clone $week;
            $number = (int) $copy->week_number;
            if ($expectedWeeklyWeights->has($number)) {
                $copy->assessment_weight = (float) $expectedWeeklyWeights->get($number, 0);
            }
            return $copy;
        });
        $weightTotal = round((float) $effectiveWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $teachingWeeks = $effectiveWeeks->filter(
            fn ($week) => ! in_array((int) $week->week_number, [8, 16], true)
        );
        $weightedTeachingWeeks = $teachingWeeks->filter(
            fn ($week) => (float) ($week->assessment_weight ?? 0) > 0
        );
        $teachingWeightTotal = round((float) $teachingWeeks->sum(
            fn ($week) => (float) ($week->assessment_weight ?? 0)
        ), 2);
        $weightedWeeklySubCount = $weightedTeachingWeeks
            ->pluck('rps_sub_cpmk_id')->filter()->unique()->count();
        $weeklySubBudgets = $teachingWeeks
            ->filter(fn ($week) => filled($week->rps_sub_cpmk_id ?? null))
            ->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id)
            ->map(fn ($items) => round((float) $items->sum(
                fn ($week) => (float) ($week->assessment_weight ?? 0)
            ), 2));
        $subBudgetAligned = $subCpmkIds->isNotEmpty()
            && $subCpmkIds->every(function ($subId) use ($weeklySubBudgets, $aggregateSubBudgets): bool {
                $weekly = (float) $weeklySubBudgets->get((string) $subId, 0);
                $aggregate = (float) $aggregateSubBudgets->get((string) $subId, 0);
                return $aggregate > 0 && abs($weekly - $aggregate) < 0.011;
            });

        $tasks = (int) $taskAlignment['task_total'];

        $evidenceNamesByWeek = collect($assessmentSnapshot['assessment_names_by_week'] ?? []);
        $evidenceSourcesByWeek = collect($assessmentSnapshot['assessment_evidence_source_by_week'] ?? []);
        $ambiguousEvidenceWeeks = collect($assessmentSnapshot['ambiguous_evidence_weeks'] ?? []);
        $weightedTeachingWeekNumbers = $weightedTeachingWeeks
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values();
        $unweightedTeachingWeekNumbers = $teachingWeeks
            ->filter(fn ($week) => (float) ($week->assessment_weight ?? 0) <= 0)
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values();
        $coveredEvidenceWeeks = $weightedTeachingWeekNumbers
            ->filter(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $ambiguousWeightedWeeks = $ambiguousEvidenceWeeks
            ->filter(fn ($item) => $weightedTeachingWeekNumbers->contains((int) ($item['week'] ?? 0)))
            ->values();
        $missingEvidenceWeeks = $weightedTeachingWeekNumbers
            ->reject(fn ($week) => filled($evidenceNamesByWeek->get((int) $week)))
            ->values();
        $weeklyEvidenceAligned = $weightedTeachingWeeks->count() === 14
            && $coveredEvidenceWeeks->count() === 14
            && $ambiguousWeightedWeeks->isEmpty();
        $ambiguousWeekNumbers = $ambiguousWeightedWeeks
            ->pluck('week')
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $missingWeekNumbers = $missingEvidenceWeeks
            ->map(fn ($week) => (int) $week)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $positiveNonExamAssessments = $nonExamAssessments->filter(
            fn ($assessment) => (float) ($assessment->weight ?? 0) > 0
        );
        $positiveNonExamMappedCount = $positiveNonExamAssessments->filter(
            fn ($assessment) => DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->exists()
        )->count();
        $allPositiveNonExamMapped = $positiveNonExamAssessments->isNotEmpty()
            && $positiveNonExamMappedCount === $positiveNonExamAssessments->count();
        $assessmentChainAligned = $allPositiveNonExamMapped
            && $weightedTeachingWeeks->count() === 14
            && $weightedWeeklySubCount === $subCpmks->count()
            && $subBudgetAligned
            && $assessmentBudgetAligned
            && $weeklyEvidenceAligned
            && (bool) $taskAlignment['is_aligned'];

        // --- Validator akademik/semantik -------------------------------------
        // Validator ini hanya memberi peringatan. Rumusan dosen tidak pernah
        // diubah otomatis hanya karena pemeriksaan semantik.
        $cpmkById = $cpmks->keyBy(fn ($item) => (string) $item->id);
        $subById = $subCpmks->keyBy(fn ($item) => (string) $item->id);

        $subMappings = ($cpmkIds->isEmpty() || $subCpmkIds->isEmpty())
            ? collect()
            : DB::table('rps_cpmk_subcpmks')
                ->whereIn('rps_cpmk_id', $cpmkIds)
                ->whereIn('rps_sub_cpmk_id', $subCpmkIds)
                ->get(['rps_cpmk_id', 'rps_sub_cpmk_id']);

        $bloomViolations = collect();
        foreach ($subMappings as $mapping) {
            $parent = $cpmkById->get((string) $mapping->rps_cpmk_id);
            $child = $subById->get((string) $mapping->rps_sub_cpmk_id);
            if (! $parent || ! $child) continue;

            $parentRank = $this->bloomRank($parent->bloom_level ?? null);
            $childRank = $this->bloomRank($child->bloom_level ?? null);
            if ($parentRank !== null && $childRank !== null && $childRank > $parentRank) {
                $bloomViolations->push([
                    'cpmk_id' => (string) $parent->id,
                    'cpmk_code' => (string) $parent->code,
                    'cpmk_bloom' => (string) $parent->bloom_level,
                    'sub_cpmk_id' => (string) $child->id,
                    'sub_cpmk_code' => (string) $child->code,
                    'sub_cpmk_bloom' => (string) $child->bloom_level,
                ]);
            }
        }
        $bloomHierarchyAligned = $bloomViolations->isEmpty();

        $duplicateMaterials = collect();
        for ($i = 0; $i < $materials->count(); $i++) {
            for ($j = $i + 1; $j < $materials->count(); $j++) {
                $a = $materials[$i];
                $b = $materials[$j];
                if ($this->semanticNearDuplicate((string) $a->title, (string) $b->title)) {
                    $duplicateMaterials->push([
                        'first_id' => (string) $a->id,
                        'first' => trim((string) $a->title),
                        'second_id' => (string) $b->id,
                        'second' => trim((string) $b->title),
                    ]);
                }
            }
        }
        $materialQualityAligned = $duplicateMaterials->isEmpty();

        $assessmentLinks = $assessmentIds->isEmpty()
            ? collect()
            : DB::table('assessment_subcpmks')
                ->whereIn('assessment_id', $assessmentIds)
                ->get(['assessment_id', 'rps_sub_cpmk_id'])
                ->groupBy(fn ($item) => (string) $item->assessment_id);

        $assessmentSemanticIssues = collect();
        $confirmedAssessmentSemanticCount = 0;
        foreach ($nonExamAssessments as $assessment) {
            $linkedSubIds = collect($assessmentLinks->get((string) $assessment->id, []))
                ->pluck('rps_sub_cpmk_id')->map('strval')->unique()->values();
            if ($linkedSubIds->isEmpty()) continue;

            $text = trim((string) ($assessment->name ?? '').' '.(string) ($assessment->description ?? ''));
            $scores = $subCpmks->mapWithKeys(fn ($sub) => [
                (string) $sub->id => $this->semanticSimilarity($text, (string) $sub->description),
            ]);
            $bestSubId = (string) ($scores->sortDesc()->keys()->first() ?? '');
            $bestScore = (float) $scores->get($bestSubId, 0);
            $explicitCodes = $this->explicitSubCpmkNumbers($text);

            foreach ($linkedSubIds as $linkedSubId) {
                $linkedSub = $subById->get((string) $linkedSubId);
                if (! $linkedSub) continue;

                $linkedScore = (float) $scores->get((string) $linkedSubId, 0);
                $linkedNumber = $this->codeNumber((string) $linkedSub->code);
                $explicitMismatch = $explicitCodes !== []
                    && $linkedNumber !== null
                    && ! in_array($linkedNumber, $explicitCodes, true);
                $clearlyCloserElsewhere = $bestSubId !== ''
                    && $bestSubId !== (string) $linkedSubId
                    && $bestScore >= 0.34
                    && $linkedScore < 0.22
                    && ($bestScore - $linkedScore) >= 0.20;

                if ($explicitMismatch || $clearlyCloserElsewhere) {
                    $bestSub = $subById->get($bestSubId);
                    $decisionKey = 'assessment:'.(string) $assessment->id
                        .':sub:'.(string) $linkedSub->id
                        .':'.sha1($this->semanticNormalized($text).'|'.$this->semanticNormalized((string) $linkedSub->description));
                    if ($keptDecisionKeys->has($decisionKey)) {
                        $confirmedAssessmentSemanticCount++;
                        continue;
                    }
                    $assessmentSemanticIssues->push([
                        'decision_key' => $decisionKey,
                        'assessment_id' => (string) $assessment->id,
                        'assessment_name' => trim((string) $assessment->name),
                        'linked_sub_id' => (string) $linkedSub->id,
                        'linked_sub_code' => (string) $linkedSub->code,
                        'suggested_sub_id' => $bestSub?->id ? (string) $bestSub->id : null,
                        'suggested_sub_code' => $bestSub?->code ? (string) $bestSub->code : null,
                        'reason' => $explicitMismatch ? 'explicit_sub_reference' : 'semantic_distance',
                        'linked_score' => round($linkedScore, 3),
                        'best_score' => round($bestScore, 3),
                    ]);
                }
            }
        }
        $assessmentSemanticIssues = $assessmentSemanticIssues
            ->unique(fn ($item) => $item['assessment_id'].'|'.$item['linked_sub_id'])
            ->values();
        $assessmentSemanticsAligned = $assessmentSemanticIssues->isEmpty();

        $taskRows = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'title', 'assessment_id', 'due_week']);
        $assessmentById = $assessments->keyBy(fn ($item) => (string) $item->id);
        $rtmSemanticIssues = collect();
        $confirmedRtmSemanticCount = 0;
        foreach ($taskRows as $task) {
            if (! filled($task->assessment_id ?? null)) continue;
            $assessment = $assessmentById->get((string) $task->assessment_id);
            if (! $assessment) continue;

            $taskCore = $this->assessmentCoreLabel((string) $task->title);
            $assessmentCore = $this->assessmentCoreLabel((string) $assessment->name);
            if ($taskCore === '' || $assessmentCore === '') continue;

            $similarity = $this->semanticSimilarity($taskCore, $assessmentCore);
            if ($similarity < 0.34) {
                $decisionKey = 'rtm:'.(string) $task->id
                    .':assessment:'.(string) $assessment->id
                    .':'.sha1($this->semanticNormalized((string) $task->title).'|'.$this->semanticNormalized((string) $assessment->name));
                if ($keptDecisionKeys->has($decisionKey)) {
                    $confirmedRtmSemanticCount++;
                    continue;
                }
                $rtmSemanticIssues->push([
                    'decision_key' => $decisionKey,
                    'task_id' => (string) $task->id,
                    'task_code' => (string) $task->code,
                    'task_title' => trim((string) $task->title),
                    'week' => (int) ($task->due_week ?? 0),
                    'assessment_id' => (string) $assessment->id,
                    'assessment_name' => trim((string) $assessment->name),
                    'similarity' => round($similarity, 3),
                ]);
            }
        }
        $rtmSemanticsAligned = $rtmSemanticIssues->isEmpty();

        $weeklyMaterialIssues = collect();
        foreach ($teachingWeeks as $week) {
            $currentSubId = filled($week->rps_sub_cpmk_id ?? null)
                ? (string) $week->rps_sub_cpmk_id
                : null;
            $materialText = trim((string) ($week->material_text ?? ''));
            if (! $currentSubId || $materialText === '' || ! $subById->has($currentSubId)) continue;

            $scores = $subCpmks->mapWithKeys(fn ($sub) => [
                (string) $sub->id => $this->semanticSimilarity($materialText, (string) $sub->description),
            ]);
            $bestSubId = (string) ($scores->sortDesc()->keys()->first() ?? '');
            $bestScore = (float) $scores->get($bestSubId, 0);
            $currentScore = (float) $scores->get($currentSubId, 0);

            if (
                $bestSubId !== ''
                && $bestSubId !== $currentSubId
                && $bestScore >= 0.42
                && $currentScore < 0.18
                && ($bestScore - $currentScore) >= 0.28
            ) {
                $currentSub = $subById->get($currentSubId);
                $bestSub = $subById->get($bestSubId);
                $weeklyMaterialIssues->push([
                    'week' => (int) $week->week_number,
                    'material' => $materialText,
                    'current_sub_code' => (string) ($currentSub?->code ?? ''),
                    'suggested_sub_code' => (string) ($bestSub?->code ?? ''),
                    'current_score' => round($currentScore, 3),
                    'best_score' => round($bestScore, 3),
                ]);
            }
        }
        $weeklyMaterialSemanticsAligned = $weeklyMaterialIssues->isEmpty();

        $ambiguousEvidenceMessage = null;
        if ($ambiguousWeightedWeeks->isNotEmpty()) {
            $firstAmbiguous = $ambiguousWeightedWeeks->first();
            $ambiguousWeek = (int) ($firstAmbiguous['week'] ?? 0);
            $candidateTitles = collect($firstAmbiguous['candidates'] ?? [])
                ->map(fn ($title) => trim((string) $title))
                ->filter();
            $candidateLabels = $taskRows
                ->filter(fn ($task) => (int) ($task->due_week ?? 0) === $ambiguousWeek)
                ->filter(fn ($task) => $candidateTitles->isEmpty() || $candidateTitles->contains(trim((string) $task->title)))
                ->map(fn ($task) => trim((string) $task->code).' '.trim((string) $task->title))
                ->filter()
                ->unique()
                ->values();
            if ($candidateLabels->isEmpty()) {
                $candidateLabels = $candidateTitles->values();
            }
            $ambiguousEvidenceMessage = 'Pekan '.$ambiguousWeek.' memiliki lebih dari satu bukti penilaian'
                .($candidateLabels->isNotEmpty() ? ': '.$candidateLabels->implode(' dan ') : '')
                .'.';
        }

        $cplMessage = $scopeCplCount === 0
            ? "{$mappedCpmkCount}/{$cpmks->count()} CPMK terpetakan · CPL belum tersedia."
            : "{$mappedCpmkCount}/{$cpmks->count()} CPMK · {$mappedScopeCplCount}/{$scopeCplCount} CPL terpetakan.";

        $checks = [
            [
                'key' => 'cpmk_cpl',
                'label' => 'CPMK ↔ CPL',
                'done' => $allCpmksMapped && $allScopeCplsMapped,
                'message' => $cplMessage,
                'details' => [
                    'cpmk_total' => $cpmks->count(),
                    'cpmk_mapped' => $mappedCpmkCount,
                    'cpl_scope_total' => $scopeCplCount,
                    'cpl_scope_mapped' => $mappedScopeCplCount,
                    'cpl_curriculum' => $officialCplCount,
                    'cpl_additional_lecturer' => $additionalCplCount,
                ],
            ],
            [
                'key' => 'sub_cpmk',
                'label' => 'Sub-CPMK',
                'done' => $subCpmks->isNotEmpty() && ($cpmks->isEmpty() || $coveredCpmkCount === $cpmks->count()),
                'message' => "{$subCpmks->count()} Sub-CPMK · {$coveredCpmkCount}/{$cpmks->count()} CPMK terwakili.",
            ],
            [
                'key' => 'bloom_hierarchy',
                'label' => 'Hierarki Bloom',
                'done' => $bloomHierarchyAligned,
                'message' => $bloomHierarchyAligned
                    ? 'Level Bloom CPMK dan Sub-CPMK konsisten.'
                    : (($first = $bloomViolations->first())
                        ? $first['sub_cpmk_code'].' '.$first['sub_cpmk_bloom'].' melampaui '.$first['cpmk_code'].' '.$first['cpmk_bloom'].'.'
                        : 'Ada hierarki Bloom yang perlu diperiksa.'),
                'details' => [
                    'violations' => $bloomViolations->all(),
                ],
            ],
            [
                'key' => 'materials',
                'label' => 'Bahan Kajian',
                'done' => $materialCount > 0,
                'message' => "{$materialCount} bahan kajian.",
            ],
            [
                'key' => 'material_quality',
                'label' => 'Kualitas Bahan Kajian',
                'severity' => 'advisory',
                'done' => $materialQualityAligned,
                'message' => $materialQualityAligned
                    ? 'Tidak ada bahan kajian yang duplikat.'
                    : (($pair = $duplicateMaterials->first())
                        ? 'Bahan kajian mirip: '.$pair['first'].' ↔ '.$pair['second'].'.'
                        : 'Ada bahan kajian yang perlu dirapikan.'),
                'details' => [
                    'duplicates' => $duplicateMaterials->all(),
                ],
            ],
            [
                'key' => 'weekly_material_semantics',
                'label' => 'Kesesuaian Materi per Pekan',
                'severity' => 'advisory',
                'done' => $weeklyMaterialSemanticsAligned,
                'message' => $weeklyMaterialSemanticsAligned
                    ? 'Materi pekan selaras dengan Sub-CPMK.'
                    : (($issue = $weeklyMaterialIssues->first())
                        ? 'Pekan '.$issue['week'].': materi lebih dekat ke '.$issue['suggested_sub_code'].' daripada '.$issue['current_sub_code'].'.'
                        : 'Ada materi pekan yang perlu ditelaah.'),
                'details' => [
                    'issues' => $weeklyMaterialIssues->all(),
                ],
            ],
            [
                'key' => 'weeks',
                'label' => '16 Pertemuan',
                'done' => $weeks->count() === 16 && $filledWeeks === 16,
                'message' => "{$filledWeeks}/16 pertemuan terisi.",
            ],
            [
                'key' => 'exam_weeks',
                'label' => 'UTS/UAS',
                'done' => $weeks->firstWhere('week_number', 8)?->exam_type === 'UTS'
                    && $weeks->firstWhere('week_number', 16)?->exam_type === 'UAS',
                'message' => 'UTS Pekan 8 · UAS Pekan 16.',
            ],
            [
                'key' => 'assessment_weight',
                'label' => 'Bobot Penilaian',
                'done' => abs($assessmentWeightTotal - 100.0) < 0.01
                    && $weightedTeachingWeeks->count() === 14
                    && abs($teachingWeightTotal - $nonExamAssessmentWeight) < 0.01
                    && $subBudgetAligned
                    && $assessmentBudgetAligned
                    && abs($weightTotal - 100.0) < 0.01,
                'message' => "{$weightedTeachingWeeks->count()}/14 pekan berbobot · Total {$weightTotal}%.",
                'details' => [
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'teaching_week_total' => $teachingWeightTotal,
                    'non_exam_assessment_budget' => $nonExamAssessmentWeight,
                    'weekly_total' => $weightTotal,
                    'aggregate_assessment_total' => $assessmentWeightTotal,
                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_sub_budgets' => $weeklySubBudgets->all(),
                    'aggregate_sub_budgets' => $aggregateSubBudgets->all(),
                ],
            ],
            [
                'key' => 'subcpmk_assessed',
                'label' => 'Pengukuran Sub-CPMK per Pekan',
                'done' => $subCpmks->isNotEmpty()
                    && $weightedTeachingWeeks->count() === 14
                    && $weightedWeeklySubCount === $subCpmks->count(),
                'message' => "{$weightedWeeklySubCount}/{$subCpmks->count()} Sub-CPMK terukur · {$weightedTeachingWeeks->count()}/14 pekan.",
                'details' => [
                    'sub_cpmk_total' => $subCpmks->count(),
                    'sub_cpmk_measured_in_weighted_weeks' => $weightedWeeklySubCount,
                    'assessment_mapping_count' => $mappedAssessmentCount,
                    'assessment_total' => $assessments->count(),
                ],
            ],
            [
                'key' => 'assessment_semantics',
                'label' => 'Kesesuaian Asesmen',
                'severity' => 'advisory',
                'done' => $assessmentSemanticsAligned,
                'message' => $assessmentSemanticsAligned
                    ? ($confirmedAssessmentSemanticCount > 0
                        ? 'Rumusan asesmen diterima · '.$confirmedAssessmentSemanticCount.' keputusan dosen dipertahankan.'
                        : 'Rumusan asesmen selaras dengan Sub-CPMK.')
                    : (($issue = $assessmentSemanticIssues->first())
                        ? $issue['assessment_name'].': sistem menyarankan meninjau tag '.$issue['linked_sub_code'].($issue['suggested_sub_code'] ? ' (lebih dekat ke '.$issue['suggested_sub_code'].').' : '.').' Dosen boleh mempertahankan tag.'
                        : 'Ada tag asesmen yang disarankan untuk ditelaah.'),
                'details' => [
                    'issues' => $assessmentSemanticIssues->all(),
                    'confirmed_count' => $confirmedAssessmentSemanticCount,
                ],
            ],
            [
                'key' => 'assessment_chain_sync',
                'label' => 'Konsistensi Penilaian',
                'done' => $assessmentChainAligned,
                'message' => $assessmentChainAligned
                    ? 'Semua penilaian sudah konsisten.'
                    : (! $assessmentBudgetAligned
                        ? $assessmentBudgetMismatches->count().' asesmen memiliki distribusi bobot pekan yang tidak sesuai.'
                        : ($weightedTeachingWeeks->count() < 14
                            ? $unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
                            : ($ambiguousWeekNumbers->isNotEmpty()
                                ? ($ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.')
                                : ($missingWeekNumbers->isNotEmpty()
                                    ? 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.'
                                    : ($taskAlignment['missing_required_assessment_count'] > 0
                                        ? $taskAlignment['missing_required_assessment_count'].' asesmen belum memiliki RTM.'
                                        : 'Masih ada data penilaian yang belum konsisten.'))))),
                'details' => [
                    'positive_non_exam_assessments' => $positiveNonExamAssessments->count(),
                    'mapped_positive_non_exam_assessments' => $positiveNonExamMappedCount,
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'sub_budget_aligned' => $subBudgetAligned,
                    'assessment_budget_aligned' => $assessmentBudgetAligned,
                    'assessment_budget_mismatches' => $assessmentBudgetMismatches->all(),
                    'weekly_evidence_aligned' => $weeklyEvidenceAligned,
                    'weekly_evidence_covered' => $coveredEvidenceWeeks->count(),
                    'weekly_evidence_ambiguous' => $ambiguousWeightedWeeks->count(),
                    'rtm_mapping_mismatch' => $taskAlignment['mapping_mismatch_count'],
                    'rtm_unlinked_weighted' => $taskAlignment['unlinked_weighted_task_count'],
                    'rtm_due_week_subcpmk_mismatch' => $taskAlignment['due_week_subcpmk_mismatch_count'],
                    'rtm_required_missing' => $taskAlignment['missing_required_assessment_count'],
                ],
            ],
            [
                'key' => 'weekly_assessment_evidence',
                'label' => 'Bukti Penilaian per Pekan',
                'done' => $weeklyEvidenceAligned,
                'message' => $weeklyEvidenceAligned
                    ? '14/14 pekan memiliki satu bukti penilaian.'
                    : ($weightedTeachingWeeks->count() < 14
                        ? 'Belum dapat diperiksa: '.$unweightedTeachingWeekNumbers->count().' pekan belum memiliki bobot penilaian.'
                        : ($ambiguousWeekNumbers->isNotEmpty()
                            ? ($ambiguousEvidenceMessage ?: 'Ada pekan dengan lebih dari satu bukti penilaian.')
                            : 'Pekan '.$missingWeekNumbers->implode(', ').' belum memiliki bukti penilaian.')),
                'details' => [
                    'covered_weeks' => $coveredEvidenceWeeks->all(),
                    'missing_weeks' => $missingEvidenceWeeks->all(),
                    'ambiguous_weeks' => $ambiguousWeightedWeeks->all(),
                    'source_by_week' => $evidenceSourcesByWeek->all(),
                    'weighted_teaching_weeks' => $weightedTeachingWeeks->count(),
                    'unweighted_weeks' => $unweightedTeachingWeekNumbers->all(),
                ],
            ],
            [
                'key' => 'rtm_semantics',
                'label' => 'Kesesuaian RTM',
                'severity' => 'advisory',
                'done' => $rtmSemanticsAligned,
                'message' => $rtmSemanticsAligned
                    ? ($confirmedRtmSemanticCount > 0
                        ? 'Hubungan RTM diterima · '.$confirmedRtmSemanticCount.' keputusan dosen dipertahankan.'
                        : 'Judul RTM selaras dengan asesmen induk.')
                    : (($issue = $rtmSemanticIssues->first())
                        ? $issue['task_code'].' “'.$issue['task_title'].'” terhubung ke asesmen “'.$issue['assessment_name'].'”. Periksa asesmen terkait atau pertahankan hubungan jika memang disengaja.'
                        : 'Ada hubungan RTM dan asesmen yang disarankan untuk ditelaah.'),
                'details' => [
                    'issues' => $rtmSemanticIssues->all(),
                    'confirmed_count' => $confirmedRtmSemanticCount,
                ],
            ],
            [
                'key' => 'rtm',
                'label' => 'RTM',
                'done' => $taskAssessments->isEmpty() || (bool) $taskAlignment['is_aligned'],
                'message' => $taskAssessments->isEmpty()
                    ? 'RTM tidak diperlukan.'
                    : ((bool) $taskAlignment['is_aligned']
                        ? "{$tasks} RTM · Semua sinkron."
                        : "{$tasks} RTM · {$taskAlignment['missing_required_assessment_count']} belum ada · {$taskAlignment['mapping_mismatch_count']} tidak sinkron."),
                'details' => $taskAlignment,
            ],
        ];

        $blockingChecks = collect($checks)->reject(fn ($check) => ($check['severity'] ?? 'required') === 'advisory');
        $done = $blockingChecks->where('done', true)->count();
        $percent = $blockingChecks->isEmpty()
            ? 100
            : (int) round(($done / $blockingChecks->count()) * 100);

        return [
            'checks' => $checks,
            'percent' => $percent,
            'is_valid' => $done === $blockingChecks->count(),
            'assessment_weight_total' => $weightTotal,
            'cpl_scope' => [
                'curriculum' => $officialCplCount,
                'additional' => $additionalCplCount,
                'total' => $scopeCplCount,
                'mapped' => $mappedScopeCplCount,
            ],
        ];
    }

    private function bloomRank(?string $level): ?int
    {
        $value = strtoupper(trim((string) $level));
        if (preg_match('/^C([1-6])$/', $value, $match) !== 1) return null;

        return (int) $match[1];
    }

    private function codeNumber(string $code): ?int
    {
        return preg_match('/(\d+)/', $code, $match) === 1 ? (int) $match[1] : null;
    }

    private function explicitSubCpmkNumbers(string $text): array
    {
        preg_match_all('/sub\s*[\p{Pd}\- ]?cpmk\s*[\p{Pd}\- ]?(\d{1,2})/iu', $text, $matches);

        return collect($matches[1] ?? [])->map('intval')->unique()->values()->all();
    }

    private function assessmentCoreLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^(?:quiz|kuis|assignment|tugas|project|proyek|praktikum|presentasi)\s*[- ]*\d+\s*[-–—:]*/iu', '', $value) ?? $value;

        return trim($value);
    }

    private function semanticNearDuplicate(string $a, string $b): bool
    {
        $aNorm = $this->semanticNormalized($a);
        $bNorm = $this->semanticNormalized($b);
        if ($aNorm === '' || $bNorm === '') return false;
        if ($aNorm === $bNorm) return true;

        $short = mb_strlen($aNorm) <= mb_strlen($bNorm) ? $aNorm : $bNorm;
        $long = $short === $aNorm ? $bNorm : $aNorm;
        if (mb_strlen($short) >= 18 && str_contains($long, $short)) return true;

        $aTokens = $this->semanticTokens($a);
        $bTokens = $this->semanticTokens($b);
        if (count($aTokens) < 3 || count($bTokens) < 3) return false;

        $intersection = count(array_intersect($aTokens, $bTokens));
        return $intersection / max(1, min(count($aTokens), count($bTokens))) >= 0.78;
    }

    private function semanticSimilarity(string $a, string $b): float
    {
        $aTokens = $this->semanticTokens($a);
        $bTokens = $this->semanticTokens($b);
        if ($aTokens === [] || $bTokens === []) return 0.0;

        $intersection = count(array_intersect($aTokens, $bTokens));
        return $intersection / max(1, min(count($aTokens), count($bTokens)));
    }

    private function semanticNormalized(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['–', '—'], '-', $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function semanticTokens(string $value): array
    {
        $value = $this->semanticNormalized($value);
        $value = preg_replace('/\b(?:mengimplementasikan|implementasikan|implementasi)\b/u', ' implementasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:menganalisis|analisis)\b/u', ' analisis ', $value) ?? $value;
        $value = preg_replace('/\b(?:mengevaluasi|evaluasi)\b/u', ' evaluasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:pemrograman|memprogram|program)\b/u', ' program ', $value) ?? $value;
        $value = preg_replace('/\b(?:membangun|bangun)\b/u', ' bangun ', $value) ?? $value;
        $value = preg_replace('/\b(?:menggunakan|penggunaan|gunakan)\b/u', ' guna ', $value) ?? $value;
        $value = preg_replace('/\b(?:memvisualisasikan|visualisasi)\b/u', ' visualisasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:merancang|rancang)\b/u', ' rancang ', $value) ?? $value;
        $value = preg_replace('/\b(?:menjelaskan|penjelasan)\b/u', ' jelas ', $value) ?? $value;
        $value = preg_replace('/\b(?:mengidentifikasi|identifikasi)\b/u', ' identifikasi ', $value) ?? $value;
        $value = preg_replace('/\b(?:melatih|pelatihan|training)\b/u', ' latih ', $value) ?? $value;

        $stop = [
            'dan','atau','yang','untuk','dengan','pada','dalam','dari','ke','serta','melalui','sesuai','tentang','secara','berbagai',
            'mahasiswa','kemampuan','ketercapaian','sub','cpmk','jaringan','syaraf','tiruan','model','nilai','tugas','akhir','awal',
            'mis','seperti','suatu','ini','itu','dapat','mampu','hasil','metode','teknik','praktik','praktis','data','bidang','lebih',
            'quiz','kuis','assignment','project','proyek','rtm','pekan','komponen',
        ];

        return collect(preg_split('/\s+/u', $value) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3 && ! in_array($token, $stop, true) && ! ctype_digit($token))
            ->unique()->values()->all();
    }

    public function validateAndPersist(string $versionId): array
    {
        $result = $this->progress($versionId);

        DB::transaction(function () use ($versionId, $result): void {
            DB::table('obe_validation_results')
                ->where('rps_version_id', $versionId)
                ->delete();

            foreach ($result['checks'] as $check) {
                DB::table('obe_validation_results')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $versionId,
                    'rule_code' => $check['key'],
                    'severity' => $check['done'] ? 'info' : 'warning',
                    'is_passed' => $check['done'],
                    'message' => $check['message'],
                    'details' => json_encode($check),
                    'validated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $result;
    }

    public function allowedCplIds(string $courseId): Collection
    {
        return DB::table('course_cpls')
            ->where('course_id', $courseId)
            ->pluck('cpl_id');
    }
}
