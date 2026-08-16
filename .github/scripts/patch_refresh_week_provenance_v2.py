from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"target not found: {label}")
    return text.replace(old, new, 1)

# Smart Draft: manual meeting allocation remains a hard constraint, while
# generated/manual/AI content provenance stays distinguishable.
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

s = replace_once(
    s,
    """                && ($manualRow->source_type ?? null) === 'manual_allocation'\n                && filled($manualRow->rps_sub_cpmk_id ?? null)""",
    """                && $this->isManualAllocationSource((string) ($manualRow->source_type ?? ''))\n                && filled($manualRow->rps_sub_cpmk_id ?? null)""",
    'manual allocation count detection',
)

s = replace_once(
    s,
    """            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $manualAllocation = ($current->source_type ?? null) === 'manual_allocation'
                && filled($current->rps_sub_cpmk_id ?? null);

            if ($manualAllocation) {""",
    """            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $currentSource = (string) ($current->source_type ?? '');
            $manualAllocation = $this->isManualAllocationSource($currentSource)
                && filled($current->rps_sub_cpmk_id ?? null);
            $legacyManualAllocationAuto = $currentSource === 'manual_allocation'
                && $this->legacyManualAllocationLooksGenerated($current);

            if ($manualAllocation) {""",
    'manual allocation source block',
)

s = replace_once(
    s,
    """            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';
""",
    """            $resultSourceType = 'smart_draft';
            if ($manualAllocation) {
                if (in_array($currentSource, ['manual_allocation_manual', 'manual_allocation_ai'], true)) {
                    $resultSourceType = $currentSource;
                } elseif ($currentSource === 'manual_allocation' && ! $legacyManualAllocationAuto) {
                    $resultSourceType = 'manual_allocation_manual';
                } else {
                    $resultSourceType = 'manual_allocation_auto';
                }
            }

            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';
""",
    'result provenance before payload',
)

s = replace_once(
    s,
    """                'source_type' => $manualAllocation ? 'manual_allocation' : 'smart_draft',""",
    """                'source_type' => $resultSourceType,""",
    'payload source type',
)

s = replace_once(
    s,
    """            $changed = false;

            foreach ($payload as $key => $value) {""",
    """            $changed = false;
            $refreshableGeneratedSource = $currentSource === 'smart_draft'
                || $currentSource === 'manual_allocation_auto'
                || $legacyManualAllocationAuto;

            foreach ($payload as $key => $value) {""",
    'refreshable source flag',
)

s = replace_once(
    s,
    """                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'""",
    """                // Normalisasi provenance legacy tanpa mengubah isi manual/AI.
                if ($key === 'source_type' && $manualAllocation) {
                    $merged[$key] = $resultSourceType;
                    if ($existing !== $resultSourceType) {
                        $changed = true;
                    }
                    continue;
                }

                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'""",
    'source type normalization in merge',
)

s = replace_once(
    s,
    """                $refreshGeneratedField = ($current->source_type ?? null) === 'smart_draft'
                    && in_array($key, [""",
    """                $refreshGeneratedField = $refreshableGeneratedSource
                    && in_array($key, [""",
    'refresh generated source check',
)

# Salin Sebelumnya must not replace the Sub-CPMK chosen in Atur Pertemuan.
s = replace_once(
    s,
    """        DB::table('rps_weekly_plans')
            ->where('id', $target->id)
            ->update([
                'rps_sub_cpmk_id' => $source->rps_sub_cpmk_id,
                'material_text' => $source->material_text,
                'learning_method' => $source->learning_method,
                'learning_activity' => $source->learning_activity,
                'assessment_indicator' => $source->assessment_indicator,
                'assessment_criteria' => $source->assessment_criteria,
                'assessment_method' => $source->assessment_method,
                'reference_text' => $source->reference_text,
                'source_type' => 'copied_previous',
                'updated_at' => now(),
            ]);""",
    """        $targetHasManualAllocation = $this->isManualAllocationSource(
            (string) ($target->source_type ?? '')
        );

        DB::table('rps_weekly_plans')
            ->where('id', $target->id)
            ->update([
                'rps_sub_cpmk_id' => $targetHasManualAllocation
                    ? $target->rps_sub_cpmk_id
                    : $source->rps_sub_cpmk_id,
                'material_text' => $source->material_text,
                'learning_method' => $source->learning_method,
                'learning_activity' => $source->learning_activity,
                'assessment_indicator' => $source->assessment_indicator,
                'assessment_criteria' => $source->assessment_criteria,
                'assessment_method' => $source->assessment_method,
                'reference_text' => $source->reference_text,
                'source_type' => $targetHasManualAllocation
                    ? 'manual_allocation_manual'
                    : 'copied_previous',
                'updated_at' => now(),
            ]);""",
    'copy previous respects allocation',
)

anchor = """    private function referenceCodes(
        string $courseId,
        string $versionId
    ): array {"""
helpers = """    private function isManualAllocationSource(string $source): bool
    {
        return $source === 'manual_allocation'
            || str_starts_with($source, 'manual_allocation_');
    }

    private function legacyManualAllocationLooksGenerated(object $week): bool
    {
        $core = [
            trim((string) ($week->material_text ?? '')),
            trim((string) ($week->learning_activity ?? '')),
            trim((string) ($week->student_assignment ?? '')),
            trim((string) ($week->assessment_indicator ?? '')),
            trim((string) ($week->assessment_criteria ?? '')),
            trim((string) ($week->assessment_method ?? '')),
        ];

        if (collect($core)->filter(fn ($value) => $value !== '')->isEmpty()) {
            return true;
        }

        $signals = 0;
        $activity = (string) ($week->learning_activity ?? '');
        $assignment = (string) ($week->student_assignment ?? '');
        $criteria = (string) ($week->assessment_criteria ?? '');
        $method = (string) ($week->assessment_method ?? '');
        $learningMethod = (string) ($week->learning_method ?? '');

        if (preg_match('/^Mahasiswa mempelajari .+mendiskusikan contoh, dan menyelesaikan latihan yang mendukung Sub-CPMK-?\\d+\\.$/u', $activity) === 1) {
            $signals++;
        }
        if (preg_match('/^Latihan\\/tugas terstruktur yang selaras dengan Sub-CPMK-?\\d+\\.$/u', $assignment) === 1) {
            $signals++;
        }
        if (str_starts_with($criteria, 'Ketepatan, kelengkapan, dan kesesuaian jawaban/kinerja terhadap indikator Sub-CPMK-')) {
            $signals++;
        }
        if ($method === 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.') {
            $signals++;
        }
        if (str_contains($learningMethod, 'Ceramah interaktif') && str_contains($learningMethod, 'latihan terbimbing')) {
            $signals++;
        }

        return $signals >= 2;
    }

""" + anchor
s = replace_once(s, anchor, helpers, 'smart draft provenance helpers')
p.write_text(s, encoding='utf-8')

# Atur Pertemuan: classify content provenance and clear only generated content
# when a Sub-CPMK moves to a different week.
p = Path('app/Http/Controllers/RpsAutomationController.php')
s = p.read_text(encoding='utf-8')

s = replace_once(
    s,
    """                $oldSource = (string) ($row->source_type ?? '');

                $update = [
                    'rps_sub_cpmk_id' => $newSubId,
                    'source_type' => 'manual_allocation',
                    'updated_at' => now(),
                ];

                // Jika alokasi menggeser baris yang sebelumnya dihasilkan otomatis,
                // kosongkan konten turunan agar tidak terjadi Sub-CPMK baru dengan
                // materi/indikator milik Sub-CPMK lama. Isian manual/AI dosen tidak
                // dihapus; dosen tetap dapat meninjaunya.
                if (
                    $oldSubId !== $newSubId
                    && in_array($oldSource, ['smart_draft', 'manual_allocation'], true)
                ) {""",
    """                $oldSource = (string) ($row->source_type ?? '');
                $legacyLooksGenerated = $oldSource === 'manual_allocation'
                    && $this->legacyManualAllocationLooksGenerated($row);

                if (
                    $oldSource === 'manual'
                    || $oldSource === 'copied_previous'
                    || $oldSource === 'manual_allocation_manual'
                    || ($oldSource === 'manual_allocation' && ! $legacyLooksGenerated)
                ) {
                    $newSource = 'manual_allocation_manual';
                } elseif (
                    $oldSource === 'ai_accepted'
                    || $oldSource === 'manual_allocation_ai'
                ) {
                    $newSource = 'manual_allocation_ai';
                } else {
                    $newSource = 'manual_allocation_auto';
                }

                $update = [
                    'rps_sub_cpmk_id' => $newSubId,
                    'source_type' => $newSource,
                    'updated_at' => now(),
                ];

                // Hanya konten generator yang dikosongkan saat Sub-CPMK bergeser.
                // Lengkapi RPS Otomatis akan langsung menyusunnya kembali tanpa
                // reset manual. Isi manual dan AI tetap dilindungi.
                if (
                    $oldSubId !== $newSubId
                    && $newSource === 'manual_allocation_auto'
                ) {""",
    'allocation provenance classification',
)

s = replace_once(
    s,
    """            'Alokasi pertemuan Sub-CPMK disimpan. Lengkapi RPS Otomatis akan mengikuti jumlah pertemuan yang ditetapkan dosen.'""",
    """            'Alokasi pertemuan Sub-CPMK disimpan. Isi otomatis dapat disegarkan langsung dengan Lengkapi RPS Otomatis tanpa mengosongkan 14 pertemuan; edit manual dan AI tetap dilindungi.'""",
    'allocation success message',
)

anchor = """    private function context(Request $request, string $rps): array
    {"""
helpers = """    private function isManualAllocationSource(string $source): bool
    {
        return $source === 'manual_allocation'
            || str_starts_with($source, 'manual_allocation_');
    }

    private function legacyManualAllocationLooksGenerated(object $week): bool
    {
        $core = [
            trim((string) ($week->material_text ?? '')),
            trim((string) ($week->learning_activity ?? '')),
            trim((string) ($week->student_assignment ?? '')),
            trim((string) ($week->assessment_indicator ?? '')),
            trim((string) ($week->assessment_criteria ?? '')),
            trim((string) ($week->assessment_method ?? '')),
        ];

        if (collect($core)->filter(fn ($value) => $value !== '')->isEmpty()) {
            return true;
        }

        $signals = 0;
        if (preg_match('/^Mahasiswa mempelajari .+mendiskusikan contoh, dan menyelesaikan latihan yang mendukung Sub-CPMK-?\\d+\\.$/u', (string) ($week->learning_activity ?? '')) === 1) {
            $signals++;
        }
        if (preg_match('/^Latihan\\/tugas terstruktur yang selaras dengan Sub-CPMK-?\\d+\\.$/u', (string) ($week->student_assignment ?? '')) === 1) {
            $signals++;
        }
        if (str_starts_with((string) ($week->assessment_criteria ?? ''), 'Ketepatan, kelengkapan, dan kesesuaian jawaban/kinerja terhadap indikator Sub-CPMK-')) {
            $signals++;
        }
        if ((string) ($week->assessment_method ?? '') === 'Latihan/kuis formatif atau observasi kinerja sesuai aktivitas pembelajaran.') {
            $signals++;
        }
        if (
            str_contains((string) ($week->learning_method ?? ''), 'Ceramah interaktif')
            && str_contains((string) ($week->learning_method ?? ''), 'latihan terbimbing')
        ) {
            $signals++;
        }

        return $signals >= 2;
    }

""" + anchor
s = replace_once(s, anchor, helpers, 'automation provenance helpers')
p.write_text(s, encoding='utf-8')

# Manual editor: preserve allocation lock and mark the content as lecturer-edited.
p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')
s = replace_once(
    s,
    """            ->where('source_type', 'manual_allocation')
            ->exists();""",
    """            ->where('source_type', 'like', 'manual_allocation%')
            ->exists();""",
    'align manual allocation prefix detection',
)
s = replace_once(
    s,
    """            'reference_text' => $this->normalizeReferenceCodes((string) ($data['reference_text'] ?? '')),
            'source_type' => 'manual',
            'updated_at' => now(),""",
    """            'reference_text' => $this->normalizeReferenceCodes((string) ($data['reference_text'] ?? '')),
            'source_type' => str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
                ? 'manual_allocation_manual'
                : 'manual',
            'updated_at' => now(),""",
    'manual week provenance',
)
p.write_text(s, encoding='utf-8')

# Weekly AI: preserve allocation lock and mark the content as AI-derived.
p = Path('app/Http/Controllers/RpsAiController.php')
s = p.read_text(encoding='utf-8')
s = replace_once(
    s,
    """        $updates['source_type'] = 'ai_accepted';
        $updates['updated_at'] = now();""",
    """        $updates['source_type'] = str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
            ? 'manual_allocation_ai'
            : 'ai_accepted';
        $updates['updated_at'] = now();""",
    'weekly AI provenance',
)
p.write_text(s, encoding='utf-8')
