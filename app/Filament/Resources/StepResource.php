<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StepResource\Pages;
use App\Filament\Resources\StepResource\RelationManagers;
use App\Models\Step;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StepResource extends Resource
{
    protected static ?string $model = Step::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Настройки шага')
                ->schema([
                    Forms\Components\Select::make('funnel_id')
                        ->relationship('funnel', 'title')
                        ->label('Воронка')
                        ->required(),
                        
                    Forms\Components\TextInput::make('name')
                        ->label('Название шага (например: Квалификация)')
                        ->required(),
                        
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Порядок')
                        ->numeric()
                        ->default(0),
                        
                    Forms\Components\Textarea::make('stage_prompt')
                        ->label('Локальный промт (Задача для ИИ)')
                        ->columnSpanFull()
                        ->rows(3)
                        ->helperText('Например: Узнай у клиента его возраст и цель тренировок.'),
                ])->columns(3),

            Forms\Components\Section::make('Переменные для извлечения (JSON Schema)')
                ->description('Какие данные ИИ должен вытащить из текста клиента на этом шаге')
                ->schema([
                    Forms\Components\Repeater::make('variables_definition')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->label('Ключ (eng)')
                                ->required()
                                ->placeholder('client_age'),
                            Forms\Components\Select::make('type')
                                ->label('Тип')
                                ->options([
                                    'string' => 'Текст',
                                    'integer' => 'Число',
                                    'boolean' => 'Да/Нет',
                                ])->required(),
                            Forms\Components\TextInput::make('description')
                                ->label('Инструкция для ИИ')
                                ->required()
                                ->placeholder('Возраст клиента. Вычленяй только число.'),
                            Forms\Components\Toggle::make('required')
                                ->label('Обязательно')
                                ->default(false)
                        ])->columns(4)
                ]),

            Forms\Components\Section::make('Правила переходов на следующие шаги')
                ->description('Куда перевести клиента, если условия выполнены')
                ->schema([
                    // Используем relationship, так как переходы лежат в отдельной таблице
                    Forms\Components\Repeater::make('outgoingTransitions')
    ->relationship()
    ->label('')
    ->defaultItems(0) // 💡 Ключевой фикс: по умолчанию 0 блоков переходов
    ->schema([
        Forms\Components\Select::make('to_step_id')
    ->label('Перевести на шаг')
    ->options(function (Forms\Get $get, $livewire) {
        // Берем ID воронки из родительской формы
        $funnelId = $get('../../funnel_id');

        if (!$funnelId) {
            return [];
        }

        $query = \App\Models\Step::where('funnel_id', $funnelId);

        // Получаем ID текущего шага через глобальный объект Livewire-страницы
        $currentStepId = $livewire->record?->id ?? null;
        
        if ($currentStepId) {
            $query->where('id', '!=', $currentStepId);
        }

        return $query->pluck('name', 'id');
    })
    ->required(),
            
        Forms\Components\Select::make('logical_operator')
            ->label('Оператор')
            ->options(['AND' => 'И (Все условия)', 'OR' => 'ИЛИ (Хотя бы одно)'])
            ->default('AND'),
            
        Forms\Components\Repeater::make('rules')
            ->label('Условия проверки контекста')
            ->defaultItems(1) // А вот тут одно правило по умолчанию нужно
            ->schema([
                Forms\Components\TextInput::make('field')
                    ->label('Переменная')
                    ->required()
                    ->placeholder('client_age'),
                Forms\Components\Select::make('operator')
                    ->label('Сравнение')
                    ->options([
                        '==' => 'Равно',
                        '!=' => 'Не равно',
                        '>=' => 'Больше или равно',
                        '<=' => 'Меньше или равно',
                        'not_empty' => 'Заполнено',
                        'empty' => 'Пусто',
                    ])->required(),
                Forms\Components\TextInput::make('value')
                    ->label('Значение')
                    ->placeholder('18')
            ])->columns(3)
    ])->columns(2)
                ]),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название шага')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('funnel.title')
                    ->label('Воронка')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->filters([
                // Здесь позже можно будет добавить фильтр по воронкам
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc') // Сортируем по порядку по умолчанию
            ->reorderable('sort_order'); // Позволяет админу менять порядок шагов перетаскиванием (drag & drop)
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
            'index' => Pages\ListSteps::route('/'),
            'create' => Pages\CreateStep::route('/create'),
            'edit' => Pages\EditStep::route('/{record}/edit'),
        ];
    }
}
