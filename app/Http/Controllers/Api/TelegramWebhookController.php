<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Step;
use App\Models\ChatSession;
use App\Services\Llm\LlmFactory;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Bot $bot, Request $request)
    {
        if (!$bot->is_active || empty($bot->tg_token)) {
            return response('Bot inactive', 403);
        }

        try {
            $nutgram = new Nutgram($bot->tg_token);
            $nutgram->setRunningMode(Webhook::class);

            $nutgram->onMessage(function (Nutgram $tg) use ($bot) {
                $chatId = $tg->chatId();
                $text = $tg->message()->text;
                
                if (!$text) return;

                // Используем твои поля (external_chat_id)
                $session = ChatSession::firstOrCreate(
                    [
                        'bot_id' => $bot->id, 
                        'external_chat_id' => $chatId,
                        'platform' => 'telegram'
                    ],
                    [
                        'context' => [], 
                        'messages' => [],
                        'last_message_at' => now(),
                    ]
                );

                if ($text === '/start') {
                    $funnel = $bot->funnels()->first();
                    $firstStep = $funnel ? $funnel->steps()->orderBy('sort_order')->first() : null;
                    
                    $session->update([
                        'current_step_id' => $firstStep?->id,
                        'context' => [],
                        'messages' => [],
                        'last_message_at' => now(),
                    ]);
                    
                    $this->processAiOrchestrator($bot, $session, $tg, "⚠️ Системное уведомление: Клиент запустил бота (команда /start). Изучи инструкцию текущего этапа и начни диалог первым.", 'system');
                    return;
                }

                $session->update(['last_message_at' => now()]);
                $this->processAiOrchestrator($bot, $session, $tg, $text, 'user');
            });

            $nutgram->run();
            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error("Nutgram Error (Bot ID: {$bot->id}): " . $e->getMessage());
            return response('Error', 500);
        }
    }

    protected function processAiOrchestrator(Bot $bot, ChatSession $session, Nutgram $tg, string $inputText, string $role = 'user')
    {
        // Статус печатает
        $tg->sendChatAction('typing');

        $messages = $session->messages ?? [];
        $messages[] = ['role' => $role, 'content' => $inputText];

        $step = $session->currentStep;
        $funnel = $step ? $step->funnel : $bot->funnels()->first();

        if (!$bot->api_key) {
            $tg->sendMessage("❌ Ошибка: У бота не настроен API-ключ.");
            return;
        }

        $llmMessages = [];
        if ($funnel && $funnel->global_system_prompt) {
            $llmMessages[] = ['role' => 'system', 'content' => $funnel->global_system_prompt];
        }
        if ($step && $step->stage_prompt) {
            $llmMessages[] = ['role' => 'system', 'content' => $step->stage_prompt];
        }

        $context = $session->context ?? [];
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
            $llmMessages[] = [
                'role' => 'system',
                'content' => "Данные клиента на данный момент: {$contextJson}. Используй их, не переспрашивай то, что уже известно."
            ];
        }

        foreach ($messages as $msg) {
            $llmMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $toolsSchema = $step ? $step->variables_definition : null;

        try {
            $llm = LlmFactory::make($bot->llm_provider);
            $response = $llm->generateResponse($bot->api_key, $bot->llm_model, $llmMessages, $toolsSchema);

            $textToUser = $response['text'];
            $extractedData = $response['extracted_data'];

            if (!empty($textToUser)) {
                // Магия UTM: прогоняем текст через наш парсер ссылок перед отправкой
                $textToUser = $this->appendUtmToUrls($textToUser, (string)$session->external_chat_id);
                
                $messages[] = ['role' => 'assistant', 'content' => $textToUser];
                $tg->sendMessage($textToUser);
            }

            $didTransition = false;
            if (!empty($extractedData)) {
                $context = array_merge($context, $extractedData);
                $session->context = $context;
                $didTransition = $this->evaluateTransitions($step, $context, $session);
            }

            $session->messages = $messages;
            $session->save();

            if ($didTransition) {
                // СБРОС КЭША СВЯЗЕЙ (Исправление бесконечного цикла)
                $session->refresh(); 

                $this->processAiOrchestrator(
                    $bot, 
                    $session, 
                    $tg, 
                    '⚠️ Системное уведомление: Выполнен переход на новый этап. Изучи инструкцию нового этапа и немедленно напиши клиенту первым.', 
                    'system'
                );
            }

        } catch (\Exception $e) {
            Log::error("LLM API Error: " . $e->getMessage());
            $tg->sendMessage("❌ Возникла ошибка при обращении к нейросети.");
        }
    }

    protected function evaluateTransitions(?Step $currentStep, array $context, ChatSession &$session): bool
    {
        if (!$currentStep) return false;

        $transitions = $currentStep->outgoingTransitions;

        foreach ($transitions as $transition) {
            $rules = $transition->rules ?? [];
            $logicalOperator = $transition->logical_operator ?? 'AND';
            
            if (empty($rules)) continue;

            $matchesCount = 0;
            foreach ($rules as $rule) {
                $field = $rule['field'];
                $operator = $rule['operator'];
                $expectedValue = $rule['value'] ?? null;
                $actualValue = $context[$field] ?? null;

                $rulePassed = match ($operator) {
                    '==' => $actualValue == $expectedValue,
                    '!=' => $actualValue != $expectedValue,
                    '>=' => $actualValue >= $expectedValue,
                    '<=' => $actualValue <= $expectedValue,
                    'not_empty' => !empty($actualValue),
                    'empty' => empty($actualValue),
                    default => false,
                };

                if ($rulePassed) $matchesCount++;
            }

            $isMatch = ($logicalOperator === 'AND' && $matchesCount === count($rules)) || 
                       ($logicalOperator === 'OR' && $matchesCount > 0);

            if ($isMatch) {
                $nextStep = Step::find($transition->to_step_id);
                if ($nextStep) {
                    $session->current_step_id = $nextStep->id;
                    return true;
                }
            }
        }
        
        return false;
    }

    protected function appendUtmToUrls(string $text, string $chatId): string
    {
        // Ищем все ссылки начинающиеся с http:// или https://
        return preg_replace_callback('/(https?:\/\/[^\s"\'<>\)]+)/i', function ($matches) use ($chatId) {
            $url = $matches[1];
            
            // Отсекаем случайную пунктуацию в конце ссылки (точки, запятые), если регулярка их захватила
            $trailing = '';
            if (preg_match('/([.,!?]+)$/', $url, $punctMatches)) {
                $trailing = $punctMatches[1];
                $url = substr($url, 0, -strlen($trailing));
            }

            $parsedUrl = parse_url($url);
            if ($parsedUrl === false) return $matches[0]; // Если ссылка кривая, возвращаем как есть

            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams); // Разбираем текущие параметры, если они есть
            }

            // Добавляем или перезаписываем наши UTM-метки
            $queryParams['utm_source'] = 'ai_assistant';
            $queryParams['tg_id'] = $chatId;

            // Собираем ссылку обратно
            $newQuery = http_build_query($queryParams);
            
            $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
            $host = $parsedUrl['host'] ?? '';
            $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
            $path = $parsedUrl['path'] ?? '';
            $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

            $newUrl = $scheme . $host . $port . $path . '?' . $newQuery . $fragment;

            return $newUrl . $trailing;
        }, $text);
    }
}