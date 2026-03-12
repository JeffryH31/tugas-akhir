<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { confirm: confirmDialog } = useConfirmDialog();

const props = defineProps({
    workspace: Object,
    space: Object,
    sprints: Array,
    activeSprint: Object,
    statistics: Object,
    velocity: Object,
});

const showCreateSprint = ref(false);
const editingSprintId = ref(null);
const isSaving = ref(false);
const sprintForm = ref({
    name: '',
    goal: '',
    start_date: '',
    end_date: '',
});
const formErrors = ref({
    name: '',
    start_date: '',
    end_date: '',
});

const resetForm = () => {
    sprintForm.value = {
        name: '',
        goal: '',
        start_date: '',
        end_date: '',
    };
    formErrors.value = {
        name: '',
        start_date: '',
        end_date: '',
    };
    editingSprintId.value = null;
};

const validateForm = () => {
    let isValid = true;
    formErrors.value = {
        name: '',
        start_date: '',
        end_date: '',
    };

    if (!sprintForm.value.name?.trim()) {
        formErrors.value.name = 'Sprint name is required';
        isValid = false;
    }

    if (!sprintForm.value.start_date) {
        formErrors.value.start_date = 'Start date is required';
        isValid = false;
    }

    if (!sprintForm.value.end_date) {
        formErrors.value.end_date = 'End date is required';
        isValid = false;
    }

    if (sprintForm.value.start_date && sprintForm.value.end_date) {
        if (new Date(sprintForm.value.end_date) <= new Date(sprintForm.value.start_date)) {
            formErrors.value.end_date = 'End date must be after start date';
            isValid = false;
        }
    }

    return isValid;
};

const editSprint = (sprint) => {
    editingSprintId.value = sprint.id;
    sprintForm.value = {
        name: sprint.name,
        goal: sprint.goal || '',
        start_date: sprint.start_date,
        end_date: sprint.end_date,
    };
    showCreateSprint.value = true;
};

const saveSprint = () => {
    if (!validateForm() || isSaving.value) {
        if (!validateForm()) window.showSnackbar('Please fill in all required fields', 'error');
        return;
    }
    isSaving.value = true;

    if (editingSprintId.value) {
        router.patch(
            route('sprints.update', [props.workspace.id, props.space.id, editingSprintId.value]),
            sprintForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showCreateSprint.value = false;
                    resetForm();
                    window.showSnackbar('Sprint updated!', 'success');
                },
                onFinish: () => { isSaving.value = false; }
            }
        );
    } else {
        router.post(
            route('sprints.store', [props.workspace.id, props.space.id]),
            sprintForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showCreateSprint.value = false;
                    resetForm();
                    window.showSnackbar('Sprint created!', 'success');
                },
                onFinish: () => { isSaving.value = false; }
            }
        );
    }
};

const isSprintActive = (sprint) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const start = new Date(sprint.start_date);
    const end = new Date(sprint.end_date);
    return today >= start && today <= end;
};

const isSprintCompleted = (sprint) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(sprint.end_date);
    return today > end;
};

const deleteSprint = async (sprint) => {
    if (await confirmDialog('Are you sure you want to delete this sprint? All tasks will be moved to backlog.', 'Delete Sprint')) {
        router.delete(
            route('sprints.destroy', [props.workspace.id, props.space.id, sprint.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    window.showSnackbar('Sprint deleted!', 'success');
                },
            }
        );
    }
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};
</script>

<template>
    <MainLayout :title="`Sprints - ${space.name}`">
        <div class="h-full flex flex-col bg-[#1e1e1e]">
            <!-- Header -->
            <div class="border-b border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-white">Sprints</h1>
                        <p class="text-gray-400 text-sm mt-1">Manage your sprint planning and tracking</p>
                    </div>
                    <v-btn color="primary" prepend-icon="mdi-plus" @click="showCreateSprint = true">
                        Create Sprint
                    </v-btn>
                </div>

                <!-- Active Sprint Summary -->
                <div v-if="activeSprint" class="grid grid-cols-4 gap-4 mt-6">
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Active Sprint</div>
                        <div class="text-xl font-bold text-white mt-1">{{ activeSprint.name }}</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Completion</div>
                        <div class="text-xl font-bold text-primary mt-1">{{ statistics?.completion_rate }}%</div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Tasks</div>
                        <div class="text-xl font-bold text-white mt-1">
                            {{ statistics?.completed_tasks }} / {{ statistics?.total_tasks }}
                        </div>
                    </div>
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400">Days Remaining</div>
                        <div class="text-xl font-bold text-white mt-1">{{ statistics?.remaining_days }}</div>
                    </div>
                </div>
            </div>

            <!-- Sprint List -->
            <div class="flex-1 overflow-auto p-6">
                <div class="grid gap-4">
                    <div v-for="sprint in sprints" :key="sprint.id"
                        class="bg-[#2D2D2D] rounded-lg p-6 hover:bg-[#333333] transition-colors cursor-pointer"
                        @click="router.visit(route('sprints.show', [workspace.id, space.id, sprint.id]))">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-white">{{ sprint.name }}</h3>
                                    <v-chip v-if="isSprintActive(sprint)" color="success" size="small">
                                        Active
                                    </v-chip>
                                    <v-chip v-else-if="isSprintCompleted(sprint)" color="default"
                                        size="small">
                                        Completed
                                    </v-chip>
                                    <v-chip v-else color="info" size="small">
                                        Planned
                                    </v-chip>
                                </div>
                                <p v-if="sprint.goal" class="text-gray-400 text-sm mb-3">{{ sprint.goal }}</p>
                                <div class="flex items-center gap-6 text-sm text-gray-400">
                                    <div class="flex items-center gap-2">
                                        <v-icon size="16">mdi-calendar-start</v-icon>
                                        {{ formatDate(sprint.start_date) }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <v-icon size="16">mdi-calendar-end</v-icon>
                                        {{ formatDate(sprint.end_date) }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <v-icon size="16">mdi-checkbox-marked-circle-outline</v-icon>
                                        {{ sprint.tasks_count }} tasks
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2" @click.stop>
                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn v-bind="props" icon="mdi-dots-vertical" size="small" variant="text" />
                                    </template>
                                    <v-card color="surface">
                                        <v-list density="compact">
                                            <v-list-item prepend-icon="mdi-pencil" @click="editSprint(sprint)">
                                                Edit Sprint
                                            </v-list-item>
                                            <v-list-item class="text-error" prepend-icon="mdi-delete"
                                                @click="deleteSprint(sprint)">
                                                Delete Sprint
                                            </v-list-item>
                                        </v-list>
                                    </v-card>
                                </v-menu>
                            </div>
                        </div>
                    </div>

                    <div v-if="sprints.length === 0" class="text-center py-12 text-gray-500">
                        <v-icon size="64" class="mb-4">mdi-calendar-clock</v-icon>
                        <p class="text-lg">No sprints yet</p>
                        <p class="text-sm mt-2">Create your first sprint to start planning</p>
                    </div>
                </div>

                <!-- Velocity Chart -->
                <div v-if="velocity && velocity.sprints.length > 0" class="mt-8 bg-[#2D2D2D] rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Sprint Velocity</h3>
                    <div class="text-sm text-gray-400 mb-4">
                        Average velocity: <span class="text-white font-semibold">{{ velocity.average_velocity }}</span>
                        tasks/sprint
                    </div>
                    <div class="flex gap-4">
                        <div v-for="sprint in velocity.sprints" :key="sprint.sprint_name" class="flex-1">
                            <div class="text-sm text-gray-400 mb-2">{{ sprint.sprint_name }}</div>
                            <div class="bg-[#1e1e1e] rounded h-32 flex items-end p-2">
                                <div class="w-full bg-primary rounded-t"
                                    :style="{ height: `${(sprint.completed_tasks / Math.max(...velocity.sprints.map(s => s.completed_tasks))) * 100}%` }" />
                            </div>
                            <div class="text-center mt-2 text-white font-semibold">{{ sprint.completed_tasks }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Sprint Dialog -->
        <v-dialog v-model="showCreateSprint" max-width="600">
            <v-card color="surface">
                <v-card-title>{{ editingSprintId ? 'Edit Sprint' : 'Create New Sprint' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="sprintForm.name" label="Sprint Name" variant="outlined" density="compact"
                        bg-color="#1e1e1e" class="mb-4" :error-messages="formErrors.name" required />
                    <v-textarea v-model="sprintForm.goal" label="Sprint Goal (Optional)" variant="outlined"
                        density="compact" bg-color="#1e1e1e" rows="3" class="mb-4" />
                    <div class="grid grid-cols-2 gap-4">
                        <v-text-field v-model="sprintForm.start_date" label="Start Date" type="date" variant="outlined"
                            density="compact" bg-color="#1e1e1e" :error-messages="formErrors.start_date" required />
                        <v-text-field v-model="sprintForm.end_date" label="End Date" type="date" variant="outlined"
                            density="compact" bg-color="#1e1e1e" :error-messages="formErrors.end_date" required />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateSprint = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isSaving" @click="saveSprint">
                        {{ editingSprintId ? 'Update' : 'Create' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

