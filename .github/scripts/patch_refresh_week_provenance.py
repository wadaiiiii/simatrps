from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"target not found: {label}")
    return text.replace(old, new, 1)

# 1) Smart Draft: keep manual allocation as hard constraint while preserving content provenance.
p = Path('app/Services/Rps/RpsSmartDraftService.php')
s = p.read_text(encoding='utf-8')

s = replace_once(
    s,
    """                && ($manualRow->source_type ?? null) === 'manual_allocation'\n                && filled($manualRow->rps_sub_cpmk_id ?? null)""",
    """                && $this->isManualAllocationSource((string) ($manualRow->source_type ?? ''))\n                && filled($manualRow->rps_sub_cpmk_id ?? null)""",
    'manual allocation count detection',
)

old = """            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $manualAllocation = ($current->source_type ?? null) === 'manual_allocation'
                && filled($current->rps_sub_cpmk_id ?? null);

            if ($manualAllocation) {"""
new = """            $sub = $slot['sub'];
            $materialText = $slot['material'];
            $currentSource = (string) ($current->source_type ?? '');
            $manualAllocation = $this->isManualAllocationSource($currentSource)
                && filled($current->rps_sub_cpmk_id ?? null);
            $legacyManualAllocationAuto = $currentSource === 'manual_allocation'
                && $this->legacyManualAllocationLooksGenerated($current);

            if ($manualAllocation) {"""
s = replace_once(s, old, new, 'manual allocation source block')

old = """            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';
"""
new = """            $resultSourceType = 'smart_draft';
            if ($manualAllocation) {
                if (in_array($currentSource, ['manual_allocation_manual', 'manual_allocation_ai'], true)) {
                    $resultSourceType = $currentSource;
                } elseif ($currentSource === 'manual_allocation' && ! $legacyManualAllocationAuto) {
                    // Data legacy yang tidak menyerupai generator diasumsikan sebagai
                    // keputusan dosen agar tidak tertimpa saat refresh otomatis.
                    $resultSourceType = 'manual_allocation_manual';
                } else {
                    $resultSourceType = 'manual_allocation_auto';
                }
            }

            $method = $hasPracticum
                ? 'Ceramah interaktif, demonstrasi, latihan terbimbing, diskusi, dan praktikum.'
                : 'Ceramah interaktif, diskusi, studi kasus/contoh, dan latihan terbimbing.';
"""
s = replace_once(s, old, new, 'result provenance before payload')

s = replace_once(
    s,
    """                'source_type' => $manualAllocation ? 'manual_allocation' : 'smart_draft',""",
    """                'source_type' => $resultSourceType,""",
    'payload source type',
)

old = """            $changed = false;

            foreach ($payload as $key => $value) {"""
new = """            $changed = false;
            $refreshableGeneratedSource = $currentSource === 'smart_draft'
                || $currentSource === 'manual_allocation_auto'
                || $legacyManualAllocationAuto;

            foreach ($payload as $key => $value) {"""
s = replace_once(s, old, new, 'refreshable source flag')

old = """                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'"""
new = """                // Normalisasi source_type legacy setelah provenance dapat dibedakan.
                // Ini tidak mengubah isi manual/AI; hanya menandai asal isi agar
                // refresh berikutnya tahu mana yang boleh diperbarui otomatis.
                if ($key === 'source_type' && $manualAllocation) {
                    $merged[$key] = $resultSourceType;
                    if ($existing !== $resultSourceType) {
                        $changed = true;
                    }
                    continue;
                }

                // Indikator lama hasil generator boleh dinormalisasi tanpa menyentuh
                // indikator manual dosen. Pola ini berasal dari Smart Draft versi lama.
                $legacyGeneratedIndicator = $key === 'assessment_indicator'"""
s = replace_once(s, old, new, 'source type normalization in merge')

s = replace_once(
    s,
    """                $refreshGeneratedField = ($current->source_type ?? null) === 'smart_draft'
                    && in_array($key, [""",
    """                $refreshGeneratedField = $refreshableGeneratedSource
                    && in_array($key, [""",
    'refresh generated source check',
)

helper_anchor = """    private function referenceCodes(
        string $courseId,
        string $versionId
    ): array {"""
helper = """    private function isManualAllocationSource(string $source): bool
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

        // Baris yang dikosongkan otomatis saat alokasi berubah adalah baris auto
        // yang menunggu diisi ulang; jangan meminta dosen melakukan reset manual.
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

""" + helper_anchor
s = replace_once(s, helper_anchor, helper, 'smart draft provenance helpers')
p.write_text(s, encoding='utf-8')

# 2) Atur Pertemuan: preserve provenance, refresh only auto-generated rows.
p = Path('app/Http/Controllers/RpsAutomationController.php')
s = p.read_text(encoding='utf-8')

old = """                $oldSource = (string) ($row->source_type ?? '');

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
                ) {"""
new = """                $oldSource = (string) ($row->source_type ?? '');
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

                // Jika alokasi menggeser baris yang sebelumnya dihasilkan otomatis,
                // kosongkan konten turunannya. Lengkapi RPS Otomatis akan langsung
                // menyusunnya ulang sesuai Sub-CPMK baru tanpa reset manual 14 pekan.
                // Isi manual/AI tetap dilindungi dan tidak dihapus.
                if (
                    $oldSubId !== $newSubId
                    && $newSource === 'manual_allocation_auto'
                ) {"""
s = replace_once(s, old, new, 'allocation provenance classification')

s = replace_once(
    s,
    """            'Alokasi pertemuan Sub-CPMK disimpan. Lengkapi RPS Otomatis akan mengikuti jumlah pertemuan yang ditetapkan dosen.'""",
    """            'Alokasi pertemuan Sub-CPMK disimpan. Isi otomatis dapat disegarkan langsung dengan Lengkapi RPS Otomatis tanpa mengosongkan 14 pertemuan; edit manual dan AI tetap dilindungi.'""",
    'allocation success message',
)

old = """        DB::table('rps_weekly_plans')
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
            ]);"""
new = """        $targetHasManualAllocation = $this->isManualAllocationSource(
            (string) ($target->source_type ?? '')
        );

        DB::table('rps_weekly_plans')
            ->where('id', $target->id)
            ->update([
                // Atur Pertemuan adalah hard constraint. Saat target sudah
                // dialokasikan manual, Salin Sebelumnya tidak boleh mengganti
                // Sub-CPMK target tersebut.
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
            ]);"""
s = replace_once(s, old, new, 'copy previous respects allocation')

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

# 3) Manual editor: editing a manually allocated week keeps allocation lock but marks content manual.
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
    """            'source_type' => 'manual',
            'updated_at' => now(),""",
    """            'source_type' => str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
                ? 'manual_allocation_manual'
                : 'manual',
            'updated_at' => now(),""",
    'manual week provenance',
)
p.write_text(s, encoding='utf-8')

# 4) Weekly AI: keep hard allocation while marking the content as AI-reviewed.
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
