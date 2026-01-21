<script setup>
/**
 * Dashboard Page - Nested Kanban Board (Refactored)
 * 
 * This is the main orchestrator component that uses smaller, focused components.
 * 
 * Structure:
 * Workspace > Board > FeatureList > Feature > TaskList > Task
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useDisplay } from 'vuetify';

// Import Composables
import { useTimer } from '@/Composables/useTimer';
import { useNotification } from '@/Composables/useNotification';
import { useFilters } from '@/Composables/useFilters';
import { useActivity } from '@/Composables/useActivity';

// Import Components
import {
    NoWorkspace,
    NoBoard,
    SidebarContent,
    BoardHeader,
    KanbanBoard,
    FeatureModal,
    WorkspaceModal,
    BoardModal,
    DeleteConfirmModal,
    ActivityLogModal,
    TimeTrackingModal,
    MemberModal,
    LabelModal,
    AddTimeModal,
    TaskEditModal
} from '@/Components/Dashboard';

// Props from backend
const props = defineProps({
    workspaces: { type: Array, default: () => [] },
    activeBoard: { type: Object, default: null },
    teamMembers: { type: Array, default: () => [] },
    runningTimer: { type: Object, default: null },
    recentActivities: { type: Array, default: () => [] },
    myTasks: { type: Array, default: () => [] },
    timeSummary: { type: Object, default: () => ({ today: 0, week: 0 }) },
    currentUser: { type: Object, default: null },
});

// ==================== COMPOSABLES ====================
const { snackbar, showNotification, hideNotification } = useNotification();
const {
    activeTimers, timerDisplays, hasActiveTimer,
    startWorkingOn, pauseTimer, stopTimerAndLog, discardTimer,
    formatElapsedTime, getTimerDisplay, getTimerStatus,
    startTimerLoop, stopTimerLoop
} = useTimer(showNotification);
const {
    searchQuery, activeFilters, hasActiveFilters,
    clearFilters, filterFeatureLists
} = useFilters();
const {
    addActivityLog, getActivityDescription, formatActivityTime,
    calculateTeamTimeStats
} = useActivity();

// ==================== VUETIFY & LAYOUT ====================
const { mobile, smAndDown } = useDisplay();
const isSidebarOpen = ref(!mobile.value);
const toggleSidebar = () => { isSidebarOpen.value = !isSidebarOpen.value; };

// ==================== LOGOUT ====================
const logout = () => {
    router.post(route('logout'));
};

// ==================== REACTIVE STATE ====================
const workspaces = ref(props.workspaces || []);
const teamMembers = ref(props.teamMembers || []);
const activityLog = ref(props.recentActivities || []);
const activeBoard = ref(props.activeBoard || null);
const featureLists = ref(props.activeBoard?.featureLists || []);
const availableLabels = ref(props.activeBoard?.labels || []);

// ==================== WATCHERS ====================
watch(() => props.workspaces, (val) => { workspaces.value = val || []; }, { deep: true });
watch(() => props.activeBoard, (val) => {
    activeBoard.value = val || null;
    featureLists.value = val?.featureLists || [];
    availableLabels.value = val?.labels || [];

    // Sync selectedFeature and selectedFeatureList with updated data if modal is open
    if (selectedFeature.value) {
        // Find the updated feature from the new props
        for (const list of featureLists.value) {
            const updatedFeature = list.features?.find(f => f.id === selectedFeature.value.id);
            if (updatedFeature) {
                // Deep clone to avoid reactivity issues
                selectedFeature.value = JSON.parse(JSON.stringify(updatedFeature));
                selectedFeatureList.value = JSON.parse(JSON.stringify(list));
                break;
            }
        }
    }
}, { deep: true });
watch(() => props.teamMembers, (val) => { teamMembers.value = val || []; }, { deep: true });
watch(() => props.recentActivities, (val) => { activityLog.value = val || []; }, { deep: true });

// ==================== COMPUTED ====================
const hasWorkspaces = computed(() => workspaces.value.length > 0);
const hasActiveBoard = computed(() => activeBoard.value !== null && activeBoard.value?.id !== undefined);
const canPerformBoardActions = computed(() => hasWorkspaces.value && hasActiveBoard.value);
const starredBoards = computed(() => workspaces.value.flatMap(w => w.boards?.filter(b => b.starred) || []));

const filteredFeatureLists = computed(() => {
    return filterFeatureLists(featureLists.value);
});

const getBoardMembers = computed(() => {
    if (!activeBoard.value?.members) return [];
    return activeBoard.value.members.map(id => teamMembers.value.find(m => m.id === id)).filter(Boolean);
});

// ==================== MODAL STATES ====================
const modals = ref({
    feature: false,
    workspace: false,
    board: false,
    delete: false,
    activity: false,
    timeTracking: false,
    member: false,
    label: false,
    addTime: false,
    taskEdit: false
});

// Modal Data
const selectedFeature = ref(null);
const selectedFeatureList = ref(null);
const editingWorkspace = ref(null);
const editingBoard = ref(null);
const deleteTarget = ref({ type: '', item: null, parent: null });
const activityFilterUser = ref(null);
const selectedTaskForTime = ref(null);
const editingTask = ref(null);

// ==================== LOADING STATE ====================
const isLoading = ref(false);

// ==================== WORKSPACE HANDLERS ====================
const handleCreateWorkspace = () => {
    editingWorkspace.value = null;
    modals.value.workspace = true;
};

const handleEditWorkspace = (workspace) => {
    editingWorkspace.value = workspace;
    modals.value.workspace = true;
};

const handleSaveWorkspace = (data) => {
    isLoading.value = true;
    if (data.id) {
        router.put(route('workspaces.update', data.id), { name: data.name }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => showNotification('Workspace updated'),
            onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to update workspace', 'error'),
            onFinish: () => isLoading.value = false
        });
    } else {
        router.post(route('workspaces.store'), { name: data.name }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => showNotification('Workspace created'),
            onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to create workspace', 'error'),
            onFinish: () => isLoading.value = false
        });
    }
};

const handleDeleteWorkspace = (workspace) => {
    deleteTarget.value = { type: 'workspace', item: workspace, itemName: workspace.name };
    modals.value.delete = true;
};

// ==================== BOARD HANDLERS ====================
const handleCreateBoard = (workspaceId = null) => {
    editingBoard.value = null;
    modals.value.board = true;
};

const handleEditBoard = (board) => {
    editingBoard.value = board;
    modals.value.board = true;
};

const handleSaveBoard = (data) => {
    isLoading.value = true;
    if (data.id) {
        router.put(route('boards.update', data.id), {
            name: data.name,
            color: data.color
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => showNotification('Board updated'),
            onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to update board', 'error'),
            onFinish: () => isLoading.value = false
        });
    } else {
        router.post(route('boards.store'), {
            workspace_id: data.workspaceId,
            name: data.name,
            color: data.color
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => showNotification('Board created'),
            onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to create board', 'error'),
            onFinish: () => isLoading.value = false
        });
    }
};

const handleSelectBoard = (board) => {
    router.visit(route('boards.show', board.id));
};

const handleToggleBoardStar = (board) => {
    router.post(route('boards.star', board.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification(board.starred ? 'Board unstarred' : 'Board starred'),
        onError: () => showNotification('Failed to update star', 'error')
    });
};

const handleDeleteBoard = (board) => {
    deleteTarget.value = { type: 'board', item: board, itemName: board.name };
    modals.value.delete = true;
};

// ==================== FEATURE LIST HANDLERS ====================
const handleAddFeatureList = (name) => {
    if (!canPerformBoardActions.value) {
        showNotification('Please select a board first', 'warning');
        return;
    }
    isLoading.value = true;
    router.post(route('feature-lists.store'), {
        board_id: activeBoard.value.id,
        title: name
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('List added'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to add list', 'error'),
        onFinish: () => isLoading.value = false
    });
};

const handleRenameFeatureList = ({ listId, name }) => {
    router.put(route('feature-lists.update', listId), { title: name }, {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to rename list', 'error')
    });
};

const handleDeleteFeatureList = (list) => {
    deleteTarget.value = { type: 'list', item: list, itemName: list.title };
    modals.value.delete = true;
};

// ==================== FEATURE HANDLERS ====================
const handleAddFeature = ({ listId, title }) => {
    if (!canPerformBoardActions.value) {
        showNotification('Please select a board first', 'warning');
        return;
    }
    isLoading.value = true;
    router.post(route('features.store'), {
        feature_list_id: listId,
        title: title,
        priority: 'medium'
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('Feature added'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to add feature', 'error'),
        onFinish: () => isLoading.value = false
    });
};

const handleOpenFeature = (feature, list) => {
    if (!canPerformBoardActions.value) {
        showNotification('Please select a board first', 'warning');
        return;
    }
    selectedFeature.value = JSON.parse(JSON.stringify(feature));
    selectedFeatureList.value = list;
    modals.value.feature = true;
};

const handleUpdateFeature = (updatedFeature) => {
    if (!selectedFeatureList.value) return;
    router.put(route('features.update', updatedFeature.id), {
        title: updatedFeature.title,
        description: updatedFeature.description,
        priority: updatedFeature.priority,
        due_date: updatedFeature.dueDate
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('Feature updated'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to update feature', 'error')
    });
};

const handleDeleteFeature = (feature) => {
    deleteTarget.value = { type: 'feature', item: feature, itemName: feature.title };
    modals.value.delete = true;
};

const handleMoveFeature = ({ feature, toListId, newIndex }) => {
    router.post(route('features.move', feature.id), {
        feature_list_id: toListId,
        position: newIndex
    }, {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to move feature', 'error')
    });
};

// ==================== TASK HANDLERS (Inside Feature Modal) ====================
const handleAddTaskList = ({ featureId, name }) => {
    if (!selectedFeature.value) return;
    isLoading.value = true;
    router.post(route('task-lists.store'), {
        feature_id: featureId,
        title: name
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('Task list added'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to add task list', 'error'),
        onFinish: () => isLoading.value = false
    });
};

const handleRenameTaskList = ({ featureId, listId, name }) => {
    router.put(route('task-lists.update', listId), { title: name }, {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to rename task list', 'error')
    });
};

const handleDeleteTaskList = ({ featureId, listId }) => {
    router.delete(route('task-lists.destroy', listId), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('Task list deleted'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to delete task list', 'error')
    });
};

const handleAddTask = ({ featureId, listId, title }) => {
    if (!selectedFeature.value) return;
    isLoading.value = true;
    router.post(route('tasks.store'), {
        task_list_id: listId,
        title: title
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            showNotification('Task added');
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to add task', 'error'),
        onFinish: () => isLoading.value = false
    });
};

const handleEditTask = ({ featureId, task }) => {
    editingTask.value = task;
    modals.value.taskEdit = true;
};

const handleSaveTask = (updatedTask) => {
    if (!selectedFeature.value) return;
    router.put(route('tasks.update', updatedTask.id), {
        title: updatedTask.title,
        description: updatedTask.description,
        priority: updatedTask.priority,
        due_date: updatedTask.dueDate,
        estimated_hours: updatedTask.estimatedHours
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => showNotification('Task updated'),
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to update task', 'error')
    });
};

const handleDeleteTask = ({ featureId, task }) => {
    deleteTarget.value = { type: 'task', item: task, itemName: task.title };
    modals.value.delete = true;
};

const handleToggleTaskComplete = ({ featureId, task }) => {
    router.post(route('tasks.toggle', task.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            if (!task.completed) {
                addActivityLog('task_completed', {
                    taskId: task.id,
                    taskTitle: task.title,
                    featureTitle: selectedFeature.value?.title
                });
            }
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to toggle task', 'error')
    });
};

const handleMoveTask = ({ featureId, task, toListId, newIndex }) => {
    router.post(route('tasks.move', task.id), {
        task_list_id: toListId,
        position: newIndex
    }, {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to move task', 'error')
    });
};

// ==================== TIMER HANDLERS ====================
const handleStartTimer = (task) => {
    router.post(route('time-tracking.start', task.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            startWorkingOn(task, selectedFeature.value);
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to start timer', 'error')
    });
};

const handlePauseTimer = (task) => {
    router.post(route('time-tracking.pause', task.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            pauseTimer(task.id);
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to pause timer', 'error')
    });
};

const handleStopTimer = (task) => {
    const timer = activeTimers.value[task.id];
    const elapsed = timer ? timer.elapsed : 0;
    const hours = elapsed / 3600;

    // Use complete endpoint which stops timer and logs time
    router.post(route('time-tracking.complete', task.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            stopTimerAndLog(task, selectedFeature.value, '', () => { });
            showNotification(`${hours.toFixed(2)} hours logged`);
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to stop timer', 'error')
    });
};

const handleDiscardTimer = (task) => {
    discardTimer(task.id);
};

const handleAddTime = (task) => {
    selectedTaskForTime.value = task;
    modals.value.addTime = true;
};

const handleSaveTimeEntry = ({ taskId, hours, description }) => {
    if (!selectedFeature.value) return;
    router.post(route('time-tracking.log'), {
        task_id: taskId,
        hours: hours,
        description: description
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            addActivityLog('time_logged', {
                taskId: taskId,
                hours: hours
            });
            showNotification(`${hours.toFixed(2)} hours logged`);
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || 'Failed to log time', 'error')
    });
};

// ==================== DELETE HANDLER ====================
const handleConfirmDelete = () => {
    const { type, item } = deleteTarget.value;
    let routeName = '';
    let routeParam = item.id;

    switch (type) {
        case 'workspace':
            routeName = 'workspaces.destroy';
            break;
        case 'board':
            routeName = 'boards.destroy';
            break;
        case 'list':
            routeName = 'feature-lists.destroy';
            break;
        case 'feature':
            routeName = 'features.destroy';
            break;
        case 'task':
            routeName = 'tasks.destroy';
            break;
        case 'taskList':
            routeName = 'task-lists.destroy';
            break;
        default:
            showNotification('Unknown delete type', 'error');
            return;
    }

    router.delete(route(routeName, routeParam), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            if (type === 'feature') {
                modals.value.feature = false;
            }
            showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} deleted`);
        },
        onError: (errors) => showNotification(Object.values(errors)[0] || `Failed to delete ${type}`, 'error')
    });
};

// ==================== HELPER FUNCTIONS ====================
const syncFeatureToList = () => {
    if (!selectedFeature.value || !selectedFeatureList.value) return;
    const list = featureLists.value.find(l => l.id === selectedFeatureList.value.id);
    if (list) {
        const idx = list.features.findIndex(f => f.id === selectedFeature.value.id);
        if (idx > -1) {
            list.features[idx] = JSON.parse(JSON.stringify(selectedFeature.value));
        }
    }
};

const updateFeatureProgress = () => {
    if (!selectedFeature.value) return;
    const total = selectedFeature.value.taskLists.reduce((sum, l) => sum + l.tasks.length, 0);
    const completed = selectedFeature.value.taskLists.reduce((sum, l) =>
        sum + l.tasks.filter(t => t.completed).length, 0);
    selectedFeature.value.progress = total > 0 ? Math.round((completed / total) * 100) : 0;
};

const handleCloseFeatureModal = () => {
    syncFeatureToList();
    modals.value.feature = false;
    selectedFeature.value = null;
    selectedFeatureList.value = null;
};

// ==================== LIFECYCLE ====================
onMounted(() => {
    if (Object.keys(activeTimers.value).length > 0) {
        startTimerLoop();
    }
});

onUnmounted(() => {
    stopTimerLoop();
});
</script>

<template>

    <Head title="Dashboard" />

    <v-app>
        <!-- Top Navigation Bar -->
        <v-app-bar color="surface" density="compact" flat>
            <v-btn icon variant="text" @click="toggleSidebar">
                <v-icon>mdi-menu</v-icon>
            </v-btn>

            <v-toolbar-title class="d-flex align-center">
                <v-icon color="primary" class="mr-2">mdi-view-dashboard-variant</v-icon>
                <span class="font-weight-bold">Kanban</span>
            </v-toolbar-title>

            <v-spacer />

            <!-- Search -->
            <v-text-field v-if="!smAndDown" v-model="searchQuery" placeholder="Search features, tasks..."
                variant="outlined" density="compact" hide-details prepend-inner-icon="mdi-magnify"
                style="max-width: 300px;" bg-color="surface-variant" class="mr-2" />

            <!-- Active Timer Indicator -->
            <v-chip v-if="hasActiveTimer" color="success" size="small" variant="tonal" class="mr-2">
                <v-icon start size="small">mdi-timer</v-icon>
                Timer Active
            </v-chip>

            <!-- Activity Log Button -->
            <v-btn icon variant="text" @click="modals.activity = true">
                <v-badge :content="activityLog.length" color="primary" :max="99">
                    <v-icon>mdi-bell-outline</v-icon>
                </v-badge>
            </v-btn>

            <!-- Time Tracking Overview -->
            <v-btn icon variant="text" @click="modals.timeTracking = true">
                <v-icon>mdi-chart-timeline-variant</v-icon>
            </v-btn>

            <!-- User Menu -->
            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn icon variant="text" v-bind="menuProps">
                        <v-avatar size="32" color="primary">
                            <span>{{ props.currentUser?.name?.charAt(0) || 'U' }}</span>
                        </v-avatar>
                    </v-btn>
                </template>
                <v-list density="compact" nav>
                    <v-list-item :title="props.currentUser?.name || 'User'" :subtitle="props.currentUser?.email" />
                    <v-divider />
                    <v-list-item prepend-icon="mdi-account" title="Profile" :href="route('profile.show')" />
                    <v-list-item prepend-icon="mdi-cog" title="Settings" />
                    <v-divider />
                    <v-list-item prepend-icon="mdi-logout" title="Logout" class="text-error" @click="logout" />
                </v-list>
            </v-menu>
        </v-app-bar>

        <!-- Sidebar Navigation -->
        <v-navigation-drawer v-model="isSidebarOpen" :temporary="smAndDown" :permanent="!smAndDown" width="280"
            color="surface">
            <SidebarContent :workspaces="workspaces" :starred-boards="starredBoards" :active-board-id="activeBoard?.id"
                :time-summary="props.timeSummary" @create-workspace="handleCreateWorkspace"
                @edit-workspace="handleEditWorkspace" @delete-workspace="handleDeleteWorkspace"
                @create-board="handleCreateBoard" @edit-board="handleEditBoard" @delete-board="handleDeleteBoard"
                @select-board="handleSelectBoard" @toggle-star="handleToggleBoardStar" />
        </v-navigation-drawer>

        <!-- Main Content -->
        <v-main class="bg-background">
            <!-- No Workspaces State -->
            <NoWorkspace v-if="!hasWorkspaces" @create-workspace="handleCreateWorkspace" />

            <!-- No Board Selected State -->
            <NoBoard v-else-if="!hasActiveBoard" @create-board="handleCreateBoard" />

            <!-- Board Content -->
            <template v-else>
                <!-- Board Header -->
                <BoardHeader :active-board="activeBoard" :board-members="getBoardMembers"
                    :available-labels="availableLabels" :active-filters="activeFilters"
                    :has-active-filters="hasActiveFilters" @toggle-filter-label="(id) => {
                        const idx = activeFilters.labels.indexOf(id);
                        if (idx > -1) activeFilters.labels.splice(idx, 1);
                        else activeFilters.labels.push(id);
                    }" @toggle-filter-member="(id) => {
                        const idx = activeFilters.members.indexOf(id);
                        if (idx > -1) activeFilters.members.splice(idx, 1);
                        else activeFilters.members.push(id);
                    }" @set-filter-priority="(p) => activeFilters.priority = p"
                    @set-filter-due-date="(d) => activeFilters.dueDate = d" @clear-filters="clearFilters"
                    @manage-members="modals.member = true" @manage-labels="modals.label = true" />

                <!-- Kanban Board -->
                <KanbanBoard :feature-lists="filteredFeatureLists" :team-members="teamMembers"
                    :available-labels="availableLabels" @add-list="handleAddFeatureList"
                    @rename-list="handleRenameFeatureList" @delete-list="handleDeleteFeatureList"
                    @add-feature="handleAddFeature" @open-feature="handleOpenFeature"
                    @move-feature="handleMoveFeature" />
            </template>
        </v-main>

        <!-- ==================== MODALS ==================== -->

        <!-- Feature Modal -->
        <FeatureModal v-model="modals.feature" :feature="selectedFeature" :team-members="teamMembers"
            :available-labels="availableLabels" :active-timers="activeTimers" :timer-displays="timerDisplays"
            @update-feature="handleUpdateFeature" @delete-feature="handleDeleteFeature"
            @add-task-list="handleAddTaskList" @rename-task-list="handleRenameTaskList"
            @delete-task-list="handleDeleteTaskList" @add-task="handleAddTask" @edit-task="handleEditTask"
            @delete-task="handleDeleteTask" @toggle-task-complete="handleToggleTaskComplete" @move-task="handleMoveTask"
            @start-timer="handleStartTimer" @pause-timer="handlePauseTimer" @stop-timer="handleStopTimer"
            @discard-timer="handleDiscardTimer" @add-time="handleAddTime"
            @update:model-value="(v) => { if (!v) handleCloseFeatureModal(); }" />

        <!-- Workspace Modal -->
        <WorkspaceModal v-model="modals.workspace" :workspace="editingWorkspace" @save="handleSaveWorkspace" />

        <!-- Board Modal -->
        <BoardModal v-model="modals.board" :board="editingBoard" :workspaces="workspaces"
            :default-workspace-id="workspaces[0]?.id" @save="handleSaveBoard" />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmModal v-model="modals.delete" :item-type="deleteTarget.type" :item-name="deleteTarget.itemName"
            @confirm="handleConfirmDelete" />

        <!-- Activity Log Modal -->
        <ActivityLogModal v-model="modals.activity" :activities="activityLog" :team-members="teamMembers"
            :filter-user-id="activityFilterUser" @update:filter-user-id="activityFilterUser = $event" />

        <!-- Time Tracking Modal -->
        <TimeTrackingModal v-model="modals.timeTracking" :team-members="teamMembers" :time-stats="getTeamTimeStats"
            :active-timers="activeTimers" />

        <!-- Member Modal -->
        <MemberModal v-model="modals.member" :members="getBoardMembers" :all-users="teamMembers"
            @add-member="(user) => { if (activeBoard) { if (!activeBoard.members) activeBoard.members = []; activeBoard.members.push(user.id); } }"
            @remove-member="(member) => { if (activeBoard?.members) { const idx = activeBoard.members.indexOf(member.id); if (idx > -1) activeBoard.members.splice(idx, 1); } }" />

        <!-- Label Modal -->
        <LabelModal v-model="modals.label" :labels="availableLabels"
            @create="(label) => { const id = Math.max(...availableLabels.map(l => l.id), 0) + 1; availableLabels.push({ ...label, id }); }"
            @update="(label) => { const idx = availableLabels.findIndex(l => l.id === label.id); if (idx > -1) availableLabels[idx] = label; }"
            @delete="(label) => { const idx = availableLabels.findIndex(l => l.id === label.id); if (idx > -1) availableLabels.splice(idx, 1); }" />

        <!-- Add Time Modal -->
        <AddTimeModal v-model="modals.addTime" :task="selectedTaskForTime" @save="handleSaveTimeEntry" />

        <!-- Task Edit Modal -->
        <TaskEditModal v-model="modals.taskEdit" :task="editingTask" :team-members="teamMembers"
            @save="handleSaveTask" />

        <!-- Snackbar for notifications -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000" location="bottom right">
            {{ snackbar.text }}
            <template #actions>
                <v-btn variant="text" @click="hideNotification">Close</v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
.bg-background {
    background-color: rgb(var(--v-theme-background));
}
</style>
