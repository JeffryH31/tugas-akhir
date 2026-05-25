<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const emit = defineEmits(['item-open']);

const currentDate = ref(new Date());
const subView = ref('month'); // 'month' | 'week'

// Date helpers
const toDateOnly = (value) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    d.setHours(0, 0, 0, 0);
    return d;
};

const isSameDate = (a, b) => a && b && a.getTime() === b.getTime();

const isDateToday = (date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
};

const getWeekStart = (date) => {
    const copy = new Date(date);
    copy.setDate(copy.getDate() - copy.getDay());
    copy.setHours(0, 0, 0, 0);
    return copy;
};

const daysBetween = (start, end) =>
    Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

const rangesOverlap = (startA, endA, startB, endB) =>
    startA <= endB && startB <= endA;

const getItemRange = (item) => {
    const start = toDateOnly(item.start_date || item.due_date);
    const end = toDateOnly(item.due_date || item.start_date);
    if (!start || !end) return null;
    return start <= end ? { start, end } : { start: end, end: start };
};

const getItemStatus = (item) => props.statuses.find((s) => s.id === item.status_id);

// Header label
const calendarYear = computed(() => currentDate.value.getFullYear());
const calendarMonth = computed(() => currentDate.value.getMonth());

const monthLabel = computed(() =>
    currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
);

const weekLabel = computed(() => {
    const start = getWeekStart(currentDate.value);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    const fmt = { month: 'short', day: 'numeric' };
    return `${start.toLocaleDateString('en-US', fmt)} - ${end.toLocaleDateString('en-US', { ...fmt, year: 'numeric' })}`;
});

const calendarTitle = computed(() =>
    subView.value === 'month' ? monthLabel.value : weekLabel.value
);

// Grids
const calendarDays = computed(() => {
    const days = [];
    const firstDay = new Date(calendarYear.value, calendarMonth.value, 1).getDay();
    const daysInMonth = new Date(calendarYear.value, calendarMonth.value + 1, 0).getDate();
    const prevMonthDays = new Date(calendarYear.value, calendarMonth.value, 0).getDate();

    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value - 1, prevMonthDays - i),
            isCurrentMonth: false,
        });
    }
    for (let i = 1; i <= daysInMonth; i++) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value, i),
            isCurrentMonth: true,
        });
    }
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        days.push({
            date: new Date(calendarYear.value, calendarMonth.value + 1, i),
            isCurrentMonth: false,
        });
    }
    return days;
});

const calendarWeeks = computed(() => {
    const weeks = [];
    for (let i = 0; i < calendarDays.value.length; i += 7) {
        weeks.push(calendarDays.value.slice(i, i + 7));
    }
    return weeks;
});

const weekDays = computed(() => {
    const start = getWeekStart(currentDate.value);
    return Array.from({ length: 7 }, (_, i) => {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        return {
            date,
            isCurrentMonth: date.getMonth() === currentDate.value.getMonth(),
        };
    });
});

// Pre-computed item maps for performance
// Map<dateKey, items[]> for single-day items
const dateKey = (date) => {
    const d = toDateOnly(date);
    return d ? `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}` : '';
};

const singleDayItemsByDate = computed(() => {
    const map = new Map();
    for (const item of props.items) {
        const range = getItemRange(item);
        if (!range) continue;
        if (range.start.getTime() !== range.end.getTime()) continue;
        const key = dateKey(range.start);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(item);
    }
    return map;
});

const multiDayItems = computed(() =>
    props.items
        .map((item) => ({ item, range: getItemRange(item) }))
        .filter(({ range }) => range && range.start.getTime() !== range.end.getTime())
);

const getSingleDayItems = (date) => singleDayItemsByDate.value.get(dateKey(date)) || [];

const getVisibleItemsForDate = (date, limit = 1) => getSingleDayItems(date).slice(0, limit);

const getOverflowItemsCount = (date, limit = 1) => {
    const total = getSingleDayItems(date).length;
    return total > limit ? total - limit : 0;
};

// Cache bars per week (keyed by first day's timestamp)
const buildWeekBars = (days) => {
    const weekStart = toDateOnly(days[0]?.date);
    const weekEnd = toDateOnly(days[6]?.date);
    if (!weekStart || !weekEnd) return [];

    const candidates = multiDayItems.value
        .filter(({ range }) => rangesOverlap(range.start, range.end, weekStart, weekEnd))
        .sort((a, b) => {
            if (a.range.start.getTime() !== b.range.start.getTime()) {
                return a.range.start.getTime() - b.range.start.getTime();
            }
            return b.range.end.getTime() - a.range.end.getTime();
        });

    const lanes = [];
    const bars = [];

    for (const { item, range } of candidates) {
        const visualStart = range.start < weekStart ? weekStart : range.start;
        const visualEnd = range.end > weekEnd ? weekEnd : range.end;
        const startCol = daysBetween(weekStart, visualStart) + 1;
        const endCol = daysBetween(weekStart, visualEnd) + 1;

        let laneIndex = 0;
        while (
            lanes[laneIndex] &&
            lanes[laneIndex].some((seg) => !(endCol < seg.startCol || startCol > seg.endCol))
        ) {
            laneIndex += 1;
        }
        if (!lanes[laneIndex]) lanes[laneIndex] = [];
        lanes[laneIndex].push({ startCol, endCol });

        bars.push({
            item,
            row: laneIndex,
            startCol,
            endCol,
            startsBeforeWeek: range.start < weekStart,
            endsAfterWeek: range.end > weekEnd,
            color: getItemStatus(item)?.color || '#6366F1',
        });
    }
    return bars;
};

const weekBarsByIndex = computed(() => calendarWeeks.value.map(buildWeekBars));
const standaloneWeekBars = computed(() => buildWeekBars(weekDays.value));

// Stats
const completionRate = computed(() => {
    const total = props.items.length;
    if (!total) return 0;
    const completed = props.items.filter((i) => i.completed_at).length;
    return Math.round((completed / total) * 100);
});

const dueThisWeek = computed(() => {
    const start = getWeekStart(currentDate.value);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return props.items.filter((item) => {
        const due = toDateOnly(item.due_date);
        return due && due >= start && due <= end;
    }).length;
});

// Navigation
const previousMonth = () => {
    currentDate.value = new Date(calendarYear.value, calendarMonth.value - 1, 1);
};
const nextMonth = () => {
    currentDate.value = new Date(calendarYear.value, calendarMonth.value + 1, 1);
};
const previousWeek = () => {
    const d = new Date(currentDate.value);
    d.setDate(d.getDate() - 7);
    currentDate.value = d;
};
const nextWeek = () => {
    const d = new Date(currentDate.value);
    d.setDate(d.getDate() + 7);
    currentDate.value = d;
};
const previousPeriod = () => (subView.value === 'month' ? previousMonth() : previousWeek());
const nextPeriod = () => (subView.value === 'month' ? nextMonth() : nextWeek());
const goToToday = () => {
    currentDate.value = new Date();
};

const onItemOpen = (item) => emit('item-open', item);
</script>

<template>
    <div class="calendar-view">
        <div class="calendar-container">
            <!-- Header -->
            <div class="calendar-header">
                <v-btn-group density="compact" variant="outlined" divided>
                    <v-btn @click="previousPeriod">
                        <v-icon>mdi-chevron-left</v-icon>
                    </v-btn>
                    <v-btn min-width="80" @click="goToToday">Today</v-btn>
                    <v-btn @click="nextPeriod">
                        <v-icon>mdi-chevron-right</v-icon>
                    </v-btn>
                </v-btn-group>

                <h2 class="text-xl font-semibold">{{ calendarTitle }}</h2>

                <v-btn-toggle v-model="subView" mandatory density="compact" variant="outlined">
                    <v-btn value="month" size="small">
                        <v-icon size="14" class="mr-1">mdi-calendar-month</v-icon>
                        Month
                    </v-btn>
                    <v-btn value="week" size="small">
                        <v-icon size="14" class="mr-1">mdi-calendar-week</v-icon>
                        Week
                    </v-btn>
                </v-btn-toggle>

                <div class="ml-auto flex items-center gap-2">
                    <v-chip size="small" variant="tonal">
                        <v-icon start size="14">mdi-check-circle-outline</v-icon>
                        {{ completionRate }}% complete
                    </v-chip>
                    <v-chip size="small" color="warning" variant="tonal">
                        <v-icon start size="14">mdi-calendar-clock</v-icon>
                        {{ dueThisWeek }} due this week
                    </v-chip>
                </div>
            </div>

            <!-- Month grid -->
            <div v-if="subView === 'month'" class="calendar-month-grid">
                <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                    class="calendar-day-header">
                    {{ day }}
                </div>

                <div v-for="(week, weekIndex) in calendarWeeks" :key="`week-${weekIndex}`" class="calendar-week-row">
                    <div class="week-bars-overlay" :style="{
                        gridTemplateRows: `repeat(${Math.max(weekBarsByIndex[weekIndex].length, 1)}, 22px)`
                    }">
                        <div v-for="bar in weekBarsByIndex[weekIndex]"
                            :key="`bar-${weekIndex}-${bar.item.id}-${bar.row}`" class="calendar-span-bar" :style="{
                                gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                                gridRow: bar.row + 1,
                                backgroundColor: bar.color,
                            }" @click="onItemOpen(bar.item)">
                            <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                            <span class="span-name">{{ bar.item.name }}</span>
                            <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                                :color="bar.item.assignees[0].avatar_color || 'primary'">
                                <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                            </v-avatar>
                            <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                            <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                        </div>
                    </div>

                    <div class="week-days-grid">
                        <div v-for="(day, dayIndex) in week" :key="`day-${weekIndex}-${dayIndex}`" class="calendar-cell"
                            :class="{
                                'current-month': day.isCurrentMonth,
                                'other-month': !day.isCurrentMonth,
                                today: isDateToday(day.date),
                            }">
                            <div class="cell-header">
                                <span class="day-num">{{ day.date.getDate() }}</span>
                            </div>
                            <div class="cell-tasks">
                                <div v-for="item in getVisibleItemsForDate(day.date, 1)"
                                    :key="`single-${weekIndex}-${dayIndex}-${item.id}`" class="calendar-item"
                                    @click="onItemOpen(item)">
                                    <div class="item-dot"
                                        :style="{ backgroundColor: getItemStatus(item)?.color || '#6366F1' }" />
                                    <span class="item-name">{{ item.name }}</span>
                                    <v-icon v-if="item.completed_at" size="12" color="success">mdi-check-circle</v-icon>
                                </div>
                                <div v-if="getOverflowItemsCount(day.date, 1) > 0" class="calendar-overflow">
                                    +{{ getOverflowItemsCount(day.date, 1) }} more
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Week grid -->
            <div v-else class="calendar-week-only">
                <div class="mini-calendar-grid week-grid">
                    <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="`wk-${day}`"
                        class="calendar-day-header">
                        {{ day }}
                    </div>
                    <div v-for="(day, index) in weekDays" :key="`wkday-${index}`" class="calendar-cell" :class="{
                        'current-month': day.isCurrentMonth,
                        'other-month': !day.isCurrentMonth,
                        today: isDateToday(day.date),
                    }">
                        <div class="cell-header">
                            <span class="day-num">{{ day.date.getDate() }}</span>
                        </div>
                    </div>
                </div>

                <div class="week-bars-grid week-bars-standalone"
                    :style="{ gridTemplateRows: `repeat(${Math.max(standaloneWeekBars.length, 1)}, 24px)` }">
                    <div v-for="bar in standaloneWeekBars" :key="`wkbar-${bar.item.id}-${bar.row}`"
                        class="calendar-span-bar" :style="{
                            gridColumn: `${bar.startCol} / ${bar.endCol + 1}`,
                            gridRow: bar.row + 1,
                            backgroundColor: bar.color,
                        }" @click="onItemOpen(bar.item)">
                        <v-icon v-if="bar.startsBeforeWeek" size="12">mdi-chevron-left</v-icon>
                        <span class="span-name">{{ bar.item.name }}</span>
                        <v-avatar v-if="bar.item.assignees?.[0]" size="16"
                            :color="bar.item.assignees[0].avatar_color || 'primary'">
                            <span class="text-[10px]">{{ bar.item.assignees[0].initials }}</span>
                        </v-avatar>
                        <v-icon v-if="bar.item.completed_at" size="12">mdi-check</v-icon>
                        <v-icon v-if="bar.endsAfterWeek" size="12">mdi-chevron-right</v-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.calendar-view {
    padding: 24px;
    height: 100%;
    overflow: auto;
}

.calendar-container {
    max-width: 1400px;
    margin: 0 auto;
}

.calendar-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.mini-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #2d2d30;
    border: 1px solid #2d2d30;
    border-radius: 8px;
    overflow: hidden;
}

.mini-calendar-grid.week-grid {
    grid-template-columns: repeat(7, minmax(180px, 1fr));
    overflow-x: auto;
}

.calendar-month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background-color: #262a33;
    border: 1px solid #313643;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.calendar-week-row {
    grid-column: 1 / -1;
    background: #1b1f27;
    border-top: 1px solid #2c3340;
    position: relative;
}

.week-days-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 1px;
    background: #2c3340;
    position: relative;
    z-index: 1;
}

.week-bars-overlay {
    position: absolute;
    top: 30px;
    left: 0;
    right: 0;
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 3px;
    padding: 3px 8px 4px;
    pointer-events: none;
    z-index: 2;
    align-items: center;
    min-width: 0;
}

.week-bars-standalone {
    margin-top: 8px;
    border: 1px solid #2d2d30;
    border-radius: 8px;
}

.calendar-day-header {
    background-color: #20242d;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #8b949e;
}

.calendar-cell {
    background-color: #191d25;
    min-height: 102px;
    padding: 34px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border: 1px solid #262d3a;
    position: relative;
}

.calendar-cell.other-month {
    background-color: #181818;
    opacity: 0.5;
}

.calendar-cell.today {
    background-color: #182438;
    border: 2px solid #4c9aff;
}

.cell-header {
    position: absolute;
    top: 6px;
    left: 8px;
    right: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 3;
    pointer-events: none;
}

.day-num {
    font-size: 14px;
    font-weight: 600;
    color: #d7dce5;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    padding: 1px 8px;
    line-height: 20px;
}

.calendar-cell.today .day-num {
    background-color: #4c9aff;
    border-color: transparent;
    color: white;
}

.cell-tasks {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
    flex: 1;
    position: relative;
    z-index: 1;
}

.calendar-item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    background-color: #242b37;
    border: 1px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 11px;
}

.calendar-item:hover {
    background-color: #2e3747;
    border-color: #3b82f6;
}

.item-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.item-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #c5c5c5;
}

.calendar-overflow {
    font-size: 11px;
    color: #b4bdca;
    padding: 2px 6px;
    border-radius: 6px;
    background: #273245;
    width: fit-content;
}

.calendar-span-bar {
    height: 18px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 7px;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    pointer-events: auto;
    position: relative;
    z-index: 2;
    line-height: 1;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}

.calendar-span-bar:hover {
    filter: brightness(1.04);
}

.span-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}
</style>
