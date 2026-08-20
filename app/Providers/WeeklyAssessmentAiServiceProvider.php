<?php

namespace App\Providers;

use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\WeeklyTechniqueAwareAiRpsProviderService;
use Illuminate\Support\ServiceProvider;

class WeeklyAssessmentAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AiRpsProviderService::class,
            WeeklyTechniqueAwareAiRpsProviderService::class,
        );
    }
}
