import { createApp } from 'vue';
import FunnelBuilder from './components/FunnelBuilder.vue';

document.addEventListener('alpine:init', () => {
    Alpine.data('vueFlowApp', () => ({
        init() {
            const container = document.getElementById('vue-flow-root');
            if (container) {
                // Достаем JSON из атрибута data
                const rawData = container.getAttribute('data-steps');
                const stepsData = JSON.parse(rawData || '[]');

                // Монтируем Vue прямо внутрь Livewire/Filament
                const app = createApp(FunnelBuilder, { initialData: stepsData });
                app.mount('#vue-flow-root');
            }
        }
    }));
});
