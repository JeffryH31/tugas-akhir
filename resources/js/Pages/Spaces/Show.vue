<script setup>
/**
 * Space View Page - Shows all products and folders in a space
 */
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import draggable from 'vuedraggable';

const props = defineProps({
    workspace: Object,
    space: Object,
    statistics: Object,
    productsByStatus: Object,
});

// Create folder dialog
const showCreateFolder = ref(false);
const newFolderName = ref('');

// Create product dialog
const showCreateList = ref(false);
const newListName = ref('');
const selectedFolderId = ref(null);

const createFolder = () => {
    if (!newFolderName.value.trim()) return;

    router.post(
        route('folders.store', [props.workspace.id, props.space.id]),
        { name: newFolderName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                newFolderName.value = '';
                showCreateFolder.value = false;
                if (window.showSnackbar) {
                    window.showSnackbar('Folder created successfully!', 'success');
                }
            }
        }
    );
};

const createList = () => {
    if (!newListName.value.trim()) return;

    router.post(
        route('lists.store', [props.workspace.id, props.space.id]),
        {
            name: newListName.value.trim(),
            folder_id: selectedFolderId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newListName.value = '';
                selectedFolderId.value = null;
                showCreateList.value = false;
                if (window.showSnackbar) {
                    window.showSnackbar('Product created successfully!', 'success');
                }
            }
        }
    );
};

// Toggle folder collapse
const collapsedFolders = ref({});
const toggleFolder = (folderId) => {
    collapsedFolders.value[folderId] = !collapsedFolders.value[folderId];
};

// Edit space dialog
const showEditSpace = ref(false);
const editSpaceName = ref('');
const editSpaceDescription = ref('');
const editSpaceColor = ref('');

const openEditSpace = () => {
    editSpaceName.value = props.space.name;
    editSpaceDescription.value = props.space.description || '';
    editSpaceColor.value = props.space.color || '#6366F1';
    showEditSpace.value = true;
};

const updateSpace = () => {
    if (!editSpaceName.value.trim()) return;

    router.patch(
        route('spaces.update', [props.workspace.id, props.space.id]),
        {
            name: editSpaceName.value.trim(),
            description: editSpaceDescription.value.trim() || null,
            color: editSpaceColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditSpace.value = false;
                if (window.showSnackbar) {
                    window.showSnackbar('Space updated successfully!', 'success');
                }
            }
        }
    );
};

// Delete space dialog
const showDeleteSpace = ref(false);
const confirmDeleteSpace = () => {
    router.delete(
        route('spaces.destroy', [props.workspace.id, props.space.id]),
        {
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Space deleted successfully!', 'success');
                }
                router.visit(route('dashboard'));
            }
        }
    );
};

// Edit folder dialog
const showEditFolder = ref(false);
const editingFolder = ref(null);
const editFolderName = ref('');

const openEditFolder = (folder) => {
    editingFolder.value = folder;
    editFolderName.value = folder.name;
    showEditFolder.value = true;
};

const updateFolder = () => {
    if (!editFolderName.value.trim() || !editingFolder.value) return;

    router.patch(
        route('folders.update', [props.workspace.id, props.space.id, editingFolder.value.id]),
        { name: editFolderName.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditFolder.value = false;
                editingFolder.value = null;
                if (window.showSnackbar) {
                    window.showSnackbar('Folder updated successfully!', 'success');
                }
            }
        }
    );
};

// Delete folder dialog
const showDeleteFolder = ref(false);
const deletingFolder = ref(null);

const openDeleteFolder = (folder) => {
    deletingFolder.value = folder;
    showDeleteFolder.value = true;
};

const confirmDeleteFolder = () => {
    if (!deletingFolder.value) return;

    router.delete(
        route('folders.destroy', [props.workspace.id, props.space.id, deletingFolder.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteFolder.value = false;
                deletingFolder.value = null;
                if (window.showSnackbar) {
                    window.showSnackbar('Folder deleted successfully!', 'success');
                }
            }
        }
    );
};

// Move list to folder
const showMoveList = ref(false);
const movingList = ref(null);
const targetFolderId = ref(null);

const openMoveList = (list) => {
    movingList.value = list;
    targetFolderId.value = list.folder_id;
    showMoveList.value = true;
};

const moveListToFolder = () => {
    if (!movingList.value) return;

    router.post(
        route('lists.move-to-folder', [props.workspace.id, props.space.id, movingList.value.id]),
        { folder_id: targetFolderId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMoveList.value = false;
                movingList.value = null;
                if (window.showSnackbar) {
                    window.showSnackbar('Product moved successfully!', 'success');
                }
            }
        }
    );
};

// Drag and drop
const draggedList = ref(null);
const dragOverFolder = ref(null);

const handleDragStart = (event, list) => {
    draggedList.value = list;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, folderId = null) => {
    event.preventDefault();
    dragOverFolder.value = folderId;
};

const handleDragLeave = () => {
    dragOverFolder.value = null;
};

const handleDrop = (event, targetFolderId = null) => {
    event.preventDefault();

    if (!draggedList.value) return;

    // Only move if dropping to a different folder
    if (draggedList.value.folder_id !== targetFolderId) {
        router.post(
            route('lists.move-to-folder', [props.workspace.id, props.space.id, draggedList.value.id]),
            { folder_id: targetFolderId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (window.showSnackbar) {
                        window.showSnackbar('Product moved successfully!', 'success');
                    }
                }
            }
        );
    }

    draggedList.value = null;
    dragOverFolder.value = null;
};

// Available folders for moving lists
const availableFolders = computed(() => {
    return [
        { id: null, name: 'No Folder (Root)' },
        ...(props.space?.folders || [])
    ];
});

// View mode: 'hierarchy' (folders + products) or 'board' (kanban by status)
const viewMode = ref('hierarchy');

// Product kanban board state
const localProductsByStatus = ref({});

const initProductsByStatus = () => {
    const result = {};
    const statuses = props.space?.statuses || [];
    statuses.forEach(s => {
        result[s.id] = Array.isArray(props.productsByStatus?.[s.id])
            ? [...props.productsByStatus[s.id]]
            : [];
    });
    return result;
};

watch(() => props.productsByStatus, () => {
    localProductsByStatus.value = initProductsByStatus();
}, { deep: true, immediate: true });

// Handle product drag between status columns
const onProductDragChange = (evt, statusId) => {
    if (evt.added) {
        const product = evt.added.element;
        router.patch(
            route('lists.change-status', [props.workspace.id, props.space.id, product.id]),
            { status_id: statusId },
            { preserveScroll: true }
        );
    }
};
</script>

<template>
    <MainLayout :title="space?.name || 'Space'">
        <div class="space-page">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <div class="flex items-center gap-2 text-sm mb-4">
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
                        <span class="font-medium text-white">{{ space?.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Space Header -->
            <div class="space-header">
                <div class="flex items-center gap-4">
                    <div class="space-icon" :style="{ backgroundColor: space?.color || '#6366F1' }">
                        <v-icon color="white">{{ space?.icon || 'mdi-folder-outline' }}</v-icon>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">{{ space?.name }}</h1>
                        <p v-if="space?.description" class="text-gray-500 mt-1">{{ space.description }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <v-btn v-if="space?.is_starred" icon variant="text"
                        @click="router.post(route('spaces.star', [workspace.id, space.id]))">
                        <v-icon color="warning">mdi-star</v-icon>
                    </v-btn>
                    <v-btn v-else icon variant="text"
                        @click="router.post(route('spaces.star', [workspace.id, space.id]))">
                        <v-icon>mdi-star-outline</v-icon>
                    </v-btn>

                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" icon variant="text">
                                <v-icon>mdi-dots-horizontal</v-icon>
                            </v-btn>
                        </template>
                        <v-card color="surface">
                            <v-list density="compact">
                                <v-list-item prepend-icon="mdi-pencil-outline" title="Edit Space" @click="openEditSpace"
                                    class="px-4" />
                                <v-divider />
                                <v-list-item prepend-icon="mdi-delete-outline" title="Delete Space"
                                    class="text-error px-4" @click="showDeleteSpace = true" />
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold">{{ statistics?.total_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Total Tasks</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-success">{{ statistics?.completed_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Completed</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-warning">{{ statistics?.in_progress_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">In Progress</div>
                    </v-card-text>
                </v-card>
                <v-card variant="outlined" rounded="lg" class="stat-card">
                    <v-card-text class="pa-4">
                        <div class="text-3xl font-bold text-error">{{ statistics?.overdue_tasks || 0 }}</div>
                        <div class="text-sm text-gray-500">Overdue</div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Actions -->
            <div class="actions-row">
                <v-btn color="primary" @click="showCreateList = true">
                    <v-icon start>mdi-plus</v-icon>
                    New Product
                </v-btn>
                <v-btn variant="outlined" @click="showCreateFolder = true">
                    <v-icon start>mdi-folder-plus-outline</v-icon>
                    New Folder
                </v-btn>

                <v-spacer />

                <v-btn-toggle v-model="viewMode" mandatory density="compact" variant="outlined" divided>
                    <v-btn value="hierarchy" size="small">
                        <v-icon start size="16">mdi-file-tree</v-icon>
                        Hierarchy
                    </v-btn>
                    <v-btn value="board" size="small">
                        <v-icon start size="16">mdi-view-column</v-icon>
                        Board
                    </v-btn>
                </v-btn-toggle>
            </div>

            <!-- Board View (Product Kanban) -->
            <div v-if="viewMode === 'board'" class="product-board">
                <div class="board-columns">
                    <div v-for="status in space?.statuses" :key="status.id" class="board-column">
                        <!-- Column Header -->
                        <div class="board-column__header">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: status.color }" />
                                <span class="font-medium text-sm">{{ status.name }}</span>
                                <span class="text-xs text-gray-500 ml-1">{{ (localProductsByStatus[status.id] || []).length }}</span>
                            </div>
                        </div>

                        <!-- Draggable Products -->
                        <div class="board-column__content">
                            <draggable
                                :list="localProductsByStatus[status.id] || []"
                                group="products"
                                item-key="id"
                                :animation="200"
                                ghost-class="product-ghost"
                                drag-class="product-dragging"
                                class="board-column__list"
                                @change="(evt) => onProductDragChange(evt, status.id)"
                            >
                                <template #item="{ element }">
                                    <div class="product-card" @click="router.visit(route('lists.show', [workspace.id, space.id, element.id]))">
                                        <div class="product-card__status-bar" :style="{ backgroundColor: status.color }" />
                                        <div class="product-card__body">
                                            <div class="product-card__name">{{ element.name }}</div>
                                            <div class="product-card__meta">
                                                <span v-if="element.folder" class="product-card__folder">
                                                    <v-icon size="12">mdi-folder-outline</v-icon>
                                                    {{ element.folder.name }}
                                                </span>
                                                <span class="product-card__tasks">
                                                    <v-icon size="12">mdi-checkbox-marked-outline</v-icon>
                                                    {{ element.tasks_count || 0 }} tasks
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </draggable>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hierarchy View (Content) -->
            <div v-else class="space-content">
                <!-- Folders -->
                <div v-for="folder in space?.folders" :key="folder.id" class="folder-item">
                    <div class="folder-header" @click="toggleFolder(folder.id)">
                        <div class="flex items-center gap-2">
                            <v-icon size="20">
                                {{ collapsedFolders[folder.id] ? 'mdi-chevron-right' : 'mdi-chevron-down' }}
                            </v-icon>
                            <v-icon size="20" color="warning">mdi-folder</v-icon>
                            <span class="font-medium">{{ folder.name }}</span>
                            <span class="text-gray-500 text-sm">({{ folder.lists?.length || 0 }} products)</span>
                        </div>
                        <div class="folder-actions">
                            <v-btn icon variant="text" size="x-small"
                                @click.stop="selectedFolderId = folder.id; showCreateList = true">
                                <v-icon size="16">mdi-plus</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="x-small" @click.stop="openEditFolder(folder)">
                                <v-icon size="16">mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn icon variant="text" size="x-small" @click.stop="openDeleteFolder(folder)">
                                <v-icon size="16" color="error">mdi-delete</v-icon>
                            </v-btn>
                        </div>
                    </div>

                    <div v-if="!collapsedFolders[folder.id]" class="folder-lists"
                        :class="{ 'drag-over': dragOverFolder === folder.id }"
                        @dragover="handleDragOver($event, folder.id)" @dragleave="handleDragLeave"
                        @drop="handleDrop($event, folder.id)">
                        <div v-for="list in folder.lists" :key="list.id" class="list-item" draggable="true"
                            @dragstart="handleDragStart($event, list)"
                            @click="router.visit(route('lists.show', [workspace.id, space.id, list.id]))">
                            <div class="flex items-center gap-3">
                                <v-icon size="18" class="drag-handle cursor-move" @click.stop>mdi-drag</v-icon>
                                <v-icon size="18">mdi-package-variant-closed</v-icon>
                                <span>{{ list.name }}</span>
                            </div>
                            <div class="list-meta">
                                <span class="text-gray-500 text-sm">{{ list.tasks_count || 0 }} tasks</span>
                                <v-btn icon variant="text" size="x-small" @click.stop="openMoveList(list)">
                                    <v-icon size="16">mdi-folder-move-outline</v-icon>
                                </v-btn>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lists without folder -->
                <div v-if="space?.lists_without_folder?.length" class="root-lists-zone"
                    :class="{ 'drag-over': dragOverFolder === null }" @dragover="handleDragOver($event, null)"
                    @dragleave="handleDragLeave" @drop="handleDrop($event, null)">
                    <div v-for="list in space?.lists_without_folder" :key="list.id"
                        class="list-item list-item--standalone" draggable="true"
                        @dragstart="handleDragStart($event, list)"
                        @click="router.visit(route('lists.show', [workspace.id, space.id, list.id]))">
                        <div class="flex items-center gap-3">
                            <v-icon size="18" class="drag-handle cursor-move" @click.stop>mdi-drag</v-icon>
                            <v-icon size="18">mdi-package-variant-closed</v-icon>
                            <span class="font-medium">{{ list.name }}</span>
                        </div>
                        <div class="list-meta">
                            <span class="text-gray-500 text-sm">{{ list.tasks_count || 0 }} tasks</span>
                            <v-btn icon variant="text" size="x-small" @click.stop="openMoveList(list)">
                                <v-icon size="16">mdi-folder-move-outline</v-icon>
                            </v-btn>
                            <v-icon size="16" color="grey">mdi-chevron-right</v-icon>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!space?.folders?.length && !space?.lists_without_folder?.length" class="empty-state">
                    <v-icon size="80" color="grey-darken-1" class="mb-4">mdi-folder-open-outline</v-icon>
                    <h2 class="text-xl font-semibold mb-2">This space is empty</h2>
                    <p class="text-gray-500 mb-6">Get started by creating a product or folder</p>
                    <div class="flex gap-3 justify-center">
                        <v-btn color="primary" @click="showCreateList = true">
                            <v-icon start>mdi-plus</v-icon>
                            Create Product
                        </v-btn>
                        <v-btn variant="outlined" @click="showCreateFolder = true">
                            <v-icon start>mdi-folder-plus-outline</v-icon>
                            Create Folder
                        </v-btn>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Folder Dialog -->
        <v-dialog v-model="showCreateFolder" max-width="400">
            <v-card>
                <v-card-title>Create Folder</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newFolderName" label="Folder Name" placeholder="e.g., Sprint 1"
                        variant="outlined" autofocus @keydown.enter="createFolder" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="createFolder">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Create Product Dialog -->
        <v-dialog v-model="showCreateList" max-width="400">
            <v-card>
                <v-card-title>Create Product</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newListName" label="Product Name" placeholder="e.g., Product A" variant="outlined"
                        autofocus class="mb-3" @keydown.enter="createList" />
                    <v-select v-model="selectedFolderId" :items="space?.folders || []" item-title="name" item-value="id"
                        label="Folder (optional)" variant="outlined" clearable bg-color="#1e1e1e" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="createList">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Edit Space Dialog -->
        <v-dialog v-model="showEditSpace" max-width="500">
            <v-card>
                <v-card-title>Edit Space</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editSpaceName" label="Space Name" variant="outlined" autofocus
                        class="mb-4" />
                    <v-textarea v-model="editSpaceDescription" label="Description (Optional)" variant="outlined"
                        rows="3" class="mb-4" />
                    <div>
                        <div class="text-sm font-medium mb-2">Space Color</div>
                        <div class="flex gap-2">
                            <div v-for="color in ['#6366F1', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#6B7280']"
                                :key="color" class="w-8 h-8 rounded-lg cursor-pointer border-2 transition-all"
                                :class="{ 'border-white scale-110': editSpaceColor === color, 'border-transparent': editSpaceColor !== color }"
                                :style="{ backgroundColor: color }" @click="editSpaceColor = color" />
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditSpace = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateSpace">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Space Dialog -->
        <v-dialog v-model="showDeleteSpace" max-width="400">
            <v-card>
                <v-card-title class="text-error">Delete Space?</v-card-title>
                <v-card-text>
                    Are you sure you want to delete "{{ space?.name }}"? This will also delete all folders, products, and
                    tasks
                    within this space. This action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteSpace = false">Cancel</v-btn>
                    <v-btn color="error" @click="confirmDeleteSpace">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Edit Folder Dialog -->
        <v-dialog v-model="showEditFolder" max-width="400">
            <v-card>
                <v-card-title>Edit Folder</v-card-title>
                <v-card-text>
                    <v-text-field v-model="editFolderName" label="Folder Name" variant="outlined" autofocus />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditFolder = false">Cancel</v-btn>
                    <v-btn color="primary" @click="updateFolder">Update</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Folder Dialog -->
        <v-dialog v-model="showDeleteFolder" max-width="400">
            <v-card>
                <v-card-title class="text-error">Delete Folder?</v-card-title>
                <v-card-text>
                    Are you sure you want to delete "{{ deletingFolder?.name }}"? This will also delete all products within
                    this
                    folder. This action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteFolder = false">Cancel</v-btn>
                    <v-btn color="error" @click="confirmDeleteFolder">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Move Product to Folder Dialog -->
        <v-dialog v-model="showMoveList" max-width="400">
            <v-card>
                <v-card-title>Move Product to Folder</v-card-title>
                <v-card-text>
                    <div class="text-sm text-gray-400 mb-4">Moving: <span class="font-medium text-white">{{
                        movingList?.name
                            }}</span></div>
                    <v-select v-model="targetFolderId" :items="availableFolders" item-title="name" item-value="id"
                        label="Select Folder" variant="outlined" hide-details bg-color="#1e1e1e" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showMoveList = false">Cancel</v-btn>
                    <v-btn color="primary" @click="moveListToFolder">Move</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.space-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.breadcrumb a {
    transition: color 0.15s;
}

.space-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.space-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    text-align: center;
}

.actions-row {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.space-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.folder-item {
    background-color: #1e1e1e;
    border-radius: 8px;
    overflow: hidden;
}

.folder-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.15s;
}

.folder-header:hover {
    background-color: #2d2d30;
}

.folder-actions {
    opacity: 0;
    transition: opacity 0.15s;
}

.folder-header:hover .folder-actions {
    opacity: 1;
}

.folder-lists {
    border-top: 1px solid #2d2d30;
}

.list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.15s;
}

.list-item:hover {
    background-color: #2d2d30;
}

.list-item--standalone {
    background-color: #1e1e1e;
    border-radius: 8px;
}

.list-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.drag-handle {
    opacity: 0;
    transition: opacity 0.15s;
}

.list-item:hover .drag-handle {
    opacity: 0.5;
}

.list-item[draggable="true"] {
    cursor: grab;
}

.list-item[draggable="true"]:active {
    cursor: grabbing;
    opacity: 0.5;
}

.folder-lists.drag-over,
.root-lists-zone.drag-over {
    background-color: rgba(99, 102, 241, 0.1);
    border: 2px dashed rgba(99, 102, 241, 0.5);
    border-radius: 8px;
}

.cursor-move {
    cursor: move;
}

.root-lists-zone {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.15s;
}

/* ─── Product Kanban Board ─── */
.product-board {
    overflow-x: auto;
    padding-bottom: 16px;
}

.board-columns {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    min-height: 300px;
}

.board-column {
    width: 300px;
    min-width: 300px;
    display: flex;
    flex-direction: column;
    background-color: #1a1a1a;
    border-radius: 8px;
    max-height: calc(100vh - 340px);
}

.board-column__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border-bottom: 1px solid #2d2d30;
}

.board-column__content {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.board-column__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 50px;
}

.product-card {
    display: flex;
    background-color: #1e1e2e;
    border: 1px solid #2e2e3e;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s;
}

.product-card:hover {
    background-color: #242438;
    border-color: #3e3e5e;
}

.product-card__status-bar {
    width: 4px;
    min-height: 100%;
    flex-shrink: 0;
}

.product-card__body {
    padding: 12px;
    flex: 1;
    min-width: 0;
}

.product-card__name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card__meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #9ca3af;
}

.product-card__folder,
.product-card__tasks {
    display: flex;
    align-items: center;
    gap: 4px;
}

.product-ghost {
    opacity: 0.5;
}

.product-dragging {
    transform: rotate(2deg);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
}
</style>
