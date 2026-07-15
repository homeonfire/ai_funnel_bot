<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class OpenAiProvider implements LlmProviderInterface
{
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function generateResponse(string $apiKey, string $model, array $messages, ?array $tools = null): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
        ];

        // Если переданы переменные для извлечения, формируем схему Tool (Function Calling)
        if (!empty($tools)) {
            $properties = [];
            $required = [];

            foreach ($tools as $tool) {
                $properties[$tool['key']] = [
                    'type' => $tool['type'],
                    'description' => $tool['description'],
                ];
                if ($tool['required'] ?? false) {
                    $required[] = $tool['key'];
                }
            }

            $payload['tools'] = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'save_client_data',
                        'description' => 'Сохранить извлеченные данные клиента',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => $properties,
                            'required' => $required,
                        ]
                    ]
                ]
            ];
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new \Exception("Ошибка LLM API: " . $response->body());
        }

        $message = $response->json('choices.0.message');

        $text = $message['content'] ?? '';
        $extractedData = [];

        // Если ИИ решил вызвать функцию и сохранил данные
        if (isset($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $call) {
                if ($call['function']['name'] === 'save_client_data') {
                    $args = json_decode($call['function']['arguments'], true);
                    if (is_array($args)) {
                        $extractedData = array_merge($extractedData, $args);
                    }
                }
            }
        }

        return [
            'text' => $text,
            'extracted_data' => $extractedData,
        ];
    }
}