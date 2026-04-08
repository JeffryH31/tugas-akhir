<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    members: Array,
    availableUsers: Array,
    projectLists: Array,
    spaces: Array,
});

const page = usePage();

const isAdmin = computed(() => {
    const currentUserId = page.props?.auth?.user?.id;
    const currentMember = props.members?.find((member) => member.id === currentUserId);
    const role = currentMember?.pivot?.role || currentMember?.role;

    return role === 'owner' || role === 'admin';
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
const showCreateUser = ref(false);
const showEditUser = ref(false);
const editingUser = ref(null);

const createUserForm = ref({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    hourly_rate: 25,
    role: 'member',
});

const editUserForm = ref({
    user_id: null,
    name: '',
    email: '',
    hourly_rate: 25,
});

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

const openCreateUserDialog = () => {
    createUserForm.value = {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        hourly_rate: 25,
        role: 'member',
    };
    showCreateUser.value = true;
};

const createUser = () => {
    if (!createUserForm.value.name.trim() || !createUserForm.value.email.trim()) {
        return;
    }

    router.post(
        route('workspaces.members.users.store', props.workspace.id),
        createUserForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showCreateUser.value = false;
                createUserForm.value = {
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    hourly_rate: 25,
                    role: 'member',
                };
                if (window.showSnackbar) {
                    window.showSnackbar('User created successfully!', 'success');
                }
            },
        }
    );
};

const openEditUserDialog = (member) => {
    editingUser.value = member;
    editUserForm.value = {
        user_id: member.id,
        name: member.name || '',
        email: member.email || '',
        hourly_rate: Number(member.hourly_rate ?? 25),
    };
    showEditUser.value = true;
};

const updateUser = () => {
    if (!editUserForm.value.user_id || !editUserForm.value.name.trim() || !editUserForm.value.email.trim()) {
        return;
    }

    router.patch(
        route('workspaces.members.users.update', props.workspace.id),
        editUserForm.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                showEditUser.value = false;
                editingUser.value = null;
                if (window.showSnackbar) {
                    window.showSnackbar('User updated successfully!', 'success');
                }
            },
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

const spaceRoleItems = [
    { title: 'Owner', value: 'owner' },
    { title: 'Admin', value: 'admin' },
    { title: 'Manager', value: 'manager' },
    { title: 'Member', value: 'member' },
    { title: 'Guest', value: 'guest' },
];

const selectedSpaceId = ref(props.spaces?.[0]?.id ?? null);
const showAddSpaceMember = ref(false);
const selectedSpaceUser = ref(null);
const selectedSpaceRole = ref('member');

const spaceOptions = computed(() => {
    return (props.spaces || []).map((space) => ({
        title: `${space.name}${space.is_private ? ' (Private)' : ''}`,
        value: space.id,
    }));
});

const selectedSpace = computed(() => {
    const selectedId = Number(selectedSpaceId.value);
    return (props.spaces || []).find((space) => space.id === selectedId) || null;
});

const canManageSelectedSpace = computed(() => {
    if (isAdmin.value) {
        return true;
    }

    const currentUserId = page.props?.auth?.user?.id;
    const spaceMember = selectedSpace.value?.members?.find((member) => member.id === currentUserId);

    return ['owner', 'admin', 'manager'].includes(spaceMember?.role || '');
});

const availableSpaceUsers = computed(() => {
    if (!selectedSpace.value) {
        return [];
    }

    const assignedIds = new Set((selectedSpace.value.members || []).map((member) => member.id));
    return (props.members || []).filter((member) => !assignedIds.has(member.id));
});

const getSpaceRoleBadgeColor = (role) => {
    switch (role) {
        case 'owner': return 'error';
        case 'admin': return 'warning';
        case 'manager': return 'info';
        case 'member': return 'primary';
        case 'guest': return 'grey';
        default: return 'grey';
    }
};

const openAddSpaceMemberDialog = () => {
    selectedSpaceUser.value = null;
    selectedSpaceRole.value = 'member';
    showAddSpaceMember.value = true;
};

const addSpaceMember = () => {
    if (!selectedSpace.value || !selectedSpaceUser.value) {
        return;
    }

    router.post(
        route('spaces.members.add', [props.workspace.id, selectedSpace.value.id]),
        {
            user_id: selectedSpaceUser.value,
            role: selectedSpaceRole.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showAddSpaceMember.value = false;
                selectedSpaceUser.value = null;
                selectedSpaceRole.value = 'member';
                if (window.showSnackbar) {
                    window.showSnackbar('Space member added successfully!', 'success');
                }
            },
        }
    );
};

const changeSpaceMemberRole = (space, member, role) => {
    router.patch(
        route('spaces.members.role', [props.workspace.id, space.id]),
        {
            user_id: member.id,
            role,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Space role updated successfully!', 'success');
                }
            },
        }
    );
};

const removeSpaceMember = (space, member) => {
    if (!confirm(`Remove ${member.name} from ${space.name}?`)) {
        return;
    }

    router.delete(
        route('spaces.members.remove', [props.workspace.id, space.id]),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Space member removed successfully!', 'success');
                }
            },
        }
    );
};

const canModifyWorkspaceMember = (member) => {
    const role = member?.pivot?.role || member?.role;
    const currentUserId = page.props?.auth?.user?.id;

    return role !== 'owner' && member?.id !== currentUserId;
};

const projectRoleItems = [
    { title: 'Project Owner', value: 'project_owner' },
    { title: 'Project Manager', value: 'project_manager' },
    { title: 'Development Team', value: 'development_team' },
    { title: 'Guest', value: 'guest' },
];

const selectedProjectId = ref(props.projectLists?.[0]?.id ?? null);
const showAddProjectMember = ref(false);
const selectedProjectUser = ref(null);
const selectedProjectRole = ref('development_team');

const projectOptions = computed(() => {
    return (props.projectLists || []).map((project) => ({
        title: `${project.name} - ${project.space?.name || 'Unknown Space'}${project.is_archived ? ' (Archived)' : ''}`,
        value: project.id,
    }));
});

const selectedProject = computed(() => {
    const selectedId = Number(selectedProjectId.value);
    return (props.projectLists || []).find((project) => project.id === selectedId) || null;
});

const canManageSelectedProject = computed(() => {
    if (isAdmin.value) {
        return true;
    }

    const currentUserId = page.props?.auth?.user?.id;
    const projectMember = selectedProject.value?.members?.find((member) => member.id === currentUserId);

    return projectMember?.role === 'project_owner';
});

const availableProjectUsers = computed(() => {
    if (!selectedProject.value) {
        return [];
    }

    const assignedIds = new Set((selectedProject.value.members || []).map((member) => member.id));
    return (props.members || []).filter((member) => !assignedIds.has(member.id));
});

const getProjectRoleBadgeColor = (role) => {
    switch (role) {
        case 'project_owner': return 'error';
        case 'project_manager': return 'warning';
        case 'development_team': return 'primary';
        case 'guest': return 'grey';
        default: return 'grey';
    }
};

const getProjectRoleLabel = (role) => {
    const match = projectRoleItems.find((item) => item.value === role);
    return match?.title || role || 'Unknown';
};

const openAddProjectMemberDialog = () => {
    selectedProjectUser.value = null;
    selectedProjectRole.value = 'development_team';
    showAddProjectMember.value = true;
};

const addProjectMember = () => {
    if (!selectedProject.value || !selectedProjectUser.value) {
        return;
    }

    router.post(
        route('lists.members.add', [props.workspace.id, selectedProject.value.space.id, selectedProject.value.id]),
        {
            user_id: selectedProjectUser.value,
            role: selectedProjectRole.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showAddProjectMember.value = false;
                selectedProjectUser.value = null;
                selectedProjectRole.value = 'development_team';
                if (window.showSnackbar) {
                    window.showSnackbar('Project member added successfully!', 'success');
                }
            },
        }
    );
};

const changeProjectMemberRole = (project, member, role) => {
    router.patch(
        route('lists.members.role', [props.workspace.id, project.space.id, project.id]),
        {
            user_id: member.id,
            role,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Project role updated successfully!', 'success');
                }
            },
        }
    );
};

const removeProjectMember = (project, member) => {
    if (!confirm(`Remove ${member.name} from ${project.name}?`)) {
        return;
    }

    router.delete(
        route('lists.members.remove', [props.workspace.id, project.space.id, project.id]),
        {
            data: { user_id: member.id },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (window.showSnackbar) {
                    window.showSnackbar('Project member removed successfully!', 'success');
                }
            },
        }
    );
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
                <p class="text-gray-500 mt-1">Manage {{ workspace?.name }} access layers and configuration</p>
            </div>

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="primary">mdi-shield-account-outline</v-icon>
                    Access Layers
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6" lg="3">
                            <v-card variant="tonal" color="primary" rounded="lg" class="h-100">
                                <v-card-text>
                                    <div class="font-weight-bold mb-1">General Website</div>
                                    <div class="text-body-2">Controls global account access (login/profile/security).</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" md="6" lg="3">
                            <v-card variant="tonal" color="info" rounded="lg" class="h-100">
                                <v-card-text>
                                    <div class="font-weight-bold mb-1">Workspace Access</div>
                                    <div class="text-body-2">Owner/Admin/Member/Guest roles for workspace-wide capabilities.</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" md="6" lg="3">
                            <v-card variant="tonal" color="warning" rounded="lg" class="h-100">
                                <v-card-text>
                                    <div class="font-weight-bold mb-1">Space Access</div>
                                    <div class="text-body-2">Private/public space visibility and space-level membership control.</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="12" md="6" lg="3">
                            <v-card variant="tonal" color="success" rounded="lg" class="h-100">
                                <v-card-text>
                                    <div class="font-weight-bold mb-1">Product Access</div>
                                    <div class="text-body-2">Product-level member roles (owner/manager/dev/guest).</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="flex items-center justify-between">
                    <span>Workspace Access ({{ members?.length || 0 }})</span>
                    <div v-if="isAdmin" class="d-flex ga-2">
                        <v-btn color="secondary" variant="tonal" @click="openCreateUserDialog">
                            <v-icon start>mdi-account-plus-outline</v-icon>
                            Create User
                        </v-btn>
                        <v-btn color="primary" @click="showAddMember = true">
                            <v-icon start>mdi-account-multiple-plus</v-icon>
                            Add Member
                        </v-btn>
                    </div>
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
                                <v-menu v-if="isAdmin && canModifyWorkspaceMember(member)">
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
                                <v-chip v-else :color="getRoleBadgeColor(member.pivot?.role || member.role)" size="small">
                                    {{ (member.pivot?.role || member.role || 'member').toUpperCase() }}
                                </v-chip>
                            </td>
                            <td>
                                <div v-if="isAdmin" class="d-flex ga-1">
                                    <v-btn icon variant="text" size="small" @click="openEditUserDialog(member)">
                                        <v-icon size="18">mdi-pencil</v-icon>
                                    </v-btn>
                                    <v-btn
                                        icon
                                        variant="text"
                                        size="small"
                                        color="error"
                                        :disabled="!canModifyWorkspaceMember(member)"
                                        @click="removeMember(member)"
                                    >
                                        <v-icon size="18">mdi-delete</v-icon>
                                    </v-btn>
                                </div>
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

            <v-card variant="outlined" rounded="lg" class="mt-6">
                <v-card-title class="d-flex align-center ga-2">
                    <v-icon color="warning">mdi-sitemap-outline</v-icon>
                    Scope Settings Navigation
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-alert type="info" variant="tonal" class="mb-4">
                        Product Access and Space Access are now managed in dedicated settings pages per scope.
                    </v-alert>

                    <v-row>
                        <v-col cols="12" md="6">
                            <v-card variant="tonal" color="warning" rounded="lg" class="h-100">
                                <v-card-title class="text-subtitle-1">Space Settings</v-card-title>
                                <v-card-text>
                                    <div class="text-body-2 mb-3">Open each space to manage space-level membership roles.</div>
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-btn
                                            v-for="space in spaces"
                                            :key="`space-settings-link-${space.id}`"
                                            size="small"
                                            variant="outlined"
                                            color="warning"
                                            @click="router.visit(route('spaces.settings', [workspace.id, space.id]))"
                                        >
                                            {{ space.name }}
                                        </v-btn>
                                        <span v-if="!spaces?.length" class="text-body-2 text-grey">No spaces found.</span>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-card variant="tonal" color="success" rounded="lg" class="h-100">
                                <v-card-title class="text-subtitle-1">Product Settings</v-card-title>
                                <v-card-text>
                                    <div class="text-body-2 mb-3">Open each product to manage product member roles.</div>
                                    <div class="d-flex flex-wrap ga-2">
                                        <v-btn
                                            v-for="project in projectLists"
                                            :key="`project-settings-link-${project.id}`"
                                            size="small"
                                            variant="outlined"
                                            color="success"
                                            @click="router.visit(route('lists.settings', [workspace.id, project.space.id, project.id]))"
                                        >
                                            {{ project.name }}
                                        </v-btn>
                                        <span v-if="!projectLists?.length" class="text-body-2 text-grey">No products found.</span>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-card-text>
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

        <v-dialog v-model="showCreateUser" max-width="560">
            <v-card>
                <v-card-title>Create User</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="createUserForm.name"
                        label="Full Name"
                        variant="outlined"
                        class="mb-3"
                        autofocus
                    />
                    <v-text-field
                        v-model="createUserForm.email"
                        label="Email"
                        type="email"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model="createUserForm.password"
                        label="Password"
                        type="password"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model="createUserForm.password_confirmation"
                        label="Confirm Password"
                        type="password"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model.number="createUserForm.hourly_rate"
                        label="Hourly Rate"
                        type="number"
                        min="0"
                        step="0.01"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-select
                        v-model="createUserForm.role"
                        :items="[
                            { title: 'Admin', value: 'admin' },
                            { title: 'Member', value: 'member' },
                            { title: 'Guest', value: 'guest' },
                        ]"
                        label="Workspace Role"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showCreateUser = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        :disabled="!createUserForm.name.trim() || !createUserForm.email.trim() || !createUserForm.password"
                        @click="createUser"
                    >
                        Create User
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showEditUser" max-width="560">
            <v-card>
                <v-card-title>Edit User</v-card-title>
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        Updating account data for <strong>{{ editingUser?.name }}</strong>.
                    </v-alert>
                    <v-text-field
                        v-model="editUserForm.name"
                        label="Full Name"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model="editUserForm.email"
                        label="Email"
                        type="email"
                        variant="outlined"
                        class="mb-3"
                    />
                    <v-text-field
                        v-model.number="editUserForm.hourly_rate"
                        label="Hourly Rate"
                        type="number"
                        min="0"
                        step="0.01"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="showEditUser = false">Cancel</v-btn>
                    <v-btn
                        color="primary"
                        :disabled="!editUserForm.name.trim() || !editUserForm.email.trim()"
                        @click="updateUser"
                    >
                        Save Changes
                    </v-btn>
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
