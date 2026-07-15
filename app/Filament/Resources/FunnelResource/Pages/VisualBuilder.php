<?php

namespace App\Filament\Resources\FunnelResource\Pages;

use App\Filament\Resources\FunnelResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;

class VisualBuilder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FunnelResource::class;

    protected static string $view = 'filament.resources.funnel-resource.pages.visual-builder';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record); 
    }

    public function getTitle(): string | Htmlable
    {
        return 'Визуальный редактор: ' . $this->record->name;
    }

    // НОВЫЙ МЕТОД: Отдаем шаги и переходы на фронтенд
    public function getStepsDataProperty(): array
    {
        return $this->record->steps()
            ->with('outgoingTransitions')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
}