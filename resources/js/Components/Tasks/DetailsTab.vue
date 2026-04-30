<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { PRIORITIES, PRIORITY_MAP } from '@/constants/priorities';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import ColorPicker from '@/Components/ColorPicker.vue';
import {
    getFallbackCompletionTarget,
    getStoredSubtaskCompletionTarget,
    getSubtaskCompletionStatusOptions,
    setStoredSubtaskCompletionTarget,
} from '@/utils/subtaskCompletionAutomation';

const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    localTask: Object,
    task: Object,
    isSubtask: Boolean,
    workspace: Object,
    space: Object,
    list: Object,
    parentTask: Object,
    statuses: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    labels: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    siblingSubtasks: { type: Array, default: () => [] },
    isTracking: Boolean,
    formatTrackingDuration: String,
    isTimerLoading: Boolean,
    canOperateTasks: { type: Boolean, default: false },
    canManageTaskStructure: { type: Boolean, default: false },
});

const emit = defineEmits(['start-tracking', 'stop-tracking', 'updated', 'view-subtasks']);

// ===== Route helper =====
const getUpdateRoute = () => {
    if (props.isSubtask) {
        return route('tasks.subtasks.update', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id]);
    }
    return route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]);
};

// ===== Formatters =====
const formatDuration = (seconds) => {
    if (!seconds) return 'Not set';
    const h = Math.floor(seconds / 3600), m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
};
const formatTimeEstimate = (minutes) => {
    if (!minutes) return 'Not set';
    const h = minutes / 60;
    return h % 1 === 0 ? `${h}h` : `${h.toFixed(1)}h`;
};
const formatDate = (d) => d
    ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    : 'Not set';
const formatVariance = (minutes) => {
    if (!minutes) return null;
    const abs = Math.abs(minutes);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    const sign = minutes > 0 ? '+' : '-';
    return h > 0 ? `${sign}${h}h ${m}m` : `${sign}${m}m`;
};
const formatSubtaskEstimate = (m) => {
    if (!m) return '';
    const h = m / 60;
    return h >= 1 ? `${h}h` : `${m}m`;
};
const getSpentPercentage = (spentMinutes, estimateMinutes) => {
    if (!estimateMinutes) return null;
    return Math.round(((spentMinutes || 0) / estimateMinutes) * 100);
};

// ===== Priority =====
const currentPriority = computed(() => {
    if (!props.localTask?.priority_level) return null;
    return PRIORITY_MAP[props.localTask.priority_level] || null;
});

// ===== Completion automation (subtask) =====
const completionStatusOptions = computed(() => getSubtaskCompletionStatusOptions(props.statuses));
const completionTargetStatusIdState = ref(null);

const syncCompletionAutomation = () => {
    const storedTarget = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    completionTargetStatusIdState.value = storedTarget ?? getFallbackCompletionTarget(props.statuses);
};

watch(() => [props.space?.id, props.statuses], () => {
    syncCompletionAutomation();
}, { immediate: true });

const completionTargetStatus = computed(() => {
    return completionStatusOptions.value.find((status) => status.id === completionTargetStatusIdState.value) || null;
});

const completionTargetStatusName = computed(() => {
    return completionTargetStatus.value?.name || 'Off';
});

const setCompletionAutomation = (statusId) => {
    completionTargetStatusIdState.value = statusId ? Number(statusId) : null;
    setStoredSubtaskCompletionTarget(props.space?.id, statusId);
    window.showSnackbar?.(statusId ? 'Automation status updated!' : 'Automation disabled!', 'success');
};

// ===== Description =====
const editedDescription = ref(props.localTask?.description || '');
watch(() => props.localTask?.description, (v) => { editedDescription.value = v || ''; }, { immediate: true });

const saveDescription = () => {
    if (editedDescription.value !== props.task.description) {
        router.patch(getUpdateRoute(), { description: editedDescription.value }, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task', 'tasksByStatus'] });
            },
        });
    }
};

// ===== Status / Priority / Sprint =====
const sprintLookup = computed(() => {
    return new Map((props.sprints || []).map((sprint) => [Number(sprint.id), sprint]));
});

const resolveTaskSprint = (item) => {
    if (!item) return null;
    if (item.sprint) return item.sprint;

    const sprintId = Number(item.sprint_id || 0);
    if (!sprintId) return null;

    return sprintLookup.value.get(sprintId) || null;
};

const currentTaskSprint = computed(() => resolveTaskSprint(props.localTask));

const sprintDisplayName = (item) => {
    const sprint = resolveTaskSprint(item);
    if (sprint?.name) return sprint.name;

    const sprintId = Number(item?.sprint_id || 0);
    if (sprintId) return `Sprint #${sprintId}`;

    return 'Backlog';
};

const changePriority = (level) => {
    router.patch(getUpdateRoute(), { priority_level: level }, {
        preserveScroll: true,
        onSuccess: () => { router.reload({ only: ['task', 'tasksByStatus'] }); },
    });
};
const isSprintActive = (sprint) => {
    if (!sprint) return false;
    if (sprint.is_active) return true;
    if (!sprint.start_date || !sprint.end_date) return false;

    const today = new Date(); today.setHours(0, 0, 0, 0);
    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return false;

    start.setHours(0, 0, 0, 0);
    end.setHours(23, 59, 59, 999);
    return today >= start && today <= end;
};
const changeSprint = (id) => {
    router.patch(getUpdateRoute(), { sprint_id: id }, {
        preserveScroll: true,
        onSuccess: () => { router.reload({ only: ['task', 'tasksByStatus'] }); },
    });
};

// ===== Dates =====
const showStartDatePicker = ref(false);
const tempStartDate = ref(null);
const showDueDatePicker = ref(false);
const tempDueDate = ref(null);

watch(showStartDatePicker, (isOpen) => { if (isOpen) tempStartDate.value = props.task.start_date ? String(props.task.start_date).substring(0, 10) : null; });
const updateStartDate = () => {
    const dueDay = props.task.due_date ? String(props.task.due_date).substring(0, 10) : null;
    if (tempStartDate.value && dueDay && tempStartDate.value > dueDay) {
        window.showSnackbar?.('Start date cannot be after due date.', 'error'); return;
    }
    const old = props.task.start_date;
    props.task.start_date = tempStartDate.value;
    showStartDatePicker.value = false;
    router.patch(getUpdateRoute(), { start_date: tempStartDate.value || '' }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => router.reload({ only: ['task', 'tasksByStatus'] }),
        onError: (errors) => {
            props.task.start_date = old;
            window.showSnackbar?.(Object.values(errors).flat().join(', ') || 'Failed to update start date', 'error');
        },
    });
};
watch(showDueDatePicker, (isOpen) => { if (isOpen) tempDueDate.value = props.task.due_date ? String(props.task.due_date).substring(0, 10) : null; });
const updateDueDate = () => {
    const startDay = props.task.start_date ? String(props.task.start_date).substring(0, 10) : null;
    if (tempDueDate.value && startDay && tempDueDate.value < startDay) {
        window.showSnackbar?.('Due date cannot be before start date.', 'error'); return;
    }
    const old = props.task.due_date;
    props.task.due_date = tempDueDate.value;
    showDueDatePicker.value = false;
    router.patch(getUpdateRoute(), { due_date: tempDueDate.value || '' }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => router.reload({ only: ['task', 'tasksByStatus'] }),
        onError: (errors) => {
            props.task.due_date = old;
            window.showSnackbar?.(Object.values(errors).flat().join(', ') || 'Failed to update due date', 'error');
        },
    });
};

// ===== Time estimate =====
const showTimeEstimatePicker = ref(false);
const tempTimeEstimate = ref(0);
watch(showTimeEstimatePicker, (isOpen) => { if (isOpen) tempTimeEstimate.value = (props.task.time_estimate || 0) / 60; });
const updateTimeEstimate = () => {
    const raw = parseFloat(tempTimeEstimate.value) || 0;
    if (raw < 0) { window.showSnackbar?.('Time estimate cannot be negative.', 'error'); return; }
    if (raw > 8760) { window.showSnackbar?.('Time estimate cannot exceed 1 year.', 'error'); return; }
    if (!/^\d+(\.\d)?$/.test(String(tempTimeEstimate.value).trim())) {
        window.showSnackbar?.('Maximum 1 decimal place allowed.', 'error'); return;
    }
    const total = Math.round(raw * 60);
    const old = props.task.time_estimate;
    props.task.time_estimate = total;
    showTimeEstimatePicker.value = false;
    router.patch(getUpdateRoute(), { time_estimate: total }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => router.reload({ only: ['task', 'tasksByStatus'] }),
        onError: () => { props.task.time_estimate = old; },
    });
};

// ===== PERT =====
const showPertPicker = ref(false);
const tempOptimistic = ref(0), tempMostLikely = ref(0), tempPessimistic = ref(0);
watch(showPertPicker, (isOpen) => {
    if (isOpen) {
        tempOptimistic.value = (props.task.optimistic_estimate || 0) / 60;
        tempMostLikely.value = (props.task.most_likely_estimate || 0) / 60;
        tempPessimistic.value = (props.task.pessimistic_estimate || 0) / 60;
    }
});
const updatePertEstimates = () => {
    const o = Math.round((parseFloat(tempOptimistic.value) || 0) * 60);
    const m = Math.round((parseFloat(tempMostLikely.value) || 0) * 60);
    const p = Math.round((parseFloat(tempPessimistic.value) || 0) * 60);
    if (o > m || m > p) { window.showSnackbar?.('PERT estimates must satisfy optimistic <= most likely <= pessimistic.', 'error'); return; }
    const expected = Math.round((o + 4 * m + p) / 6);
    router.patch(getUpdateRoute(), {
        optimistic_estimate: o || null, most_likely_estimate: m || null,
        pessimistic_estimate: p || null, time_estimate: expected || null,
    }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { showPertPicker.value = false; router.reload({ only: ['task', 'tasksByStatus'] }); },
    });
};

// ===== Baseline =====
const setBaselineFromCurrent = () => {
    if (!props.task.start_date && !props.task.due_date) {
        window.showSnackbar?.('Set actual start/due dates first before capturing a baseline.', 'error'); return;
    }
    router.patch(getUpdateRoute(), {
        baseline_start_date: props.task.start_date || null,
        baseline_due_date: props.task.due_date || null,
    }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => router.reload({ only: ['task', 'tasksByStatus'] }),
    });
};

// ===== Labels =====
const workspaceLabels = computed(() => props.labels || []);
const showLabelEditor = ref(false);
const editingLabel = ref(null);
const labelForm = ref({ name: '', color: '#61BD4F' });

const isLabelSelected = (labelId) => {
    return (props.localTask?.labels || []).some((label) => label.id === labelId);
};

const normalizeLabelColor = (color) => {
    const raw = (color || '').trim();
    if (!raw) return '#61BD4F';
    return raw.startsWith('#') ? raw.toUpperCase() : `#${raw.toUpperCase()}`;
};

const openCreateLabelEditor = () => {
    editingLabel.value = null;
    labelForm.value = { name: '', color: '#61BD4F' };
    showLabelEditor.value = true;
};

const openEditLabelEditor = (label) => {
    editingLabel.value = label;
    labelForm.value = {
        name: label.name,
        color: label.color,
    };
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
    if (!props.localTask.labels) props.localTask.labels = [];
    props.localTask.labels.push(label);
    const url = props.isSubtask
        ? route('tasks.subtasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id])
        : route('tasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.task.id]);
    router.post(url, { label_id: label.id }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => { router.reload({ only: ['task', 'tasksByStatus'] }); },
        onError: () => { props.localTask.labels = props.localTask.labels.filter(l => l.id !== label.id); window.showSnackbar?.('Failed to add label', 'error'); },
    });
};
const removeLabel = (label) => {
    const backup = [...(props.localTask.labels || [])];
    props.localTask.labels = props.localTask.labels.filter(l => l.id !== label.id);
    const url = props.isSubtask
        ? route('tasks.subtasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id])
        : route('tasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.task.id]);
    router.delete(url, {
        data: { label_id: label.id }, preserveScroll: true, preserveState: true,
        onSuccess: () => { router.reload({ only: ['task', 'tasksByStatus'] }); },
        onError: () => { props.localTask.labels = backup; window.showSnackbar?.('Failed to remove label', 'error'); },
    });
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
            preserveState: true,
            onSuccess: () => {
                showLabelEditor.value = false;
                router.reload({ only: ['workspace', 'task', 'tasksByStatus'] });
            },
        });
        return;
    }

    router.post(route('workspaces.labels.store', props.workspace.id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showLabelEditor.value = false;
            router.reload({ only: ['workspace', 'task', 'tasksByStatus'] });
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

    const deletingId = editingLabel.value.id;

    router.delete(route('workspaces.labels.destroy', [props.workspace.id, deletingId]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            props.localTask.labels = (props.localTask.labels || []).filter((label) => label.id !== deletingId);
            showLabelEditor.value = false;
            editingLabel.value = null;
            router.reload({ only: ['workspace', 'task', 'tasksByStatus'] });
        },
    });
};

// ===== Assignees =====
const displayedAssignees = computed(() => {
    // For subtasks: show that subtask's own assignees
    if (props.isSubtask) {
        return props.localTask?.assignees || [];
    }

    // For tasks: show task-level assignees
    return props.localTask?.assignees || [];
});

const toggleAssignee = (userId) => {
    if (!props.isSubtask) {
        return;
    }

    const isAssigned = props.task.assignees?.some(a => a.id === userId);
    const member = props.members.find(m => m.id === userId);
    if (isAssigned) {
        props.task.assignees = props.task.assignees.filter(a => a.id !== userId);
    } else {
        // Replace: subtask allows max 1 assignee
        props.task.assignees = [member];
    }
    const reloadAfterAssign = () => router.reload({ only: ['task', 'tasksByStatus'] });

    const ids = props.task.assignees?.map(a => a.id) || [];
    router.patch(getUpdateRoute(), { assignee_ids: ids }, {
        preserveScroll: true,
        onSuccess: () => {
            reloadAfterAssign();
        },
        onError: () => {
            if (isAssigned) props.task.assignees.push(member);
            else props.task.assignees = props.task.assignees.filter(a => a.id !== userId);
        },
    });
};

// ===== Subtask list management (for tasks, not subtask panel) =====
const toggleSubtask = (subtask) => {
    const done = !!subtask.completed_at;
    const targetStatusId = getStoredSubtaskCompletionTarget(props.space?.id, props.statuses);
    const payload = !done && targetStatusId ? { target_status_id: targetStatusId } : {};

    router.post(route(done ? 'tasks.subtasks.reopen' : 'tasks.subtasks.complete', [props.workspace.id, props.space.id, props.list.id, props.task.id, subtask.id]), payload, {
        preserveScroll: true,
        onSuccess: () => { router.reload({ only: ['task', 'tasksByStatus'] }); },
        onError: (errors) => { if (errors.dependency) window.showSnackbar?.(errors.dependency, 'error'); },
    });
};
// ===== Dependencies =====
const dependencyLoading = ref(false);

const dependencyCandidateSubtasks = computed(() => {
    if (!props.isSubtask) return [];

    const fromParent = props.parentTask?.subtasks;
    if (Array.isArray(fromParent) && fromParent.length > 0) {
        return fromParent;
    }

    return Array.isArray(props.siblingSubtasks) ? props.siblingSubtasks : [];
});

const availableForDependency = computed(() => {
    if (!props.isSubtask) return [];

    const taskId = Number(props.task?.id || 0);
    const depIds = new Set((props.task.dependencies || []).map(d => Number(d.id)));
    const dntIds = new Set((props.task.dependents || []).map(d => Number(d.id)));

    return dependencyCandidateSubtasks.value.filter((subtask) => {
        const candidateId = Number(subtask?.id || 0);
        if (!candidateId || candidateId === taskId) return false;

        return !depIds.has(candidateId) && !dntIds.has(candidateId);
    });
});

// ===== Progress =====
const updateProgress = (value) => {
    router.patch(getUpdateRoute(), { progress: value }, {
        preserveScroll: true, preserveState: true,
        onSuccess: () => router.reload({ only: ['task', 'tasksByStatus'] }),
    });
};

const depFetch = (method, body) => {
    const routeName = method === 'DELETE' ? 'tasks.cpm.dependencies.remove' : 'tasks.cpm.dependencies.add';
    dependencyLoading.value = true;
    fetch(route(routeName, [props.workspace.id, props.space.id, props.list.id, props.parentTask.id]), {
        method,
        headers: {
            'Content-Type': 'application/json', 'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify(body),
    }).then(r => r.json()).then(result => {
        if (result.success) { emit('updated'); window.showSnackbar?.(method === 'DELETE' ? 'Dependency removed!' : 'Dependency added!', 'success'); }
        else window.showSnackbar?.(result.message || 'Failed', 'error');
    }).catch(() => window.showSnackbar?.('Request failed', 'error'))
        .finally(() => { dependencyLoading.value = false; });
};

const addDependency = (dep) => depFetch('POST', { subtask_id: props.task.id, depends_on_id: dep.id, type: 'blocks' });
const addSuccessor = (suc) => depFetch('POST', { subtask_id: suc.id, depends_on_id: props.task.id, type: 'blocks' });
const removeDependency = (dep) => depFetch('DELETE', { subtask_id: props.task.id, depends_on_id: dep.id });
const removeSuccessor = (suc) => depFetch('DELETE', { subtask_id: suc.id, depends_on_id: props.task.id });
</script>

<template>
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
                    <v-menu :disabled="!canOperateTasks">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" :color="currentPriority?.color || 'grey'" variant="tonal"
                                size="small" class="text-none">
                                <v-icon start size="14">mdi-flag{{ currentPriority ? '' : '-outline' }}</v-icon>
                                {{ currentPriority?.name || 'None' }}
                            </v-btn>
                        </template>
                        <v-card color="surface" min-width="160">
                            <v-list density="compact">
                                <v-list-item v-for="priority in PRIORITIES" :key="priority.level"
                                    :active="priority.level === localTask.priority_level"
                                    @click="changePriority(priority.level)">
                                    <template v-slot:prepend>
                                        <v-icon :color="priority.color" size="16" class="mr-2">mdi-flag</v-icon>
                                    </template>
                                    <v-list-item-title>{{ priority.name }}</v-list-item-title>
                                </v-list-item>
                                <v-divider v-if="localTask.priority_level" />
                                <v-list-item v-if="localTask.priority_level" prepend-icon="mdi-close" title="Clear"
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
                        <v-tooltip v-for="assignee in displayedAssignees" :key="assignee.id" location="top">
                            <template v-slot:activator="{ props: tooltipProps }">
                                <v-avatar v-bind="tooltipProps" :color="assignee.avatar_color || 'primary'" size="28"
                                    class="elevation-1" :class="{ 'cursor-pointer': isSubtask && canOperateTasks }"
                                    @click="isSubtask && canOperateTasks ? toggleAssignee(assignee.id) : null">
                                    <span class="text-xs font-weight-medium">{{ assignee.initials }}</span>
                                </v-avatar>
                            </template>
                            <span>{{ assignee.name }}{{ isSubtask && canOperateTasks ? ' (click to remove)' : ''
                                }}</span>
                        </v-tooltip>

                        <span v-if="!displayedAssignees.length" class="text-caption text-grey">
                            No assignees yet
                        </span>
                        <v-menu v-if="isSubtask && canOperateTasks && !localTask.assignees?.length">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" icon variant="tonal" size="x-small" color="grey">
                                    <v-icon size="14">mdi-plus</v-icon>
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="240">
                                <v-list density="compact">
                                    <v-list-item v-for="member in members" :key="member.id"
                                        :active="localTask.assignees?.some(a => a.id === member.id)"
                                        @click="toggleAssignee(member.id)">
                                        <template v-slot:prepend>
                                            <v-avatar :color="member.avatar_color || 'primary'" size="24" class="mr-2">
                                                <span class="text-[10px]">{{ member.initials }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ member.name }}</v-list-item-title>
                                        <template v-slot:append>
                                            <v-icon v-if="localTask.assignees?.some(a => a.id === member.id)"
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
                        <v-chip v-for="label in localTask.labels" :key="label.id" :color="label.color" size="small"
                            variant="flat" :closable="canOperateTasks" @click:close="removeLabel(label)">
                            {{ label.name }}
                        </v-chip>
                        <v-menu v-if="canOperateTasks" :close-on-content-click="false">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" icon variant="tonal" size="x-small" color="grey">
                                    <v-icon size="14">mdi-plus</v-icon>
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="260">
                                <v-list density="compact" max-height="260" class="overflow-auto">
                                    <v-list-item v-for="label in workspaceLabels" :key="label.id"
                                        @click="toggleLabel(label)">
                                        <template v-slot:prepend>
                                            <div class="w-3 h-3 rounded-full mr-2"
                                                :style="{ backgroundColor: label.color }" />
                                        </template>
                                        <v-list-item-title class="text-body-2">{{ label.name }}</v-list-item-title>
                                        <template #append>
                                            <div class="d-flex align-center ga-1">
                                                <v-icon v-if="isLabelSelected(label.id)" size="16" color="primary">
                                                    mdi-check
                                                </v-icon>
                                                <v-btn v-if="canManageTaskStructure" icon variant="text" size="x-small"
                                                    @click.stop="openEditLabelEditor(label)">
                                                    <v-icon size="14">mdi-pencil-outline</v-icon>
                                                </v-btn>
                                            </div>
                                        </template>
                                    </v-list-item>

                                    <v-list-item v-if="!workspaceLabels.length" disabled>
                                        <v-list-item-title class="text-body-2 text-grey">No labels
                                            yet</v-list-item-title>
                                    </v-list-item>
                                </v-list>

                                <v-divider />

                                <div v-if="canManageTaskStructure" class="pa-2 d-flex justify-end">
                                    <v-btn size="small" variant="tonal" color="primary" @click="openCreateLabelEditor">
                                        <v-icon start size="14">mdi-plus</v-icon>
                                        Create Label
                                    </v-btn>
                                </div>
                            </v-card>
                        </v-menu>
                    </div>
                </div>
            </div>

            <!-- Subtask-only: Sprint -->
            <template v-if="isSubtask">
                <v-divider class="my-1" />

                <!-- Sprint Inclusion -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-calendar-clock-outline</v-icon>
                        Sprint
                    </div>
                    <div class="prop-value">
                        <v-menu :disabled="!canManageTaskStructure">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="currentTaskSprint ? (isSprintActive(currentTaskSprint) ? 'success' : 'primary') : 'grey'">
                                    <v-icon start size="14">
                                        {{ currentTaskSprint ? 'mdi-check-circle-outline' : 'mdi-tray-arrow-down' }}
                                    </v-icon>
                                    {{ sprintDisplayName(localTask) }}
                                    <v-icon end size="14">mdi-chevron-down</v-icon>
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="260">
                                <v-list density="compact">
                                    <v-list-item :active="!localTask.sprint_id" @click="changeSprint(null)">
                                        <template v-slot:prepend>
                                            <v-icon size="16" color="grey">mdi-tray-arrow-down</v-icon>
                                        </template>
                                        <v-list-item-title>Backlog (Not in sprint)</v-list-item-title>
                                        <template v-slot:append>
                                            <v-icon v-if="!localTask.sprint_id" size="16"
                                                color="primary">mdi-check</v-icon>
                                        </template>
                                    </v-list-item>
                                    <v-divider v-if="sprints.length" />
                                    <v-list-item v-for="sprint in sprints" :key="sprint.id"
                                        :active="sprint.id === localTask.sprint_id" @click="changeSprint(sprint.id)">
                                        <template v-slot:prepend>
                                            <v-icon size="16" :color="isSprintActive(sprint) ? 'success' : 'primary'">
                                                {{ isSprintActive(sprint) ? 'mdi-rocket-launch-outline' :
                                                    'mdi-calendar-clock-outline' }}
                                            </v-icon>
                                        </template>
                                        <v-list-item-title>{{ sprint.name }}</v-list-item-title>
                                        <template v-slot:append>
                                            <v-icon v-if="sprint.id === localTask.sprint_id" size="16"
                                                color="primary">mdi-check</v-icon>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-menu>
                    </div>
                </div>
            </template>

            <!-- Start Date (tasks & subtasks) -->
            <v-divider class="my-1" />

                <!-- Start Date -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-calendar-start</v-icon>
                        Start Date
                    </div>
                    <div class="prop-value">
                        <v-menu v-model="showStartDatePicker" :close-on-content-click="false"
                            :disabled="!canOperateTasks">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="localTask.start_date ? 'primary' : 'grey'">
                                    {{ formatDate(localTask.start_date) }}
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="280">
                                <v-card-text class="pb-0">
                                    <v-text-field v-model="tempStartDate" type="date" label="Start Date"
                                        variant="outlined" density="compact" hide-details />
                                </v-card-text>
                                <v-card-actions>
                                    <v-btn v-if="localTask.start_date" size="small" variant="text" color="error"
                                        @click="tempStartDate = null; updateStartDate();">Clear</v-btn>
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
                        <v-menu v-model="showDueDatePicker" :close-on-content-click="false"
                            :disabled="!canOperateTasks">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="localTask.due_date ? 'primary' : 'grey'">
                                    {{ formatDate(localTask.due_date) }}
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="280">
                                <v-card-text class="pb-0">
                                    <v-text-field v-model="tempDueDate" type="date" label="Due Date" variant="outlined"
                                        density="compact" hide-details />
                                </v-card-text>
                                <v-card-actions>
                                    <v-btn v-if="localTask.due_date" size="small" variant="text" color="error"
                                        @click="tempDueDate = null; updateDueDate();">Clear</v-btn>
                                    <v-spacer />
                                    <v-btn size="small" variant="text" @click="showDueDatePicker = false">Cancel</v-btn>
                                    <v-btn size="small" color="primary" variant="flat"
                                        @click="updateDueDate">Save</v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-menu>
                    </div>
                </div>

            <!-- Subtask-only: Automation -->
            <template v-if="isSubtask">
                <v-divider class="my-1" />

                <!-- Completion Automation -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-lightning-bolt</v-icon>
                        Automation
                    </div>
                    <div class="prop-value">
                        <v-menu location="bottom start" :disabled="!canManageTaskStructure">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="completionTargetStatusIdState ? 'primary' : 'grey'">
                                    <span class="automation-preview-dot"
                                        :style="{ backgroundColor: completionTargetStatus?.color || '#64748b' }" />
                                    {{ completionTargetStatusName }}
                                    <v-icon end size="14">mdi-chevron-down</v-icon>
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="280">
                                <v-list density="compact">
                                    <v-list-item :active="!completionTargetStatusIdState"
                                        @click="setCompletionAutomation(null)">
                                        <template v-slot:prepend>
                                            <span class="automation-option-dot automation-option-dot--off" />
                                        </template>
                                        <v-list-item-title>Off</v-list-item-title>
                                        <template v-slot:append>
                                            <v-icon v-if="!completionTargetStatusIdState" size="16"
                                                color="primary">mdi-check</v-icon>
                                        </template>
                                    </v-list-item>
                                    <v-list-item v-for="status in completionStatusOptions" :key="status.id"
                                        :active="status.id === completionTargetStatusIdState"
                                        @click="setCompletionAutomation(status.id)">
                                        <template v-slot:prepend>
                                            <span class="automation-option-dot"
                                                :style="{ backgroundColor: status.color }" />
                                        </template>
                                        <v-list-item-title>{{ status.name }}</v-list-item-title>
                                        <template v-slot:append>
                                            <v-icon v-if="status.id === completionTargetStatusIdState" size="16"
                                                color="primary">mdi-check</v-icon>
                                        </template>
                                    </v-list-item>
                                </v-list>
                            </v-card>
                        </v-menu>
                    </div>
                </div>
            </template>

            <!-- Time Estimate (tasks & subtasks) -->
            <v-divider class="my-1" />

                <!-- Time Estimate -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-timer-sand</v-icon>
                        Estimate
                    </div>
                    <div class="prop-value">
                        <v-menu v-model="showTimeEstimatePicker" :close-on-content-click="false"
                            :disabled="!canOperateTasks">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="localTask.time_estimate ? 'primary' : 'grey'">
                                    {{ formatTimeEstimate(localTask.time_estimate) }}
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="280">
                                <v-card-text class="pb-0">
                                    <v-text-field v-model="tempTimeEstimate" type="number" label="Estimate (hours)"
                                        variant="outlined" density="compact" hide-details step="0.1" min="0" />
                                </v-card-text>
                                <v-card-actions>
                                    <v-btn v-if="localTask.time_estimate" size="small" variant="text" color="error"
                                        @click="tempTimeEstimate = 0; updateTimeEstimate();">Clear</v-btn>
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

            <!-- Subtask-only: PERT, Baseline, Tracker, Progress -->
            <template v-if="isSubtask">
                <v-divider class="my-1" />

                <!-- PERT -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-function-variant</v-icon>
                        PERT
                    </div>
                    <div class="prop-value">
                        <v-menu v-model="showPertPicker" :close-on-content-click="false" :disabled="!canOperateTasks">
                            <template v-slot:activator="{ props: menuProps }">
                                <v-btn v-bind="menuProps" variant="text" size="small" class="text-none"
                                    :color="localTask.pert_expected_estimate ? 'primary' : 'grey'">
                                    {{ formatTimeEstimate(localTask.pert_expected_estimate) }}
                                </v-btn>
                            </template>
                            <v-card color="surface" min-width="320">
                                <v-card-text class="pb-0 d-flex flex-column ga-3">
                                    <v-text-field v-model="tempOptimistic" type="number" label="Optimistic (hours)"
                                        variant="outlined" density="compact" hide-details step="0.5" min="0" />
                                    <v-text-field v-model="tempMostLikely" type="number" label="Most likely (hours)"
                                        variant="outlined" density="compact" hide-details step="0.5" min="0" />
                                    <v-text-field v-model="tempPessimistic" type="number" label="Pessimistic (hours)"
                                        variant="outlined" density="compact" hide-details step="0.5" min="0" />
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer />
                                    <v-btn size="small" variant="text" @click="showPertPicker = false">Cancel</v-btn>
                                    <v-btn size="small" color="primary" variant="flat"
                                        @click="updatePertEstimates">Save</v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-menu>
                    </div>
                </div>

                <v-divider class="my-1" />

                <!-- Baseline -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-source-branch</v-icon>
                        Baseline
                    </div>
                    <div class="prop-value d-flex align-center ga-2 flex-wrap">
                        <span class="text-body-2">
                            {{ localTask.baseline_start_date || localTask.baseline_due_date
                                ? `${formatDate(localTask.baseline_start_date)} →
                            ${formatDate(localTask.baseline_due_date)}`
                                : 'Not set' }}
                        </span>
                        <v-chip
                            v-if="localTask.schedule_variance_minutes !== null && localTask.schedule_variance_minutes !== 0"
                            size="x-small" :color="localTask.schedule_variance_minutes > 0 ? 'warning' : 'success'"
                            variant="tonal">
                            {{ formatVariance(localTask.schedule_variance_minutes) }} variance
                        </v-chip>
                        <v-btn v-if="canManageTaskStructure" size="x-small" variant="tonal" color="primary"
                            @click="setBaselineFromCurrent">
                            Set from current dates
                        </v-btn>
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
                            <code class="timer-display" :class="{ 'timer-display--active': isTracking }">
                {{ formatTrackingDuration }}
            </code>
                            <v-btn v-if="!isTracking && canOperateTasks" icon="mdi-play" color="success" variant="tonal"
                                size="x-small" :loading="isTimerLoading" @click="$emit('start-tracking')" />
                            <v-btn v-else-if="isTracking" icon="mdi-stop" color="error" variant="tonal" size="x-small"
                                :loading="isTimerLoading" @click="$emit('stop-tracking')" />
                        </div>
                    </div>
                </div>

            </template>

            <!-- Time Spent (tasks & subtasks) -->
            <v-divider class="my-1" />

                <!-- Time Spent -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-chart-timeline-variant</v-icon>
                        Spent
                    </div>
                    <div class="prop-value">
                        <div class="spent-progress-wrap">
                            <div class="spent-metrics-row">
                                <span class="text-body-2">
                                    {{ formatDuration((localTask.time_spent || 0) * 60) }}
                                </span>
                                <span v-if="localTask.time_estimate" class="spent-percent"
                                    :class="{ 'spent-percent--over': (localTask.time_spent || 0) > localTask.time_estimate }">
                                    {{ getSpentPercentage(localTask.time_spent, localTask.time_estimate) }}%
                                </span>
                            </div>
                            <v-progress-linear v-if="localTask.time_estimate"
                                :model-value="(localTask.time_spent / localTask.time_estimate) * 100"
                                :color="localTask.time_spent > localTask.time_estimate ? 'error' : 'primary'" height="4"
                                rounded class="spent-progress-bar mt-1" />
                        </div>
                    </div>
                </div>

            <!-- Subtask-only: Progress -->
            <template v-if="isSubtask">
                <v-divider class="my-1" />

                <!-- Progress (% Complete for EVM) -->
                <div class="prop-row">
                    <div class="prop-label">
                        <v-icon size="16" class="prop-icon">mdi-percent</v-icon>
                        Progress
                    </div>
                    <div class="prop-value d-flex align-center ga-3">
                        <v-slider :model-value="localTask.progress ?? 0" min="0" max="100" step="1" density="compact"
                            hide-details thumb-label class="progress-slider" :disabled="!canOperateTasks"
                            :color="(localTask.progress ?? 0) >= 100 ? 'success' : 'primary'" @end="updateProgress" />
                        <span class="text-body-2">{{ localTask.progress ?? 0 }}%</span>
                    </div>
                </div>

            </template>

            <!-- Subtask-only: Dependencies -->
            <template v-if="isSubtask">
                <v-divider class="my-1" />

                <!-- Waiting On -->
                <div class="prop-row prop-row--top">
                    <div class="prop-label prop-label--top">
                        <v-icon size="16" class="prop-icon">mdi-clock-outline</v-icon>
                        Waiting On
                    </div>
                    <div class="prop-value">
                        <div class="d-flex flex-wrap ga-1 align-center">
                            <v-chip v-for="dep in (localTask?.dependencies || [])" :key="dep.id" size="small"
                                color="warning" variant="tonal" :closable="canOperateTasks"
                                :disabled="dependencyLoading" @click:close="removeDependency(dep)">
                                <v-icon start size="12">mdi-clock-outline</v-icon>
                                {{ dep.name }}
                            </v-chip>
                            <v-menu v-if="canOperateTasks" :close-on-content-click="false">
                                <template v-slot:activator="{ props: menuProps }">
                                    <v-btn v-bind="menuProps" icon variant="tonal" size="x-small" color="grey"
                                        :loading="dependencyLoading">
                                        <v-icon size="14">mdi-plus</v-icon>
                                    </v-btn>
                                </template>
                                <v-card color="surface" min-width="260">
                                    <v-list density="compact" max-height="200" class="overflow-auto">
                                        <v-list-item v-for="s in availableForDependency" :key="s.id"
                                            :disabled="dependencyLoading" @click="addDependency(s)">
                                            <template #prepend>
                                                <v-icon size="14">mdi-subtitles-outline</v-icon>
                                            </template>
                                            <v-list-item-title class="text-body-2">{{ s.name }}</v-list-item-title>
                                            <v-list-item-subtitle v-if="s.time_estimate" class="text-caption">
                                                Est: {{ formatSubtaskEstimate(s.time_estimate) }}
                                            </v-list-item-subtitle>
                                        </v-list-item>
                                        <v-list-item v-if="availableForDependency.length === 0" disabled>
                                            <v-list-item-title class="text-body-2 text-grey">
                                                No available subtasks
                                            </v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-card>
                            </v-menu>
                        </div>
                        <div v-if="!(localTask?.dependencies || []).length" class="text-caption text-grey mt-1">
                            No waiting-on dependencies
                        </div>
                    </div>
                </div>

                <v-divider class="my-1" />

                <!-- Blocking -->
                <div class="prop-row prop-row--top">
                    <div class="prop-label prop-label--top">
                        <v-icon size="16" class="prop-icon">mdi-hand-back-left</v-icon>
                        Blocking
                    </div>
                    <div class="prop-value">
                        <div class="d-flex flex-wrap ga-1 align-center">
                            <v-chip v-for="dep in (localTask?.dependents || [])" :key="'s-' + dep.id" size="small"
                                color="error" variant="tonal" :closable="canOperateTasks" :disabled="dependencyLoading"
                                @click:close="removeSuccessor(dep)">
                                <v-icon start size="12">mdi-hand-back-left</v-icon>
                                {{ dep.name }}
                            </v-chip>
                            <v-menu v-if="canOperateTasks" :close-on-content-click="false">
                                <template v-slot:activator="{ props: menuProps }">
                                    <v-btn v-bind="menuProps" icon variant="tonal" size="x-small" color="grey"
                                        :loading="dependencyLoading">
                                        <v-icon size="14">mdi-plus</v-icon>
                                    </v-btn>
                                </template>
                                <v-card color="surface" min-width="260">
                                    <v-list density="compact" max-height="200" class="overflow-auto">
                                        <v-list-item v-for="s in availableForDependency" :key="s.id"
                                            :disabled="dependencyLoading" @click="addSuccessor(s)">
                                            <template #prepend>
                                                <v-icon size="14">mdi-subtitles-outline</v-icon>
                                            </template>
                                            <v-list-item-title class="text-body-2">{{ s.name }}</v-list-item-title>
                                            <v-list-item-subtitle v-if="s.time_estimate" class="text-caption">
                                                Est: {{ formatSubtaskEstimate(s.time_estimate) }}
                                            </v-list-item-subtitle>
                                        </v-list-item>
                                        <v-list-item v-if="availableForDependency.length === 0" disabled>
                                            <v-list-item-title class="text-body-2 text-grey">
                                                No available subtasks
                                            </v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-card>
                            </v-menu>
                        </div>
                        <div v-if="!(localTask?.dependents || []).length" class="text-caption text-grey mt-1">
                            Not blocking any subtask
                        </div>
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
                    <v-chip v-if="localTask.subtasks?.length" size="x-small" variant="tonal" color="primary">
                        {{localTask.subtasks.filter(s => s.completed_at).length}}/{{ localTask.subtasks.length }}
                    </v-chip>
                </div>
                <v-btn variant="tonal" size="x-small" color="primary" @click="$emit('view-subtasks', localTask)">
                    <v-icon start size="14">mdi-view-dashboard-outline</v-icon>
                    Board
                </v-btn>
            </div>
            <v-divider />
            <div v-if="localTask.subtasks?.length" class="subtask-checklist">
                <div v-for="sub in localTask.subtasks" :key="sub.id" class="subtask-check-item"
                    @click="canOperateTasks && toggleSubtask(sub)">
                    <v-icon :color="sub.completed_at ? 'success' : '#555'" size="18">
                        {{ sub.completed_at ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline' }}
                    </v-icon>
                    <span class="subtask-check-name" :class="{ 'subtask-check-done': sub.completed_at }">
                        {{ sub.name }}
                    </span>
                    <v-chip size="x-small" variant="tonal" class="subtask-sprint-chip ml-auto" @click.stop
                        :color="resolveTaskSprint(sub) ? (isSprintActive(resolveTaskSprint(sub)) ? 'success' : 'primary') : 'grey'">
                        <v-icon start size="12">mdi-calendar-clock-outline</v-icon>
                        {{ sprintDisplayName(sub) }}
                    </v-chip>
                    <v-icon v-if="sub.priority" :color="sub.priority.level <= 2 ? 'warning' : 'grey'" size="12"
                        class="flex-shrink-0">mdi-flag</v-icon>
                </div>
            </div>
            <div v-else class="pa-4 text-center text-body-2 text-grey">No subtasks yet</div>
        </div>

        <!-- Description Section -->
        <div class="section-card mt-4">
            <div class="d-flex align-center ga-2 pa-3 pb-2">
                <v-icon size="18" color="grey">mdi-text</v-icon>
                <span class="text-body-2 font-weight-medium">Description</span>
            </div>
            <div class="px-3 pb-3">
                <v-textarea v-model="editedDescription" placeholder="Add a description..." variant="outlined" rows="3"
                    hide-details auto-grow :readonly="!canOperateTasks" @blur="saveDescription" />
            </div>
        </div>

        <v-dialog v-if="canManageTaskStructure" v-model="showLabelEditor" max-width="420">
            <v-card>
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon :color="editingLabel ? 'warning' : 'primary'">mdi-label-outline</v-icon>
                    <span>{{ editingLabel ? 'Edit Label' : 'Create Label' }}</span>
                </v-card-title>

                <v-card-text>
                    <v-text-field v-model="labelForm.name" label="Label name" variant="outlined" density="compact"
                        autofocus class="mb-3" />

                    <ColorPicker v-model="labelForm.color" />
                </v-card-text>

                <v-card-actions>
                    <v-btn v-if="editingLabel" variant="text" color="error" @click="deleteWorkspaceLabel">
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
    </div>
</template>

<style scoped>
.section-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    overflow: hidden;
}

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

.automation-preview-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-block;
    margin-right: 8px;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25);
}

.automation-option-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    display: inline-block;
    margin-right: 12px;
}

.automation-option-dot--off {
    background-color: #64748b;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.2);
}

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

.spent-metrics-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.spent-progress-wrap {
    width: min(220px, 100%);
}

.spent-progress-bar {
    width: 100%;
}

.spent-percent {
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    min-width: 32px;
    text-align: right;
    color: rgba(255, 255, 255, 0.6);
}

.spent-percent--over {
    color: #ef5350;
}

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

.subtask-sprint-chip {
    max-width: 180px;
    font-weight: 600;
}

.subtask-sprint-chip :deep(.v-chip__content) {
    display: inline-flex;
    align-items: center;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.prop-row--top {
    align-items: flex-start;
    padding-top: 10px;
    padding-bottom: 10px;
}

.prop-label--top {
    padding-top: 2px;
}

.progress-slider {
    flex: 1;
    min-width: 80px;
    max-width: 160px;
}
</style>
