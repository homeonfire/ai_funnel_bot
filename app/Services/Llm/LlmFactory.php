<?php

namespace App\Services\Llm;

class LlmFactory
{
    public static function make(string $provider): LlmProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAiProvider(),
            'deepseek' => new DeepseekProvider(),
            // 'gemini' => new GeminiProvider(), // Добавим позже, у него другой формат
            default => throw new \Exception("Провайдер {$provider} не поддерживается."),
        };
    }
}