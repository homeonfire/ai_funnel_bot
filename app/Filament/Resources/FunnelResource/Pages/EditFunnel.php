<?php

namespace App\Filament\Resources\FunnelResource\Pages;

use App\Filament\Resources\FunnelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFunnel extends EditRecord
{
    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
