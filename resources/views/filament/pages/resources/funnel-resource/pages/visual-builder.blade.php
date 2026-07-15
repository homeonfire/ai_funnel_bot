<x-filament-panels::page>
    <!-- Подключаем Drawflow -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.css">
    <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.js"></script>
    
    <style>
        /* Задаем размеры холста */
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
        /* Стилизуем карточки шагов */
        .drawflow .drawflow-node {
            background: var(--white);
            border: 2px solid var(--primary-500);
            border-radius: 8px;
            padding: 15px;
            color: var(--gray-900);
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

                initDrawflow() {
                    const id = document.getElementById("drawflow");
                    this.editor = new Drawflow(id);
                    this.editor.reroute = true;
                    
                    // Запускаем
                    this.editor.start();

                    // Пример: загружаем стартовый узел
                    this.editor.addNode('start', 0, 1, 150, 200, 'start', {}, 'Приветствие (/start)');
                },

                addNode() {
                    // addNode(name, inputs, outputs, posx, posy, class, data, html)
                    this.editor.addNode(
                        'step', 
                        1, 
                        1, 
                        400, 
                        200, 
                        'step-node', 
                        {}, 
                        '<div><strong>Новый этап</strong><br><small>Нажмите для ред.</small></div>'
                    );
                },

                saveData() {
                    const data = this.editor.export();
                    console.log('Схема для сохранения в базу:', data);
                    // В будущем здесь будет вызов Livewire метода: $wire.saveNodes(data)
                }
            }))
        })
    </script>
</x-filament-panels::page>