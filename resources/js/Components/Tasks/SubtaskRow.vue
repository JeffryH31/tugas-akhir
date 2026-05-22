<script setup>
import { computed } from "vue";
import { PRIORITY_MAP } from "@/constants/priorities";

const props = defineProps({
    item: { type: Object, required: true },
    canOperate: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle", "open"]);

const isOverdue = computed(() => {
    if (!props.item.due_date || props.item.completed_at) return false;
    return new Date(props.item.due_date) < new Date();
});

const formattedDate = computed(() => {
    if (!props.item.due_date) return null;
    return new Date(props.item.due_date).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
    });
});

const priorityColor = computed(() => {
    return PRIORITY_MAP[props.item.priority_level]?.color || props.item.priority?.color || null;
});
</script>

<template>
    <div class="cu-subtask-row" @click="emit('open', item)">
        <div
            class="cu-subtask-checkbox"
            @click.stop="canOperate && emit('toggle', item)"
        >
            <span
                class="cu-checkbox-box"
                :class="{ 'cu-checkbox-box--done': item.completed_at }"
                :style="{ '--status-color': item.status?.color || '#6b7280' }"
            >
                <v-icon v-if="item.completed_at" size="14" color="white">mdi-check</v-icon>
            </span>
        </div>

        <span
            class="cu-subtask-name"
            :class="{ 'cu-subtask-name--done': item.completed_at }"
        >
            {{ item.name }}
        </span>

        <div class="cu-subtask-meta">
            <span
                v-if="formattedDate"
                class="cu-meta-date"
                :class="{ 'cu-meta-date--overdue': isOverdue }"
            >
                <v-icon size="13">mdi-calendar-blank-outline</v-icon>
                {{ formattedDate }}
            </span>

            <v-icon
                v-if="priorityColor"
                size="16"
                :color="priorityColor"
            >mdi-flag</v-icon>

            <v-avatar
                v-if="item.assignees?.length"
                :color="item.assignees[0].avatar_color || 'primary'"
                size="24"
                class="cu-meta-avatar"
            >
                <span class="cu-avatar-text">{{ item.assignees[0].initials }}</span>
            </v-avatar>

            <span
                class="cu-meta-status"
                :style="{ color: item.status?.color || '#6b7280' }"
            >
                {{ item.status?.name || "Open" }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.cu-subtask-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 16px;
    cursor: pointer;
    transition: background-color 0.1s;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.cu-subtask-row:hover {
    background-color: rgba(255, 255, 255, 0.03);
}

.cu-subtask-checkbox {
    flex-shrink: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cu-checkbox-box {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid var(--status-color);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    background: transparent;
}

.cu-checkbox-box:hover {
    opacity: 0.8;
    transform: scale(1.08);
}

.cu-checkbox-box--done {
    background-color: var(--status-color);
    border-color: var(--status-color);
}

.cu-subtask-name {
    flex: 1;
    min-width: 0;
    font-size: 14px;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.88);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.4;
}

.cu-subtask-name--done {
    text-decoration: line-through;
    color: rgba(255, 255, 255, 0.35);
}

.cu-subtask-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.cu-meta-date {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
}

.cu-meta-date--overdue {
    color: #ef5350;
}

.cu-meta-avatar {
    flex-shrink: 0;
}

.cu-avatar-text {
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
}

.cu-meta-status {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    min-width: 55px;
    text-align: right;
}
</style>
