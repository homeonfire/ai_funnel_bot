<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotResource\Pages;
use App\Models\Bot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class BotResource extends Resource
{
    protected static ?string $model = Bot::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название бота')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Бот активен')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Настройки Нейросети (LLM)')
                    ->schema([
                        Forms\Components\Select::make('llm_provider')
                            ->label('Нейросеть (Провайдер)')
                            ->options([
                                'openai' => 'ChatGPT (OpenAI)',
                                'deepseek' => 'DeepSeek',
                                'gemini' => 'Gemini (Google)',
                            ])
                            ->live()
                            ->required(),
                        
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Ключ')
                            ->password()
                            ->revealable()
                            ->live(debounce: 500) // Ждем полсекунды после ввода, чтобы не дергать API на каждую букву
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Введите ключ, чтобы загрузить актуальный список моделей. Ключ будет надежно зашифрован.'),

                        Forms\Components\Select::make('llm_model')
                            ->label('Модель')
                            ->searchable()
                            ->options(function (Forms\Get $get, $record) {
                                $provider = $get('llm_provider');
                                
                                // Получаем ключ из формы (если вводим новый) или из базы (если редактируем)
                                $apiKey = $get('api_key') ?: ($record?->api_key);
                                
                                if (!$provider) return [];

                                // Если ключа нет, выдаем заглушки
                                if (!$apiKey) {
                                    return match ($provider) {
                                        'openai' => ['gpt-4o-mini' => 'Введите ключ для загрузки списка'],
                                        'deepseek' => ['deepseek-chat' => 'Введите ключ для загрузки списка'],
                                        'gemini' => ['gemini-2.5-flash' => 'Gemini 2.5 Flash'],
                                        default => [],
                                    };
                                }

                                // Кэшируем список моделей на 1 час
                                $cacheKey = "llm_models_{$provider}_" . md5($apiKey);

                                return Cache::remember($cacheKey, 3600, function () use ($provider, $apiKey) {
                                    try {
                                        if ($provider === 'openai') {
                                            $response = Http::withToken($apiKey)->timeout(5)->get('https://api.openai.com/v1/models');
                                            if ($response->successful()) {
                                                return collect($response->json('data'))
                                                    ->filter(fn($m) => str_contains($m['id'], 'gpt') || str_contains($m['id'], 'o1') || str_contains($m['id'], 'o3')) 
                                                    ->sortBy('id')
                                                    ->pluck('id', 'id')
                                                    ->toArray();
                                            }
                                        }

                                        if ($provider === 'deepseek') {
                                            $response = Http::withToken($apiKey)->timeout(5)->get('https://api.deepseek.com/models');
                                            if ($response->successful()) {
                                                return collect($response->json('data'))
                                                    ->sortBy('id')
                                                    ->pluck('id', 'id')
                                                    ->toArray();
                                            }
                                        }
                                        
                                        if ($provider === 'gemini') {
                                            return ['gemini-2.5-flash' => 'Gemini 2.5 Flash', 'gemini-2.5-pro' => 'Gemini 2.5 Pro'];
                                        }

                                    } catch (\Exception $e) {
                                        // Глушим ошибку, если API лежит
                                    }

                                    // Фоллбэк
                                    return match ($provider) {
                                        'openai' => ['gpt-4o-mini' => 'gpt-4o-mini (Fallback)'],
                                        'deepseek' => ['deepseek-chat' => 'deepseek-chat (Fallback)'],
                                        default => [],
                                    };
                                });
                            })
                            ->required(),
                    ])->columns(1),

                Forms\Components\Section::make('Интеграция с Telegram')
                    ->schema([
                        Forms\Components\TextInput::make('tg_token')
                            ->label('Токен Telegram-бота (от BotFather)')
                            ->password()
                            ->revealable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('llm_provider')
                    ->label('Провайдер')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'openai' => 'success',
                        'deepseek' => 'info',
                        'gemini' => 'warning',
                        default => 'gray',
                    }),
                
                Tables\Columns\IconColumn::make('webhook_status')
                    ->label('Webhook TG')
                    ->boolean(),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Активен'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // --- НОВАЯ КНОПКА ДЛЯ ВЕБХУКА ---
                Tables\Actions\Action::make('set_webhook')
                    ->label('Установить Webhook')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('app_url')
                            ->label('Публичный URL сервера (например, от ngrok)')
                            ->default(config('app.url')) // По умолчанию берет из .env
                            ->required()
                            ->url()
                            ->helperText('Telegram требует HTTPS. Если тестируешь локально, используй ngrok или sail share.'),
                    ])
                    ->action(function (Bot $record, array $data) {
                        if (empty($record->tg_token)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка')
                                ->body('Сначала укажите и сохраните токен Telegram-бота в настройках.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        try {
                            // Формируем полный URL для вебхука
                            $url = rtrim($data['app_url'], '/') . "/api/telegram/webhook/{$record->id}";
                            
                            // Делаем прямой запрос к Telegram API
                            $response = Http::get("https://api.telegram.org/bot{$record->tg_token}/setWebhook", [
                                'url' => $url
                            ]);
                            
                            if ($response->successful() && $response->json('ok')) {
                                
                                // Обновляем статус в базе данных, чтобы горела зеленая галочка
                                $record->update(['webhook_status' => true]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Успех!')
                                    ->body('Вебхук успешно установлен: ' . $url)
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Ошибка от Telegram')
                                    ->body($response->json('description') ?? 'Неизвестная ошибка')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Системная ошибка')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBots::route('/'),
            'create' => Pages\CreateBot::route('/create'),
            'edit' => Pages\EditBot::route('/{record}/edit'),
        ];
    }
}