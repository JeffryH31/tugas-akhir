<script setup>
/**
 * Task Detail Panel - Slide-over panel for task details
 */
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: Boolean,
    task: Object,
    workspace: Object,
    space: Object,
    list: Object,
    parentTask: Object, // If present, we're viewing a subtask
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

// Watch task changes
watch(() => props.task, (newTask) => {
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
const isCompleted = computed(() => props.task?.completed_at);

// Priority config
const priorityConfig = {
    1: { color: 'error', icon: 'mdi-flag', label: 'Urgent' },
    2: { color: 'warning', icon: 'mdi-flag', label: 'High' },
    3: { color: 'info', icon: 'mdi-flag', label: 'Normal' },
    4: { color: 'grey', icon: 'mdi-flag-outline', label: 'Low' },
};

// Get current priority
const currentPriority = computed(() => {
    if (!props.task?.priority) return null;
    return priorityConfig[props.task.priority.level] || null;
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
    if (subtask.completed_at) {
        router.post(
            route('tasks.reopen', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['task', 'tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Subtask reopened!', 'success');
                    }
                }
            }
        );
    } else {
        router.post(
            route('tasks.complete', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['task', 'tasksByStatus'] });
                    if (window.showSnackbar) {
                        window.showSnackbar('Subtask completed!', 'success');
                    }
                }
            }
        );
    }
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
    const oldValue = props.task.start_date;
    props.task.start_date = tempStartDate.value;
    showStartDatePicker.value = false;

    router.patch(
        getUpdateRoute(),
        { start_date: tempStartDate.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Start date updated!', 'success');
                }
            },
            onError: () => {
                props.task.start_date = oldValue;
            }
        }
    );
};

const openStartDatePicker = () => {
    tempStartDate.value = props.task.start_date;
    showStartDatePicker.value = true;
};

const updateDueDate = () => {
    const oldValue = props.task.due_date;
    props.task.due_date = tempDueDate.value;
    showDueDatePicker.value = false;

    router.patch(
        getUpdateRoute(),
        { due_date: tempDueDate.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Due date updated!', 'success');
                }
            },
            onError: () => {
                props.task.due_date = oldValue;
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
    const totalMinutes = (parseFloat(tempTimeEstimate.value) || 0) * 60;
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

// Time tracking
const isTracking = ref(false);
const isPaused = ref(false);
const trackingDuration = ref(0);
const trackingInterval = ref(null);

const startTracking = () => {
    isTracking.value = true;
    isPaused.value = false;
    trackingDuration.value = 0;

    trackingInterval.value = setInterval(() => {
        trackingDuration.value += 1;
    }, 1000);
};

const pauseTracking = () => {
    isPaused.value = true;
    if (trackingInterval.value) {
        clearInterval(trackingInterval.value);
        trackingInterval.value = null;
    }
};

const resumeTracking = () => {
    isPaused.value = false;
    trackingInterval.value = setInterval(() => {
        trackingDuration.value += 1;
    }, 1000);
};

const stopTracking = () => {
    if (trackingInterval.value) {
        clearInterval(trackingInterval.value);
        trackingInterval.value = null;
    }

    const durationInMinutes = Math.round(trackingDuration.value / 60);

    if (durationInMinutes > 0) {
        // Optimistically update time_spent
        const oldTimeSpent = props.task.time_spent || 0;
        props.task.time_spent = oldTimeSpent + durationInMinutes;

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
                description: 'Tracked time'
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    // Update task data from response
                    if (page.props.task) {
                        Object.assign(props.task, page.props.task);
                    }
                    if (window.showSnackbar) {
                        window.showSnackbar(`Time entry saved: ${formatDuration(trackingDuration.value)}`, 'success');
                    }
                },
                onError: (errors) => {
                    // Revert on error
                    props.task.time_spent = oldTimeSpent;
                    console.error('Failed to save time entry:', errors);
                    if (window.showSnackbar) {
                        window.showSnackbar('Failed to save time entry', 'error');
                    }
                }
            }
        );
    }

    // Reset tracker state
    isTracking.value = false;
    isPaused.value = false;
    trackingDuration.value = 0;
};

const formatTrackingDuration = computed(() => {
    const hours = Math.floor(trackingDuration.value / 3600);
    const minutes = Math.floor((trackingDuration.value % 3600) / 60);
    const seconds = trackingDuration.value % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

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

// Complete task
const toggleComplete = () => {
    if (isCompleted.value) {
        router.post(
            route('tasks.reopen', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task reopened!', 'success');
                    }
                    router.reload({ only: ['task', 'tasksByStatus'] });
                }
            }
        );
    } else {
        router.post(
            route('tasks.complete', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task completed!', 'success');
                    }
                    router.reload({ only: ['task', 'tasksByStatus'] });
                }
            }
        );
    }
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

    console.log('=== Submitting Comment ===');
    console.log('Task ID:', props.task?.id);
    console.log('Content:', newComment.value);

    // Get the correct task ID for comments (always use main task, not subtask)
    const taskId = isSubtask.value ? props.parentTask.id : props.task.id;

    console.log('Using Task ID for comment:', taskId);
    console.log('Route params:', {
        workspace: props.workspace?.id,
        space: props.space?.id,
        list: props.list?.id,
        task: taskId
    });

    isSubmittingComment.value = true;

    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, taskId]),
        { content: newComment.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Comment added successfully!');
                newComment.value = '';
                if (window.showSnackbar) {
                    window.showSnackbar('Comment added!', 'success');
                }
                // Emit updated event to refresh task data
                emit('updated');
            },
            onError: (errors) => {
                console.error('Failed to add comment:', errors);
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

    const durationInMinutes = parseFloat(newTimeEntry.value.duration) * 60;

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
        <div v-if="task" class="flex flex-col h-full">
            <!-- Header -->
            <div class="panel-header">
                <div class="flex items-center gap-2">
                    <!-- Complete Button -->
                    <v-btn :icon="isCompleted ? 'mdi-checkbox-marked-circle' : 'mdi-checkbox-blank-circle-outline'"
                        :color="isCompleted ? 'success' : 'grey'" variant="text" size="small" @click="toggleComplete" />

                    <!-- Status -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-chip v-bind="menuProps" :color="task.status?.color" size="small" variant="tonal" label>
                                {{ task.status?.name || 'No Status' }}
                                <v-icon end size="14">mdi-chevron-down</v-icon>
                            </v-chip>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item v-for="status in statuses" :key="status.id"
                                    :active="status.id === task.status_id" @click="changeStatus(status.id)">
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
                    {{ task.name }}
                </div>
                <v-text-field v-else v-model="editedName" variant="outlined" density="compact" hide-details autofocus
                    @blur="saveName" @keydown.enter="saveName" @keydown.escape="isEditing = false" />
            </div>

            <!-- Tabs -->
            <v-tabs v-model="activeTab" color="primary" class="flex-shrink-0">
                <v-tab value="details">Details</v-tab>
                <v-tab value="comments">
                    Comments
                    <v-badge v-if="task.comments_count" :content="task.comments_count" color="grey" inline
                        class="ml-1" />
                </v-tab>
                <v-tab v-if="isSubtask" value="time">
                    Time Tracking
                    <v-badge v-if="task.time_entries?.length" :content="task.time_entries.length" color="grey" inline
                        class="ml-1" />
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
                                                {{ task.priority?.name || 'No Priority' }}
                                            </v-btn>
                                        </template>
                                        <v-list density="compact" color="surface">
                                            <v-list-item v-for="priority in priorities" :key="priority.id"
                                                :active="priority.id === task.priority_id"
                                                @click="changePriority(priority.id)">
                                                <template v-slot:prepend>
                                                    <v-icon :color="priorityConfig[priority.level]?.color" size="18"
                                                        class="mr-2">
                                                        {{ priorityConfig[priority.level]?.icon }}
                                                    </v-icon>
                                                </template>
                                                <v-list-item-title>{{ priority.name }}</v-list-item-title>
                                            </v-list-item>
                                            <v-divider v-if="task.priority_id" />
                                            <v-list-item v-if="task.priority_id" prepend-icon="mdi-close"
                                                title="Clear Priority" @click="changePriority(null)" />
                                        </v-list>
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
                                        <div v-if="task.assignees?.length" class="flex -space-x-2">
                                            <v-tooltip v-for="assignee in task.assignees" :key="assignee.id"
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
                                                            :active="task.assignees?.some(a => a.id === member.id)"
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
                                                                    v-if="task.assignees?.some(a => a.id === member.id)"
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
                                                :color="task.start_date ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-calendar-start</v-icon>
                                                {{ formatDate(task.start_date) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempStartDate" type="date" label="Start Date"
                                                    variant="outlined" density="compact" hide-details
                                                    bg-color="#1e1e1e" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="task.start_date" size="small" variant="text"
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
                                                :color="task.due_date ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-calendar</v-icon>
                                                {{ formatDate(task.due_date) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempDueDate" type="date" label="Due Date"
                                                    variant="outlined" density="compact" hide-details
                                                    bg-color="#1e1e1e" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="task.due_date" size="small" variant="text"
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
                                                :color="task.time_estimate ? 'primary' : 'default'">
                                                <v-icon start size="16">mdi-timer-outline</v-icon>
                                                {{ formatTimeEstimate(task.time_estimate) }}
                                            </v-btn>
                                        </template>
                                        <v-card color="surface" min-width="300">
                                            <v-card-text>
                                                <v-text-field v-model="tempTimeEstimate" type="number"
                                                    label="Time Estimate (hours)" variant="outlined" density="compact"
                                                    hide-details bg-color="#1e1e1e" step="0.5" min="0" />
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn v-if="task.time_estimate" size="small" variant="text"
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
                                        <v-chip variant="text" size="small" class="font-mono">
                                            <v-icon start size="16">mdi-clock</v-icon>
                                            {{ formatTrackingDuration }}
                                        </v-chip>
                                        <div class="flex gap-1">
                                            <v-btn v-if="!isTracking" icon="mdi-play" color="success" variant="tonal"
                                                size="small" @click="startTracking" />
                                            <template v-else>
                                                <v-btn v-if="!isPaused" icon="mdi-pause" color="warning" variant="tonal"
                                                    size="small" @click="pauseTracking" />
                                                <v-btn v-else icon="mdi-play" color="success" variant="tonal"
                                                    size="small" @click="resumeTracking" />
                                                <v-btn icon="mdi-stop" color="error" variant="tonal" size="small"
                                                    @click="stopTracking" />
                                            </template>
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
                                        {{ formatDuration((task.time_spent || 0) * 60) }}
                                    </v-chip>
                                    <v-progress-linear v-if="task.time_estimate && task.time_spent"
                                        :model-value="(task.time_spent / task.time_estimate) * 100"
                                        :color="task.time_spent > task.time_estimate ? 'error' : 'primary'" height="4"
                                        class="mt-2" />
                                </div>
                            </div>

                            <!-- Subtasks (Tasks only) -->
                            <div v-if="!isSubtask" class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-file-tree-outline</v-icon>
                                    Subtasks
                                </div>
                                <div class="detail-value">
                                    <v-btn variant="tonal" size="small" @click="emit('view-subtasks', task)">
                                        <v-icon start size="16">mdi-view-dashboard-outline</v-icon>
                                        View Subtasks
                                        <v-badge v-if="task.subtasks_count" :content="task.subtasks_count"
                                            color="primary" inline class="ml-2" />
                                    </v-btn>
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
                            <div v-if="!task.comments?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-comment-outline</v-icon>
                                <div>No comments yet</div>
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="comment in task.comments" :key="comment.id" class="flex gap-3">
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
                                                {{ formatTimeEstimate(task.time_estimate) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-gray-400 mb-1">Time Spent</div>
                                            <div class="text-lg font-semibold"
                                                :class="task.time_spent > task.time_estimate ? 'text-error' : ''">
                                                {{ formatDuration((task.time_spent || 0) * 60) }}
                                            </div>
                                        </div>
                                    </div>
                                    <v-progress-linear v-if="task.time_estimate"
                                        :model-value="((task.time_spent || 0) / task.time_estimate) * 100"
                                        :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'"
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
                                            min="0" hide-details />
                                        <v-textarea v-model="newTimeEntry.description" label="Description (optional)"
                                            variant="outlined" density="compact" rows="2" hide-details />
                                        <v-btn color="primary" size="small" block :disabled="!newTimeEntry.duration"
                                            @click="addTimeEntry">
                                            <v-icon start>mdi-plus</v-icon>
                                            Add Time Entry
                                        </v-btn>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <!-- Time Entries List -->
                            <div v-if="!task.time_entries?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-clock-outline</v-icon>
                                <div>No time entries yet</div>
                            </div>
                            <div v-else class="space-y-2">
                                <v-card v-for="entry in task.time_entries" :key="entry.id" variant="outlined">
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
                            <div v-if="!task.activities?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-history</v-icon>
                                <div>No activity yet</div>
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="activity in task.activities" :key="activity.id"
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
</style>
