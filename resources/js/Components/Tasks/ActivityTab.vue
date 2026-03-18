<script setup>
defineProps({
    activities: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="pa-5">
        <div v-if="!activities.length" class="d-flex flex-column align-center py-10 text-grey">
            <v-icon size="48" class="mb-2" color="grey-darken-1">mdi-history</v-icon>
            <div class="text-body-2">No activity yet</div>
        </div>
        <div v-else class="activity-list">
            <div v-for="activity in activities" :key="activity.id" class="activity-item">
                <v-avatar :color="activity.user?.avatar_color" size="24">
                    <span class="text-[10px]">{{ activity.user?.initials }}</span>
                </v-avatar>
                <div class="flex-1 text-body-2">
                    <span class="font-weight-medium">{{ activity.user?.name }}</span>
                    <span class="text-grey ml-1">{{ activity.description }}</span>
                    <div class="text-caption text-grey-darken-1 mt-1">
                        {{ new Date(activity.created_at).toLocaleString() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
</style>
