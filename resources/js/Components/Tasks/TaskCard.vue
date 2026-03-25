<script setup>
/**
 * Task Card Component - ClickUp Style
 */
import { computed, inject } from 'vue';
import { router } from '@inertiajs/vue3';
import { PRIORITY_MAP } from '@/constants/priorities';

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
    showCheckbox: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['click', 'complete', 'open-detail']);

// Inject CPM critical path checker (provided by parent)
const isSubtaskCritical = inject('isSubtaskCritical', () => false);

// Check if this task/subtask is on critical path
const isCritical = computed(() => isSubtaskCritical(props.task.id));

const priority = computed(() => {
    if (!props.task.priority_level) return null;
    return PRIORITY_MAP[props.task.priority_level] || null;
});

// Status color for left border
const statusColor = computed(() => props.task.status?.color || '#6b7280');

// Due date formatting
const dueDate = computed(() => {
    if (!props.task.due_date) return null;
    const date = new Date(props.task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((date - now) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        return { text: `${Math.abs(diffDays)}d overdue`, color: '#f87171', overdue: true };
    } else if (diffDays === 0) {
        return { text: 'Today', color: '#fbbf24', overdue: false };
    } else if (diffDays === 1) {
        return { text: 'Tomorrow', color: '#60a5fa', overdue: false };
    } else if (diffDays <= 7) {
        return { text: `${diffDays}d`, color: '#9ca3af', overdue: false };
    } else {
        return { text: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }), color: '#9ca3af', overdue: false };
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

// Display assignees: use direct assignees first, fallback to aggregated from subtasks
const displayAssignees = computed(() => {
    const toList = (value) => {
        if (Array.isArray(value)) return value;
        if (Array.isArray(value?.data)) return value.data;
        if (value && typeof value === 'object') return Object.values(value).filter(Boolean);
        return [];
    };

    const directAssignees = toList(props.task.assignees);
    if (directAssignees.length) return directAssignees;

    const subtasks = toList(props.task.subtasks);
    if (subtasks.length === 0) return [];

    const assigneeMap = new Map();
    subtasks.forEach(subtask => {
        const subtaskAssignees = toList(subtask?.assignees);
        if (subtaskAssignees.length) {
            subtaskAssignees.forEach(a => {
                if (!assigneeMap.has(a.id)) assigneeMap.set(a.id, a);
            });
        }
    });
    return Array.from(assigneeMap.values());
});

// Aggregate time spent from subtasks
const aggregatedTimeSpent = computed(() => {
    if (!props.task.subtasks || props.task.subtasks.length === 0) return null;
    const totalMinutes = props.task.subtasks.reduce((sum, s) => sum + (s.time_spent || 0), 0);
    if (totalMinutes === 0) return null;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
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
    <div class="cu-card" :class="{
        'cu-card--completed': isCompleted,
        'cu-card--critical': isCritical && !isCompleted,
        'cu-card--no-checkbox': !showCheckbox,
    }" @click="openDetail">
        <!-- Left status color strip -->
        <div class="cu-card__status-bar" :style="{ backgroundColor: statusColor }"></div>

        <div class="cu-card__content">
            <!-- Top: Critical indicator -->
            <div v-if="isCritical && !isCompleted" class="cu-card__id-row">
                <v-tooltip location="top">
                    <template #activator="{ props: tp }">
                        <v-icon v-bind="tp" size="12" color="error" class="cu-card__critical-badge">
                            mdi-alert-circle
                        </v-icon>
                    </template>
                    <span>On Critical Path</span>
                </v-tooltip>
            </div>

            <!-- Task name row -->
            <div class="cu-card__name-row">
                <button v-if="showCheckbox" class="cu-card__checkbox"
                    :class="{ 'cu-card__checkbox--done': isCompleted }"
                    :style="!isCompleted ? { borderColor: statusColor } : {}" @click="toggleComplete">
                    <v-icon v-if="isCompleted" size="12" color="white">mdi-check</v-icon>
                </button>
                <span class="cu-card__name" :class="{ 'cu-card__name--done': isCompleted }">
                    {{ task.name }}
                </span>
            </div>

            <!-- Labels -->
            <div v-if="task.labels?.length" class="cu-card__labels">
                <span v-for="label in task.labels.slice(0, 4)" :key="label.id" class="cu-card__label"
                    :style="{ backgroundColor: label.color + '22', color: label.color, borderColor: label.color + '44' }">
                    {{ label.name }}
                </span>
                <span v-if="task.labels.length > 4" class="cu-card__label cu-card__label--more">
                    +{{ task.labels.length - 4 }}
                </span>
            </div>

            <!-- Bottom meta row -->
            <div v-if="!compact" class="cu-card__footer">
                <div class="cu-card__meta">
                    <!-- Priority -->
                    <v-tooltip v-if="priority" location="top">
                        <template #activator="{ props: tp }">
                            <div v-bind="tp" class="cu-card__meta-item">
                                <v-icon size="14" :style="{ color: priority.color }">mdi-flag</v-icon>
                            </div>
                        </template>
                        <span>{{ priority.name }}</span>
                    </v-tooltip>

                    <!-- Due Date -->
                    <div v-if="dueDate" class="cu-card__meta-item" :style="{ color: dueDate.color }">
                        <v-icon size="13" :style="{ color: dueDate.color }">mdi-calendar-blank-outline</v-icon>
                        <span>{{ dueDate.text }}</span>
                    </div>

                    <!-- Subtask Progress -->
                    <div v-if="subtaskProgress" class="cu-card__meta-item cu-card__meta-item--subtle">
                        <v-icon size="13">mdi-file-tree-outline</v-icon>
                        <span>{{ subtaskProgress.completed }}/{{ subtaskProgress.total }}</span>
                    </div>

                    <!-- Time -->
                    <div v-if="aggregatedTimeSpent" class="cu-card__meta-item cu-card__meta-item--subtle">
                        <v-icon size="13">mdi-clock-outline</v-icon>
                        <span>{{ aggregatedTimeSpent }}</span>
                    </div>

                    <!-- Comments -->
                    <div v-if="task.comments_count" class="cu-card__meta-item cu-card__meta-item--subtle">
                        <v-icon size="13">mdi-chat-outline</v-icon>
                        <span>{{ task.comments_count }}</span>
                    </div>
                </div>

                <!-- Assignees -->
                <div v-if="displayAssignees.length" class="cu-card__assignees">
                    <v-tooltip v-for="assignee in displayAssignees.slice(0, 3)" :key="assignee.id" location="top">
                        <template #activator="{ props: tp }">
                            <v-avatar v-bind="tp" :color="assignee.avatar_color || '#4f46e5'" size="24"
                                class="cu-card__avatar">
                                <img v-if="assignee.profile_photo_url" :src="assignee.profile_photo_url"
                                    :alt="assignee.name" />
                                <span v-else class="cu-card__avatar-text">{{ assignee.name?.charAt(0) }}</span>
                            </v-avatar>
                        </template>
                        <span>{{ assignee.name }}</span>
                    </v-tooltip>
                    <v-avatar v-if="displayAssignees.length > 3" color="#374151" size="24" class="cu-card__avatar">
                        <span class="cu-card__avatar-text">+{{ displayAssignees.length - 3 }}</span>
                    </v-avatar>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ClickUp-style card */
.cu-card {
    display: flex;
    background: #1e1e2e;
    border: 1px solid #2e2e3e;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    overflow: hidden;
    position: relative;
}

.cu-card:hover {
    background: #242438;
    border-color: #3e3e52;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.cu-card--completed {
    opacity: 0.55;
}

.cu-card--critical {
    border-color: rgba(239, 68, 68, 0.5);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.06) 0%, #1e1e2e 40%);
}

.cu-card--critical:hover {
    border-color: rgba(239, 68, 68, 0.7);
    box-shadow: 0 2px 12px rgba(239, 68, 68, 0.15);
}

/* Left colored strip */
.cu-card__status-bar {
    width: 4px;
    flex-shrink: 0;
    border-radius: 8px 0 0 8px;
}

/* Content area */
.cu-card__content {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Task ID */
.cu-card__id-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cu-card__id {
    font-size: 10px;
    color: #6b7280;
    font-weight: 500;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.cu-card__critical-badge {
    animation: critical-pulse 2s infinite;
}

@keyframes critical-pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}

/* Name row */
.cu-card__name-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.cu-card__checkbox {
    width: 16px;
    height: 16px;
    min-width: 16px;
    border-radius: 4px;
    border: 2px solid #6b7280;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-top: 2px;
}

.cu-card__checkbox:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: scale(1.1);
}

.cu-card__checkbox--done {
    background: #22c55e !important;
    border-color: #22c55e !important;
}

.cu-card__name {
    font-size: 13px;
    font-weight: 500;
    color: #e5e7eb;
    line-height: 1.45;
    word-break: break-word;
    flex: 1;
    min-width: 0;
}

.cu-card__name--done {
    text-decoration: line-through;
    color: #6b7280;
}

/* Labels */
.cu-card__labels {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding-left: 24px;
}

.cu-card__label {
    font-size: 10px;
    font-weight: 600;
    padding: 1px 8px;
    border-radius: 10px;
    border: 1px solid;
    white-space: nowrap;
    line-height: 1.6;
}

.cu-card__label--more {
    background: rgba(107, 114, 128, 0.15);
    color: #9ca3af;
    border-color: rgba(107, 114, 128, 0.3);
}

/* Footer meta row */
.cu-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-left: 24px;
    margin-top: 2px;
}

.cu-card__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.cu-card__meta-item {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 500;
    color: #9ca3af;
    white-space: nowrap;
}

.cu-card__meta-item--subtle {
    color: #6b7280;
}

/* Assignees */
.cu-card__assignees {
    display: flex;
    flex-shrink: 0;
}

.cu-card__avatar {
    border: 2px solid #1e1e2e;
    margin-left: -6px;
    cursor: default;
    font-size: 11px;
}

.cu-card__avatar:first-child {
    margin-left: 0;
}

.cu-card__avatar-text {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.cu-card--no-checkbox .cu-card__labels,
.cu-card--no-checkbox .cu-card__footer {
    padding-left: 0;
}
</style>
