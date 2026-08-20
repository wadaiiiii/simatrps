<?php

namespace App\Services\Rps;

final class WeeklyTechniqueAwareAiRpsProviderService extends AiRpsProviderService
{
    public function __construct(
        GroqRpsService $groq,
        MistralRpsService $mistral,
        SambaNovaRpsService $sambanova,
        OpenRouterRpsService $openrouter,
        HuggingFaceRpsService $huggingface,
        CohereRpsService $cohere,
        private readonly WeeklyAssessmentTechniquePolicy $techniquePolicy,
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

        return $this->techniquePolicy->normalizeResult($result, $context);
    }

    public function generateWeek(
        array $context,
        int $week,
        ?string $instruction = null
    ): array {
        $result = parent::generateWeek(
            $context,
            $week,
            $this->techniquePolicy->appendInstruction($instruction),
        );

        return $this->techniquePolicy->normalizeResult($result, $context);
    }
}
