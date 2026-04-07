<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskCard from '@/Components/Tasks/TaskCard.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    workspace: Object,
    space: Object,
    list: Object,
    sprint: Object,
    backlogSubtasks: Array,
    statistics: Object,
    burndown: Object,
    statuses: Array,
    labels: Array,
    members: Array,
});

const { confirm: confirmDialog } = useConfirmDialog();
const draggingSubtaskId = ref(null);
const draggingSource = ref(null);
const activeDropZone = ref(null);
const isMoving = ref(false);
const showDetail = ref(false);
const detailTask = ref(null);
const detailParentTask = ref(null);
const detailList = ref(null);

const isSprintActive = computed(() => {
    return !!props.sprint?.is_active;
});

const isSprintCompleted = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(props.sprint.end_date);
    return !props.sprint?.is_active && today > end;
});

const validBacklogSubtasks = computed(() => (props.backlogSubtasks || []).filter((s) => !!s.task));
const validSprintSubtasks = computed(() => (props.sprint?.subtasks || []).filter((s) => !!s.task));
const activeListId = computed(() => props.list?.id || props.sprint?.task_list_id || null);

const goToSprintIndex = () => {
    if (!activeListId.value) {
        router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
        return;
    }

    router.visit(route('lists.show', {
        workspace: props.workspace.id,
        space: props.space.id,
        list: activeListId.value,
        view: 'sprint',
        sprint_id: props.sprint.id,
    }));
};

const startSprint = async () => {
    const ok = await confirmDialog('Start this sprint now?', 'Start Sprint');
    if (!ok) return;

    router.post(route('sprints.start', [props.workspace.id, props.space.id, props.sprint.id]), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['sprint', 'statistics', 'burndown'] });
            window.showSnackbar?.('Sprint started!', 'success');
        },
    });
};

const completeSprint = async () => {
    const ok = await confirmDialog('Complete this sprint?', 'Complete Sprint');
    if (!ok) return;

    router.post(route('sprints.complete', [props.workspace.id, props.space.id, props.sprint.id]), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['sprint', 'statistics', 'burndown'] });
            window.showSnackbar?.('Sprint completed!', 'success');
        },
    });
};

const openTask = (subtask) => {
    const parentTask = subtask.task;
    if (!parentTask) {
        window.showSnackbar?.('Task detail is unavailable because its parent task no longer exists.', 'error');
        return;
    }
    detailTask.value = subtask;
    detailParentTask.value = parentTask;
    detailList.value = { id: parentTask.task_list_id };
    showDetail.value = true;
};

const addTaskToSprint = (task) => {
    if (isMoving.value) return;
    isMoving.value = true;

    router.post(
        route('sprints.tasks.add', [props.workspace.id, props.space.id, props.sprint.id]),
        { subtask_id: task.id },
        {
            preserveScroll: true,
            onFinish: () => {
                isMoving.value = false;
                router.reload({ only: ['sprint', 'backlogSubtasks', 'statistics', 'burndown'] });
                window.showSnackbar('Task added to sprint!', 'success');
            },
        }
    );
};

const removeTaskFromSprint = (task) => {
    if (isMoving.value) return;
    isMoving.value = true;

    router.delete(
        route('sprints.tasks.remove', [props.workspace.id, props.space.id, props.sprint.id]),
        {
            data: { subtask_id: task.id },
            preserveScroll: true,
            onFinish: () => {
                isMoving.value = false;
                router.reload({ only: ['sprint', 'backlogSubtasks', 'statistics', 'burndown'] });
                window.showSnackbar('Task removed from sprint!', 'success');
            },
        }
    );
};

const onDragStart = (subtask, source, event) => {
    draggingSubtaskId.value = subtask.id;
    draggingSource.value = source;
    event.dataTransfer.effectAllowed = 'move';
};

const onDragEnd = () => {
    draggingSubtaskId.value = null;
    draggingSource.value = null;
    activeDropZone.value = null;
};

const onDragOverZone = (zone) => {
    if (!draggingSubtaskId.value) return;
    activeDropZone.value = zone;
};

const onDropToSprint = () => {
    if (!draggingSubtaskId.value || draggingSource.value !== 'backlog') {
        activeDropZone.value = null;
        return;
    }

    const subtask = validBacklogSubtasks.value.find((item) => item.id === draggingSubtaskId.value);
    if (subtask) addTaskToSprint(subtask);
    activeDropZone.value = null;
};

const onDropToBacklog = () => {
    if (!draggingSubtaskId.value || draggingSource.value !== 'sprint') {
        activeDropZone.value = null;
        return;
    }

    const sprintSubtasks = validSprintSubtasks.value;
    const subtask = sprintSubtasks.find((item) => item.id === draggingSubtaskId.value);
    if (subtask) removeTaskFromSprint(subtask);
    activeDropZone.value = null;
};
</script>

<template>
    <MainLayout :title="`${sprint.name} - ${list?.name || space.name}`">
        <div class="h-full flex flex-col bg-[#1e1e1e]">
            <!-- Header -->
            <div class="border-b border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <v-btn icon="mdi-arrow-left" variant="text" @click="goToSprintIndex" />
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-white">{{ sprint.name }}</h1>
                                <v-chip v-if="isSprintActive" color="success" size="small">Active</v-chip>
                                <v-chip v-else-if="isSprintCompleted" color="default" size="small">Completed</v-chip>
                                <v-chip v-else color="info" size="small">Planned</v-chip>
                            </div>
                            <p v-if="list?.name" class="text-gray-500 text-xs mt-1">Product: {{ list.name }}</p>
                            <p v-if="sprint.goal" class="text-gray-400 text-sm mt-1">{{ sprint.goal }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <v-btn v-if="!isSprintActive && !isSprintCompleted" color="success" prepend-icon="mdi-play"
                            @click="startSprint">
                            Start Sprint
                        </v-btn>
                        <v-btn v-if="isSprintActive" color="warning" prepend-icon="mdi-check" @click="completeSprint">
                            Complete Sprint
                        </v-btn>
                    </div>
                </div>

                <!-- Sprint Statistics -->
                <div class="grid grid-cols-5 gap-4 mt-6">
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Tasks</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ statistics.total_subtasks }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Completed</div>
                        <div class="text-2xl font-bold text-success mt-1">{{ statistics.completed_subtasks }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">In Progress</div>
                        <div class="text-2xl font-bold text-info mt-1">{{ statistics.in_progress_subtasks }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Completion Rate</div>
                        <div class="text-2xl font-bold text-primary mt-1">{{ statistics.completion_rate }}%</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Days Remaining</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ statistics.remaining_days }}</div>
                    </div>
                </div>
            </div>

            <!-- Sprint Board -->
            <div class="flex-1 overflow-auto p-6">
                <div class="grid grid-cols-2 gap-6 h-full">
                    <!-- Backlog Column -->
                    <div class="flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">
                                Backlog
                                <span class="text-gray-400 text-sm ml-2">({{ validBacklogSubtasks.length }})</span>
                            </h3>
                        </div>
                        <div class="flex-1 bg-[#2D2D2D] rounded-lg p-4 overflow-auto drop-zone"
                            :class="{ 'drop-zone--active': activeDropZone === 'backlog' }"
                            @dragover.prevent="onDragOverZone('backlog')" @drop.prevent="onDropToBacklog">
                            <div class="space-y-2">
                                <div v-for="subtask in validBacklogSubtasks" :key="subtask.id" class="relative"
                                    :class="{ 'drag-item--dragging': draggingSubtaskId === subtask.id }"
                                    draggable="true" @dragstart="onDragStart(subtask, 'backlog', $event)"
                                    @dragend="onDragEnd">
                                    <TaskCard :task="subtask" :show-checkbox="false" @open-detail="openTask" />
                                    <v-btn icon="mdi-plus" size="x-small" variant="text" color="primary"
                                        class="!absolute top-2 right-2 z-10" @click.stop="addTaskToSprint(subtask)" />
                                </div>
                                <div v-if="validBacklogSubtasks.length === 0" class="text-center py-8 text-gray-500">
                                    <p>No tasks in backlog</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sprint Tasks Column -->
                    <div class="flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">
                                Sprint Tasks
                                <span class="text-gray-400 text-sm ml-2">({{ validSprintSubtasks.length }})</span>
                            </h3>
                        </div>
                        <div class="flex-1 bg-[#2D2D2D] rounded-lg p-4 overflow-auto drop-zone"
                            :class="{ 'drop-zone--active': activeDropZone === 'sprint' }"
                            @dragover.prevent="onDragOverZone('sprint')" @drop.prevent="onDropToSprint">
                            <div class="space-y-2">
                                <div v-for="subtask in validSprintSubtasks" :key="subtask.id" class="relative"
                                    :class="{ 'drag-item--dragging': draggingSubtaskId === subtask.id }"
                                    draggable="true" @dragstart="onDragStart(subtask, 'sprint', $event)"
                                    @dragend="onDragEnd">
                                    <TaskCard :task="subtask" :show-checkbox="false" @open-detail="openTask" />
                                    <v-btn icon="mdi-minus" size="x-small" variant="text" color="error"
                                        class="!absolute top-2 right-2 z-10"
                                        @click.stop="removeTaskFromSprint(subtask)" />
                                </div>
                                <div v-if="validSprintSubtasks.length === 0" class="text-center py-8 text-gray-500">
                                    <p>No tasks in sprint</p>
                                    <p class="text-sm mt-2">Add tasks from backlog</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Burndown Chart -->
                <div v-if="burndown && burndown.actual && burndown.actual.length > 0"
                    class="mt-8 bg-[#2D2D2D] rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Burndown Chart</h3>
                    <div class="h-64 flex items-end gap-2">
                        <div v-for="(point, index) in burndown.actual" :key="index"
                            class="flex-1 flex flex-col items-center">
                            <div class="w-full flex items-end justify-center gap-1 h-48">
                                <!-- Ideal line -->
                                <div class="w-2 bg-gray-600 rounded-t"
                                    :style="{ height: `${(burndown.ideal[index]?.remaining / statistics.total_subtasks) * 100}%` }" />
                                <!-- Actual line -->
                                <div class="w-2 bg-primary rounded-t"
                                    :style="{ height: `${(point.remaining / statistics.total_subtasks) * 100}%` }" />
                            </div>
                            <div class="text-xs text-gray-400 mt-2">Day {{ point.day }}</div>
                        </div>
                    </div>
                    <div class="flex justify-center gap-6 mt-4 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-600 rounded" />
                            <span class="text-gray-400">Ideal</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-primary rounded" />
                            <span class="text-gray-400">Actual</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <TaskDetailPanel
            v-model="showDetail"
            :task="detailTask"
            :parent-task="detailParentTask"
            :list="detailList"
            :workspace="workspace"
            :space="space"
            :statuses="statuses"
            :members="members"
            :labels="labels"
        />
    </MainLayout>
</template>

<style scoped>
.drop-zone {
    border: 1px dashed transparent;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.drop-zone--active {
    border-color: rgba(99, 102, 241, 0.8);
    background-color: rgba(99, 102, 241, 0.08);
}

.drag-item--dragging {
    opacity: 0.55;
}
</style>
