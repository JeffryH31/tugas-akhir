<script setup>
/**
 * Task Detail Panel - Slide-over panel for task details
 */
import { ref, computed, watch, reactive, nextTick, onMounted, onUnmounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

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
    priorities: {
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
    priority_id: null,
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
        form.priority_id = newTask.priority_id;
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
const priorityConfig = {
    1: { color: 'error', icon: 'mdi-flag', label: 'Urgent' },
    2: { color: 'warning', icon: 'mdi-flag', label: 'High' },
    3: { color: 'info', icon: 'mdi-flag', label: 'Normal' },
    4: { color: 'grey', icon: 'mdi-flag-outline', label: 'Low' },
};

// Get current priority
const currentPriority = computed(() => {
    if (!localTask.value?.priority) return null;
    return priorityConfig[localTask.value.priority.level] || null;
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
const changePriority = (priorityId) => {
    router.patch(
        getUpdateRoute(),
        { priority_id: priorityId },
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

const deleteSubtaskPanel = (subtask) => {
    if (confirm(`Delete subtask "${subtask.name}"?`)) {
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

// Time tracking (server-side timer)
const isTracking = ref(false);
const trackingDuration = ref(0);
const trackingInterval = ref(null);
const runningEntryId = ref(null);
const isTimerLoading = ref(false);

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

const getAvailableDependencies = computed(() => {
    if (!isSubtask.value || !props.parentTask?.subtasks) return [];
    const existingDepIds = (props.task.dependencies || []).map(d => d.id);
    return props.parentTask.subtasks.filter(
        s => s.id !== props.task.id && !existingDepIds.includes(s.id)
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

const formatSubtaskEstimate = (minutes) => {
    if (!minutes) return '';
    const hours = minutes / 60;
    return hours >= 1 ? `${hours}h` : `${minutes}m`;
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
const deleteTask = () => {
    if (confirm('Are you sure you want to delete this task?')) {
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

const deleteTimeEntry = (entryId) => {
    if (!confirm('Delete this time entry?')) return;

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
        location="right" temporary width="600" class="task-detail-panel">
        <div v-if="localTask" class="flex flex-col h-full">
            <!-- Header -->
            <div class="panel-header">
                <div class="flex items-center gap-2">
                    <!-- Complete Button -->
                    <v-btn :icon="isCompleted ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                        :color="isCompleted ? 'success' : 'grey'" variant="text" size="small" @click="toggleComplete" />

                    <!-- Status -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-chip v-bind="menuProps" :color="localTask.status?.color" size="small" variant="tonal"
                                label>
                                {{ localTask.status?.name || 'No Status' }}
                                <v-icon end size="14">mdi-chevron-down</v-icon>
                            </v-chip>
                        </template>
                        <v-card color="surface">
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
                </div>

                <div class="flex items-center gap-1">
                    <v-btn icon variant="text" size="small">
                        <v-icon>mdi-link-variant</v-icon>
                    </v-btn>
                    <v-btn icon variant="text" size="small">
                        <v-icon>mdi-star-outline</v-icon>
                    </v-btn>
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
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
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </div>
            </div>
            <!-- Task Name -->
            <div class="px-4 py-2">
                <div v-if="!isEditing"
                    class="text-xl font-semibold cursor-pointer hover:bg-[#2d2d30] rounded px-2 py-1 -mx-2"
                    @click="isEditing = true">
                    {{ localTask.name }}
                </div>
                <v-text-field v-else v-model="editedName" variant="outlined" density="compact" hide-details autofocus
                    @blur="saveName" @keydown.enter="saveName" @keydown.escape="isEditing = false" />
            </div>

            <!-- Tabs -->
            <v-tabs v-model="activeTab" color="primary" class="flex-shrink-0">
                <v-tab value="details">Details</v-tab>
                <v-tab value="comments">
                    Comments
                    <v-badge v-if="localTask.comments?.length" :content="localTask.comments.length" color="grey" inline
                        class="ml-1" />
                </v-tab>
                <v-tab v-if="isSubtask" value="time">
                    Time Tracking
                    <v-badge v-if="localTask.time_entries?.length" :content="localTask.time_entries.length" color="grey"
                        inline class="ml-1" />
                </v-tab>
                <v-tab value="activity">Activity</v-tab>
            </v-tabs>

            <v-divider />

            <!-- Tab Content -->
            <div class="flex-1 overflow-y-auto">
                <v-tabs-window v-model="activeTab">
                    <!-- Details Tab -->
                    <v-tabs-window-item value="details">
                        <div class="p-4 space-y-4">
                            <!-- Priority -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-flag-outline</v-icon>
                                    Priority
                                </div>
                                <div class="detail-value">
                                    <v-menu>
                                        <template v-slot:activator="{ props: menuProps }">
                                            <v-btn v-bind="menuProps" :color="currentPriority?.color || 'grey'"
                                                variant="tonal" size="small">
                                                <v-icon start size="16">{{ currentPriority?.icon || 'mdi-flag-outline'
                                                }}</v-icon>
                                                {{ localTask.priority?.name || 'No Priority' }}
                                                </ v-btn>
                                        </template>
                                        <v-card color="surface">
                                            <v-list density="compact">
                                                <v-list-item v-for="priority in priorities" :key="priority.id"
                                                    :active="priority.id === localTask.priority_id"
                                                    @click="changePriority(priority.id)">
                                                    <template v-slot:prepend>
                                                        <v-icon :color="priorityConfig[priority.level]?.color" size="18"
                                                            class="mr-2">
                                                            {{ priorityConfig[priority.level]?.icon }}
                                                        </v-icon>
                                                    </template>
                                                    <v-list-item-title>{{ priority.name }}</v-list-item-title>
                                                </v-list-item>
                                                <v-divider v-if="localTask.priority_id" />
                                                <v-list-item v-if="localTask.priority_id" prepend-icon="mdi-close"
                                                    title="Clear Priority" @click="changePriority(null)" />
                                            </v-list>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </div>

                            <!-- Assignees -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-account-outline</v-icon>
                                    Assignees
                                </div>
                                <div class="detail-value">
                                    <div class="flex items-center gap-2">
                                        <!-- Current Assignees -->
                                        <div v-if="localTask.assignees?.length" class="flex -space-x-2">
                                            <v-tooltip v-for="assignee in localTask.assignees" :key="assignee.id"
                                                location="top">
                                                <template v-slot:activator="{ props: tooltipProps }">
                                                    <v-avatar v-bind="tooltipProps"
                                                        :color="assignee.avatar_color || 'primary'" size="32"
                                                        class="cursor-pointer border-2 border-[#1e1e1e]"
                                                        @click="toggleAssignee(assignee.id)">
                                                        <span class="text-xs">{{ assignee.initials }}</span>
                                                    </v-avatar>
                                                </template>
                                                <span>{{ assignee.name }} (click to remove)</span>
                                            </v-tooltip>
                                        </div>

                                        <!-- Add Assignee Button -->
                                        <v-menu>
                                            <template v-slot:activator="{ props: menuProps }">
                                                <v-btn v-bind="menuProps" icon variant="outlined" size="small">
                                                    <v-icon>mdi-plus</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-card color="surface" max-width="300">
                                                <v-card-text class="pa-2">
                                                    <v-list density="compact">
                                                        <v-list-item v-for="member in members" :key="member.id"
                                                            :active="localTask.assignees?.some(a => a.id === member.id)"
                                                            @click="toggleAssignee(member.id)">
                                                            <template v-slot:prepend>
                                                                <v-avatar :color="member.avatar_color || 'primary'"
                                                                    size="28" class="mr-2">
                                                                    <span class="text-xs">{{ member.initials }}</span>
                                                                </v-avatar>
                                                            </template>
                                                            <v-list-item-title>{{ member.name }}</v-list-item-title>
                                                            <template v-slot:append>
                                                                <v-icon
                                                                    v-if="localTask.assignees?.some(a => a.id === member.id)"
                                                                    color="primary">
                                                                    mdi-check
                                                                </v-icon>
                                                            </template>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-card-text>
                                            </v-card>
                                        </v-menu>
                                    </div>
                                </div>
                            </div>

                            <!-- Start Date (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-calendar-start</v-icon>
                                    Start Date
                                </div>
                                <div class="detail-value">
                                    <v-menu v-model="showStartDatePicker" :close-on-content-click="false">
                                        <template v-slot:activator="{ props: menuProps }">
                                            <v-btn v-bind="menuProps" variant="text" size="small"
                                                :color="localTask.start_date ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-calendar-start</v-icon>
                                                {{ formatDate(localTask.start_date) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempStartDate" type="date" label="Start Date"
                                                    variant="outlined" density="compact" hide-details
                                                    bg-color="#1e1e1e" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="localTask.start_date" size="small" variant="text"
                                                    @click="tempStartDate = null; updateStartDate();">
                                                    Clear
                                                </v-btn>
                                                <v-spacer />
                                                <v-btn size="small" variant="text"
                                                    @click="showStartDatePicker = false">Cancel</v-btn>
                                                <v-btn size="small" color="primary"
                                                    @click="updateStartDate">Save</v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </div>

                            <!-- Due Date (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-calendar-outline</v-icon>
                                    Due Date
                                </div>
                                <div class="detail-value">
                                    <v-menu v-model="showDueDatePicker" :close-on-content-click="false">
                                        <template v-slot:activator="{ props: menuProps }">
                                            <v-btn v-bind="menuProps" variant="text" size="small"
                                                :color="localTask.due_date ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-calendar</v-icon>
                                                {{ formatDate(localTask.due_date) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempDueDate" type="date" label="Due Date"
                                                    variant="outlined" density="compact" hide-details
                                                    bg-color="#1e1e1e" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="localTask.due_date" size="small" variant="text"
                                                    @click="tempDueDate = null; updateDueDate();">
                                                    Clear
                                                </v-btn>
                                                <v-spacer />
                                                <v-btn size="small" variant="text"
                                                    @click="showDueDatePicker = false">Cancel</v-btn>
                                                <v-btn size="small" color="primary" @click="updateDueDate">Save</v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </div>

                            <!-- Time Estimate (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-clock-outline</v-icon>
                                    Time Estimate
                                </div>
                                <div class="detail-value">
                                    <v-menu v-model="showTimeEstimatePicker" :close-on-content-click="false">
                                        <template v-slot:activator="{ props: menuProps }">
                                            <v-btn v-bind="menuProps" variant="text" size="small"
                                                :color="localTask.time_estimate ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-timer-outline</v-icon>
                                                {{ formatTimeEstimate(localTask.time_estimate) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempTimeEstimate" type="number"
                                                    label="Time Estimate (hours)" variant="outlined" density="compact"
                                                    hide-details bg-color="#1e1e1e" step="0.5" min="0" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="localTask.time_estimate" size="small" variant="text"
                                                    @click="tempTimeEstimate = 0; updateTimeEstimate();">
                                                    Clear
                                                </v-btn>
                                                <v-spacer />
                                                <v-btn size="small" variant="text"
                                                    @click="showTimeEstimatePicker = false">Cancel</v-btn>
                                                <v-btn size="small" color="primary"
                                                    @click="updateTimeEstimate">Save</v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </div>

                            <!-- Time Tracker (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-timer</v-icon>
                                    Time Tracker
                                </div>
                                <div class="detail-value">
                                    <div class="flex items-center gap-2">
                                        <v-chip variant="text" size="small" class="font-mono"
                                            :color="isTracking ? 'success' : 'default'">
                                            <v-icon start size="16">mdi-clock</v-icon>
                                            {{ formatTrackingDuration }}
                                            <div v-if="isTracking"
                                                class="w-2 h-2 bg-green-500 rounded-full animate-pulse ml-2" />
                                        </v-chip>
                                        <div class="flex gap-1">
                                            <v-btn v-if="!isTracking" icon="mdi-play" color="success" variant="tonal"
                                                size="small" @click="startTracking" />
                                            <v-btn v-else icon="mdi-stop" color="error" variant="tonal" size="small"
                                                @click="stopTracking" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Spent (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-chart-timeline-variant</v-icon>
                                    Time Spent
                                </div>
                                <div class="detail-value">
                                    <v-chip variant="text" size="small">
                                        <v-icon start size="16">mdi-clock</v-icon>
                                        {{ formatDuration((localTask.time_spent || 0) * 60) }}
                                    </v-chip>
                                    <v-progress-linear v-if="localTask.time_estimate && localTask.time_spent"
                                        :model-value="(localTask.time_spent / localTask.time_estimate) * 100"
                                        :color="localTask.time_spent > localTask.time_estimate ? 'error' : 'primary'"
                                        height="4" class="mt-2" />
                                </div>
                            </div>

                            <!-- Dependencies (Subtasks only) -->
                            <div v-if="isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-link-variant</v-icon>
                                    Dependencies
                                </div>
                                <div class="detail-value">
                                    <div class="flex flex-wrap gap-1 items-center">
                                        <!-- Predecessors (depends on) -->
                                        <v-chip v-for="dep in (localTask?.dependencies || [])" :key="dep.id"
                                            size="small" color="warning" variant="tonal" closable
                                            :disabled="dependencyLoading" @click:close="removeDependency(dep)">
                                            <v-icon start size="12">mdi-arrow-left</v-icon>
                                            {{ dep.name }}
                                        </v-chip>
                                        <!-- Successors (depended by) -->
                                        <v-chip v-for="dep in (localTask?.dependents || [])" :key="'s-' + dep.id"
                                            size="small" color="info" variant="tonal">
                                            <v-icon start size="12">mdi-arrow-right</v-icon>
                                            {{ dep.name }}
                                        </v-chip>
                                        <!-- Add predecessor -->
                                        <v-menu :close-on-content-click="false">
                                            <template v-slot:activator="{ props: menuProps }">
                                                <v-btn v-bind="menuProps" icon="mdi-plus" size="x-small" variant="text"
                                                    :loading="dependencyLoading" />
                                            </template>
                                            <v-card color="surface" min-width="250">
                                                <v-card-title class="text-sm py-2">Add Predecessor</v-card-title>
                                                <v-divider />
                                                <v-list density="compact" max-height="200" class="overflow-auto">
                                                    <v-list-item v-for="s in getAvailableDependencies" :key="s.id"
                                                        :disabled="dependencyLoading" @click="addDependency(s)">
                                                        <template #prepend>
                                                            <v-icon size="16">mdi-subtitles-outline</v-icon>
                                                        </template>
                                                        <v-list-item-title class="text-sm">{{ s.name
                                                        }}</v-list-item-title>
                                                        <v-list-item-subtitle v-if="s.time_estimate" class="text-xs">
                                                            Est: {{ formatSubtaskEstimate(s.time_estimate) }}
                                                        </v-list-item-subtitle>
                                                    </v-list-item>
                                                    <v-list-item v-if="getAvailableDependencies.length === 0" disabled>
                                                        <v-list-item-title class="text-sm text-gray-500">
                                                            No available subtasks
                                                        </v-list-item-title>
                                                    </v-list-item>
                                                </v-list>
                                            </v-card>
                                        </v-menu>
                                    </div>
                                    <div v-if="!(localTask?.dependencies || []).length && !(localTask?.dependents || []).length"
                                        class="text-xs text-gray-500 mt-1">No dependencies</div>
                                </div>
                            </div>

                            <!-- Subtasks (Tasks only) -->
                            <div v-if="!isSubtask" class="mt-1">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="detail-label" style="width: auto;">
                                        <v-icon size="18" class="mr-2">mdi-file-tree-outline</v-icon>
                                        Subtasks
                                        <span v-if="localTask.subtasks?.length" class="text-xs text-gray-500 ml-1">
                                            ({{ localTask.subtasks.filter(s => s.completed_at).length }}/{{ localTask.subtasks.length }})
                                        </span>
                                    </div>
                                    <v-btn variant="tonal" size="small" @click="emit('view-subtasks', localTask)">
                                        <v-icon start size="16">mdi-view-dashboard-outline</v-icon>
                                        View Board
                                    </v-btn>
                                </div>

                                <!-- Subtask checklist -->
                                <div v-if="localTask.subtasks?.length" class="subtask-checklist">
                                    <div v-for="sub in localTask.subtasks" :key="sub.id" class="subtask-check-item"
                                        @click="toggleSubtaskPanel(sub)">
                                        <v-icon :color="sub.completed_at ? 'success' : '#555'" size="18">
                                            {{ sub.completed_at ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline' }}
                                        </v-icon>
                                        <span class="subtask-check-name" :class="{ 'subtask-check-done': sub.completed_at }">
                                            {{ sub.name }}
                                        </span>
                                        <v-icon v-if="sub.priority" :color="sub.priority.level <= 2 ? 'warning' : 'grey'" size="12" class="ml-auto flex-shrink-0">
                                            mdi-flag
                                        </v-icon>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500 pl-7">
                                    No subtasks yet
                                </div>
                            </div>

                            <v-divider />

                            <!-- Description -->
                            <div>
                                <div class="text-sm font-medium mb-2">Description</div>
                                <v-textarea v-model="editedDescription" placeholder="Add a description..."
                                    variant="outlined" rows="4" hide-details @blur="saveDescription" />
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- Comments Tab -->
                    <v-tabs-window-item value="comments">
                        <div class="p-4">
                            <!-- Comment Input -->
                            <div class="mb-4">
                                <v-textarea v-model="newComment" placeholder="Write a comment..." variant="outlined"
                                    rows="3" hide-details :disabled="isSubmittingComment" />
                                <div class="flex justify-end mt-2">
                                    <v-btn color="primary" size="small"
                                        :disabled="!newComment.trim() || isSubmittingComment"
                                        :loading="isSubmittingComment" @click="submitComment">
                                        Comment
                                    </v-btn>
                                </div>
                            </div>

                            <!-- Comments List -->
                            <div v-if="!localTask.comments?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-comment-outline</v-icon>
                                <div>No comments yet</div>
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="comment in localTask.comments" :key="comment.id" class="flex gap-3">
                                    <v-avatar :color="comment.user?.avatar_color" size="32">
                                        <span class="text-xs">{{ comment.user?.initials }}</span>
                                    </v-avatar>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-medium text-sm">{{ comment.user?.name }}</span>
                                            <span class="text-xs text-gray-500">
                                                {{ new Date(comment.created_at).toLocaleDateString() }}
                                            </span>
                                        </div>
                                        <div class="text-sm">{{ comment.content }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- Time Tracking Tab (Subtasks only) -->
                    <v-tabs-window-item v-if="isSubtask" value="time">
                        <div class="p-4">
                            <!-- Summary Card -->
                            <v-card variant="tonal" class="mb-4">
                                <v-card-text class="pb-2">
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <div class="text-gray-400 mb-1">Time Estimate</div>
                                            <div class="text-lg font-semibold">
                                                {{ formatTimeEstimate(localTask.time_estimate) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-gray-400 mb-1">Time Spent</div>
                                            <div class="text-lg font-semibold"
                                                :class="localTask.time_spent > localTask.time_estimate ? 'text-error' : ''">
                                                {{ formatDuration((localTask.time_spent || 0) * 60) }}
                                            </div>
                                        </div>
                                    </div>
                                    <v-progress-linear v-if="localTask.time_estimate"
                                        :model-value="((localTask.time_spent || 0) / localTask.time_estimate) * 100"
                                        :color="(localTask.time_spent || 0) > localTask.time_estimate ? 'error' : 'primary'"
                                        height="6" rounded class="mt-3" />
                                </v-card-text>
                            </v-card>

                            <!-- Add Time Entry Form -->
                            <v-card variant="outlined" class="mb-4">
                                <v-card-text>
                                    <div class="text-sm font-medium mb-3">Log Time</div>
                                    <div class="space-y-3">
                                        <v-text-field v-model="newTimeEntry.duration" type="number"
                                            label="Duration (hours)" variant="outlined" density="compact" step="0.25"
                                            min="0.01" max="24" hide-details />
                                        <v-textarea v-model="newTimeEntry.description" label="Description (optional)"
                                            variant="outlined" density="compact" rows="2" hide-details counter
                                            maxlength="500" />
                                        <v-btn color="primary" size="small" block :disabled="!newTimeEntry.duration"
                                            @click="addTimeEntry">
                                            <v-icon start>mdi-plus</v-icon>
                                            Add Time Entry
                                        </v-btn>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Time Entries List -->
                            <div v-if="!localTask.time_entries?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-clock-outline</v-icon>
                                <div>No time entries yet</div>
                            </div>
                            <div v-else class="space-y-2">
                                <v-card v-for="entry in localTask.time_entries" :key="entry.id" variant="outlined">
                                    <v-card-text class="py-2">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <v-avatar :color="entry.user?.avatar_color" size="20">
                                                        <span class="text-[10px]">{{ entry.user?.initials }}</span>
                                                    </v-avatar>
                                                    <span class="text-sm font-medium">{{ entry.user?.name }}</span>
                                                    <v-chip size="x-small" color="primary">
                                                        {{ formatDuration(entry.duration * 60) }}
                                                    </v-chip>
                                                </div>
                                                <div v-if="entry.description" class="text-sm text-gray-400 ml-7">
                                                    {{ entry.description }}
                                                </div>
                                                <div class="text-xs text-gray-500 ml-7 mt-1">
                                                    {{ new Date(entry.created_at).toLocaleString() }}
                                                </div>
                                            </div>
                                            <v-btn icon size="x-small" variant="text"
                                                @click="deleteTimeEntry(entry.id)">
                                                <v-icon size="16">mdi-delete-outline</v-icon>
                                            </v-btn>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </div>
                        </div>
                    </v-tabs-window-item>

                    <!-- Activity Tab -->
                    <v-tabs-window-item value="activity">
                        <div class="p-4">
                            <div v-if="!localTask.activities?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-history</v-icon>
                                <div>No activity yet</div>
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="activity in localTask.activities" :key="activity.id"
                                    class="flex items-start gap-3">
                                    <v-avatar :color="activity.user?.avatar_color" size="24">
                                        <span class="text-[10px]">{{ activity.user?.initials }}</span>
                                    </v-avatar>
                                    <div class="flex-1 text-sm">
                                        <span class="font-medium">{{ activity.user?.name }}</span>
                                        <span class="text-gray-400"> {{ activity.description }}</span>
                                        <div class="text-xs text-gray-500 mt-0.5">
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
    padding: 12px 16px;
    border-bottom: 1px solid #2d2d30;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 16px;
}

.detail-label {
    display: flex;
    align-items: center;
    width: 140px;
    color: #9ca3af;
    font-size: 14px;
}

.detail-value {
    flex: 1;
}

.space-y-4>*+* {
    margin-top: 16px;
}

.space-y-3>*+* {
    margin-top: 12px;
}

.space-y-2>*+* {
    margin-top: 8px;
}

.subtask-checklist {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-left: 7px;
}

.subtask-check-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.12s;
}

.subtask-check-item:hover {
    background-color: #2d2d30;
}

.subtask-check-name {
    font-size: 13px;
    color: #d1d5db;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.subtask-check-done {
    text-decoration: line-through;
    color: #6b7280;
}
</style>
