<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FunnelResource\Pages;
use App\Filament\Resources\FunnelResource\RelationManagers;
use App\Models\Funnel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FunnelResource extends Resource
{
    protected static ?string $model = Funnel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('bot_id')
                ->relationship('bot', 'name')
                ->label('Привязать к боту')
                ->required(),
            
            Forms\Components\TextInput::make('title')
                ->label('Название воронки')
                ->required(),
                
            Forms\Components\Textarea::make('global_system_prompt')
                ->label('Глобальный промт (Tone of Voice)')
                ->columnSpanFull()
                ->rows(4)
                ->helperText('Например: Ты вежливый менеджер по продажам. Отвечай кратко.'),
                
            Forms\Components\Toggle::make('is_active')
                ->label('Активна')
                ->default(true),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')->label('Название'),
            Tables\Columns\TextColumn::make('bot.name')->label('Бот'),
            Tables\Columns\ToggleColumn::make('is_active')->label('Активна'),
        ])
        ->filters([])
        ->actions([Tables\Actions\EditAction::make()]);
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
            'index' => Pages\ListFunnels::route('/'),
            'create' => Pages\CreateFunnel::route('/create'),
            'edit' => Pages\EditFunnel::route('/{record}/edit'),
        ];
    }
}
