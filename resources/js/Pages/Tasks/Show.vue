<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import MainLayout from '@/Layouts/MainLayout.vue';
import { PRIORITIES } from '@/constants/priorities';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import {
    getStoredSubtaskCompletionTarget,
} from '@/utils/subtaskCompletionAutomation';

const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    workspace: Object,
    space: Object,
    list: Object,
    task: Object,
    statuses: Array,
    sprints: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// Local task state
const localTask = ref({
    name: props.task.name,
    description: props.task.description || '',
    status_id: props.task.status_id,
    priority_level: props.task.priority_level,
    due_date: props.task.due_date,
});

// Time estimate (man-hour only)
const timeEstimateHours = ref((props.task.time_estimate || 0) / 60);

const updateTimeEstimate = () => {
    const totalMinutes = (parseFloat(timeEstimateHours.value) || 0) * 60;

    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { time_estimate: totalMinutes },
        {
            preserveScroll: true,
            onFinish: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
};

// Comment
const newComment = ref('');
const isSubmittingComment = ref(false);

// Subtask
const showAddSubtask = ref(false);
const newSubtaskName = ref('');
const subtasksDragging = ref(false);
const isProcessing = ref(false);

// Local subtasks list for drag-drop reactivity
const localSubtasks = computed({
    get: () => props.task.subtasks || [],
    set: (val) => {
        props.task.subtasks = val;
    }
});

const onSubtaskDragEnd = () => {
    subtasksDragging.value = false;
    const subtaskIds = localSubtasks.value.map(s => s.id);
    router.post(
        route('tasks.subtasks.reorder', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { subtask_ids: subtaskIds },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Failed to reorder subtasks', 'error');
                }
                router.reload({ only: ['task'] });
            }
        }
    );
};

// Running timer
const runningTimer = ref(null);
const timerInterval = ref(null);
const elapsedSeconds = ref(0);
const isTimerLoading = ref(false);

// Computed
const labels = computed(() => props.workspace?.labels || []);
const members = computed(() => props.workspace?.members || []);

const showLabelEditor = ref(false);
const editingLabel = ref(null);
const labelForm = ref({ name: '', color: '#61BD4F' });

const isLabelSelected = (labelId) => {
    return (props.task.labels || []).some((label) => label.id === labelId);
};

const normalizeLabelColor = (color) => {
    const raw = (color || '').trim();
    if (!raw) return '#61BD4F';
    return raw.startsWith('#') ? raw.toUpperCase() : `#${raw.toUpperCase()}`;
};

const formatRunningTime = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Methods
const goBack = () => {
    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]));
};

const updateTask = () => {
    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            name: localTask.value.name,
            description: localTask.value.description,
            due_date: localTask.value.due_date || '',
        },
        { preserveScroll: true }
    );
};

const updateStatus = (statusId) => {
    router.patch(
        route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { status_id: statusId },
        { preserveScroll: true }
    );
};

const updatePriority = (priorityLevel) => {
    router.patch(
        route('tasks.change-priority', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { priority_level: priorityLevel },
        { preserveScroll: true }
    );
};

const toggleComplete = () => {
    // Tasks don't support completion (only subtasks do)
};

const duplicateTask = () => {
    router.post(
        route('tasks.duplicate', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {},
        { preserveScroll: true }
    );
};

const deleteTask = async () => {
    if (await confirmDialog('Are you sure you want to delete this task?', 'Delete Task')) {
        router.delete(
            route('tasks.destroy', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {
                onSuccess: () => {
                    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]));
                }
            }
        );
    }
};

const openCreateLabelEditor = () => {
    editingLabel.value = null;
    labelForm.value = { name: '', color: '#61BD4F' };
    showLabelEditor.value = true;
};

const openEditLabelEditor = (label) => {
    editingLabel.value = label;
    labelForm.value = { name: label.name, color: label.color };
    showLabelEditor.value = true;
};

const toggleLabel = (label) => {
    if (isLabelSelected(label.id)) {
        removeLabel(label);
        return;
    }

    addLabel(label);
};

const addLabel = (label) => {
    if (isLabelSelected(label.id)) return;

    const backup = [...(props.task.labels || [])];
    if (!props.task.labels) props.task.labels = [];
    props.task.labels.push(label);

    router.post(
        route('tasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { label_id: label.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task'] });
            },
            onError: () => {
                props.task.labels = backup;
                window.showSnackbar?.('Failed to add label', 'error');
            }
        }
    );
};

const removeLabel = (label) => {
    const backup = [...(props.task.labels || [])];
    props.task.labels = (props.task.labels || []).filter((item) => item.id !== label.id);

    router.delete(
        route('tasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            data: { label_id: label.id },
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task'] });
            },
            onError: () => {
                props.task.labels = backup;
                window.showSnackbar?.('Failed to remove label', 'error');
            }
        }
    );
};

const saveWorkspaceLabel = () => {
    const name = labelForm.value.name?.trim();
    const color = normalizeLabelColor(labelForm.value.color);

    if (!name) {
        window.showSnackbar?.('Label name is required.', 'error');
        return;
    }

    if (!/^#[0-9A-F]{6}$/.test(color)) {
        window.showSnackbar?.('Color must be valid hex format (#RRGGBB).', 'error');
        return;
    }

    const payload = { name, color };

    if (editingLabel.value) {
        router.patch(route('workspaces.labels.update', [props.workspace.id, editingLabel.value.id]), payload, {
            preserveScroll: true,
            onSuccess: () => {
                showLabelEditor.value = false;
                router.reload({ only: ['workspace', 'task'] });
            },
        });
        return;
    }

    router.post(route('workspaces.labels.store', props.workspace.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showLabelEditor.value = false;
            router.reload({ only: ['workspace', 'task'] });
        },
    });
};

const deleteWorkspaceLabel = async () => {
    if (!editingLabel.value) return;

    const confirmed = await confirmDialog(
        `Delete label "${editingLabel.value.name}"? This removes it from all tasks and subtasks.`,
        'Delete Label'
    );

    if (!confirmed) return;

    const deletingLabelId = editingLabel.value.id;

    router.delete(route('workspaces.labels.destroy', [props.workspace.id, deletingLabelId]), {
        preserveScroll: true,
        onSuccess: () => {
            props.task.labels = (props.task.labels || []).filter((label) => label.id !== deletingLabelId);
            showLabelEditor.value = false;
            editingLabel.value = null;
            router.reload({ only: ['workspace', 'task'] });
        },
    });
};

const addComment = () => {
    if (!newComment.value.trim() || isSubmittingComment.value) return;

    if (!props.workspace?.id || !props.space?.id || !props.list?.id || !props.task?.id) {
        if (window.showSnackbar) {
            window.showSnackbar('Missing required data', 'error');
        }
        return;
    }

    isSubmittingComment.value = true;

    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { content: newComment.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                newComment.value = '';
            },
            onError: (errors) => {
                if (window.showSnackbar) {
                    window.showSnackbar(Object.values(errors).flat().join(', ') || 'Failed to add comment', 'error');
                }
            },
            onFinish: () => {
                isSubmittingComment.value = false;
            }
        }
    );
};

const toggleReaction = (comment, emoji) => {
    router.post(
        route('comments.react', comment.id),
        { emoji },
        {
            preserveScroll: true,
            onFinish: () => router.reload({ only: ['task'] })
        }
    );
};

const getReactionCount = (comment, emoji) => {
    return comment.reactions?.filter(r => r.emoji === emoji).length || 0;
};

const addSubtask = () => {
    if (!newSubtaskName.value.trim() || isProcessing.value) return;
    isProcessing.value = true;

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
                showAddSubtask.value = false;
            },
            onFinish: () => {
                isProcessing.value = false;
                router.reload({ only: ['task'] });
            }
        }
    );
};

const toggleSubtask = (subtask) => {
    const wasCompleted = !!subtask.completed_at;
    const routeName = wasCompleted ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete';
    const targetStatusId = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    const payload = !wasCompleted && targetStatusId ? { target_status_id: targetStatusId } : {};

    router.post(
        route(routeName, [props.workspace.id, props.space.id, props.list.id, props.task.id, subtask.id]),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] }),
            onError: (errors) => {
                if (errors.dependency) {
                    window.showSnackbar(errors.dependency, 'error');
                }
            }
        }
    );
};

const editingSubtaskId = ref(null);
const editingSubtaskName = ref('');

const startEditSubtask = (subtask) => {
    editingSubtaskId.value = subtask.id;
    editingSubtaskName.value = subtask.name;
};

const cancelSubtaskEdit = () => {
    editingSubtaskId.value = null;
    editingSubtaskName.value = '';
};

const saveSubtaskEdit = (subtask) => {
    if (!editingSubtaskName.value.trim()) return;

    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
        { name: editingSubtaskName.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                cancelSubtaskEdit();
            },
            onFinish: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
};

const deleteSubtask = async (subtask) => {
    // Prevent deleting the last subtask
    if (props.task.subtasks?.length <= 1) {
        window.showSnackbar('Cannot delete the last subtask. Tasks must have at least one subtask.', 'error');
        return;
    }

    if (await confirmDialog(`Delete subtask "${subtask.name}"?`, 'Delete Subtask')) {
        router.delete(
            route('tasks.destroy', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {
                preserveScroll: true,
                onFinish: () => {
                    router.reload({ only: ['task'] });
                }
            }
        );
    }
};

// Subtask assignee management
const getAvailableSubtaskMembers = (subtask) => {
    const assignedIds = (subtask.assignees || []).map(a => a.id);
    return members.value.filter(m => !assignedIds.includes(m.id));
};

const assignSubtaskMember = (subtask, member) => {
    const assigneeIds = Array.from(new Set([
        ...(subtask.assignees || []).map((assignee) => assignee.id),
        member.id,
    ]));

    router.patch(
        route('tasks.subtasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id, subtask.id]),
        { assignee_ids: assigneeIds },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
};

const removeSubtaskAssignee = (subtask, assignee) => {
    const assigneeIds = (subtask.assignees || [])
        .map((member) => member.id)
        .filter((id) => id !== assignee.id);

    router.patch(
        route('tasks.subtasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id, subtask.id]),
        { assignee_ids: assigneeIds },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
};

// Subtask sprint management
const changeSubtaskSprint = (subtask, sprintId) => {
    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
        { sprint_id: sprintId },
        {
            preserveScroll: true,
            onFinish: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
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

// Subtask time tracking
const isSubtaskTimerRunning = (subtask) => {
    // Check directly from subtask's own time_entries (most reliable)
    if (subtask.time_entries?.some(e => e.is_running)) return true;
    // Fallback: check local state
    return runningTimer.value && runningTimer.value.subtask_id === subtask.id;
};

const startSubtaskTimer = async (subtask) => {
    if (isTimerLoading.value) return;
    isTimerLoading.value = true;
    try {
        const url = route('tasks.timer.start', [props.workspace.id, props.space.id, props.list.id, props.task.id]);
        const res = await safeFetch(url, {
            method: 'POST',
            body: JSON.stringify({ subtask_id: subtask.id }),
        });

        if (res.ok || res.status === 302 || res.status === 303) {
            const data = await res.json().catch(() => ({}));

            for (const s of (props.task.subtasks || [])) {
                if (s.id !== subtask.id && s.time_entries) {
                    s.time_entries.forEach(e => { e.is_running = false; });
                }
            }

            stopTimerInterval();
            runningTimer.value = data.timeEntry || { subtask_id: subtask.id };
            elapsedSeconds.value = 0;
            startTimerInterval();
            window.showSnackbar('Timer started!', 'success');
            router.reload({ preserveScroll: true, only: ['task', 'runningTimer'] });
        } else if (res.status === 419) {
            window.location.reload();
        } else {
            window.showSnackbar('Failed to start timer', 'error');
        }
    } catch (err) {
        window.showSnackbar('Failed to start timer', 'error');
    } finally {
        isTimerLoading.value = false;
    }
};

const stopSubtaskTimer = async (subtask) => {
    if (isTimerLoading.value) return;

    let entryId = null;

    // Source 1: subtask's own time_entries
    const runningEntry = subtask.time_entries?.find(e => e.is_running);
    entryId = runningEntry?.id;

    // Source 2: local runningTimer state (set during startSubtaskTimer)
    if (!entryId && runningTimer.value?.id && runningTimer.value.subtask_id === subtask.id) {
        entryId = runningTimer.value.id;
    }

    // Source 3: global running timer from shared Inertia props
    if (!entryId) {
        const globalTimer = page.props.runningTimer;
        if (globalTimer?.subtask_id === subtask.id && globalTimer?.is_running) {
            entryId = globalTimer.id;
        }
    }

    // Source 4: ask the server
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
        window.showSnackbar('No running timer found.', 'warning');
        runningTimer.value = null;
        elapsedSeconds.value = 0;
        stopTimerInterval();
        router.reload({ preserveScroll: true, only: ['task', 'runningTimer'] });
        return;
    }

    isTimerLoading.value = true;

    try {
        const url = route('tasks.timer.stop', [props.workspace.id, props.space.id, props.list.id, props.task.id, entryId]);
        const res = await safeFetch(url, { method: 'POST' });

        if (res.ok || res.status === 302 || res.status === 303) {
            runningTimer.value = null;
            elapsedSeconds.value = 0;
            stopTimerInterval();
            window.showSnackbar('Timer stopped!', 'success');
            router.reload({ preserveScroll: true, only: ['task', 'runningTimer'] });
        } else if (res.status === 419) {
            window.location.reload();
        } else {
            window.showSnackbar('Failed to stop timer', 'error');
        }
    } catch (err) {
        window.showSnackbar('Failed to stop timer', 'error');
    } finally {
        isTimerLoading.value = false;
    }
};

// time_spent is in minutes (from DB)
const formatSubtaskTime = (minutes) => {
    if (!minutes) return '0m';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};

const formatSubtaskTimeEstimate = (minutes) => {
    if (!minutes) return 'No Estimate';
    const hours = minutes / 60;
    if (hours >= 1) {
        return `${hours}h`;
    }
    return `${minutes}m`;
};

const updateSubtaskTimeEstimate = (subtask, hours) => {
    const minutes = parseFloat(hours) * 60 || 0;

    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
        { time_estimate: minutes },
        {
            preserveScroll: true,
            onFinish: () => {
                router.reload({ only: ['task'] });
            }
        }
    );
};

const startTimerInterval = () => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    timerInterval.value = setInterval(() => {
        elapsedSeconds.value++;
    }, 1000);
};

const stopTimerInterval = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
        elapsedSeconds.value = 0;
    }
};

// ===== Dependency Management =====
const dependencyDialog = ref(false);
const dependencySubtask = ref(null);
const dependencyLoading = ref(false);

const openDependencyDialog = (subtask) => {
    dependencySubtask.value = subtask;
    dependencyDialog.value = true;
};

// Get subtasks that can be added as dependencies (not self, not already a dependency)
const getAvailableDependencies = (subtask) => {
    const existingDepIds = (subtask.dependencies || []).map(d => d.id);
    return (props.task.subtasks || []).filter(
        s => s.id !== subtask.id && !existingDepIds.includes(s.id)
    );
};

const addDependency = (subtask, dependsOn) => {
    dependencyLoading.value = true;

    fetch(
        route('tasks.cpm.dependencies.add', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({
                subtask_id: subtask.id,
                depends_on_id: dependsOn.id,
                type: 'blocks',
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            router.reload({ only: ['task'] });
            if (window.showSnackbar) {
                window.showSnackbar('Dependency added!', 'success');
            }
        } else {
            if (window.showSnackbar) {
                window.showSnackbar(result.message || 'Failed to add dependency', 'error');
            }
        }
    }).catch(() => {
        if (window.showSnackbar) {
            window.showSnackbar('Failed to add dependency', 'error');
        }
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const removeDependency = (subtask, dependsOn) => {
    dependencyLoading.value = true;

    fetch(
        route('tasks.cpm.dependencies.remove', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({
                subtask_id: subtask.id,
                depends_on_id: dependsOn.id,
            }),
        }
    ).then(res => res.json()).then(result => {
        if (result.success) {
            router.reload({ only: ['task'] });
            if (window.showSnackbar) {
                window.showSnackbar('Dependency removed!', 'success');
            }
        } else {
            if (window.showSnackbar) {
                window.showSnackbar(result.message || 'Failed to remove dependency', 'error');
            }
        }
    }).catch(() => {
        if (window.showSnackbar) {
            window.showSnackbar('Failed to remove dependency', 'error');
        }
    }).finally(() => {
        dependencyLoading.value = false;
    });
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Check for running timer on mount — scan subtasks since task-level time_entries isn't loaded
onMounted(() => {
    // Search through subtask time_entries
    for (const subtask of (props.task.subtasks || [])) {
        const runningEntry = subtask.time_entries?.find(e => e.is_running);
        if (runningEntry) {
            runningTimer.value = { ...runningEntry, subtask_id: subtask.id };
            const startTime = new Date(runningEntry.started_at).getTime();
            elapsedSeconds.value = Math.floor((Date.now() - startTime) / 1000);
            startTimerInterval();
            break;
        }
    }

    // Fallback: check global running timer from shared props
    if (!runningTimer.value) {
        const globalTimer = page.props.runningTimer;
        if (globalTimer?.is_running) {
            const belongsToThisTask = (props.task.subtasks || []).some(s => s.id === globalTimer.subtask_id);
            if (belongsToThisTask) {
                runningTimer.value = globalTimer;
                const startTime = new Date(globalTimer.started_at).getTime();
                elapsedSeconds.value = Math.floor((Date.now() - startTime) / 1000);
                startTimerInterval();
            }
        }
    }
});

onUnmounted(() => {
    stopTimerInterval();
});
</script>

<template>

    <Head :title="task.name" />

    <MainLayout :workspace="workspace">
        <div class="h-full flex bg-[#1E1E1E]">
            <!-- Main Content -->
            <div class="flex-1 overflow-auto p-6">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                    <v-btn icon="mdi-arrow-left" variant="text" size="small" @click="goBack" />
                    <span>{{ space?.name }}</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <span>{{ list?.name }}</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <span class="text-white">{{ task.name }}</span>
                </div>

                <!-- Task Header -->
                <div class="mb-6">
                    <div class="flex items-start gap-4">
                        <!-- Status indicator -->
                        <v-btn :icon="task.completed_at ? 'mdi-check-circle' : 'mdi-circle-outline'" variant="text"
                            size="large" :color="task.completed_at ? 'success' : 'default'" @click="toggleComplete" />

                        <div class="flex-1">
                            <!-- Editable title -->
                            <input v-model="localTask.name" @blur="updateTask" @keyup.enter="$event.target.blur()"
                                class="w-full bg-transparent text-2xl font-bold text-white focus:outline-none focus:bg-[#2D2D2D] px-2 py-1 rounded" />

                            <!-- Task ID -->
                            <div class="text-sm text-gray-500 mt-1 px-2">
                                #{{ task.id }}
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <v-btn icon="mdi-dots-vertical" variant="text">
                                <v-icon>mdi-dots-vertical</v-icon>
                                <v-menu activator="parent">
                                    <v-card color="surface">
                                        <v-list density="compact">
                                            <v-list-item @click="duplicateTask" prepend-icon="mdi-content-copy">
                                                <v-list-item-title>Duplicate</v-list-item-title>
                                            </v-list-item>
                                            <v-list-item @click="deleteTask" prepend-icon="mdi-delete"
                                                class="text-error">
                                                <v-list-item-title>Delete</v-list-item-title>
                                            </v-list-item>
                                        </v-list>
                                    </v-card>
                                </v-menu>
                            </v-btn>
                        </div>
                    </div>
                </div>

                <!-- Task Details Grid -->
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <!-- Status -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Status</div>
                        <v-select v-model="localTask.status_id" :items="statuses" item-title="name" item-value="id"
                            variant="solo-filled" density="compact" hide-details bg-color="#3D3D3D"
                            @update:model-value="updateStatus">
                            <template #selection="{ item }">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.raw.color }">
                                    </div>
                                    {{ item.title }}
                                </div>
                            </template>
                            <template #item="{ item, props }">
                                <v-list-item v-bind="props">
                                    <template #prepend>
                                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.raw.color }">
                                        </div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
                    </div>

                    <!-- Priority -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Priority</div>
                        <v-select v-model="localTask.priority_level" :items="PRIORITIES" item-title="name"
                            item-value="level" variant="solo-filled" density="compact" hide-details clearable
                            bg-color="#3D3D3D" @update:model-value="updatePriority">
                            <template #selection="{ item }">
                                <div class="flex items-center gap-2">
                                    <v-icon :color="item.raw.color" size="16">mdi-flag</v-icon>
                                    {{ item.title }}
                                </div>
                            </template>
                            <template #item="{ item, props }">
                                <v-list-item v-bind="props">
                                    <template #prepend>
                                        <v-icon :color="item.raw.color" size="16">mdi-flag</v-icon>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
                    </div>

                    <!-- Due Date -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Due Date</div>
                        <v-text-field v-model="localTask.due_date" type="date" variant="solo-filled" density="compact"
                            hide-details bg-color="#3D3D3D" @update:model-value="updateTask" />
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-[#2D2D2D] rounded-lg p-4 mb-6">
                    <div class="text-sm text-gray-400 mb-2">Description</div>
                    <v-textarea v-model="localTask.description" placeholder="Add a description..." variant="solo-filled"
                        bg-color="#3D3D3D" hide-details rows="4" @blur="updateTask" />
                </div>

                <!-- Labels -->
                <div class="bg-[#2D2D2D] rounded-lg p-4 mb-6">
                    <div class="text-sm text-gray-400 mb-2">Labels</div>
                    <div class="flex flex-wrap gap-2">
                        <v-chip v-for="label in task.labels" :key="label.id" :color="label.color" closable
                            @click:close="removeLabel(label)" size="small">
                            {{ label.name }}
                        </v-chip>

                        <v-menu :close-on-content-click="false">
                            <template #activator="{ props }">
                                <v-btn v-bind="props" prepend-icon="mdi-plus" size="small" variant="outlined">
                                    Add Label
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="280">
                                <v-list density="compact" max-height="280" class="overflow-auto">
                                    <v-list-item v-for="label in labels" :key="label.id" @click="toggleLabel(label)">
                                        <template #prepend>
                                            <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: label.color }">
                                            </div>
                                        </template>
                                        <v-list-item-title>{{ label.name }}</v-list-item-title>
                                        <template #append>
                                            <div class="flex items-center gap-1">
                                                <v-icon v-if="isLabelSelected(label.id)" size="16" color="primary">
                                                    mdi-check
                                                </v-icon>
                                                <v-btn
                                                    icon
                                                    variant="text"
                                                    size="x-small"
                                                    @click.stop="openEditLabelEditor(label)"
                                                >
                                                    <v-icon size="14">mdi-pencil-outline</v-icon>
                                                </v-btn>
                                            </div>
                                        </template>
                                    </v-list-item>
                                    <v-list-item v-if="!labels.length" disabled>
                                        <v-list-item-title class="text-gray-500">No labels yet</v-list-item-title>
                                    </v-list-item>
                                </v-list>

                                <v-divider />
                                <div class="p-2 flex justify-end">
                                    <v-btn size="small" variant="tonal" color="primary" @click="openCreateLabelEditor">
                                        <v-icon start size="14">mdi-plus</v-icon>
                                        Create Label
                                    </v-btn>
                                </div>
                            </v-card>
                        </v-menu>
                    </div>
                </div>

                <v-dialog v-model="showLabelEditor" max-width="420">
                    <v-card>
                        <v-card-title class="d-flex align-center ga-2">
                            <v-icon :color="editingLabel ? 'warning' : 'primary'">mdi-label-outline</v-icon>
                            <span>{{ editingLabel ? 'Edit Label' : 'Create Label' }}</span>
                        </v-card-title>

                        <v-card-text>
                            <v-text-field
                                v-model="labelForm.name"
                                label="Label name"
                                variant="outlined"
                                density="compact"
                                autofocus
                                class="mb-3"
                            />

                            <v-text-field
                                v-model="labelForm.color"
                                label="Color (#RRGGBB)"
                                variant="outlined"
                                density="compact"
                                class="mb-3"
                                @blur="labelForm.color = normalizeLabelColor(labelForm.color)"
                            />

                            <div class="d-flex align-center ga-3 mb-4">
                                <input v-model="labelForm.color" type="color" class="color-input-native" />
                                <div class="text-xs text-gray-400">Choose any color with the picker</div>
                            </div>
                        </v-card-text>

                        <v-card-actions>
                            <v-btn
                                v-if="editingLabel"
                                variant="text"
                                color="error"
                                @click="deleteWorkspaceLabel"
                            >
                                Delete
                            </v-btn>
                            <v-spacer />
                            <v-btn variant="text" @click="showLabelEditor = false">Cancel</v-btn>
                            <v-btn color="primary" :disabled="!labelForm.name?.trim()" @click="saveWorkspaceLabel">
                                {{ editingLabel ? 'Save' : 'Create' }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>

                <!-- Comments Section -->
                <div class="bg-[#2D2D2D] rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-4">Comments</div>

                    <!-- Add comment -->
                    <div class="flex gap-3 mb-4">
                        <v-avatar size="32" :image="$page.props.auth?.user?.profile_photo_url">
                            <span v-if="!$page.props.auth?.user?.profile_photo_url">
                                {{ $page.props.auth?.user?.name?.[0] }}
                            </span>
                        </v-avatar>
                        <div class="flex-1">
                            <v-textarea v-model="newComment" placeholder="Write a comment..." variant="solo-filled"
                                bg-color="#3D3D3D" hide-details rows="2" :disabled="isSubmittingComment" />
                            <div class="flex justify-end mt-2">
                                <v-btn color="primary" size="small"
                                    :disabled="!newComment.trim() || isSubmittingComment" :loading="isSubmittingComment"
                                    @click="addComment">
                                    Comment
                                </v-btn>
                            </div>
                        </div>
                    </div>

                    <!-- Comments list -->
                    <div v-if="task.comments && task.comments.length > 0">
                        <div v-for="comment in task.comments" :key="comment.id"
                            class="flex gap-3 py-3 border-b border-gray-700 last:border-0">
                            <v-avatar size="32" :image="comment.user?.profile_photo_url">
                                <span v-if="!comment.user?.profile_photo_url">
                                    {{ comment.user?.name?.[0] }}
                                </span>
                            </v-avatar>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ comment.user?.name }}</span>
                                    <span class="text-xs text-gray-500">{{ formatDate(comment.created_at) }}</span>
                                    <v-chip v-if="comment.is_resolved" size="x-small" color="success">
                                        Resolved
                                    </v-chip>
                                </div>
                                <p class="text-gray-300 mt-1 whitespace-pre-wrap">{{ comment.content }}</p>

                                <!-- Comment Actions -->
                                <div class="flex items-center gap-2 mt-2">
                                    <!-- Reactions -->
                                    <div class="flex gap-1">
                                        <v-btn v-for="emoji in ['👍', '❤️', '😄', '🎉', '👀']" :key="emoji"
                                            size="x-small" variant="text"
                                            :color="getReactionCount(comment, emoji) > 0 ? 'primary' : undefined"
                                            @click="toggleReaction(comment, emoji)">
                                            {{ emoji }} <span v-if="getReactionCount(comment, emoji) > 0"
                                                class="ml-1">{{
                                                    getReactionCount(comment, emoji) }}</span>
                                        </v-btn>
                                    </div>

                                    <!-- Resolve Button -->
                                    <v-btn v-if="!comment.is_resolved" size="x-small" variant="text" color="success"
                                        prepend-icon="mdi-check" @click="router.post(route('comments.resolve', comment.id), {}, {
                                            preserveScroll: true,
                                            onFinish: () => router.reload({ only: ['task'] })
                                        })">
                                        Resolve
                                    </v-btn>
                                    <v-btn v-else size="x-small" variant="text" prepend-icon="mdi-undo" @click="router.post(route('comments.unresolve', comment.id), {}, {
                                        preserveScroll: true,
                                        onFinish: () => router.reload({ only: ['task'] })
                                    })">
                                        Unresolve
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 text-center py-4">
                        No comments yet. Be the first to comment!
                    </div>
                </div>
            </div>

            <!-- Subtasks Panel (Right sidebar) -->
            <div class="w-96 border-l border-gray-800 bg-[#252526] p-4 overflow-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Subtasks</h3>
                    <v-btn icon="mdi-plus" size="x-small" variant="outlined" @click="showAddSubtask = true" />
                </div>

                <!-- Add subtask input -->
                <div v-if="showAddSubtask" class="mb-4">
                    <v-text-field v-model="newSubtaskName" placeholder="Subtask name" variant="solo-filled"
                        density="compact" hide-details bg-color="#3D3D3D" @keyup.enter="addSubtask" autofocus />
                    <div class="flex gap-2 mt-2">
                        <v-btn size="small" color="primary" @click="addSubtask">Add</v-btn>
                        <v-btn size="small" variant="text" @click="showAddSubtask = false">Cancel</v-btn>
                    </div>
                </div>

                <!-- Subtasks list -->
                <div v-if="task.subtasks?.length > 0" class="space-y-3">
                    <draggable v-model="localSubtasks" item-key="id" :animation="200" ghost-class="subtask-ghost"
                        handle=".subtask-drag-handle" @start="subtasksDragging = true" @end="onSubtaskDragEnd">
                        <template #item="{ element: subtask }">
                            <div class="bg-[#2D2D2D] rounded-lg p-3 hover:bg-[#323233] transition-colors mb-3">
                                <!-- Subtask Header -->
                                <div class="group flex items-start gap-2 mb-2">
                                    <v-icon
                                        class="subtask-drag-handle cursor-grab mt-1 text-gray-500 hover:text-gray-300"
                                        size="16">mdi-drag</v-icon>
                                    <v-checkbox :model-value="!!subtask.completed_at" hide-details density="compact"
                                        @update:model-value="toggleSubtask(subtask)" />
                                    <div v-if="editingSubtaskId === subtask.id" class="flex-1">
                                        <v-text-field v-model="editingSubtaskName" variant="solo-filled"
                                            density="compact" hide-details bg-color="#3D3D3D"
                                            @keyup.enter="saveSubtaskEdit(subtask)" @keyup.esc="cancelSubtaskEdit"
                                            autofocus />
                                    </div>
                                    <span v-else :class="{ 'line-through text-gray-500': subtask.completed_at }"
                                        class="flex-1 cursor-pointer" @click="startEditSubtask(subtask)">
                                        {{ subtask.name }}
                                    </span>
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                        <v-btn v-if="editingSubtaskId === subtask.id" icon="mdi-check" size="x-small"
                                            variant="text" color="success" @click="saveSubtaskEdit(subtask)" />
                                        <v-btn v-if="editingSubtaskId === subtask.id" icon="mdi-close" size="x-small"
                                            variant="text" @click="cancelSubtaskEdit" />
                                        <v-btn v-else icon="mdi-pencil" size="x-small" variant="text"
                                            @click="startEditSubtask(subtask)" />
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error"
                                            @click="deleteSubtask(subtask)" />
                                    </div>
                                </div>

                                <!-- Subtask Details -->
                                <div class="pl-8 space-y-2 text-xs">
                                    <!-- Assignees -->
                                    <div class="flex items-center gap-2">
                                        <v-icon size="14" class="text-gray-500">mdi-account</v-icon>
                                        <div class="flex flex-wrap gap-1 flex-1">
                                            <v-chip v-for="assignee in subtask.assignees" :key="assignee.id"
                                                size="x-small" closable
                                                @click:close="removeSubtaskAssignee(subtask, assignee)">
                                                {{ assignee.name }}
                                            </v-chip>
                                            <v-menu>
                                                <template #activator="{ props }">
                                                    <v-btn v-bind="props" icon="mdi-plus" size="x-small"
                                                        variant="text" />
                                                </template>
                                                <v-card color="surface">
                                                    <v-list density="compact">
                                                        <v-list-item
                                                            v-for="member in getAvailableSubtaskMembers(subtask)"
                                                            :key="member.id"
                                                            @click="assignSubtaskMember(subtask, member)">
                                                            <v-list-item-title>{{ member.name }}</v-list-item-title>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-card>
                                            </v-menu>
                                        </div>
                                    </div>

                                    <!-- Sprint -->
                                    <div class="flex items-center gap-2">
                                        <v-icon size="14" class="text-gray-500">mdi-calendar-clock</v-icon>
                                        <v-menu>
                                            <template #activator="{ props }">
                                                <v-btn v-bind="props" variant="text" size="x-small"
                                                    :color="subtask.sprint ? 'primary' : 'default'">
                                                    {{ subtask.sprint?.name || 'No Sprint' }}
                                                </v-btn>
                                            </template>
                                            <v-card color="surface">
                                                <v-list density="compact">
                                                    <v-list-item v-for="sprint in sprints" :key="sprint.id"
                                                        @click="changeSubtaskSprint(subtask, sprint.id)">
                                                        <v-list-item-title>{{ sprint.name }}</v-list-item-title>
                                                    </v-list-item>
                                                    <v-divider v-if="subtask.sprint" />
                                                    <v-list-item v-if="subtask.sprint" prepend-icon="mdi-close"
                                                        title="Remove from Sprint"
                                                        @click="changeSubtaskSprint(subtask, null)" />
                                                </v-list>
                                            </v-card>
                                        </v-menu>
                                    </div>

                                    <!-- Time Estimate -->
                                    <div class="flex items-center gap-2">
                                        <v-icon size="14" class="text-gray-500">mdi-timer-sand</v-icon>
                                        <v-menu :close-on-content-click="false">
                                            <template #activator="{ props }">
                                                <v-btn v-bind="props" variant="text" size="x-small"
                                                    :color="subtask.time_estimate ? 'primary' : 'default'">
                                                    {{ formatSubtaskTimeEstimate(subtask.time_estimate) }}
                                                </v-btn>
                                            </template>
                                            <v-card color="surface" min-width="200">
                                                <v-card-text>
                                                    <div class="text-sm font-medium mb-2">Time Estimate (Man-Hour)</div>
                                                    <v-text-field :model-value="(subtask.time_estimate || 0) / 60"
                                                        @update:model-value="(val) => updateSubtaskTimeEstimate(subtask, val)"
                                                        type="number" variant="outlined" density="compact" hide-details
                                                        bg-color="#3D3D3D" min="0" step="0.5" />
                                                </v-card-text>
                                            </v-card>
                                        </v-menu>
                                    </div>

                                    <!-- Time Tracking -->
                                    <div class="flex items-center gap-2">
                                        <v-icon size="14" class="text-gray-500">mdi-timer</v-icon>
                                        <v-btn v-if="!isSubtaskTimerRunning(subtask)" prepend-icon="mdi-play"
                                            size="x-small" variant="text" @click="startSubtaskTimer(subtask)">
                                            Start
                                        </v-btn>
                                        <template v-else>
                                            <v-btn prepend-icon="mdi-stop" size="x-small" variant="text" color="error"
                                                @click="stopSubtaskTimer(subtask)">
                                                Stop
                                            </v-btn>
                                            <span class="text-red-400 font-mono text-[11px]">
                                                {{ formatRunningTime }}
                                            </span>
                                        </template>
                                        <span v-if="subtask.time_spent" class="text-gray-400">
                                            {{ formatSubtaskTime(subtask.time_spent) }}
                                        </span>
                                    </div>

                                    <!-- Dependencies (Predecessor) -->
                                    <div class="flex items-start gap-2">
                                        <v-icon size="14" class="text-gray-500 mt-1">mdi-link-variant</v-icon>
                                        <div class="flex-1">
                                            <div class="flex flex-wrap gap-1 items-center">
                                                <v-chip v-for="dep in (subtask.dependencies || [])" :key="dep.id"
                                                    size="x-small" color="warning" variant="tonal" closable
                                                    @click:close="removeDependency(subtask, dep)">
                                                    <v-icon start size="10">mdi-arrow-left</v-icon>
                                                    {{ dep.name }}
                                                </v-chip>
                                                <v-chip v-for="dep in (subtask.dependents || [])" :key="'dep-' + dep.id"
                                                    size="x-small" color="info" variant="tonal">
                                                    <v-icon start size="10">mdi-arrow-right</v-icon>
                                                    {{ dep.name }}
                                                </v-chip>
                                                <v-menu :close-on-content-click="false">
                                                    <template #activator="{ props: menuProps }">
                                                        <v-btn v-bind="menuProps" icon="mdi-plus" size="x-small"
                                                            variant="text" />
                                                    </template>
                                                    <v-card color="surface" min-width="250">
                                                        <v-card-title class="text-sm py-2">
                                                            Add Predecessor
                                                        </v-card-title>
                                                        <v-divider />
                                                        <v-list density="compact" max-height="200"
                                                            class="overflow-auto">
                                                            <v-list-item v-for="s in getAvailableDependencies(subtask)"
                                                                :key="s.id" :disabled="dependencyLoading"
                                                                @click="addDependency(subtask, s)">
                                                                <template #prepend>
                                                                    <v-icon size="16">mdi-subtitles-outline</v-icon>
                                                                </template>
                                                                <v-list-item-title class="text-sm">{{ s.name
                                                                    }}</v-list-item-title>
                                                                <v-list-item-subtitle v-if="s.time_estimate"
                                                                    class="text-xs">
                                                                    Est: {{ formatSubtaskTimeEstimate(s.time_estimate)
                                                                    }}
                                                                </v-list-item-subtitle>
                                                            </v-list-item>
                                                            <v-list-item
                                                                v-if="getAvailableDependencies(subtask).length === 0"
                                                                disabled>
                                                                <v-list-item-title class="text-sm text-gray-500">No
                                                                    available subtasks</v-list-item-title>
                                                            </v-list-item>
                                                        </v-list>
                                                    </v-card>
                                                </v-menu>
                                            </div>
                                            <div v-if="!(subtask.dependencies || []).length && !(subtask.dependents || []).length"
                                                class="text-gray-500 text-[10px]">No dependencies</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
                <div v-else class="text-sm text-gray-500 text-center py-4">
                    No subtasks yet
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.subtask-ghost {
    opacity: 0.4;
    background: #3D3D3D;
    border-radius: 8px;
}

.subtask-drag-handle:active {
    cursor: grabbing;
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