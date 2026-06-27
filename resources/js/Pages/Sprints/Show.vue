<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskCard from '@/Components/Tasks/TaskCard.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSnackbar } from '@/composables/useSnackbar';

const { showSnackbar } = useSnackbar();

const props = defineProps({
    workspace: { type: Object, default: null },
    space: { type: Object, default: null },
    list: { type: Object, default: null },
    sprint: { type: Object, default: null },
    backlogSubtasks: { type: Array, default: () => [] },
    statistics: { type: Object, default: null },
    burndown: { type: Object, default: null },
    statuses: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    canManageTaskStructure: { type: Boolean, default: false },
    canOperateTasks: { type: Boolean, default: false },
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
    if (props.sprint?.is_active) return false;
    if (!props.sprint?.end_date) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(props.sprint.end_date);
    return today > end;
});

const validBacklogSubtasks = computed(() => (props.backlogSubtasks || []).filter((s) => !!s.task));
const validSprintSubtasks = computed(() => (props.sprint?.subtasks || []).filter((s) => !!s.task));
const activeListId = computed(() => props.list?.id || props.sprint?.project_id || null);

// Burndown chart SVG helpers
const burndownWidth = 600;
const burndownHeight = 200;
const burndownPad = 40;

const burndownMaxVal = computed(() => {
    if (!props.burndown?.ideal?.length) return 10;
    return Math.max(...props.burndown.ideal.map((p) => p.remaining), 1);
});

const burndownYTicks = computed(() => {
    const max = burndownMaxVal.value;
    const step = Math.ceil(max / 4);
    const ticks = [];
    for (let v = 0; v <= max; v += step) ticks.push(v);
    if (!ticks.includes(max)) ticks.push(max);
    return ticks.sort((a, b) => a - b);
});

const burndownX = (index, total) => {
    const usable = burndownWidth - burndownPad * 2;
    return burndownPad + (index / Math.max(total - 1, 1)) * usable;
};

const burndownY = (val) => {
    const usable = burndownHeight - burndownPad * 1.5;
    const max = burndownMaxVal.value;
    return burndownPad / 2 + usable - (val / max) * usable;
};

const goToSprintIndex = () => {
    if (!activeListId.value) {
        router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
        return;
    }

    router.visit(route('projects.show', {
        workspace: props.workspace.id,
        space: props.space.id,
        project: activeListId.value,
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
        },
        onError: () => showSnackbar('Failed to start sprint', 'error'),
    });
};

const completeSprint = async () => {
    const ok = await confirmDialog('Complete this sprint?', 'Complete Sprint');
    if (!ok) return;

    router.post(route('sprints.complete', [props.workspace.id, props.space.id, props.sprint.id]), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['sprint', 'statistics', 'burndown'] });
        },
        onError: () => showSnackbar('Failed to complete sprint', 'error'),
    });
};

const openTask = (subtask) => {
    const parentTask = subtask.task;
    if (!parentTask) {
        showSnackbar('Task detail is unavailable because its parent task no longer exists.', 'error');
        return;
    }
    detailTask.value = subtask;
    detailParentTask.value = parentTask;
    detailList.value = props.list;
    showDetail.value = true;
};

const openSubtaskFromPanel = (subtask) => {
    detailParentTask.value = detailParentTask.value || detailTask.value;
    detailTask.value = subtask;
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
            },
            onError: () => showSnackbar('Failed to add task to sprint', 'error'),
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
            },
            onError: () => showSnackbar('Failed to remove task from sprint', 'error'),
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
            <div class="border-b border-gray-800 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-4">
                        <v-btn icon="mdi-arrow-left" variant="text" @click="goToSprintIndex" />
                        <div>
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <h1 class="text-xl sm:text-2xl font-bold text-white">{{ sprint.name }}</h1>
                                <v-chip v-if="isSprintActive" color="success" size="small">Active</v-chip>
                                <v-chip v-else-if="isSprintCompleted" color="default" size="small">Completed</v-chip>
                                <v-chip v-else color="info" size="small">Planned</v-chip>
                            </div>
                            <p v-if="list?.name" class="text-gray-500 text-xs mt-1">Project: {{ list.name }}</p>
                            <p v-if="sprint.goal" class="text-gray-400 text-sm mt-1">{{ sprint.goal }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <v-btn v-if="canManageTaskStructure && !isSprintActive && !isSprintCompleted" color="success" prepend-icon="mdi-play"
                            @click="startSprint">
                            Start Sprint
                        </v-btn>
                        <v-btn v-if="canManageTaskStructure && isSprintActive" color="warning" prepend-icon="mdi-check" @click="completeSprint">
                            Complete Sprint
                        </v-btn>
                    </div>
                </div>

                <!-- Sprint Statistics -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mt-4 sm:mt-6">
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
            <div class="flex-1 overflow-auto p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
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
                                    :draggable="canManageTaskStructure" @dragstart="onDragStart(subtask, 'backlog', $event)"
                                    @dragend="onDragEnd">
                                    <TaskCard :task="subtask" :show-checkbox="false" @open-detail="openTask" />
                                    <v-btn v-if="canManageTaskStructure" icon="mdi-plus" size="x-small" variant="text" color="primary"
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
                                    :draggable="canManageTaskStructure" @dragstart="onDragStart(subtask, 'sprint', $event)"
                                    @dragend="onDragEnd">
                                    <TaskCard :task="subtask" :show-checkbox="false" @open-detail="openTask" />
                                    <v-btn v-if="canManageTaskStructure" icon="mdi-minus" size="x-small" variant="text" color="error"
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
                    class="mt-6 sm:mt-8 bg-[#2D2D2D] rounded-lg p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-white mb-1">Burndown Chart</h3>
                    <p class="text-xs text-gray-500 mb-4">Remaining tasks over sprint duration. Ideal vs actual progress.</p>

                    <div class="w-full overflow-x-auto">
                        <svg :viewBox="`0 0 ${burndownWidth} ${burndownHeight}`" class="w-full" style="min-width:320px; height:220px">
                            <!-- Grid lines -->
                            <line v-for="tick in burndownYTicks" :key="`gy-${tick}`"
                                :x1="burndownPad" :y1="burndownY(tick)"
                                :x2="burndownWidth - burndownPad" :y2="burndownY(tick)"
                                stroke="#444" stroke-width="1" stroke-dasharray="4,4" />

                            <!-- Y axis labels -->
                            <text v-for="tick in burndownYTicks" :key="`ty-${tick}`"
                                :x="burndownPad - 8" :y="burndownY(tick) + 4"
                                text-anchor="end" font-size="11" fill="#888">{{ tick }}</text>

                            <!-- X axis labels -->
                            <text v-for="(pt, i) in burndown.ideal" :key="`tx-${i}`"
                                :x="burndownX(i, burndown.ideal.length)"
                                :y="burndownHeight - burndownPad + 18"
                                text-anchor="middle" font-size="11" fill="#888">Day {{ pt.day }}</text>

                            <!-- Ideal line -->
                            <polyline
                                :points="burndown.ideal.map((pt, i) =>
                                    `${burndownX(i, burndown.ideal.length)},${burndownY(pt.remaining)}`
                                ).join(' ')"
                                fill="none" stroke="#6b7280" stroke-width="2" stroke-dasharray="6,3" />

                            <!-- Actual line -->
                            <polyline
                                :points="burndown.actual.map((pt, i) =>
                                    `${burndownX(i, burndown.ideal.length)},${burndownY(pt.remaining)}`
                                ).join(' ')"
                                fill="none" stroke="#7c3aed" stroke-width="2.5" />

                            <!-- Actual data points -->
                            <circle v-for="(pt, i) in burndown.actual" :key="`dot-${i}`"
                                :cx="burndownX(i, burndown.ideal.length)"
                                :cy="burndownY(pt.remaining)"
                                r="4" fill="#7c3aed" stroke="#2D2D2D" stroke-width="2">
                                <title>Day {{ pt.day }}: {{ pt.remaining }} remaining</title>
                            </circle>

                            <!-- Axes -->
                            <line :x1="burndownPad" :y1="burndownPad / 2"
                                  :x2="burndownPad" :y2="burndownHeight - burndownPad"
                                  stroke="#555" stroke-width="1.5" />
                            <line :x1="burndownPad" :y1="burndownHeight - burndownPad"
                                  :x2="burndownWidth - burndownPad" :y2="burndownHeight - burndownPad"
                                  stroke="#555" stroke-width="1.5" />
                        </svg>
                    </div>

                    <div class="flex justify-center gap-6 mt-2 text-sm">
                        <div class="flex items-center gap-2">
                            <svg width="24" height="10"><line x1="0" y1="5" x2="24" y2="5" stroke="#6b7280" stroke-width="2" stroke-dasharray="6,3"/></svg>
                            <span class="text-gray-400">Ideal</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg width="24" height="10"><line x1="0" y1="5" x2="24" y2="5" stroke="#7c3aed" stroke-width="2.5"/></svg>
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
            :sprints="sprint ? [sprint] : []"
            :can-operate-tasks="canOperateTasks"
            :can-manage-task-structure="canManageTaskStructure"
            @open-subtask="openSubtaskFromPanel"
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
