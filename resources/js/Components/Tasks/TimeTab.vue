<script setup>
import { computed, ref } from 'vue';
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

const newEntry = ref({
    startDate: '',
    startTime: '',
    endDate: '',
    endTime: '',
    description: '',
});

const showStartDatePicker = ref(false);
const showStartTimePicker = ref(false);
const showEndDatePicker = ref(false);
const showEndTimePicker = ref(false);

const pad2 = (n) => String(n).padStart(2, '0');

// Normalize picker output across adapters (string, Date, array, or date-like objects)
const normalizeDateValue = (value) => {
    const raw = Array.isArray(value) ? value[0] : value;
    if (!raw) return '';

    if (typeof raw === 'string') {
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return '';
        return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
    }

    if (raw instanceof Date) {
        if (Number.isNaN(raw.getTime())) return '';
        return `${raw.getFullYear()}-${pad2(raw.getMonth() + 1)}-${pad2(raw.getDate())}`;
    }

    if (raw && typeof raw.toISODate === 'function') {
        return raw.toISODate() || '';
    }

    return '';
};

const normalizeTimeValue = (value) => {
    const raw = Array.isArray(value) ? value[0] : value;
    if (!raw) return '';

    if (typeof raw === 'string') {
        const m = raw.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return '';
        return `${pad2(m[1])}:${m[2]}`;
    }

    if (raw instanceof Date) {
        if (Number.isNaN(raw.getTime())) return '';
        return `${pad2(raw.getHours())}:${pad2(raw.getMinutes())}`;
    }

    if (raw && typeof raw === 'object' && Number.isFinite(raw.hours) && Number.isFinite(raw.minutes)) {
        return `${pad2(raw.hours)}:${pad2(raw.minutes)}`;
    }

    if (raw && typeof raw.toFormat === 'function') {
        return raw.toFormat('HH:mm');
    }

    return '';
};

const sortedTimeEntries = computed(() => {
    const entries = Array.isArray(props.task?.time_entries) ? [...props.task.time_entries] : [];
    return entries.sort((a, b) => {
        const aTime = new Date(a.started_at || a.created_at || 0).getTime();
        const bTime = new Date(b.started_at || b.created_at || 0).getTime();
        return bTime - aTime;
    });
});

const formatPickerDate = (value) => {
    const normalized = normalizeDateValue(value);
    if (!normalized) return 'Select date';
    const d = new Date(`${normalized}T00:00:00`);
    if (Number.isNaN(d.getTime())) return 'Select date';
    return d.toLocaleDateString();
};

const formatPickerTime = (value) => {
    const normalized = normalizeTimeValue(value);
    if (!normalized) return 'Select time';
    const [hour = '00', minute = '00'] = normalized.split(':');
    const d = new Date();
    d.setHours(Number(hour), Number(minute), 0, 0);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const buildDateTime = (dateValue, timeValue) => {
    const normalizedDate = normalizeDateValue(dateValue);
    const normalizedTime = normalizeTimeValue(timeValue);
    if (!normalizedDate || !normalizedTime) return null;
    const dt = new Date(`${normalizedDate}T${normalizedTime}:00`);
    return Number.isNaN(dt.getTime()) ? null : dt;
};

const normalizedStartDate = computed(() => normalizeDateValue(newEntry.value.startDate));
const normalizedEndDate = computed(() => normalizeDateValue(newEntry.value.endDate));
const normalizedStartTime = computed(() => normalizeTimeValue(newEntry.value.startTime));
const normalizedEndTime = computed(() => normalizeTimeValue(newEntry.value.endTime));

const canLogEntry = computed(() => {
    return !!(normalizedStartDate.value && normalizedStartTime.value && normalizedEndDate.value && normalizedEndTime.value);
});

const startDateTime = computed(() => buildDateTime(newEntry.value.startDate, newEntry.value.startTime));
const endDateTime = computed(() => buildDateTime(newEntry.value.endDate, newEntry.value.endTime));

const durationMinutes = computed(() => {
    if (!startDateTime.value || !endDateTime.value) return null;
    return Math.round((endDateTime.value.getTime() - startDateTime.value.getTime()) / 60000);
});

const durationPreview = computed(() => {
    if (!durationMinutes.value || durationMinutes.value <= 0) return null;
    const h = Math.floor(durationMinutes.value / 60);
    const m = durationMinutes.value % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
});

const addEntry = () => {
    if (!startDateTime.value || !endDateTime.value) return;

    const start = startDateTime.value;
    const end = endDateTime.value;

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        window.showSnackbar?.('Start and end time are required.', 'error');
        return;
    }

    if (end <= start) {
        window.showSnackbar?.('End time must be after start time.', 'error');
        return;
    }

    if ((durationMinutes.value || 0) <= 0) {
        window.showSnackbar?.('End time must be after start time.', 'error');
        return;
    }

    if ((durationMinutes.value || 0) > 1440) {
        window.showSnackbar?.('Duration cannot exceed 24 hours.', 'error');
        return;
    }

    if (newEntry.value.description?.length > 500) { window.showSnackbar?.('Description cannot exceed 500 characters.', 'error'); return; }

    router.post(
        route('tasks.subtasks.time-entries.store', [props.workspace.id, props.space.id, props.list.id, props.parentTask.id, props.task.id]),
        {
            started_at: `${normalizedStartDate.value} ${normalizedStartTime.value}:00`,
            ended_at: `${normalizedEndDate.value} ${normalizedEndTime.value}:00`,
            description: newEntry.value.description,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newEntry.value = {
                    startDate: '',
                    startTime: '',
                    endDate: '',
                    endTime: '',
                    description: '',
                };
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
                    :color="(task.time_spent || 0) > task.time_estimate ? 'error' : 'primary'" height="8" rounded
                    class="mt-1" />
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
                <div class="log-time-grid">
                    <div class="time-row">
                        <div class="time-row-label">Start</div>
                        <v-menu v-model="showStartDatePicker" :close-on-content-click="false">
                            <template #activator="{ props: menuProps }">
                                <v-text-field v-bind="menuProps" :model-value="formatPickerDate(newEntry.startDate)"
                                    label="Date" prepend-inner-icon="mdi-calendar" variant="outlined" density="compact"
                                    readonly hide-details class="picker-field" />
                            </template>
                            <v-card color="surface">
                                <v-date-picker :model-value="newEntry.startDate" hide-header
                                    @update:model-value="(value) => { newEntry.startDate = normalizeDateValue(value); showStartDatePicker = false; }" />
                            </v-card>
                        </v-menu>

                        <v-menu v-model="showStartTimePicker" :close-on-content-click="false">
                            <template #activator="{ props: menuProps }">
                                <v-text-field v-bind="menuProps" :model-value="formatPickerTime(newEntry.startTime)"
                                    label="Time" prepend-inner-icon="mdi-clock-outline" variant="outlined"
                                    density="compact" readonly hide-details class="picker-field" />
                            </template>
                            <v-card color="surface" min-width="260">
                                <v-time-picker :model-value="newEntry.startTime" format="24hr"
                                    @update:model-value="(value) => { newEntry.startTime = normalizeTimeValue(value); showStartTimePicker = false; }" />
                            </v-card>
                        </v-menu>
                    </div>

                    <div class="time-row">
                        <div class="time-row-label">End</div>
                        <v-menu v-model="showEndDatePicker" :close-on-content-click="false">
                            <template #activator="{ props: menuProps }">
                                <v-text-field v-bind="menuProps" :model-value="formatPickerDate(newEntry.endDate)"
                                    label="Date" prepend-inner-icon="mdi-calendar" variant="outlined" density="compact"
                                    readonly hide-details class="picker-field" />
                            </template>
                            <v-card color="surface">
                                <v-date-picker :model-value="newEntry.endDate" hide-header
                                    @update:model-value="(value) => { newEntry.endDate = normalizeDateValue(value); showEndDatePicker = false; }" />
                            </v-card>
                        </v-menu>

                        <v-menu v-model="showEndTimePicker" :close-on-content-click="false">
                            <template #activator="{ props: menuProps }">
                                <v-text-field v-bind="menuProps" :model-value="formatPickerTime(newEntry.endTime)"
                                    label="Time" prepend-inner-icon="mdi-clock-outline" variant="outlined"
                                    density="compact" readonly hide-details class="picker-field" />
                            </template>
                            <v-card color="surface" min-width="260">
                                <v-time-picker :model-value="newEntry.endTime" format="24hr"
                                    @update:model-value="(value) => { newEntry.endTime = normalizeTimeValue(value); showEndTimePicker = false; }" />
                            </v-card>
                        </v-menu>
                    </div>

                    <div class="time-row time-row--full">
                        <v-text-field v-model="newEntry.description" label="Description (optional)" variant="outlined"
                            density="compact" hide-details class="flex-1" />
                        <v-chip v-if="durationPreview" size="small" color="primary" variant="tonal">
                            Duration: {{ durationPreview }}
                        </v-chip>
                        <v-btn color="primary" variant="flat" size="small"
                            :disabled="!canLogEntry"
                            @click="addEntry">
                            <v-icon size="16">mdi-plus</v-icon>
                            Log
                        </v-btn>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!sortedTimeEntries.length" class="d-flex flex-column align-center py-10 text-grey">
            <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-clock-outline</v-icon>
            <div class="text-body-2">No time entries yet</div>
        </div>

        <!-- Time Entries list -->
        <div v-else class="time-entry-list">
            <div v-for="entry in sortedTimeEntries" :key="entry.id" class="time-entry-item">
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

.log-time-grid {
    display: grid;
    gap: 10px;
}

.time-row {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px;
    align-items: center;
}

.time-row--full {
    grid-template-columns: minmax(0, 1fr) auto auto;
}

.time-row-label {
    font-size: 12px;
    color: #9ca3af;
    font-weight: 600;
}

.picker-field {
    min-width: 0;
}

@media (max-width: 900px) {

    .time-row,
    .time-row--full {
        grid-template-columns: 1fr;
    }
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
