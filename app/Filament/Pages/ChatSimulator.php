<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Bot;
use App\Models\Step;
use App\Services\Llm\LlmFactory;
use Livewire\Attributes\On; // 💡 Добавили импорт для событий

class ChatSimulator extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'Симулятор Воронки';
    protected static ?string $title = 'Тест ИИ-Ботов';
    protected static string $view = 'filament.pages.chat-simulator';

    public $selectedBotId = null;
    public $chatInput = '';
    
    public $messages = []; 
    public $currentContext = [];
    public $currentStepName = 'Нет активной воронки';
    public $currentStepId = null;

    public function mount()
    {
        $bot = Bot::where('is_active', true)->first();
        if ($bot) {
            $this->selectedBotId = $bot->id;
            $this->initSession();
        }
    }

    public function updatedSelectedBotId()
    {
        $this->initSession();
    }

    public function initSession()
    {
        $this->messages = [];
        $this->currentContext = [];
        $this->currentStepName = 'Нет активной воронки';
        $this->currentStepId = null;
        
        $bot = Bot::with('funnels.steps')->find($this->selectedBotId);
        
        if ($bot && $bot->funnels->isNotEmpty()) {
            $funnel = $bot->funnels->first();
            $firstStep = $funnel->steps()->orderBy('sort_order')->first();
            
            if ($firstStep) {
                $this->currentStepId = $firstStep->id;
                $this->currentStepName = $firstStep->name;
            }
            
            $this->messages[] = [
                'role' => 'system',
                'content' => "⚙️ Сессия инициализирована. Воронка: {$funnel->title}. Бот готов."
            ];
        } else {
            $this->messages[] = [
                'role' => 'system',
                'content' => "⚠️ У этого бота нет активной воронки или шагов."
            ];
        }
    }

    // 💡 ШАГ 1: Мгновенное добавление сообщения и запуск события
    public function submitUserMessage()
    {
        if (empty(trim($this->chatInput)) || !$this->selectedBotId) return;

        // Выводим сообщение пользователя в интерфейс
        $this->messages[] = ['role' => 'user', 'content' => $this->chatInput];
        $this->chatInput = ''; 

        // Отправляем событие браузеру, чтобы он запустил получение ответа отдельным запросом
        $this->dispatch('fetch-ai-response');
    }

    // 💡 ШАГ 2: Фоновый вызов ИИ (запустится сразу после перерисовки чата)
    #[On('fetch-ai-response')]
    public function fetchAiResponse()
    {
        $bot = Bot::find($this->selectedBotId);
        $step = Step::find($this->currentStepId);
        $funnel = $step ? $step->funnel : ($bot->funnels()->first());

        if (!$bot || !$bot->api_key) {
            $this->messages[] = ['role' => 'system', 'content' => '❌ У бота не указан API-ключ.'];
            return;
        }

        $llmMessages = [];

        if ($funnel && $funnel->global_system_prompt) {
            $llmMessages[] = ['role' => 'system', 'content' => $funnel->global_system_prompt];
        }

        if ($step && $step->stage_prompt) {
            $llmMessages[] = ['role' => 'system', 'content' => $step->stage_prompt];
        }

        if (!empty($this->currentContext)) {
            $contextJson = json_encode($this->currentContext, JSON_UNESCAPED_UNICODE);
            $llmMessages[] = [
                'role' => 'system',
                'content' => "Данные клиента на данный момент: {$contextJson}. Используй их, не переспрашивай то, что уже известно."
            ];
        }

        // Передаем историю переписки
        foreach ($this->messages as $msg) {
            if (in_array($msg['role'], ['user', 'assistant'])) {
                $llmMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $toolsSchema = $step ? $step->variables_definition : null;

        try {
            $llm = LlmFactory::make($bot->llm_provider);
            $response = $llm->generateResponse($bot->api_key, $bot->llm_model, $llmMessages, $toolsSchema);

            $textToUser = $response['text'];
            $extractedData = $response['extracted_data'];

            // Выводим текст клиенту, если ИИ что-то сказал
            if (!empty($textToUser)) {
                $this->messages[] = ['role' => 'assistant', 'content' => $textToUser];
            }

            $didTransition = false;

            // Если ИИ извлек данные, сливаем контекст и проверяем переходы
            if (!empty($extractedData)) {
                $this->currentContext = array_merge($this->currentContext, $extractedData);
                $didTransition = $this->evaluateTransitions($step);
            }

            // 💡 КЛЮЧЕВОЙ ФИКС: Если мы перешли на новый шаг, заставляем бота сказать первое слово
            if ($didTransition) {
                // Добавляем невидимую команду для LLM, чтобы она проявила инициативу на новом шаге
                $this->messages[] = [
                    'role' => 'system', 
                    'content' => '⚠️ Системное уведомление: Выполнен переход на новый этап. Изучи инструкцию нового этапа и немедленно напиши клиенту первым, чтобы продолжить воронку.'
                ];
                
                // Триггерим Livewire запустить этот же метод по второму кругу
                $this->dispatch('fetch-ai-response');
            }

        } catch (\Exception $e) {
            $this->messages[] = ['role' => 'system', 'content' => '❌ Ошибка API: ' . $e->getMessage()];
        }
    }

    /**
     * Проверка правил и переключение шагов
     * Теперь возвращает bool (true если переход состоялся)
     */
    protected function evaluateTransitions(?Step $currentStep): bool
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
                $actualValue = $this->currentContext[$field] ?? null;

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
                    $this->currentStepId = $nextStep->id;
                    $this->currentStepName = $nextStep->name;
                    $this->messages[] = [
                        'role' => 'system',
                        'content' => "🚀 Сработал триггер перехода! Вы переведены на шаг: {$nextStep->name}"
                    ];
                    return true; // 💡 Сообщаем, что переход случился
                }
            }
        }
        
        return false; // 💡 Перехода не было
    }

    public function getBotsProperty()
    {
        return Bot::where('is_active', true)->pluck('name', 'id');
    }

    public function getMaxContentWidth(): \Filament\Support\Enums\MaxWidth | string | null
    {
        return \Filament\Support\Enums\MaxWidth::Full;
    }
}