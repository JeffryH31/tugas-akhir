<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import { useSnackbar } from '@/composables/useSnackbar';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    title: String,
});

const page = usePage();
const { mobile, smAndDown } = useDisplay();

// Snackbar — use composable for global state
const { snackbar, snackbarText, snackbarColor, showSnackbar } = useSnackbar();
const { isOpen: confirmOpen, dialogTitle, dialogMessage, dialogColor, onConfirm, onCancel } = useConfirmDialog();

// Sidebar state
const isSidebarOpen = ref(!mobile.value);
const isSidebarMini = ref(false);
const activeSpaceId = ref(null);

// Watch for mobile changes
watch(mobile, (val) => {
    isSidebarOpen.value = !val;
});

// User menu
const userMenuOpen = ref(false);
const notificationMenuOpen = ref(false);

// Computed
const user = computed(() => page.props.auth?.user);
const workspaces = computed(() => page.props.workspaces || []);
const activeWorkspace = computed(() => page.props.activeWorkspace);
const notifications = computed(() => page.props.notifications || []);
const unreadNotificationsCount = computed(() => page.props.unreadNotificationsCount || 0);

// Search
const searchQuery = ref('');
const searchDialog = ref(false);
const searchResults = ref({ tasks: [], lists: [], spaces: [] });
const isSearching = ref(false);
const searchType = ref('all');
const searchWorkspaceId = ref(null);
const searchTypeOptions = [
    { title: 'All', value: 'all' },
    { title: 'Tasks', value: 'tasks' },
    { title: 'Products', value: 'lists' },
    { title: 'Spaces', value: 'spaces' },
];
const searchWorkspaceOptions = computed(() => [
    { title: 'All Workspaces', value: null },
    ...workspaces.value.map((w) => ({ title: w.name, value: w.id })),
]);

// Debounced search function
let searchTimeout = null;
const performSearch = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = { tasks: [], lists: [], spaces: [] };
        return;
    }

    isSearching.value = true;
    try {
        const params = new URLSearchParams();
        params.set('q', searchQuery.value);
        params.set('type', searchType.value);
        if (searchWorkspaceId.value) params.set('workspace_id', String(searchWorkspaceId.value));

        const response = await fetch(route('search') + '?' + params.toString());
        const data = await response.json();
        searchResults.value = data;
    } catch (error) {
        showSnackbar('Search failed. Please try again.', 'error');
    } finally {
        isSearching.value = false;
    }
};

watch([searchQuery, searchType, searchWorkspaceId], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 300);
});

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) showSnackbar(flash.success, 'success');
        if (flash?.error) showSnackbar(flash.error, 'error');
    },
    { deep: true }
);

watch(
    () => page.props.validationErrors,
    (errors) => {
        if (Array.isArray(errors) && errors.length > 0) {
            showSnackbar(errors[0], 'error');
        }
    }
);

const goToTask = (task) => {
    router.visit(route('tasks.show', [
        task.task_list.space.workspace_id,
        task.task_list.space_id,
        task.task_list_id,
        task.id
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const goToList = (list) => {
    router.visit(route('lists.show', [
        list.space.workspace_id,
        list.space_id,
        list.id
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const goToSpace = (space) => {
    router.visit(route('spaces.show', [
        space.workspace_id,
        space.id
    ]));
    searchDialog.value = false;
    searchQuery.value = '';
    searchType.value = 'all';
    searchWorkspaceId.value = null;
};

const markNotificationsRead = () => {
    if (!unreadNotificationsCount.value) return;

    router.post(route('notifications.read'), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications', 'unreadNotificationsCount', 'flash'],
    });
};

// Running timer from shared props
const runningTimer = computed(() => page.props.runningTimer);
const timerElapsed = ref(0);
let timerInterval = null;

const startGlobalTimerInterval = () => {
    if (timerInterval) clearInterval(timerInterval);
    if (runningTimer.value?.started_at) {
        timerElapsed.value = Math.floor((Date.now() - new Date(runningTimer.value.started_at).getTime()) / 1000);
        timerInterval = setInterval(() => { timerElapsed.value++; }, 1000);
    }
};

watch(runningTimer, (val) => {
    if (val?.started_at) {
        startGlobalTimerInterval();
    } else {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        timerElapsed.value = 0;
    }
}, { immediate: true });

// Create Space dialog
const showCreateSpace = ref(false);
const newSpaceName = ref('');
const newSpaceDescription = ref('');
const newSpaceColor = ref('#6366F1');
const isCreatingSpace = ref(false);

const createSpace = () => {
    if (!newSpaceName.value.trim() || !activeWorkspace.value || isCreatingSpace.value) return;
    isCreatingSpace.value = true;

    router.post(
        route('spaces.store', activeWorkspace.value.id),
        {
            name: newSpaceName.value.trim(),
            description: newSpaceDescription.value.trim() || null,
            color: newSpaceColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newSpaceName.value = '';
                newSpaceDescription.value = '';
                newSpaceColor.value = '#6366F1';
                showCreateSpace.value = false;
                showSnackbar('Space created successfully!', 'success');
                router.reload({ only: ['activeWorkspace'] });
            },
            onFinish: () => { isCreatingSpace.value = false; }
        }
    );
};

// Logout
const logout = () => {
    router.post(route('logout'));
};

// Expose functions to window for global access
onMounted(() => {
    if (typeof window !== 'undefined') {
        window.showSnackbar = showSnackbar;
        window.openCreateSpaceDialog = () => {
            showCreateSpace.value = true;
        };
    }
    startGlobalTimerInterval();
});

onUnmounted(() => {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
});

// Format duration helper
const formatDuration = (seconds) => {
    if (!seconds) return '00:00:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
};
</script>

<template>
    <v-app class="clickup-app">

        <Head :title="title" />

        <!-- Top Navigation Bar -->
        <v-app-bar color="surface" elevation="0" height="48" class="border-b border-[#2d2d30]">
            <!-- Sidebar Toggle -->
            <v-btn icon variant="text" size="small" @click="isSidebarOpen = !isSidebarOpen">
                <v-icon>mdi-menu</v-icon>
            </v-btn>

            <!-- Logo -->
            <Link :href="route('dashboard')" class="flex items-center ml-2">
                <div class="flex items-center gap-2">
                    <span v-if="!smAndDown" class="text-lg font-semibold text-white">Project Management</span>
                </div>
            </Link>

            <v-spacer />

            <!-- Search -->
            <v-btn variant="tonal" color="surface-light" rounded="lg" size="small" class="mr-2 px-4"
                @click="searchDialog = true">
                <v-icon start size="18">mdi-magnify</v-icon>
                <span v-if="!smAndDown" class="text-sm text-gray-400">Search...</span>
            </v-btn>

            <!-- Running Timer Indicator -->
            <v-chip v-if="runningTimer" color="success" size="small" class="mr-2" variant="tonal">
                <v-icon start size="14">mdi-timer-outline</v-icon>
                {{ formatDuration(timerElapsed) }}
            </v-chip>

            <!-- Create Button -->
            <v-menu location="bottom end">
                <template v-slot:activator="{ props: createMenuProps }">
                    <v-btn v-bind="createMenuProps" color="primary" size="small" rounded="lg" class="mr-2">
                        <v-icon start size="18">mdi-plus</v-icon>
                        <span v-if="!smAndDown">Create</span>
                    </v-btn>
                </template>
                <v-list density="compact" min-width="180">
                    <v-list-item prepend-icon="mdi-folder-plus-outline" title="New Space" @click="showCreateSpace = true" />
                </v-list>
            </v-menu>

            <!-- Notifications -->
            <v-menu v-model="notificationMenuOpen" location="bottom end" @update:model-value="(open) => open && markNotificationsRead()">
                <template v-slot:activator="{ props: notificationProps }">
                    <v-btn v-bind="notificationProps" icon variant="text" size="small" class="mr-1">
                        <v-badge :content="unreadNotificationsCount" :model-value="unreadNotificationsCount > 0" color="error">
                            <v-icon>mdi-bell-outline</v-icon>
                        </v-badge>
                    </v-btn>
                </template>
                <v-card min-width="340" max-width="420">
                    <v-card-title class="d-flex align-center justify-space-between">
                        <span>Notifications</span>
                        <v-chip size="x-small" variant="tonal">{{ unreadNotificationsCount }} unread</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-list density="compact" max-height="360" class="overflow-auto">
                        <v-list-item v-for="notification in notifications" :key="notification.id">
                            <v-list-item-title class="text-body-2">{{ notification.description }}</v-list-item-title>
                            <v-list-item-subtitle>{{ notification.created_at }}</v-list-item-subtitle>
                        </v-list-item>
                        <v-list-item v-if="!notifications.length" disabled>
                            <v-list-item-title>No notifications yet.</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-card>
            </v-menu>

            <!-- User Menu -->
            <v-menu v-model="userMenuOpen" :close-on-content-click="false" location="bottom end">
                <template v-slot:activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" icon variant="text" size="small">
                        <v-avatar size="32" :color="user?.avatar_color || 'primary'">
                            <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" :alt="user?.name" />
                            <span v-else class="text-xs font-medium">{{ user?.initials }}</span>
                        </v-avatar>
                    </v-btn>
                </template>

                <v-card width="280" class="mt-2">
                    <v-card-text class="pb-2">
                        <div class="flex items-center gap-3 mb-3">
                            <v-avatar size="40" :color="user?.avatar_color || 'primary'">
                                <img v-if="user?.profile_photo_url" :src="user.profile_photo_url" :alt="user?.name" />
                                <span v-else class="text-sm font-medium">{{ user?.initials }}</span>
                            </v-avatar>
                            <div>
                                <div class="font-medium">{{ user?.name }}</div>
                                <div class="text-xs text-gray-400">{{ user?.email }}</div>
                            </div>
                        </div>
                    </v-card-text>
                    <v-divider />
                    <v-list density="compact">
                        <v-list-item prepend-icon="mdi-account-outline" title="Profile" :href="route('profile.show')" />
                        <v-list-item prepend-icon="mdi-cog-outline" title="Settings"
                            :href="activeWorkspace ? route('workspaces.settings', activeWorkspace.id) : undefined"
                            :disabled="!activeWorkspace" />
                        <v-list-item prepend-icon="mdi-chart-box-outline" title="Analytics"
                            :href="activeWorkspace ? route('workspaces.analytics', activeWorkspace.id) : undefined"
                            :disabled="!activeWorkspace" />
                        <v-list-item prepend-icon="mdi-delete-clock-outline" title="Recycle Bin"
                            :href="activeWorkspace ? route('workspaces.recycle-bin.index', activeWorkspace.id) : undefined"
                            :disabled="!activeWorkspace" />
                        <v-list-item prepend-icon="mdi-theme-light-dark" title="Theme" />
                    </v-list>
                    <v-divider />
                    <v-list density="compact">
                        <v-list-item prepend-icon="mdi-logout" title="Log Out" @click="logout" />
                    </v-list>
                </v-card>
            </v-menu>
        </v-app-bar>

        <!-- Sidebar -->
        <v-navigation-drawer v-model="isSidebarOpen" :rail="isSidebarMini" :temporary="mobile" color="surface"
            width="280" class="border-r border-[#2d2d30]">
            <div class="flex flex-col h-full">
                <!-- Workspace Selector -->
                <div class="border-b border-[#2d2d30]" :class="isSidebarMini ? 'px-1 py-3' : 'px-3 py-3'">
                    <v-menu>
                        <template v-slot:activator="{ props: menuProps }">
                            <v-btn v-bind="menuProps" variant="text" :block="!isSidebarMini"
                                :icon="isSidebarMini" :class="isSidebarMini ? 'mx-auto' : 'justify-start text-left'">
                                <div class="flex items-center gap-2" :class="{ 'w-full': !isSidebarMini }">
                                    <v-tooltip v-if="isSidebarMini" location="right" :text="activeWorkspace?.name || 'Select Workspace'">
                                        <template #activator="{ props: tipProps }">
                                            <div v-bind="tipProps" class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                                                :style="{ backgroundColor: activeWorkspace?.color || '#6366F1' }">
                                                {{ activeWorkspace?.name?.charAt(0)?.toUpperCase() || 'W' }}
                                            </div>
                                        </template>
                                    </v-tooltip>
                                    <div v-else class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                                        :style="{ backgroundColor: activeWorkspace?.color || '#6366F1' }">
                                        {{ activeWorkspace?.name?.charAt(0)?.toUpperCase() || 'W' }}
                                    </div>
                                    <div v-if="!isSidebarMini" class="flex-1 min-w-0">
                                        <div class="font-medium truncate">{{ activeWorkspace?.name || 'Select Workspace' }}</div>
                                    </div>
                                    <v-icon v-if="!isSidebarMini" size="18">mdi-chevron-down</v-icon>
                                </div>
                            </v-btn>
                        </template>

                        <v-card width="280" color="surface">
                            <v-list density="compact">
                                <v-list-subheader>WORKSPACES</v-list-subheader>
                                <v-list-item v-for="workspace in workspaces" :key="workspace.id"
                                    :active="workspace.id === activeWorkspace?.id"
                                    @click="router.post(route('workspaces.switch', workspace.id))">
                                    <template v-slot:prepend>
                                        <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold mr-2"
                                            :style="{ backgroundColor: workspace.color || '#6366F1' }">
                                            {{ workspace.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                    </template>
                                    <v-list-item-title>{{ workspace.name }}</v-list-item-title>
                                </v-list-item>
                                <v-divider class="my-2" />
                                <v-list-item prepend-icon="mdi-plus" title="Create Workspace" />
                            </v-list>
                        </v-card>
                    </v-menu>
                </div>

                <!-- Navigation Links -->
                <div :class="isSidebarMini ? 'px-1 py-2' : 'px-2 py-2'">
                    <v-list density="compact" nav>
                        <v-tooltip v-for="navItem in [
                            { href: route('dashboard'), active: route().current('dashboard'), icon: 'mdi-home-outline', label: 'Home', show: true },
                            { href: route('my-tasks'), active: route().current('my-tasks'), icon: 'mdi-checkbox-marked-circle-outline', label: 'My Tasks', show: true },
                            { href: activeWorkspace ? route('calendar.index', activeWorkspace.id) : undefined, active: route().current('calendar.*'), icon: 'mdi-calendar-month-outline', label: 'Calendar', show: !!activeWorkspace },
                            { href: route('time-tracking.index'), active: route().current('time-tracking.*'), icon: 'mdi-timer-outline', label: 'Time Tracking', show: true },
                        ]" :key="navItem.label" location="right" :text="navItem.label" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="navItem.show" v-bind="tipProps" :href="navItem.href" :active="navItem.active"
                                    :prepend-icon="navItem.icon" :title="isSidebarMini ? undefined : navItem.label" rounded="lg" />
                            </template>
                        </v-tooltip>
                    </v-list>
                </div>

                <v-divider class="my-1" />

                <!-- Favorites -->
                <div v-if="activeWorkspace?.starred_spaces?.length" :class="isSidebarMini ? 'px-1 py-2' : 'px-2 py-2'">
                    <div v-if="!isSidebarMini" class="px-3 py-1 text-xs font-medium text-gray-500 uppercase">
                        Favorites
                    </div>
                    <v-list density="compact" nav>
                        <v-tooltip v-for="space in activeWorkspace?.starred_spaces || []" :key="space.id"
                            location="right" :text="space.name" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-bind="tipProps"
                                    :href="route('spaces.show', [activeWorkspace.id, space.id])" rounded="lg">
                                    <template v-slot:prepend>
                                        <div class="w-2 h-2 rounded-full" :class="isSidebarMini ? '' : 'mr-3'"
                                            :style="{ backgroundColor: space.color || '#6366F1' }" />
                                    </template>
                                    <v-list-item-title v-if="!isSidebarMini" class="text-sm">{{ space.name }}</v-list-item-title>
                                </v-list-item>
                            </template>
                        </v-tooltip>
                    </v-list>
                </div>

                <!-- Spaces -->
                <div class="flex-1 overflow-y-auto py-2" :class="isSidebarMini ? 'px-1' : 'px-2'">
                    <div v-if="!isSidebarMini" class="flex items-center justify-between px-3 py-1">
                        <span class="text-xs font-medium text-gray-500 uppercase">Spaces</span>
                        <v-btn icon variant="text" size="x-small" @click="showCreateSpace = true">
                            <v-icon size="16">mdi-plus</v-icon>
                        </v-btn>
                    </div>

                    <!-- View All Spaces Link -->
                    <v-list density="compact" nav class="mb-2">
                        <v-tooltip location="right" text="View All Spaces" :disabled="!isSidebarMini">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-if="activeWorkspace" v-bind="tipProps"
                                    :href="route('workspaces.show', activeWorkspace.id)"
                                    prepend-icon="mdi-view-grid-outline"
                                    :title="isSidebarMini ? undefined : 'View All Spaces'" rounded="lg" class="text-sm" />
                            </template>
                        </v-tooltip>
                    </v-list>

                    <!-- Mini mode: icon-only space list -->
                    <v-list v-if="isSidebarMini" density="compact" nav>
                        <v-tooltip v-for="space in activeWorkspace?.spaces || []" :key="space.id"
                            location="right" :text="space.name">
                            <template #activator="{ props: tipProps }">
                                <v-list-item v-bind="tipProps"
                                    :href="route('spaces.show', [activeWorkspace.id, space.id])" rounded="lg">
                                    <template v-slot:prepend>
                                        <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs"
                                            :style="{ backgroundColor: space.color || '#6366F1' }">
                                            <v-icon size="14">{{ space.icon || 'mdi-folder-outline' }}</v-icon>
                                        </div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-tooltip>
                    </v-list>

                    <!-- Full mode: expandable space tree -->
                    <v-list v-else density="compact" nav>
                        <template v-for="space in activeWorkspace?.spaces || []" :key="space.id">
                            <v-list-group :value="space.id">
                                <template v-slot:activator="{ props: groupProps }">
                                    <v-list-item v-bind="groupProps" rounded="lg">
                                        <template v-slot:prepend>
                                            <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs mr-2"
                                                :style="{ backgroundColor: space.color || '#6366F1' }">
                                                <v-icon size="14">{{ space.icon || 'mdi-folder-outline' }}</v-icon>
                                            </div>
                                        </template>
                                        <v-list-item-title class="text-sm font-medium">{{ space.name }}</v-list-item-title>
                                    </v-list-item>
                                </template>

                                <!-- Folders -->
                                <template v-for="folder in space.folders || []" :key="folder.id">
                                    <v-list-group :value="'folder-' + folder.id">
                                        <template v-slot:activator="{ props: folderProps }">
                                            <v-list-item v-bind="folderProps" rounded="lg" class="pl-8">
                                                <template v-slot:prepend>
                                                    <v-icon size="16" class="mr-1">mdi-folder-outline</v-icon>
                                                </template>
                                                <v-list-item-title class="text-sm">{{ folder.name }}</v-list-item-title>
                                            </v-list-item>
                                        </template>

                                        <!-- Products in Folder -->
                                        <v-list-item v-for="list in folder.lists || []" :key="list.id"
                                            :href="route('lists.show', [activeWorkspace?.id, space.id, list.id])"
                                            rounded="lg" class="pl-12">
                                            <template v-slot:prepend>
                                                <v-icon size="14" class="mr-1">mdi-package-variant-closed</v-icon>
                                            </template>
                                            <v-list-item-title class="text-sm">{{ list.name }}</v-list-item-title>
                                        </v-list-item>
                                    </v-list-group>
                                </template>

                                <!-- Products without Folder -->
                                <v-list-item v-for="list in space.lists_without_folder || []" :key="list.id"
                                    :href="route('lists.show', [activeWorkspace?.id, space.id, list.id])" rounded="lg" class="pl-8">
                                    <template v-slot:prepend>
                                        <v-icon size="14" class="mr-1">mdi-package-variant-closed</v-icon>
                                    </template>
                                    <v-list-item-title class="text-sm">{{ list.name }}</v-list-item-title>
                                </v-list-item>
                            </v-list-group>
                        </template>
                    </v-list>
                </div>

                <!-- Sidebar Footer -->
                <div class="border-t border-[#2d2d30]" :class="isSidebarMini ? 'px-1 py-2' : 'px-3 py-2'">
                    <v-tooltip location="right" text="Expand" :disabled="!isSidebarMini">
                        <template #activator="{ props: tipProps }">
                            <v-btn v-bind="tipProps" variant="text" :block="!isSidebarMini" size="small"
                                :icon="isSidebarMini" :class="isSidebarMini ? 'mx-auto' : 'justify-start'"
                                @click="isSidebarMini = !isSidebarMini">
                                <v-icon>{{ isSidebarMini ? 'mdi-chevron-right' : 'mdi-chevron-left' }}</v-icon>
                                <span v-if="!isSidebarMini" class="ml-2 text-sm">Collapse</span>
                            </v-btn>
                        </template>
                    </v-tooltip>
                </div>
            </div>
        </v-navigation-drawer>

        <!-- Main Content -->
        <v-main class="bg-background">
            <slot />
        </v-main>

        <!-- Search Dialog -->
        <v-dialog v-model="searchDialog" max-width="700" scrollable>
            <v-card>
                <v-text-field v-model="searchQuery" placeholder="Search tasks, products, spaces..."
                    prepend-inner-icon="mdi-magnify" variant="solo" single-line hide-details autofocus
                    class="search-dialog-input" :loading="isSearching" />
                <div class="px-4 py-3 d-flex ga-3 flex-wrap">
                    <v-select v-model="searchType" :items="searchTypeOptions" item-title="title" item-value="value"
                        label="Type" hide-details density="compact" variant="outlined" style="min-width: 180px;" />
                    <v-select v-model="searchWorkspaceId" :items="searchWorkspaceOptions" item-title="title" item-value="value"
                        label="Workspace" hide-details density="compact" variant="outlined" clearable style="min-width: 220px;" />
                </div>
                <v-divider />
                <v-card-text class="pa-0" style="max-height: 500px;">
                    <div v-if="!searchQuery || searchQuery.length < 2" class="pa-8 text-center text-gray-500">
                        <v-icon size="48" class="mb-2">mdi-magnify</v-icon>
                        <div>Type at least 2 characters to search</div>
                    </div>
                    <div v-else-if="isSearching" class="pa-8 text-center">
                        <v-progress-circular indeterminate color="primary" />
                    </div>
                    <div v-else-if="!searchResults.tasks.length && !searchResults.lists.length && !searchResults.spaces.length"
                        class="pa-8 text-center text-gray-500">
                        <v-icon size="48" class="mb-2">mdi-file-search-outline</v-icon>
                        <div>No results found for "{{ searchQuery }}"</div>
                    </div>
                    <div v-else>
                        <!-- Tasks -->
                        <v-list v-if="searchResults.tasks.length">
                            <v-list-subheader>TASKS ({{ searchResults.tasks.length }})</v-list-subheader>
                            <v-list-item v-for="task in searchResults.tasks" :key="task.id" @click="goToTask(task)">
                                <template v-slot:prepend>
                                    <v-icon>mdi-checkbox-marked-circle-outline</v-icon>
                                </template>
                                <v-list-item-title>{{ task.name }}</v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ task.task_list?.space?.name }} / {{ task.task_list?.name }}
                                </v-list-item-subtitle>
                            </v-list-item>
                        </v-list>

                        <!-- Products -->
                        <v-list v-if="searchResults.lists.length">
                            <v-list-subheader>PRODUCTS ({{ searchResults.lists.length }})</v-list-subheader>
                            <v-list-item v-for="list in searchResults.lists" :key="list.id" @click="goToList(list)">
                                <template v-slot:prepend>
                                    <v-icon>mdi-package-variant-closed</v-icon>
                                </template>
                                <v-list-item-title>{{ list.name }}</v-list-item-title>
                                <v-list-item-subtitle>{{ list.space?.name }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>

                        <!-- Spaces -->
                        <v-list v-if="searchResults.spaces.length">
                            <v-list-subheader>SPACES ({{ searchResults.spaces.length }})</v-list-subheader>
                            <v-list-item v-for="space in searchResults.spaces" :key="space.id"
                                @click="goToSpace(space)">
                                <template v-slot:prepend>
                                    <div class="w-6 h-6 rounded flex items-center justify-center text-white text-xs"
                                        :style="{ backgroundColor: space.color || '#6366F1' }">
                                        <v-icon size="14" color="white">{{ space.icon || 'mdi-folder' }}</v-icon>
                                    </div>
                                </template>
                                <v-list-item-title>{{ space.name }}</v-list-item-title>
                                <v-list-item-subtitle>{{ space.workspace?.name }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>

        <!-- Create Space Dialog -->
        <v-dialog v-model="showCreateSpace" max-width="500">
            <v-card>
                <v-card-title>Create Space</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newSpaceName" label="Space Name"
                        placeholder="e.g., Marketing, Engineering, Sales" variant="outlined" autofocus
                        @keydown.enter="createSpace" />
                    <v-textarea v-model="newSpaceDescription" label="Description (Optional)"
                        placeholder="What is this space for?" variant="outlined" rows="3" class="mt-4" />
                    <div class="mt-4">
                        <div class="text-sm font-medium mb-2">Space Color</div>
                        <div class="flex gap-2">
                            <div v-for="color in ['#6366F1', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#6B7280']"
                                :key="color" class="w-8 h-8 rounded-lg cursor-pointer border-2 transition-all"
                                :class="{ 'border-white scale-110': newSpaceColor === color, 'border-transparent': newSpaceColor !== color }"
                                :style="{ backgroundColor: color }" @click="newSpaceColor = color" />
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateSpace = false">Cancel</v-btn>
                    <v-btn color="primary" :loading="isCreatingSpace" @click="createSpace">Create Space</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Global Snackbar -->
        <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="bottom right">
            {{ snackbarText }}
            <template v-slot:actions>
                <v-btn variant="text" @click="snackbar = false">
                    Close
                </v-btn>
            </template>
        </v-snackbar>

        <!-- Global Confirm Dialog -->
        <v-dialog v-model="confirmOpen" max-width="400" persistent>
            <v-card>
                <v-card-title :class="`text-${dialogColor}`">{{ dialogTitle }}</v-card-title>
                <v-card-text>{{ dialogMessage }}</v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="onCancel">Cancel</v-btn>
                    <v-btn :color="dialogColor" @click="onConfirm">Confirm</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-app>
</template>

<style scoped>
.clickup-app {
    --v-theme-surface: #1e1e1e;
    --v-theme-surface-light: #2d2d30;
    --v-theme-background: #121212;
}

.border-b {
    border-bottom-width: 1px;
    border-bottom-style: solid;
}

.border-r {
    border-right-width: 1px;
    border-right-style: solid;
}

.border-t {
    border-top-width: 1px;
    border-top-style: solid;
}

:deep(.v-navigation-drawer__content) {
    display: flex;
    flex-direction: column;
}

:deep(.v-navigation-drawer .v-list) {
    background-color: transparent !important;
}

/* Override Vuetify's default list indentation */
:deep(.v-list-group__items) {
    --indent-padding: 0px !important;
}

:deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 16px !important;
}

:deep(.v-list-group__items .v-list-group__items .v-list-item) {
    padding-inline-start: 32px !important;
}

:deep(.v-list-item__prepend) {
    width: auto !important;
}

/* Rail mode overrides */
:deep(.v-navigation-drawer--rail) .v-list-item {
    justify-content: center;
}

:deep(.v-navigation-drawer--rail) .v-list-item__prepend {
    margin-right: 0 !important;
}

.search-dialog-input :deep(.v-field) {
    border-radius: 0;
}
</style>
