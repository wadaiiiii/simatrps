from pathlib import Path
import re

root = Path('.')
smart_path = root / 'app/Services/Rps/RpsSmartDraftService.php'
doc_path = root / 'app/Http/Controllers/RpsDocumentController.php'
ai_path = root / 'app/Http/Controllers/RpsAiController.php'
workflow_path = root / '.github/workflows/patch-weekly-weight-rebalance.yml'
script_path = root / '.github/scripts/patch-weekly-weight-rebalance.py'
migration_path = root / 'database/migrations/2026_08_16_192500_add_assessment_weight_source_to_rps_weekly_plans.php'

smart = smart_path.read_text()

new_method = r'''    private function fillEmptyTeachingWeights(string $versionId): string
    {
        $assessments = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'code', 'name', 'type', 'weight']);

        $assessmentTotal = round((float) $assessments->sum(
            fn ($row) => (float) ($row->weight ?? 0)
        ), 2);
        $examTotal = round((float) $assessments
            ->filter(fn ($row) => in_array(strtolower((string) $row->type), ['uts', 'uas'], true))
            ->sum(fn ($row) => (float) ($row->weight ?? 0)), 2);
        $teachingBudget = round($assessmentTotal - $examTotal, 2);

        if ($teachingBudget <= 0) {
            return 'Bobot 14 pekan belum dibagi karena anggaran asesmen non-UTS/UAS masih 0%.';
        }

        $hasWeightSource = Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source');
        $weekColumns = ['id', 'week_number', 'rps_sub_cpmk_id', 'assessment_weight'];
        if ($hasWeightSource) {
            $weekColumns[] = 'assessment_weight_source';
        }

        $weeks = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', self::TEACHING_WEEKS)
            ->orderBy('week_number')
            ->get($weekColumns);

        if ($weeks->count() !== count(self::TEACHING_WEEKS)) {
            return 'Distribusi bobot pekan menunggu struktur 14 pekan pembelajaran yang lengkap.';
        }

        if ($weeks->contains(fn ($week) => ! filled($week->rps_sub_cpmk_id ?? null))) {
            return 'Distribusi bobot pekan menunggu setiap pekan memiliki Sub-CPMK.';
        }

        $budgetCents = (int) round($teachingBudget * 100);
        $groups = $weeks->groupBy(fn ($week) => (string) $week->rps_sub_cpmk_id);
        $subIds = $groups->keys()->values();

        // Utamakan target yang benar-benar berasal dari pemetaan asesmen agregat.
        // Jika rekap asesmen belum lengkap (mis. total baru 95% atau masih ada
        // Sub-CPMK tanpa asesmen non-UTS/UAS), jangan biarkan tabel RPS kosong:
        // gunakan fallback pembagian rata per Sub-CPMK dari anggaran yang saat ini
        // tersedia. Validator tetap menandai kekurangan/mismatch agar dosen dapat
        // menyempurnakan rekap asesmen kemudian.
        $mappedTargetBySub = array_fill_keys($subIds->all(), 0);
        $mappingComplete = true;
        $nonExamAssessments = $assessments
            ->reject(fn ($row) => in_array(strtolower((string) $row->type), ['uts', 'uas'], true))
            ->filter(fn ($row) => (float) ($row->weight ?? 0) > 0)
            ->values();

        foreach ($nonExamAssessments as $assessment) {
            $linkedSubIds = DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessment->id)
                ->whereIn('rps_sub_cpmk_id', $subIds->all())
                ->pluck('rps_sub_cpmk_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            if ($linkedSubIds->isEmpty()) {
                $mappingComplete = false;
                continue;
            }

            $assessmentCents = (int) round(max(0, (float) ($assessment->weight ?? 0)) * 100);
            $base = intdiv($assessmentCents, $linkedSubIds->count());
            $remainder = $assessmentCents % $linkedSubIds->count();

            foreach ($linkedSubIds as $index => $subId) {
                $mappedTargetBySub[$subId] = ($mappedTargetBySub[$subId] ?? 0)
                    + $base
                    + ($index < $remainder ? 1 : 0);
            }
        }

        if (
            array_sum($mappedTargetBySub) !== $budgetCents
            || collect($subIds)->contains(fn ($subId) => ($mappedTargetBySub[$subId] ?? 0) <= 0)
        ) {
            $mappingComplete = false;
        }

        if ($mappingComplete) {
            $targetBySub = $mappedTargetBySub;
            $distributionMode = 'pemetaan asesmen → Sub-CPMK';
        } else {
            $targetBySub = [];
            $subCount = max(1, $subIds->count());
            $base = intdiv($budgetCents, $subCount);
            $remainder = $budgetCents % $subCount;

            foreach ($subIds as $index => $subId) {
                $targetBySub[$subId] = $base + ($index < $remainder ? 1 : 0);
            }

            $distributionMode = 'fallback rata per Sub-CPMK karena rekap/pemetaan asesmen belum lengkap';
        }

        $manualBySub = array_fill_keys($subIds->all(), 0);
        $autoIdsBySub = array_fill_keys($subIds->all(), []);
        $manualTotal = 0;

        foreach ($groups as $subId => $group) {
            foreach ($group as $week) {
                $source = $hasWeightSource
                    ? strtolower(trim((string) ($week->assessment_weight_source ?? '')))
                    : '';
                $cents = (int) round(max(0, (float) ($week->assessment_weight ?? 0)) * 100);

                if ($source === 'manual') {
                    $manualBySub[$subId] += $cents;
                    $manualTotal += $cents;
                } else {
                    // Data lama tanpa provenance dianggap distribusi otomatis/legacy
                    // sehingga dapat dibangun ulang. Ini membersihkan kasus satu pekan
                    // berisi bobot lama sementara 13 pekan lain tetap 0.
                    $autoIdsBySub[$subId][] = (string) $week->id;
                }
            }
        }

        if ($manualTotal > $budgetCents) {
            return 'Bobot manual pekan sudah melebihi anggaran asesmen non-UTS/UAS. Turunkan bobot manual atau sesuaikan rekap asesmen terlebih dahulu.';
        }

        $allAutoIds = collect($autoIdsBySub)->flatten()->values()->all();
        if ($allAutoIds === []) {
            return 'Seluruh bobot pekan sudah ditetapkan manual; Isi Bagian Kosong tidak mengubahnya.';
        }

        $remainingBudget = $budgetCents - $manualTotal;
        if ($remainingBudget < count($allAutoIds)) {
            return 'Anggaran bobot yang tersisa terlalu kecil untuk memberi bobot positif pada setiap pekan yang belum ditetapkan manual.';
        }

        // Minimum 0,01% untuk setiap pekan otomatis, lalu sisa anggaran dibagi
        // menurut target Sub-CPMK. Dengan pemetaan lengkap, contoh target 10%
        // pada Sub-CPMK yang muncul 2 pekan menghasilkan 5% + 5%.
        $allocations = array_fill_keys($allAutoIds, 1);
        $remaining = $remainingBudget - count($allAutoIds);
        $desiredBySub = [];

        foreach ($subIds as $subId) {
            $autoIds = $autoIdsBySub[$subId] ?? [];
            if ($autoIds === []) {
                continue;
            }

            $target = (int) ($targetBySub[$subId] ?? 0);
            $manual = (int) ($manualBySub[$subId] ?? 0);
            $desiredAuto = max(0, $target - $manual);
            $desiredBySub[$subId] = max(0, $desiredAuto - count($autoIds));
        }

        $totalDesired = array_sum($desiredBySub);
        $groupPool = min($remaining, $totalDesired);
        $assignedGroup = 0;
        $entries = array_keys($desiredBySub);

        foreach ($entries as $entryIndex => $subId) {
            $desired = $desiredBySub[$subId];
            $ids = $autoIdsBySub[$subId] ?? [];
            if ($desired <= 0 || $ids === [] || $groupPool <= 0) {
                continue;
            }

            $isLast = $entryIndex === count($entries) - 1;
            $groupAllocation = $totalDesired > 0
                ? ($isLast
                    ? $groupPool - $assignedGroup
                    : (int) floor($groupPool * ($desired / $totalDesired)))
                : 0;
            $groupAllocation = max(0, min($groupAllocation, $remaining - $assignedGroup));

            $base = intdiv($groupAllocation, count($ids));
            $remainder = $groupAllocation % count($ids);
            foreach ($ids as $index => $id) {
                $allocations[$id] += $base + ($index < $remainder ? 1 : 0);
            }
            $assignedGroup += $groupAllocation;
        }

        $remaining -= $assignedGroup;

        // Bila bobot manual membuat target satu Sub-CPMK terlampaui, sisa anggaran
        // tetap harus terdistribusi agar total pekan = anggaran non-UTS/UAS.
        if ($remaining > 0) {
            $base = intdiv($remaining, count($allAutoIds));
            $remainder = $remaining % count($allAutoIds);
            foreach ($allAutoIds as $index => $id) {
                $allocations[$id] += $base + ($index < $remainder ? 1 : 0);
            }
        }

        foreach ($allocations as $id => $cents) {
            $updates = [
                'assessment_weight' => round($cents / 100, 2),
                'updated_at' => now(),
            ];
            if ($hasWeightSource) {
                $updates['assessment_weight_source'] = 'auto';
            }

            DB::table('rps_weekly_plans')
                ->where('id', $id)
                ->update($updates);
        }

        $message = "Bobot 14 pekan didistribusikan dari anggaran non-UTS/UAS {$teachingBudget}% menggunakan {$distributionMode}.";
        if (abs($assessmentTotal - 100.0) >= 0.01) {
            $message .= " Total asesmen agregat saat ini {$assessmentTotal}%; Validator OBE tetap menandainya sampai tepat 100%.";
        }

        return $message;
    }
'''

pattern = re.compile(r"    private function fillEmptyTeachingWeights\(string \$versionId\): string\n    \{.*?\n    \}\n\n    private function ensureExamAssessments", re.S)
if not pattern.search(smart):
    raise SystemExit('fillEmptyTeachingWeights method not found')
smart = pattern.sub(new_method + "\n    private function ensureExamAssessments", smart, count=1)

# Mark exam-generated weights when the provenance column exists.
old_exam_update = """            DB::table('rps_weekly_plans')\n                ->where('rps_version_id', $versionId)\n                ->where('week_number', $week)\n                ->update([\n                    'assessment_weight' => (float) $weights->get($type, 0),\n                    'updated_at' => now(),\n                ]);"""
new_exam_update = """            $updates = [\n                'assessment_weight' => (float) $weights->get($type, 0),\n                'updated_at' => now(),\n            ];\n            if (Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {\n                $updates['assessment_weight_source'] = 'exam';\n            }\n\n            DB::table('rps_weekly_plans')\n                ->where('rps_version_id', $versionId)\n                ->where('week_number', $week)\n                ->update($updates);"""
if old_exam_update not in smart:
    raise SystemExit('syncExamWeekWeights update block not found')
smart = smart.replace(old_exam_update, new_exam_update, 1)
smart_path.write_text(smart)

# Manual weekly weight edits get their own provenance without changing the content source_type.
doc = doc_path.read_text()
if 'use Illuminate\\Support\\Facades\\Schema;' not in doc:
    doc = doc.replace('use Illuminate\\Support\\Facades\\DB;\n', 'use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;\n', 1)
old_doc_update = """        DB::table('rps_weekly_plans')\n            ->where('id', $row->id)\n            ->update([\n                'assessment_weight' => $newWeight,\n                'updated_at' => now(),\n            ]);"""
new_doc_update = """        $updates = [\n            'assessment_weight' => $newWeight,\n            'updated_at' => now(),\n        ];\n        if (Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {\n            $updates['assessment_weight_source'] = 'manual';\n        }\n\n        DB::table('rps_weekly_plans')\n            ->where('id', $row->id)\n            ->update($updates);"""
if old_doc_update not in doc:
    raise SystemExit('manual week weight update block not found')
doc = doc.replace(old_doc_update, new_doc_update, 1)
doc_path.write_text(doc)

# AI aggregate assessments must never zero-out teaching-week weights.
ai = ai_path.read_text()
old_ai_loop = """        foreach (array_unique($affectedWeeks) as $affectedWeek) {\n            $weekWeight = round(\n                (float) DB::table('assessments')\n                    ->where('rps_version_id', $version->id)\n                    ->where('week_number', $affectedWeek)\n                    ->whereIn('type', ['uts', 'uas'])\n                    ->sum('weight'),\n                2\n            );\n\n            DB::table('rps_weekly_plans')\n                ->where('rps_version_id', $version->id)\n                ->where('week_number', $affectedWeek)\n                ->update([\n                    'assessment_weight' => $weekWeight,\n                    'updated_at' => now(),\n                ]);\n        }"""
new_ai_loop = """        foreach (array_unique($affectedWeeks) as $affectedWeek) {\n            // Asesmen non-UTS/UAS adalah rekap/agregat. Jangan pernah menulis\n            // langsung ke satu pekan karena bobotnya harus didistribusikan\n            // melalui Sub-CPMK oleh Isi Bagian Kosong.\n            if (! in_array((int) $affectedWeek, [8, 16], true)) {\n                continue;\n            }\n\n            $weekWeight = round(\n                (float) DB::table('assessments')\n                    ->where('rps_version_id', $version->id)\n                    ->where('week_number', $affectedWeek)\n                    ->whereIn('type', ['uts', 'uas'])\n                    ->sum('weight'),\n                2\n            );\n\n            DB::table('rps_weekly_plans')\n                ->where('rps_version_id', $version->id)\n                ->where('week_number', $affectedWeek)\n                ->update([\n                    'assessment_weight' => $weekWeight,\n                    'updated_at' => now(),\n                ]);\n        }"""
if old_ai_loop not in ai:
    raise SystemExit('AI affected week sync block not found')
ai = ai.replace(old_ai_loop, new_ai_loop, 1)
ai_path.write_text(ai)

migration_path.write_text(r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->string('assessment_weight_source', 32)
                    ->nullable()
                    ->after('assessment_weight');
            });
        }

        DB::table('rps_weekly_plans')
            ->whereIn('week_number', [8, 16])
            ->update(['assessment_weight_source' => 'exam']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->dropColumn('assessment_weight_source');
            });
        }
    }
};
''')

# Remove temporary patch infrastructure in the implementation commit.
if workflow_path.exists():
    workflow_path.unlink()
if script_path.exists():
    script_path.unlink()
