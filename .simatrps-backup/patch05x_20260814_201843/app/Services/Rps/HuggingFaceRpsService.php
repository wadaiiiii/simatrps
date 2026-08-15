<?php

namespace App\Services\Rps;

class HuggingFaceRpsService extends OpenAiCompatibleBackupRpsService
{
    protected function providerKey(): string
    {
        return 'huggingface';
    }

    protected function providerLabel(): string
    {
        return 'Hugging Face Inference Providers';
    }

    protected function defaultModel(): string
    {
        return 'Qwen/Qwen3-4B-Thinking-2507:fastest';
    }
}
