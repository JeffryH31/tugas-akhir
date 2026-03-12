<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    activeWorkspace: Object,
    entries: Array,
    runningTimer: Object,
    stats: Object,
});

const page = usePage();

// Date range filter
const dateRange = ref('today');

// Running timer
const elapsedSeconds = ref(0);
const timerInterval = ref(null);

// Form
const showAddEntry = ref(false);
const editingEntry = ref(null);
const entryForm = ref({
    description: '',
    started_at: '',
    ended_at: '',
    is_billable: false,
});

// Computed
const formatRunningTime = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Methods
const formatDuration = (minutes) => {
    if (!minutes) return '0h 0m';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    return `${hours}h ${mins}m`;
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const goToTask = (entry) => {
    const task = entry?.subtask?.task;
    if (!task) return;
    
    router.visit(route('tasks.show', [
        props.activeWorkspace.id,
        task.task_list?.space_id,
        task.task_list_id,
        task.id,
    ]));
};

// Helper: fetch with fresh CSRF token
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

const stopTimer = async (entry) => {
    const task = entry?.subtask?.task;
    if (!task) return;

    try {
        const url = route('tasks.timer.stop', [
            props.activeWorkspace.id,
            task.task_list?.space_id,
            task.task_list_id,
            task.id,
            entry.id,
        ]);
        const res = await safeFetch(url, { method: 'POST' });

        if (res.ok || res.status === 302 || res.status === 303) {
            stopTimerInterval();
            elapsedSeconds.value = 0;
            router.reload({ preserveScroll: true });
        } else if (res.status === 419) {
            window.location.reload();
        }
    } catch (err) {
        if (window.showSnackbar) {
            window.showSnackbar('Failed to stop timer', 'error');
        }
    }
};

const editEntry = (entry) => {
    editingEntry.value = entry;
    entryForm.value = {
        description: entry.description || '',
        started_at: entry.started_at ? new Date(entry.started_at).toISOString().slice(0, 16) : '',
        ended_at: entry.ended_at ? new Date(entry.ended_at).toISOString().slice(0, 16) : '',
        is_billable: entry.is_billable,
    };
    showAddEntry.value = true;
};

const saveEntry = () => {
    if (editingEntry.value) {
        router.patch(
            route('time-entries.update', editingEntry.value.id),
            entryForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showAddEntry.value = false;
                    editingEntry.value = null;
                    resetForm();
                }
            }
        );
    }
};

const deleteEntry = async (entry) => {
    const { confirm } = useConfirmDialog();
    if (await confirm('Are you sure you want to delete this time entry?', 'Delete Time Entry')) {
        router.delete(route('time-entries.destroy', entry.id), {
            preserveScroll: true
        });
    }
};

const resetForm = () => {
    entryForm.value = {
        description: '',
        started_at: '',
        ended_at: '',
        is_billable: false,
    };
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
    }
};

onMounted(() => {
    if (props.runningTimer) {
        const startTime = new Date(props.runningTimer.started_at).getTime();
        elapsedSeconds.value = Math.floor((Date.now() - startTime) / 1000);
        startTimerInterval();
    }
});

onUnmounted(() => {
    stopTimerInterval();
});

// Re-fetch entries when date range changes
watch(dateRange, (range) => {
    let startDate = null;
    let endDate = null;
    const now = new Date();

    if (range === 'today') {
        startDate = now.toISOString().split('T')[0];
        endDate = startDate;
    } else if (range === 'week') {
        const day = now.getDay();
        const diff = day === 0 ? 6 : day - 1;
        const monday = new Date(now);
        monday.setDate(now.getDate() - diff);
        startDate = monday.toISOString().split('T')[0];
        endDate = now.toISOString().split('T')[0];
    } else if (range === 'month') {
        startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        endDate = now.toISOString().split('T')[0];
    }

    router.reload({
        preserveScroll: true,
        data: { start_date: startDate, end_date: endDate },
        only: ['entries', 'stats'],
    });
});

// Re-sync timer display when runningTimer prop changes (e.g. after stop)
watch(() => props.runningTimer, (newTimer) => {
    stopTimerInterval();
    if (newTimer?.is_running) {
        const startTime = new Date(newTimer.started_at).getTime();
        elapsedSeconds.value = Math.floor((Date.now() - startTime) / 1000);
        startTimerInterval();
    } else {
        elapsedSeconds.value = 0;
    }
});
</script>

<template>
    <Head title="Time Tracking" />
    
    <MainLayout :workspace="activeWorkspace">
        <div class="h-full bg-[#1E1E1E] overflow-auto">
            <!-- Header -->
            <div class="bg-[#252526] border-b border-gray-800 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-white">Time Tracking</h1>
                        <p class="text-sm text-gray-400">Track time spent on tasks</p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Quick timer -->
                        <div v-if="runningTimer" class="flex items-center gap-2 bg-red-900/30 px-4 py-2 rounded-lg">
                            <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                            <span class="text-white font-mono">{{ formatRunningTime }}</span>
                            <span class="text-gray-400">on</span>
                            <span class="text-white">{{ runningTimer.subtask?.name || runningTimer.subtask?.task?.name || 'Task' }}</span>
                            <v-btn
                                icon="mdi-stop"
                                size="small"
                                color="error"
                                @click="stopTimer(runningTimer)"
                            />
                        </div>
                        
                        <!-- Date filter -->
                        <v-btn-toggle v-model="dateRange" mandatory color="primary">
                            <v-btn value="today" size="small">Today</v-btn>
                            <v-btn value="week" size="small">This Week</v-btn>
                            <v-btn value="month" size="small">This Month</v-btn>
                        </v-btn-toggle>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-1">Today</div>
                        <div class="text-2xl font-bold text-white">{{ formatDuration(stats.today) }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-1">This Week</div>
                        <div class="text-2xl font-bold text-white">{{ formatDuration(stats.week) }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-1">This Month</div>
                        <div class="text-2xl font-bold text-white">{{ formatDuration(stats.month) }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-1">Billable Hours</div>
                        <div class="text-2xl font-bold text-green-400">{{ formatDuration(stats.billable) }}</div>
                    </div>
                </div>
                
                <!-- Time Entries Table -->
                <div class="bg-[#2D2D2D] rounded-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-700">
                        <h2 class="font-semibold">Time Entries</h2>
                    </div>
                    
                    <v-table class="bg-transparent" dark>
                        <thead>
                            <tr>
                                <th class="text-left">Task</th>
                                <th class="text-left">Project</th>
                                <th class="text-left">Description</th>
                                <th class="text-left">Start</th>
                                <th class="text-left">End</th>
                                <th class="text-right">Duration</th>
                                <th class="text-center">Billable</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="entry in entries"
                                :key="entry.id"
                                class="hover:bg-[#3D3D3D]"
                            >
                                <td>
                                    <a 
                                        @click="goToTask(entry)"
                                        class="text-primary hover:underline cursor-pointer"
                                    >
                                        {{ entry.subtask?.name || 'No subtask' }}
                                    </a>
                                    <div v-if="entry.subtask?.task" class="text-xs text-gray-500">
                                        {{ entry.subtask.task.name }}
                                    </div>
                                </td>
                                <td class="text-gray-400">
                                    {{ entry.subtask?.task?.task_list?.name || '-' }}
                                </td>
                                <td>{{ entry.description || '-' }}</td>
                                <td class="text-gray-400">{{ formatDateTime(entry.started_at) }}</td>
                                <td class="text-gray-400">{{ entry.ended_at ? formatDateTime(entry.ended_at) : '-' }}</td>
                                <td class="text-right font-mono">
                                    <span v-if="entry.is_running" class="text-red-400 flex items-center justify-end gap-1">
                                        <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                        Running
                                    </span>
                                    <span v-else>{{ formatDuration(entry.duration) }}</span>
                                </td>
                                <td class="text-center">
                                    <v-icon 
                                        :color="entry.is_billable ? 'success' : 'grey'"
                                        size="20"
                                    >
                                        {{ entry.is_billable ? 'mdi-currency-usd' : 'mdi-currency-usd-off' }}
                                    </v-icon>
                                </td>
                                <td class="text-right">
                                    <v-btn
                                        v-if="entry.is_running"
                                        icon="mdi-stop"
                                        size="x-small"
                                        color="error"
                                        @click="stopTimer(entry)"
                                    />
                                    <v-btn
                                        v-else
                                        icon="mdi-pencil"
                                        size="x-small"
                                        variant="text"
                                        @click="editEntry(entry)"
                                    />
                                    <v-btn
                                        icon="mdi-delete"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        @click="deleteEntry(entry)"
                                    />
                                </td>
                            </tr>
                            <tr v-if="entries.length === 0">
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    No time entries found. Start tracking time on a task!
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </div>
                
                <!-- Add Manual Entry Button -->
                <v-btn
                    class="mt-4"
                    prepend-icon="mdi-plus"
                    color="primary"
                    @click="showAddEntry = true"
                >
                    Add Manual Entry
                </v-btn>
            </div>
            
            <!-- Add/Edit Entry Dialog -->
            <v-dialog v-model="showAddEntry" max-width="500">
                <v-card class="bg-[#2D2D2D]">
                    <v-card-title>{{ editingEntry ? 'Edit Time Entry' : 'Add Time Entry' }}</v-card-title>
                    <v-card-text>
                        <v-text-field
                            v-model="entryForm.description"
                            label="Description"
                            variant="outlined"
                            hide-details
                            class="mb-4"
                        />
                        
                        <div class="grid grid-cols-2 gap-4">
                            <v-text-field
                                v-model="entryForm.started_at"
                                label="Start Time"
                                type="datetime-local"
                                variant="outlined"
                                hide-details
                            />
                            <v-text-field
                                v-model="entryForm.ended_at"
                                label="End Time"
                                type="datetime-local"
                                variant="outlined"
                                hide-details
                            />
                        </div>
                        
                        <v-checkbox
                            v-model="entryForm.is_billable"
                            label="Billable"
                            hide-details
                            class="mt-4"
                        />
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" @click="showAddEntry = false">Cancel</v-btn>
                        <v-btn color="primary" @click="saveEntry">Save</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </div>
    </MainLayout>
</template>

