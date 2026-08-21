<?php

use App\Services\Rps\CohereRpsService;
use App\Services\Rps\ComputerAssistedTechniqueGuard;
use App\Services\Rps\GroqRpsService;
use App\Services\Rps\HuggingFaceRpsService;
use App\Services\Rps\MistralRpsService;
use App\Services\Rps\OpenRouterRpsService;
use App\Services\Rps\SambaNovaRpsService;
use App\Services\Rps\WeeklyAssessmentTechniquePolicy;
use App\Services\Rps\WeeklyTechniqueAwareAiRpsProviderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

it('moves to the next provider inside one Susun AI request after a transient failure', function () {
    Cache::flush();

    $groq = Mockery::mock(GroqRpsService::class);
    $mistral = Mockery::mock(MistralRpsService::class);
    $sambanova = Mockery::mock(SambaNovaRpsService::class);
    $openrouter = Mockery::mock(OpenRouterRpsService::class);
    $huggingface = Mockery::mock(HuggingFaceRpsService::class);
    $cohere = Mockery::mock(CohereRpsService::class);

    $groq->shouldReceive('isConfigured')->andReturn(true);
    $mistral->shouldReceive('isConfigured')->andReturn(true);
    $sambanova->shouldReceive('isConfigured')->andReturn(false);
    $openrouter->shouldReceive('isConfigured')->andReturn(false);
    $huggingface->shouldReceive('isConfigured')->andReturn(false);
    $cohere->shouldReceive('isConfigured')->andReturn(false);

    $groq->shouldReceive('generateWeeklyBatch')
        ->once()
        ->andThrow(ValidationException::withMessages([
            'ai' => 'Groq timeout saat memproses pekan.',
        ]));

    $mistral->shouldReceive('generateWeeklyBatch')
        ->once()
        ->andReturn([
            'payload' => [
                'summary' => 'Pekan berhasil disusun.',
                'weeks' => [[
                    'week_number' => 1,
                    'assessment_method' => 'Tes tertulis',
                    'assessment_indicator' => 'Menjawab soal tertulis pada lembar jawaban.',
                ]],
            ],
            'provider' => 'mistral',
            'model' => 'test-model',
        ]);

    $service = new WeeklyTechniqueAwareAiRpsProviderService(
        $groq,
        $mistral,
        $sambanova,
        $openrouter,
        $huggingface,
        $cohere,
        new WeeklyAssessmentTechniquePolicy,
        new ComputerAssistedTechniqueGuard,
    );

    $result = $service->generateWeek([], 1);

    expect($result['provider'])->toBe('mistral')
        ->and($result['payload']['weeks'][0]['assessment_method'])->toBe('Tes tertulis');
});
