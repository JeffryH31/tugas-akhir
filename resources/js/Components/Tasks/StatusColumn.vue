<script setup>
/**
 * Status Column Component - Kanban Board Column
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import TaskCard from './TaskCard.vue';

const props = defineProps({
    status: {
        type: Object,
        required: true,
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    workspace: {
        type: Object,
        required: true,
    },
    space: {
        type: Object,
        required: true,
    },
    list: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['task-moved', 'task-complete', 'task-open', 'add-task']);

// Drag state
const isDragging = ref(false);

// Add task form
const showAddTask = ref(false);
const newTaskName = ref('');

// Task count
const taskCount = computed(() => props.tasks.length);

// Handle drag change
const onDragChange = (evt) => {
    if (evt.added) {
        emit('task-moved', {
            task: evt.added.element,
            statusId: props.status.id,
            newIndex: evt.added.newIndex,
        });
    } else if (evt.moved) {
        emit('task-moved', {
            task: evt.moved.element,
            statusId: props.status.id,
            newIndex: evt.moved.newIndex,
        });
    }
};

// Add new task
const addTask = () => {
    if (!newTaskName.value.trim()) return;
    
    emit('add-task', {
        name: newTaskName.value.trim(),
        status_id: props.status.id,
    });
    
    newTaskName.value = '';
    showAddTask.value = false;
};

// Cancel add task
const cancelAddTask = () => {
    newTaskName.value = '';
    showAddTask.value = false;
};

// Handle task complete
const handleTaskComplete = (task) => {
    emit('task-complete', task);
};

// Handle task open
const handleTaskOpen = (task) => {
    emit('task-open', task);
};
</script>

<template>
    <div class="status-column">
        <!-- Column Header -->
        <div class="column-header">
            <div class="flex items-center gap-2">
                <div 
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: status.color }"
                />
                <span class="font-medium text-sm">{{ status.name }}</span>
                <span class="text-xs text-gray-500 ml-1">{{ taskCount }}</span>
            </div>
            
            <div class="column-actions">
                <v-btn
                    icon
                    variant="text"
                    size="x-small"
                    @click="showAddTask = true"
                >
                    <v-icon size="16">mdi-plus</v-icon>
                </v-btn>
                <v-menu>
                    <template v-slot:activator="{ props: menuProps }">
                        <v-btn
                            v-bind="menuProps"
                            icon
                            variant="text"
                            size="x-small"
                        >
                            <v-icon size="16">mdi-dots-horizontal</v-icon>
                        </v-btn>
                    </template>
                <v-card color="surface">
                    <v-list density="compact">
                        <v-list-item prepend-icon="mdi-pencil-outline" title="Edit Status" />
                        <v-list-item prepend-icon="mdi-palette-outline" title="Change Color" />
                        <v-divider />
                        <v-list-item prepend-icon="mdi-delete-outline" title="Delete Status" class="text-error" />
                    </v-list>
                </v-card>
                </v-menu>
            </div>
        </div>

        <!-- Tasks Container -->
        <div class="column-content">
            <!-- Add Task Form -->
            <div v-if="showAddTask" class="add-task-form mb-2">
                <v-card variant="outlined" rounded="lg">
                    <v-card-text class="pa-2">
                        <v-text-field
                            v-model="newTaskName"
                            placeholder="Task name"
                            variant="plain"
                            density="compact"
                            hide-details
                            autofocus
                            @keydown.enter="addTask"
                            @keydown.escape="cancelAddTask"
                        />
                        <div class="flex items-center gap-2 mt-2">
                            <v-btn
                                color="primary"
                                size="small"
                                variant="flat"
                                @click="addTask"
                            >
                                Save
                            </v-btn>
                            <v-btn
                                size="small"
                                variant="text"
                                @click="cancelAddTask"
                            >
                                Cancel
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Draggable Tasks -->
            <draggable
                :list="tasks"
                group="tasks"
                item-key="id"
                :animation="200"
                ghost-class="task-ghost"
                drag-class="task-dragging"
                class="tasks-list"
                @change="onDragChange"
                @start="isDragging = true"
                @end="isDragging = false"
            >
                <template #item="{ element }">
                    <div class="task-wrapper">
                        <TaskCard
                            :task="element"
                            @complete="handleTaskComplete"
                            @open-detail="handleTaskOpen"
                        />
                    </div>
                </template>
            </draggable>

            <!-- Add Task Button (when form is hidden) -->
            <v-btn
                v-if="!showAddTask"
                variant="text"
                block
                class="add-task-btn mt-2"
                @click="showAddTask = true"
            >
                <v-icon start size="16">mdi-plus</v-icon>
                Add Task
            </v-btn>
        </div>
    </div>
</template>

<style scoped>
.status-column {
    width: 300px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background-color: #1a1a1a;
    border-radius: 8px;
    max-height: calc(100vh - 180px);
}

.column-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid #2d2d30;
}

.column-actions {
    display: flex;
    gap: 2px;
    opacity: 0;
    transition: opacity 0.15s;
}

.status-column:hover .column-actions {
    opacity: 1;
}

.column-content {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 50px;
}

.task-wrapper {
    cursor: grab;
}

.task-wrapper:active {
    cursor: grabbing;
}

.task-ghost {
    opacity: 0.5;
}

.task-dragging {
    transform: rotate(2deg);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}

.add-task-btn {
    justify-content: flex-start;
    opacity: 0.6;
}

.add-task-btn:hover {
    opacity: 1;
}

.add-task-form :deep(.v-field) {
    background-color: transparent;
}
</style>
