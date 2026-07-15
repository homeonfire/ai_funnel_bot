<?php

namespace App\Filament\Resources\ChatSessionResource\Pages;

use App\Filament\Resources\ChatSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatSession extends EditRecord
{
    protected static string $resource = ChatSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
