<x-filament-panels::page>
    <!-- Подключаем Drawflow -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.js"></script>
    
    <style>
        #drawflow {
            position: relative;
            width: 100%;
            height: 600px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
        }
        .dark #drawflow {
            background: var(--gray-900);
            border-color: var(--gray-700);
        }
        .drawflow .drawflow-node {
            background: var(--white);
            border: 2px solid var(--primary-500);
            border-radius: 8px;
            padding: 15px;
            color: var(--gray-900);
            width: 250px;
        }
        .dark .drawflow .drawflow-node {
            background: var(--gray-800);
            color: var(--white);
        }
    </style>

    <!-- Обертка Alpine.js -->
    <div 
        x-data="visualBuilder()"
        x-init="initDrawflow()"
        class="w-full"
    >
        <div class="mb-4 flex gap-4">
            <x-filament::button x-on:click="addNode()">
                + Добавить этап
            </x-filament::button>
            <x-filament::button color="success" x-on:click="saveData()">
                Сохранить схему
            </x-filament::button>
        </div>

        <!-- Сам холст -->
        <div id="drawflow"></div>
    </div>

    <!-- Логика -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('visualBuilder', () => ({
                editor: null,
                // Получаем массив этапов и связей напрямую из Livewire PHP
                stepsData: @json($this->stepsData), 

                initDrawflow() {
                    const id = document.getElementById("drawflow");
                    this.editor = new Drawflow(id);
                    this.editor.reroute = true;
                    this.editor.start();

                    this.loadNodesFromDb();
                },

                loadNodesFromDb() {
                    let dbIdToNodeId = {};
                    let posX = 50;

                    // 1. Создаем узлы (блоки этапов)
                    this.stepsData.forEach((step, index) => {
                        let html = `
                            <div>
                                <strong class="text-lg">${step.name}</strong><br>
                                <small class="text-gray-500">ID в базе: ${step.id}</small>
                            </div>
                        `;

                        // Смещаем блоки лесенкой для красоты
                        let posY = 150 + (index % 2 === 0 ? 0 : 100);

                        // addNode(name, inputs, outputs, posx, posy, class, data, html)
                        let nodeId = this.editor.addNode(
                            'step', 
                            1, // 1 точка входа
                            1, // 1 точка выхода
                            posX, 
                            posY, 
                            'step-node', 
                            { db_id: step.id }, 
                            html
                        );

                        // Запоминаем, какой внутренний ID выдал Drawflow для нашего ID из базы
                        dbIdToNodeId[step.id] = nodeId;
                        posX += 300; 
                    });

                    // 2. Рисуем связи (стрелочки переходов)
                    this.stepsData.forEach(step => {
                        let fromNodeId = dbIdToNodeId[step.id];

                        if (step.outgoing_transitions && step.outgoing_transitions.length > 0) {
                            step.outgoing_transitions.forEach(transition => {
                                let toNodeId = dbIdToNodeId[transition.to_step_id];

                                if (fromNodeId && toNodeId) {
                                    // Протягиваем стрелочку: от выхода 1-го узла ко входу 2-го
                                    this.editor.addConnection(fromNodeId, toNodeId, 'output_1', 'input_1');
                                }
                            });
                        }
                    });
                },

                addNode() {
                    this.editor.addNode(
                        'step', 
                        1, 
                        1, 
                        400, 
                        200, 
                        'step-node', 
                        { db_id: null }, 
                        '<div><strong>Новый этап</strong><br><small>Нажмите для ред.</small></div>'
                    );
                },

                saveData() {
                    const data = this.editor.export();
                    console.log('Схема для сохранения в базу:', data);
                }
            }))
        })
    </script>
</x-filament-panels::page>