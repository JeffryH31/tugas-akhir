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
});

const emit = defineEmits(['update:modelValue', 'updated']);

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

// Format duration
const formatDuration = (seconds) => {
    if (!seconds) return 'Not set';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
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
            route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            { name: editedName.value.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Task name updated!', 'success');
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
            route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            { description: editedDescription.value },
            {
                preserveScroll: true,
                onSuccess: () => {
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
        route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { status_id: statusId },
        {
            preserveScroll: true,
            onSuccess: () => {
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
        route('tasks.change-priority', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { priority_id: priorityId },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Priority changed!', 'success');
                }
            }
        }
    );
};

// Toggle assignee
const toggleAssignee = (userId) => {
    const isAssigned = props.task.assignees?.some(a => a.id === userId);

    if (isAssigned) {
        router.delete(
            route('tasks.unassign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {
                data: { user_id: userId },
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Assignee removed!', 'success');
                    }
                }
            }
        );
    } else {
        router.post(
            route('tasks.assign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            { user_id: userId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Assignee added!', 'success');
                    }
                }
            }
        );
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

const submitComment = () => {
    if (!newComment.value.trim()) return;

    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { content: newComment.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Comment added!', 'success');
                }
                newComment.value = '';
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

            <!-- Task ID -->
            <div class="px-4 py-1 text-xs text-gray-500">
                {{ task.task_id }}
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
                <v-tab value="subtasks">
                    Subtasks
                    <v-badge v-if="task.subtasks_count" :content="task.subtasks_count" color="grey" inline
                        class="ml-1" />
                </v-tab>
                <v-tab value="comments">
                    Comments
                    <v-badge v-if="task.comments_count" :content="task.comments_count" color="grey" inline
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
                            <!-- Assignees -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-account-outline</v-icon>
                                    Assignees
                                </div>
                                <div class="detail-value">
                                    <v-menu :close-on-content-click="false">
                                        <template v-slot:activator="{ props: menuProps }">
                                            <div v-bind="menuProps" class="cursor-pointer">
                                                <div v-if="task.assignees?.length"
                                                    class="flex items-center gap-1 flex-wrap">
                                                    <v-chip v-for="assignee in task.assignees" :key="assignee.id"
                                                        size="small" variant="tonal">
                                                        <v-avatar :color="assignee.avatar_color" size="20" start>
                                                            <span class="text-[10px]">{{ assignee.initials }}</span>
                                                        </v-avatar>
                                                        {{ assignee.name }}
                                                    </v-chip>
                                                </div>
                                                <span v-else class="text-gray-500">Add assignees</span>
                                            </div>
                                        </template>
                                        <v-card width="250">
                                            <v-list density="compact">
                                                <v-list-item v-for="member in members" :key="member.id"
                                                    @click="toggleAssignee(member.id)">
                                                    <template v-slot:prepend>
                                                        <v-avatar :color="member.avatar_color" size="28">
                                                            <span class="text-xs">{{ member.initials }}</span>
                                                        </v-avatar>
                                                    </template>
                                                    <v-list-item-title>{{ member.name }}</v-list-item-title>
                                                    <template v-slot:append>
                                                        <v-icon v-if="task.assignees?.some(a => a.id === member.id)"
                                                            color="success" size="18">
                                                            mdi-check
                                                        </v-icon>
                                                    </template>
                                                </v-list-item>
                                            </v-list>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </div>

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

                            <!-- Due Date -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-calendar-outline</v-icon>
                                    Due Date
                                </div>
                                <div class="detail-value">
                                    {{ formatDate(task.due_date) }}
                                </div>
                            </div>

                            <!-- Time Estimate -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-timer-outline</v-icon>
                                    Time Estimate
                                </div>
                                <div class="detail-value">
                                    {{ formatDuration(task.time_estimate) }}
                                </div>
                            </div>

                            <!-- Time Spent -->
                            <div class="detail-row">
                                <div class="detail-label">
                                    <v-icon size="18" class="mr-2">mdi-timer-check-outline</v-icon>
                                    Time Spent
                                </div>
                                <div class="detail-value">
                                    {{ formatDuration(task.time_spent) }}
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

                    <!-- Subtasks Tab -->
                    <v-tabs-window-item value="subtasks">
                        <div class="p-4">
                            <div v-if="!task.subtasks?.length" class="text-center py-8 text-gray-500">
                                <v-icon size="48" class="mb-2">mdi-checkbox-multiple-outline</v-icon>
                                <div>No subtasks yet</div>
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="subtask in task.subtasks" :key="subtask.id"
                                    class="flex items-center gap-2 p-2 rounded hover:bg-[#2d2d30]">
                                    <v-checkbox :model-value="!!subtask.completed_at" hide-details density="compact" />
                                    <span :class="{ 'line-through text-gray-500': subtask.completed_at }">
                                        {{ subtask.name }}
                                    </span>
                                </div>
                            </div>
                            <v-btn variant="text" block class="mt-4">
                                <v-icon start>mdi-plus</v-icon>
                                Add Subtask
                            </v-btn>
                        </div>
                    </v-tabs-window-item>

                    <!-- Comments Tab -->
                    <v-tabs-window-item value="comments">
                        <div class="p-4">
                            <!-- Comment Input -->
                            <div class="mb-4">
                                <v-textarea v-model="newComment" placeholder="Write a comment..." variant="outlined"
                                    rows="3" hide-details />
                                <div class="flex justify-end mt-2">
                                    <v-btn color="primary" size="small" :disabled="!newComment.trim()"
                                        @click="submitComment">
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
