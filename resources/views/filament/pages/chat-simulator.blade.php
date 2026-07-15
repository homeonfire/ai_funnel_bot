<x-filament-panels::page>
    <style>
        /* Скрываем стандартный заголовок админки */
        header.fi-header { display: none !important; }
        
        /* Убираем лишние отступы главного контейнера Filament (убрали 100vh чтобы не было скролла) */
        main.fi-main { padding: 1.5rem !important; }

        /* --- Изолированные стили Симулятора --- */
        .sim-container {
            display: flex;
            gap: 1.5rem;
            /* Уменьшили высоту, чтобы окно идеально влезало в экран без прокрутки страницы */
            height: calc(100vh - 9rem); 
            min-height: 500px;
            font-family: inherit;
            box-sizing: border-box;
        }
        @media (max-width: 1024px) {
            .sim-container { flex-direction: column; height: auto; overflow-y: auto; }
        }

        :root {
            --sim-bg: #ffffff;
            --sim-border: #e5e7eb;
            --sim-text: #111827;
            --sim-msg-ai: #f3f4f6;
            --sim-input-bg: #ffffff;
            --sim-input-border: #d1d5db;
            --sim-sidebar-bg: #ffffff;
            --sim-code-bg: #111827;
            --sim-code-text: #4ade80;
        }
        .dark {
            --sim-bg: #18181b; 
            --sim-border: #27272a; 
            --sim-text: #f9fafb;
            --sim-msg-ai: #27272a;
            --sim-input-bg: #27272a;
            --sim-input-border: #3f3f46;
            --sim-sidebar-bg: #18181b;
        }

        /* Панели */
        .sim-panel {
            background: var(--sim-bg);
            border: 1px solid var(--sim-border);
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            min-height: 0;
        }
        .sim-main { flex: 3; }
        
        /* Правая колонка - добавили flex-direction */
        .sim-sidebar { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 1.5rem; 
            background: transparent; 
            border: none; 
            box-shadow: none; 
            overflow: visible; 
        }
        
        .sim-sidebar-card {
            background: var(--sim-sidebar-bg);
            border: 1px solid var(--sim-border);
            border-radius: 1rem;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        /* Шапка */
        .sim-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--sim-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--sim-bg);
            flex-shrink: 0;
        }
        
        .sim-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: var(--sim-input-bg);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            color: var(--sim-text);
            border: 1px solid var(--sim-input-border);
            border-radius: 0.5rem;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            outline: none;
            width: 250px;
            font-size: 0.875rem;
        }

        /* Чат */
        .sim-chat-area {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--sim-bg);
        }
        
        .sim-chat-area::-webkit-scrollbar { width: 6px; }
        .sim-chat-area::-webkit-scrollbar-track { background: transparent; }
        .sim-chat-area::-webkit-scrollbar-thumb { background-color: var(--sim-border); border-radius: 10px; }

        .sim-bubble {
            max-width: 75%;
            padding: 0.85rem 1.25rem;
            border-radius: 1.25rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--sim-text);
            animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(10px);
        }
        .sim-user {
            background: rgba(var(--primary-600), 1);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }
        .sim-ai {
            background: var(--sim-msg-ai);
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
        }
        .sim-system {
            align-self: center;
            background: transparent;
            font-size: 0.75rem;
            color: #6b7280;
            text-align: center;
            max-width: 100%;
        }

        /* Ввод */
        .sim-input-area {
            padding: 1.5rem;
            background: var(--sim-bg);
            border-top: 1px solid var(--sim-border);
            flex-shrink: 0;
        }
        .sim-input-wrapper {
            position: relative;
            max-width: 48rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
        }
        .sim-input {
            width: 100%;
            background: var(--sim-input-bg);
            color: var(--sim-text);
            border: 1px solid var(--sim-input-border);
            border-radius: 9999px;
            padding: 1rem 3.5rem 1rem 1.5rem;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .sim-input:focus { border-color: rgba(var(--primary-500), 1); }
        .sim-input:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .sim-btn {
            position: absolute;
            right: 0.5rem;
            background: rgba(var(--primary-600), 1);
            color: white;
            border: none;
            border-radius: 50%;
            width: 2.7rem;
            height: 2.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, opacity 0.2s;
        }
        .sim-btn:hover:not(:disabled) { background: rgba(var(--primary-500), 1); }
        .sim-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .sim-code-box {
            flex: 1;
            background: var(--sim-code-bg);
            border-radius: 0.5rem;
            padding: 1rem;
            overflow: auto;
            color: var(--sim-code-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
            margin-top: 0.75rem;
        }

        @keyframes popIn {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <div class="sim-container">
        
        <!-- ЛЕВОЕ ОКНО: Чат -->
        <div class="sim-panel sim-main">
            <div class="sim-header">
                <div style="font-weight: 600; font-size: 1.1rem; color: var(--sim-text)">Интерфейс диалога</div>
                <select wire:model.live="selectedBotId" class="sim-select">
                    <option value="">-- Выберите бота --</option>
                    @foreach($this->bots as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sim-chat-area" id="chat-container">
                @foreach($messages as $message)
                    @if($message['role'] === 'system')
                        <div class="sim-bubble sim-system">{{ $message['content'] }}</div>
                    @elseif($message['role'] === 'user')
                        <div class="sim-bubble sim-user">{{ $message['content'] }}</div>
                    @else
                        <div class="sim-bubble sim-ai">{!! nl2br(e($message['content'])) !!}</div>
                    @endif
                @endforeach

                <!-- Индикатор загрузки (Фоновый процесс) -->
                <div wire:loading wire:target="fetchAiResponse" class="sim-bubble sim-ai" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg class="animate-spin" style="height: 1.25rem; width: 1.25rem; color: rgba(var(--primary-500), 1)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span style="color: #6b7280; font-style: italic; font-size: 0.85rem;">Ожидание ответа...</span>
                </div>
            </div>

            <div class="sim-input-area">
                <form wire:submit.prevent="submitUserMessage" class="sim-input-wrapper">
                    <!-- Поле заблокировано, если идет любой из двух процессов отправки/ожидания -->
                    <input type="text" wire:model="chatInput" wire:loading.attr="disabled" wire:target="submitUserMessage, fetchAiResponse" 
                           placeholder="Напишите сообщение..." 
                           class="sim-input"
                           {{ !$selectedBotId ? 'disabled' : '' }}>
                    
                    <button type="submit" wire:loading.attr="disabled" wire:target="submitUserMessage, fetchAiResponse" class="sim-btn" {{ !$selectedBotId ? 'disabled' : '' }}>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                          <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- ПРАВОЕ ОКНО: Дебаггер -->
        <div class="sim-sidebar">
            <div class="sim-sidebar-card" style="flex: 0 0 auto;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem; color: rgba(var(--primary-500), 1)">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                    </svg>
                    Текущий этап
                </div>
                <div style="font-size: 1.25rem; font-weight: 600; color: var(--sim-text);">
                    {{ $currentStepName }}
                </div>
            </div>

            <div class="sim-sidebar-card" style="flex: 1; min-height: 0;">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem; color: #10b981">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                    </svg>
                    Извлеченный JSON
                </div>
                <div class="sim-code-box">
                    @if(empty($currentContext))
                        <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: #4b5563; font-style: italic;">
                            Данные не собраны
                        </div>
                    @else
                        <pre style="margin: 0; white-space: pre-wrap; word-break: break-all;">{{ json_encode($currentContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('livewire:initialized', () => {
            const container = document.getElementById('chat-container');
            Livewire.hook('morph.updated', ({ el, component }) => {
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });
    </script>
</x-filament-panels::page>