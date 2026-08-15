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
            } catch (ValidationException) {
                throw $primaryError;
            }
        }
    }
}
