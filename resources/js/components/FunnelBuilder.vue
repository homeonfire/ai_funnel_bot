<template>
    <div class="vue-flow-wrapper">
        <VueFlow
            v-model:nodes="nodes"
            v-model:edges="edges"
            :default-viewport="{ zoom: 1 }"
            :min-zoom="0.2"
            :max-zoom="4"
            @connect="onConnect"
            @edge-double-click="onEdgeDoubleClick"
            @node-click="onNodeClick" 
        >
            <!-- Кастомный дизайн узла (в стиле n8n) -->
            <template #node-custom="props">
    <div class="n8n-node">
        <!-- Точка входа -->
        <Handle type="target" position="left" class="n8n-handle" />

        <div class="n8n-node-header">
            <div class="n8n-icon">⚡️</div>
            <div class="n8n-title">{{ props.data.label }}</div>
        </div>

        <div class="n8n-node-body">
            <div class="n8n-subtitle">ID: {{ props.data.db_id }}</div>
        </div>

        <!-- ГЕНЕРАЦИЯ ТОЧЕК ВЫХОДА -->
        <div class="n8n-node-outputs">
            <div v-for="(trans, index) in props.data.transitions" :key="index" class="n8n-output-row">
                <span class="n8n-output-label">{{ trans.label || 'Ветка ' + (index + 1) }}</span>
                <!-- Каждому переходу - свой уникальный Handle -->
                <Handle 
                    type="source" 
                    position="right" 
                    :id="trans.id.toString()" 
                    :style="{ top: (20 + (index * 30)) + 'px' }" 
                    class="n8n-handle" 
                />
            </div>
        </div>
    </div>
</template>

            <Background pattern-color="#cbd5e1" :gap="24" :size="2" />
            <Controls position="bottom-right" />
        </VueFlow>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { Handle } from '@vue-flow/core';

import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

const props = defineProps({
    initialData: {
        type: Array,
        required: true,
        default: () => []
    }
});

const nodes = ref([]);
const edges = ref([]);

// Функция: Добавление нового этапа на холст
const addNewNode = () => {
    const newId = `new_${Date.now()}`; // Временный ID до сохранения в базу
    nodes.value.push({
        id: newId,
        type: 'custom',
        position: { x: 250, y: 250 },
        data: {
            label: 'Новый этап',
            db_id: 'new',
            transitions: 0
        },
    });
};

// Функция: Обработка клика по карточке этапа (вызов шторки Filament)
// Правильная обработка клика через событие Vue Flow
const onNodeClick = (event) => {
    // В Vue Flow данные лежат внутри объекта event.node
    const nodeData = event.node.data; 
    
    console.log('✅ Клик пойман! Открываем настройки для ID:', nodeData.db_id);
    
    if (nodeData.db_id === 'new') {
        alert('Сначала сохраните схему, чтобы настроить этот этап!');
        return;
    }

    // Отправляем ID этапа в Livewire
    window.dispatchEvent(new CustomEvent('open-step-settings', { 
        detail: { step_id: nodeData.db_id } 
    }));
};

// Функция: Обработка новой связи (когда тянешь стрелочку)
const onConnect = (params) => {
    edges.value.push({
        id: `e${params.source}-${params.target}`,
        source: params.source,
        target: params.target,
        animated: true,
        style: { stroke: '#3b82f6', strokeWidth: 2 },
    });
};

// Функция: Удаление связи по двойному клику
const onEdgeDoubleClick = (event) => {
    if (confirm('Удалить эту связь?')) {
        edges.value = edges.value.filter(e => e.id !== event.edge.id);
    }
};

// Функция: Сбор данных и отправка их обратно в Livewire
const emitSaveData = () => {
    const graphData = {
        nodes: nodes.value,
        edges: edges.value
    };
    // Отправляем событие наружу, чтобы Alpine его поймал
    window.dispatchEvent(new CustomEvent('save-funnel-data', { detail: graphData }));
};

onMounted(() => {
    // Временные массивы
    const tempNodes = [];
    const tempEdges = [];

    // ШАГ 1: Сначала собираем ВСЕ карточки на холсте
    props.initialData.forEach((step, index) => {
        let posX = step.pos_x !== undefined && step.pos_x !== null ? parseFloat(step.pos_x) : 100 + (index * 350);
        let posY = step.pos_y !== undefined && step.pos_y !== null ? parseFloat(step.pos_y) : 150 + (index % 2 === 0 ? 0 : 80);

        tempNodes.push({
            id: step.id.toString(),
            type: 'custom',
            position: { x: posX, y: posY },
            data: {
                label: step.name,
                db_id: step.id,
                transitions: step.outgoing_transitions ? step.outgoing_transitions.length : 0
            },
        });
    });

    // ШАГ 2: Только когда все карточки созданы, перебираем и добавляем связи
    props.initialData.forEach((step) => {
        if (step.outgoing_transitions) {
            step.outgoing_transitions.forEach(trans => {
                tempEdges.push({
                    id: `e${step.id}-${trans.to_step_id}`,
                    source: step.id.toString(),
                    target: trans.to_step_id.toString(),
                    animated: true,
                    style: { stroke: '#3b82f6', strokeWidth: 2 },
                });
            });
        }
    });

    // Разом отдаем данные Vue (чтобы избежать лишних перерисовок)
    nodes.value = tempNodes;
    edges.value = tempEdges;

    // Подписываемся на события от кнопок Filament
    window.addEventListener('request-add-node', addNewNode);
    window.addEventListener('request-save-graph', emitSaveData);
});

onUnmounted(() => {
    // Убираем слушатели при закрытии страницы
    window.removeEventListener('request-add-node', addNewNode);
    window.removeEventListener('request-save-graph', emitSaveData);
});
</script>

<style scoped>
.vue-flow-wrapper {
    width: 100%;
    height: 700px;
    background-color: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

/* Стили карточки под n8n */
.n8n-node {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 240px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    transition: box-shadow 0.2s, border-color 0.2s;
    cursor: pointer; /* Добавили курсор-руку при наведении */
}

.n8n-node:hover {
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    border-color: #94a3b8;
}

/* Заголовок с иконкой */
.n8n-node-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    background: linear-gradient(to right, #ffffff, #f8fafc);
}

.n8n-icon {
    background: #eff6ff;
    color: #3b82f6;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    margin-right: 12px;
}

.n8n-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Тело карточки */
.n8n-node-body {
    padding: 12px 16px;
}

.n8n-subtitle {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 4px;
}

.n8n-text {
    font-size: 12px;
    color: #475569;
}

/* Кастомные точки входа/выхода */
.n8n-handle {
    width: 12px;
    height: 12px;
    background: white;
    border: 2px solid #cbd5e1;
    transition: border-color 0.2s, background-color 0.2s;
}
.n8n-handle:hover {
    border-color: #3b82f6;
    background: #eff6ff;
    width: 14px;
    height: 14px;
}
</style>