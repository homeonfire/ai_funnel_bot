<?php

namespace App\Services\Llm;

interface LlmProviderInterface
{
    /**
     * @param string $apiKey API-ключ
     * @param string $model Модель LLM
     * @param array $messages История переписки
     * @param array|null $tools JSON-схема переменных для извлечения
     */
    public function generateResponse(string $apiKey, string $model, array $messages, ?array $tools = null): array;
}