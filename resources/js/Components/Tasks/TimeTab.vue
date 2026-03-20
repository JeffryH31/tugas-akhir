<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    task: Object,       // localTask (time_entries, time_estimate, time_spent)
    workspace: Object,
    space: Object,
    list: Object,
    parentTask: Object,
});

const formatDuration = (seconds) => {
    if (!seconds) return 'Not set';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
};

const formatTimeEstimate = (minutes) => {
    if (!minutes) return 'Not set';
    const h = minutes / 60;
    return h % 1 === 0 ? `${h}h` : `${h.toFixed(1)}h`;
};

const getSpentPercentage = (spentMinutes, estimateMinutes) => {
    if (!estimateMinutes) return null;
    return Math.round(((spentMinutes || 0) / estimateMinutes) * 100);
};

const newEntry = ref({ duration: null, description: '' });

const addEntry = () => {
    if (!newEntry.value.duration) return;
    const hours = parseFloat(newEntry.value.duration);
    if (isNaN(hours) || hours <= 0) { window.showSnackbar?.('Duration must be greater than 0.', 'error'); return; }
    if (hours > 24) { window.showSnackbar?.('Duration cannot exceed 24 hours.', 'error'); return; }
    if (newEntry.value.description?.length > 500) { window.showSnackbar?.('Description cannot exceed 500 characters.', 'error'); return; }

    router.post(
        route('tasks.subtasks.time-entries.store', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id]),
        { duration: hours * 60, description: newEntry.value.description },
        {
            preserveScroll: true,
            onSuccess: () => {
                newEntry.value = { duration: null, description: '' };
                window.showSnackbar?.('Time entry added!', 'success');
                router.reload({ only: ['task', 'tasksByStatus'] });
            },
        }
    );
};

const deleteEntry = async (entryId) => {
    if (!await confirmDialog('Delete this time entry?', 'Delete Time Entry')) return;
    router.delete(
        route('tasks.subtasks.time-entries.destroy', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id, entryId]),
        {
            preserveScroll: true,
            onSuccess: () => {
                window.showSnackbar?.('Time entry deleted!', 'success');
                router.reload({ only: ['task', 'tasksByStatus'] });
            },
        }
    );
};
</script>

<template>
    <div class="pa-5">
        <!-- Summary -->
        <div class="time-summary mb-4">
            <div class="time-summary-item">
                <div class="text-caption text-grey mb-1">Estimated</div>
                <div class="text-h6 font-weight-bold">{{ formatTimeEstimate(task.time_estimate) }}</div>
            </div>
            <v-divider vertical class="mx-4" />
            <div class="time-summary-item">
                <div class="text-caption text-grey mb-1">Spent</div>
                <div class="d-flex align-center ga-2 justify-center">
                    <div class="text-h6 font-weight-bold"
                        :class="task.time_spent > task.time_estimate ? 'text-error' : ''">
                        {{ formatDuration((task.time_spent || 0) * 60) }}
                    </div>
                    <v-chip v-if="task.time_estimate" size="x-small" variant="tonal"
                        :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'">
                        {{ getSpentPercentage(task.time_spent, task.time_estimate) }}%
                    </v-chip>
                </div>
            </div>
            <v-divider vertical class="mx-4" />
            <div class="time-summary-item flex-1">
                <div class="text-caption text-grey mb-1">Progress</div>
                <v-progress-linear v-if="task.time_estimate"
                    :model-value="((task.time_spent || 0) / task.time_estimate) * 100"
                    :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'"
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
                    <v-text-field v-model="newEntry.duration" type="number" label="Hours" variant="outlined"
                        density="compact" step="0.25" min="0.01" max="24" hide-details style="max-width: 120px;" />
                    <v-text-field v-model="newEntry.description" label="Description (optional)" variant="outlined"
                        density="compact" hide-details class="flex-1" />
                    <v-btn color="primary" variant="flat" size="small" :disabled="!newEntry.duration" @click="addEntry">
                        <v-icon size="16">mdi-plus</v-icon>
                        Log
                    </v-btn>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!task.time_entries?.length" class="d-flex flex-column align-center py-10 text-grey">
            <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-clock-outline</v-icon>
            <div class="text-body-2">No time entries yet</div>
        </div>

        <!-- Time Entries list -->
        <div v-else class="time-entry-list">
            <div v-for="entry in task.time_entries" :key="entry.id" class="time-entry-item">
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
                        <v-chip v-if="entry.is_running" size="x-small" color="success" variant="tonal">
                            <v-icon start size="10">mdi-circle</v-icon>
                            Running
                        </v-chip>
                    </div>
                </div>
                <v-btn icon size="x-small" variant="text" color="grey" @click="deleteEntry(entry.id)">
                    <v-icon size="14">mdi-delete-outline</v-icon>
                </v-btn>
            </div>
        </div>
    </div>
</template>

<style scoped>
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

.section-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    overflow: hidden;
}

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
</style>
