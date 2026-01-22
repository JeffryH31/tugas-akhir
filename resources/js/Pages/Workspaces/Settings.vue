<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    members: Array,
    availableUsers: Array,
});

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
                        label="Select User" variant="outlined" class="mb-4" bg-color="#1e1e1e" :menu-props="{ contentClass: 'bg-[#1e1e1e]' }">
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
                    ]" label="Role" variant="outlined" bg-color="#1e1e1e" :menu-props="{ contentClass: 'bg-[#1e1e1e]' }" />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showAddMember = false">Cancel</v-btn>
                    <v-btn color="primary" @click="addMember">Add Member</v-btn>
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
</style>
