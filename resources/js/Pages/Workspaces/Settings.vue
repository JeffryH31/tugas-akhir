<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    members: Array,
    availableUsers: Array,
});

// ===== Label Management =====
const showLabelDialog = ref(false);
const editingLabel = ref(null);
const labelForm = ref({ name: '', color: '#6366F1' });

const presetColors = [
    '#ef4444', '#f97316', '#f59e0b', '#22c55e', '#14b8a6',
    '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#6b7280',
];

const openCreateLabel = () => {
    editingLabel.value = null;
    labelForm.value = { name: '', color: '#6366F1' };
    showLabelDialog.value = true;
};

const openEditLabel = (label) => {
    editingLabel.value = label;
    labelForm.value = { name: label.name, color: label.color };
    showLabelDialog.value = true;
};

const saveLabel = () => {
    if (!labelForm.value.name.trim()) return;

    if (editingLabel.value) {
        router.patch(
            route('workspaces.labels.update', [props.workspace.id, editingLabel.value.id]),
            labelForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showLabelDialog.value = false;
                    if (window.showSnackbar) window.showSnackbar('Label updated!', 'success');
                },
            }
        );
    } else {
        router.post(
            route('workspaces.labels.store', props.workspace.id),
            labelForm.value,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showLabelDialog.value = false;
                    if (window.showSnackbar) window.showSnackbar('Label created!', 'success');
                },
            }
        );
    }
};

const deleteLabel = (label) => {
    if (!confirm(`Delete label "${label.name}"? It will be removed from all tasks.`)) return;

    router.delete(
        route('workspaces.labels.destroy', [props.workspace.id, label.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) window.showSnackbar('Label deleted!', 'success');
            },
        }
    );
};

// Add member dialog
const showAddMember = ref(false);
const selectedUser = ref(null);
const selectedRole = ref('member');

const addMember = () => {
    if (!selectedUser.value) return;

    router.post(
        route('workspaces.members.add', props.workspace.id),
        {
            user_id: selectedUser.value,
            role: selectedRole.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAddMember.value = false;
                selectedUser.value = null;
                selectedRole.value = 'member';
                if (window.showSnackbar) {
                    window.showSnackbar('Member added successfully!', 'success');
                }
            }
        }
    );
};

// Remove member
const removeMember = (member) => {
    if (!confirm(`Remove ${member.name} from workspace?`)) return;

    router.delete(
        route('workspaces.members.remove', props.workspace.id),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Member removed successfully!', 'success');
                }
            }
        }
    );
};

// Change role
const changeRole = (member, newRole) => {
    router.patch(
        route('workspaces.members.role', props.workspace.id),
        {
            user_id: member.id,
            role: newRole,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Role updated successfully!', 'success');
                }
            }
        }
    );
};

const getRoleBadgeColor = (role) => {
    switch (role) {
        case 'admin': return 'error';
        case 'member': return 'primary';
        case 'guest': return 'grey';
        default: return 'grey';
    }
};

// Delete workspace
const showDeleteWorkspace = ref(false);
const confirmationName = ref('');

const deleteWorkspace = () => {
    if (confirmationName.value !== props.workspace.name) {
        return;
    }

    router.delete(
        route('workspaces.destroy', props.workspace.id),
        {
            onSuccess: () => {
                confirmationName.value = '';
                if (window.showSnackbar) {
                    window.showSnackbar('Workspace deleted successfully!', 'success');
                }
            },
            onError: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Failed to delete workspace', 'error');
                }
            }
        }
    );
};
</script>

<template>
    <MainLayout :title="`${workspace?.name} Settings`">
        <div class="settings-page">
            <div class="settings-header">
                <h1 class="text-2xl font-bold">Workspace Settings</h1>
                <p class="text-gray-500 mt-1">Manage {{ workspace?.name }} settings and members</p>
            </div>

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="flex items-center justify-between">
                    <span>Members ({{ members?.length || 0 }})</span>
                    <v-btn v-if="isAdmin" color="primary" @click="showAddMember = true">
                        <v-icon start>mdi-account-plus</v-icon>
                        Add Member
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in members" :key="member.id">
                            <td>
                                <div class="flex items-center gap-3">
                                    <v-avatar size="32" :color="member.avatar_color || 'primary'">
                                        <img v-if="member.profile_photo_url" :src="member.profile_photo_url" />
                                        <span v-else class="text-xs">{{ member.initials }}</span>
                                    </v-avatar>
                                    <span class="font-medium">{{ member.name }}</span>
                                </div>
                            </td>
                            <td>{{ member.email }}</td>
                            <td>
                                <v-menu>
                                    <template v-slot:activator="{ props: menuProps }">
                                        <v-chip v-bind="menuProps"
                                            :color="getRoleBadgeColor(member.pivot?.role || member.role)" size="small"
                                            class="cursor-pointer">
                                            {{ (member.pivot?.role || member.role || 'member').toUpperCase() }}
                                        </v-chip>
                                    </template>
                                    <v-card color="surface">
                                        <v-list density="compact">
                                            <v-list-item title="Admin" @click="changeRole(member, 'admin')" />
                                            <v-list-item title="Member" @click="changeRole(member, 'member')" />
                                            <v-list-item title="Guest" @click="changeRole(member, 'guest')" />
                                        </v-list>
                                    </v-card>
                                </v-menu>
                            </td>
                            <td>
                                <v-btn icon variant="text" size="small" color="error" @click="removeMember(member)">
                                    <v-icon size="18">mdi-delete</v-icon>
                                </v-btn>
                            </td>
                        </tr>
                        <tr v-if="!members?.length">
                            <td colspan="4" class="text-center py-8 text-gray-500">
                                No members yet
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>

            <!-- Labels Management -->
            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="d-flex align-center justify-space-between">
                    <div class="d-flex align-center ga-2">
                        <v-icon color="primary">mdi-label-multiple-outline</v-icon>
                        <span>Labels</span>
                        <v-chip size="x-small" variant="tonal" color="primary">{{ workspace?.labels?.length || 0
                        }}</v-chip>
                    </div>
                    <v-btn color="primary" size="small" variant="tonal" @click="openCreateLabel">
                        <v-icon start size="16">mdi-plus</v-icon>
                        Add Label
                    </v-btn>
                </v-card-title>
                <v-divider />

                <!-- Empty state -->
                <div v-if="!workspace?.labels?.length" class="pa-8 text-center">
                    <v-icon size="48" color="grey" class="mb-3">mdi-label-off-outline</v-icon>
                    <div class="text-body-1 text-grey mb-1">No labels yet</div>
                    <div class="text-body-2 text-grey-darken-1 mb-4">Create labels to categorize and filter your tasks
                    </div>
                    <v-btn color="primary" variant="tonal" size="small" @click="openCreateLabel">
                        <v-icon start size="16">mdi-plus</v-icon>
                        Create First Label
                    </v-btn>
                </div>

                <!-- Labels list -->
                <v-list v-else lines="two" class="pa-0">
                    <template v-for="(label, index) in workspace?.labels" :key="label.id">
                        <v-list-item class="px-4 py-2">
                            <template #prepend>
                                <div class="w-10 h-10 rounded-lg d-flex align-center justify-center mr-3"
                                    :style="{ backgroundColor: label.color + '22' }">
                                    <v-icon :color="label.color" size="20">mdi-label</v-icon>
                                </div>
                            </template>

                            <v-list-item-title class="font-weight-medium">
                                {{ label.name }}
                            </v-list-item-title>
                            <v-list-item-subtitle>
                                <v-chip :color="label.color" size="x-small" variant="flat" class="mt-1">
                                    {{ label.color }}
                                </v-chip>
                            </v-list-item-subtitle>

                            <template #append>
                                <div class="d-flex ga-1">
                                    <v-btn icon variant="text" size="small" @click="openEditLabel(label)">
                                        <v-icon size="18">mdi-pencil-outline</v-icon>
                                        <v-tooltip activator="parent" location="top">Edit</v-tooltip>
                                    </v-btn>
                                    <v-btn icon variant="text" size="small" color="error" @click="deleteLabel(label)">
                                        <v-icon size="18">mdi-delete-outline</v-icon>
                                        <v-tooltip activator="parent" location="top">Delete</v-tooltip>
                                    </v-btn>
                                </div>
                            </template>
                        </v-list-item>
                        <v-divider v-if="index < workspace.labels.length - 1" />
                    </template>
                </v-list>
            </v-card>

            <!-- Danger Zone -->
            <v-card variant="outlined" rounded="lg" class="mt-6 border-error">
                <v-card-title class="text-error">
                    <v-icon start color="error">mdi-alert-circle</v-icon>
                    Danger Zone
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-semibold mb-1">Delete Workspace</div>
                            <div class="text-sm text-gray-500">
                                Permanently delete this workspace, all spaces, folders, lists, and tasks.
                                This action cannot be undone.
                            </div>
                        </div>
                        <v-btn color="error" variant="outlined" @click="showDeleteWorkspace = true">
                            <v-icon start>mdi-delete</v-icon>
                            Delete Workspace
                        </v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </div>

        <!-- Add Member Dialog -->
        <v-dialog v-model="showAddMember" max-width="500">
            <v-card>
                <v-card-title>Add Member</v-card-title>
                <v-card-text>
                    <v-select v-model="selectedUser" :items="availableUsers" item-title="name" item-value="id"
                        label="Select User" variant="outlined" class="mb-4" bg-color="#1e1e1e">
                        <template v-slot:item="{ props: itemProps, item }">
                            <v-list-item v-bind="itemProps">
                                <template v-slot:prepend>
                                    <v-avatar size="32" :color="item.raw.avatar_color || 'primary'">
                                        <span class="text-xs">{{ item.raw.initials }}</span>
                                    </v-avatar>
                                </template>
                                <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                                <v-list-item-subtitle>{{ item.raw.email }}</v-list-item-subtitle>
                            </v-list-item>
                        </template>
                    </v-select>

                    <v-select v-model="selectedRole" :items="[
                        { title: 'Admin', value: 'admin' },
                        { title: 'Member', value: 'member' },
                        { title: 'Guest', value: 'guest' },
                    ]" label="Role" variant="outlined" bg-color="#1e1e1e"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showAddMember = false">Cancel</v-btn>
                    <v-btn color="primary" @click="addMember">Add Member</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Label Dialog -->
        <v-dialog v-model="showLabelDialog" max-width="480">
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-2 pa-5 pb-2">
                    <v-icon :color="editingLabel ? 'warning' : 'primary'">{{ editingLabel ? 'mdi-pencil' :
                        'mdi-label-outline'
                    }}</v-icon>
                    {{ editingLabel ? 'Edit Label' : 'New Label' }}
                </v-card-title>

                <v-card-text class="pa-5">
                    <!-- Name input -->
                    <v-text-field v-model="labelForm.name" label="Label Name" variant="outlined"
                        placeholder="e.g. Bug, Feature, Enhancement" density="comfortable" autofocus
                        @keyup.enter="saveLabel">
                        <template #prepend-inner>
                            <v-icon :color="labelForm.color" size="20">mdi-label</v-icon>
                        </template>
                    </v-text-field>

                    <!-- Color picker -->
                    <div class="mt-4">
                        <div class="text-body-2 text-grey mb-3">Choose a color</div>
                        <div class="d-flex flex-wrap ga-2">
                            <div v-for="c in presetColors" :key="c" class="label-color-option"
                                :class="{ 'label-color-option--active': labelForm.color === c }"
                                :style="{ backgroundColor: c }" @click="labelForm.color = c">
                                <v-icon v-if="labelForm.color === c" size="16" color="white">mdi-check</v-icon>
                            </div>
                        </div>
                    </div>

                    <!-- Custom color -->
                    <v-text-field v-model="labelForm.color" label="Hex Color" variant="outlined" density="compact"
                        placeholder="#6366F1" class="mt-4" hide-details>
                        <template #prepend-inner>
                            <div class="w-5 h-5 rounded border mr-1" style="border-color: rgba(255,255,255,0.2);"
                                :style="{ backgroundColor: labelForm.color }" />
                        </template>
                    </v-text-field>

                    <!-- Preview -->
                    <div class="mt-5 pa-3 rounded-lg d-flex align-center ga-3"
                        style="background: rgba(255,255,255,0.04);">
                        <span class="text-body-2 text-grey">Preview:</span>
                        <v-chip :color="labelForm.color" size="small" variant="flat">
                            {{ labelForm.name || 'Label Name' }}
                        </v-chip>
                        <v-chip :color="labelForm.color" size="small" variant="tonal">
                            {{ labelForm.name || 'Label Name' }}
                        </v-chip>
                    </div>
                </v-card-text>

                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="showLabelDialog = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" :disabled="!labelForm.name.trim()" @click="saveLabel">
                        <v-icon start size="16">{{ editingLabel ? 'mdi-check' : 'mdi-plus' }}</v-icon>
                        {{ editingLabel ? 'Save Changes' : 'Create Label' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Workspace Dialog -->
        <v-dialog v-model="showDeleteWorkspace" max-width="500">
            <v-card>
                <v-card-title class="text-error d-flex align-center">
                    <v-icon start color="error">mdi-alert-circle</v-icon>
                    Delete Workspace?
                </v-card-title>
                <v-card-text>
                    <div class="mb-4">
                        Are you sure you want to permanently delete <strong>{{ workspace?.name }}</strong>?
                    </div>
                    <v-alert type="error" variant="outlined" density="compact">
                        This will delete:
                        <ul class="mt-2 ml-4">
                            <li>All spaces in this workspace</li>
                            <li>All folders and lists</li>
                            <li>All tasks and comments</li>
                            <li>All time tracking data</li>
                        </ul>
                        <div class="mt-2 font-semibold">This action cannot be undone!</div>
                    </v-alert>

                    <div class="mt-4">
                        <div class="text-sm mb-2">
                            Please type <strong>{{ workspace?.name }}</strong> to confirm:
                        </div>
                        <v-text-field v-model="confirmationName" variant="outlined" density="compact"
                            placeholder="Type workspace name" autofocus />
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showDeleteWorkspace = false; confirmationName = ''">Cancel</v-btn>
                    <v-btn color="error" :disabled="confirmationName !== workspace?.name" @click="deleteWorkspace">
                        <v-icon start>mdi-delete</v-icon>
                        Delete Permanently
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </MainLayout>
</template>

<style scoped>
.settings-page {
    padding: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

.settings-header {
    margin-bottom: 24px;
}

.label-color-option {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid transparent;
    transition: all 0.15s ease;
}

.label-color-option:hover {
    transform: scale(1.1);
    border-color: rgba(255, 255, 255, 0.3);
}

.label-color-option--active {
    border-color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.2);
}
</style>
