<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    statistics: Object,
});

// Create Space dialog
const showCreateSpace = ref(false);
const newSpaceName = ref('');
const newSpaceDescription = ref('');
const newSpaceColor = ref('#6366F1');

const normalizeHexColor = (value, fallback = '#6366F1') => {
    const raw = (value || '').trim();
    if (!raw) return fallback;
    const hex = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.toUpperCase() : fallback;
};

const createSpace = () => {
    if (!newSpaceName.value.trim()) return;

    router.post(
        route('spaces.store', props.workspace.id),
        {
            name: newSpaceName.value.trim(),
            description: newSpaceDescription.value.trim() || null,
            color: normalizeHexColor(newSpaceColor.value),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newSpaceName.value = '';
                newSpaceDescription.value = '';
                newSpaceColor.value = '#6366F1';
                showCreateSpace.value = false;
                if (window.showSnackbar) {
                    window.showSnackbar('Space created successfully!', 'success');
                }
            },
        }
    );
};
</script>

<template>
    <MainLayout :title="workspace?.name || 'Workspace'">
        <div class="workspace-page">
            <!-- Header -->
            <div class="workspace-header">
                <div>
                    <h1 class="text-2xl font-bold mb-1">{{ workspace?.name }}</h1>
                    <p class="text-gray-500">Manage your spaces and projects</p>
                </div>
                <v-btn color="primary" @click="showCreateSpace = true">
                    <v-icon start>mdi-plus</v-icon>
                    Create Space
                </v-btn>
            </div>

            <!-- Spaces Grid -->
            <div v-if="workspace?.spaces?.length" class="spaces-grid">
                <v-card v-for="space in workspace.spaces" :key="space.id" variant="outlined" rounded="lg"
                    class="space-card" hover @click="router.visit(route('spaces.show', [workspace.id, space.id]))">
                    <v-card-text class="pa-4">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white flex-shrink-0"
                                :style="{ backgroundColor: space.color || '#6366F1' }">
                                <v-icon color="white" size="24">{{ space.icon || 'mdi-folder' }}</v-icon>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-lg mb-1">{{ space.name }}</div>
                                <p v-if="space.description" class="text-sm text-gray-500 line-clamp-2">
                                    {{ space.description }}
                                </p>
                                <div class="flex items-center gap-4 mt-3 text-sm text-gray-400">
                                    <div class="flex items-center gap-1">
                                        <v-icon size="16">mdi-package-variant-closed</v-icon>
                                        <span>{{ space.lists_count || 0 }} products</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <v-icon size="16">mdi-checkbox-marked-circle-outline</v-icon>
                                        <span>{{ space.tasks_count || 0 }} tasks</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <v-icon size="80" color="grey-darken-1" class="mb-4">mdi-folder-open-outline</v-icon>
                <h2 class="text-xl font-semibold mb-2">No spaces yet</h2>
                <p class="text-gray-500">Create your first space to organize your work</p>
            </div>
        </div>

        <!-- Create Space Dialog -->
        <v-dialog v-model="showCreateSpace" max-width="500">
            <v-card>
                <v-card-title>Create Space</v-card-title>
                <v-card-text>
                    <v-text-field v-model="newSpaceName" label="Space Name" placeholder="e.g., Marketing, Development"
                        variant="outlined" autofocus class="mb-3" @keydown.enter="createSpace" />
                    <v-textarea v-model="newSpaceDescription" label="Description (Optional)" variant="outlined" rows="3"
                        class="mb-3" />
                    <div>
                        <div class="text-sm font-medium mb-2">Space Color</div>
                        <div class="d-flex align-center ga-3">
                            <input v-model="newSpaceColor" type="color" class="color-input-native" />
                            <v-text-field
                                v-model="newSpaceColor"
                                label="Hex Color"
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="flex-1"
                                @blur="newSpaceColor = normalizeHexColor(newSpaceColor)"
                            />
                        </div>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateSpace = false">Cancel</v-btn>
                    <v-btn color="primary" @click="createSpace">Create</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.workspace-page {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
}

.spaces-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.space-card {
    cursor: pointer;
    transition: all 0.2s;
}

.space-card:hover {
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
}

.line-clamp-2 {
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.color-input-native {
    width: 48px;
    height: 38px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 8px;
    background: transparent;
    padding: 4px;
    cursor: pointer;
}

.color-input-native::-webkit-color-swatch-wrapper {
    padding: 0;
}

.color-input-native::-webkit-color-swatch {
    border: none;
    border-radius: 5px;
}
</style>
