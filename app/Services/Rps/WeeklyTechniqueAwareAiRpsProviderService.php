<?php

namespace App\Services\Rps;

use Illuminate\Validation\ValidationException;

final class WeeklyTechniqueAwareAiRpsProviderService extends AiRpsProviderService
{
    private const WEEKLY_AI_ATTEMPTS = 3;

    public function __construct(
        GroqRpsService $groq,
        MistralRpsService $mistral,
        SambaNovaRpsService $sambanova,
        OpenRouterRpsService $openrouter,
        HuggingFaceRpsService $huggingface,
        CohereRpsService $cohere,
        private readonly WeeklyAssessmentTechniquePolicy $techniquePolicy,
        private readonly ComputerAssistedTechniqueGuard $computerTechniqueGuard,
    ) {
        parent::__construct(
            $groq,
            $mistral,
            $sambanova,
            $openrouter,
            $huggingface,
            $cohere,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        if ($type !== 'weekly_plan') {
            return parent::generate($type, $context, $instruction);
        }

        $result = parent::generate(
            $type,
            $context,
            $this->techniquePolicy->appendInstruction($instruction),
        );

        return $this->normalizeTechniqueResult($result, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generateWeek(
        array $context,
        int $week,
        ?string $instruction = null
    ): array {
        $effectiveInstruction = $this->techniquePolicy->appendInstruction($instruction);
        $this->applySingleClickRetryBudget(8);
        $lastError = null;

        for ($attempt = 1; $attempt <= self::WEEKLY_AI_ATTEMPTS; $attempt++) {
            try {
                $result = parent::generateWeek(
                    $context,
                    $week,
                    $effectiveInstruction,
                );

                return $this->normalizeTechniqueResult($result, $context);
            } catch (ValidationException $error) {
                $lastError = $error;

                if (! $this->isRetryableWeeklyFailure($error)) {
                    throw $error;
                }
            }
        }

        report($lastError);

        throw ValidationException::withMessages([
            'ai' => 'Fitur AI sedang tidak tersedia. Silakan menunggu beberapa saat dan coba kembali.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeTechniqueResult(array $result, array $context): array
    {
        $result = $this->techniquePolicy->normalizeResult($result, $context);

        return $this->computerTechniqueGuard->normalizeResult($result, $context);
    }

    private function applySingleClickRetryBudget(int $seconds): void
    {
        foreach (['groq', 'mistral', 'sambanova', 'openrouter', 'huggingface', 'cohere'] as $provider) {
            $key = 'simatrps-ai.'.$provider.'.timeout';
            $current = (int) config($key, 22);
            config([$key => max(5, min($current, $seconds))]);
        }
    }

    private function isRetryableWeeklyFailure(ValidationException $error): bool
    {
        $message = (string) (
            collect($error->errors())->flatten()->first() ?: ''
        );

        return preg_match(
            '/provider|groq|mistral|sambanova|openrouter|huggingface|cohere|timeout|timed out|rate limit|quota|cooldown|HTTP 429|output JSON|invalid json|tidak mengembalikan|pekan tidak lengkap|sementara tidak tersedia|temporarily unavailable|service unavailable|gagal sebelum batas waktu/i',
            $message
        ) === 1;
    }
}
