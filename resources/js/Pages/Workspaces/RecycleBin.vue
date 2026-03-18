<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    trash: Object,
});

const activeTab = ref('tasks');
const restoring = ref(false);

const totals = computed(() => ({
    lists: props.trash?.lists?.length || 0,
    tasks: props.trash?.tasks?.length || 0,
    subtasks: props.trash?.subtasks?.length || 0,
    timeEntries: props.trash?.time_entries?.length || 0,
}));

const restoreItem = (type, id) => {
    if (restoring.value) return;
    restoring.value = true;

    router.post(route('workspaces.recycle-bin.restore', props.workspace.id), { type, id }, {
        preserveScroll: true,
        onFinish: () => { restoring.value = false; },
    });
};
</script>

<template>
    <MainLayout :title="`${workspace?.name} Recycle Bin`">
        <div class="p-4 md:p-6">
            <h1 class="text-2xl font-bold mb-1">Recycle Bin</h1>
            <p class="text-gray-400 text-sm mb-4">Restore accidentally deleted items.</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <v-card class="pa-3"><div class="text-xs text-gray-400">Lists</div><div class="text-xl font-semibold">{{ totals.lists }}</div></v-card>
                <v-card class="pa-3"><div class="text-xs text-gray-400">Tasks</div><div class="text-xl font-semibold">{{ totals.tasks }}</div></v-card>
                <v-card class="pa-3"><div class="text-xs text-gray-400">Subtasks</div><div class="text-xl font-semibold">{{ totals.subtasks }}</div></v-card>
                <v-card class="pa-3"><div class="text-xs text-gray-400">Time Entries</div><div class="text-xl font-semibold">{{ totals.timeEntries }}</div></v-card>
            </div>

            <v-tabs v-model="activeTab" class="mb-3">
                <v-tab value="tasks">Tasks</v-tab>
                <v-tab value="subtasks">Subtasks</v-tab>
                <v-tab value="lists">Lists</v-tab>
                <v-tab value="time_entries">Time Entries</v-tab>
            </v-tabs>

            <v-window v-model="activeTab">
                <v-window-item value="tasks">
                    <v-card>
                        <v-table>
                            <thead><tr><th>Task</th><th>List / Space</th><th>Deleted At</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="task in (trash?.tasks || [])" :key="task.id">
                                    <td>{{ task.name }}</td>
                                    <td>{{ task.task_list?.name }} / {{ task.task_list?.space?.name }}</td>
                                    <td>{{ task.deleted_at }}</td>
                                    <td><v-btn size="small" color="primary" :loading="restoring" @click="restoreItem('task', task.id)">Restore</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="subtasks">
                    <v-card>
                        <v-table>
                            <thead><tr><th>Subtask</th><th>Task</th><th>Deleted At</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="subtask in (trash?.subtasks || [])" :key="subtask.id">
                                    <td>{{ subtask.name }}</td>
                                    <td>{{ subtask.task?.name }}</td>
                                    <td>{{ subtask.deleted_at }}</td>
                                    <td><v-btn size="small" color="primary" :loading="restoring" @click="restoreItem('subtask', subtask.id)">Restore</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="lists">
                    <v-card>
                        <v-table>
                            <thead><tr><th>List</th><th>Space</th><th>Deleted At</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="list in (trash?.lists || [])" :key="list.id">
                                    <td>{{ list.name }}</td>
                                    <td>{{ list.space?.name }}</td>
                                    <td>{{ list.deleted_at }}</td>
                                    <td><v-btn size="small" color="primary" :loading="restoring" @click="restoreItem('list', list.id)">Restore</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>

                <v-window-item value="time_entries">
                    <v-card>
                        <v-table>
                            <thead><tr><th>User</th><th>Subtask</th><th>Minutes</th><th>Deleted At</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="entry in (trash?.time_entries || [])" :key="entry.id">
                                    <td>{{ entry.user?.name }}</td>
                                    <td>{{ entry.subtask?.name }}</td>
                                    <td>{{ entry.duration }}</td>
                                    <td>{{ entry.deleted_at }}</td>
                                    <td><v-btn size="small" color="primary" :loading="restoring" @click="restoreItem('time_entry', entry.id)">Restore</v-btn></td>
                                </tr>
                            </tbody>
                        </v-table>
                    </v-card>
                </v-window-item>
            </v-window>
        </div>
    </MainLayout>
</template>
