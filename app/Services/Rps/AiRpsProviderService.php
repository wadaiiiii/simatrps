<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AiRpsProviderService
{
    public function __construct(
        private readonly GroqRpsService $groq,
        private readonly MistralRpsService $mistral,
        private readonly SambaNovaRpsService $sambanova,
        private readonly OpenRouterRpsService $openrouter,
        private readonly HuggingFaceRpsService $huggingface,
        private readonly CohereRpsService $cohere,
    ) {}

    public function isConfigured(): bool
    {
        return count($this->configuredProviders()) > 0;
    }

    public function configuredProviderNames(): array
    {
        return array_keys($this->configuredProviders());
    }

    public function primaryProvider(): string
    {
        return array_key_first($this->configuredProviders())
            ?: (string) config('simatrps-ai.provider', 'groq');
    }

    public function displayModel(): string
    {
        $providers = $this->configuredProviders();
        $primary = array_key_first($providers);

        return $primary ? $providers[$primary]->model() : '-';
    }

    public function testPrimary(): array
    {
        $providers = $this->configuredProviders();

        if ($providers === []) {
            throw ValidationException::withMessages([
                'ai' => 'Belum ada provider AI yang dikonfigurasi.',
            ]);
        }

        $name = array_key_first($providers);

        return $providers[$name]->testConnection();
    }

    public function testBackups(): array
    {
        $providers = $this->configuredProviders();

        if (count($providers) <= 1) {
            return [];
        }

        array_shift($providers);
        $results = [];

        foreach ($providers as $name => $service) {
            try {
                $results[$name] = [
                    'ok' => true,
                    'result' => $service->testConnection(),
                ];
            } catch (\Throwable $error) {
                $results[$name] = [
                    'ok' => false,
                    'error' => $error->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        if ($type === 'weekly_plan') {
            return $this->generateWeeklyPlan($context, $instruction);
        }

        try {
            return $this->generateAcrossProviders(
                fn ($service) => $service->generate(
                    $type,
                    $context,
                    $instruction
                )
            );
        } catch (ValidationException $error) {
            if ($type === 'assessment_plan') {
                return $this->localAssessmentFallback(
                    $context,
                    $error
                );
            }

            throw $error;
        }
    }

    public function generateWeek(
        array $context,
        int $week,
        ?string $instruction = null
    ): array {
        $allowed = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        if (! in_array($week, $allowed, true)) {
            throw ValidationException::withMessages([
                'ai' => 'AI per pekan hanya tersedia untuk pekan pembelajaran 1-7 dan 9-15.',
            ]);
        }

        return $this->generateAcrossProviders(
            fn ($service) => $service->generateWeeklyBatch(
                $context,
                [$week],
                $instruction
            )
        );
    }

    private function generateWeeklyPlan(array $context, ?string $instruction): array
    {
        $batches = [
            [1,2],
            [3,4],
            [5,6],
            [7],
            [9,10],
            [11,12],
            [13,14],
            [15],
        ];

        $weeks = [];
        $summaries = [];
        $providers = [];
        $models = [];
        $responseIds = [];
        $usages = [];
        $fallbackUsed = false;
        $primaryErrors = [];

        foreach ($batches as $targetWeeks) {
            $result = $this->generateWeeklyBatchResilient(
                $context,
                $targetWeeks,
                $instruction
            );

            $weeks = [...$weeks, ...($result['payload']['weeks'] ?? [])];
            $summaries[] = $result['payload']['summary'] ?? null;
            $providers[] = $result['provider'] ?? 'ai';
            $models[] = $result['model'] ?? null;
            $responseIds[] = $result['response_id'] ?? null;
            $usages[] = $result['usage'] ?? null;
            $fallbackUsed = $fallbackUsed || (bool) ($result['fallback_used'] ?? false);

            if (filled($result['primary_error'] ?? null)) {
                $primaryErrors[] = $result['primary_error'];
            }
        }

        $weeks = collect($weeks)
            ->keyBy(fn (array $week) => (int) ($week['week_number'] ?? 0))
            ->sortKeys()
            ->values()
            ->all();

        $expected = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $actual = collect($weeks)
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->all();

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan 14 pekan lengkap. Tidak ada rekomendasi yang disimpan.',
            ]);
        }

        return [
            'payload' => [
                'summary' => collect($summaries)->filter()->implode(' '),
                'weeks' => $weeks,
            ],
            'provider' => count(array_unique($providers)) === 1
                ? ($providers[0] ?? 'ai')
                : 'mixed',
            'model' => implode(' + ', array_values(array_unique(array_filter($models)))),
            'response_id' => implode(',', array_values(array_filter($responseIds))),
            'usage' => $usages,
            'fallback_used' => $fallbackUsed,
            'primary_error' => $primaryErrors
                ? implode(' | ', array_unique($primaryErrors))
                : null,
        ];
    }

    private function generateWeeklyBatchResilient(
        array $context,
        array $targetWeeks,
        ?string $instruction
    ): array {
        try {
            return $this->generateAcrossProviders(
                fn ($service) => $service->generateWeeklyBatch(
                    $context,
                    $targetWeeks,
                    $instruction
                )
            );
        } catch (ValidationException $error) {
            $message = (string) (
                collect($error->errors())->flatten()->first() ?: ''
            );

            if (
                count($targetWeeks) <= 1
                || ! str_contains(strtolower($message), 'tidak lengkap')
            ) {
                throw $error;
            }

            $weeks = [];
            $summaries = [];
            $providers = [];
            $models = [];
            $responseIds = [];
            $usages = [];
            $fallbackUsed = false;
            $primaryErrors = [];

            foreach ($targetWeeks as $week) {
                $single = $this->generateAcrossProviders(
                    fn ($service) => $service->generateWeeklyBatch(
                        $context,
                        [$week],
                        $instruction
                    )
                );

                $weeks = [...$weeks, ...($single['payload']['weeks'] ?? [])];
                $summaries[] = $single['payload']['summary'] ?? null;
                $providers[] = $single['provider'] ?? 'ai';
                $models[] = $single['model'] ?? null;
                $responseIds[] = $single['response_id'] ?? null;
                $usages[] = $single['usage'] ?? null;
                $fallbackUsed = $fallbackUsed || (bool) ($single['fallback_used'] ?? false);

                if (filled($single['primary_error'] ?? null)) {
                    $primaryErrors[] = $single['primary_error'];
                }
            }

            return [
                'payload' => [
                    'summary' => collect($summaries)->filter()->implode(' '),
                    'weeks' => collect($weeks)
                        ->sortBy('week_number')
                        ->values()
                        ->all(),
                ],
                'provider' => count(array_unique($providers)) === 1
                    ? ($providers[0] ?? 'ai')
                    : 'mixed',
                'model' => implode(' + ', array_values(array_unique(array_filter($models)))),
                'response_id' => implode(',', array_values(array_filter($responseIds))),
                'usage' => $usages,
                'fallback_used' => $fallbackUsed,
                'primary_error' => $primaryErrors
                    ? implode(' | ', array_unique($primaryErrors))
                    : null,
            ];
        }
    }

    private function generateAcrossProviders(callable $callback): array
    {
        $configured = $this->configuredProviders();

        if ($configured === []) {
            throw ValidationException::withMessages([
                'ai' => 'Belum ada provider AI aktif. Konfigurasikan minimal satu provider AI SiMatRPS.',
            ]);
        }

        $primary = array_key_first($configured);
        $available = [];
        $skipped = [];

        foreach ($configured as $name => $service) {
            $cooldown = Cache::get($this->cooldownKey($name));

            if ($cooldown) {
                $skipped[$name] = (string) $cooldown;
                continue;
            }

            $available[$name] = $service;
        }

        // Do not immediately hit providers that are already known to be
        // unavailable/rate-limited. The caller may use a local fallback.
        if ($available === []) {
            throw ValidationException::withMessages([
                'ai' => 'Semua provider AI aktif sedang cooldown: '
                    .collect($skipped)
                        ->map(fn ($message, $provider) =>
                            strtoupper($provider).': '.$message
                        )
                        ->implode(' | '),
            ]);
        }

        $errors = [];
        $attempted = [];

        foreach ($available as $name => $service) {
            $attempted[] = $name;

            try {
                $result = $callback($service);
                $result['provider'] = $result['provider'] ?? $name;
                $result['fallback_used'] = $name !== $primary;

                $previousProblems = [
                    ...$skipped,
                    ...$errors,
                ];

                $result['primary_error'] = $name !== $primary
                    ? collect($previousProblems)
                        ->map(fn ($message, $provider) =>
                            strtoupper($provider).': '.$message
                        )
                        ->implode(' | ')
                    : null;

                return $result;
            } catch (ValidationException $error) {
                $raw = (string) (
                    collect($error->errors())->flatten()->first() ?: 'gagal'
                );

                $friendly = $this->compactProviderError($raw);
                $errors[$name] = $friendly;

                if ($this->shouldCooldown($raw)) {
                    Cache::put(
                        $this->cooldownKey($name),
                        $friendly,
                        now()->addMinutes($this->cooldownMinutes($raw))
                    );
                }
            } catch (\Throwable $error) {
                $raw = $error->getMessage();
                $friendly = $this->compactProviderError($raw);
                $errors[$name] = $friendly;

                if ($this->shouldCooldown($raw)) {
                    Cache::put(
                        $this->cooldownKey($name),
                        $friendly,
                        now()->addMinutes($this->cooldownMinutes($raw))
                    );
                }
            }
        }

        $allProblems = [...$skipped, ...$errors];

        throw ValidationException::withMessages([
            'ai' => 'Semua provider AI aktif gagal. Sudah mencoba/melewati: '
                .collect(array_keys($allProblems))
                    ->map(fn ($name) => strtoupper($name))
                    ->implode(', ')
                .'. '
                .collect($allProblems)
                    ->map(fn ($message, $name) =>
                        strtoupper($name).': '.$message
                    )
                    ->implode(' | '),
        ]);
    }

    private function cooldownKey(string $provider): string
    {
        return 'simatrps:ai:cooldown:'.strtolower($provider);
    }

    private function shouldCooldown(string $message): bool
    {
        return (bool) preg_match(
            '/tokens per day|TPD|daily quota|rate limit|high demand|temporarily unavailable|timeout|timed out|denied access|access denied|service unavailable|payment method is required|payment required|HTTP 402|invalid api key|unauthorized|HTTP 401/i',
            $message
        );
    }

    private function cooldownMinutes(string $message): int
    {
        if (preg_match('/payment method is required|payment required|HTTP 402/i', $message)) {
            return 1440;
        }

        if (preg_match('/invalid api key|unauthorized|HTTP 401/i', $message)) {
            return 1440;
        }

        if (preg_match('/denied access|access denied|forbidden/i', $message)) {
            return 1440;
        }

        if (preg_match('/tokens per day|TPD|daily quota/i', $message)) {
            return 15;
        }

        if (preg_match('/timeout|timed out/i', $message)) {
            return 3;
        }

        return 2;
    }

    private function compactProviderError(string $message): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);

        if (preg_match('/payment method is required|payment required|HTTP 402/i', $message)) {
            return 'memerlukan metode pembayaran/billing';
        }

        if (preg_match('/invalid api key|unauthorized|HTTP 401/i', $message)) {
            return 'API key/otorisasi tidak valid';
        }

        if (preg_match('/tokens per day|TPD|daily quota/i', $message)) {
            return 'kuota harian tercapai';
        }

        if (preg_match('/tokens per minute|TPM/i', $message)) {
            return 'batas token per menit tercapai';
        }

        if (preg_match('/high demand/i', $message)) {
            return 'model sedang high demand';
        }

        if (preg_match('/timeout|timed out|maximum execution time/i', $message)) {
            return 'timeout';
        }

        if (preg_match('/denied access|access denied|forbidden/i', $message)) {
            return 'akses ditolak';
        }

        if (preg_match('/invalid api key|api key.*invalid/i', $message)) {
            return 'API key tidak valid';
        }

        return mb_strlen($message) > 180
            ? mb_substr($message, 0, 177).'...'
            : $message;
    }

    private function localAssessmentFallback(
        array $context,
        ValidationException $error
    ): array {
        $subs = collect($context['sub_cpmks'] ?? [])
            ->filter(fn ($item) =>
                is_array($item)
                && filled($item['code'] ?? null)
            )
            ->values();

        if ($subs->isEmpty()) {
            throw $error;
        }

        $codes = $subs
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->values();

        $courseName = trim(
            (string) data_get($context, 'course.name', 'Mata Kuliah')
        );

        $count = max(1, $codes->count());
        $third = max(1, (int) ceil($count / 3));
        $half = max(1, (int) ceil($count / 2));

        $group1 = $codes->slice(0, $third)->values()->all();
        $group2 = $codes->slice($third, $third)->values()->all();
        $group3 = $codes->slice($third * 2)->values()->all();

        if ($group2 === []) {
            $group2 = $group1;
        }

        if ($group3 === []) {
            $group3 = $group2;
        }

        $firstHalf = $codes->slice(0, $half)->values()->all();
        $secondHalf = $codes->slice($half)->values()->all();

        if ($secondHalf === []) {
            $secondHalf = $codes->all();
        }

        $practicalType = (bool) data_get(
            $context,
            'course.has_practicum',
            false
        )
            ? 'practicum'
            : 'assignment';

        $practicalName = $practicalType === 'practicum'
            ? 'Praktikum Terstruktur'
            : 'Tugas Terstruktur 2';

        $assessments = [
            [
                'name' => 'Tugas Terstruktur 1',
                'type' => 'assignment',
                'week_number' => 4,
                'weight' => 15,
                'sub_cpmk_codes' => $group1,
                'description' => 'Penilaian terstruktur untuk mengukur capaian Sub-CPMK awal.',
            ],
            [
                'name' => $practicalName,
                'type' => $practicalType,
                'week_number' => 7,
                'weight' => 15,
                'sub_cpmk_codes' => $group2,
                'description' => 'Penilaian penerapan konsep/keterampilan pada capaian Sub-CPMK tahap menengah.',
            ],
            [
                'name' => 'Ujian Tengah Semester',
                'type' => 'uts',
                'week_number' => 8,
                'weight' => 20,
                'sub_cpmk_codes' => $firstHalf,
                'description' => 'UTS mengukur capaian pembelajaran paruh pertama semester.',
            ],
            [
                'name' => 'Proyek / Tugas Integratif',
                'type' => 'project',
                'week_number' => 14,
                'weight' => 25,
                'sub_cpmk_codes' => $group3,
                'description' => 'Penilaian integratif untuk menerapkan dan menggabungkan capaian Sub-CPMK tingkat lanjut.',
            ],
            [
                'name' => 'Ujian Akhir Semester',
                'type' => 'uas',
                'week_number' => 16,
                'weight' => 25,
                'sub_cpmk_codes' => $secondHalf,
                'description' => 'UAS mengukur capaian pembelajaran akhir semester.',
            ],
        ];

        $tasks = [
            [
                'title' => "Tugas Terstruktur 1 - {$courseName}",
                'type' => 'assignment',
                'assessment_name' => 'Tugas Terstruktur 1',
                'due_week' => 4,
                'purpose' => 'Mengukur penguasaan capaian Sub-CPMK awal secara terstruktur.',
                'instructions' => 'Kerjakan tugas berdasarkan materi dan aktivitas pembelajaran yang telah dilaksanakan. Sertakan proses/argumentasi yang mendukung jawaban.',
                'expected_output' => 'Dokumen tugas/laporan ringkas sesuai ketentuan dosen.',
                'sub_cpmk_codes' => $group1,
            ],
            [
                'title' => "{$practicalName} - {$courseName}",
                'type' => $practicalType,
                'assessment_name' => $practicalName,
                'due_week' => 7,
                'purpose' => 'Mengukur kemampuan penerapan konsep dan keterampilan pada tahap menengah.',
                'instructions' => 'Laksanakan aktivitas terstruktur sesuai konteks mata kuliah dan dokumentasikan proses serta hasil.',
                'expected_output' => 'Laporan/produk praktik atau tugas terstruktur.',
                'sub_cpmk_codes' => $group2,
            ],
            [
                'title' => "Proyek Integratif - {$courseName}",
                'type' => 'project',
                'assessment_name' => 'Proyek / Tugas Integratif',
                'due_week' => 14,
                'purpose' => 'Mengintegrasikan capaian Sub-CPMK lanjut dalam satu pekerjaan komprehensif.',
                'instructions' => 'Susun proyek/tugas integratif yang menunjukkan proses analisis, penerapan, evaluasi, dan komunikasi hasil.',
                'expected_output' => 'Laporan/produk akhir dan bahan presentasi sesuai kebutuhan mata kuliah.',
                'sub_cpmk_codes' => $group3,
            ],
        ];

        $reason = (string) (
            collect($error->errors())->flatten()->first()
                ?: 'provider eksternal tidak tersedia'
        );

        return [
            'payload' => [
                'summary' => 'Provider AI eksternal belum berhasil. SiMatRPS membuat rancangan asesmen dan RTM sementara dengan rule engine lokal agar pekerjaan dosen tidak terhenti. Seluruh item tetap perlu direview.',
                'assessments' => $assessments,
                'tasks' => $tasks,
            ],
            'provider' => 'system-rule',
            'model' => 'SiMatRPS Rule Engine',
            'response_id' => null,
            'usage' => null,
            'fallback_used' => true,
            'primary_error' => $reason,
        ];
    }

    private function configuredProviders(): array
    {
        $services = [
            'groq' => $this->groq,
            'mistral' => $this->mistral,
            'sambanova' => $this->sambanova,
            'openrouter' => $this->openrouter,
            'huggingface' => $this->huggingface,
            'cohere' => $this->cohere,
        ];

        $order = config(
            'simatrps-ai.provider_chain',
            ['groq', 'mistral', 'sambanova', 'openrouter', 'huggingface', 'cohere']
        );

        $configured = [];

        foreach ($order as $name) {
            if (
                isset($services[$name])
                && $services[$name]->isConfigured()
            ) {
                $configured[$name] = $services[$name];
            }
        }

        return $configured;
    }
}
