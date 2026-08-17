from pathlib import Path

p = Path('app/Services/Rps/AiRpsProviderService.php')
s = p.read_text()

old = '''            if ($type === 'assessment_plan') {
                return $this->localAssessmentFallback(
                    $context,
                    $error
                );
            }

            throw $error;
'''
new = '''            if ($type === 'assessment_plan') {
                return $this->localAssessmentFallback(
                    $context,
                    $error
                );
            }

            if ($type === 'material_plan') {
                return $this->localMaterialFallback(
                    $context,
                    $error
                );
            }

            throw $error;
'''
if old not in s:
    raise SystemExit('Missing generate fallback marker')
s = s.replace(old, new, 1)

marker = '''    private function localAssessmentFallback(
        array $context,
        ValidationException $error
    ): array {
'''
if marker not in s:
    raise SystemExit('Missing localAssessmentFallback marker')

fallback = '''    private function localMaterialFallback(
        array $context,
        ValidationException $error
    ): array {
        $existing = collect($context['materials'] ?? [])
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique(fn ($title) => mb_strtolower($title))
            ->values();

        $syllabusItems = collect(data_get($context, 'master_syllabus.items', []))
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique(fn ($title) => mb_strtolower($title))
            ->values();

        // Fallback lokal tidak mengarang Bahan Kajian dari rumusan Sub-CPMK.
        // Ia hanya menggunakan item silabus/master kurikulum yang memang sudah
        // tersedia pada konteks RPS, sehingga tetap aman untuk direview dosen.
        $items = $syllabusItems
            ->reject(function (string $title) use ($existing): bool {
                $needle = mb_strtolower(trim($title));

                return $existing->contains(
                    fn (string $current) => mb_strtolower(trim($current)) === $needle
                );
            })
            ->take(16)
            ->map(fn (string $title): array => [
                'action' => 'add',
                'target_title' => null,
                'title' => $title,
                'rationale' => 'Bahan Kajian ini tersedia pada silabus/master kurikulum tetapi belum tercantum pada daftar Bahan Kajian RPS. Review relevansinya sebelum diterapkan.',
            ])
            ->values()
            ->all();

        if ($items === []) {
            throw ValidationException::withMessages([
                'ai' => 'Provider AI sedang tidak tersedia. Rule-engine lokal tidak menemukan Bahan Kajian silabus yang belum masuk ke RPS, sehingga tidak membuat rekomendasi baru agar tidak mengarang materi. Coba Telaah Bahan Kajian AI lagi setelah provider tersedia.',
            ]);
        }

        $reason = (string) (
            collect($error->errors())->flatten()->first()
                ?: 'provider eksternal tidak tersedia'
        );

        return [
            'payload' => [
                'summary' => 'Provider AI eksternal belum berhasil. SiMatRPS menggunakan fallback lokal yang konservatif: hanya Bahan Kajian dari silabus/master kurikulum yang belum tercantum pada RPS yang ditawarkan. Seluruh rekomendasi tetap harus direview dosen.',
                'items' => $items,
            ],
            'provider' => 'system-rule',
            'model' => 'SiMatRPS Material Rule Engine',
            'response_id' => null,
            'usage' => null,
            'fallback_used' => true,
            'primary_error' => $reason,
        ];
    }

'''
s = s.replace(marker, fallback + marker, 1)
p.write_text(s)
