<?php

namespace App\Filament\Resources\FunnelResource\Pages;

use App\Filament\Resources\FunnelResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use App\Models\Step;

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

    // Отдаем шаги и переходы на фронтенд
    public function getStepsDataProperty(): array
    {
        return $this->record->steps()
            ->with('outgoingTransitions')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    // Сохраняем новые координаты и пересобираем связи
    public function saveGraph(array $data)
    {
        $nodeIdMapping = []; // Маппинг временных ID из Vue в реальные ID из базы

        // 1. Сохраняем узлы (карточки)
        if (isset($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $node) {
                $isNew = $node['data']['db_id'] === 'new';

                if ($isNew) {
                    $step = $this->record->steps()->create([
                        'name' => $node['data']['label'],
                        'pos_x' => $node['position']['x'],
                        'pos_y' => $node['position']['y'],
                    ]);
                    $nodeIdMapping[$node['id']] = $step->id; 
                } else {
                    $step = $this->record->steps()->find($node['data']['db_id']);
                    if ($step) {
                        $step->update([
                            'pos_x' => $node['position']['x'],
                            'pos_y' => $node['position']['y'],
                        ]);
                        $nodeIdMapping[$node['id']] = $step->id;
                    }
                }
            }
        }

        // 2. Пересобираем связи
        $stepIds = $this->record->steps()->pluck('id');
        
        // ВАЖНО: Убедись, что твоя таблица со связями называется именно 'transitions'
        // и колонки называются 'from_step_id' и 'to_step_id'
        DB::table('transitions')->whereIn('from_step_id', $stepIds)->delete();

        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as $edge) {
                $fromId = $nodeIdMapping[$edge['source']] ?? $edge['source'];
                $toId = $nodeIdMapping[$edge['target']] ?? $edge['target'];

                if ($fromId && $toId) {
                    DB::table('transitions')->insert([
                        'from_step_id' => $fromId,
                        'to_step_id' => $toId,
                    ]);
                }
            }
        }

        Notification::make()
            ->title('Воронка успешно сохранена')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editStep')
                ->hidden() // Прячем саму кнопку, так как вызываем экшен программно из JS
                ->slideOver() // Делаем боковую панель вместо модального окна по центру
                ->modalHeading('Настройка этапа')
                ->modalWidth('md')
                ->form([
                    Section::make('Базовые настройки')->schema([
                        TextInput::make('name')
                            ->label('Название этапа внутри воронки')
                            ->required(),
                    ]),
                    // Позже добавим сюда поля для AI промптов и текста сообщения
                ])
                // Заполняем форму данными из базы при открытии
                ->fillForm(function (array $arguments): array {
                    if (empty($arguments['step_id'])) return [];
                    
                    $step = Step::find($arguments['step_id']);
                    return [
                        'name' => $step?->name,
                    ];
                })
                // Сохраняем данные в базу
                ->action(function (array $data, array $arguments): void {
                    $step = Step::find($arguments['step_id']);
                    if ($step) {
                        $step->update($data);
                    }
                    
                    Notification::make()
                        ->title('Настройки этапа сохранены')
                        ->success()
                        ->send();
                        
                    // Перезагружаем страницу, чтобы обновить имя узла прямо на холсте
                    $this->redirect(request()->header('Referer')); 
                }),
        ];
    }
}