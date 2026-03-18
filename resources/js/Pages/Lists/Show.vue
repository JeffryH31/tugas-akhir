<script setup>
/**
 * List View Page - Kanban Board Style
 * 
 * Features:
 * - Board view with status columns
 * - Drag and drop tasks
 * - Task detail panel
 * - Add/edit tasks
 * - Gantt chart with CPM analysis (for subtasks)
 */
import { ref, computed, provide, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import StatusColumn from '@/Components/Tasks/StatusColumn.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';
import GanttChart from '@/Components/Cpm/GanttChart.vue';
import CpmSummary from '@/Components/Cpm/CpmSummary.vue';
import { PRIORITIES } from '@/constants/priorities';

const props = defineProps({
    workspace: Object,
    space: Object,
    list: Object,
    tasksByStatus: Object,
    statuses: Array,
    sprints: Array,
    parentTask: Object,
});

const page = usePage();

const initializeTasksByStatus = () => {
    const result = {};
    if (props.statuses) {
        props.statuses.forEach(status => {
            result[status.id] = Array.isArray(props.tasksByStatus?.[status.id])
                ? [...props.tasksByStatus[status.id]]
                : [];
        });
    }
    return result;
};

const localTasksByStatus = ref(initializeTasksByStatus());

// Watch for changes in props.tasksByStatus to update local state
watch(() => props.tasksByStatus, (newValue) => {
    localTasksByStatus.value = initializeTasksByStatus();
    
    // Also update selectedTask with fresh data if panel is open
    if (selectedTask.value) {
        const taskId = selectedTask.value.id;
        for (const statusId in newValue) {
            const tasks = newValue[statusId];
            const updatedTask = tasks.find(t => t.id === taskId);
            if (updatedTask) {
                selectedTask.value = { ...updatedTask };
                break;
            }
        }
    }
}, { deep: true });

// Watch for parentTask changes
watch(() => props.parentTask, (newValue) => {
}, { immediate: true });

// Selected task for detail panel
const selectedTask = ref(null);
const showTaskDetail = ref(false);

// View mode
const viewMode = ref('board'); // board, list, calendar, gantt

// CPM data for Gantt chart (only for subtasks)
const cpmData = ref(null);
const loadingCpm = ref(false);
const isAddingTask = ref(false);
const isDeleting = ref(false);

// Calendar state
const currentCalendarDate = ref(new Date());

// Filters
const filterStatus = ref([]);
const filterPriority = ref([]);
const filterAssignee = ref([]);
const searchQuery = ref('');

// Members and priorities from workspace
const members = computed(() => props.workspace?.members || []);
const labels = computed(() => props.workspace?.labels || []);

// Filtered tasks by status — applies search, priority, and assignee filters
const filteredTasksByStatus = computed(() => {
    const result = {};
    for (const statusId in localTasksByStatus.value) {
        let tasks = localTasksByStatus.value[statusId] || [];

        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            tasks = tasks.filter(t => t.name?.toLowerCase().includes(q));
        }

        if (filterPriority.value.length > 0) {
            tasks = tasks.filter(t => filterPriority.value.includes(t.priority_level));
        }

        if (filterAssignee.value.length > 0) {
            tasks = tasks.filter(t =>
                t.assignees?.some(a => filterAssignee.value.includes(a.id))
            );
        }

        result[statusId] = tasks;
    }
    return result;
});

// Handle task moved between columns
const handleTaskMoved = ({ task, statusId, newIndex }) => {
    if (props.parentTask) {
        // Subtask view — use subtask update route
        router.patch(
            route('tasks.subtasks.update', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, task.id]),
            { status_id: statusId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Subtask moved successfully!', 'success');
                    }
                    router.reload({ only: ['tasksByStatus'] });
                }
            }
        );
    } else {
        // Task view — use task change-status route
        router.patch(
            route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, task.id]),
            { status_id: statusId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task moved successfully!', 'success');
                    }
                    router.reload({ only: ['tasksByStatus'] });
                }
            }
        );
    }
};

// Handle task/subtask complete toggle
const handleTaskComplete = (task) => {
    if (!props.parentTask) return; // Tasks don't support completion

    const wasCompleted = !!task.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    router.post(
        route(routeName, [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, task.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar(wasCompleted ? 'Subtask reopened!' : 'Subtask completed!', 'success');
                }
                router.reload({ only: ['tasksByStatus'] });
            },
            onError: (errors) => {
                if (errors.dependency && window.showSnackbar) {
                    window.showSnackbar(errors.dependency, 'error');
                }
            }
        }
    );
};

// Handle task open
const handleTaskOpen = (task) => {
    selectedTask.value = task;
    showTaskDetail.value = true;
};

const findTaskInBoard = (id) => {
    if (!id) return null;
    for (const statusId in localTasksByStatus.value) {
        const found = (localTasksByStatus.value[statusId] || []).find(t => t.id === id);
        if (found) return found;
    }
    return null;
};

let openedFromQuery = false;
const openDetailFromQuery = () => {
    if (openedFromQuery) return;
    const queryString = page.url?.split('?')[1] || '';
    if (!queryString) return;

    const params = new URLSearchParams(queryString);
    const openTaskId = Number(params.get('open_task_id') || 0);
    const openSubtaskId = Number(params.get('open_subtask_id') || 0);

    if (openSubtaskId > 0) {
        const subtask = findTaskInBoard(openSubtaskId);
        if (subtask) {
            handleTaskOpen(subtask);
            openedFromQuery = true;
            return;
        }
    }

    // Only open task directly when viewing main task board (not subtask board)
    if (!props.parentTask && openTaskId > 0) {
        const task = findTaskInBoard(openTaskId);
        if (task) {
            handleTaskOpen(task);
            openedFromQuery = true;
        }
    }
};

watch(() => localTasksByStatus.value, () => {
    openDetailFromQuery();
}, { deep: true, immediate: true });

// Handle view subtasks
const viewSubtasks = (task) => {
    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]) + `?task_id=${task.id}`);
};

// Handle refresh tasks (after comment added, etc)
const refreshTasks = () => {
    router.reload({ only: ['tasksByStatus'] });
};

// Handle add task
const handleAddTask = ({ name, status_id }) => {
    if (isAddingTask.value) return;
    isAddingTask.value = true;

    // If we're viewing subtasks, use subtask route
    if (props.parentTask) {
        const data = {
            name,
            status_id,
            task_id: props.parentTask.id
        };

        router.post(
            route('tasks.subtasks.store', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]),
            data,
            {
                preserveScroll: true,
                onSuccess: (response) => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Subtask added successfully!', 'success');
                    }
                    // Reload subtask view
                    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]) + `?task_id=${props.parentTask.id}`, {
                        preserveScroll: true
                    });
                },
                onError: (errors) => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Failed to add subtask', 'error');
                    }
                },
                onFinish: () => { isAddingTask.value = false; }
            }
        );
    } else {
        // Creating a regular task
        const data = {
            name,
            status_id
        };

        router.post(
            route('tasks.store', [props.workspace.id, props.space.id, props.list.id]),
            data,
            {
                preserveScroll: true,
                onSuccess: (response) => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task added successfully!', 'success');
                    }
                    router.reload({ only: ['tasksByStatus'] });
                },
                onError: (errors) => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Failed to add task', 'error');
                    }
                },
                onFinish: () => { isAddingTask.value = false; }
            }
        );
    }
};

// Add status dialog
const showAddStatus = ref(false);
const newStatusName = ref('');
const newStatusColor = ref('#6366F1');

const addStatus = () => {
    if (!newStatusName.value.trim()) return;

    router.post(
        route('spaces.statuses.add', [props.workspace.id, props.space.id]),
        {
            name: newStatusName.value.trim(),
            color: newStatusColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Status added successfully!', 'success');
                }
            }
        }
    );

    newStatusName.value = '';
    showAddStatus.value = false;
};

// Color options
const colorOptions = [
    '#6366F1', '#8B5CF6', '#EC4899', '#EF4444',
    '#F59E0B', '#10B981', '#0EA5E9', '#06B6D4',
    '#84cc16', '#22c55e', '#14b8a6', '#0891b2',
];

// Edit list dialog
const showEditList = ref(false);
const editListName = ref('');

const openEditList = () => {
    editListName.value = props.list.name;
    showEditList.value = true;
};

const updateList = () => {
    if (!editListName.value.trim()) return;

    router.patch(
        route('lists.update', [props.workspace.id, props.space.id, props.list.id]),
        { name: editListName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditList.value = false;
            }
        }
    );
};

// Duplicate list
const duplicateList = () => {
    router.post(
        route('lists.duplicate', [props.workspace.id, props.space.id, props.list.id]),
        {},
        { preserveScroll: true }
    );
};

// Archive list
const archiveList = () => {
    router.post(
        route('lists.archive', [props.workspace.id, props.space.id, props.list.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
            }
        }
    );
};

// Delete list dialog
const showDeleteList = ref(false);

const confirmDeleteList = () => {
    if (isDeleting.value) return;
    isDeleting.value = true;
    router.delete(
        route('lists.destroy', [props.workspace.id, props.space.id, props.list.id]),
        {
            onSuccess: () => {
                router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
            },
            onFinish: () => { isDeleting.value = false; }
        }
    );
};

// Move to folder dialog
const showMoveToFolder = ref(false);
const selectedFolder = ref(null);

// Get available folders from space
const availableFolders = computed(() => {
    return [
        { id: null, name: 'No Folder (Root)' },
        ...(props.space?.folders || [])
    ];
});

const openMoveToFolder = () => {
    selectedFolder.value = props.list.folder_id;
    showMoveToFolder.value = true;
};

const moveToFolder = () => {
    router.post(
        route('lists.move-to-folder', [props.workspace.id, props.space.id, props.list.id]),
        { folder_id: selectedFolder.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMoveToFolder.value = false;
            }
        }
    );
};

// Calendar functions
const currentMonthName = computed(() => {
    return currentCalendarDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const calendarYear = computed(() => currentCalendarDate.value.getFullYear());
const calendarMonth = computed(() => currentCalendarDate.value.getMonth());

const daysInCurrentMonth = computed(() => {
    return new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate();
});

const firstDayOfCurrentMonth = computed(() => {
    return new Date(calendarYear.value, calendarMonth.value, 1).getDay();
});

const calendarDays = computed(() => {
    const days = [];
    const prevMonthDays = new Date(calendarYear.value, calendarMonth.value, 0).getDate();

    // Previous month days
    for (let i = firstDayOfCurrentMonth.value - 1; i >= 0; i--) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value - 1, prevMonthDays - i),
            isCurrentMonth: false,
        });
    }

    // Current month days
    for (let i = 1; i <= daysInCurrentMonth.value; i++) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value, i),
            isCurrentMonth: true,
        });
    }

    // Next month days to complete the grid
    const remainingDays = 42 - days.length;
    for (let i = 1; i <= remainingDays; i++) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value + 1, i),
            isCurrentMonth: false,
        });
    }

    return days;
});

// Get all items (tasks or subtasks) for calendar view
const allItems = computed(() => {
    return Object.values(filteredTasksByStatus.value).flat();
});

// Get items for a specific date
const getItemsForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    return allItems.value.filter(item => {
        const dueDate = item.due_date ? item.due_date.split('T')[0] : null;
        const startDate = item.start_date ? item.start_date.split('T')[0] : null;

        // If both start and due date exist, check if date is in range
        if (startDate && dueDate) {
            return dateStr >= startDate && dateStr <= dueDate;
        }
        // Otherwise, check if date matches either start or due date
        return dueDate === dateStr || startDate === dateStr;
    });
};

// Get item status
const getItemStatus = (item) => {
    return props.statuses.find(s => s.id === item.status_id);
};

// Check if date is today
const isDateToday = (date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
};

// Calendar navigation
const previousMonth = () => {
    currentCalendarDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1);
};

const nextMonth = () => {
    currentCalendarDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1);
};

const goToToday = () => {
    currentCalendarDate.value = new Date();
};

// CPM Analysis Functions
const fetchCpmData = async () => {
    if (!props.parentTask) return;
    
    loadingCpm.value = true;
    try {
        const response = await fetch(
            route('tasks.cpm.analyze', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]),
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );
        
        if (response.ok) {
            cpmData.value = await response.json();
        } else {
            cpmData.value = {
                success: false,
                message: 'Failed to fetch CPM data',
            };
        }
    } catch (error) {
        console.error('Error fetching CPM data:', error);
        cpmData.value = {
            success: false,
            message: 'An error occurred while calculating CPM',
        };
    } finally {
        loadingCpm.value = false;
    }
};

// Fetch CPM data when switching to gantt view or when viewing subtasks
watch(viewMode, (newMode) => {
    if (newMode === 'gantt' && props.parentTask && !cpmData.value) {
        fetchCpmData();
    }
});

// Also fetch when parentTask changes (entering subtask view)
watch(() => props.parentTask, (newParentTask) => {
    if (newParentTask) {
        cpmData.value = null; // Reset CPM data
        if (viewMode.value === 'gantt') {
            fetchCpmData();
        }
    }
}, { immediate: true });

// Handle subtask click from Gantt chart
const handleGanttSubtaskClick = (subtask) => {
    // Find the full subtask object from our local data
    for (const statusId in localTasksByStatus.value) {
        const found = localTasksByStatus.value[statusId].find(s => s.id === subtask.id);
        if (found) {
            handleTaskOpen(found);
            return;
        }
    }
};

// Handle dependency add/remove from Gantt chart
const handleGanttDependencyAdd = async ({ subtaskId, dependsOnId }) => {
    try {
        const response = await fetch(
            route('tasks.cpm.dependencies.add', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({
                    subtask_id: subtaskId,
                    depends_on_id: dependsOnId,
                    type: 'blocks',
                }),
            }
        );
        const result = await response.json();
        if (result.success) {
            await fetchCpmData();
            if (window.showSnackbar) window.showSnackbar('Dependency added!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    } catch {
        if (window.showSnackbar) window.showSnackbar('Failed to add dependency', 'error');
    }
};

const handleGanttDependencyRemove = async ({ subtaskId, dependsOnId }) => {
    try {
        const response = await fetch(
            route('tasks.cpm.dependencies.remove', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]),
            {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({
                    subtask_id: subtaskId,
                    depends_on_id: dependsOnId,
                }),
            }
        );
        const result = await response.json();
        if (result.success) {
            await fetchCpmData();
            if (window.showSnackbar) window.showSnackbar('Dependency removed!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    } catch {
        if (window.showSnackbar) window.showSnackbar('Failed to remove dependency', 'error');
    }
};

// Switch to Gantt view
const viewGantt = () => {
    viewMode.value = 'gantt';
    if (!cpmData.value) {
        fetchCpmData();
    }
};

// Check if a subtask is on the critical path (for board view highlighting)
const isSubtaskCritical = (subtaskId) => {
    if (!cpmData.value?.success) return false;
    return cpmData.value.data?.criticalPath?.includes(subtaskId) || false;
};

// Provide critical path info to child components
provide('isSubtaskCritical', isSubtaskCritical);
provide('cpmData', cpmData);

onMounted(() => {
    openDetailFromQuery();
});
</script>

<template>
    <MainLayout :title="list?.name || 'Product'">
        <div class="list-page">
            <!-- Breadcrumb -->
            <div class="breadcrumb-header">
                <div class="flex items-center gap-2 text-sm">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <a :href="route('dashboard')" class="text-gray-400 hover:text-white">Dashboard</a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a :href="route('workspaces.show', workspace.id)"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: workspace?.color || '#6366F1' }">
                            {{ workspace?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span>{{ workspace?.name }}</span>
                    </a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: space?.color || '#6366F1' }">
                            <v-icon size="10" color="white">{{ space?.icon || 'mdi-folder' }}</v-icon>
                        </div>
                        <a :href="route('spaces.show', [workspace.id, space.id])"
                            class="text-gray-400 hover:text-white">
                            {{ space?.name }}
                        </a>
                    </div>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a v-if="parentTask" :href="route('lists.show', [workspace.id, space.id, list.id])"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <v-icon size="16">mdi-package-variant-closed</v-icon>
                        <span>{{ list?.name }}</span>
                    </a>
                    <div v-else class="flex items-center gap-2">
                        <v-icon size="16" color="primary">mdi-package-variant-closed</v-icon>
                        <span class="font-medium text-white">{{ list?.name }}</span>
                    </div>
                    <template v-if="parentTask">
                        <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                        <div class="flex items-center gap-2">
                            <v-icon size="16" color="primary">mdi-file-tree-outline</v-icon>
                            <span class="font-medium text-white">{{ parentTask.name }}</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Subtask View Banner -->
            <div v-if="parentTask"
                class="bg-gradient-to-r from-blue-900/40 to-purple-900/40 border border-blue-700/30 rounded-lg p-3 mb-4 flex items-center gap-3">
                <v-btn icon="mdi-arrow-left" variant="tonal" size="small" color="primary"
                    @click="router.visit(route('lists.show', [workspace.id, space.id, list.id]))" />
                <div class="flex items-center gap-2 flex-1">
                    <v-icon size="20" color="primary">mdi-file-tree-outline</v-icon>
                    <div>
                        <div class="text-xs text-gray-400">Viewing subtasks of</div>
                        <div class="font-semibold text-white">{{ parentTask.name }}</div>
                    </div>
                </div>
                <v-chip size="small" color="primary" variant="flat">
                    Subtask Board
                </v-chip>
                <!-- CPM Analysis Button -->
                <v-btn 
                    variant="tonal" 
                    color="warning" 
                    size="small"
                    :loading="loadingCpm"
                    @click="viewGantt"
                >
                    <v-icon start size="16">mdi-chart-gantt</v-icon>
                    CPM Analysis
                </v-btn>
            </div>

            <!-- List Header -->
            <div class="list-header">
                <div class="flex items-center gap-3">
                    <!-- List Title -->
                    <h1 class="text-xl font-bold">
                        {{ parentTask ? 'Subtasks' : list?.name }}
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Search -->
                    <v-text-field v-model="searchQuery" placeholder="Search tasks..." prepend-inner-icon="mdi-magnify"
                        variant="outlined" density="compact" hide-details single-line style="width: 200px;" />

                    <!-- Filters -->
                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="outlined" size="small">
                                <v-icon start size="16">mdi-filter-variant</v-icon>
                                Filter
                            </v-btn>
                        </template>
                        <v-card width="280" color="surface">
                            <v-card-text>
                                <div class="text-sm font-medium mb-2">Filters</div>
                                <v-select v-model="filterPriority" :items="PRIORITIES" item-title="name" item-value="level"
                                    label="Priority" variant="outlined" density="compact" multiple chips closable-chips
                                    hide-details class="mb-3" bg-color="#1e1e1e"
                                    :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                                <v-autocomplete v-model="filterAssignee" :items="members" item-title="name"
                                    item-value="id" label="Assignee" variant="outlined" density="compact" multiple
                                    chips closable-chips hide-details bg-color="#1e1e1e"
                                    :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                            </v-card-text>
                            <v-card-actions>
                                <v-btn variant="text" size="small" @click="filterPriority = []; filterAssignee = []">
                                    Clear All
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>

                    <!-- View Mode -->
                    <v-btn-toggle v-model="viewMode" mandatory density="compact" variant="outlined">
                        <v-btn value="board" size="small">
                            <v-icon size="16">mdi-view-column</v-icon>
                        </v-btn>
                        <v-btn value="list" size="small">
                            <v-icon size="16">mdi-format-list-bulleted</v-icon>
                        </v-btn>
                        <v-btn value="calendar" size="small">
                            <v-icon size="16">mdi-calendar</v-icon>
                        </v-btn>
                        <!-- Gantt view only available for subtasks -->
                        <v-btn v-if="parentTask" value="gantt" size="small">
                            <v-icon size="16">mdi-chart-gantt</v-icon>
                        </v-btn>
                    </v-btn-toggle>

                    <!-- More Options -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-pencil-outline" title="Edit Product"
                                    @click="openEditList" />
                                <v-list-item prepend-icon="mdi-folder-move-outline" title="Move to Folder"
                                    @click="openMoveToFolder" />
                                <v-list-item prepend-icon="mdi-content-copy" title="Duplicate Product"
                                    @click="duplicateList" />
                                <v-list-item prepend-icon="mdi-archive-outline" title="Archive Product"
                                    @click="archiveList" />
                                <v-divider />
                                <v-list-item prepend-icon="mdi-delete-outline" title="Delete Product" class="text-error"
                                    @click="showDeleteList = true" />
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <!-- Board View -->
            <div v-if="viewMode === 'board'" class="board-container">
                <!-- Board columns (show even when empty for subtasks) -->
                <div class="board-columns">
                    <!-- Status Columns -->
                    <StatusColumn v-for="status in statuses" :key="status.id" :status="status" :statuses="statuses"
                        :tasks="filteredTasksByStatus[status.id] || []" :workspace="workspace" :space="space" :list="list"
                        :parent-task="parentTask" @task-moved="handleTaskMoved" @task-complete="handleTaskComplete"
                        @task-open="handleTaskOpen" @add-task="handleAddTask" />

                    <!-- Add Status Column -->
                    <div class="add-status-column">
                        <v-btn v-if="!showAddStatus" variant="text" block class="add-status-btn"
                            @click="showAddStatus = true">
                            <v-icon start>mdi-plus</v-icon>
                            Add Status
                        </v-btn>

                        <v-card v-else variant="outlined" rounded="lg" class="pa-3">
                            <v-text-field v-model="newStatusName" placeholder="Status name" variant="outlined"
                                density="compact" hide-details autofocus class="mb-2" @keydown.enter="addStatus"
                                @keydown.escape="showAddStatus = false" />
                            <div class="flex flex-wrap gap-1 mb-3">
                                <div v-for="color in colorOptions" :key="color"
                                    class="w-6 h-6 rounded cursor-pointer border-2"
                                    :class="color === newStatusColor ? 'border-white' : 'border-transparent'"
                                    :style="{ backgroundColor: color }" @click="newStatusColor = color" />
                            </div>
                            <div class="flex gap-2">
                                <v-btn color="primary" size="small" @click="addStatus">Add</v-btn>
                                <v-btn variant="text" size="small" @click="showAddStatus = false">Cancel</v-btn>
                            </div>
                        </v-card>
                    </div>
                </div>
            </div>

            <!-- List View (TODO) -->
            <div v-else-if="viewMode === 'list'" class="list-view">
                <v-card variant="outlined" rounded="lg">
                    <v-table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Task</th>
                                <th style="width: 150px;">Status</th>
                                <th style="width: 120px;">Priority</th>
                                <th style="width: 150px;">Assignee</th>
                                <th style="width: 120px;">Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="status in statuses" :key="status.id">
                                <tr v-for="task in filteredTasksByStatus[status.id] || []" :key="task.id" class="task-row"
                                    @click="handleTaskOpen(task)">
                                    <td>
                                        <v-checkbox-btn :model-value="!!task.completed_at"
                                            @click.stop="handleTaskComplete(task)" hide-details density="compact" />
                                    </td>
                                    <td>
                                        <div class="font-medium">{{ task.name }}</div>
                                        <div v-if="task.description" class="text-sm text-gray-500 truncate"
                                            style="max-width: 300px;">
                                            {{ task.description }}
                                        </div>
                                    </td>
                                    <td>
                                        <v-chip :color="status.color" size="small" variant="tonal">
                                            {{ status.name }}
                                        </v-chip>
                                    </td>
                                    <td>
                                        <v-chip v-if="task.priority" :color="task.priority.color" size="small"
                                            variant="tonal">
                                            {{ task.priority.name }}
                                        </v-chip>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td>
                                        <div v-if="task.assignees?.length" class="flex items-center gap-1">
                                            <v-avatar v-for="assignee in task.assignees.slice(0, 3)" :key="assignee.id"
                                                size="24" :color="assignee.avatar_color || 'primary'">
                                                <span class="text-xs">{{ assignee.initials }}</span>
                                            </v-avatar>
                                            <span v-if="task.assignees.length > 3" class="text-xs text-gray-500">
                                                +{{ task.assignees.length - 3 }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td>
                                        <span v-if="task.due_date" class="text-sm">
                                            {{ new Date(task.due_date).toLocaleDateString() }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!Object.values(filteredTasksByStatus).some(tasks => tasks.length)">
                                <td colspan="6" class="text-center py-12 text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-checkbox-marked-circle-outline</v-icon>
                                    <div>No tasks yet</div>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </div>

            <!-- Calendar View -->
            <div v-else-if="viewMode === 'calendar'" class="calendar-view">
                <div class="calendar-container">
                    <!-- Calendar Header -->
                    <div class="calendar-header">
                        <v-btn-group density="compact" variant="outlined" divided>
                            <v-btn @click="previousMonth">
                                <v-icon>mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn @click="goToToday" min-width="80">
                                Today
                            </v-btn>
                            <v-btn @click="nextMonth">
                                <v-icon>mdi-chevron-right</v-icon>
                            </v-btn>
                        </v-btn-group>
                        <h2 class="text-xl font-semibold">{{ currentMonthName }}</h2>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="mini-calendar-grid">
                        <!-- Day headers -->
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                            class="calendar-day-header">
                            {{ day }}
                        </div>

                        <!-- Calendar days -->
                        <div v-for="(day, index) in calendarDays" :key="index" class="calendar-cell" :class="{
                            'current-month': day.isCurrentMonth,
                            'other-month': !day.isCurrentMonth,
                            'today': isDateToday(day.date)
                        }">
                            <div class="cell-header">
                                <span class="day-num">{{ day.date.getDate() }}</span>
                            </div>

                            <div class="cell-tasks">
                                <div v-for="item in getItemsForDate(day.date)" :key="item.id" class="calendar-item"
                                    @click="handleTaskOpen(item)">
                                    <div class="item-dot"
                                        :style="{ backgroundColor: getItemStatus(item)?.color || '#6366F1' }" />
                                    <span class="item-name">{{ item.name }}</span>
                                    <v-icon v-if="item.completed_at" size="12" color="success">
                                        mdi-check-circle
                                    </v-icon>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gantt View (CPM Analysis) - Only for subtasks -->
            <div v-else-if="viewMode === 'gantt' && parentTask" class="gantt-view">
                <!-- Loading State -->
                <div v-if="loadingCpm" class="flex items-center justify-center h-64">
                    <v-progress-circular indeterminate color="primary" size="48" />
                    <span class="ml-4 text-gray-400">Calculating Critical Path...</span>
                </div>
                
                <!-- CPM Content -->
                <template v-else>
                    <!-- CPM Summary Card -->
                    <div class="mb-4">
                        <CpmSummary 
                            :cpm-data="cpmData" 
                            @subtask-click="handleGanttSubtaskClick"
                        />
                    </div>
                    
                    <!-- Gantt Chart -->
                    <GanttChart 
                        :cpm-data="cpmData"
                        :workspace="workspace"
                        :space="space"
                        :list="list"
                        :task="parentTask"
                        @subtask-click="handleGanttSubtaskClick"
                        @dependency-add="handleGanttDependencyAdd"
                        @dependency-remove="handleGanttDependencyRemove"
                    />
                </template>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel v-model="showTaskDetail" :task="selectedTask" :workspace="workspace" :space="space"
            :list="list" :parent-task="parentTask" :statuses="statuses" :members="members"
            :labels="labels" :sprints="sprints" @view-subtasks="viewSubtasks" @updated="refreshTasks" />

        <!-- Edit Product Dialog -->
        <v-dialog v-model="showEditList" max-width="400">
            <v-card>
                <v-card-title>Edit Product</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editListName" label="Product Name" variant="outlined" autofocus />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateList">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Product Dialog -->
        <v-dialog v-model="showDeleteList" max-width="400">
            <v-card>
                <v-card-title class="text-error">Delete Product?</v-card-title>
                <v-card-text>
                    Are you sure you want to delete "{{ list?.name }}"? This will also delete all tasks within this
                    product. This
                    action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteList = false">Cancel</v-btn>
                    <v-btn color="error" @click="confirmDeleteList">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Move to Folder Dialog -->
        <v-dialog v-model="showMoveToFolder" max-width="400">
            <v-card>
                <v-card-title>Move Product to Folder</v-card-title>
                <v-card-text>
                    <v-select v-model="selectedFolder" :items="availableFolders" item-title="name" item-value="id"
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e"
                        :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showMoveToFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="moveToFolder">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 48px);
}

.breadcrumb-header {
    padding: 12px 24px;
    border-bottom: 1px solid #2d2d30;
}

.breadcrumb-header a {
    transition: color 0.15s;
}

.list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid #2d2d30;
    flex-shrink: 0;
}

.board-container {
    flex: 1;
    overflow: hidden;
    padding: 16px;
}

.board-columns {
    display: flex;
    gap: 16px;
    height: 100%;
    overflow-x: auto;
    padding-bottom: 16px;
}

.add-status-column {
    width: 300px;
    min-width: 300px;
    flex-shrink: 0;
}

.add-status-btn {
    height: 48px;
    border: 2px dashed #2d2d30;
    border-radius: 8px;
    opacity: 0.6;
}

.add-status-btn:hover {
    opacity: 1;
    border-color: #3d3d40;
}

.list-view,
.calendar-view,
.gantt-view {
    padding: 24px;
}

.gantt-view {
    height: calc(100vh - 200px);
    overflow: auto;
}

.task-row {
    cursor: pointer;
    transition: background-color 0.15s;
}

.task-row:hover {
    background-color: #2d2d30;
}

/* Calendar View Styles */
.calendar-view {
    padding: 24px;
    height: 100%;
    overflow: auto;
}

.calendar-container {
    max-width: 1400px;
    margin: 0 auto;
}

.calendar-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.mini-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #2d2d30;
    border: 1px solid #2d2d30;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background-color: #252526;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-cell {
    background-color: #1e1e1e;
    min-height: 100px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.calendar-cell.other-month {
    background-color: #181818;
    opacity: 0.5;
}

.calendar-cell.today {
    background-color: #1a2332;
    border: 2px solid #4c9aff;
}

.cell-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.day-num {
    font-size: 14px;
    font-weight: 600;
    color: #c5c5c5;
}

.calendar-cell.today .day-num {
    background-color: #4c9aff;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cell-tasks {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    flex: 1;
}

.calendar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 6px;
    background-color: #252526;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 12px;
}

.calendar-item:hover {
    background-color: #2d2d30;
    transform: translateY(-1px);
}

.item-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.item-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #c5c5c5;
}
</style>
