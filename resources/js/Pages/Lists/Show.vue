<script setup>
/**
 * List View Page - Kanban Board Style
 * 
 * Features:
 * - Board view with status columns
 * - Drag and drop tasks
 * - Task detail panel
 * - Add/edit tasks
 */
import { ref, computed, provide, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import StatusColumn from '@/Components/Tasks/StatusColumn.vue';
import TaskDetailPanel from '@/Components/Tasks/TaskDetailPanel.vue';

const props = defineProps({
    workspace: Object,
    space: Object,
    list: Object,
    tasksByStatus: Object,
    statuses: Array,
});

const page = usePage();

const initializeTasksByStatus = () => {
    const result = {};
    if (props.statuses) {
        props.statuses.forEach(status => {
            result[status.id] = Array.isArray(props.tasksByStatus?.[status.id])
                ? [...props.tasksByStatus[status.id]]
                : [];
        });
    }
    return result;
};

const localTasksByStatus = ref(initializeTasksByStatus());

// Watch for changes in props.tasksByStatus to update local state
watch(() => props.tasksByStatus, () => {
    localTasksByStatus.value = initializeTasksByStatus();
}, { deep: true });

// Selected task for detail panel
const selectedTask = ref(null);
const showTaskDetail = ref(false);

// View mode
const viewMode = ref('board'); // board, list, calendar

// Filters
const filterStatus = ref([]);
const filterPriority = ref([]);
const filterAssignee = ref([]);
const searchQuery = ref('');

// Members and priorities from workspace
const members = computed(() => props.workspace?.members || []);
const priorities = computed(() => props.workspace?.priorities || []);
const labels = computed(() => props.workspace?.labels || []);

// Handle task moved between columns
const handleTaskMoved = ({ task, statusId, newIndex }) => {
    router.patch(
        route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, task.id]),
        { status_id: statusId },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Task moved successfully!', 'success');
                }
                router.reload({ only: ['tasksByStatus'] });
            }
        }
    );
};

// Handle task complete
const handleTaskComplete = (task) => {
    router.post(
        route('tasks.complete', [props.workspace.id, props.space.id, props.list.id, task.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Task completed!', 'success');
                }
                router.reload({ only: ['tasksByStatus'] });
            }
        }
    );
};

// Handle task open
const handleTaskOpen = (task) => {
    selectedTask.value = task;
    showTaskDetail.value = true;
};

// Handle add task
const handleAddTask = ({ name, status_id }) => {
    router.post(
        route('tasks.store', [props.workspace.id, props.space.id, props.list.id]),
        { name, status_id },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Task added successfully!', 'success');
                }
                router.reload({ only: ['tasksByStatus'] });
            }
        }
    );
};

// Add status dialog
const showAddStatus = ref(false);
const newStatusName = ref('');
const newStatusColor = ref('#6366F1');

const addStatus = () => {
    if (!newStatusName.value.trim()) return;

    router.post(
        route('spaces.statuses.add', [props.workspace.id, props.space.id]),
        {
            name: newStatusName.value.trim(),
            color: newStatusColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Status added successfully!', 'success');
                }
            }
        }
    );

    newStatusName.value = '';
    showAddStatus.value = false;
};

// Color options
const colorOptions = [
    '#6366F1', '#8B5CF6', '#EC4899', '#EF4444',
    '#F59E0B', '#10B981', '#0EA5E9', '#06B6D4',
    '#84cc16', '#22c55e', '#14b8a6', '#0891b2',
];

// Edit list dialog
const showEditList = ref(false);
const editListName = ref('');

const openEditList = () => {
    editListName.value = props.list.name;
    showEditList.value = true;
};

const updateList = () => {
    if (!editListName.value.trim()) return;

    router.patch(
        route('lists.update', [props.workspace.id, props.space.id, props.list.id]),
        { name: editListName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditList.value = false;
            }
        }
    );
};

// Duplicate list
const duplicateList = () => {
    router.post(
        route('lists.duplicate', [props.workspace.id, props.space.id, props.list.id]),
        {},
        { preserveScroll: true }
    );
};

// Archive list
const archiveList = () => {
    router.post(
        route('lists.archive', [props.workspace.id, props.space.id, props.list.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
            }
        }
    );
};

// Delete list dialog
const showDeleteList = ref(false);

const confirmDeleteList = () => {
    router.delete(
        route('lists.destroy', [props.workspace.id, props.space.id, props.list.id]),
        {
            onSuccess: () => {
                router.visit(route('spaces.show', [props.workspace.id, props.space.id]));
            }
        }
    );
};

// Move to folder dialog
const showMoveToFolder = ref(false);
const selectedFolder = ref(null);

// Get available folders from space
const availableFolders = computed(() => {
    return [
        { id: null, name: 'No Folder (Root)' },
        ...(props.space?.folders || [])
    ];
});

const openMoveToFolder = () => {
    selectedFolder.value = props.list.folder_id;
    showMoveToFolder.value = true;
};

const moveToFolder = () => {
    router.post(
        route('lists.move-to-folder', [props.workspace.id, props.space.id, props.list.id]),
        { folder_id: selectedFolder.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMoveToFolder.value = false;
            }
        }
    );
};
</script>

<template>
    <MainLayout :title="list?.name || 'List'">
        <div class="list-page">
            <!-- Breadcrumb -->
            <div class="breadcrumb-header">
                <div class="flex items-center gap-2 text-sm">
                    <v-icon size="16">mdi-view-dashboard</v-icon>
                    <a :href="route('dashboard')" class="text-gray-400 hover:text-white">Dashboard</a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <a :href="route('workspaces.show', workspace.id)"
                        class="flex items-center gap-2 text-gray-400 hover:text-white">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: workspace?.color || '#6366F1' }">
                            {{ workspace?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <span>{{ workspace?.name }}</span>
                    </a>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded flex items-center justify-center text-white text-xs"
                            :style="{ backgroundColor: space?.color || '#6366F1' }">
                            <v-icon size="10" color="white">{{ space?.icon || 'mdi-folder' }}</v-icon>
                        </div>
                        <a :href="route('spaces.show', [workspace.id, space.id])"
                            class="text-gray-400 hover:text-white">
                            {{ space?.name }}
                        </a>
                    </div>
                    <v-icon size="16" class="text-gray-600">mdi-chevron-right</v-icon>
                    <div class="flex items-center gap-2">
                        <v-icon size="16" color="primary">mdi-format-list-bulleted</v-icon>
                        <span class="font-medium text-white">{{ list?.name }}</span>
                    </div>
                </div>
            </div>

            <!-- List Header -->
            <div class="list-header">
                <div class="flex items-center gap-3">
                    <!-- List Title -->
                    <h1 class="text-xl font-bold">{{ list?.name }}</h1>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Search -->
                    <v-text-field v-model="searchQuery" placeholder="Search tasks..." prepend-inner-icon="mdi-magnify"
                        variant="outlined" density="compact" hide-details single-line style="width: 200px;" />

                    <!-- Filters -->
                    <v-menu :close-on-content-click="false">
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="outlined" size="small">
                                <v-icon start size="16">mdi-filter-variant</v-icon>
                                Filter
                            </v-btn>
                        </template>
                        <v-card width="280" color="surface">
                            <v-card-text>
                                <div class="text-sm font-medium mb-2">Filters</div>
                                <v-select v-model="filterPriority" :items="priorities" item-title="name" item-value="id"
                                    label="Priority" variant="outlined" density="compact" multiple chips closable-chips
                                    hide-details class="mb-3" bg-color="#1e1e1e"
                                    :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                                <v-select v-model="filterAssignee" :items="members" item-title="name" item-value="id"
                                    label="Assignee" variant="outlined" density="compact" multiple chips closable-chips
                                    hide-details bg-color="#1e1e1e" :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                            </v-card-text>
                            <v-card-actions>
                                <v-btn variant="text" size="small" @click="filterPriority = []; filterAssignee = []">
                                    Clear All
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>

                    <!-- View Mode -->
                    <v-btn-toggle v-model="viewMode" mandatory density="compact" variant="outlined">
                        <v-btn value="board" size="small">
                            <v-icon size="16">mdi-view-column</v-icon>
                        </v-btn>
                        <v-btn value="list" size="small">
                            <v-icon size="16">mdi-format-list-bulleted</v-icon>
                        </v-btn>
                        <v-btn value="calendar" size="small">
                            <v-icon size="16">mdi-calendar</v-icon>
                        </v-btn>
                    </v-btn-toggle>

                    <!-- More Options -->
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text" size="small">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-pencil-outline" title="Edit List"
                                    @click="openEditList" />
                                <v-list-item prepend-icon="mdi-folder-move-outline" title="Move to Folder"
                                    @click="openMoveToFolder" />
                                <v-list-item prepend-icon="mdi-content-copy" title="Duplicate List"
                                    @click="duplicateList" />
                                <v-list-item prepend-icon="mdi-archive-outline" title="Archive List"
                                    @click="archiveList" />
                                <v-divider />
                                <v-list-item prepend-icon="mdi-delete-outline" title="Delete List" class="text-error"
                                    @click="showDeleteList = true" />
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <!-- Board View -->
            <div v-if="viewMode === 'board'" class="board-container">
                <div class="board-columns">
                    <!-- Status Columns -->
                    <StatusColumn v-for="status in statuses" :key="status.id" :status="status"
                        :tasks="localTasksByStatus[status.id] || []" :workspace="workspace" :space="space" :list="list"
                        @task-moved="handleTaskMoved" @task-complete="handleTaskComplete" @task-open="handleTaskOpen"
                        @add-task="handleAddTask" />

                    <!-- Add Status Column -->
                    <div class="add-status-column">
                        <v-btn v-if="!showAddStatus" variant="text" block class="add-status-btn"
                            @click="showAddStatus = true">
                            <v-icon start>mdi-plus</v-icon>
                            Add Status
                        </v-btn>

                        <v-card v-else variant="outlined" rounded="lg" class="pa-3">
                            <v-text-field v-model="newStatusName" placeholder="Status name" variant="outlined"
                                density="compact" hide-details autofocus class="mb-2" @keydown.enter="addStatus"
                                @keydown.escape="showAddStatus = false" />
                            <div class="flex flex-wrap gap-1 mb-3">
                                <div v-for="color in colorOptions" :key="color"
                                    class="w-6 h-6 rounded cursor-pointer border-2"
                                    :class="color === newStatusColor ? 'border-white' : 'border-transparent'"
                                    :style="{ backgroundColor: color }" @click="newStatusColor = color" />
                            </div>
                            <div class="flex gap-2">
                                <v-btn color="primary" size="small" @click="addStatus">Add</v-btn>
                                <v-btn variant="text" size="small" @click="showAddStatus = false">Cancel</v-btn>
                            </div>
                        </v-card>
                    </div>
                </div>
            </div>

            <!-- List View (TODO) -->
            <div v-else-if="viewMode === 'list'" class="list-view">
                <v-card variant="outlined" rounded="lg">
                    <v-table>
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Task</th>
                                <th style="width: 150px;">Status</th>
                                <th style="width: 120px;">Priority</th>
                                <th style="width: 150px;">Assignee</th>
                                <th style="width: 120px;">Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="status in statuses" :key="status.id">
                                <tr v-for="task in localTasksByStatus[status.id] || []" :key="task.id" class="task-row"
                                    @click="handleTaskOpen(task)">
                                    <td>
                                        <v-checkbox-btn :model-value="!!task.completed_at"
                                            @click.stop="handleTaskComplete(task)" hide-details density="compact" />
                                    </td>
                                    <td>
                                        <div class="font-medium">{{ task.name }}</div>
                                        <div v-if="task.description" class="text-sm text-gray-500 truncate"
                                            style="max-width: 300px;">
                                            {{ task.description }}
                                        </div>
                                    </td>
                                    <td>
                                        <v-chip :color="status.color" size="small" variant="tonal">
                                            {{ status.name }}
                                        </v-chip>
                                    </td>
                                    <td>
                                        <v-chip v-if="task.priority" :color="task.priority.color" size="small"
                                            variant="tonal">
                                            {{ task.priority.name }}
                                        </v-chip>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td>
                                        <div v-if="task.assignees?.length" class="flex items-center gap-1">
                                            <v-avatar v-for="assignee in task.assignees.slice(0, 3)" :key="assignee.id"
                                                size="24" :color="assignee.avatar_color || 'primary'">
                                                <span class="text-xs">{{ assignee.initials }}</span>
                                            </v-avatar>
                                            <span v-if="task.assignees.length > 3" class="text-xs text-gray-500">
                                                +{{ task.assignees.length - 3 }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td>
                                        <span v-if="task.due_date" class="text-sm">
                                            {{ new Date(task.due_date).toLocaleDateString() }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!Object.values(localTasksByStatus).some(tasks => tasks.length)">
                                <td colspan="6" class="text-center py-12 text-gray-500">
                                    <v-icon size="48" class="mb-2">mdi-checkbox-marked-circle-outline</v-icon>
                                    <div>No tasks yet</div>
                                </td>
                            </tr>
                        </tbody>
                    </v-table>
                </v-card>
            </div>

            <!-- Calendar View (TODO) -->
            <div v-else-if="viewMode === 'calendar'" class="calendar-view">
                <v-card variant="outlined" rounded="lg">
                    <v-card-text class="text-center py-12">
                        <v-icon size="64" color="grey" class="mb-4">mdi-calendar</v-icon>
                        <div class="text-h6 mb-2">Calendar View</div>
                        <div class="text-gray-500">Coming soon...</div>
                    </v-card-text>
                </v-card>
            </div>
        </div>

        <!-- Task Detail Panel -->
        <TaskDetailPanel v-model="showTaskDetail" :task="selectedTask" :workspace="workspace" :space="space"
            :list="list" :statuses="statuses" :priorities="priorities" :members="members" :labels="labels" />

        <!-- Edit List Dialog -->
        <v-dialog v-model="showEditList" max-width="400">
            <v-card>
                <v-card-title>Edit List</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editListName" label="List Name" variant="outlined" autofocus />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateList">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete List Dialog -->
        <v-dialog v-model="showDeleteList" max-width="400">
            <v-card>
                <v-card-title class="text-error">Delete List?</v-card-title>
                <v-card-text>
                    Are you sure you want to delete "{{ list?.name }}"? This will also delete all tasks within this
                    list. This
                    action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteList = false">Cancel</v-btn>
                    <v-btn color="error" @click="confirmDeleteList">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Move to Folder Dialog -->
        <v-dialog v-model="showMoveToFolder" max-width="400">
            <v-card>
                <v-card-title>Move List to Folder</v-card-title>
                <v-card-text>
                    <v-select v-model="selectedFolder" :items="availableFolders" item-title="name" item-value="id"
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e"
                        :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showMoveToFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="moveToFolder">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 48px);
}

.breadcrumb-header {
    padding: 12px 24px;
    border-bottom: 1px solid #2d2d30;
}

.breadcrumb-header a {
    transition: color 0.15s;
}

.list-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid #2d2d30;
    flex-shrink: 0;
}

.board-container {
    flex: 1;
    overflow: hidden;
    padding: 16px;
}

.board-columns {
    display: flex;
    gap: 16px;
    height: 100%;
    overflow-x: auto;
    padding-bottom: 16px;
}

.add-status-column {
    width: 300px;
    min-width: 300px;
    flex-shrink: 0;
}

.add-status-btn {
    height: 48px;
    border: 2px dashed #2d2d30;
    border-radius: 8px;
    opacity: 0.6;
}

.add-status-btn:hover {
    opacity: 1;
    border-color: #3d3d40;
}

.list-view,
.calendar-view {
    padding: 24px;
}

.task-row {
    cursor: pointer;
    transition: background-color 0.15s;
}

.task-row:hover {
    background-color: #2d2d30;
}
</style>
