<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    space: Object,
    sprint: Object,
    backlogTasks: Array,
    statistics: Object,
    burndown: Object,
    statuses: Array,
    priorities: Array,
    labels: Array,
    members: Array,
});

const isSprintActive = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const start = new Date(props.sprint.start_date);
    const end = new Date(props.sprint.end_date);
    return today >= start && today <= end;
});

const isSprintCompleted = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(props.sprint.end_date);
    return today > end;
});

const openTask = (task) => {
    router.visit(route('tasks.show', [props.workspace.id, props.space.id, task.task_list_id, task.id]));
};

const addTaskToSprint = (task) => {
    router.post(
        route('sprints.tasks.add', [props.workspace.id, props.space.id, props.sprint.id]),
        { task_id: task.id },
        {
            preserveScroll: true,
            onFinish: () => {
                router.reload({ only: ['sprint', 'backlogTasks', 'statistics', 'burndown'] });
                window.showSnackbar('Task added to sprint!', 'success');
            },
        }
    );
};

const removeTaskFromSprint = (task) => {
    router.delete(
        route('sprints.tasks.remove', [props.workspace.id, props.space.id, props.sprint.id]),
        {
            data: { task_id: task.id },
            preserveScroll: true,
            onFinish: () => {
                router.reload({ only: ['sprint', 'backlogTasks', 'statistics', 'burndown'] });
                window.showSnackbar('Task removed from sprint!', 'success');
            },
        }
    );
};
</script>

<template>
    <MainLayout :title="`${sprint.name} - ${space.name}`">
        <div class="h-full flex flex-col bg-[#1e1e1e]">
            <!-- Header -->
            <div class="border-b border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <v-btn
                            icon="mdi-arrow-left"
                            variant="text"
                            @click="router.visit(route('sprints.index', [workspace.id, space.id]))"
                        />
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-white">{{ sprint.name }}</h1>
                                <v-chip v-if="isSprintActive" color="success" size="small">Active</v-chip>
                                <v-chip v-else-if="isSprintCompleted" color="default" size="small">Completed</v-chip>
                                <v-chip v-else color="info" size="small">Planned</v-chip>
                            </div>
                            <p v-if="sprint.goal" class="text-gray-400 text-sm mt-1">{{ sprint.goal }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sprint Statistics -->
                <div class="grid grid-cols-5 gap-4 mt-6">
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Total Tasks</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ statistics.total_tasks }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Completed</div>
                        <div class="text-2xl font-bold text-success mt-1">{{ statistics.completed_tasks }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">In Progress</div>
                        <div class="text-2xl font-bold text-info mt-1">{{ statistics.in_progress_tasks }}</div>
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
                                <span class="text-gray-400 text-sm ml-2">({{ backlogTasks.length }})</span>
                            </h3>
                        </div>
                        <div class="flex-1 bg-[#2D2D2D] rounded-lg p-4 overflow-auto">
                            <div class="space-y-2">
                                <div
                                    v-for="task in backlogTasks"
                                    :key="task.id"
                                    class="bg-[#1e1e1e] rounded-lg p-4 hover:bg-[#252526] transition-colors cursor-pointer"
                                    @click="openTask(task)"
                                >
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-white">{{ task.name }}</div>
                                            <div class="flex items-center gap-2 mt-2">
                                                <v-chip
                                                    v-if="task.status"
                                                    :color="task.status.color"
                                                    size="x-small"
                                                >
                                                    {{ task.status.name }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="task.priority"
                                                    :color="task.priority.color"
                                                    size="x-small"
                                                >
                                                    <v-icon start size="12">mdi-flag</v-icon>
                                                    {{ task.priority.name }}
                                                </v-chip>
                                            </div>
                                        </div>
                                        <v-btn
                                            icon="mdi-plus"
                                            size="x-small"
                                            variant="text"
                                            color="primary"
                                            @click.stop="addTaskToSprint(task)"
                                        />
                                    </div>
                                    <div v-if="task.assignees && task.assignees.length > 0" class="flex gap-1 mt-2">
                                        <v-avatar
                                            v-for="assignee in task.assignees"
                                            :key="assignee.id"
                                            size="24"
                                            :image="assignee.profile_photo_url"
                                        >
                                            <span v-if="!assignee.profile_photo_url">{{ assignee.name[0] }}</span>
                                        </v-avatar>
                                    </div>
                                </div>
                                <div v-if="backlogTasks.length === 0" class="text-center py-8 text-gray-500">
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
                                <span class="text-gray-400 text-sm ml-2">({{ sprint.tasks.length }})</span>
                            </h3>
                        </div>
                        <div class="flex-1 bg-[#2D2D2D] rounded-lg p-4 overflow-auto">
                            <div class="space-y-2">
                                <div
                                    v-for="task in sprint.tasks"
                                    :key="task.id"
                                    class="bg-[#1e1e1e] rounded-lg p-4 hover:bg-[#252526] transition-colors cursor-pointer"
                                    @click="openTask(task)"
                                >
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-white">{{ task.name }}</div>
                                            <div class="flex items-center gap-2 mt-2">
                                                <v-chip
                                                    v-if="task.status"
                                                    :color="task.status.color"
                                                    size="x-small"
                                                >
                                                    {{ task.status.name }}
                                                </v-chip>
                                                <v-chip
                                                    v-if="task.priority"
                                                    :color="task.priority.color"
                                                    size="x-small"
                                                >
                                                    <v-icon start size="12">mdi-flag</v-icon>
                                                    {{ task.priority.name }}
                                                </v-chip>
                                            </div>
                                        </div>
                                        <v-btn
                                            icon="mdi-minus"
                                            size="x-small"
                                            variant="text"
                                            color="error"
                                            @click.stop="removeTaskFromSprint(task)"
                                        />
                                    </div>
                                    <div v-if="task.assignees && task.assignees.length > 0" class="flex gap-1 mt-2">
                                        <v-avatar
                                            v-for="assignee in task.assignees"
                                            :key="assignee.id"
                                            size="24"
                                            :image="assignee.profile_photo_url"
                                        >
                                            <span v-if="!assignee.profile_photo_url">{{ assignee.name[0] }}</span>
                                        </v-avatar>
                                    </div>
                                </div>
                                <div v-if="sprint.tasks.length === 0" class="text-center py-8 text-gray-500">
                                    <p>No tasks in sprint</p>
                                    <p class="text-sm mt-2">Add tasks from backlog</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Burndown Chart -->
                <div v-if="burndown && burndown.actual && burndown.actual.length > 0" class="mt-8 bg-[#2D2D2D] rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Burndown Chart</h3>
                    <div class="h-64 flex items-end gap-2">
                        <div
                            v-for="(point, index) in burndown.actual"
                            :key="index"
                            class="flex-1 flex flex-col items-center"
                        >
                            <div class="w-full flex items-end justify-center gap-1 h-48">
                                <!-- Ideal line -->
                                <div
                                    class="w-2 bg-gray-600 rounded-t"
                                    :style="{ height: `${(burndown.ideal[index]?.remaining / statistics.total_tasks) * 100}%` }"
                                />
                                <!-- Actual line -->
                                <div
                                    class="w-2 bg-primary rounded-t"
                                    :style="{ height: `${(point.remaining / statistics.total_tasks) * 100}%` }"
                                />
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
    </MainLayout>
</template>

