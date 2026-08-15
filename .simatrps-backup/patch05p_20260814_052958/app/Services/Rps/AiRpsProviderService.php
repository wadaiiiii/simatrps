<?php

namespace App\Services\Rps;

use Illuminate\Validation\ValidationException;

class AiRpsProviderService
{
    public function __construct(
        private readonly GroqRpsService $groq,
        private readonly MistralRpsService $mistral,
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

        return $this->generateAcrossProviders(
            fn ($service) => $service->generate($type, $context, $instruction)
        );
    }

    public function generateWeek(
        array $context,
        int $week,
        ?string $instruction = null
    ): array {
        $allowed = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        if (! in_array($week, $allowed, true)) {
            throw ValidationException::withMessages([
                'ai' => 'AI per minggu hanya tersedia untuk minggu pembelajaran 1-7 dan 9-15.',
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
                'ai' => 'AI belum menghasilkan 14 minggu lengkap. Tidak ada rekomendasi yang disimpan.',
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
        $providers = $this->configuredProviders();

        if ($providers === []) {
            throw ValidationException::withMessages([
                'ai' => 'Belum ada provider AI aktif. Konfigurasikan Groq, Mistral, atau Cohere.',
            ]);
        }

        $errors = [];
        $index = 0;

        foreach ($providers as $name => $service) {
            try {
                $result = $callback($service);
                $result['provider'] = $result['provider'] ?? $name;
                $result['fallback_used'] = $index > 0;
                $result['primary_error'] = $index > 0
                    ? collect($errors)->map(
                        fn ($message, $provider) => strtoupper($provider).': '.$message
                    )->implode(' | ')
                    : null;

                return $result;
            } catch (ValidationException $error) {
                $errors[$name] = collect($error->errors())
                    ->flatten()
                    ->first() ?: 'gagal';
            } catch (\Throwable $error) {
                $errors[$name] = $error->getMessage();
            }

            $index++;
        }

        throw ValidationException::withMessages([
            'ai' => 'Semua provider AI gagal. '.collect($errors)
                ->map(fn ($message, $provider) =>
                    strtoupper($provider).': '.$message
                )
                ->implode(' | '),
        ]);
    }

    private function configuredProviders(): array
    {
        $services = [
            'groq' => $this->groq,
            'mistral' => $this->mistral,
            'cohere' => $this->cohere,
        ];

        $order = config(
            'simatrps-ai.provider_chain',
            ['groq', 'mistral', 'cohere']
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
