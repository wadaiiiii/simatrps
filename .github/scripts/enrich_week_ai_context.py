from pathlib import Path

# 1) Enrich week context
p = Path('app/Services/Rps/RpsAiContextService.php')
s = p.read_text(encoding='utf-8')

old_parent = """        $parentCode = DB::table('rps_cpmk_subcpmks')
            ->join('rps_cpmks', 'rps_cpmks.id', '=', 'rps_cpmk_subcpmks.rps_cpmk_id')
            ->where('rps_cpmk_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->value('rps_cpmks.code');
"""
new_parent = """        $parentCpmk = DB::table('rps_cpmk_subcpmks')
            ->join('rps_cpmks', 'rps_cpmks.id', '=', 'rps_cpmk_subcpmks.rps_cpmk_id')
            ->where('rps_cpmk_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->first([
                'rps_cpmks.code',
                'rps_cpmks.description',
                'rps_cpmks.bloom_level',
            ]);

        $parentCode = $parentCpmk?->code;
"""
if old_parent not in s:
    raise SystemExit('parent CPMK target not found')
s = s.replace(old_parent, new_parent, 1)

materials_marker = """        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->limit(20)
            ->pluck('title')
            ->all();


        $syllabusItems = DB::table('course_syllabus_items')
"""
materials_repl = """        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->limit(20)
            ->pluck('title')
            ->all();

        $targetMaterials = [];

        if (Schema::hasTable('rps_material_subcpmks')) {
            $targetMaterials = DB::table('rps_material_subcpmks')
                ->join('rps_materials', 'rps_materials.id', '=', 'rps_material_subcpmks.rps_material_id')
                ->where('rps_material_subcpmks.rps_sub_cpmk_id', $targetSub->id)
                ->where('rps_materials.rps_version_id', $version->id)
                ->orderBy('rps_materials.sequence_no')
                ->limit(10)
                ->pluck('rps_materials.title')
                ->all();
        }

        if ($targetMaterials === [] && Schema::hasColumn('rps_materials', 'rps_sub_cpmk_id')) {
            $targetMaterials = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->where('rps_sub_cpmk_id', $targetSub->id)
                ->orderBy('sequence_no')
                ->limit(10)
                ->pluck('title')
                ->all();
        }

        $targetAssessments = DB::table('assessment_subcpmks')
            ->join('assessments', 'assessments.id', '=', 'assessment_subcpmks.assessment_id')
            ->where('assessment_subcpmks.rps_sub_cpmk_id', $targetSub->id)
            ->where('assessments.rps_version_id', $version->id)
            ->orderByRaw('COALESCE(assessments.week_number, 99)')
            ->get([
                'assessments.name',
                'assessments.type',
                'assessments.week_number',
                'assessments.description',
                'assessments.weight',
            ])
            ->map(fn ($assessment): array => [
                'name' => $assessment->name,
                'type' => $assessment->type,
                'week_number' => $assessment->week_number,
                'description' => $this->clip($assessment->description, 300),
                'weight' => $assessment->weight,
            ])
            ->values()
            ->all();

        $currentWeek = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        $syllabusItems = DB::table('course_syllabus_items')
"""
if materials_marker not in s:
    raise SystemExit('materials marker not found')
s = s.replace(materials_marker, materials_repl, 1)

old_target = """            'target_sub_cpmk' => [
                'code' => $targetSub->code,
                'description' => $this->clip($targetSub->description, 600),
                'bloom_level' => $targetSub->bloom_level,
                'parent_cpmk_code' => $parentCode,
            ],
            'materials' => $materials,
"""
new_target = """            'target_sub_cpmk' => [
                'code' => $targetSub->code,
                'description' => $this->clip($targetSub->description, 600),
                'bloom_level' => $targetSub->bloom_level,
                'parent_cpmk_code' => $parentCode,
            ],
            'parent_cpmk' => $parentCpmk ? [
                'code' => $parentCpmk->code,
                'description' => $this->clip($parentCpmk->description, 700),
                'bloom_level' => $parentCpmk->bloom_level,
            ] : null,
            'target_materials' => $targetMaterials,
            'materials' => $materials,
            'target_assessments' => $targetAssessments,
            'current_week' => $currentWeek ? [
                'material' => $this->clip($currentWeek->material_text, 300),
                'learning_form' => $this->clip($currentWeek->learning_form ?? null, 160),
                'learning_method' => $this->clip($currentWeek->learning_method, 220),
                'learning_activity' => $this->clip($currentWeek->learning_activity, 500),
                'student_assignment' => $this->clip($currentWeek->student_assignment ?? null, 400),
                'assessment_indicator' => $this->clip($currentWeek->assessment_indicator, 500),
                'assessment_criteria' => $this->clip($currentWeek->assessment_criteria, 400),
                'assessment_method' => $this->clip($currentWeek->assessment_method, 250),
            ] : null,
"""
if old_target not in s:
    raise SystemExit('target context marker not found')
s = s.replace(old_target, new_target, 1)
p.write_text(s, encoding='utf-8')

# 2) Make weekly AI instruction explicitly derive new observable evidence
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
old_call = """        try {
            $result = $aiProvider->generateWeek(
                $context,
                $week,
                $data['instruction'] ?? null
            );
"""
new_call = """        $indicatorInstruction = <<<'PROMPT'
Untuk minggu ini, jangan menyalin, memendekkan, atau sekadar memparafrase rumusan `target_sub_cpmk` pada `assessment_indicator`.
Turunkan indikator penilaian BARU sebagai bukti ketercapaian yang dapat diamati dan dinilai. Gunakan konteks `parent_cpmk`, `target_materials`, `target_assessments`, `current_week`, dan level Bloom untuk membuat indikator lebih spesifik terhadap materi minggu tersebut.
Indikator ideal memuat 2-3 tindakan/bukti operasional, misalnya mengidentifikasi unsur pada contoh, menjelaskan hubungan/argumen, menerapkan prosedur pada kasus, membandingkan hasil, menganalisis kesalahan, atau menghasilkan produk yang relevan—sesuaikan dengan level Bloom dan bidang ilmu pada konteks.
JANGAN menyebut kode Sub-CPMK, frasa "sesuai rumusan", "menunjukkan ketercapaian", atau membuka kalimat dengan "Mahasiswa mampu/dapat". Mulai langsung dengan kata kerja operasional.
Boleh menggunakan pengetahuan keilmuan dan pedagogis umum untuk menurunkan contoh bukti belajar yang wajar, tetapi jangan mengubah atau mengarang CPL/CPMK/Sub-CPMK resmi, bobot, referensi, atau kebijakan kurikulum. Jangan membuat ambang angka/nilai baru jika tidak tersedia pada konteks.
Pastikan `assessment_criteria` menilai kualitas bukti tersebut dan `assessment_method` konsisten dengan asesmen yang tersedia.
PROMPT;

        $effectiveInstruction = filled($data['instruction'] ?? null)
            ? trim((string) $data['instruction'])."\n\n".$indicatorInstruction
            : $indicatorInstruction;

        try {
            $result = $aiProvider->generateWeek(
                $context,
                $week,
                $effectiveInstruction
            );
"""
if old_call not in s:
    raise SystemExit('generateWeek call target not found')
s = s.replace(old_call, new_call, 1)
p.write_text(s, encoding='utf-8')
