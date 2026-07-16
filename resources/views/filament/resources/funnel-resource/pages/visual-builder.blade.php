<x-filament-panels::page>
    @vite('resources/js/vue-builder.js')
    
    <div 
        x-data="vueFlowApp()" 
        class="w-full"
        @save-funnel-data.window="$wire.saveGraph($event.detail)"
        @open-step-settings.window="$wire.mountAction('editStep', { step_id: $event.detail.step_id })" 
    >
        <div class="mb-4 flex gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <x-filament::button 
                icon="heroicon-o-plus" 
                x-on:click="$dispatch('request-add-node')"
            >
                Добавить этап
            </x-filament::button>

            <x-filament::button 
                color="success" 
                icon="heroicon-o-check" 
                x-on:click="$dispatch('request-save-graph')"
            >
                Сохранить схему
            </x-filament::button>
        </div>

        <div wire:ignore>
            <div id="vue-flow-root" data-steps="{{ json_encode($this->stepsData) }}"></div>
        </div>
    </div>
</x-filament-panels::page>