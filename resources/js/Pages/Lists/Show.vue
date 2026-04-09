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
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { PRIORITIES } from '@/constants/priorities';
import {
    getStoredSubtaskCompletionTarget,
} from '@/utils/subtaskCompletionAutomation';

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
const { confirm: confirmDialog } = useConfirmDialog();

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
const allowedViewModes = ['board', 'list', 'calendar', 'sprint', 'gantt'];
const getUrlQueryParam = (key) => {
    const queryString = page.url?.split('?')[1] || '';
    if (!queryString) return null;
    return new URLSearchParams(queryString).get(key);
};

const getRequestedSprintIdFromUrl = () => {
    const raw = Number(getUrlQueryParam('sprint_id') || 0);
    return raw > 0 ? raw : null;
};

const resolveViewModeFromUrl = () => {
    const requested = getUrlQueryParam('view');
    if (!requested || !allowedViewModes.includes(requested)) {
        return 'board';
    }
    if (requested === 'gantt' && !props.parentTask) {
        return 'board';
    }
    return requested;
};

const viewMode = ref(resolveViewModeFromUrl()); // board, list, calendar, sprint, gantt

// CPM data for Gantt chart (only for subtasks)
const cpmData = ref(null);
const loadingCpm = ref(false);
const isAddingTask = ref(false);
const isDeleting = ref(false);

// Calendar state
const currentCalendarDate = ref(new Date());
const calendarSubView = ref('month');

// Sprint state (inline view mode)
const sprintSearchQuery = ref('');
const sprintFilterState = ref('all');
const sprintSortBy = ref('start_desc');
const showCreateSprint = ref(false);
const editingSprintId = ref(null);
const isSavingSprint = ref(false);
const selectedSprintId = ref(null);
const sprintForm = ref({
    list_id: props.list?.id || null,
    name: '',
    goal: '',
    start_date: '',
    end_date: '',
});
const sprintFormErrors = ref({
    name: '',
    start_date: '',
    end_date: '',
});

const sprintStateOptions = [
    { title: 'All States', value: 'all' },
    { title: 'Active', value: 'active' },
    { title: 'Planned', value: 'planned' },
    { title: 'Completed', value: 'completed' },
];

const sprintSortOptions = [
    { title: 'Start Date (Newest)', value: 'start_desc' },
    { title: 'Start Date (Oldest)', value: 'start_asc' },
    { title: 'End Date (Soonest)', value: 'end_asc' },
    { title: 'End Date (Latest)', value: 'end_desc' },
    { title: 'Most Tasks', value: 'tasks_desc' },
    { title: 'Name A-Z', value: 'name_asc' },
];

watch(() => props.list?.id, (id) => {
    if (!editingSprintId.value) {
        sprintForm.value.list_id = id || null;
    }
}, { immediate: true });

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

const siblingSubtasks = computed(() => {
    if (!props.parentTask) {
        return [];
    }

    const seen = new Set();
    return Object.values(localTasksByStatus.value)
        .flatMap((items) => Array.isArray(items) ? items : [])
        .filter((item) => {
            const id = Number(item?.id || 0);
            if (!id || seen.has(id)) {
                return false;
            }

            seen.add(id);
            return true;
        });
});

const getBoardOrderedIds = () => {
    return (props.statuses || []).flatMap((status) =>
        (localTasksByStatus.value[status.id] || []).map((item) => item.id)
    );
};

const persistBoardOrder = (successMessage) => {
    if (props.parentTask) {
        router.post(
            route('tasks.subtasks.reorder', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]),
            { subtask_ids: getBoardOrderedIds() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar && successMessage) {
                        window.showSnackbar(successMessage, 'success');
                    }
                    router.reload({ only: ['tasksByStatus'] });
                },
                onError: () => {
                    router.reload({ only: ['tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Failed to reorder subtasks', 'error');
                    }
                },
            }
        );
        return;
    }

    router.post(
        route('tasks.reorder', [props.workspace.id, props.space.id, props.list.id]),
        { order: getBoardOrderedIds() },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar && successMessage) {
                    window.showSnackbar(successMessage, 'success');
                }
                router.reload({ only: ['tasksByStatus'] });
            },
            onError: () => {
                router.reload({ only: ['tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Failed to reorder tasks', 'error');
                }
            },
        }
    );
};

// Handle task moved between columns or reordered in same column
const handleTaskMoved = ({ task, statusId, changeType }) => {
    const entity = props.parentTask ? 'Subtask' : 'Task';

    // Reorder inside the same status column
    if (changeType === 'moved') {
        persistBoardOrder(`${entity} order updated successfully!`);
        return;
    }

    if (props.parentTask) {
        // Cross-column move for subtasks
        router.patch(
            route('tasks.subtasks.update', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, task.id]),
            { status_id: statusId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    persistBoardOrder('Subtask moved successfully!');
                },
                onError: () => {
                    router.reload({ only: ['tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Failed to move subtask', 'error');
                    }
                },
            }
        );
        return;
    }

    // Cross-column move for tasks
    router.patch(
        route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, task.id]),
        { status_id: statusId },
        {
            preserveScroll: true,
            onSuccess: () => {
                persistBoardOrder('Task moved successfully!');
            },
            onError: () => {
                router.reload({ only: ['tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Failed to move task', 'error');
                }
            },
        }
    );
};

// Handle task/subtask complete toggle
const handleTaskComplete = (task) => {
    if (!props.parentTask) return; // Tasks don't support completion

    const wasCompleted = !!task.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    const targetStatusId = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    const payload = !wasCompleted && targetStatusId ? { target_status_id: targetStatusId } : {};

    router.post(
        route(routeName, [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, task.id]),
        payload,
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

const normalizeDateInput = (value) => {
    if (!value) return '';
    if (typeof value === 'string') return value.split('T')[0];

    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';

    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const resetSprintForm = () => {
    sprintForm.value = {
        list_id: props.list?.id || null,
        name: '',
        goal: '',
        start_date: '',
        end_date: '',
    };
    sprintFormErrors.value = {
        name: '',
        start_date: '',
        end_date: '',
    };
    editingSprintId.value = null;
};

const openCreateSprintDialog = () => {
    resetSprintForm();
    showCreateSprint.value = true;
};

const editSprint = (sprint) => {
    editingSprintId.value = sprint.id;
    sprintForm.value = {
        list_id: sprint.task_list_id || props.list?.id || null,
        name: sprint.name || '',
        goal: sprint.goal || '',
        start_date: normalizeDateInput(sprint.start_date),
        end_date: normalizeDateInput(sprint.end_date),
    };
    sprintFormErrors.value = {
        name: '',
        start_date: '',
        end_date: '',
    };
    showCreateSprint.value = true;
};

const validateSprintForm = () => {
    let isValid = true;
    sprintFormErrors.value = {
        name: '',
        start_date: '',
        end_date: '',
    };

    if (!sprintForm.value.name?.trim()) {
        sprintFormErrors.value.name = 'Sprint name is required';
        isValid = false;
    }

    if (!sprintForm.value.start_date) {
        sprintFormErrors.value.start_date = 'Start date is required';
        isValid = false;
    }

    if (!sprintForm.value.end_date) {
        sprintFormErrors.value.end_date = 'End date is required';
        isValid = false;
    }

    if (sprintForm.value.start_date && sprintForm.value.end_date) {
        if (new Date(sprintForm.value.end_date) <= new Date(sprintForm.value.start_date)) {
            sprintFormErrors.value.end_date = 'End date must be after start date';
            isValid = false;
        }
    }

    return isValid;
};

const applySprintErrors = (errors) => {
    sprintFormErrors.value = {
        name: errors.name || '',
        start_date: errors.start_date || '',
        end_date: errors.end_date || '',
    };
};

const saveSprint = () => {
    if (isSavingSprint.value) return;

    if (!validateSprintForm()) {
        window.showSnackbar?.('Please fill in required fields correctly.', 'error');
        return;
    }

    if (!sprintForm.value.list_id) {
        window.showSnackbar?.('No active product selected.', 'error');
        return;
    }

    isSavingSprint.value = true;
    const payload = {
        list_id: sprintForm.value.list_id,
        name: sprintForm.value.name.trim(),
        goal: sprintForm.value.goal?.trim() || '',
        start_date: sprintForm.value.start_date,
        end_date: sprintForm.value.end_date,
    };

    if (editingSprintId.value) {
        router.patch(route('sprints.update', [props.workspace.id, props.space.id, editingSprintId.value]), payload, {
            preserveScroll: true,
            onSuccess: () => {
                showCreateSprint.value = false;
                window.showSnackbar?.('Sprint updated!', 'success');
                router.reload({ only: ['sprints'] });
            },
            onError: (errors) => {
                applySprintErrors(errors);
            },
            onFinish: () => {
                isSavingSprint.value = false;
            },
        });
        return;
    }

    router.post(route('sprints.store', [props.workspace.id, props.space.id]), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateSprint.value = false;
            window.showSnackbar?.('Sprint created!', 'success');
            router.reload({ only: ['sprints'] });
        },
        onError: (errors) => {
            applySprintErrors(errors);
        },
        onFinish: () => {
            isSavingSprint.value = false;
        },
    });
};

const isSprintActive = (sprint) => {
    return !!sprint?.is_active;
};

const isSprintCompleted = (sprint) => {
    if (!sprint?.end_date) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(sprint.end_date);
    return !sprint?.is_active && today > end;
};

const formatSprintDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const getSprintRemainingDays = (sprint) => {
    if (!sprint?.end_date) return 0;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(sprint.end_date);
    end.setHours(0, 0, 0, 0);

    const diff = end.getTime() - today.getTime();
    return diff < 0 ? 0 : Math.ceil(diff / (1000 * 60 * 60 * 24));
};

const getSprintDurationDays = (sprint) => {
    if (!sprint?.start_date || !sprint?.end_date) return 0;

    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);

    const diff = end.getTime() - start.getTime();
    if (diff <= 0) return 1;
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
};

const getSprintProgressPercent = (sprint) => {
    if (isSprintCompleted(sprint)) return 100;
    if (!sprint?.start_date || !sprint?.end_date) return 0;

    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    const today = new Date();

    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);

    if (today <= start) return isSprintActive(sprint) ? 1 : 0;
    if (today >= end) return 100;

    const total = Math.max(end.getTime() - start.getTime(), 1);
    const elapsed = today.getTime() - start.getTime();
    return Math.min(100, Math.max(0, Math.round((elapsed / total) * 100)));
};

const getSprintStateMeta = (sprint) => {
    if (isSprintActive(sprint)) {
        return { label: 'Active', color: 'success', icon: 'mdi-rocket-launch-outline' };
    }

    if (isSprintCompleted(sprint)) {
        return { label: 'Completed', color: 'secondary', icon: 'mdi-check-circle-outline' };
    }

    return { label: 'Planned', color: 'info', icon: 'mdi-calendar-clock-outline' };
};

const activeSprint = computed(() => {
    const sprints = props.sprints || [];
    const explicitActive = sprints.find(s => isSprintActive(s));
    if (explicitActive) return explicitActive;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return sprints.find((s) => {
        if (!s.start_date || !s.end_date) return false;
        const start = new Date(s.start_date);
        const end = new Date(s.end_date);
        start.setHours(0, 0, 0, 0);
        end.setHours(23, 59, 59, 999);
        return today >= start && today <= end;
    }) || null;
});

const sprintSummary = computed(() => {
    const all = props.sprints || [];
    const activeCount = all.filter(s => isSprintActive(s)).length;
    const completedCount = all.filter(s => isSprintCompleted(s)).length;
    const plannedCount = all.length - activeCount - completedCount;
    const totalTasks = all.reduce((sum, s) => sum + (s.subtasks_count || 0), 0);

    return {
        total: all.length,
        active: activeCount,
        planned: plannedCount,
        completed: completedCount,
        totalTasks,
    };
});

const filteredSprints = computed(() => {
    let result = [...(props.sprints || [])];
    const q = sprintSearchQuery.value.trim().toLowerCase();

    if (q) {
        result = result.filter((sprint) => {
            return `${sprint.name || ''} ${sprint.goal || ''}`.toLowerCase().includes(q);
        });
    }

    if (sprintFilterState.value !== 'all') {
        result = result.filter((sprint) => {
            if (sprintFilterState.value === 'active') return isSprintActive(sprint);
            if (sprintFilterState.value === 'completed') return isSprintCompleted(sprint);
            if (sprintFilterState.value === 'planned') return !isSprintActive(sprint) && !isSprintCompleted(sprint);
            return true;
        });
    }

    const byStart = (a, b) => new Date(a.start_date) - new Date(b.start_date);
    const byEnd = (a, b) => new Date(a.end_date) - new Date(b.end_date);

    switch (sprintSortBy.value) {
        case 'start_asc':
            result.sort(byStart);
            break;
        case 'end_asc':
            result.sort(byEnd);
            break;
        case 'end_desc':
            result.sort((a, b) => byEnd(b, a));
            break;
        case 'tasks_desc':
            result.sort((a, b) => (b.subtasks_count || 0) - (a.subtasks_count || 0));
            break;
        case 'name_asc':
            result.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            break;
        case 'start_desc':
        default:
            result.sort((a, b) => byStart(b, a));
            break;
    }

    return result;
});

const openSprint = (sprint) => {
    selectedSprintId.value = sprint.id;
    openSprintBoard(sprint);
};

const openSprintBoard = (sprint) => {
    router.visit(route('sprints.show', [props.workspace.id, props.space.id, sprint.id]));
};

watch(filteredSprints, (items) => {
    if (!items.length) {
        selectedSprintId.value = null;
        return;
    }

    const stillExists = items.some(item => item.id === selectedSprintId.value);
    if (!stillExists) {
        const requestedSprintId = getRequestedSprintIdFromUrl();
        const requestedSprint = requestedSprintId
            ? items.find(item => item.id === requestedSprintId)
            : null;

        selectedSprintId.value = requestedSprint?.id || items[0].id;
    }
}, { immediate: true });

watch(() => page.url, () => {
    viewMode.value = resolveViewModeFromUrl();

    const requestedSprintId = getRequestedSprintIdFromUrl();
    if (!requestedSprintId) return;

    const sprintExists = (props.sprints || []).some(item => item.id === requestedSprintId);
    if (sprintExists) {
        selectedSprintId.value = requestedSprintId;
    }
});

const deleteSprint = async (sprint) => {
    const shouldDelete = await confirmDialog(
        'Delete this sprint? All attached subtasks will be moved back to backlog.',
        'Delete Sprint'
    );
    if (!shouldDelete) return;

    router.delete(route('sprints.destroy', [props.workspace.id, props.space.id, sprint.id]), {
        preserveScroll: true,
        onSuccess: () => {
            window.showSnackbar?.('Sprint deleted!', 'success');
            router.reload({ only: ['sprints'] });
        },
    });
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

const normalizeHexColor = (value, fallback = '#6366F1') => {
    const raw = (value || '').trim();
    if (!raw) return fallback;
    const hex = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.toUpperCase() : fallback;
};

const addStatus = () => {
    if (!newStatusName.value.trim()) return;

    router.post(
        route('spaces.statuses.add', [props.workspace.id, props.space.id]),
        {
            name: newStatusName.value.trim(),
            color: normalizeHexColor(newStatusColor.value),
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

const currentWeekLabel = computed(() => {
    const start = getWeekStart(currentCalendarDate.value);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    return `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

const calendarTitle = computed(() => {
    return calendarSubView.value === 'month' ? currentMonthName.value : currentWeekLabel.value;
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

const calendarWeeks = computed(() => {
    const weeks = [];
    for (let i = 0; i < calendarDays.value.length; i += 7) {
        weeks.push(calendarDays.value.slice(i, i + 7));
    }
    return weeks;
});

const weekDays = computed(() => {
    const start = getWeekStart(currentCalendarDate.value);
    const days = [];

    for (let i = 0; i < 7; i++) {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        days.push({
            date,
            isCurrentMonth: date.getMonth() === currentCalendarDate.value.getMonth(),
        });
    }

    return days;
});

const visibleCalendarDays = computed(() => {
    return calendarSubView.value === 'month' ? calendarDays.value : weekDays.value;
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

const getVisibleItemsForDate = (date, limit = 3) => {
    return getSingleDayItemsForDate(date).slice(0, limit);
};

const getOverflowItemsCount = (date, limit = 3) => {
    const total = getSingleDayItemsForDate(date).length;
    return total > limit ? total - limit : 0;
};

const toDateOnly = (value) => {
    if (!value) return null;
    const d = new Date(value);
    d.setHours(0, 0, 0, 0);
    return d;
};

const isSameDate = (a, b) => {
    return a && b && a.getTime() === b.getTime();
};

const getWeekStart = (date) => {
    const copy = new Date(date);
    const day = copy.getDay();
    copy.setDate(copy.getDate() - day);
    copy.setHours(0, 0, 0, 0);
    return copy;
};

const isItemStartDate = (item, date) => {
    const start = toDateOnly(item.start_date || item.due_date);
    const current = toDateOnly(date);
    return isSameDate(start, current);
};

const isItemEndDate = (item, date) => {
    const end = toDateOnly(item.due_date || item.start_date);
    const current = toDateOnly(date);
    return isSameDate(end, current);
};

const isMultiDayItem = (item) => {
    const start = toDateOnly(item.start_date);
    const end = toDateOnly(item.due_date);
    if (!start || !end) return false;
    return start.getTime() !== end.getTime();
};

const daysBetween = (start, end) => {
    const diff = end.getTime() - start.getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24));
};

const rangesOverlap = (startA, endA, startB, endB) => {
    return startA <= endB && startB <= endA;
};

const getItemRange = (item) => {
    const start = toDateOnly(item.start_date || item.due_date);
    const end = toDateOnly(item.due_date || item.start_date);
    if (!start || !end) return null;
    return start <= end ? { start, end } : { start: end, end: start };
};

const getSingleDayItemsForDate = (date) => {
    const current = toDateOnly(date);
    return allItems.value.filter(item => {
        const range = getItemRange(item);
        if (!range) return false;
        if (range.start.getTime() !== range.end.getTime()) return false;
        return isSameDate(range.start, current);
    });
};

const getWeekBars = (days) => {
    const weekStart = toDateOnly(days[0]?.date);
    const weekEnd = toDateOnly(days[6]?.date);
    if (!weekStart || !weekEnd) return [];

    const candidates = allItems.value
        .map(item => ({ item, range: getItemRange(item) }))
        .filter(({ range }) => range && range.start.getTime() !== range.end.getTime())
        .filter(({ range }) => rangesOverlap(range.start, range.end, weekStart, weekEnd))
        .sort((a, b) => {
            if (a.range.start.getTime() !== b.range.start.getTime()) {
                return a.range.start.getTime() - b.range.start.getTime();
            }
            return b.range.end.getTime() - a.range.end.getTime();
        });

    const lanes = [];
    const bars = [];

    candidates.forEach(({ item, range }) => {
        const visualStart = range.start < weekStart ? weekStart : range.start;
        const visualEnd = range.end > weekEnd ? weekEnd : range.end;

        const startCol = daysBetween(weekStart, visualStart) + 1;
        const endCol = daysBetween(weekStart, visualEnd) + 1;

        let laneIndex = 0;
        while (lanes[laneIndex] && lanes[laneIndex].some(seg => !(endCol < seg.startCol || startCol > seg.endCol))) {
            laneIndex += 1;
        }

        if (!lanes[laneIndex]) {
            lanes[laneIndex] = [];
        }
        lanes[laneIndex].push({ startCol, endCol });

        bars.push({
            item,
            row: laneIndex,
            startCol,
            endCol,
            startsBeforeWeek: range.start < weekStart,
            endsAfterWeek: range.end > weekEnd,
            color: getItemStatus(item)?.color || '#6366F1',
        });
    });

    return bars;
};

// Get item status
const getItemStatus = (item) => {
    return props.statuses.find(s => s.id === item.status_id);
};

const calendarCompletionRate = computed(() => {
    const total = allItems.value.length;
    if (!total) return 0;
    const completed = allItems.value.filter(i => i.completed_at).length;
    return Math.round((completed / total) * 100);
});

const calendarDueThisWeek = computed(() => {
    const weekStartDate = getWeekStart(currentCalendarDate.value);
    const weekEndDate = new Date(weekStartDate);
    weekEndDate.setDate(weekStartDate.getDate() + 6);

    return allItems.value.filter(item => {
        if (!item.due_date) return false;
        const due = toDateOnly(item.due_date);
        return due && due >= weekStartDate && due <= weekEndDate;
    }).length;
});

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

const previousWeek = () => {
    const d = new Date(currentCalendarDate.value);
    d.setDate(d.getDate() - 7);
    currentCalendarDate.value = d;
};

const nextWeek = () => {
    const d = new Date(currentCalendarDate.value);
    d.setDate(d.getDate() + 7);
    currentCalendarDate.value = d;
};

const previousCalendarPeriod = () => {
    if (calendarSubView.value === 'month') {
        previousMonth();
        return;
    }
    previousWeek();
};

const nextCalendarPeriod = () => {
    if (calendarSubView.value === 'month') {
        nextMonth();
        return;
    }
    nextWeek();
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
                <v-btn variant="tonal" color="warning" size="small" :loading="loadingCpm" @click="viewGantt">
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

                <div class="flex items-center gap-2 list-toolbar-controls">
                    <!-- Search -->
                    <v-text-field v-model="searchQuery" placeholder="Search tasks..." prepend-inner-icon="mdi-magnify"
                        variant="outlined" density="compact" hide-details single-line style="width: 200px;"
                        class="toolbar-search" />

                    <!-- Filters -->
                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="outlined" size="small" class="toolbar-filter-btn">
                                <v-icon start size="16">mdi-filter-variant</v-icon>
                                Filter
                            </v-btn>
                        </template>
                        <v-card width="280" color="surface">
                            <v-card-text>
                                <div class="text-sm font-medium mb-2">Filters</div>
                                <v-select v-model="filterPriority" :items="PRIORITIES" item-title="name"
                                    item-value="level" label="Priority" variant="outlined" density="compact" multiple
                                    chips closable-chips hide-details class="mb-3" bg-color="#1e1e1e" />
                                <v-autocomplete v-model="filterAssignee" :items="members" item-title="name"
                                    item-value="id" label="Assignee" variant="outlined" density="compact" multiple chips
                                    closable-chips hide-details bg-color="#1e1e1e" />
                            </v-card-text>
                            <v-card-actions>
                                <v-btn variant="text" size="small" @click="filterPriority = []; filterAssignee = []">
                                    Clear All
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>

                    <!-- View Mode + Sprint -->
                    <v-btn-group density="compact" variant="outlined" rounded="0" class="view-controls-group">
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'board' }" @click="viewMode = 'board'">
                            <v-icon size="16">mdi-view-column</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'list' }" @click="viewMode = 'list'">
                            <v-icon size="16">mdi-format-list-bulleted</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'calendar' }"
                            @click="viewMode = 'calendar'">
                            <v-icon size="16">mdi-calendar</v-icon>
                        </v-btn>
                        <v-btn size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'sprint' }" @click="viewMode = 'sprint'">
                            <v-icon size="16">mdi-calendar-clock</v-icon>
                        </v-btn>
                        <!-- Gantt view only available for subtasks -->
                        <v-btn v-if="parentTask" size="small" rounded="0" class="view-mode-btn"
                            :class="{ 'view-mode-btn--active': viewMode === 'gantt' }" @click="viewMode = 'gantt'">
                            <v-icon size="16">mdi-chart-gantt</v-icon>
                        </v-btn>
                    </v-btn-group>

                    <!-- More Options -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-account-lock-outline" title="Product Access"
                                    @click="router.visit(route('lists.settings', [workspace.id, space.id, list.id]))" />
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
                        :tasks="filteredTasksByStatus[status.id] || []" :workspace="workspace" :space="space"
                        :list="list" :parent-task="parentTask" @task-moved="handleTaskMoved"
                        @task-complete="handleTaskComplete" @task-open="handleTaskOpen" @add-task="handleAddTask" />

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
                            <div class="d-flex align-center ga-3 mb-3">
                                <input v-model="newStatusColor" type="color" class="color-input-native" />
                                <v-text-field
                                    v-model="newStatusColor"
                                    label="Hex Color"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    class="flex-1"
                                    @blur="newStatusColor = normalizeHexColor(newStatusColor)"
                                />
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
                                <tr v-for="task in filteredTasksByStatus[status.id] || []" :key="task.id"
                                    class="task-row" @click="handleTaskOpen(task)">
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
                            <v-btn @click="previousCalendarPeriod">
                                <v-icon>mdi-chevron-left</v-icon>
                            </v-btn>
                            <v-btn @click="goToToday" min-width="80">
                                Today
                            </v-btn>
                            <v-btn @click="nextCalendarPeriod">
                                <v-icon>mdi-chevron-right</v-icon>
                            </v-btn>
                        </v-btn-group>

                        <h2 class="text-xl font-semibold">{{ calendarTitle }}</h2>

                        <v-btn-toggle v-model="calendarSubView" mandatory density="compact" variant="outlined">
                            <v-btn value="month" size="small">
                                <v-icon size="14" class="mr-1">mdi-calendar-month</v-icon>
                                Month
                            </v-btn>
                            <v-btn value="week" size="small">
                                <v-icon size="14" class="mr-1">mdi-calendar-week</v-icon>
                                Week
                            </v-btn>
                        </v-btn-toggle>

                        <div class="ml-auto flex items-center gap-2">
                            <v-chip size="small" variant="tonal">
                                <v-icon start size="14">mdi-check-circle-outline</v-icon>
                                {{ calendarCompletionRate }}% complete
                            </v-chip>
                            <v-chip size="small" color="warning" variant="tonal">
                                <v-icon start size="14">mdi-calendar-clock</v-icon>
                                {{ calendarDueThisWeek }} due this week
                            </v-chip>
                        </div>
                    </div>

                    <!-- Calendar Grid -->
                    <div v-if="calendarSubView === 'month'" class="calendar-month-grid">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                            class="calendar-day-header">
                            {{ day }}
                        </div>

                        <div v-for="(week, weekIndex) in calendarWeeks" :key="`week-${weekIndex}`"
                            class="calendar-week-row">
                            <div class="week-bars-overlay"
                                :style="{ gridTemplateRows: `repeat(${Math.max(getWeekBars(week).length, 1)}, 22px)` }">
                                <div v-for="bar in getWeekBars(week)"
                                    :key="`bar-${weekIndex}-${bar.item.id}-${bar.row}`" class="calendar-span-bar"
                                    :style="{
                                        gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                        gridRow: bar.row + 1,
                                        backgroundColor: bar.color,
                                    }" @click="handleTaskOpen(bar.item)">
                                    <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                                    <span class="span-name">{{ bar.item.name }}</span>
                                    <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                                        :color="bar.item.assignees[0].avatar_color || 'primary'">
                                        <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                                    </v-avatar>
                                    <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                                    <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                                </div>
                            </div>

                            <div class="week-days-grid">
                                <div v-for="(day, dayIndex) in week" :key="`day-${weekIndex}-${dayIndex}`"
                                    class="calendar-cell" :class="{
                                        'current-month': day.isCurrentMonth,
                                        'other-month': !day.isCurrentMonth,
                                        'today': isDateToday(day.date)
                                    }">
                                    <div class="cell-header">
                                        <span class="day-num">{{ day.date.getDate() }}</span>
                                    </div>

                                    <div class="cell-tasks">
                                        <div v-for="item in getVisibleItemsForDate(day.date, 1)"
                                            :key="`single-${weekIndex}-${dayIndex}-${item.id}`" class="calendar-item"
                                            @click="handleTaskOpen(item)">
                                            <div class="item-dot"
                                                :style="{ backgroundColor: getItemStatus(item)?.color || '#6366F1' }" />
                                            <span class="item-name">{{ item.name }}</span>
                                            <v-icon v-if="item.completed_at" size="12" color="success">
                                                mdi-check-circle
                                            </v-icon>
                                        </div>

                                        <div v-if="getOverflowItemsCount(day.date, 1) > 0" class="calendar-overflow">
                                            +{{ getOverflowItemsCount(day.date, 1) }} more
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="calendar-week-only">
                        <div class="mini-calendar-grid week-grid">
                            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="`wk-${day}`"
                                class="calendar-day-header">
                                {{ day }}
                            </div>

                            <div v-for="(day, index) in weekDays" :key="`wkday-${index}`" class="calendar-cell" :class="{
                                'current-month': day.isCurrentMonth,
                                'other-month': !day.isCurrentMonth,
                                'today': isDateToday(day.date)
                            }">
                                <div class="cell-header">
                                    <span class="day-num">{{ day.date.getDate() }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="week-bars-grid week-bars-standalone"
                            :style="{ gridTemplateRows: `repeat(${Math.max(getWeekBars(weekDays).length, 1)}, 24px)` }">
                            <div v-for="bar in getWeekBars(weekDays)" :key="`wkbar-${bar.item.id}-${bar.row}`"
                                class="calendar-span-bar" :style="{
                                    gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                    gridRow: bar.row + 1,
                                    backgroundColor: bar.color,
                                }" @click="handleTaskOpen(bar.item)">
                                <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                                <span class="span-name">{{ bar.item.name }}</span>
                                <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                                    :color="bar.item.assignees[0].avatar_color || 'primary'">
                                    <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                                </v-avatar>
                                <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                                <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sprint View -->
            <div v-else-if="viewMode === 'sprint'" class="sprint-view">
                <div class="sprint-summary-grid">
                    <v-card class="sprint-summary-card" variant="outlined">
                        <div class="sprint-summary-label">Active Sprint</div>
                        <div class="sprint-summary-value">{{ activeSprint?.name || 'None' }}</div>
                    </v-card>
                    <v-card class="sprint-summary-card" variant="outlined">
                        <div class="sprint-summary-label">Total Sprints</div>
                        <div class="sprint-summary-value">{{ sprintSummary.total }}</div>
                    </v-card>
                    <v-card class="sprint-summary-card" variant="outlined">
                        <div class="sprint-summary-label">Completed</div>
                        <div class="sprint-summary-value">{{ sprintSummary.completed }}</div>
                    </v-card>
                    <v-card class="sprint-summary-card" variant="outlined">
                        <div class="sprint-summary-label">Total Tasks</div>
                        <div class="sprint-summary-value">{{ sprintSummary.totalTasks }}</div>
                    </v-card>
                </div>

                <div class="sprint-header-row">
                    <v-text-field v-model="sprintSearchQuery" placeholder="Search sprint..."
                        prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" hide-details
                        class="sprint-search" />

                    <v-select v-model="sprintFilterState" :items="sprintStateOptions" item-title="title"
                        item-value="value" label="Filter status" density="compact" variant="outlined" hide-details
                        bg-color="#1e1e1e" class="sprint-select" />

                    <v-select v-model="sprintSortBy" :items="sprintSortOptions" item-title="title" item-value="value"
                        label="Sort by" density="compact" variant="outlined" hide-details bg-color="#1e1e1e"
                        class="sprint-select" />

                    <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateSprintDialog">
                        Create Sprint
                    </v-btn>
                </div>

                <div v-if="!filteredSprints.length" class="sprint-empty-state">
                    <v-icon size="44" class="mb-2">mdi-calendar-clock-outline</v-icon>
                    <div class="text-subtitle-1">No sprint found</div>
                    <div class="text-sm text-gray-500">Try changing filter/search or create a new sprint.</div>
                </div>

                <div v-else class="sprint-grid">
                    <v-card v-for="sprint in filteredSprints" :key="sprint.id" class="sprint-card" variant="outlined"
                        rounded="lg" :class="{ 'sprint-card--selected': selectedSprintId === sprint.id }"
                        @click="openSprint(sprint)">
                        <div class="sprint-card-accent"
                            :class="`sprint-card-accent--${getSprintStateMeta(sprint).label.toLowerCase()}`" />

                        <v-card-title class="sprint-card-title-row">
                            <div class="sprint-card-title-main">
                                <v-icon size="16" class="sprint-card-title-icon">mdi-run-fast</v-icon>
                                <span class="truncate">{{ sprint.name }}</span>
                            </div>
                            <div class="flex items-center gap-2" @click.stop>
                                <v-chip :color="getSprintStateMeta(sprint).color" size="small"
                                    class="sprint-state-chip">
                                    <v-icon start size="14">{{ getSprintStateMeta(sprint).icon }}</v-icon>
                                    {{ getSprintStateMeta(sprint).label }}
                                </v-chip>
                                <v-menu>
                                    <template #activator="{ props: menuProps }">
                                        <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="x-small"
                                            variant="text" />
                                    </template>
                                    <v-card color="surface">
                                        <v-list density="compact">
                                            <v-list-item prepend-icon="mdi-pencil" title="Edit Sprint"
                                                @click="editSprint(sprint)" />
                                            <v-list-item prepend-icon="mdi-delete" title="Delete Sprint"
                                                class="text-error" @click="deleteSprint(sprint)" />
                                        </v-list>
                                    </v-card>
                                </v-menu>
                            </div>
                        </v-card-title>

                        <v-card-text class="sprint-card-content">
                            <div class="sprint-goal-text">
                                {{ sprint.goal || 'No sprint goal provided.' }}
                            </div>

                            <div class="sprint-date-pill">
                                <v-icon size="14">mdi-calendar-range</v-icon>
                                <span>{{ formatSprintDate(sprint.start_date) }}</span>
                                <span class="sprint-date-separator">to</span>
                                <span>{{ formatSprintDate(sprint.end_date) }}</span>
                            </div>

                            <div class="sprint-metrics-grid">
                                <div class="sprint-metric-item">
                                    <div class="sprint-metric-label">Tasks</div>
                                    <div class="sprint-metric-value">{{ sprint.subtasks_count || 0 }}</div>
                                </div>
                                <div class="sprint-metric-item">
                                    <div class="sprint-metric-label">Days Left</div>
                                    <div class="sprint-metric-value">{{ getSprintRemainingDays(sprint) }}</div>
                                </div>
                                <div class="sprint-metric-item">
                                    <div class="sprint-metric-label">Duration</div>
                                    <div class="sprint-metric-value">{{ getSprintDurationDays(sprint) }}d</div>
                                </div>
                                <div class="sprint-metric-item">
                                    <div class="sprint-metric-label">Progress</div>
                                    <div class="sprint-metric-value">{{ getSprintProgressPercent(sprint) }}%</div>
                                </div>
                            </div>

                            <div class="sprint-progress-block">
                                <v-progress-linear :model-value="getSprintProgressPercent(sprint)" height="8" rounded
                                    color="primary" bg-color="#2b3140" />
                            </div>
                        </v-card-text>
                    </v-card>
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
                        <CpmSummary :cpm-data="cpmData" @subtask-click="handleGanttSubtaskClick" />
                    </div>

                    <!-- Gantt Chart -->
                    <GanttChart :cpm-data="cpmData" :workspace="workspace" :space="space" :list="list"
                        :task="parentTask" @subtask-click="handleGanttSubtaskClick"
                        @dependency-add="handleGanttDependencyAdd" @dependency-remove="handleGanttDependencyRemove" />
                </template>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel v-model="showTaskDetail" :task="selectedTask" :workspace="workspace" :space="space"
            :list="list" :parent-task="parentTask" :statuses="statuses" :members="members" :labels="labels"
            :sprints="sprints" :sibling-subtasks="siblingSubtasks" @view-subtasks="viewSubtasks"
            @updated="refreshTasks" />

        <!-- Create/Edit Sprint Dialog -->
        <v-dialog v-model="showCreateSprint" max-width="620">
            <v-card color="surface">
                <v-card-title>{{ editingSprintId ? 'Edit Sprint' : 'Create Sprint' }}</v-card-title>
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        Product: {{ list?.name || 'Unknown' }}
                    </v-alert>

                    <v-text-field v-model="sprintForm.name" label="Sprint Name" variant="outlined" density="compact"
                        class="mb-3" bg-color="#1e1e1e" :error-messages="sprintFormErrors.name" required />

                    <v-textarea v-model="sprintForm.goal" label="Sprint Goal (Optional)" variant="outlined"
                        density="compact" rows="3" class="mb-3" bg-color="#1e1e1e" />

                    <div class="grid grid-cols-2 gap-3">
                        <v-text-field v-model="sprintForm.start_date" type="date" label="Start Date" variant="outlined"
                            density="compact" bg-color="#1e1e1e" :error-messages="sprintFormErrors.start_date"
                            required />
                        <v-text-field v-model="sprintForm.end_date" type="date" label="End Date" variant="outlined"
                            density="compact" bg-color="#1e1e1e" :error-messages="sprintFormErrors.end_date" required />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateSprint = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isSavingSprint" @click="saveSprint">
                        {{ editingSprintId ? 'Update' : 'Create' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

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
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e" />
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

.list-toolbar-controls {
    --toolbar-control-height: 40px;
}

.toolbar-search :deep(.v-field) {
    min-height: var(--toolbar-control-height) !important;
    height: var(--toolbar-control-height) !important;
}

.toolbar-search :deep(.v-field__input) {
    min-height: calc(var(--toolbar-control-height) - 2px) !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    align-items: center;
}

.toolbar-filter-btn {
    height: var(--toolbar-control-height) !important;
}

.view-controls-group {
    overflow: visible;
    border-radius: 10px;
}

.view-mode-btn {
    min-width: 52px;
    height: var(--toolbar-control-height) !important;
}

.view-mode-btn :deep(.v-btn__content) {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.view-mode-btn :deep(.v-icon) {
    line-height: 1 !important;
    overflow: visible;
}

.view-controls-group :deep(.v-btn:first-child) {
    border-top-left-radius: 10px !important;
    border-bottom-left-radius: 10px !important;
}

.view-controls-group :deep(.v-btn:last-child) {
    border-top-right-radius: 10px !important;
    border-bottom-right-radius: 10px !important;
}

.view-mode-btn--active {
    background: rgba(255, 255, 255, 0.14) !important;
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
.sprint-view,
.gantt-view {
    padding: 24px;
}

.sprint-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
    height: 100%;
    overflow: auto;
}

.sprint-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.sprint-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.sprint-summary-card {
    padding: 14px;
    background: #1f232b;
    border-color: #2f3542;
}

.sprint-summary-label {
    color: #95a1b3;
    font-size: 12px;
}

.sprint-summary-value {
    color: #f5f7fb;
    font-size: 22px;
    font-weight: 700;
    margin-top: 4px;
}

.sprint-header-row {
    display: grid;
    grid-template-columns: minmax(180px, 1.3fr) minmax(150px, 0.9fr) minmax(150px, 0.9fr) auto;
    align-items: center;
    gap: 12px;
}

.sprint-search,
.sprint-select {
    min-width: 0;
}

.sprint-select :deep(.v-field) {
    background-color: #1e1e1e !important;
}

.sprint-select :deep(.v-field__overlay) {
    background-color: #1e1e1e !important;
    opacity: 1 !important;
}

.sprint-select :deep(.v-field__input),
.sprint-select :deep(.v-field__append-inner) {
    color: #d7dce5 !important;
}

.sprint-empty-state {
    border: 1px dashed #3b3f46;
    border-radius: 12px;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #9aa4b2;
    gap: 4px;
}

.sprint-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.sprint-card {
    position: relative;
    overflow: hidden;
    background: linear-gradient(165deg, #212735 0%, #1b212d 100%);
    border-color: #313a4c;
    cursor: pointer;
    transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s;
}

.sprint-card:hover {
    border-color: #45516a;
    box-shadow: 0 8px 22px rgba(9, 12, 20, 0.42);
    transform: translateY(-2px);
}

.sprint-card--selected {
    border-color: #5e9dff;
    box-shadow: 0 0 0 1px rgba(94, 157, 255, 0.45), 0 8px 26px rgba(10, 40, 90, 0.35);
}

.sprint-card-accent {
    height: 4px;
    width: 100%;
}

.sprint-card-accent--active {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.sprint-card-accent--planned {
    background: linear-gradient(90deg, #38bdf8, #60a5fa);
}

.sprint-card-accent--completed {
    background: linear-gradient(90deg, #a78bfa, #818cf8);
}

.sprint-card-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 8px;
}

.sprint-card-title-main {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #f5f7fb;
}

.sprint-card-title-icon {
    color: #8fb7ff;
}

.sprint-state-chip {
    font-weight: 600;
}

.sprint-goal-text {
    color: #b6c0cf;
    font-size: 13px;
    line-height: 1.45;
    min-height: 40px;
    margin-bottom: 12px;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.sprint-card-content {
    padding-top: 0;
}

.sprint-date-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #c4ccda;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    padding: 6px 8px;
    margin-bottom: 12px;
}

.sprint-date-separator {
    color: #8f99aa;
    font-weight: 500;
}

.sprint-metrics-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.sprint-metric-item {
    border: 1px solid #364157;
    background: rgba(28, 34, 46, 0.72);
    border-radius: 10px;
    padding: 8px 10px;
}

.sprint-metric-label {
    font-size: 11px;
    color: #8f9ab0;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.sprint-metric-value {
    font-size: 18px;
    line-height: 1.15;
    color: #ecf2ff;
    font-weight: 700;
    margin-top: 2px;
}

.sprint-progress-block {
    margin-top: 12px;
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
    flex-wrap: wrap;
}

@media (max-width: 980px) {
    .sprint-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sprint-header-row {
        grid-template-columns: 1fr;
    }
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

.mini-calendar-grid.week-grid {
    grid-template-columns: repeat(7, minmax(180px, 1fr));
    overflow-x: auto;
}

.calendar-month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background-color: #262a33;
    border: 1px solid #313643;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.calendar-week-row {
    grid-column: 1 / -1;
    background: #1b1f27;
    border-top: 1px solid #2c3340;
    position: relative;
}

.week-days-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background: #2c3340;
    position: relative;
    z-index: 1;
}

.week-bars-overlay {
    position: absolute;
    top: 30px;
    left: 0;
    right: 0;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 3px;
    padding: 3px 8px 4px;
    pointer-events: none;
    z-index: 2;
    align-items: center;
    min-width: 0;
}

.week-bars-standalone {
    margin-top: 8px;
    border: 1px solid #2d2d30;
    border-radius: 8px;
}

.calendar-day-header {
    background-color: #20242d;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-cell {
    background-color: #191d25;
    min-height: 102px;
    padding: 34px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border: 1px solid #262d3a;
    position: relative;
}

.calendar-cell.other-month {
    background-color: #181818;
    opacity: 0.5;
}

.calendar-cell.today {
    background-color: #182438;
    border: 2px solid #4c9aff;
}

.cell-header {
    position: absolute;
    top: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    z-index: 3;
    pointer-events: none;
}

.day-num {
    font-size: 14px;
    font-weight: 600;
    color: #d7dce5;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    padding: 1px 8px;
    line-height: 20px;
}

.calendar-cell.today .day-num {
    background-color: #4c9aff;
    border-color: transparent;
    color: white;
    width: auto;
    height: auto;
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
    position: relative;
    z-index: 1;
}

.calendar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    background-color: #242b37;
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 11px;
    transform: none;
}

.calendar-item:hover {
    background-color: #2e3747;
    border-color: #3b82f6;
    transform: none;
}

.calendar-item-start {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

.calendar-item-end {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

.calendar-item-mid {
    border-radius: 4px;
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

.calendar-overflow {
    font-size: 11px;
    color: #b4bdca;
    padding: 2px 6px;
    border-radius: 6px;
    background: #273245;
    width: fit-content;
}

.calendar-span-bar {
    height: 18px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 7px;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    pointer-events: auto;
    position: relative;
    z-index: 2;
    transform: none;
    line-height: 1;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.calendar-span-bar:hover {
    filter: brightness(1.04);
    transform: none;
}

.span-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.color-input-native {
    width: 44px;
    height: 36px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    background: transparent;
    padding: 4px;
    cursor: pointer;
}

.color-input-native::-webkit-color-swatch-wrapper {
    padding: 0;
}

.color-input-native::-webkit-color-swatch {
    border: none;
    border-radius: 5px;
}
</style>
