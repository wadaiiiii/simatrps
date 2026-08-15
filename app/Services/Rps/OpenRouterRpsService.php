<?php

namespace App\Services\Rps;

class OpenRouterRpsService extends OpenAiCompatibleBackupRpsService
{
    protected function providerKey(): string
    {
        return 'openrouter';
    }

    protected function providerLabel(): string
    {
        return 'OpenRouter';
    }

    protected function defaultModel(): string
    {
        return 'openrouter/free';
    }
}
