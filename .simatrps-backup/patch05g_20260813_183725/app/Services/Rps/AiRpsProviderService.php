<?php

namespace App\Services\Rps;

use Illuminate\Validation\ValidationException;

class AiRpsProviderService
{
    public function __construct(
        private readonly GeminiRpsService $gemini,
        private readonly GroqRpsService $groq,
    ) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured() || $this->groq->isConfigured();
    }

    public function primaryProvider(): string
    {
        return (string) config('simatrps-ai.provider', 'gemini');
    }

    public function displayModel(): string
    {
        return $this->primaryProvider() === 'groq'
            ? $this->groq->model()
            : $this->gemini->model();
    }

    public function testPrimary(): array
    {
        return $this->primaryProvider() === 'groq'
            ? $this->groq->testConnection()
            : $this->gemini->testConnection();
    }

    public function testFallback(): ?array
    {
        if (! config('simatrps-ai.fallback_enabled', true)) {
            return null;
        }

        if ($this->primaryProvider() === 'gemini' && $this->groq->isConfigured()) {
            return $this->groq->testConnection();
        }

        if ($this->primaryProvider() === 'groq' && $this->gemini->isConfigured()) {
            return $this->gemini->testConnection();
        }

        return null;
    }

    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        if ($type === 'weekly_plan') {
            return $this->generateWeeklyPlan($context, $instruction);
        }

        return $this->generateWithFallback($type, $context, $instruction);
    }

    private function generateWeeklyPlan(array $context, ?string $instruction): array
    {
        $batches = [
            [1,2,3,4],
            [5,6,7],
            [9,10,11,12],
            [13,14,15],
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
            $result = $this->generateWeeklyBatchWithFallback($context, $targetWeeks, $instruction);
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
        $actual = collect($weeks)->pluck('week_number')->map(fn ($week) => (int) $week)->all();

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan 14 minggu lengkap. Tidak ada rekomendasi yang disimpan. Silakan coba ulang.',
            ]);
        }

        return [
            'payload' => [
                'summary' => collect($summaries)->filter()->implode(' '),
                'weeks' => $weeks,
            ],
            'provider' => count(array_unique($providers)) === 1 ? $providers[0] : 'mixed',
            'model' => implode(' + ', array_values(array_unique(array_filter($models)))),
            'response_id' => implode(',', array_values(array_filter($responseIds))),
            'usage' => $usages,
            'fallback_used' => $fallbackUsed,
            'primary_error' => $primaryErrors ? implode(' | ', array_unique($primaryErrors)) : null,
        ];
    }

    private function generateWeeklyBatchWithFallback(array $context, array $targetWeeks, ?string $instruction): array
    {
        $primary = $this->primaryProvider();
        $primaryService = $primary === 'groq' ? $this->groq : $this->gemini;

        try {
            return $primaryService->generateWeeklyBatch($context, $targetWeeks, $instruction);
        } catch (ValidationException $primaryError) {
            if (! config('simatrps-ai.fallback_enabled', true)) {
                throw $primaryError;
            }

            $fallback = $primary === 'gemini' ? $this->groq : $this->gemini;
            if (! $fallback->isConfigured()) {
                throw $primaryError;
            }

            try {
                $result = $fallback->generateWeeklyBatch($context, $targetWeeks, $instruction);
                $result['fallback_used'] = true;
                $result['primary_error'] = collect($primaryError->errors())->flatten()->first();
                return $result;
            } catch (ValidationException $fallbackError) {
                throw ValidationException::withMessages([
                    'ai' => 'AI gagal menyusun minggu '.implode('-', $targetWeeks).'. Primary: '
                        .(collect($primaryError->errors())->flatten()->first() ?: 'gagal')
                        .' Fallback: '.(collect($fallbackError->errors())->flatten()->first() ?: 'gagal'),
                ]);
            }
        }
    }

    private function generateWithFallback(string $type, array $context, ?string $instruction): array
    {
        $primary = $this->primaryProvider();

        try {
            return $primary === 'groq'
                ? $this->groq->generate($type, $context, $instruction)
                : $this->gemini->generate($type, $context, $instruction);
        } catch (ValidationException $primaryError) {
            if (! config('simatrps-ai.fallback_enabled', true)) {
                throw $primaryError;
            }

            $fallback = $primary === 'gemini' ? $this->groq : $this->gemini;
            if (! $fallback->isConfigured()) {
                throw $primaryError;
            }

            try {
                $result = $fallback->generate($type, $context, $instruction);
                $result['fallback_used'] = true;
                $result['primary_error'] = collect($primaryError->errors())->flatten()->first();
                return $result;
            } catch (ValidationException $fallbackError) {
                throw ValidationException::withMessages([
                    'ai' => 'AI utama dan fallback sama-sama gagal. Primary: '
                        .(collect($primaryError->errors())->flatten()->first() ?: 'gagal')
                        .' Fallback: '.(collect($fallbackError->errors())->flatten()->first() ?: 'gagal'),
                ]);
            }
        }
    }
}
