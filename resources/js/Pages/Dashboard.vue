<script setup>
/**
 * Dashboard Page - ClickUp Style Home
 * 
 * Features:
 * - My Tasks overview
 * - Overdue tasks alert
 * - Recent activity
 * - Time tracking summary
 * - Quick actions
 */
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskCard from '@/Components/Tasks/TaskCard.vue';
import { useSnackbar } from '@/composables/useSnackbar';

const props = defineProps({
    workspaces: Array,
    activeWorkspace: Object,
    myTasks: Array,
    overdueTasks: Array,
    runningTimer: Object,
    timeStats: Object,
});

// Active tab for tasks
const activeTaskTab = ref('my-tasks');
const { showSnackbar } = useSnackbar();
const page = usePage();

const currentUserId = computed(() => page.props?.auth?.user?.id || null);

const getAssignedSubtaskId = (task) => {
    if (!currentUserId.value || !Array.isArray(task?.subtasks)) return null;
    const assigned = task.subtasks.find((subtask) =>
        Array.isArray(subtask?.assignees) && subtask.assignees.some((assignee) => assignee.id === currentUserId.value)
    );

    return assigned?.id || null;
};

const buildTaskDeepLink = (task) => {
    const workspaceId = task.task_list?.space?.workspace_id || props.activeWorkspace?.id;
    const spaceId = task.task_list?.space_id;
    const listId = task.task_list_id;
    const taskId = task.id;

    const baseUrl = route('lists.show', [workspaceId, spaceId, listId]);
    const assignedSubtaskId = getAssignedSubtaskId(task);

    if (assignedSubtaskId) {
        return `${baseUrl}?task_id=${taskId}&open_subtask_id=${assignedSubtaskId}`;
    }

    return `${baseUrl}?open_task_id=${taskId}`;
};

// Format duration (input is in minutes from backend)
const formatDuration = (minutes) => {
    if (!minutes) return '0h 0m';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    return `${hours}h ${mins}m`;
};

// Quick create
const showQuickCreate = ref(false);
const quickTaskName = ref('');
const quickTaskList = ref(null);
const isProcessing = ref(false);

// Get all lists from active workspace
const availableLists = computed(() => {
    if (!props.activeWorkspace?.spaces) return [];

    const lists = [];
    props.activeWorkspace.spaces.forEach(space => {
        // Lists without folder
        if (space.lists_without_folder) {
            space.lists_without_folder.forEach(list => {
                lists.push({
                    id: list.id,
                    name: list.name,
                    space_id: space.id,
                    space_name: space.name,
                    workspace_id: props.activeWorkspace.id,
                    display: `${space.name} / ${list.name}`
                });
            });
        }
        // Lists in folders
        if (space.folders) {
            space.folders.forEach(folder => {
                if (folder.lists) {
                    folder.lists.forEach(list => {
                        lists.push({
                            id: list.id,
                            name: list.name,
                            space_id: space.id,
                            space_name: space.name,
                            workspace_id: props.activeWorkspace.id,
                            display: `${space.name} / ${folder.name} / ${list.name}`
                        });
                    });
                }
            });
        }
    });
    return lists;
});

// Check if workspace has spaces
const hasSpaces = computed(() => {
    return props.activeWorkspace?.spaces && props.activeWorkspace.spaces.length > 0;
});

const createQuickTask = () => {
    if (!quickTaskName.value.trim() || !quickTaskList.value || isProcessing.value) return;
    isProcessing.value = true;

    router.post(
        route('tasks.store', [
            quickTaskList.value.workspace_id,
            quickTaskList.value.space_id,
            quickTaskList.value.id
        ]),
        { name: quickTaskName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                quickTaskName.value = '';
                quickTaskList.value = null;
                showQuickCreate.value = false;
            },
            onFinish: () => { isProcessing.value = false; }
        }
    );
};

// Recent tasks (limit to 10)
const recentTasks = computed(() => props.myTasks?.slice(0, 10) || []);

// Handle task complete — tasks don't support completion (only subtasks do)
const handleTaskComplete = (task) => {
    showSnackbar('Tasks cannot be completed directly. Complete subtasks instead.', 'info');
};

// Handle task open
const handleTaskOpen = (task) => {
    router.visit(buildTaskDeepLink(task));
};

// Navigate to the task containing the running timer's subtask
const goToTimerTask = () => {
    const subtask = props.runningTimer?.subtask;
    const t = subtask?.task;
    if (!t) return;

    const baseUrl = route('lists.show', [
        t.task_list.space.workspace_id,
        t.task_list.space.id,
        t.task_list.id,
    ]);

    // Open subtask board and auto-open running subtask detail panel
    const url = `${baseUrl}?task_id=${t.id}&open_subtask_id=${subtask?.id}`;
    router.visit(url);
};

// Create workspace dialog
const showCreateWorkspace = ref(false);
const newWorkspaceName = ref('');

const createWorkspace = () => {
    if (!newWorkspaceName.value.trim() || isProcessing.value) return;
    isProcessing.value = true;
    router.post(route('workspaces.store'), {
        name: newWorkspaceName.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newWorkspaceName.value = '';
            showCreateWorkspace.value = false;
            showSnackbar('Workspace created successfully!', 'success');
            router.reload({ only: ['workspaces', 'activeWorkspace'] });
        },
        onFinish: () => { isProcessing.value = false; }
    });
};

// Navigate to first space or workspaces page
const goToFirstSpaceOrWorkspaces = () => {
    if (!props.activeWorkspace) {
        router.visit(route('dashboard'));
        return;
    }

    const spaces = props.activeWorkspace?.spaces || [];
    if (spaces.length > 0) {
        // Navigate to the first space
        router.visit(route('spaces.show', [props.activeWorkspace.id, spaces[0].id]));
    } else {
        // No spaces exist, create a space
        showSnackbar('Create a space first to start organizing your work', 'info');
        // Redirect to workspace to create spaces
        router.visit(route('workspaces.show', props.activeWorkspace.id));
    }
};

// Open create space dialog
const openCreateSpace = () => {
    if (typeof window !== 'undefined' && window.openCreateSpaceDialog) {
        window.openCreateSpaceDialog();
    }
};
</script>

<template>
    <MainLayout title="Home">
        <div class="dashboard-page">
            <!-- Breadcrumb / Context Header -->
            <div class="context-header">
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <span>Dashboard</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <div v-if="activeWorkspace" class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: activeWorkspace.color || '#6366F1' }">
                            {{ activeWorkspace.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span class="font-medium text-white">{{ activeWorkspace.name }}</span>
                    </div>
                    <span v-else class="text-gray-500 italic">No workspace selected</span>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Left Column -->
                <div class="dashboard-main">
                    <!-- Overdue Alert -->
                    <v-alert v-if="overdueTasks?.length" type="warning" variant="tonal" class="mb-4" closable>
                        <v-alert-title>{{ overdueTasks.length }} Overdue Task{{ overdueTasks.length > 1 ? 's' : ''
                            }}</v-alert-title>
                        You have tasks that need your attention.
                    </v-alert>

                    <!-- Quick Create -->
                    <v-card class="mb-4" variant="outlined" rounded="lg">
                        <v-card-text>
                            <div v-if="!showQuickCreate"
                                class="flex items-center gap-2 cursor-pointer hover:bg-[#2d2d30] rounded-lg p-2 -m-2"
                                @click="showQuickCreate = true">
                                <v-icon color="primary">mdi-plus-circle</v-icon>
                                <span class="text-gray-400">Create a task...</span>
                            </div>
                            <div v-else class="space-y-2">
                                <v-text-field v-model="quickTaskName" placeholder="What needs to be done?"
                                    variant="outlined" density="compact" hide-details autofocus
                                    @keydown.enter="createQuickTask" @keydown.escape="showQuickCreate = false" />
                                <v-alert v-if="!availableLists.length" type="info" variant="tonal" density="compact"
                                    class="text-sm">
                                    <div class="flex items-center justify-between">
                                        <span v-if="!hasSpaces">No spaces available. Create a space first.</span>
                                        <span v-else>No products available. Go to a space to create a product.</span>
                                        <v-btn v-if="!hasSpaces" size="small" variant="text" color="primary"
                                            @click="openCreateSpace">
                                            Create a Space
                                        </v-btn>
                                        <v-btn v-else size="small" variant="text" color="primary"
                                            @click="router.visit(route('spaces.show', [activeWorkspace.id, activeWorkspace.spaces[0].id]))">
                                            Go to Spaces
                                        </v-btn>
                                    </div>
                                </v-alert>

                                <v-select v-model="quickTaskList" :items="availableLists" item-title="display"
                                    item-value="id" return-object label="Select Product" variant="outlined"
                                    density="compact" hide-details :disabled="!availableLists.length"
                                    placeholder="Choose a product..." bg-color="#1e1e1e" base-color="white"
                                    color="primary" />
                                <div class="flex gap-2">
                                    <v-btn color="primary" size="small" @click="createQuickTask" :loading="isProcessing"
                                        :disabled="!quickTaskName.trim() || !quickTaskList">
                                        Create Task
                                    </v-btn>
                                    <v-btn variant="text" size="small" @click="showQuickCreate = false">
                                        Cancel
                                    </v-btn>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- My Tasks -->
                    <v-card variant="outlined" rounded="lg">
                        <v-card-title class="flex items-center justify-between">
                            <span>My Tasks</span>
                            <v-btn variant="text" size="small" :href="route('my-tasks')">
                                View All
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </v-card-title>
                        <v-divider />

                        <v-tabs v-model="activeTaskTab" color="primary">
                            <v-tab value="my-tasks">Assigned to me</v-tab>
                            <v-tab value="overdue">
                                Overdue
                                <v-badge v-if="overdueTasks?.length" :content="overdueTasks.length" color="error" inline
                                    class="ml-1" />
                            </v-tab>
                        </v-tabs>

                        <v-tabs-window v-model="activeTaskTab">
                            <v-tabs-window-item value="my-tasks">
                                <div v-if="!recentTasks.length" class="pa-8 text-center text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-checkbox-marked-circle-outline</v-icon>
                                    <div>No tasks assigned to you</div>
                                </div>
                                <div v-else class="pa-2">
                                    <div class="space-y-2">
                                        <TaskCard v-for="task in recentTasks" :key="task.id" :task="task" show-list
                                            :show-checkbox="false" @complete="handleTaskComplete"
                                            @open-detail="handleTaskOpen" />
                                    </div>
                                </div>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="overdue">
                                <div v-if="!overdueTasks?.length" class="pa-8 text-center text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-check-circle</v-icon>
                                    <div>No overdue tasks!</div>
                                </div>
                                <div v-else class="pa-2">
                                    <div class="space-y-2">
                                        <TaskCard v-for="task in overdueTasks" :key="task.id" :task="task" show-list
                                            :show-checkbox="false" @complete="handleTaskComplete"
                                            @open-detail="handleTaskOpen" />
                                    </div>
                                </div>
                            </v-tabs-window-item>
                        </v-tabs-window>
                    </v-card>
                </div>

                <!-- Right Sidebar -->
                <div class="dashboard-sidebar">
                    <!-- Spaces Overview Card -->
                    <v-card v-if="activeWorkspace" variant="outlined" rounded="lg" class="mb-4" hover
                        :href="route('workspaces.show', activeWorkspace.id)">
                        <v-card-title class="flex items-center gap-2">
                            <v-icon size="20" color="purple">mdi-view-grid</v-icon>
                            Spaces Overview
                        </v-card-title>
                        <v-card-text>
                            <div class="text-center py-4">
                                <div class="text-4xl font-bold text-purple-500 mb-2">
                                    {{ activeWorkspace?.spaces?.length || 0 }}
                                </div>
                                <div class="text-sm text-gray-500 mb-3">
                                    {{ activeWorkspace?.spaces?.length === 1 ? 'Space' : 'Spaces' }} in workspace
                                </div>
                                <v-btn color="purple" variant="tonal" size="small" block
                                    :href="route('workspaces.show', activeWorkspace.id)">
                                    <v-icon start size="16">mdi-view-grid-outline</v-icon>
                                    View All Spaces
                                </v-btn>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- Time Tracking Widget -->
                    <v-card variant="outlined" rounded="lg" class="mb-4">
                        <v-card-title class="flex items-center gap-2">
                            <v-icon size="20">mdi-timer-outline</v-icon>
                            Time Tracked
                        </v-card-title>
                        <v-card-text>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-2xl font-bold">{{ formatDuration(timeStats?.today) }}</div>
                                    <div class="text-xs text-gray-500">Today</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold">{{ formatDuration(timeStats?.week) }}</div>
                                    <div class="text-xs text-gray-500">This Week</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-warning">{{
                                        formatDuration(timeStats?.idle_today) }}</div>
                                    <div class="text-xs text-gray-500">Idle Today</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-info">{{ timeStats?.todo_count || 0 }}</div>
                                    <div class="text-xs text-gray-500">To-do Items</div>
                                </div>
                            </div>

                            <!-- Running Timer -->
                            <div v-if="runningTimer"
                                class="mt-4 p-3 bg-success/10 rounded-lg cursor-pointer hover:bg-success/20 transition-colors"
                                @click="goToTimerTask">
                                <div class="flex items-center gap-2 mb-2">
                                    <v-icon color="success" size="16">mdi-timer-outline</v-icon>
                                    <span class="text-sm font-medium text-success">Timer Running</span>
                                </div>
                                <div class="text-sm truncate">{{ runningTimer.subtask?.name }}</div>
                                <div class="text-xs text-gray-500">{{ runningTimer.subtask?.task?.name }} &middot; {{
                                    runningTimer.subtask?.task?.task_list?.space?.name }}</div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- Workspaces -->
                    <v-card variant="outlined" rounded="lg" class="mb-4">
                        <v-card-title class="flex items-center justify-between">
                            <span>Workspaces</span>
                            <v-btn icon variant="text" size="x-small" @click="showCreateWorkspace = true">
                                <v-icon size="18">mdi-plus</v-icon>
                            </v-btn>
                        </v-card-title>
                        <v-card-text class="pa-2">
                            <v-list density="compact" nav>
                                <v-list-item v-for="workspace in workspaces" :key="workspace.id"
                                    :active="workspace.id === activeWorkspace?.id" rounded="lg"
                                    @click="router.post(route('workspaces.switch', workspace.id))">
                                    <template v-slot:prepend>
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm mr-3"
                                            :style="{ backgroundColor: workspace.color || '#6366F1' }">
                                            {{ workspace.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                    </template>
                                    <v-list-item-title>{{ workspace.name }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ workspace.spaces_count || 0 }}
                                        spaces</v-list-item-subtitle>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>

                    <!-- Quick Links -->
                    <v-card variant="outlined" rounded="lg">
                        <v-card-title>Quick Links</v-card-title>
                        <v-list density="compact" nav>
                            <v-list-item prepend-icon="mdi-checkbox-marked-circle-outline" title="My Tasks"
                                :href="route('my-tasks')" rounded="lg" />
                            <v-list-item prepend-icon="mdi-timer-outline" title="Time Tracking"
                                :href="route('time-tracking.index')" rounded="lg" />
                            <v-list-item prepend-icon="mdi-chart-box-outline" title="Analytics"
                                :href="activeWorkspace ? route('workspaces.analytics', activeWorkspace.id) : undefined"
                                rounded="lg" />
                            <v-list-item prepend-icon="mdi-cog-outline" title="Settings"
                                :href="activeWorkspace ? route('workspaces.settings', activeWorkspace.id) : undefined"
                                rounded="lg" />
                        </v-list>
                    </v-card>
                </div>
            </div>
        </div>

        <!-- Create Workspace Dialog -->
        <v-dialog v-model="showCreateWorkspace" max-width="400">
            <v-card>
                <v-card-title>Create Workspace</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newWorkspaceName" label="Workspace Name" placeholder="e.g., My Company"
                        variant="outlined" autofocus @keydown.enter="createWorkspace" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateWorkspace = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isProcessing" @click="createWorkspace">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.dashboard-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.welcome-section {
    margin-bottom: 24px;
}

.context-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #2d2d30;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
}

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-sidebar {
        order: -1;
    }
}

.space-y-2>*+* {
    margin-top: 8px;
}

.grid {
    display: grid;
}

.grid-cols-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.gap-4 {
    gap: 16px;
}
</style>
