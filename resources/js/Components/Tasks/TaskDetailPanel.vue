<script setup>
/**
 * Task Detail Panel - Slide-over panel for task details
 */
import { ref, computed, watch, reactive, nextTick, onMounted, onUnmounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { PRIORITIES, PRIORITY_MAP } from '@/constants/priorities';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    modelValue: Boolean,
    task: Object,
    workspace: Object,
    space: Object,
    list: Object,
    parentTask: Object,
    statuses: {
        type: Array,
        default: () => [],
    },
    members: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Array,
        default: () => [],
    },
    sprints: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'updated', 'view-subtasks']);

// Local reactive copy of task to ensure proper reactivity
const localTask = ref(null);

// Deep clone helper
const page = usePage();

const deepClone = (obj) => {
    if (!obj) return null;
    return JSON.parse(JSON.stringify(obj));
};

// Time tracking state (declared early so the watch below can reference them)
const isTracking = ref(false);
const trackingDuration = ref(0);
const trackingInterval = ref(null);
const runningEntryId = ref(null);
const isTimerLoading = ref(false);

const startTimerInterval = () => {
    if (trackingInterval.value) clearInterval(trackingInterval.value);
    trackingInterval.value = setInterval(() => {
        trackingDuration.value += 1;
    }, 1000);
};

const stopTimerInterval = () => {
    if (trackingInterval.value) {
        clearInterval(trackingInterval.value);
        trackingInterval.value = null;
    }
};

// Watch for task prop changes and update local copy
watch(() => props.task, (newTask, oldTask) => {
    localTask.value = deepClone(newTask);

    const oldId = oldTask?.id;
    const newId = newTask?.id;

    // Reset timer state when task identity changes (including null transitions)
    if (oldId !== newId) {
        stopTimerInterval();
        isTracking.value = false;
        trackingDuration.value = 0;
        runningEntryId.value = null;
        isTimerLoading.value = false;

        if (newTask) {
            nextTick(() => initRunningTimer());
        }
    }
}, { immediate: true, deep: true });

// Determine if we're viewing a subtask
const isSubtask = computed(() => !!props.parentTask);

// Helper function to get correct update route
const getUpdateRoute = () => {
    if (isSubtask.value) {
        return route('tasks.subtasks.update', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            props.task.id
        ]);
    }
    return route('tasks.update', [
        props.workspace.id,
        props.space.id,
        props.list.id,
        props.task.id
    ]);
};

// Helper function to get correct delete route
const getDeleteRoute = () => {
    if (isSubtask.value) {
        return route('tasks.subtasks.destroy', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            props.task.id
        ]);
    }
    return route('tasks.destroy', [
        props.workspace.id,
        props.space.id,
        props.list.id,
        props.task.id
    ]);
};

// Local state
const activeTab = ref('details');
const isEditing = ref(false);
const editedName = ref('');
const editedDescription = ref('');

// Form for updates
const form = useForm({
    name: '',
    description: '',
    status_id: null,
    priority_level: null,
    due_date: null,
    start_date: null,
    time_estimate: null,
});

// Watch task changes for form
watch(() => localTask.value, (newTask) => {
    if (newTask) {
        form.name = newTask.name;
        form.description = newTask.description || '';
        form.status_id = newTask.status_id;
        form.priority_level = newTask.priority_level;
        form.due_date = newTask.due_date;
        form.start_date = newTask.start_date;
        form.time_estimate = newTask.time_estimate;
        editedName.value = newTask.name;
        editedDescription.value = newTask.description || '';
    }
}, { immediate: true });

// Close panel
const close = () => {
    emit('update:modelValue', false);
};

// Is completed
const isCompleted = computed(() => localTask.value?.completed_at);

// Priority config
const priorityConfig = PRIORITY_MAP;

// Get current priority
const currentPriority = computed(() => {
    if (!localTask.value?.priority_level) return null;
    return PRIORITY_MAP[localTask.value.priority_level] || null;
});

// Format duration (for time spent in seconds)
const formatDuration = (seconds) => {
    if (!seconds) return 'Not set';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
};

// Format time estimate (in minutes, display as man-hour)
const formatTimeEstimate = (minutes) => {
    if (!minutes) return 'Not set';
    const hours = minutes / 60;
    return hours % 1 === 0 ? `${hours}h` : `${hours.toFixed(1)}h`;
};

// Date formatting
const formatDate = (dateStr) => {
    if (!dateStr) return 'Not set';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

// Save name
const saveName = () => {
    if (editedName.value.trim() && editedName.value !== props.task.name) {
        if (editedName.value.trim().length > 255) {
            if (window.showSnackbar) window.showSnackbar('Name cannot exceed 255 characters.', 'error');
            isEditing.value = false;
            return;
        }
        router.patch(
            getUpdateRoute(),
            { name: editedName.value.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['task', 'tasksByStatus'] });
                    if (window.showSnackbar) {
                        const itemType = isSubtask.value ? 'Subtask' : 'Task';
                        window.showSnackbar(`${itemType} name updated!`, 'success');
                    }
                }
            }
        );
    }
    isEditing.value = false;
};

// Save description
const saveDescription = () => {
    if (editedDescription.value !== props.task.description) {
        router.patch(
            getUpdateRoute(),
            { description: editedDescription.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['task', 'tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Description updated!', 'success');
                    }
                }
            }
        );
    }
};

// Change status
const changeStatus = (statusId) => {
    router.patch(
        getUpdateRoute(),
        { status_id: statusId },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Status changed!', 'success');
                }
            }
        }
    );
};

// Change priority
const changePriority = (priorityLevel) => {
    router.patch(
        getUpdateRoute(),
        { priority_level: priorityLevel },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Priority changed!', 'success');
                }
            }
        }
    );
};

// Change sprint
const changeSprint = (sprintId) => {
    router.patch(
        getUpdateRoute(),
        { sprint_id: sprintId },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar(sprintId ? 'Added to sprint!' : 'Removed from sprint!', 'success');
                }
            }
        }
    );
};

// Check if sprint is active based on dates
const isSprintActive = (sprint) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    return today >= start && today <= end;
};

// Subtask management
const showAddSubtaskPanel = ref(false);
const newSubtaskName = ref('');
const editingSubtaskId = ref(null);
const editingSubtaskName = ref('');

const addSubtaskPanel = () => {
    if (!newSubtaskName.value.trim()) return;

    router.post(
        route('tasks.store', [props.workspace.id, props.space.id, props.list.id]),
        {
            name: newSubtaskName.value,
            task_id: props.task.id,
            status_id: props.statuses[0]?.id,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newSubtaskName.value = '';
                showAddSubtaskPanel.value = false;
            },
            onFinish: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Subtask added!', 'success');
                }
            }
        }
    );
};

const toggleSubtaskPanel = (subtask) => {
    const wasCompleted = !!subtask.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    router.post(
        route(routeName, [props.workspace.id, props.space.id, props.list.id, props.task.id, subtask.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar(wasCompleted ? 'Subtask reopened!' : 'Subtask completed!', 'success');
                }
            }
        }
    );
};

const startEditSubtaskPanel = (subtask) => {
    editingSubtaskId.value = subtask.id;
    editingSubtaskName.value = subtask.name;
};

const cancelSubtaskEditPanel = () => {
    editingSubtaskId.value = null;
    editingSubtaskName.value = '';
};

const saveSubtaskEditPanel = (subtask) => {
    if (!editingSubtaskName.value.trim()) return;

    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
        { name: editingSubtaskName.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                cancelSubtaskEditPanel();
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Subtask updated!', 'success');
                }
            }
        }
    );
};

const deleteSubtaskPanel = async (subtask) => {
    if (await confirmDialog(`Delete subtask "${subtask.name}"?`, 'Delete Subtask')) {
        router.delete(
            route('tasks.destroy', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['task', 'tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Subtask deleted!', 'success');
                    }
                }
            }
        );
    }
};

// Update due date
const showDueDatePicker = ref(false);
const tempDueDate = ref(null);
const showStartDatePicker = ref(false);
const tempStartDate = ref(null);

const updateStartDate = () => {
    if (tempStartDate.value && props.task.due_date && tempStartDate.value > props.task.due_date) {
        if (window.showSnackbar) {
            window.showSnackbar('Start date cannot be after due date.', 'error');
        }
        return;
    }

    const oldValue = props.task.start_date;
    props.task.start_date = tempStartDate.value;
    showStartDatePicker.value = false;

    router.patch(
        getUpdateRoute(),
        { start_date: tempStartDate.value || '' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Start date updated!', 'success');
                }
            },
            onError: (errors) => {
                props.task.start_date = oldValue;
                if (window.showSnackbar) {
                    const msg = Object.values(errors).flat().join(', ');
                    window.showSnackbar(msg || 'Failed to update start date', 'error');
                }
            }
        }
    );
};

const openStartDatePicker = () => {
    tempStartDate.value = props.task.start_date;
    showStartDatePicker.value = true;
};

const updateDueDate = () => {
    if (tempDueDate.value && props.task.start_date && tempDueDate.value < props.task.start_date) {
        if (window.showSnackbar) {
            window.showSnackbar('Due date cannot be before start date.', 'error');
        }
        return;
    }

    const oldValue = props.task.due_date;
    props.task.due_date = tempDueDate.value;
    showDueDatePicker.value = false;

    router.patch(
        getUpdateRoute(),
        { due_date: tempDueDate.value || '' },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Due date updated!', 'success');
                }
            },
            onError: (errors) => {
                props.task.due_date = oldValue;
                if (window.showSnackbar) {
                    const msg = Object.values(errors).flat().join(', ');
                    window.showSnackbar(msg || 'Failed to update due date', 'error');
                }
            }
        }
    );
};

const openDueDatePicker = () => {
    tempDueDate.value = props.task.due_date;
    showDueDatePicker.value = true;
};

// Update time estimate
const showTimeEstimatePicker = ref(false);
const tempTimeEstimate = ref(0);

const updateTimeEstimate = () => {
    const hours = parseFloat(tempTimeEstimate.value) || 0;
    if (hours < 0) {
        if (window.showSnackbar) window.showSnackbar('Time estimate cannot be negative.', 'error');
        return;
    }
    if (hours > 8760) {
        if (window.showSnackbar) window.showSnackbar('Time estimate cannot exceed 1 year.', 'error');
        return;
    }
    const totalMinutes = hours * 60;
    const oldValue = props.task.time_estimate;
    props.task.time_estimate = totalMinutes;
    showTimeEstimatePicker.value = false;

    router.patch(
        getUpdateRoute(),
        { time_estimate: totalMinutes },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Time estimate updated!', 'success');
                }
            },
            onError: () => {
                props.task.time_estimate = oldValue;
            }
        }
    );
};

const openTimeEstimatePicker = () => {
    const minutes = props.task.time_estimate || 0;
    tempTimeEstimate.value = minutes / 60;
    showTimeEstimatePicker.value = true;
};

// Helper: fetch with fresh CSRF token (avoids 419 on long sessions)
const safeFetch = async (url, options = {}) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    return fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
        credentials: 'same-origin',
    });
};

const startTracking = async () => {
    if (!isSubtask.value || isTimerLoading.value) return;
    isTimerLoading.value = true;

    try {
        const url = route('tasks.timer.start', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
        ]);

        const res = await safeFetch(url, {
            method: 'POST',
            body: JSON.stringify({ subtask_id: props.task.id }),
        });

        if (res.ok || res.status === 302 || res.status === 303) {
            // Read entry ID from the JSON response
            const data = await res.json().catch(() => ({}));

            isTracking.value = true;
            trackingDuration.value = 0;
            runningEntryId.value = data.timeEntry?.id || data.time_entry?.id || data.id || null;
            startTimerInterval();

            // Reload to sync UI (include tasksByStatus for list views)
            router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });

            if (window.showSnackbar) {
                window.showSnackbar('Timer started!', 'success');
            }
        } else if (res.status === 419) {
            // Session expired — reload the page to get fresh token
            window.location.reload();
        } else {
            const data = await res.json().catch(() => ({}));
            if (window.showSnackbar) {
                window.showSnackbar(data.message || 'Failed to start timer', 'error');
            }
        }
    } catch (err) {
        if (window.showSnackbar) {
            window.showSnackbar('Failed to start timer', 'error');
        }
    } finally {
        isTimerLoading.value = false;
    }
};

const stopTracking = async () => {
    if (isTimerLoading.value) return;
    isTimerLoading.value = true;
    stopTimerInterval();

    // Find the running entry ID from multiple sources
    let entryId = runningEntryId.value;

    // Source 2: task's time_entries
    if (!entryId) {
        const runningEntry = props.task.time_entries?.find(e => e.is_running);
        entryId = runningEntry?.id;
    }

    // Source 3: global running timer from shared Inertia props
    if (!entryId) {
        const globalTimer = page.props.runningTimer;
        if (globalTimer?.subtask_id === props.task.id && globalTimer?.is_running) {
            entryId = globalTimer.id;
        }
    }

    // Source 4: ask the server directly for the running timer
    if (!entryId) {
        try {
            const runningRes = await safeFetch(route('time-tracking.running'), { method: 'GET' });
            if (runningRes.ok) {
                const runningData = await runningRes.json().catch(() => ({}));
                if (runningData.timer?.id) {
                    entryId = runningData.timer.id;
                }
            }
        } catch (e) { }
    }

    if (!entryId) {
        if (window.showSnackbar) {
            window.showSnackbar('No running timer found.', 'warning');
        }
        isTracking.value = false;
        trackingDuration.value = 0;
        isTimerLoading.value = false;
        router.reload({ preserveScroll: true });
        return;
    }

    try {
        const url = route('tasks.timer.stop', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            entryId,
        ]);

        const res = await safeFetch(url, { method: 'POST' });

        if (res.ok || res.status === 302 || res.status === 303) {
            const data = await res.json().catch(() => ({}));

            isTracking.value = false;
            runningEntryId.value = null;

            if (window.showSnackbar) {
                window.showSnackbar(`Timer stopped: ${formatTrackingDuration.value}`, 'success');
            }

            // Update local data instantly for responsive UI
            if (data.timeEntry) {
                const stoppedEntry = data.timeEntry;
                const oldSpent = localTask.value.time_spent || 0;
                localTask.value.time_spent = oldSpent + (stoppedEntry.duration || 0);

                if (localTask.value.time_entries) {
                    const idx = localTask.value.time_entries.findIndex(e => e.id === stoppedEntry.id);
                    if (idx >= 0) {
                        localTask.value.time_entries[idx] = stoppedEntry;
                    } else {
                        localTask.value.time_entries.push(stoppedEntry);
                    }
                }
            } else {
                const minutesTracked = Math.max(1, Math.ceil(trackingDuration.value / 60));
                localTask.value.time_spent = (localTask.value.time_spent || 0) + minutesTracked;
            }

            trackingDuration.value = 0;

            // Reload to sync navbar timer chip and server state
            router.reload({ preserveScroll: true, only: ['task', 'tasksByStatus', 'runningTimer'] });
        } else if (res.status === 419) {
            window.location.reload();
        } else {
            if (window.showSnackbar) {
                window.showSnackbar('Failed to stop timer', 'error');
            }
        }
    } catch (err) {
        if (window.showSnackbar) {
            window.showSnackbar('Failed to stop timer', 'error');
        }
    } finally {
        isTimerLoading.value = false;
    }
};

// Check for existing running timer on this subtask (e.g. page reload, browser reopened)
const initRunningTimer = async () => {
    if (!isSubtask.value) return;

    const currentTaskId = props.task?.id;

    // Source 1: task's time_entries (from list data)
    const runningEntry = props.task?.time_entries?.find(e => e.is_running);
    if (runningEntry && runningEntry.subtask_id === currentTaskId) {
        isTracking.value = true;
        runningEntryId.value = runningEntry.id;
        const startedAt = new Date(runningEntry.started_at).getTime();
        trackingDuration.value = Math.floor((Date.now() - startedAt) / 1000);
        startTimerInterval();
        return;
    }

    // Source 2: global running timer from shared Inertia props
    const globalTimer = page.props.runningTimer;
    if (globalTimer?.subtask_id === currentTaskId && globalTimer?.is_running) {
        isTracking.value = true;
        runningEntryId.value = globalTimer.id;
        const startedAt = new Date(globalTimer.started_at).getTime();
        trackingDuration.value = Math.floor((Date.now() - startedAt) / 1000);
        startTimerInterval();
        return;
    }

    // Source 3: ask the server (handles stale local/global data)
    try {
        const res = await safeFetch(route('time-tracking.running'), { method: 'GET' });
        if (res.ok && props.task?.id === currentTaskId) {
            const data = await res.json().catch(() => ({}));
            const timer = data.timer;
            if (timer?.subtask_id === currentTaskId && (timer?.is_running ?? false)) {
                isTracking.value = true;
                runningEntryId.value = timer.id;
                const startedAt = new Date(timer.started_at).getTime();
                trackingDuration.value = Math.floor((Date.now() - startedAt) / 1000);
                startTimerInterval();
            }
        }
    } catch (e) { }
};

const formatTrackingDuration = computed(() => {
    const hours = Math.floor(trackingDuration.value / 3600);
    const minutes = Math.floor((trackingDuration.value % 3600) / 60);
    const seconds = trackingDuration.value % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Initialize running timer on mount, cleanup on unmount
onMounted(() => {
    initRunningTimer();
});

onUnmounted(() => {
    stopTimerInterval();
});

// Re-check running timer when time_entries changes (e.g. after reload)
watch(() => props.task?.time_entries, () => {
    if (!isTracking.value) {
        initRunningTimer();
    } else {
        const runningEntry = props.task?.time_entries?.find(e => e.is_running);
        if (runningEntry) {
            runningEntryId.value = runningEntry.id;
        } else {
            // Timer was stopped externally (e.g. another subtask started)
            stopTimerInterval();
            isTracking.value = false;
            trackingDuration.value = 0;
            runningEntryId.value = null;
        }
    }
}, { deep: true });

// ===== Dependency Management =====
const dependencyLoading = ref(false);
const dependencyTab = ref('waiting');

const getAvailablePredecessors = computed(() => {
    if (!isSubtask.value || !props.parentTask?.subtasks) return [];
    const existingDepIds = (props.task.dependencies || []).map(d => d.id);
    const existingDependentIds = (props.task.dependents || []).map(d => d.id);
    return props.parentTask.subtasks.filter(
        s => s.id !== props.task.id && !existingDepIds.includes(s.id) && !existingDependentIds.includes(s.id)
    );
});

const getAvailableSuccessors = computed(() => {
    if (!isSubtask.value || !props.parentTask?.subtasks) return [];
    const existingDepIds = (props.task.dependencies || []).map(d => d.id);
    const existingDependentIds = (props.task.dependents || []).map(d => d.id);
    return props.parentTask.subtasks.filter(
        s => s.id !== props.task.id && !existingDepIds.includes(s.id) && !existingDependentIds.includes(s.id)
    );
});

const addDependency = (dependsOn) => {
    if (!isSubtask.value || !props.parentTask) return;
    dependencyLoading.value = true;

    fetch(
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
                subtask_id: props.task.id,
                depends_on_id: dependsOn.id,
                type: 'blocks',
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            emit('updated');
            if (window.showSnackbar) window.showSnackbar('Dependency added!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    }).catch(() => {
        if (window.showSnackbar) window.showSnackbar('Failed to add dependency', 'error');
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const addSuccessor = (successor) => {
    if (!isSubtask.value || !props.parentTask) return;
    dependencyLoading.value = true;

    fetch(
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
                subtask_id: successor.id,
                depends_on_id: props.task.id,
                type: 'blocks',
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            emit('updated');
            if (window.showSnackbar) window.showSnackbar('Dependency added!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    }).catch(() => {
        if (window.showSnackbar) window.showSnackbar('Failed to add dependency', 'error');
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const removeDependency = (dependsOn) => {
    if (!isSubtask.value || !props.parentTask) return;
    dependencyLoading.value = true;

    fetch(
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
                subtask_id: props.task.id,
                depends_on_id: dependsOn.id,
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            emit('updated');
            if (window.showSnackbar) window.showSnackbar('Dependency removed!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    }).catch(() => {
        if (window.showSnackbar) window.showSnackbar('Failed to remove dependency', 'error');
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const removeSuccessor = (successor) => {
    if (!isSubtask.value || !props.parentTask) return;
    dependencyLoading.value = true;

    fetch(
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
                subtask_id: successor.id,
                depends_on_id: props.task.id,
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            emit('updated');
            if (window.showSnackbar) window.showSnackbar('Dependency removed!', 'success');
        } else {
            if (window.showSnackbar) window.showSnackbar(result.message || 'Failed', 'error');
        }
    }).catch(() => {
        if (window.showSnackbar) window.showSnackbar('Failed to remove dependency', 'error');
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const formatSubtaskEstimate = (minutes) => {
    if (!minutes) return '';
    const hours = minutes / 60;
    return hours >= 1 ? `${hours}h` : `${minutes}m`;
};

// Available labels (not yet assigned to this task)
const availableLabels = computed(() => {
    const taskLabelIds = (localTask.value?.labels || []).map(l => l.id);
    return props.labels.filter(l => !taskLabelIds.includes(l.id));
});

// Add label to task
const addLabel = (label) => {
    // Optimistic update
    if (!localTask.value.labels) localTask.value.labels = [];
    localTask.value.labels.push(label);

    const url = isSubtask.value
        ? route('tasks.subtasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id])
        : route('tasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.task.id]);

    router.post(
        url,
        { label_id: label.id },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) window.showSnackbar('Label added!', 'success');
            },
            onError: () => {
                localTask.value.labels = localTask.value.labels.filter(l => l.id !== label.id);
                if (window.showSnackbar) window.showSnackbar('Failed to add label', 'error');
            }
        }
    );
};

// Remove label from task
const removeLabel = (label) => {
    // Optimistic update
    const backup = [...(localTask.value.labels || [])];
    localTask.value.labels = localTask.value.labels.filter(l => l.id !== label.id);

    const url = isSubtask.value
        ? route('tasks.subtasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id])
        : route('tasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.task.id]);

    router.delete(
        url,
        {
            data: { label_id: label.id },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
                if (window.showSnackbar) window.showSnackbar('Label removed!', 'success');
            },
            onError: () => {
                localTask.value.labels = backup;
                if (window.showSnackbar) window.showSnackbar('Failed to remove label', 'error');
            }
        }
    );
};

// Toggle assignee
const toggleAssignee = (userId) => {
    const isAssigned = props.task.assignees?.some(a => a.id === userId);
    const member = props.members.find(m => m.id === userId);

    // Optimistically update UI
    if (isAssigned) {
        props.task.assignees = props.task.assignees.filter(a => a.id !== userId);
    } else {
        if (!props.task.assignees) {
            props.task.assignees = [];
        }
        props.task.assignees.push(member);
    }

    if (isSubtask.value) {
        // For subtasks, use update route with assignee_ids
        const currentAssigneeIds = props.task.assignees?.map(a => a.id) || [];

        router.patch(
            getUpdateRoute(),
            { assignee_ids: currentAssigneeIds },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar(isAssigned ? 'Assignee removed!' : 'Assignee added!', 'success');
                    }
                },
                onError: () => {
                    // Revert on error
                    if (isAssigned) {
                        props.task.assignees.push(member);
                    } else {
                        props.task.assignees = props.task.assignees.filter(a => a.id !== userId);
                    }
                }
            }
        );
    } else {
        // For tasks, use assign/unassign routes
        if (isAssigned) {
            router.delete(
                route('tasks.unassign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
                {
                    data: { user_id: userId },
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        if (window.showSnackbar) {
                            window.showSnackbar('Assignee removed!', 'success');
                        }
                    },
                    onError: () => {
                        props.task.assignees.push(member);
                    }
                }
            );
        } else {
            router.post(
                route('tasks.assign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
                { user_id: userId },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        if (window.showSnackbar) {
                            window.showSnackbar('Assignee added!', 'success');
                        }
                    },
                }
            );
        }
    }
};

// Complete subtask (only subtasks support completion)
const toggleComplete = () => {
    if (!isSubtask.value) return; // Tasks don't support completion

    const wasCompleted = isCompleted.value;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    router.post(
        route(routeName, [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar(wasCompleted ? 'Subtask reopened!' : 'Subtask completed!', 'success');
                }
                router.reload({ only: ['task', 'tasksByStatus'] });
            }
        }
    );
};

// Delete task
const deleteTask = async () => {
    if (await confirmDialog('Are you sure you want to delete this task?', 'Delete Task')) {
        router.delete(
            route('tasks.destroy', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task deleted!', 'success');
                    }
                    close();
                }
            }
        );
    }
};

// Comment form
const newComment = ref('');
const isSubmittingComment = ref(false);

const submitComment = () => {
    if (!newComment.value.trim() || isSubmittingComment.value) return;

    // Get the correct task ID for comments (always use main task, not subtask)
    const taskId = isSubtask.value ? props.parentTask.id : props.task.id;

    isSubmittingComment.value = true;

    const commentData = { content: newComment.value };
    if (isSubtask.value) {
        commentData.subtask_id = props.task.id;
    }

    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, taskId]),
        commentData,
        {
            preserveScroll: true,
            onSuccess: () => {
                newComment.value = '';
                if (window.showSnackbar) {
                    window.showSnackbar('Comment added!', 'success');
                }
                // Emit updated event to refresh task data
                emit('updated');
            },
            onError: (errors) => {
                if (window.showSnackbar) {
                    window.showSnackbar('Failed to add comment', 'error');
                }
            },
            onFinish: () => {
                isSubmittingComment.value = false;
            }
        }
    );
};

// Time entry management
const newTimeEntry = ref({
    duration: null,
    description: ''
});

const addTimeEntry = () => {
    if (!newTimeEntry.value.duration || !isSubtask.value) return;

    const hours = parseFloat(newTimeEntry.value.duration);
    if (isNaN(hours) || hours <= 0) {
        if (window.showSnackbar) window.showSnackbar('Duration must be greater than 0.', 'error');
        return;
    }
    if (hours > 24) {
        if (window.showSnackbar) window.showSnackbar('Duration cannot exceed 24 hours.', 'error');
        return;
    }
    if (newTimeEntry.value.description && newTimeEntry.value.description.length > 500) {
        if (window.showSnackbar) window.showSnackbar('Description cannot exceed 500 characters.', 'error');
        return;
    }

    const durationInMinutes = hours * 60;

    router.post(
        route('tasks.subtasks.time-entries.store', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            props.task.id
        ]),
        {
            duration: durationInMinutes,
            description: newTimeEntry.value.description
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newTimeEntry.value = { duration: null, description: '' };
                if (window.showSnackbar) {
                    window.showSnackbar('Time entry added!', 'success');
                }
                router.reload({ only: ['task', 'tasksByStatus'] });
            }
        }
    );
};

const deleteTimeEntry = async (entryId) => {
    if (!await confirmDialog('Delete this time entry?', 'Delete Time Entry')) return;

    router.delete(
        route('tasks.subtasks.time-entries.destroy', [
            props.workspace.id,
            props.space.id,
            props.list.id,
            props.parentTask.id,
            props.task.id,
            entryId
        ]),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Time entry deleted!', 'success');
                }
                router.reload({ only: ['task', 'tasksByStatus'] });
            }
        }
    );
};
</script>

<template>
    <v-navigation-drawer :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)"
        location="right" temporary width="620" class="task-detail-panel">
        <div v-if="localTask" class="d-flex flex-column h-100">

            <!-- ===== Header ===== -->
            <div class="panel-header">
                <div class="d-flex align-center ga-2">
                    <!-- Complete Button (subtask only) -->
                    <v-btn v-if="isSubtask"
                        :icon="isCompleted ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                        :color="isCompleted ? 'success' : 'grey'" variant="text" size="small" @click="toggleComplete" />

                    <!-- Status Chip -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-chip v-bind="menuProps" :color="localTask.status?.color" size="small" variant="flat"
                                class="cursor-pointer font-weight-medium">
                                {{ localTask.status?.name || 'No Status' }}
                                <v-icon end size="14">mdi-chevron-down</v-icon>
                            </v-chip>
                        </template>
                        <v-card color="surface" min-width="180">
                            <v-list density="compact">
                                <v-list-item v-for="status in statuses" :key="status.id"
                                    :active="status.id === localTask.status_id" @click="changeStatus(status.id)">
                                    <template v-slot:prepend>
                                        <div class="w-3 h-3 rounded-full mr-3"
                                            :style="{ backgroundColor: status.color }" />
                                    </template>
                                    <v-list-item-title>{{ status.name }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card>
                    </v-menu>

                    <!-- Task ID badge -->
                    <v-chip v-if="localTask.task_id" size="x-small" variant="outlined" color="grey">
                        {{ localTask.task_id }}
                    </v-chip>
                </div>

                <div class="d-flex align-center">
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon size="20">mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface" min-width="160">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-content-copy" title="Duplicate" />
                                <v-list-item prepend-icon="mdi-archive-outline" title="Archive" />
                                <v-divider />
                                <v-list-item prepend-icon="mdi-delete-outline" title="Delete" class="text-error"
                                    @click="deleteTask" />
                            </v-list>
                        </v-card>
                    </v-menu>
                    <v-btn icon variant="text" size="small" @click="close">
                        <v-icon size="20">mdi-close</v-icon>
                    </v-btn>
                </div>
            </div>

            <!-- ===== Task Name ===== -->
            <div class="px-5 pt-4 pb-2">
                <div v-if="!isEditing" class="task-name-display" @click="isEditing = true">
                    {{ localTask.name }}
                </div>
                <v-text-field v-else v-model="editedName" variant="outlined" density="compact" hide-details autofocus
                    class="task-name-input" @blur="saveName" @keydown.enter="saveName"
                    @keydown.escape="isEditing = false" />
            </div>

            <!-- ===== Tabs ===== -->
            <v-tabs v-model="activeTab" color="primary" class="flex-shrink-0 px-2" height="40">
                <v-tab value="details" size="small">
                    <v-icon start size="16">mdi-text-box-outline</v-icon>
                    Details
                </v-tab>
                <v-tab value="comments" size="small">
                    <v-icon start size="16">mdi-comment-outline</v-icon>
                    Comments
                    <v-badge v-if="localTask.comments?.length" :content="localTask.comments.length" color="primary"
                        inline class="ml-1" />
                </v-tab>
                <v-tab v-if="isSubtask" value="time" size="small">
                    <v-icon start size="16">mdi-clock-outline</v-icon>
                    Time
                    <v-badge v-if="localTask.time_entries?.length" :content="localTask.time_entries.length"
                        color="primary" inline class="ml-1" />
                </v-tab>
                <v-tab value="activity" size="small">
                    <v-icon start size="16">mdi-history</v-icon>
                    Activity
                </v-tab>
            </v-tabs>

            <v-divider />

            <!-- ===== Tab Content ===== -->
            <div class="flex-1 overflow-y-auto">
                <v-tabs-window v-model="activeTab">

                    <!-- ==================== Details Tab ==================== -->
                    <v-tabs-window-item value="details">
                        <div class="pa-5">

                            <!-- Properties Section -->
                            <div class="section-card">
                                <!-- Priority -->
                                <div class="prop-row">
                                    <div class="prop-label">
                                        <v-icon size="16" class="prop-icon">mdi-flag-outline</v-icon>
                                        Priority
                                    </div>
                                    <div class="prop-value">
                                        <v-menu>
                                            <template v-slot:activator="{ props: menuProps }">
                                                <v-btn v-bind="menuProps" :color="currentPriority?.color || 'grey'"
                                                    variant="tonal" size="small" class="text-none">
                                                    <v-icon start size="14">mdi-flag{{ currentPriority ? '' : '-outline'
                                                    }}</v-icon>
                                                    {{ currentPriority?.name || 'None' }}
                                                </v-btn>
                                            </template>
                                            <v-card color="surface" min-width="160">
                                                <v-list density="compact">
                                                    <v-list-item v-for="priority in PRIORITIES" :key="priority.level"
                                                        :active="priority.level === localTask.priority_level"
                                                        @click="changePriority(priority.level)">
                                                        <template v-slot:prepend>
                                                            <v-icon :color="priority.color" size="16" class="mr-2">
                                                                mdi-flag
                                                            </v-icon>
                                                        </template>
                                                        <v-list-item-title>{{ priority.name }}</v-list-item-title>
                                                    </v-list-item>
                                                    <v-divider v-if="localTask.priority_level" />
                                                    <v-list-item v-if="localTask.priority_level"
                                                        prepend-icon="mdi-close" title="Clear"
                                                        @click="changePriority(null)" />
                                                </v-list>
                                            </v-card>
                                        </v-menu>
                                    </div>
                                </div>

                                <v-divider class="my-1" />

                                <!-- Assignees -->
                                <div class="prop-row">
                                    <div class="prop-label">
                                        <v-icon size="16" class="prop-icon">mdi-account-outline</v-icon>
                                        Assignees
                                    </div>
                                    <div class="prop-value">
                                        <div class="d-flex align-center ga-2 flex-wrap">
                                            <v-tooltip v-for="assignee in localTask.assignees" :key="assignee.id"
                                                location="top">
                                                <template v-slot:activator="{ props: tooltipProps }">
                                                    <v-avatar v-bind="tooltipProps"
                                                        :color="assignee.avatar_color || 'primary'" size="28"
                                                        class="cursor-pointer elevation-1"
                                                        @click="toggleAssignee(assignee.id)">
                                                        <span class="text-xs font-weight-medium">{{ assignee.initials
                                                        }}</span>
                                                    </v-avatar>
                                                </template>
                                                <span>{{ assignee.name }} (click to remove)</span>
                                            </v-tooltip>

                                            <v-menu>
                                                <template v-slot:activator="{ props: menuProps }">
                                                    <v-btn v-bind="menuProps" icon variant="tonal" size="x-small"
                                                        color="grey">
                                                        <v-icon size="14">mdi-plus</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-card color="surface" min-width="240">
                                                    <v-list density="compact">
                                                        <v-list-item v-for="member in members" :key="member.id"
                                                            :active="localTask.assignees?.some(a => a.id === member.id)"
                                                            @click="toggleAssignee(member.id)">
                                                            <template v-slot:prepend>
                                                                <v-avatar :color="member.avatar_color || 'primary'"
                                                                    size="24" class="mr-2">
                                                                    <span class="text-[10px]">{{ member.initials
                                                                    }}</span>
                                                                </v-avatar>
                                                            </template>
                                                            <v-list-item-title class="text-body-2">{{ member.name
                                                            }}</v-list-item-title>
                                                            <template v-slot:append>
                                                                <v-icon
                                                                    v-if="localTask.assignees?.some(a => a.id === member.id)"
                                                                    color="primary" size="16">mdi-check</v-icon>
                                                            </template>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>
                                </div>

                                <v-divider class="my-1" />

                                <!-- Labels -->
                                <div class="prop-row">
                                    <div class="prop-label">
                                        <v-icon size="16" class="prop-icon">mdi-label-outline</v-icon>
                                        Labels
                                    </div>
                                    <div class="prop-value">
                                        <div class="d-flex align-center ga-1 flex-wrap">
                                            <v-chip v-for="label in localTask.labels" :key="label.id"
                                                :color="label.color" size="small" variant="flat" closable
                                                @click:close="removeLabel(label)">
                                                {{ label.name }}
                                            </v-chip>

                                            <v-menu :close-on-content-click="false">
                                                <template v-slot:activator="{ props: menuProps }">
                                                    <v-btn v-bind="menuProps" icon variant="tonal" size="x-small"
                                                        color="grey">
                                                        <v-icon size="14">mdi-plus</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-card color="surface" min-width="200">
                                                    <v-list v-if="availableLabels.length" density="compact">
                                                        <v-list-item v-for="label in availableLabels" :key="label.id"
                                                            @click="addLabel(label)">
                                                            <template v-slot:prepend>
                                                                <div class="w-3 h-3 rounded-full mr-2"
                                                                    :style="{ backgroundColor: label.color }" />
                                                            </template>
                                                            <v-list-item-title class="text-body-2">{{ label.name
                                                            }}</v-list-item-title>
                                                        </v-list-item>
                                                    </v-list>
                                                    <div v-else class="pa-3 text-body-2 text-grey">
                                                        No labels available
                                                    </div>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subtask-specific properties -->
                                <template v-if="isSubtask">
                                    <v-divider class="my-1" />

                                    <!-- Start Date -->
                                    <div class="prop-row">
                                        <div class="prop-label">
                                            <v-icon size="16" class="prop-icon">mdi-calendar-start</v-icon>
                                            Start Date
                                        </div>
                                        <div class="prop-value">
                                            <v-menu v-model="showStartDatePicker" :close-on-content-click="false">
                                                <template v-slot:activator="{ props: menuProps }">
                                                    <v-btn v-bind="menuProps" variant="text" size="small"
                                                        class="text-none"
                                                        :color="localTask.start_date ? 'primary' : 'grey'">
                                                        {{ formatDate(localTask.start_date) }}
                                                    </v-btn>
                                                </template>
                                                <v-card color="surface" min-width="280">
                                                    <v-card-text class="pb-0">
                                                        <v-text-field v-model="tempStartDate" type="date"
                                                            label="Start Date" variant="outlined" density="compact"
                                                            hide-details />
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-btn v-if="localTask.start_date" size="small" variant="text"
                                                            color="error"
                                                            @click="tempStartDate = null; updateStartDate();">
                                                            Clear
                                                        </v-btn>
                                                        <v-spacer />
                                                        <v-btn size="small" variant="text"
                                                            @click="showStartDatePicker = false">Cancel</v-btn>
                                                        <v-btn size="small" color="primary" variant="flat"
                                                            @click="updateStartDate">Save</v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>

                                    <v-divider class="my-1" />

                                    <!-- Due Date -->
                                    <div class="prop-row">
                                        <div class="prop-label">
                                            <v-icon size="16" class="prop-icon">mdi-calendar-end</v-icon>
                                            Due Date
                                        </div>
                                        <div class="prop-value">
                                            <v-menu v-model="showDueDatePicker" :close-on-content-click="false">
                                                <template v-slot:activator="{ props: menuProps }">
                                                    <v-btn v-bind="menuProps" variant="text" size="small"
                                                        class="text-none"
                                                        :color="localTask.due_date ? 'primary' : 'grey'">
                                                        {{ formatDate(localTask.due_date) }}
                                                    </v-btn>
                                                </template>
                                                <v-card color="surface" min-width="280">
                                                    <v-card-text class="pb-0">
                                                        <v-text-field v-model="tempDueDate" type="date" label="Due Date"
                                                            variant="outlined" density="compact" hide-details />
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-btn v-if="localTask.due_date" size="small" variant="text"
                                                            color="error" @click="tempDueDate = null; updateDueDate();">
                                                            Clear
                                                        </v-btn>
                                                        <v-spacer />
                                                        <v-btn size="small" variant="text"
                                                            @click="showDueDatePicker = false">Cancel</v-btn>
                                                        <v-btn size="small" color="primary" variant="flat"
                                                            @click="updateDueDate">Save</v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>

                                    <v-divider class="my-1" />

                                    <!-- Time Estimate -->
                                    <div class="prop-row">
                                        <div class="prop-label">
                                            <v-icon size="16" class="prop-icon">mdi-timer-sand</v-icon>
                                            Estimate
                                        </div>
                                        <div class="prop-value">
                                            <v-menu v-model="showTimeEstimatePicker" :close-on-content-click="false">
                                                <template v-slot:activator="{ props: menuProps }">
                                                    <v-btn v-bind="menuProps" variant="text" size="small"
                                                        class="text-none"
                                                        :color="localTask.time_estimate ? 'primary' : 'grey'">
                                                        {{ formatTimeEstimate(localTask.time_estimate) }}
                                                    </v-btn>
                                                </template>
                                                <v-card color="surface" min-width="280">
                                                    <v-card-text class="pb-0">
                                                        <v-text-field v-model="tempTimeEstimate" type="number"
                                                            label="Estimate (hours)" variant="outlined"
                                                            density="compact" hide-details step="0.5" min="0" />
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-btn v-if="localTask.time_estimate" size="small"
                                                            variant="text" color="error"
                                                            @click="tempTimeEstimate = 0; updateTimeEstimate();">
                                                            Clear
                                                        </v-btn>
                                                        <v-spacer />
                                                        <v-btn size="small" variant="text"
                                                            @click="showTimeEstimatePicker = false">Cancel</v-btn>
                                                        <v-btn size="small" color="primary" variant="flat"
                                                            @click="updateTimeEstimate">Save</v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>

                                    <v-divider class="my-1" />

                                    <!-- Time Tracker -->
                                    <div class="prop-row">
                                        <div class="prop-label">
                                            <v-icon size="16" class="prop-icon">mdi-timer</v-icon>
                                            Tracker
                                        </div>
                                        <div class="prop-value">
                                            <div class="d-flex align-center ga-2">
                                                <code class="timer-display"
                                                    :class="{ 'timer-display--active': isTracking }">
                            {{ formatTrackingDuration }}
                        </code>
                                                <v-btn v-if="!isTracking" icon="mdi-play" color="success"
                                                    variant="tonal" size="x-small" @click="startTracking" />
                                                <v-btn v-else icon="mdi-stop" color="error" variant="tonal"
                                                    size="x-small" @click="stopTracking" />
                                            </div>
                                        </div>
                                    </div>

                                    <v-divider class="my-1" />

                                    <!-- Time Spent -->
                                    <div class="prop-row">
                                        <div class="prop-label">
                                            <v-icon size="16" class="prop-icon">mdi-chart-timeline-variant</v-icon>
                                            Spent
                                        </div>
                                        <div class="prop-value">
                                            <span class="text-body-2">
                                                {{ formatDuration((localTask.time_spent || 0) * 60) }}
                                            </span>
                                            <v-progress-linear v-if="localTask.time_estimate && localTask.time_spent"
                                                :model-value="(localTask.time_spent / localTask.time_estimate) * 100"
                                                :color="localTask.time_spent > localTask.time_estimate ? 'error' : 'primary'"
                                                height="4" rounded class="mt-1" style="max-width: 200px;" />
                                        </div>
                                    </div>

                                    <v-divider class="my-1" />

                                    <!-- Dependencies -->
                                    <div class="prop-row"
                                        style="align-items: flex-start; padding-top: 10px; padding-bottom: 10px;">
                                        <div class="prop-label" style="padding-top: 2px;">
                                            <v-icon size="16" class="prop-icon">mdi-link-variant</v-icon>
                                            Dependencies
                                        </div>
                                        <div class="prop-value">
                                            <div class="d-flex flex-wrap ga-1 align-center">
                                                <!-- Waiting on (predecessors) -->
                                                <v-chip v-for="dep in (localTask?.dependencies || [])" :key="dep.id"
                                                    size="small" color="warning" variant="tonal" closable
                                                    :disabled="dependencyLoading" @click:close="removeDependency(dep)">
                                                    <v-icon start size="12">mdi-clock-outline</v-icon>
                                                    {{ dep.name }}
                                                </v-chip>
                                                <!-- Blocking (successors) -->
                                                <v-chip v-for="dep in (localTask?.dependents || [])"
                                                    :key="'s-' + dep.id" size="small" color="error" variant="tonal"
                                                    closable :disabled="dependencyLoading"
                                                    @click:close="removeSuccessor(dep)">
                                                    <v-icon start size="12">mdi-hand-back-left</v-icon>
                                                    {{ dep.name }}
                                                </v-chip>
                                                <!-- Add dependency button -->
                                                <v-menu :close-on-content-click="false">
                                                    <template v-slot:activator="{ props: menuProps }">
                                                        <v-btn v-bind="menuProps" icon variant="tonal" size="x-small"
                                                            color="grey" :loading="dependencyLoading">
                                                            <v-icon size="14">mdi-plus</v-icon>
                                                        </v-btn>
                                                    </template>
                                                    <v-card color="surface" min-width="260">
                                                        <v-tabs v-model="dependencyTab" density="compact" grow>
                                                            <v-tab value="waiting" class="text-caption">
                                                                <v-icon start size="14">mdi-clock-outline</v-icon>
                                                                Waiting on
                                                            </v-tab>
                                                            <v-tab value="blocking" class="text-caption">
                                                                <v-icon start size="14">mdi-hand-back-left</v-icon>
                                                                Blocking
                                                            </v-tab>
                                                        </v-tabs>
                                                        <v-divider />
                                                        <v-window v-model="dependencyTab">
                                                            <v-window-item value="waiting">
                                                                <v-list density="compact" max-height="200"
                                                                    class="overflow-auto">
                                                                    <v-list-item v-for="s in getAvailablePredecessors"
                                                                        :key="s.id" :disabled="dependencyLoading"
                                                                        @click="addDependency(s)">
                                                                        <template #prepend>
                                                                            <v-icon
                                                                                size="14">mdi-subtitles-outline</v-icon>
                                                                        </template>
                                                                        <v-list-item-title class="text-body-2">{{ s.name
                                                                        }}</v-list-item-title>
                                                                        <v-list-item-subtitle v-if="s.time_estimate"
                                                                            class="text-caption">
                                                                            Est: {{
                                                                                formatSubtaskEstimate(s.time_estimate) }}
                                                                        </v-list-item-subtitle>
                                                                    </v-list-item>
                                                                    <v-list-item
                                                                        v-if="getAvailablePredecessors.length === 0"
                                                                        disabled>
                                                                        <v-list-item-title
                                                                            class="text-body-2 text-grey">
                                                                            No available subtasks
                                                                        </v-list-item-title>
                                                                    </v-list-item>
                                                                </v-list>
                                                            </v-window-item>
                                                            <v-window-item value="blocking">
                                                                <v-list density="compact" max-height="200"
                                                                    class="overflow-auto">
                                                                    <v-list-item v-for="s in getAvailableSuccessors"
                                                                        :key="s.id" :disabled="dependencyLoading"
                                                                        @click="addSuccessor(s)">
                                                                        <template #prepend>
                                                                            <v-icon
                                                                                size="14">mdi-subtitles-outline</v-icon>
                                                                        </template>
                                                                        <v-list-item-title class="text-body-2">{{ s.name
                                                                        }}</v-list-item-title>
                                                                        <v-list-item-subtitle v-if="s.time_estimate"
                                                                            class="text-caption">
                                                                            Est: {{
                                                                                formatSubtaskEstimate(s.time_estimate) }}
                                                                        </v-list-item-subtitle>
                                                                    </v-list-item>
                                                                    <v-list-item
                                                                        v-if="getAvailableSuccessors.length === 0"
                                                                        disabled>
                                                                        <v-list-item-title
                                                                            class="text-body-2 text-grey">
                                                                            No available subtasks
                                                                        </v-list-item-title>
                                                                    </v-list-item>
                                                                </v-list>
                                                            </v-window-item>
                                                        </v-window>
                                                    </v-card>
                                                </v-menu>
                                            </div>
                                            <div v-if="!(localTask?.dependencies || []).length && !(localTask?.dependents || []).length"
                                                class="text-caption text-grey mt-1">No dependencies</div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Subtasks Section (Tasks only) -->
                            <div v-if="!isSubtask" class="section-card mt-4">
                                <div class="d-flex align-center justify-space-between pa-3">
                                    <div class="d-flex align-center ga-2">
                                        <v-icon size="18" color="grey">mdi-file-tree-outline</v-icon>
                                        <span class="text-body-2 font-weight-medium">Subtasks</span>
                                        <v-chip v-if="localTask.subtasks?.length" size="x-small" variant="tonal"
                                            color="primary">
                                            {{localTask.subtasks.filter(s => s.completed_at).length}}/{{
                                                localTask.subtasks.length }}
                                        </v-chip>
                                    </div>
                                    <v-btn variant="tonal" size="x-small" color="primary"
                                        @click="emit('view-subtasks', localTask)">
                                        <v-icon start size="14">mdi-view-dashboard-outline</v-icon>
                                        Board
                                    </v-btn>
                                </div>

                                <v-divider />

                                <div v-if="localTask.subtasks?.length" class="subtask-checklist">
                                    <div v-for="sub in localTask.subtasks" :key="sub.id" class="subtask-check-item"
                                        @click="toggleSubtaskPanel(sub)">
                                        <v-icon :color="sub.completed_at ? 'success' : '#555'" size="18">
                                            {{ sub.completed_at ? 'mdi-checkbox-marked-circle' :
                                                'mdi-checkbox-blank-circle-outline' }}
                                        </v-icon>
                                        <span class="subtask-check-name"
                                            :class="{ 'subtask-check-done': sub.completed_at }">
                                            {{ sub.name }}
                                        </span>
                                        <v-icon v-if="sub.priority"
                                            :color="sub.priority.level <= 2 ? 'warning' : 'grey'" size="12"
                                            class="ml-auto flex-shrink-0">mdi-flag</v-icon>
                                    </div>
                                </div>
                                <div v-else class="pa-4 text-center text-body-2 text-grey">
                                    No subtasks yet
                                </div>
                            </div>

                            <!-- Description Section -->
                            <div class="section-card mt-4">
                                <div class="d-flex align-center ga-2 pa-3 pb-2">
                                    <v-icon size="18" color="grey">mdi-text</v-icon>
                                    <span class="text-body-2 font-weight-medium">Description</span>
                                </div>
                                <div class="px-3 pb-3">
                                    <v-textarea v-model="editedDescription" placeholder="Add a description..."
                                        variant="outlined" rows="3" hide-details auto-grow @blur="saveDescription" />
                                </div>
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- ==================== Comments Tab ==================== -->
                    <v-tabs-window-item value="comments">
                        <div class="pa-5">
                            <!-- Comment Input -->
                            <div class="section-card mb-4">
                                <div class="pa-3">
                                    <v-textarea v-model="newComment" placeholder="Write a comment..." variant="outlined"
                                        rows="3" hide-details auto-grow :disabled="isSubmittingComment" />
                                    <div class="d-flex justify-end mt-2">
                                        <v-btn color="primary" size="small" variant="flat"
                                            :disabled="!newComment.trim() || isSubmittingComment"
                                            :loading="isSubmittingComment" @click="submitComment">
                                            <v-icon start size="14">mdi-send</v-icon>
                                            Comment
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <!-- Comments List -->
                            <div v-if="!localTask.comments?.length"
                                class="d-flex flex-column align-center py-10 text-grey">
                                <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-comment-outline</v-icon>
                                <div class="text-body-2">No comments yet</div>
                                <div class="text-caption">Be the first to leave a comment</div>
                            </div>
                            <div v-else class="comment-list">
                                <div v-for="comment in localTask.comments" :key="comment.id" class="comment-item">
                                    <v-avatar :color="comment.user?.avatar_color" size="32">
                                        <span class="text-xs font-weight-medium">{{ comment.user?.initials }}</span>
                                    </v-avatar>
                                    <div class="flex-1 min-w-0">
                                        <div class="d-flex align-center ga-2 mb-1">
                                            <span class="text-body-2 font-weight-medium">{{ comment.user?.name }}</span>
                                            <span class="text-caption text-grey">
                                                {{ new Date(comment.created_at).toLocaleDateString() }}
                                            </span>
                                        </div>
                                        <div class="text-body-2 comment-content">{{ comment.content }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- ==================== Time Tracking Tab ==================== -->
                    <v-tabs-window-item v-if="isSubtask" value="time">
                        <div class="pa-5">
                            <!-- Summary -->
                            <div class="time-summary mb-4">
                                <div class="time-summary-item">
                                    <div class="text-caption text-grey mb-1">Estimated</div>
                                    <div class="text-h6 font-weight-bold">
                                        {{ formatTimeEstimate(localTask.time_estimate) }}
                                    </div>
                                </div>
                                <v-divider vertical class="mx-4" />
                                <div class="time-summary-item">
                                    <div class="text-caption text-grey mb-1">Spent</div>
                                    <div class="text-h6 font-weight-bold"
                                        :class="localTask.time_spent > localTask.time_estimate ? 'text-error' : ''">
                                        {{ formatDuration((localTask.time_spent || 0) * 60) }}
                                    </div>
                                </div>
                                <v-divider vertical class="mx-4" />
                                <div class="time-summary-item flex-1">
                                    <div class="text-caption text-grey mb-1">Progress</div>
                                    <v-progress-linear v-if="localTask.time_estimate"
                                        :model-value="((localTask.time_spent || 0) / localTask.time_estimate) * 100"
                                        :color="(localTask.time_spent || 0) > localTask.time_estimate ? 'error' : 'primary'"
                                        height="8" rounded class="mt-1" />
                                    <span v-else class="text-body-2 text-grey">No estimate</span>
                                </div>
                            </div>

                            <!-- Log Time -->
                            <div class="section-card mb-4">
                                <div class="d-flex align-center ga-2 pa-3 pb-2">
                                    <v-icon size="16" color="grey">mdi-plus-circle-outline</v-icon>
                                    <span class="text-body-2 font-weight-medium">Log Time</span>
                                </div>
                                <div class="px-3 pb-3">
                                    <div class="d-flex ga-2 align-end">
                                        <v-text-field v-model="newTimeEntry.duration" type="number" label="Hours"
                                            variant="outlined" density="compact" step="0.25" min="0.01" max="24"
                                            hide-details style="max-width: 120px;" />
                                        <v-text-field v-model="newTimeEntry.description" label="Description (optional)"
                                            variant="outlined" density="compact" hide-details class="flex-1" />
                                        <v-btn color="primary" variant="flat" size="small"
                                            :disabled="!newTimeEntry.duration" @click="addTimeEntry">
                                            <v-icon size="16">mdi-plus</v-icon>
                                            Log
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Entries -->
                            <div v-if="!localTask.time_entries?.length"
                                class="d-flex flex-column align-center py-10 text-grey">
                                <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-clock-outline</v-icon>
                                <div class="text-body-2">No time entries yet</div>
                            </div>
                            <div v-else class="time-entry-list">
                                <div v-for="entry in localTask.time_entries" :key="entry.id" class="time-entry-item">
                                    <v-avatar :color="entry.user?.avatar_color" size="28">
                                        <span class="text-[10px] font-weight-medium">{{ entry.user?.initials }}</span>
                                    </v-avatar>
                                    <div class="flex-1 min-w-0">
                                        <div class="d-flex align-center ga-2">
                                            <span class="text-body-2 font-weight-medium">{{ entry.user?.name }}</span>
                                            <v-chip size="x-small" color="primary" variant="tonal">
                                                {{ formatDuration(entry.duration * 60) }}
                                            </v-chip>
                                        </div>
                                        <div v-if="entry.description" class="text-caption text-grey mt-1 text-truncate">
                                            {{ entry.description }}
                                        </div>
                                        <div class="d-flex align-center ga-3 mt-1 text-caption text-grey-darken-1">
                                            <span v-if="entry.started_at" class="d-flex align-center ga-1">
                                                <v-icon size="11" color="success">mdi-play</v-icon>
                                                {{ new Date(entry.started_at).toLocaleString() }}
                                            </span>
                                            <span v-if="entry.ended_at" class="d-flex align-center ga-1">
                                                <v-icon size="11" color="error">mdi-stop</v-icon>
                                                {{ new Date(entry.ended_at).toLocaleString() }}
                                            </span>
                                            <span v-if="!entry.started_at && !entry.ended_at">
                                                {{ new Date(entry.created_at).toLocaleString() }}
                                            </span>
                                            <v-chip v-if="entry.is_running" size="x-small" color="success"
                                                variant="tonal">
                                                <v-icon start size="10">mdi-circle</v-icon>
                                                Running
                                            </v-chip>
                                        </div>
                                    </div>
                                    <v-btn icon size="x-small" variant="text" color="grey"
                                        @click="deleteTimeEntry(entry.id)">
                                        <v-icon size="14">mdi-delete-outline</v-icon>
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- ==================== Activity Tab ==================== -->
                    <v-tabs-window-item value="activity">
                        <div class="pa-5">
                            <div v-if="!localTask.activities?.length"
                                class="d-flex flex-column align-center py-10 text-grey">
                                <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-history</v-icon>
                                <div class="text-body-2">No activity yet</div>
                            </div>
                            <div v-else class="activity-list">
                                <div v-for="activity in localTask.activities" :key="activity.id" class="activity-item">
                                    <v-avatar :color="activity.user?.avatar_color" size="24">
                                        <span class="text-[10px]">{{ activity.user?.initials }}</span>
                                    </v-avatar>
                                    <div class="flex-1 text-body-2">
                                        <span class="font-weight-medium">{{ activity.user?.name }}</span>
                                        <span class="text-grey"> {{ activity.description }}</span>
                                        <div class="text-caption text-grey-darken-1 mt-1">
                                            {{ new Date(activity.created_at).toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-tabs-window-item>
                </v-tabs-window>
            </div>
        </div>
    </v-navigation-drawer>
</template>

<style scoped>
.task-detail-panel {
    background-color: #1e1e1e !important;
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

/* ===== Task Name ===== */
.task-name-display {
    font-size: 1.15rem;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    padding: 6px 10px;
    margin: 0 -10px;
    border-radius: 6px;
    transition: background-color 0.12s;
}

.task-name-display:hover {
    background-color: rgba(255, 255, 255, 0.04);
}

.task-name-input :deep(.v-field) {
    font-size: 1.15rem;
    font-weight: 600;
}

/* ===== Section Card ===== */
.section-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    overflow: hidden;
}

/* ===== Property Rows ===== */
.prop-row {
    display: flex;
    align-items: center;
    padding: 8px 14px;
    min-height: 40px;
}

.prop-label {
    display: flex;
    align-items: center;
    width: 130px;
    flex-shrink: 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.45);
    gap: 8px;
    font-weight: 500;
}

.prop-icon {
    opacity: 0.6;
}

.prop-value {
    flex: 1;
    min-width: 0;
}

/* ===== Timer ===== */
.timer-display {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 13px;
    padding: 3px 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.7);
}

.timer-display--active {
    background: rgba(76, 175, 80, 0.12);
    color: #66bb6a;
}

/* ===== Time Summary ===== */
.time-summary {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
}

.time-summary-item {
    text-align: center;
}

/* ===== Subtask Checklist ===== */
.subtask-checklist {
    display: flex;
    flex-direction: column;
}

.subtask-check-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    cursor: pointer;
    transition: background-color 0.12s;
}

.subtask-check-item:hover {
    background-color: rgba(255, 255, 255, 0.04);
}

.subtask-check-name {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.subtask-check-done {
    text-decoration: line-through;
    color: rgba(255, 255, 255, 0.3);
}

/* ===== Comment List ===== */
.comment-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.comment-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.comment-content {
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.5;
}

/* ===== Time Entries ===== */
.time-entry-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.time-entry-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 8px;
}

/* ===== Activity List ===== */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
</style>
