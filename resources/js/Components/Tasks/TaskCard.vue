<script setup>
/**
 * Task Card Component - ClickUp Style
 */
import { computed, inject } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    task: {
        type: Object,
        required: true,
    },
    showList: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click', 'complete', 'open-detail']);

// Inject CPM critical path checker (provided by parent)
const isSubtaskCritical = inject('isSubtaskCritical', () => false);

// Check if this task/subtask is on critical path
const isCritical = computed(() => isSubtaskCritical(props.task.id));

// Priority config
const priorityConfig = {
    1: { color: 'error', icon: 'mdi-flag', label: 'Urgent' },
    2: { color: 'warning', icon: 'mdi-flag', label: 'High' },
    3: { color: 'info', icon: 'mdi-flag', label: 'Normal' },
    4: { color: 'grey', icon: 'mdi-flag-outline', label: 'Low' },
};

const priority = computed(() => {
    if (!props.task.priority) return null;
    return priorityConfig[props.task.priority.level] || priorityConfig[3];
});

// Due date formatting
const dueDate = computed(() => {
    if (!props.task.due_date) return null;
    const date = new Date(props.task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((date - now) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return { text: `${Math.abs(diffDays)}d overdue`, color: 'error', overdue: true };
    } else if (diffDays === 0) {
        return { text: 'Today', color: 'warning', overdue: false };
    } else if (diffDays === 1) {
        return { text: 'Tomorrow', color: 'info', overdue: false };
    } else if (diffDays <= 7) {
        return { text: `${diffDays}d`, color: 'grey', overdue: false };
    } else {
        return { text: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }), color: 'grey', overdue: false };
    }
});

// Is completed
const isCompleted = computed(() => !!props.task.completed_at);

// Progress for subtasks
const subtaskProgress = computed(() => {
    if (!props.task.subtasks_count) return null;
    const completed = props.task.completed_subtasks_count || 0;
    const total = props.task.subtasks_count;
    return {
        completed,
        total,
        percentage: Math.round((completed / total) * 100),
    };
});

// Aggregate assignees from subtasks
const aggregatedAssignees = computed(() => {
    if (!props.task.subtasks || props.task.subtasks.length === 0) return [];

    const assigneeMap = new Map();
    props.task.subtasks.forEach(subtask => {
        if (subtask.assignees) {
            subtask.assignees.forEach(assignee => {
                if (!assigneeMap.has(assignee.id)) {
                    assigneeMap.set(assignee.id, assignee);
                }
            });
        }
    });

    return Array.from(assigneeMap.values());
});

// Aggregate time spent from subtasks
const aggregatedTimeSpent = computed(() => {
    if (!props.task.subtasks || props.task.subtasks.length === 0) return null;

    const totalMinutes = props.task.subtasks.reduce((sum, subtask) => {
        return sum + (subtask.time_spent || 0);
    }, 0);

    if (totalMinutes === 0) return null;

    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
});

// Handle complete toggle
const toggleComplete = (e) => {
    e.stopPropagation();
    emit('complete', props.task);
};

// Open task detail
const openDetail = () => {
    emit('open-detail', props.task);
};
</script>

<template>
    <v-card class="task-card" :class="{
        'task-card--completed': isCompleted,
        'task-card--compact': compact,
        'task-card--critical': isCritical && !isCompleted,
    }" variant="outlined" rounded="lg" @click="openDetail">
        <v-card-text class="pa-3">
            <div class="flex items-center gap-2">
                <!-- Critical Path Indicator -->
                <v-tooltip v-if="isCritical && !isCompleted" location="top">
                    <template #activator="{ props: tooltipProps }">
                        <v-icon v-bind="tooltipProps" size="16" color="error" class="critical-icon">
                            mdi-alert-circle
                        </v-icon>
                    </template>
                    <span>On Critical Path - Any delay affects project deadline</span>
                </v-tooltip>

                <!-- Complete Checkbox -->
                <v-btn :icon="isCompleted ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline'"
                    :color="isCompleted ? 'success' : 'grey'" variant="text" size="x-small" density="compact"
                    @click="toggleComplete" />

                <!-- Task Title -->
                <div class="text-sm font-medium" :class="{ 'line-through text-gray-500': isCompleted }">
                    {{ task.name }}
                </div>

                <!-- Task Meta -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Labels -->
                    <v-chip v-for="label in task.labels?.slice(0, 2)" :key="label.id" :color="label.color"
                        size="x-small" variant="tonal">
                        {{ label.name }}
                    </v-chip>
                    <v-chip v-if="task.labels?.length > 2" size="x-small" variant="tonal">
                        +{{ task.labels.length - 2 }}
                    </v-chip>
                </div>

                <!-- Bottom Row -->
                <div v-if="!compact" class="flex items-center gap-3 mt-2">
                    <!-- Priority -->
                    <div v-if="priority" class="flex items-center gap-1">
                        <v-icon :color="priority.color" size="14">{{ priority.icon }}</v-icon>
                    </div>

                    <!-- Due Date -->
                    <div v-if="dueDate" class="flex items-center gap-1 text-xs"
                        :class="dueDate.overdue ? 'text-red-500' : 'text-gray-500'">
                        <v-icon size="12">mdi-calendar-outline</v-icon>
                        {{ dueDate.text }}
                    </div>

                    <!-- Subtasks Progress -->
                    <div v-if="subtaskProgress" class="flex items-center gap-1 text-xs text-gray-500">
                        <v-icon size="12">mdi-checkbox-multiple-outline</v-icon>
                        {{ subtaskProgress.completed }}/{{ subtaskProgress.total }}
                    </div>

                    <!-- Time Spent -->
                    <div v-if="aggregatedTimeSpent" class="flex items-center gap-1 text-xs text-gray-500">
                        <v-icon size="12">mdi-timer-outline</v-icon>
                        {{ aggregatedTimeSpent }}
                    </div>

                    <!-- Comments Count -->
                    <div v-if="task.comments_count" class="flex items-center gap-1 text-xs text-gray-500">
                        <v-icon size="12">mdi-comment-outline</v-icon>
                        {{ task.comments_count }}
                    </div>

                    <v-spacer />

                    <!-- Assignees from Subtasks -->
                    <div v-if="aggregatedAssignees?.length" class="flex -space-x-1">
                        <v-avatar v-for="assignee in aggregatedAssignees.slice(0, 3)" :key="assignee.id"
                            :color="assignee.avatar_color" size="20" class="border border-[#2d2d30]">
                            <img v-if="assignee.profile_photo_url" :src="assignee.profile_photo_url"
                                :alt="assignee.name" />
                            <span v-else class="text-[10px]">{{ assignee.initials }}</span>
                        </v-avatar>
                        <v-avatar v-if="aggregatedAssignees.length > 3" color="grey-darken-2" size="20"
                            class="border border-[#2d2d30]">
                            <span class="text-[10px]">+{{ aggregatedAssignees.length - 3 }}</span>
                        </v-avatar>
                    </div>
                </div>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.task-card {
    transition: all 0.15s ease;
    cursor: pointer;
    background-color: #1e1e1e;
    border-color: #2d2d30;
}

.task-card:hover {
    border-color: #3d3d40;
    transform: translateY(-1px);
}

.task-card--completed {
    opacity: 0.7;
}

.task-card--critical {
    border-color: #ef4444 !important;
    border-width: 2px;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, transparent 50%);
}

.task-card--critical:hover {
    border-color: #f87171 !important;
    box-shadow: 0 0 12px rgba(239, 68, 68, 0.3);
}

.task-card--compact .v-card-text {
    padding: 8px 12px !important;
}

.-space-x-1>*+* {
    margin-left: -4px;
}

.critical-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}
</style>
