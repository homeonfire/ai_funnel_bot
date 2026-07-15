<?php

namespace App\Filament\Resources\FunnelResource\Pages;

use App\Filament\Resources\FunnelResource;
use App\Models\Funnel;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VisualBuilder extends Page
{
    protected static string $resource = FunnelResource::class;

    protected static string $view = 'filament.resources.funnel-resource.pages.visual-builder';

    // Скрываем страницу из бокового меню, так как мы будем заходить на неё из таблицы
    protected static bool $shouldRegisterNavigation = false;

    public Funnel $record;

    public function mount(Funnel $record): void
    {
        $this->record = $record;
    }

    // Меняем заголовок страницы
    public function getTitle(): string | Htmlable
    {
        return 'Визуальный редактор: ' . $this->record->name;
    }
}