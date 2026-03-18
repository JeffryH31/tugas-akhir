<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    analytics: Object,
    filters: Object,
});

const startDate = ref(props.filters?.start_date || props.analytics?.range?.start || '');
const endDate = ref(props.filters?.end_date || props.analytics?.range?.end || '');

const cards = computed(() => [
    { label: 'Total Tasks', value: props.analytics?.kpi?.tasks_total ?? 0 },
    { label: 'Completed Subtasks', value: props.analytics?.kpi?.subtasks_completed ?? 0 },
    { label: 'Overdue Subtasks', value: props.analytics?.kpi?.subtasks_overdue ?? 0 },
    { label: 'Active Sprints', value: props.analytics?.kpi?.active_sprints ?? 0 },
]);

const evmCards = computed(() => [
    { label: 'Planned Value (PV)', value: props.analytics?.evm?.pv?.toFixed(2) ?? '0.00', hint: 'Budgeted cost of work scheduled' },
    { label: 'Earned Value (EV)', value: props.analytics?.evm?.ev?.toFixed(2) ?? '0.00', hint: 'Budgeted cost of work performed' },
    { label: 'Actual Cost (AC)', value: props.analytics?.evm?.ac?.toFixed(2) ?? '0.00', hint: 'Actual cost of work performed' },
]);

const evm = computed(() => props.analytics?.evm ?? {});

const cpiColor = computed(() => (evm.value.cpi ?? 1) >= 1 ? 'success' : 'error');
const spiColor = computed(() => (evm.value.spi ?? 1) >= 1 ? 'success' : 'error');

const applyFilters = () => {
    router.get(route('workspaces.analytics', props.workspace.id), {
        start_date: startDate.value || null,
        end_date: endDate.value || null,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportCsv = () => {
    const params = new URLSearchParams();
    if (startDate.value) params.set('start_date', startDate.value);
    if (endDate.value) params.set('end_date', endDate.value);
    window.location.href = `${route('workspaces.analytics.export', props.workspace.id)}?${params.toString()}`;
};
</script>

<template>
    <MainLayout :title="`${workspace?.name} Analytics`">
        <div class="p-4 md:p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-5 gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Workspace Analytics</h1>
                    <p class="text-gray-400 text-sm">Insights and throughput trends for this workspace.</p>
                </div>
                <div class="flex gap-2">
                    <v-text-field v-model="startDate" type="date" label="Start" density="compact" hide-details
                        variant="outlined" />
                    <v-text-field v-model="endDate" type="date" label="End" density="compact" hide-details
                        variant="outlined" />
                    <v-btn color="primary" @click="applyFilters">Apply</v-btn>
                    <v-btn variant="outlined" @click="exportCsv">Export CSV</v-btn>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <v-card v-for="card in cards" :key="card.label" class="pa-4">
                    <div class="text-xs text-gray-400">{{ card.label }}</div>
                    <div class="text-2xl font-semibold mt-1">{{ card.value }}</div>
                </v-card>
            </div>

            <!-- EVM KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <v-card v-for="card in evmCards" :key="card.label" class="pa-4">
                    <div class="text-xs text-gray-400">{{ card.label }}</div>
                    <div class="text-2xl font-semibold mt-1">${{ card.value }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ card.hint }}</div>
                </v-card>
            </div>

            <!-- EVM Summary -->
            <v-card class="mb-5">
                <v-card-title>Earned Value Management Summary</v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-400">Cost Variance (CV)</div>
                            <div class="text-lg font-semibold"
                                :class="(evm.cv ?? 0) >= 0 ? 'text-green-500' : 'text-red-500'">
                                ${{ (evm.cv ?? 0).toFixed(2) }}
                            </div>
                            <div class="text-xs text-gray-500">{{ (evm.cv ?? 0) >= 0 ? 'Under budget' : 'Over budget' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Schedule Variance (SV)</div>
                            <div class="text-lg font-semibold"
                                :class="(evm.sv ?? 0) >= 0 ? 'text-green-500' : 'text-red-500'">
                                ${{ (evm.sv ?? 0).toFixed(2) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ (evm.sv ?? 0) >= 0 ? 'Ahead of schedule' : 'Behind schedule' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Cost Performance Index (CPI)</div>
                            <v-chip :color="cpiColor" size="small" class="mt-1">{{ (evm.cpi ?? 1).toFixed(2) }}</v-chip>
                            <div class="text-xs text-gray-500 mt-1">{{ (evm.cpi ?? 1) >= 1 ? 'Efficient' : 'Inefficient'
                            }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Schedule Performance Index (SPI)</div>
                            <v-chip :color="spiColor" size="small" class="mt-1">{{ (evm.spi ?? 1).toFixed(2) }}</v-chip>
                            <div class="text-xs text-gray-500 mt-1">{{ (evm.spi ?? 1) >= 1 ? 'On schedule' : 'Delayed'
                            }}</div>
                        </div>
                    </div>
                </v-card-text>
            </v-card>

            <v-card class="mb-5">
                <v-card-title>Completion Trend (Last 14 days)</v-card-title>
                <v-divider />
                <v-table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Completed Subtasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in (analytics?.completion_trend || [])" :key="row.date">
                            <td>{{ row.date }}</td>
                            <td>{{ row.completed }}</td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>

            <v-card>
                <v-card-title>Throughput by Space</v-card-title>
                <v-divider />
                <v-table>
                    <thead>
                        <tr>
                            <th>Space</th>
                            <th>Total Tasks</th>
                            <th>Archived Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in (analytics?.throughput_by_space || [])" :key="row.id">
                            <td>{{ row.name }}</td>
                            <td>{{ row.tasks_count }}</td>
                            <td>{{ row.archived_tasks_count }}</td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
        </div>
    </MainLayout>
</template>
