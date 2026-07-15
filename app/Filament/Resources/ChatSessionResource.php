<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChatSessionResource\Pages;
use App\Models\ChatSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChatSessionResource extends Resource
{
    protected static ?string $model = ChatSession::class;

    // Настраиваем внешний вид в меню
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Клиент';
    protected static ?string $pluralModelLabel = 'Клиенты';
    protected static ?string $navigationGroup = 'Воронки';

    // Запрещаем ручное создание клиентов (кнопка "Создать" исчезнет)
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\TextInput::make('external_chat_id')
                            ->label('Telegram ID')
                            ->readOnly(),
                        Forms\Components\Select::make('bot_id')
                            ->relationship('bot', 'name')
                            ->label('Бот')
                            ->disabled(),
                        Forms\Components\Select::make('current_step_id')
                            ->relationship('currentStep', 'name')
                            ->label('Текущий этап')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Собранные данные (Контекст)')
                    ->schema([
                        // Идеальный компонент для вывода JSON
                        Forms\Components\KeyValue::make('context')
                            ->label('')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(false), // Развернуто по умолчанию

                Forms\Components\Section::make('История переписки')
                    ->schema([
                        Forms\Components\Repeater::make('messages')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('role')
                                    ->label('Отправитель')
                                    // Красиво переименовываем роли для админа
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        'user' => '👤 Клиент',
                                        'assistant' => '🤖 Бот',
                                        'system' => '⚙️ Система',
                                        default => $state,
                                    })
                                    ->readOnly(),
                                Forms\Components\Textarea::make('content')
                                    ->label('Сообщение')
                                    ->readOnly()
                                    ->rows(3),
                            ])
                            // Запрещаем админу удалять/добавлять сообщения руками
                            ->disableItemAddition()
                            ->disableItemDeletion()
                            ->disableItemMovement()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_chat_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->copyable(), // Можно скопировать ID по клику
                Tables\Columns\TextColumn::make('bot.name')
                    ->label('Бот')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currentStep.name')
                    ->label('Текущий этап')
                    ->sortable()
                    ->badge() // Делаем красивой плашкой
                    ->color('success'),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Последнее сообщение')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                // Добавляем фильтр, чтобы смотреть клиентов конкретного бота
                Tables\Filters\SelectFilter::make('bot_id')
                    ->relationship('bot', 'name')
                    ->label('Фильтр по боту'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatSessions::route('/'),
            // Роут для просмотра (ViewAction будет вести сюда)
            'view' => Pages\ViewChatSession::route('/{record}'),
        ];
    }
}