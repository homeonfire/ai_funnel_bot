<?php

namespace App\Filament\Resources\ChatSessionResource\Pages;

use App\Filament\Resources\ChatSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatSessions extends ListRecords
{
    protected static string $resource = ChatSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
