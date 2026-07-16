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
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use App\Filament\Resources\StepResource;
use Filament\Forms\Form;    

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
    $nodeIdMapping = []; 

    // 1. Сохраняем узлы
    foreach ($data['nodes'] as $node) {
        $dbId = $node['data']['db_id'];
        
        if ($dbId === 'new') {
            $step = $this->record->steps()->create([
                'name' => $node['data']['label'],
                'pos_x' => $node['position']['x'],
                'pos_y' => $node['position']['y'],
            ]);
            $nodeIdMapping[$node['id']] = $step->id;
        } else {
            $step = $this->record->steps()->find($dbId);
            if ($step) {
                $step->update([
                    'pos_x' => $node['position']['x'],
                    'pos_y' => $node['position']['y'],
                ]);
                $nodeIdMapping[$node['id']] = $step->id;
            }
        }
    }

    // 2. Пересобираем связи
    $stepIds = $this->record->steps()->pluck('id');
    DB::table('transitions')->whereIn('from_step_id', $stepIds)->delete();

    if (isset($data['edges']) && is_array($data['edges'])) {
        $transitionsToInsert = [];
        
        foreach ($data['edges'] as $edge) {
            $fromId = $nodeIdMapping[$edge['source']] ?? $edge['source'];
            $toId = $nodeIdMapping[$edge['target']] ?? $edge['target'];

            if ($fromId && $toId) {
                $transitionsToInsert[] = [
                    'from_step_id'     => $fromId,
                    'to_step_id'       => $toId,
                    'logical_operator' => 'AND', // Соответствует миграции
                    'rules'            => json_encode([]), // Соответствует json типу
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
        }
        
        if (!empty($transitionsToInsert)) {
            DB::table('transitions')->insert($transitionsToInsert);
        }
    }

    Notification::make()->title('Схема успешно сохранена')->success()->send();
}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editStep')
                ->extraAttributes(['style' => 'display: none;']) 
                ->slideOver()
                ->modalHeading('Настройка этапа')
                ->modalWidth('3xl') // Можно сделать пошире под твою форму
                
                // 1. МАГИЯ ЗДЕСЬ: Принудительно говорим Filament, что форма работает с моделью Step
                ->form(fn (Form $form) => StepResource::form($form->model(Step::class)))
                
                // 2. Заполняем форму
                ->fillForm(function (array $arguments): array {
                    if (empty($arguments['step_id'])) return [];
                    
                    $step = Step::find($arguments['step_id']);
                    // Используем toArray(), чтобы подтянуть и базовые поля, и связи (если они есть в форме)
                    return $step ? $step->toArray() : []; 
                })
                
                // 3. Сохраняем изменения
                ->action(function (array $data, array $arguments): void {
                    $step = Step::find($arguments['step_id']);
                    if ($step) {
                        $step->update($data);
                    }
                    
                    Notification::make()
                        ->title('Настройки этапа сохранены')
                        ->success()
                        ->send();
                        
                    $this->redirect(request()->header('Referer')); 
                }),
        ];
    }
}