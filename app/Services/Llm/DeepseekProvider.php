<?php

namespace App\Services\Llm;

class DeepseekProvider extends OpenAiProvider
{
    // У DeepSeek другой эндпоинт, но структура запроса/ответа идентична OpenAI
    protected string $baseUrl = 'https://api.deepseek.com';
}