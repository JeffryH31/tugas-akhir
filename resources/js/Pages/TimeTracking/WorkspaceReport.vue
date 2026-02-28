<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    report: Object,
});

// Format duration (input is in minutes)
const formatDuration = (minutes) => {
    if (!minutes) return '0h 0m';
    const hours = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    return `${hours}h ${mins}m`;
};

// Calculate percentage for bar chart
const maxUserMinutes = computed(() => {
    if (!props.report?.by_user?.length) return 1;
    return Math.max(...props.report.by_user.map(u => u.total_minutes), 1);
});

const maxSpaceMinutes = computed(() => {
    if (!props.report?.by_space?.length) return 1;
    return Math.max(...props.report.by_space.map(s => s.total_minutes), 1);
});

const getUserPercentage = (minutes) => {
    return (minutes / maxUserMinutes.value) * 100;
};

const getSpacePercentage = (minutes) => {
    return (minutes / maxSpaceMinutes.value) * 100;
};
</script>

<template>
    <Head title="Time Report" />

    <MainLayout>
        <div class="h-full bg-[#1E1E1E] overflow-auto">
            <!-- Header -->
            <div class="bg-[#252526] border-b border-gray-800 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-white">Time Report</h1>
                        <p class="text-sm text-gray-400 mt-1">{{ workspace?.name }}</p>
                    </div>
                    <v-btn
                        variant="text"
                        prepend-icon="mdi-arrow-left"
                        @click="router.visit(route('time-tracking.index'))"
                    >
                        Back to Time Tracking
                    </v-btn>
                </div>
            </div>

            <div class="p-6 max-w-5xl mx-auto">
                <!-- Total Summary -->
                <v-card class="bg-[#252526] mb-6" rounded="lg">
                    <v-card-text class="text-center py-8">
                        <div class="text-4xl font-bold text-white mb-2">
                            {{ report?.total_formatted || '0m' }}
                        </div>
                        <div class="text-gray-400">Total Time Tracked</div>
                    </v-card-text>
                </v-card>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- By User -->
                    <v-card class="bg-[#252526]" rounded="lg">
                        <v-card-title class="text-sm text-gray-400 font-medium">
                            <v-icon start size="18">mdi-account-group</v-icon>
                            By Member
                        </v-card-title>
                        <v-card-text>
                            <div v-if="report?.by_user?.length" class="space-y-4">
                                <div v-for="user in report.by_user" :key="user.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-white">{{ user.name }}</span>
                                        <span class="text-gray-400 font-mono">{{ formatDuration(user.total_minutes) }}</span>
                                    </div>
                                    <div class="w-full bg-[#1E1E1E] rounded-full h-2">
                                        <div
                                            class="bg-primary h-2 rounded-full transition-all"
                                            :style="{ width: getUserPercentage(user.total_minutes) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No data available
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- By Space -->
                    <v-card class="bg-[#252526]" rounded="lg">
                        <v-card-title class="text-sm text-gray-400 font-medium">
                            <v-icon start size="18">mdi-folder-multiple</v-icon>
                            By Space
                        </v-card-title>
                        <v-card-text>
                            <div v-if="report?.by_space?.length" class="space-y-4">
                                <div v-for="space in report.by_space" :key="space.id">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-white">{{ space.name }}</span>
                                        <span class="text-gray-400 font-mono">{{ formatDuration(space.total_minutes) }}</span>
                                    </div>
                                    <div class="w-full bg-[#1E1E1E] rounded-full h-2">
                                        <div
                                            class="bg-info h-2 rounded-full transition-all"
                                            :style="{ width: getSpacePercentage(space.total_minutes) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center text-gray-500 py-8">
                                No data available
                            </div>
                        </v-card-text>
                    </v-card>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
