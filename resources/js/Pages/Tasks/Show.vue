<template>
    <Head :title="task.name" />
    
    <MainLayout :workspace="workspace">
        <div class="h-full flex bg-[#1E1E1E]">
            <!-- Main Content -->
            <div class="flex-1 overflow-auto p-6">
                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                    <v-btn
                        icon="mdi-arrow-left"
                        variant="text"
                        size="small"
                        @click="goBack"
                    />
                    <span>{{ space?.name }}</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <span>{{ list?.name }}</span>
                    <v-icon size="16">mdi-chevron-right</v-icon>
                    <span class="text-white">{{ task.name }}</span>
                </div>
                
                <!-- Task Header -->
                <div class="mb-6">
                    <div class="flex items-start gap-4">
                        <!-- Status indicator -->
                        <v-btn
                            :icon="task.completed_at ? 'mdi-check-circle' : 'mdi-circle-outline'"
                            variant="text"
                            size="large"
                            :color="task.completed_at ? 'success' : 'default'"
                            @click="toggleComplete"
                        />
                        
                        <div class="flex-1">
                            <!-- Editable title -->
                            <input
                                v-model="localTask.name"
                                @blur="updateTask"
                                @keyup.enter="$event.target.blur()"
                                class="w-full bg-transparent text-2xl font-bold text-white focus:outline-none focus:bg-[#2D2D2D] px-2 py-1 rounded"
                            />
                            
                            <!-- Task ID -->
                            <div class="text-sm text-gray-500 mt-1 px-2">
                                #{{ task.id }}
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <v-btn
                                icon="mdi-dots-vertical"
                                variant="text"
                            >
                                <v-icon>mdi-dots-vertical</v-icon>
                                <v-menu activator="parent">
                                    <v-list class="bg-[#2D2D2D]" density="compact" color="surface">
                                        <v-list-item @click="duplicateTask" prepend-icon="mdi-content-copy">
                                            <v-list-item-title>Duplicate</v-list-item-title>
                                        </v-list-item>
                                        <v-list-item @click="deleteTask" prepend-icon="mdi-delete" class="text-error">
                                            <v-list-item-title>Delete</v-list-item-title>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>
                            </v-btn>
                        </div>
                    </div>
                </div>
                
                <!-- Task Details Grid -->
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <!-- Status -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Status</div>
                        <v-select
                            v-model="localTask.status_id"
                            :items="statuses"
                            item-title="name"
                            item-value="id"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                            bg-color="#3D3D3D"
                            @update:model-value="updateStatus"
                        >
                            <template #selection="{ item }">
                                <div class="flex items-center gap-2">
                                    <div 
                                        class="w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: item.raw.color }"
                                    ></div>
                                    {{ item.title }}
                                </div>
                            </template>
                            <template #item="{ item, props }">
                                <v-list-item v-bind="props">
                                    <template #prepend>
                                        <div 
                                            class="w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: item.raw.color }"
                                        ></div>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
                    </div>
                    
                    <!-- Priority -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Priority</div>
                        <v-select
                            v-model="localTask.priority_id"
                            :items="priorities"
                            item-title="name"
                            item-value="id"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                            clearable
                            bg-color="#3D3D3D"
                            @update:model-value="updatePriority"
                        >
                            <template #selection="{ item }">
                                <div class="flex items-center gap-2">
                                    <v-icon :color="item.raw.color" size="16">mdi-flag</v-icon>
                                    {{ item.title }}
                                </div>
                            </template>
                            <template #item="{ item, props }">
                                <v-list-item v-bind="props">
                                    <template #prepend>
                                        <v-icon :color="item.raw.color" size="16">mdi-flag</v-icon>
                                    </template>
                                </v-list-item>
                            </template>
                        </v-select>
                    </div>
                    
                    <!-- Assignees -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Assignees</div>
                        <div class="flex flex-wrap gap-2">
                            <v-chip
                                v-for="assignee in task.assignees"
                                :key="assignee.id"
                                closable
                                @click:close="removeAssignee(assignee)"
                                size="small"
                            >
                                <v-avatar start size="20" :image="assignee.profile_photo_url">
                                    <span v-if="!assignee.profile_photo_url">{{ assignee.name[0] }}</span>
                                </v-avatar>
                                {{ assignee.name }}
                            </v-chip>
                            
                            <v-menu>
                                <template #activator="{ props }">
                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-plus"
                                        size="x-small"
                                        variant="outlined"
                                    />
                                </template>
                                <v-list class="bg-[#2D2D2D]" density="compact" color="surface">
                                    <v-list-item
                                        v-for="member in availableMembers"
                                        :key="member.id"
                                        @click="assignMember(member)"
                                    >
                                        <template #prepend>
                                            <v-avatar size="24" :image="member.profile_photo_url">
                                                <span v-if="!member.profile_photo_url">{{ member.name[0] }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title>{{ member.name }}</v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>
                        </div>
                    </div>
                    
                    <!-- Due Date -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Due Date</div>
                        <v-text-field
                            v-model="localTask.due_date"
                            type="date"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                            bg-color="#3D3D3D"
                            @update:model-value="updateTask"
                        />
                    </div>

                    <!-- Time Estimate -->
                    <div class="bg-[#2D2D2D] rounded-lg p-4">
                        <div class="text-sm text-gray-400 mb-2">Time Estimate (Man-Hour)</div>
                        <v-text-field
                            v-model="timeEstimateHours"
                            type="number"
                            label="Hours"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                            bg-color="#3D3D3D"
                            min="0"
                            step="0.5"
                            @blur="updateTimeEstimate"
                        />
                    </div>
                </div>
                
                <!-- Description -->
                <div class="bg-[#2D2D2D] rounded-lg p-4 mb-6">
                    <div class="text-sm text-gray-400 mb-2">Description</div>
                    <v-textarea
                        v-model="localTask.description"
                        placeholder="Add a description..."
                        variant="solo-filled"
                        bg-color="#3D3D3D"
                        hide-details
                        rows="4"
                        @blur="updateTask"
                    />
                </div>
                
                <!-- Labels -->
                <div class="bg-[#2D2D2D] rounded-lg p-4 mb-6">
                    <div class="text-sm text-gray-400 mb-2">Labels</div>
                    <div class="flex flex-wrap gap-2">
                        <v-chip
                            v-for="label in task.labels"
                            :key="label.id"
                            :color="label.color"
                            closable
                            @click:close="removeLabel(label)"
                            size="small"
                        >
                            {{ label.name }}
                        </v-chip>
                        
                        <v-menu>
                            <template #activator="{ props }">
                                <v-btn
                                    v-bind="props"
                                    prepend-icon="mdi-plus"
                                    size="small"
                                    variant="outlined"
                                >
                                    Add Label
                                </v-btn>
                            </template>
                            <v-list class="bg-[#2D2D2D]" density="compact" color="surface">
                                <v-list-item
                                    v-for="label in availableLabels"
                                    :key="label.id"
                                    @click="addLabel(label)"
                                >
                                    <template #prepend>
                                        <div 
                                            class="w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: label.color }"
                                        ></div>
                                    </template>
                                    <v-list-item-title>{{ label.name }}</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </div>
                </div>
                
                <!-- Time Tracking -->
                <div class="bg-[#2D2D2D] rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-sm text-gray-400">Time Tracking</div>
                        <v-btn
                            v-if="!runningTimer"
                            prepend-icon="mdi-play"
                            size="small"
                            color="primary"
                            @click="startTimer"
                        >
                            Start Timer
                        </v-btn>
                        <v-btn
                            v-else
                            prepend-icon="mdi-stop"
                            size="small"
                            color="error"
                            @click="stopTimer"
                        >
                            Stop ({{ formatRunningTime }})
                        </v-btn>
                    </div>
                    
                    <div v-if="task.time_entries?.length > 0">
                        <div 
                            v-for="entry in task.time_entries"
                            :key="entry.id"
                            class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0"
                        >
                            <div class="flex items-center gap-2">
                                <v-avatar size="24" :image="entry.user?.profile_photo_url">
                                    <span v-if="!entry.user?.profile_photo_url">{{ entry.user?.name?.[0] }}</span>
                                </v-avatar>
                                <span class="text-sm">{{ entry.description || 'Time entry' }}</span>
                            </div>
                            <div class="text-sm text-gray-400">
                                {{ formatDuration(entry.duration) }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500">
                        No time tracked yet
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="bg-[#2D2D2D] rounded-lg p-4">
                    <div class="text-sm text-gray-400 mb-4">Comments</div>
                    
                    <!-- Add comment -->
                    <div class="flex gap-3 mb-4">
                        <v-avatar size="32" :image="$page.props.auth?.user?.profile_photo_url">
                            <span v-if="!$page.props.auth?.user?.profile_photo_url">
                                {{ $page.props.auth?.user?.name?.[0] }}
                            </span>
                        </v-avatar>
                        <div class="flex-1">
                            <v-textarea
                                v-model="newComment"
                                placeholder="Write a comment..."
                                variant="solo-filled"
                                bg-color="#3D3D3D"
                                hide-details
                                rows="2"
                            />
                            <div class="flex justify-end mt-2">
                                <v-btn
                                    color="primary"
                                    size="small"
                                    :disabled="!newComment.trim()"
                                    @click="addComment"
                                >
                                    Comment
                                </v-btn>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comments list -->
                    <div v-if="task.comments?.length > 0">
                        <div 
                            v-for="comment in task.comments"
                            :key="comment.id"
                            class="flex gap-3 py-3 border-b border-gray-700 last:border-0"
                        >
                            <v-avatar size="32" :image="comment.user?.profile_photo_url">
                                <span v-if="!comment.user?.profile_photo_url">
                                    {{ comment.user?.name?.[0] }}
                                </span>
                            </v-avatar>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-white">{{ comment.user?.name }}</span>
                                    <span class="text-xs text-gray-500">{{ formatDate(comment.created_at) }}</span>
                                    <v-chip
                                        v-if="comment.is_resolved"
                                        size="x-small"
                                        color="success"
                                    >
                                        Resolved
                                    </v-chip>
                                </div>
                                <p class="text-gray-300 mt-1">{{ comment.content }}</p>
                                
                                <!-- Comment Actions -->
                                <div class="flex items-center gap-2 mt-2">
                                    <!-- Reactions -->
                                    <div class="flex gap-1">
                                        <v-btn
                                            v-for="emoji in ['👍', '❤️', '😄', '🎉', '👀']"
                                            :key="emoji"
                                            size="x-small"
                                            variant="text"
                                            :color="getReactionCount(comment, emoji) > 0 ? 'primary' : undefined"
                                            @click="toggleReaction(comment, emoji)"
                                        >
                                            {{ emoji }} <span v-if="getReactionCount(comment, emoji) > 0" class="ml-1">{{ getReactionCount(comment, emoji) }}</span>
                                        </v-btn>
                                    </div>
                                    
                                    <!-- Resolve Button -->
                                    <v-btn
                                        v-if="!comment.is_resolved"
                                        size="x-small"
                                        variant="text"
                                        color="success"
                                        prepend-icon="mdi-check"
                                        @click="router.post(route('comments.resolve', comment.id), {}, { 
                                            preserveScroll: true,
                                            onSuccess: () => router.reload({ only: ['task'] })
                                        })"
                                    >
                                        Resolve
                                    </v-btn>
                                    <v-btn
                                        v-else
                                        size="x-small"
                                        variant="text"
                                        prepend-icon="mdi-undo"
                                        @click="router.post(route('comments.unresolve', comment.id), {}, { 
                                            preserveScroll: true,
                                            onSuccess: () => router.reload({ only: ['task'] })
                                        })"
                                    >
                                        Unresolve
                                    </v-btn>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 text-center py-4">
                        No comments yet. Be the first to comment!
                    </div>
                </div>
            </div>
            
            <!-- Subtasks Panel (Right sidebar) -->
            <div class="w-80 border-l border-gray-800 bg-[#252526] p-4 overflow-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Subtasks</h3>
                    <v-btn
                        icon="mdi-plus"
                        size="x-small"
                        variant="outlined"
                        @click="showAddSubtask = true"
                    />
                </div>
                
                <!-- Add subtask input -->
                <div v-if="showAddSubtask" class="mb-4">
                    <v-text-field
                        v-model="newSubtaskName"
                        placeholder="Subtask name"
                        variant="solo-filled"
                        density="compact"
                        hide-details
                        bg-color="#3D3D3D"
                        @keyup.enter="addSubtask"
                        autofocus
                    />
                    <div class="flex gap-2 mt-2">
                        <v-btn size="small" color="primary" @click="addSubtask">Add</v-btn>
                        <v-btn size="small" variant="text" @click="showAddSubtask = false">Cancel</v-btn>
                    </div>
                </div>
                
                <!-- Subtasks list -->
                <div v-if="task.subtasks?.length > 0">
                    <div 
                        v-for="subtask in task.subtasks"
                        :key="subtask.id"
                        class="flex items-center gap-2 py-2 px-2 hover:bg-[#3D3D3D] rounded cursor-pointer"
                    >
                        <v-checkbox
                            :model-value="!!subtask.completed_at"
                            hide-details
                            density="compact"
                            @update:model-value="toggleSubtask(subtask)"
                        />
                        <span :class="{ 'line-through text-gray-500': subtask.completed_at }">
                            {{ subtask.name }}
                        </span>
                    </div>
                </div>
                <div v-else class="text-sm text-gray-500 text-center py-4">
                    No subtasks yet
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    workspace: Object,
    space: Object,
    list: Object,
    task: Object,
    statuses: Array,
});

const page = usePage();

// Local task state
const localTask = ref({
    name: props.task.name,
    description: props.task.description || '',
    status_id: props.task.status_id,
    priority_id: props.task.priority_id,
    due_date: props.task.due_date,
});

// Time estimate (man-hour only)
const timeEstimateHours = ref((props.task.time_estimate || 0) / 60);

const updateTimeEstimate = () => {
    const totalMinutes = (parseFloat(timeEstimateHours.value) || 0) * 60;
    
    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { time_estimate: totalMinutes },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['task'] });
                if (window.showSnackbar) {
                    window.showSnackbar('Time estimate updated!', 'success');
                }
            }
        }
    );
};

// Comment
const newComment = ref('');

// Subtask
const showAddSubtask = ref(false);
const newSubtaskName = ref('');

// Running timer
const runningTimer = ref(null);
const timerInterval = ref(null);
const elapsedSeconds = ref(0);

// Computed
const priorities = computed(() => props.workspace?.priorities || []);
const labels = computed(() => props.workspace?.labels || []);
const members = computed(() => props.workspace?.members || []);

const availableMembers = computed(() => {
    const assignedIds = (props.task.assignees || []).map(a => a.id);
    return members.value.filter(m => !assignedIds.includes(m.id));
});

const availableLabels = computed(() => {
    const labelIds = (props.task.labels || []).map(l => l.id);
    return labels.value.filter(l => !labelIds.includes(l.id));
});

const formatRunningTime = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Methods
const goBack = () => {
    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]));
};

const updateTask = () => {
    router.patch(
        route('tasks.update', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            name: localTask.value.name,
            description: localTask.value.description,
            due_date: localTask.value.due_date,
        },
        { preserveScroll: true }
    );
};

const updateStatus = (statusId) => {
    router.patch(
        route('tasks.change-status', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { status_id: statusId },
        { preserveScroll: true }
    );
};

const updatePriority = (priorityId) => {
    router.patch(
        route('tasks.change-priority', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { priority_id: priorityId },
        { preserveScroll: true }
    );
};

const toggleComplete = () => {
    if (props.task.completed_at) {
        router.post(
            route('tasks.reopen', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {},
            { preserveScroll: true }
        );
    } else {
        router.post(
            route('tasks.complete', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            {},
            { preserveScroll: true }
        );
    }
};

const duplicateTask = () => {
    router.post(
        route('tasks.duplicate', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {},
        { preserveScroll: true }
    );
};

const deleteTask = () => {
    if (confirm('Are you sure you want to delete this task?')) {
        router.delete(
            route('tasks.destroy', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
            { 
                onSuccess: () => {
                    router.visit(route('lists.show', [props.workspace.id, props.space.id, props.list.id]));
                }
            }
        );
    }
};

const assignMember = (member) => {
    router.post(
        route('tasks.assign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { user_id: member.id },
        { 
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] })
        }
    );
};

const removeAssignee = (assignee) => {
    router.delete(
        route('tasks.unassign', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            data: { user_id: assignee.id },
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] })
        }
    );
};

const addLabel = (label) => {
    router.post(
        route('tasks.labels.add', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { label_id: label.id },
        { 
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] })
        }
    );
};

const removeLabel = (label) => {
    router.delete(
        route('tasks.labels.remove', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {
            data: { label_id: label.id },
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] })
        }
    );
};

const addComment = () => {
    if (!newComment.value.trim()) return;
    
    router.post(
        route('tasks.comments.store', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        { content: newComment.value },
        { 
            preserveScroll: true,
            onSuccess: () => {
                newComment.value = '';
                router.reload({ only: ['task'] });
            }
        }
    );
};

const toggleReaction = (comment, emoji) => {
    router.post(
        route('comments.react', comment.id),
        { emoji },
        { 
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['task'] })
        }
    );
};

const getReactionCount = (comment, emoji) => {
    return comment.reactions?.filter(r => r.emoji === emoji).length || 0;
};

const addSubtask = () => {
    if (!newSubtaskName.value.trim()) return;
    
    router.post(
        route('tasks.store', [props.workspace.id, props.space.id, props.list.id]),
        { 
            name: newSubtaskName.value,
            parent_id: props.task.id,
            status_id: props.statuses[0]?.id,
        },
        { 
            preserveScroll: true,
            onSuccess: () => {
                newSubtaskName.value = '';
                showAddSubtask.value = false;
                router.reload({ only: ['task'] });
            }
        }
    );
};

const toggleSubtask = (subtask) => {
    if (subtask.completed_at) {
        router.post(
            route('tasks.reopen', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {},
            { 
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['task'] })
            }
        );
    } else {
        router.post(
            route('tasks.complete', [props.workspace.id, props.space.id, props.list.id, subtask.id]),
            {},
            { 
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['task'] })
            }
        );
    }
};

const startTimer = () => {
    router.post(
        route('tasks.timer.start', [props.workspace.id, props.space.id, props.list.id, props.task.id]),
        {},
        { 
            preserveScroll: true,
            onSuccess: (page) => {
                runningTimer.value = true;
                startTimerInterval();
            }
        }
    );
};

const stopTimer = () => {
    // Find the running entry
    const runningEntry = props.task.time_entries?.find(e => e.is_running);
    if (runningEntry) {
        router.post(
            route('tasks.timer.stop', [props.workspace.id, props.space.id, props.list.id, props.task.id, runningEntry.id]),
            {},
            { 
                preserveScroll: true,
                onSuccess: () => {
                    runningTimer.value = null;
                    stopTimerInterval();
                }
            }
        );
    }
};

const startTimerInterval = () => {
    timerInterval.value = setInterval(() => {
        elapsedSeconds.value++;
    }, 1000);
};

const stopTimerInterval = () => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
        timerInterval.value = null;
        elapsedSeconds.value = 0;
    }
};

const formatDuration = (seconds) => {
    if (!seconds) return '0m';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Check for running timer on mount
onMounted(() => {
    const runningEntry = props.task.time_entries?.find(e => e.is_running);
    if (runningEntry) {
        runningTimer.value = runningEntry;
        // Calculate elapsed time
        const startTime = new Date(runningEntry.started_at).getTime();
        elapsedSeconds.value = Math.floor((Date.now() - startTime) / 1000);
        startTimerInterval();
    }
});

onUnmounted(() => {
    stopTimerInterval();
});
</script>
