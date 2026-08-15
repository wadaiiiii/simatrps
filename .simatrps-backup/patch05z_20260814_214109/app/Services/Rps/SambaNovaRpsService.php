<?php

namespace App\Services\Rps;

class SambaNovaRpsService extends OpenAiCompatibleBackupRpsService
{
    protected function providerKey(): string
    {
        return 'sambanova';
    }

    protected function providerLabel(): string
    {
        return 'SambaNova Cloud';
    }

    protected function defaultModel(): string
    {
        return 'Meta-Llama-3.3-70B-Instruct';
    }
}
