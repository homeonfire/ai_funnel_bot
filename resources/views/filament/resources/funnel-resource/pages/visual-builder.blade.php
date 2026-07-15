<x-filament-panels::page>
    <!-- Подключаем Drawflow -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.js"></script>
    
    <style>
        /* 1. Сетка как в современных no-code редакторах */
        #drawflow {
            position: relative;
            width: 100%;
            height: 700px;
            background-color: #f8fafc; /* Светло-серый фон */
            background-image: radial-gradient(#cbd5e1 1.5px, transparent 1.5px); /* Точечки */
            background-size: 25px 25px;
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
        }
        .dark #drawflow {
            background-color: #0f172a;
            background-image: radial-gradient(#334155 1.5px, transparent 1.5px);
            border-color: var(--gray-800);
        }

        /* 2. Убираем дефолтную уродливую рамку Drawflow и делаем контейнер прозрачным */
        .drawflow .drawflow-node {
            background: transparent;
            border: none;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); /* Красивая тень */
            padding: 0;
            width: 280px;
            border-radius: 0.75rem;
        }
        
        /* 3. Кастомный дизайн самой карточки (Внутри Drawflow) */
        .custom-node {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .dark .custom-node {
            background: var(--gray-900);
            border-color: var(--gray-700);
        }

        /* 4. Стили точек входа и выхода (кружочки коннекторов) */
        .drawflow .drawflow-node .input, 
        .drawflow .drawflow-node .output {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid var(--white);
            background: var(--primary-500); /* Используем главный цвет твоей темы Filament */
            box-shadow: 0 0 0 2px var(--gray-200);
            transition: transform 0.2s;
        }
        .drawflow .drawflow-node .input:hover, 
        .drawflow .drawflow-node .output:hover {
            transform: scale(1.2);
            cursor: crosshair;
        }
        .dark .drawflow .drawflow-node .input, 
        .dark .drawflow .drawflow-node .output {
            border-color: var(--gray-900);
            box-shadow: 0 0 0 2px var(--gray-700);
        }
        /* Сдвигаем точки чуть за край карточки */
        .drawflow .drawflow-node .input { left: -10px; }
        .drawflow .drawflow-node .output { right: -10px; }

        /* 5. Красивые плавные линии связей */
        .drawflow .connection .main-path {
            stroke: var(--primary-500);
            stroke-width: 3px;
        }
        .drawflow .connection .main-path:hover {
            stroke: var(--primary-600);
            stroke-width: 4px;
            cursor: pointer;
        }
    </style>

    <div 
        x-data="visualBuilder()"
        x-init="initDrawflow()"
        class="w-full"
    >
        <!-- Верхняя панель управления -->
        <div class="mb-4 flex gap-4 items-center bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <x-filament::button x-on:click="addNode()" icon="heroicon-o-plus">
                Добавить этап
            </x-filament::button>
            <x-filament::button color="success" x-on:click="saveData()" icon="heroicon-o-check">
                Сохранить схему
            </x-filament::button>
            <div class="ml-auto text-sm text-gray-500">
                Колесико мыши — масштаб, ПКМ — удалить связь
            </div>
        </div>

        <!-- Сам холст -->
        <div id="drawflow"></div>
    </div>

    <!-- Логика и генерация красивого HTML -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('visualBuilder', () => ({
                editor: null,
                stepsData: @json($this->stepsData), 

                initDrawflow() {
                    const id = document.getElementById("drawflow");
                    this.editor = new Drawflow(id);
                    this.editor.reroute = true;
                    this.editor.start();
                    this.loadNodesFromDb();
                },

                // Метод для создания красивого HTML узла
                getNodeHtml(title, dbId, transitionsCount) {
                    // Используем inline-стили на основе CSS переменных Filament, чтобы не зависеть от JIT компилятора Tailwind
                    return `
                        <div class="custom-node">
                            <!-- Шапка -->
                            <div style="background: var(--gray-50); padding: 12px 16px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center;" class="dark:bg-gray-800 dark:border-gray-700">
                                <strong style="color: var(--gray-900); font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;" class="dark:text-white">${title}</strong>
                                <span style="background: var(--primary-100); color: var(--primary-700); padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: bold;" class="dark:bg-primary-900 dark:text-primary-300">ID: ${dbId || 'New'}</span>
                            </div>
                            <!-- Тело -->
                            <div style="padding: 16px;">
                                <div style="color: var(--gray-500); font-size: 12px; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
                                    <svg style="width: 14px; height: 14px; color: var(--primary-500);" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    <span>Условий перехода: <b style="color: var(--gray-700);" class="dark:text-gray-300">${transitionsCount}</b></span>
                                </div>
                                
                                <!-- Кнопка настройки -->
                                <button type="button" style="width: 100%; background: var(--white); border: 1px solid var(--gray-200); color: var(--gray-700); padding: 8px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary-500)'; this.style.color='var(--primary-600)'" onmouseout="this.style.borderColor='var(--gray-200)'; this.style.color='var(--gray-700)'" class="dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300">
                                    Настроить этап ⚙️
                                </button>
                            </div>
                        </div>
                    `;
                },

                loadNodesFromDb() {
                    let dbIdToNodeId = {};
                    let posX = 80;

                    this.stepsData.forEach((step, index) => {
                        let posY = 150 + (index % 2 === 0 ? 0 : 120);
                        let transCount = step.outgoing_transitions ? step.outgoing_transitions.length : 0;
                        
                        let html = this.getNodeHtml(step.name, step.id, transCount);

                        let nodeId = this.editor.addNode('step', 1, 1, posX, posY, 'step-node', { db_id: step.id }, html);
                        dbIdToNodeId[step.id] = nodeId;
                        posX += 340; 
                    });

                    this.stepsData.forEach(step => {
                        let fromNodeId = dbIdToNodeId[step.id];
                        if (step.outgoing_transitions && step.outgoing_transitions.length > 0) {
                            step.outgoing_transitions.forEach(transition => {
                                let toNodeId = dbIdToNodeId[transition.to_step_id];
                                if (fromNodeId && toNodeId) {
                                    this.editor.addConnection(fromNodeId, toNodeId, 'output_1', 'input_1');
                                }
                            });
                        }
                    });
                },

                addNode() {
                    let html = this.getNodeHtml('Новый этап', null, 0);
                    this.editor.addNode('step', 1, 1, 100, 100, 'step-node', { db_id: null }, html);
                },

                saveData() {
                    const data = this.editor.export();
                    console.log('Схема для сохранения в базу:', data);
                }
            }))
        })
    </script>
</x-filament-panels::page>