<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';

const props = defineProps({
    workspace: Object,
    subtasks: Array,
    startDate: String,
    endDate: String,
    viewMode: {
        type: String,
        default: 'month'
    }
});

// State
const currentDate = ref(new Date(props.startDate || new Date()));
const viewMode = ref(props.viewMode);
const selectedTask = ref(null);
const showTaskDetail = ref(false);

// Calendar calculations
const year = computed(() => currentDate.value.getFullYear());
const month = computed(() => currentDate.value.getMonth());

const monthName = computed(() => {
    return currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const daysInMonth = computed(() => {
    return new Date(year.value, month.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(year.value, month.value, 1).getDay();
});

const calendarDays = computed(() => {
    const days = [];
    const prevMonthDays = new Date(year.value, month.value, 0).getDate();
    
    // Previous month days
    for (let i = firstDayOfMonth.value - 1; i >= 0; i--) {
        days.push({
            date: new Date(year.value, month.value - 1, prevMonthDays - i),
            isCurrentMonth: false,
        });
    }
    
    // Current month days
    for (let i = 1; i <= daysInMonth.value; i++) {
        days.push({
            date: new Date(year.value, month.value, i),
            isCurrentMonth: true,
        });
    }
    
    // Next month days to complete the grid
    const remainingDays = 42 - days.length; // 6 rows * 7 days
    for (let i = 1; i <= remainingDays; i++) {
        days.push({
            date: new Date(year.value, month.value + 1, i),
            isCurrentMonth: false,
        });
    }
    
    return days;
});

// Get subtasks for a specific date
const getSubtasksForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    return props.subtasks.filter(subtask => {
        const dueDate = subtask.due_date ? subtask.due_date.split('T')[0] : null;
        const startDate = subtask.start_date ? subtask.start_date.split('T')[0] : null;
        
        // If both start and due date exist, check if date is in range
        if (startDate && dueDate) {
            return dateStr >= startDate && dateStr <= dueDate;
        }
        // Otherwise, check if date matches either start or due date
        return dueDate === dateStr || startDate === dateStr;
    });
};

// Check if date is today
const isToday = (date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
};

// Navigation
const previousMonth = () => {
    currentDate.value = new Date(year.value, month.value - 1, 1);
    loadCalendarData();
};

const nextMonth = () => {
    currentDate.value = new Date(year.value, month.value + 1, 1);
    loadCalendarData();
};

const goToToday = () => {
    currentDate.value = new Date();
    loadCalendarData();
};

const loadCalendarData = () => {
    const start = new Date(year.value, month.value, 1);
    const end = new Date(year.value, month.value + 1, 0);
    
    router.get(route('calendar.index', props.workspace.id), {
        start_date: start.toISOString().split('T')[0],
        end_date: end.toISOString().split('T')[0],
        view: viewMode.value
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Task actions
const handleTaskClick = (subtask) => {
    selectedTask.value = subtask;
    showTaskDetail.value = true;
};

const getPriorityColor = (priority) => {
    const colors = {
        1: 'error',
        2: 'warning',
        3: 'info',
        4: 'grey'
    };
    return colors[priority?.level] || 'grey';
};

const getStatusColor = (status) => {
    return status?.color || '#6366F1';
};

// Summary stats
const totalSubtasks = computed(() => props.subtasks.length);
const completedSubtasks = computed(() => props.subtasks.filter(s => s.completed_at).length);
const overdueSubtasks = computed(() => {
    const today = new Date().toISOString().split('T')[0];
    return props.subtasks.filter(s => !s.completed_at && s.due_date && s.due_date < today).length;
});
</script>

<template>
    <MainLayout>
        <Head title="Calendar" />

        <div class="h-full flex flex-col bg-[#1e1e1e]">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-[#2d2d30]">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold text-white">
                        <v-icon class="mr-2">mdi-calendar-month</v-icon>
                        {{ workspace.name }} Calendar
                    </h1>
                    <v-btn-group density="compact" variant="outlined" divided>
                        <v-btn @click="previousMonth">
                            <v-icon>mdi-chevron-left</v-icon>
                        </v-btn>
                        <v-btn @click="goToToday" min-width="80">
                            Today
                        </v-btn>
                        <v-btn @click="nextMonth">
                            <v-icon>mdi-chevron-right</v-icon>
                        </v-btn>
                    </v-btn-group>
                    <h2 class="text-xl font-semibold">{{ monthName }}</h2>
                    
                    <!-- Summary Stats -->
                    <div class="flex items-center gap-3 ml-4 text-sm">
                        <v-chip size="small" variant="tonal">
                            <v-icon start size="14">mdi-checkbox-marked-circle</v-icon>
                            {{ totalSubtasks }} subtasks
                        </v-chip>
                        <v-chip v-if="completedSubtasks > 0" size="small" color="success" variant="tonal">
                            <v-icon start size="14">mdi-check</v-icon>
                            {{ completedSubtasks }} completed
                        </v-chip>
                        <v-chip v-if="overdueSubtasks > 0" size="small" color="error" variant="tonal">
                            <v-icon start size="14">mdi-alert-circle</v-icon>
                            {{ overdueSubtasks }} overdue
                        </v-chip>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <v-btn-group density="compact" variant="outlined" divided>
                        <v-btn 
                            :variant="viewMode === 'month' ? 'flat' : 'outlined'"
                            @click="viewMode = 'month'"
                        >
                            Month
                        </v-btn>
                        <v-btn 
                            :variant="viewMode === 'week' ? 'flat' : 'outlined'"
                            @click="viewMode = 'week'"
                        >
                            Week
                        </v-btn>
                    </v-btn-group>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="flex-1 overflow-auto p-4">
                <!-- Empty State -->
                <div v-if="totalSubtasks === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                    <v-icon size="80" class="mb-4">mdi-calendar-blank-outline</v-icon>
                    <h3 class="text-xl font-semibold mb-2">No Subtasks Scheduled</h3>
                    <p class="text-sm">Subtasks with start or due dates will appear here.</p>
                </div>

                <!-- Calendar Grid -->
                <div v-else class="calendar-grid">
                    <!-- Day headers -->
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" 
                        :key="day"
                        class="calendar-header">
                        {{ day }}
                    </div>

                    <!-- Calendar days -->
                    <div 
                        v-for="(day, index) in calendarDays" 
                        :key="index"
                        class="calendar-day"
                        :class="{
                            'current-month': day.isCurrentMonth,
                            'other-month': !day.isCurrentMonth,
                            'today': isToday(day.date)
                        }"
                    >
                        <div class="day-header">
                            <span class="day-number">{{ day.date.getDate() }}</span>
                        </div>
                        
                        <div class="day-content">
                            <div 
                                v-for="subtask in getSubtasksForDate(day.date)"
                                :key="subtask.id"
                                class="calendar-task"
                                @click="handleTaskClick(subtask)"
                            >
                                <v-tooltip location="top">
                                    <template v-slot:activator="{ props: tooltipProps }">
                                        <v-chip 
                                            v-bind="tooltipProps"
                                            size="small" 
                                            :color="getStatusColor(subtask.status)"
                                            variant="flat"
                                            class="w-full text-left"
                                        >
                                            <div class="flex items-center gap-1 w-full overflow-hidden">
                                                <v-icon 
                                                    v-if="subtask.priority" 
                                                    size="12"
                                                    :color="getPriorityColor(subtask.priority)"
                                                >
                                                    mdi-flag
                                                </v-icon>
                                                <span class="text-xs truncate flex-1">{{ subtask.name }}</span>
                                                <v-icon v-if="subtask.completed_at" size="12" color="success">
                                                    mdi-check-circle
                                                </v-icon>
                                            </div>
                                        </v-chip>
                                    </template>
                                    <div class="text-sm">
                                        <div class="font-semibold">{{ subtask.name }}</div>
                                        <div class="text-xs opacity-80 mt-1">
                                            {{ subtask.task?.task_id }} - {{ subtask.task?.name }}
                                        </div>
                                        <div v-if="subtask.time_estimate" class="text-xs opacity-80 mt-1">
                                            <v-icon size="12">mdi-clock-outline</v-icon>
                                            Estimate: {{ Math.round(subtask.time_estimate / 60 * 10) / 10 }}h
                                        </div>
                                    </div>
                                </v-tooltip>
                            </div>
                            
                            <!-- Show more indicator if there are many tasks -->
                            <div 
                                v-if="getSubtasksForDate(day.date).length > 3"
                                class="text-xs text-gray-500 text-center py-1"
                            >
                                +{{ getSubtasksForDate(day.date).length - 3 }} more
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel 
            v-model="showTaskDetail" 
            :task="selectedTask"
            :workspace="workspace"
            :space="selectedTask?.task?.task_list?.space"
            :list="selectedTask?.task?.task_list"
            :parent-task="selectedTask?.task"
            :statuses="workspace.spaces?.flatMap(s => s.statuses) || []"
            :members="workspace.members || []"
            :labels="workspace.labels || []"
        />
    </MainLayout>
</template>

<style scoped>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #2d2d30;
    border: 1px solid #2d2d30;
    min-height: 600px;
}

.calendar-header {
    background-color: #252526;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-day {
    background-color: #1e1e1e;
    min-height: 100px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: background-color 0.2s;
}

.calendar-day:hover {
    background-color: #252526;
}

.calendar-day.other-month {
    background-color: #181818;
    opacity: 0.5;
}

.calendar-day.today {
    background-color: #1a2332;
    border: 2px solid #4c9aff;
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.day-number {
    font-size: 14px;
    font-weight: 600;
    color: #c5c5c5;
}

.calendar-day.today .day-number {
    background-color: #4c9aff;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.day-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    flex: 1;
}

.calendar-task {
    cursor: pointer;
    transition: transform 0.1s;
}

.calendar-task:hover {
    transform: scale(1.02);
}
</style>
