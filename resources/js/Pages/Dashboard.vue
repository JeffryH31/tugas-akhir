<script setup>
/**
 * Dashboard Page - Trello-like Kanban Board
 * 
 * Features:
 * - Navigation bar with search and profile
 * - Sidebar with workspace/boards navigation
 * - Kanban board with draggable lists and cards
 */
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import draggable from 'vuedraggable';

// Sidebar state
const isSidebarOpen = ref(true);
const selectedWorkspace = ref('My Workspace');

// Dummy workspaces data
const workspaces = ref([
    {
        id: 1,
        name: 'My Workspace',
        boards: [
            { id: 1, name: 'Project Alpha', color: '#0079BF', starred: true },
            { id: 2, name: 'Marketing Campaign', color: '#D29034', starred: false },
            { id: 3, name: 'Development Sprint', color: '#519839', starred: true },
        ]
    },
    {
        id: 2,
        name: 'Team Workspace',
        boards: [
            { id: 4, name: 'Q1 Planning', color: '#B04632', starred: false },
            { id: 5, name: 'Product Roadmap', color: '#89609E', starred: false },
        ]
    }
]);

// Current active board
const activeBoard = ref({
    id: 1,
    name: 'Project Alpha',
    color: '#6366F1',
    background: '#0F172A',
    members: [
        { id: 1, name: 'John Doe', avatar: 'JD', color: '#6366F1' },
        { id: 2, name: 'Jane Smith', avatar: 'JS', color: '#8B5CF6' },
        { id: 3, name: 'Bob Wilson', avatar: 'BW', color: '#0EA5E9' },
    ]
});

// Kanban lists with cards
const lists = ref([
    {
        id: 1,
        title: 'To Do',
        cards: [
            {
                id: 1,
                title: 'Research competitor analysis',
                labels: [
                    { id: 1, name: 'Research', color: '#10B981' },
                    { id: 2, name: 'High Priority', color: '#EF4444' }
                ],
                dueDate: '2026-01-15',
                dueSoon: true,
                assignees: [1, 2],
                hasDescription: true,
                checklistTotal: 5,
                checklistCompleted: 2,
                commentsCount: 3,
                attachmentsCount: 1
            },
            {
                id: 2,
                title: 'Design system documentation',
                labels: [
                    { id: 3, name: 'Design', color: '#F59E0B' }
                ],
                dueDate: null,
                assignees: [1],
                hasDescription: true,
                checklistTotal: 0,
                checklistCompleted: 0,
                commentsCount: 0,
                attachmentsCount: 0
            },
            {
                id: 3,
                title: 'Setup development environment',
                labels: [],
                dueDate: '2026-01-20',
                dueSoon: false,
                assignees: [3],
                hasDescription: false,
                checklistTotal: 3,
                checklistCompleted: 3,
                commentsCount: 1,
                attachmentsCount: 2
            }
        ]
    },
    {
        id: 2,
        title: 'In Progress',
        cards: [
            {
                id: 4,
                title: 'Implement user authentication flow with OAuth 2.0',
                labels: [
                    { id: 4, name: 'Backend', color: '#6366F1' },
                    { id: 2, name: 'High Priority', color: '#EF4444' }
                ],
                dueDate: '2026-01-14',
                dueSoon: true,
                overdue: true,
                assignees: [2, 3],
                hasDescription: true,
                checklistTotal: 8,
                checklistCompleted: 5,
                commentsCount: 7,
                attachmentsCount: 0
            },
            {
                id: 5,
                title: 'Create API endpoints',
                labels: [
                    { id: 4, name: 'Backend', color: '#6366F1' }
                ],
                dueDate: null,
                assignees: [2],
                hasDescription: true,
                checklistTotal: 0,
                checklistCompleted: 0,
                commentsCount: 2,
                attachmentsCount: 0
            }
        ]
    },
    {
        id: 3,
        title: 'Review',
        cards: [
            {
                id: 6,
                title: 'Code review: Login component',
                labels: [
                    { id: 5, name: 'Review', color: '#8B5CF6' }
                ],
                dueDate: '2026-01-16',
                dueSoon: false,
                assignees: [1, 2, 3],
                hasDescription: true,
                checklistTotal: 4,
                checklistCompleted: 4,
                commentsCount: 12,
                attachmentsCount: 3
            }
        ]
    },
    {
        id: 4,
        title: 'Done',
        cards: [
            {
                id: 7,
                title: 'Project kickoff meeting',
                labels: [
                    { id: 6, name: 'Meeting', color: '#0EA5E9' }
                ],
                dueDate: '2026-01-10',
                completed: true,
                assignees: [1, 2, 3],
                hasDescription: true,
                checklistTotal: 3,
                checklistCompleted: 3,
                commentsCount: 5,
                attachmentsCount: 1
            },
            {
                id: 8,
                title: 'Initial wireframes',
                labels: [
                    { id: 3, name: 'Design', color: '#F59E0B' }
                ],
                dueDate: '2026-01-08',
                completed: true,
                assignees: [1],
                hasDescription: true,
                checklistTotal: 0,
                checklistCompleted: 0,
                commentsCount: 8,
                attachmentsCount: 4
            }
        ]
    }
]);

// Available labels - Minimalist pastel colors
const availableLabels = ref([
    { id: 1, name: 'Research', color: '#10B981' },
    { id: 2, name: 'High Priority', color: '#EF4444' },
    { id: 3, name: 'Design', color: '#F59E0B' },
    { id: 4, name: 'Backend', color: '#6366F1' },
    { id: 5, name: 'Review', color: '#8B5CF6' },
    { id: 6, name: 'Meeting', color: '#0EA5E9' },
]);

// Card detail modal
const isCardModalOpen = ref(false);
const selectedCard = ref(null);
const selectedList = ref(null);

// Search
const searchQuery = ref('');

// New list
const isAddingList = ref(false);
const newListTitle = ref('');

// New card states per list
const addingCardToList = ref(null);
const newCardTitle = ref('');

// Methods
const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const openCardModal = (card, list) => {
    selectedCard.value = { ...card };
    selectedList.value = list;
    isCardModalOpen.value = true;
};

const closeCardModal = () => {
    isCardModalOpen.value = false;
    selectedCard.value = null;
    selectedList.value = null;
};

const getAssignee = (assigneeId) => {
    return activeBoard.value.members.find(m => m.id === assigneeId);
};

const formatDueDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const getDueDateClass = (card) => {
    if (card.completed) return 'due-complete';
    if (card.overdue) return 'due-overdue';
    if (card.dueSoon) return 'due-soon';
    return 'due-default';
};

const startAddingCard = (listId) => {
    addingCardToList.value = listId;
    newCardTitle.value = '';
};

const cancelAddingCard = () => {
    addingCardToList.value = null;
    newCardTitle.value = '';
};

const addCard = (listId) => {
    if (!newCardTitle.value.trim()) return;

    const list = lists.value.find(l => l.id === listId);
    if (list) {
        const newId = Math.max(...lists.value.flatMap(l => l.cards.map(c => c.id))) + 1;
        list.cards.push({
            id: newId,
            title: newCardTitle.value.trim(),
            labels: [],
            dueDate: null,
            assignees: [],
            hasDescription: false,
            checklistTotal: 0,
            checklistCompleted: 0,
            commentsCount: 0,
            attachmentsCount: 0
        });
    }
    cancelAddingCard();
};

const startAddingList = () => {
    isAddingList.value = true;
    newListTitle.value = '';
};

const cancelAddingList = () => {
    isAddingList.value = false;
    newListTitle.value = '';
};

const addList = () => {
    if (!newListTitle.value.trim()) return;

    const newId = Math.max(...lists.value.map(l => l.id)) + 1;
    lists.value.push({
        id: newId,
        title: newListTitle.value.trim(),
        cards: []
    });
    cancelAddingList();
};

// Starred boards computed
const starredBoards = computed(() => {
    return workspaces.value.flatMap(w => w.boards.filter(b => b.starred));
});
</script>

<template>

    <Head title="Dashboard - Project Alpha" />

    <v-app theme="darkTheme">
        <!-- Top Navigation Bar -->
        <v-app-bar color="surface" density="compact" elevation="0" border>
            <template #prepend>
                <v-btn icon variant="text" size="small" @click="toggleSidebar">
                    <v-icon color="grey-lighten-1">mdi-menu</v-icon>
                </v-btn>
            </template>

            <!-- Logo -->
            <v-btn variant="text" class="text-none font-weight-bold text-h6 mx-2 text-white">
                <v-icon class="mr-1" color="primary">mdi-view-dashboard-outline</v-icon>
                Taskboard
            </v-btn>

            <v-spacer />

            <!-- Search -->
            <v-text-field v-model="searchQuery" density="compact" variant="solo-filled" bg-color="surface-variant"
                placeholder="Search..." prepend-inner-icon="mdi-magnify" hide-details single-line rounded
                class="mx-4" style="max-width: 400px;" />

            <v-spacer />

            <!-- Right actions -->
            <v-btn icon variant="text" size="small" class="mr-1">
                <v-icon color="grey-lighten-1">mdi-bell-outline</v-icon>
            </v-btn>
            <v-btn icon variant="text" size="small" class="mr-1">
                <v-icon color="grey-lighten-1">mdi-help-circle-outline</v-icon>
            </v-btn>

            <!-- Profile Menu -->
            <v-menu>
                <template #activator="{ props }">
                    <v-btn icon v-bind="props" size="small">
                        <v-avatar size="28" color="primary">
                            <span class="text-caption font-weight-bold">JD</span>
                        </v-avatar>
                    </v-btn>
                </template>
                <v-list density="compact" width="280">
                    <v-list-item prepend-icon="mdi-account" title="Profile" />
                    <v-list-item prepend-icon="mdi-cog" title="Settings" />
                    <v-divider class="my-1" />
                    <v-list-item prepend-icon="mdi-logout" title="Sign Out" href="/login" />
                </v-list>
            </v-menu>
        </v-app-bar>

        <!-- Main Content Area -->
        <v-main class="board-main" :style="{ background: activeBoard.background }">
            <div class="d-flex fill-height">
                <!-- Sidebar -->
                <v-navigation-drawer v-model="isSidebarOpen" :rail="!isSidebarOpen" permanent color="surface"
                    border width="260">
                    <v-list density="compact" nav class="pa-2">
                        <!-- Boards -->
                        <v-list-item prepend-icon="mdi-view-dashboard-outline" title="Boards" rounded="lg"
                            class="mb-1" />

                        <!-- Templates -->
                        <v-list-item prepend-icon="mdi-file-document-outline" title="Templates" rounded="lg"
                            class="mb-1" />

                        <!-- Home -->
                        <v-list-item prepend-icon="mdi-home-outline" title="Home" rounded="lg" class="mb-1" />

                        <v-divider class="my-3" />

                        <!-- Starred Boards -->
                        <v-list-subheader class="text-medium-emphasis">
                            Starred
                        </v-list-subheader>
                        <v-list-item v-for="board in starredBoards" :key="board.id" rounded="lg" class="mb-1">
                            <template #prepend>
                                <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
                            </template>
                            <v-list-item-title>{{ board.name }}</v-list-item-title>
                        </v-list-item>

                        <v-divider class="my-3 border-opacity-25" />

                        <!-- Workspaces -->
                        <v-list-subheader class="text-medium-emphasis">
                            Workspaces
                        </v-list-subheader>
                        <template v-for="workspace in workspaces" :key="workspace.id">
                            <v-list-group>
                                <template #activator="{ props }">
                                    <v-list-item v-bind="props" rounded="lg">
                                        <template #prepend>
                                            <v-avatar size="24" color="surface-variant" rounded="sm">
                                                <span class="text-caption">{{
                                                    workspace.name.charAt(0) }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title>{{ workspace.name }}</v-list-item-title>
                                    </v-list-item>
                                </template>
                                <v-list-item v-for="board in workspace.boards" :key="board.id" rounded="lg"
                                    class="ml-4">
                                    <template #prepend>
                                        <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ board.name }}</v-list-item-title>
                                    <template #append>
                                        <v-icon v-if="board.starred" size="14" color="amber">
                                            mdi-star
                                        </v-icon>
                                    </template>
                                </v-list-item>
                            </v-list-group>
                        </template>

                        <v-divider class="my-3 border-opacity-25" />

                        <!-- Create Board Button -->
                        <v-list-item prepend-icon="mdi-plus" title="Create new board" rounded="lg"
                            base-color="surface-variant" />
                    </v-list>
                </v-navigation-drawer>

                <!-- Board Area -->
                <div class="board-area flex-grow-1">
                    <!-- Board Header -->
                    <v-sheet color="surface" class="d-flex align-center pa-3 board-header">
                        <h1 class="text-h5 font-weight-bold text-white mr-3">
                            {{ activeBoard.name }}
                        </h1>
                        <v-btn icon variant="text" size="small" class="mr-2">
                            <v-icon color="grey-lighten-1">mdi-star-outline</v-icon>
                        </v-btn>
                        <v-divider vertical class="mx-2 border-opacity-25" style="height: 24px;" />

                        <!-- Members -->
                        <div class="d-flex align-center mx-2">
                            <v-avatar v-for="member in activeBoard.members" :key="member.id" size="32"
                                :color="member.color" class="member-avatar">
                                <span class="text-caption font-weight-bold">{{ member.avatar }}</span>
                            </v-avatar>
                            <v-btn icon variant="text" size="small" class="ml-1">
                                <v-icon color="grey-lighten-1" size="20">mdi-plus</v-icon>
                            </v-btn>
                        </div>

                        <v-spacer />

                        <!-- Board Actions -->
                        <v-btn variant="tonal" size="small" class="mr-2 text-none">
                            <v-icon start size="18">mdi-filter-variant</v-icon>
                            Filters
                        </v-btn>
                        <v-btn variant="tonal" size="small" class="text-none">
                            <v-icon start size="18">mdi-dots-horizontal</v-icon>
                            Menu
                        </v-btn>
                    </v-sheet>

                    <!-- Kanban Board -->
                    <div class="kanban-board pa-3">
                        <draggable v-model="lists" group="lists" item-key="id" class="lists-container d-flex"
                            ghost-class="ghost-list" handle=".list-header">
                            <template #item="{ element: list }">
                                <v-sheet color="surface" rounded="xl" class="kanban-list mr-3">
                                    <!-- List Header -->
                                    <div class="list-header d-flex align-center justify-space-between pa-2">
                                        <span class="font-weight-medium text-grey-lighten-2">
                                            {{ list.title }}
                                        </span>
                                        <v-menu>
                                            <template #activator="{ props }">
                                                <v-btn icon variant="text" size="x-small" v-bind="props">
                                                    <v-icon size="18">mdi-dots-horizontal</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-list density="compact" width="200">
                                                <v-list-item title="Add card" prepend-icon="mdi-plus" />
                                                <v-list-item title="Copy list" prepend-icon="mdi-content-copy" />
                                                <v-list-item title="Move list" prepend-icon="mdi-arrow-right" />
                                                <v-divider class="my-1" />
                                                <v-list-item title="Delete list" prepend-icon="mdi-delete-outline"
                                                    base-color="error" />
                                            </v-list>
                                        </v-menu>
                                    </div>

                                    <!-- Cards Container -->
                                    <draggable v-model="list.cards" group="cards" item-key="id"
                                        class="cards-container pa-1" ghost-class="ghost-card">
                                        <template #item="{ element: card }">
                                            <v-card class="kanban-card mb-2" rounded="lg" elevation="1"
                                                color="surface-variant" @click="openCardModal(card, list)">
                                                <v-card-text class="pa-2">
                                                    <!-- Labels -->
                                                    <div v-if="card.labels.length" class="d-flex flex-wrap gap-1 mb-2">
                                                        <div v-for="label in card.labels" :key="label.id"
                                                            class="card-label" :style="{ backgroundColor: label.color }"
                                                            :title="label.name" />
                                                    </div>

                                                    <!-- Card Title -->
                                                    <p class="text-body-2 mb-2">
                                                        {{ card.title }}
                                                    </p>

                                                    <!-- Card Badges -->
                                                    <div class="d-flex align-center flex-wrap gap-2">
                                                        <!-- Due Date -->
                                                        <v-chip v-if="card.dueDate" size="x-small"
                                                            :class="getDueDateClass(card)" class="due-chip">
                                                            <v-icon start size="12">mdi-clock-outline</v-icon>
                                                            {{ formatDueDate(card.dueDate) }}
                                                        </v-chip>

                                                        <!-- Description -->
                                                        <v-icon v-if="card.hasDescription" size="16"
                                                            class="text-medium-emphasis" title="This card has a description">
                                                            mdi-text-subject
                                                        </v-icon>

                                                        <!-- Comments -->
                                                        <div v-if="card.commentsCount > 0"
                                                            class="d-flex align-center text-medium-emphasis">
                                                            <v-icon size="14" class="mr-1">mdi-comment-outline</v-icon>
                                                            <span class="text-caption">{{ card.commentsCount }}</span>
                                                        </div>

                                                        <!-- Attachments -->
                                                        <div v-if="card.attachmentsCount > 0"
                                                            class="d-flex align-center text-medium-emphasis">
                                                            <v-icon size="14" class="mr-1">mdi-paperclip</v-icon>
                                                            <span class="text-caption">{{ card.attachmentsCount
                                                                }}</span>
                                                        </div>

                                                        <!-- Checklist -->
                                                        <div v-if="card.checklistTotal > 0" class="d-flex align-center"
                                                            :class="card.checklistCompleted === card.checklistTotal ? 'text-success' : 'text-medium-emphasis'">
                                                            <v-icon size="14"
                                                                class="mr-1">mdi-checkbox-marked-outline</v-icon>
                                                            <span class="text-caption">
                                                                {{ card.checklistCompleted }}/{{ card.checklistTotal }}
                                                            </span>
                                                        </div>

                                                        <v-spacer />

                                                        <!-- Assignees -->
                                                        <div class="d-flex">
                                                            <v-avatar v-for="assigneeId in card.assignees.slice(0, 3)"
                                                                :key="assigneeId" size="24"
                                                                :color="getAssignee(assigneeId)?.color"
                                                                class="card-assignee">
                                                                <span class="text-caption" style="font-size: 10px;">
                                                                    {{ getAssignee(assigneeId)?.avatar }}
                                                                </span>
                                                            </v-avatar>
                                                        </div>
                                                    </div>
                                                </v-card-text>
                                            </v-card>
                                        </template>
                                    </draggable>

                                    <!-- Add Card Form -->
                                    <div v-if="addingCardToList === list.id" class="pa-1">
                                        <v-textarea v-model="newCardTitle" placeholder="Enter a title for this card..."
                                            variant="outlined" density="compact" rows="3" hide-details autofocus
                                            bg-color="surface-variant" class="mb-2"
                                            @keydown.enter.prevent="addCard(list.id)" @keydown.esc="cancelAddingCard" />
                                        <div class="d-flex align-center ga-2">
                                            <v-btn color="primary" size="small" class="text-none"
                                                @click="addCard(list.id)">
                                                Add card
                                            </v-btn>
                                            <v-btn icon variant="text" size="small" @click="cancelAddingCard">
                                                <v-icon>mdi-close</v-icon>
                                            </v-btn>
                                        </div>
                                    </div>

                                    <!-- Add Card Button -->
                                    <v-btn v-if="addingCardToList !== list.id" variant="text" block
                                        class="justify-start text-none text-medium-emphasis pa-2"
                                        @click="startAddingCard(list.id)">
                                        <v-icon size="18" class="mr-1">mdi-plus</v-icon>
                                        Add a card
                                    </v-btn>
                                </v-sheet>
                            </template>
                        </draggable>

                        <!-- Add List -->
                        <div class="add-list-container">
                            <v-btn v-if="!isAddingList" variant="tonal" class="text-none pa-3 add-list-btn"
                                @click="startAddingList">
                                <v-icon size="18" class="mr-1">mdi-plus</v-icon>
                                Add another list
                            </v-btn>
                            <v-sheet v-else color="surface" rounded="xl" class="pa-2 add-list-form">
                                <v-text-field v-model="newListTitle" placeholder="Enter list title..."
                                    variant="outlined" density="compact" hide-details autofocus bg-color="surface-variant"
                                    class="mb-2" @keydown.enter.prevent="addList" @keydown.esc="cancelAddingList" />
                                <div class="d-flex align-center ga-2">
                                    <v-btn color="primary" size="small" class="text-none" @click="addList">
                                        Add list
                                    </v-btn>
                                    <v-btn icon variant="text" size="small" @click="cancelAddingList">
                                        <v-icon>mdi-close</v-icon>
                                    </v-btn>
                                </div>
                            </v-sheet>
                        </div>
                    </div>
                </div>
            </div>
        </v-main>

        <!-- Card Detail Modal -->
        <v-dialog v-model="isCardModalOpen" max-width="800" scrollable>
            <v-card v-if="selectedCard" rounded="lg">
                <v-card-title class="d-flex align-start pa-4 pb-2">
                    <v-icon class="mr-3 mt-1 text-medium-emphasis">mdi-card-text-outline</v-icon>
                    <div class="flex-grow-1">
                        <v-text-field v-model="selectedCard.title" variant="plain" density="compact" hide-details
                            class="card-title-input text-h6 font-weight-medium" />
                        <p class="text-body-2 text-grey mt-1">
                            in list <span class="text-decoration-underline">{{ selectedList?.title }}</span>
                        </p>
                    </div>
                    <v-btn icon variant="text" size="small" @click="closeCardModal">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>

                <v-card-text class="pa-4">
                    <v-row>
                        <!-- Main Content -->
                        <v-col cols="12" md="9">
                            <!-- Labels -->
                            <div v-if="selectedCard.labels.length" class="mb-4">
                                <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2">Labels</h4>
                                <div class="d-flex flex-wrap gap-1">
                                    <v-chip v-for="label in selectedCard.labels" :key="label.id" :color="label.color"
                                        size="small" class="font-weight-medium">
                                        {{ label.name }}
                                    </v-chip>
                                    <v-btn size="small" variant="tonal" class="text-none">
                                        <v-icon size="16">mdi-plus</v-icon>
                                    </v-btn>
                                </div>
                            </div>

                            <!-- Due Date -->
                            <div v-if="selectedCard.dueDate" class="mb-4">
                                <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2">Due date</h4>
                                <v-chip :class="getDueDateClass(selectedCard)" size="small">
                                    <v-icon start size="14">mdi-clock-outline</v-icon>
                                    {{ formatDueDate(selectedCard.dueDate) }}
                                </v-chip>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <div class="d-flex align-center mb-2">
                                    <v-icon class="mr-2 text-medium-emphasis">mdi-text-subject</v-icon>
                                    <h4 class="text-subtitle-1 font-weight-medium">Description</h4>
                                </div>
                                <v-textarea variant="outlined" placeholder="Add a more detailed description..." rows="4"
                                    hide-details class="description-textarea" />
                            </div>

                            <!-- Checklist -->
                            <div v-if="selectedCard.checklistTotal > 0" class="mb-4">
                                <div class="d-flex align-center mb-2">
                                    <v-icon class="mr-2 text-medium-emphasis">mdi-checkbox-marked-outline</v-icon>
                                    <h4 class="text-subtitle-1 font-weight-medium">Checklist</h4>
                                    <v-spacer />
                                    <v-btn size="small" variant="tonal" class="text-none">
                                        Delete
                                    </v-btn>
                                </div>
                                <v-progress-linear
                                    :model-value="(selectedCard.checklistCompleted / selectedCard.checklistTotal) * 100"
                                    color="primary" height="8" rounded class="mb-3" />
                                <v-checkbox label="Sample checklist item 1" density="compact" hide-details
                                    class="mb-1" />
                                <v-checkbox label="Sample checklist item 2" density="compact" hide-details
                                    class="mb-1" />
                                <v-checkbox label="Sample checklist item 3" density="compact" hide-details
                                    class="mb-2" />
                                <v-btn size="small" variant="tonal" class="text-none">
                                    Add an item
                                </v-btn>
                            </div>

                            <!-- Activity -->
                            <div>
                                <div class="d-flex align-center mb-3">
                                    <v-icon class="mr-2 text-medium-emphasis">mdi-format-list-bulleted</v-icon>
                                    <h4 class="text-subtitle-1 font-weight-medium">Activity</h4>
                                    <v-spacer />
                                    <v-btn size="small" variant="tonal" class="text-none">
                                        Show details
                                    </v-btn>
                                </div>
                                <div class="d-flex mb-3">
                                    <v-avatar size="32" color="#E91E63" class="mr-3">
                                        <span class="text-caption font-weight-bold">JD</span>
                                    </v-avatar>
                                    <v-text-field variant="outlined" density="compact" placeholder="Write a comment..."
                                        hide-details />
                                </div>
                            </div>
                        </v-col>

                        <!-- Sidebar Actions -->
                        <v-col cols="12" md="3">
                            <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2">Add to card</h4>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-account-outline</v-icon>
                                Members
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-label-outline</v-icon>
                                Labels
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-checkbox-marked-outline</v-icon>
                                Checklist
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-clock-outline</v-icon>
                                Dates
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-paperclip</v-icon>
                                Attachment
                            </v-btn>

                            <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-4">Actions</h4>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-arrow-right</v-icon>
                                Move
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-content-copy</v-icon>
                                Copy
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                <v-icon start size="18">mdi-archive-outline</v-icon>
                                Archive
                            </v-btn>
                            <v-btn block variant="tonal" size="small" class="justify-start text-none" color="error">
                                <v-icon start size="18">mdi-delete-outline</v-icon>
                                Delete
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-dialog>
    </v-app>
</template>

<style scoped>
/* Board Color Dot */
.board-color-dot {
    width: 20px;
    height: 16px;
    border-radius: 4px;
}

/* Board Area */
.board-main {
    overflow: hidden;
}

.board-area {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.board-header {
    border-bottom: 1px solid rgb(var(--v-theme-surface-variant));
}

/* Member Avatar Stacking */
.member-avatar {
    margin-left: -8px;
    border: 2px solid rgb(var(--v-theme-surface));
}

.member-avatar:first-child {
    margin-left: 0;
}

/* Kanban Board */
.kanban-board {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
}

.lists-container {
    height: 100%;
    align-items: flex-start;
}

.kanban-list {
    width: 272px;
    min-width: 272px;
    max-height: calc(100vh - 180px);
    display: flex;
    flex-direction: column;
}

.list-header {
    flex-shrink: 0;
}

.cards-container {
    flex: 1;
    overflow-y: auto;
    min-height: 20px;
}

/* Card Styles */
.kanban-card {
    cursor: grab;
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.kanban-card:hover {
    transform: translateY(-2px);
}

.kanban-card:active {
    cursor: grabbing;
}

.card-label {
    width: 40px;
    height: 8px;
    border-radius: 4px;
}

.card-assignee {
    margin-left: -4px;
    border: 2px solid rgb(var(--v-theme-surface-variant));
}

.card-assignee:first-child {
    margin-left: 0;
}

/* Due Date Chips - Using Vuetify color prop when possible */
.due-chip {
    font-size: 11px !important;
}

.due-default {
    background: rgb(var(--v-theme-surface-variant)) !important;
}

.due-soon {
    background: rgb(var(--v-theme-warning)) !important;
    color: white !important;
}

.due-overdue {
    background: rgb(var(--v-theme-error)) !important;
    color: white !important;
}

.due-complete {
    background: rgb(var(--v-theme-success)) !important;
    color: white !important;
}

/* Add List */
.add-list-container {
    min-width: 272px;
}

.add-list-btn {
    min-height: 48px;
}

.add-list-form {
    width: 272px;
}

/* Card Title Input */
.card-title-input :deep(.v-field__input) {
    padding: 0 !important;
    min-height: auto !important;
}

/* Button letter spacing */
:deep(.v-btn) {
    letter-spacing: normal !important;
}

/* Drag and Drop Ghost Styles */
.ghost-list {
    opacity: 0.5;
    border: 2px dashed rgb(var(--v-theme-on-surface-variant));
}

.ghost-card {
    opacity: 0.5;
    border: 2px dashed rgb(var(--v-theme-on-surface-variant));
}

.sortable-chosen {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25) !important;
}
</style>
