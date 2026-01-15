<script setup>
/**
 * Dashboard Page - Nested Kanban Board
 * 
 * Structure:
 * Workspace > Board > List > Feature Card > Task List > Task Card
 * 
 * Features:
 * - Navigation bar with search and profile
 * - Sidebar with workspace/boards navigation
 * - Kanban board with draggable lists and feature cards
 * - Feature cards open to reveal nested task kanban
 */
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useDisplay } from 'vuetify';
import draggable from 'vuedraggable';

// Vuetify display composable for responsive
const { mobile, smAndDown, mdAndDown } = useDisplay();

// Sidebar state - closed by default on mobile
const isSidebarOpen = ref(!mobile.value);

// Dummy workspaces data
const workspaces = ref([
    {
        id: 1,
        name: 'PT ABC Development',
        boards: [
            { id: 1, name: 'ERP Integration Project', color: '#6366F1', starred: true },
            { id: 2, name: 'Mobile App v2.0', color: '#10B981', starred: false },
            { id: 3, name: 'Website Redesign', color: '#F59E0B', starred: true },
        ]
    },
    {
        id: 2,
        name: 'Internal Team',
        boards: [
            { id: 4, name: 'Q1 Planning', color: '#EF4444', starred: false },
            { id: 5, name: 'Infrastructure', color: '#8B5CF6', starred: false },
        ]
    }
]);

// Team members
const teamMembers = ref([
    { id: 1, name: 'John Doe', role: 'Project Manager', avatar: 'JD', color: '#6366F1' },
    { id: 2, name: 'Jane Smith', role: 'System Analyst', avatar: 'JS', color: '#EC4899' },
    { id: 3, name: 'Bob Wilson', role: 'API Developer', avatar: 'BW', color: '#10B981' },
    { id: 4, name: 'Alice Brown', role: 'Software Engineer', avatar: 'AB', color: '#F59E0B' },
    { id: 5, name: 'Charlie Davis', role: 'QA Engineer', avatar: 'CD', color: '#0EA5E9' },
]);

// Current active board
const activeBoard = ref({
    id: 1,
    name: 'ERP Integration Project',
    color: '#6366F1',
    background: '#0F172A',
    members: [1, 2, 3, 4, 5]
});

// Feature Lists (main kanban columns)
const featureLists = ref([
    {
        id: 1,
        title: 'Backlog',
        features: [
            {
                id: 1,
                title: 'User Management Module',
                description: 'Complete user management with roles and permissions',
                labels: [{ id: 1, name: 'Backend', color: '#6366F1' }],
                priority: 'medium',
                assignees: [1, 2],
                dueDate: '2026-02-15',
                taskLists: [
                    {
                        id: 1, title: 'To Do', tasks: [
                            { id: 1, title: 'Design database schema', assignee: 2, status: 'todo' },
                            { id: 2, title: 'Create API endpoints', assignee: 3, status: 'todo' },
                        ]
                    },
                    { id: 2, title: 'In Progress', tasks: [] },
                    { id: 3, title: 'Done', tasks: [] }
                ],
                progress: 0
            },
            {
                id: 2,
                title: 'Report Generator',
                description: 'Generate PDF and Excel reports',
                labels: [{ id: 2, name: 'Frontend', color: '#10B981' }],
                priority: 'low',
                assignees: [4],
                dueDate: '2026-03-01',
                taskLists: [
                    {
                        id: 1, title: 'To Do', tasks: [
                            { id: 3, title: 'Design report templates', assignee: 4, status: 'todo' },
                        ]
                    },
                    { id: 2, title: 'In Progress', tasks: [] },
                    { id: 3, title: 'Done', tasks: [] }
                ],
                progress: 0
            }
        ]
    },
    {
        id: 2,
        title: 'In Development',
        features: [
            {
                id: 3,
                title: 'SAP Integration',
                description: 'Integrate with SAP ERP system for data synchronization',
                labels: [
                    { id: 1, name: 'Backend', color: '#6366F1' },
                    { id: 3, name: 'Integration', color: '#F59E0B' },
                    { id: 4, name: 'High Priority', color: '#EF4444' }
                ],
                priority: 'high',
                assignees: [2, 3, 4],
                dueDate: '2026-01-25',
                taskLists: [
                    {
                        id: 1,
                        title: 'To Do',
                        tasks: [
                            { id: 4, title: 'Setup SAP sandbox environment', assignee: 2, status: 'todo', dueDate: '2026-01-18', estimatedHours: 8, actualHours: 0, timeEntries: [] },
                        ]
                    },
                    {
                        id: 2,
                        title: 'In Progress',
                        tasks: [
                            {
                                id: 5, title: 'Create API service layer', assignee: 3, status: 'in-progress', dueDate: '2026-01-20', estimatedHours: 16, actualHours: 6, timeEntries: [
                                    { id: 1, userId: 3, hours: 4, date: '2026-01-14', description: 'Initial API structure setup' },
                                    { id: 2, userId: 3, hours: 2, date: '2026-01-15', description: 'Implemented base endpoints' }
                                ]
                            },
                            {
                                id: 6, title: 'Configure SAP RFC connections', assignee: 2, status: 'in-progress', dueDate: '2026-01-19', estimatedHours: 12, actualHours: 4, timeEntries: [
                                    { id: 3, userId: 2, hours: 4, date: '2026-01-14', description: 'RFC connection configuration' }
                                ]
                            },
                        ]
                    },
                    {
                        id: 3,
                        title: 'Review',
                        tasks: [
                            {
                                id: 7, title: 'Document API specifications', assignee: 3, status: 'review', dueDate: '2026-01-17', estimatedHours: 6, actualHours: 5, timeEntries: [
                                    { id: 4, userId: 3, hours: 3, date: '2026-01-13', description: 'Drafted API documentation' },
                                    { id: 5, userId: 3, hours: 2, date: '2026-01-14', description: 'Added examples and diagrams' }
                                ]
                            },
                        ]
                    },
                    {
                        id: 4,
                        title: 'Done',
                        tasks: [
                            {
                                id: 8, title: 'Initial feasibility analysis', assignee: 2, status: 'done', dueDate: '2026-01-10', completed: true, estimatedHours: 8, actualHours: 7, timeEntries: [
                                    { id: 6, userId: 2, hours: 4, date: '2026-01-09', description: 'Research and analysis' },
                                    { id: 7, userId: 2, hours: 3, date: '2026-01-10', description: 'Compiled findings report' }
                                ]
                            },
                            {
                                id: 9, title: 'Setup project structure', assignee: 4, status: 'done', dueDate: '2026-01-12', completed: true, estimatedHours: 4, actualHours: 3, timeEntries: [
                                    { id: 8, userId: 4, hours: 3, date: '2026-01-11', description: 'Project scaffolding complete' }
                                ]
                            },
                        ]
                    }
                ],
                progress: 33
            },
            {
                id: 4,
                title: 'Authentication System',
                description: 'OAuth 2.0 with Microsoft Teams SSO',
                labels: [
                    { id: 1, name: 'Backend', color: '#6366F1' },
                    { id: 5, name: 'Security', color: '#8B5CF6' }
                ],
                priority: 'high',
                assignees: [3, 4],
                dueDate: '2026-01-22',
                taskLists: [
                    { id: 1, title: 'To Do', tasks: [] },
                    {
                        id: 2, title: 'In Progress', tasks: [
                            { id: 10, title: 'Implement OAuth flow', assignee: 3, status: 'in-progress' },
                            { id: 11, title: 'Create auth middleware', assignee: 4, status: 'in-progress' },
                        ]
                    },
                    {
                        id: 3, title: 'Done', tasks: [
                            { id: 12, title: 'Research OAuth providers', assignee: 3, status: 'done', completed: true },
                        ]
                    }
                ],
                progress: 33
            }
        ]
    },
    {
        id: 3,
        title: 'Testing',
        features: [
            {
                id: 5,
                title: 'Email Notification Service',
                description: 'Automated email notifications for various events',
                labels: [{ id: 1, name: 'Backend', color: '#6366F1' }],
                priority: 'medium',
                assignees: [3, 5],
                dueDate: '2026-01-20',
                taskLists: [
                    { id: 1, title: 'To Do', tasks: [] },
                    {
                        id: 2, title: 'In Progress', tasks: [
                            { id: 13, title: 'Write unit tests', assignee: 5, status: 'in-progress' },
                        ]
                    },
                    {
                        id: 3, title: 'Done', tasks: [
                            { id: 14, title: 'Create email templates', assignee: 4, status: 'done', completed: true },
                            { id: 15, title: 'Setup SMTP configuration', assignee: 3, status: 'done', completed: true },
                            { id: 16, title: 'Implement queue system', assignee: 3, status: 'done', completed: true },
                        ]
                    }
                ],
                progress: 75
            }
        ]
    },
    {
        id: 4,
        title: 'Completed',
        features: [
            {
                id: 6,
                title: 'Database Setup',
                description: 'Initial database design and setup',
                labels: [{ id: 1, name: 'Backend', color: '#6366F1' }],
                priority: 'high',
                assignees: [2, 3],
                dueDate: '2026-01-05',
                completedDate: '2026-01-04',
                taskLists: [
                    { id: 1, title: 'To Do', tasks: [] },
                    { id: 2, title: 'In Progress', tasks: [] },
                    {
                        id: 3, title: 'Done', tasks: [
                            { id: 17, title: 'Design ERD', assignee: 2, status: 'done', completed: true },
                            { id: 18, title: 'Create migrations', assignee: 3, status: 'done', completed: true },
                            { id: 19, title: 'Setup seeders', assignee: 3, status: 'done', completed: true },
                        ]
                    }
                ],
                progress: 100
            }
        ]
    }
]);

// Available labels
const availableLabels = ref([
    { id: 1, name: 'Backend', color: '#6366F1' },
    { id: 2, name: 'Frontend', color: '#10B981' },
    { id: 3, name: 'Integration', color: '#F59E0B' },
    { id: 4, name: 'High Priority', color: '#EF4444' },
    { id: 5, name: 'Security', color: '#8B5CF6' },
    { id: 6, name: 'Bug', color: '#DC2626' },
]);

// Activity Log - Tracks all activities across the project
const activityLog = ref([
    { id: 1, type: 'time_logged', userId: 3, taskId: 5, taskTitle: 'Create API service layer', featureTitle: 'SAP Integration', hours: 4, description: 'Initial API structure setup', timestamp: '2026-01-14T09:30:00' },
    { id: 2, type: 'time_logged', userId: 2, taskId: 6, taskTitle: 'Configure SAP RFC connections', featureTitle: 'SAP Integration', hours: 4, description: 'RFC connection configuration', timestamp: '2026-01-14T10:00:00' },
    { id: 3, type: 'task_completed', userId: 2, taskId: 8, taskTitle: 'Initial feasibility analysis', featureTitle: 'SAP Integration', timestamp: '2026-01-10T16:00:00' },
    { id: 4, type: 'task_moved', userId: 3, taskId: 7, taskTitle: 'Document API specifications', featureTitle: 'SAP Integration', fromList: 'In Progress', toList: 'Review', timestamp: '2026-01-14T15:00:00' },
    { id: 5, type: 'time_logged', userId: 3, taskId: 5, taskTitle: 'Create API service layer', featureTitle: 'SAP Integration', hours: 2, description: 'Implemented base endpoints', timestamp: '2026-01-15T11:00:00' },
    { id: 6, type: 'task_assigned', userId: 1, taskId: 4, taskTitle: 'Setup SAP sandbox environment', featureTitle: 'SAP Integration', assignedTo: 2, timestamp: '2026-01-13T09:00:00' },
    { id: 7, type: 'estimation_updated', userId: 1, taskId: 5, taskTitle: 'Create API service layer', featureTitle: 'SAP Integration', oldEstimate: 12, newEstimate: 16, timestamp: '2026-01-14T08:00:00' },
]);

// Priority options
const priorityOptions = [
    { title: 'High', value: 'high', color: 'error' },
    { title: 'Medium', value: 'medium', color: 'warning' },
    { title: 'Low', value: 'low', color: 'success' },
];

// Modal states
const isFeatureModalOpen = ref(false);
const selectedFeature = ref(null);
const selectedFeatureList = ref(null);

// Time Tracking Modal
const isTimeTrackingModalOpen = ref(false);
const selectedTaskForTime = ref(null);
const timeEntryForm = ref({ hours: '', description: '', date: new Date().toISOString().split('T')[0] });

// Activity Log Modal
const isActivityLogModalOpen = ref(false);
const activityFilterUser = ref(null);

// Workspace Modal
const isWorkspaceModalOpen = ref(false);
const editingWorkspace = ref(null);
const workspaceForm = ref({ name: '' });


// Board Modal
const isBoardModalOpen = ref(false);
const editingBoard = ref(null);
const boardForm = ref({ name: '', color: '#6366F1' });
const boardColors = [
    '#6366F1', '#8B5CF6', '#EC4899', '#EF4444',
    '#F59E0B', '#10B981', '#0EA5E9', '#6B7280'
];

// Board Members Modal
const isBoardMembersModalOpen = ref(false);

const toggleBoardMember = (memberId) => {
    const index = activeBoard.value.members.indexOf(memberId);
    if (index > -1) {
        activeBoard.value.members.splice(index, 1);
    } else {
        activeBoard.value.members.push(memberId);
    }
};

// Member Modal (for feature/task assignment)
const isMemberModalOpen = ref(false);
const memberModalTarget = ref(null); // 'feature' or 'task'
const selectedTaskForMember = ref(null);

// Label Modal
const isLabelModalOpen = ref(false);

// Due Date Modal
const isDueDateModalOpen = ref(false);
const dueDateTarget = ref(null); // 'feature' or 'task'
const tempDueDate = ref('');

// Delete Confirmation
const isDeleteDialogOpen = ref(false);
const deleteTarget = ref({ type: '', item: null, parent: null });

// Snackbar for notifications
const snackbar = ref({ show: false, text: '', color: 'success' });

const showNotification = (text, color = 'success') => {
    snackbar.value = { show: true, text, color };
};

// Search
const searchQuery = ref('');
const isMobileSearchOpen = ref(false);

// Filters
const isFilterMenuOpen = ref(false);
const activeFilters = ref({
    labels: [],
    members: [],
    priority: null,
    dueDate: null // 'overdue', 'due-soon', 'no-date'
});

const hasActiveFilters = computed(() => {
    return activeFilters.value.labels.length > 0 ||
        activeFilters.value.members.length > 0 ||
        activeFilters.value.priority !== null ||
        activeFilters.value.dueDate !== null;
});

const clearFilters = () => {
    activeFilters.value = {
        labels: [],
        members: [],
        priority: null,
        dueDate: null
    };
    showNotification('Filters cleared');
};

const featureMatchesFilter = (feature) => {
    // Check search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        const matchesSearch = feature.title.toLowerCase().includes(query) ||
            feature.description?.toLowerCase().includes(query) ||
            feature.labels.some(l => l.name.toLowerCase().includes(query)) ||
            feature.taskLists.some(tl => tl.tasks.some(t => t.title.toLowerCase().includes(query)));
        if (!matchesSearch) return false;
    }

    // Check label filter
    if (activeFilters.value.labels.length > 0) {
        if (!feature.labels.some(l => activeFilters.value.labels.includes(l.id))) {
            return false;
        }
    }

    // Check member filter
    if (activeFilters.value.members.length > 0) {
        if (!feature.assignees.some(a => activeFilters.value.members.includes(a))) {
            return false;
        }
    }

    // Check priority filter
    if (activeFilters.value.priority !== null) {
        if (feature.priority !== activeFilters.value.priority) {
            return false;
        }
    }

    // Check due date filter
    if (activeFilters.value.dueDate !== null) {
        if (activeFilters.value.dueDate === 'overdue' && !isOverdue(feature.dueDate)) return false;
        if (activeFilters.value.dueDate === 'due-soon' && !isDueSoon(feature.dueDate)) return false;
        if (activeFilters.value.dueDate === 'no-date' && feature.dueDate) return false;
    }

    return true;
};

const getVisibleFeaturesCount = (list) => {
    if (!hasActiveFilters.value && !searchQuery.value.trim()) {
        return list.features.length;
    }
    return list.features.filter(f => featureMatchesFilter(f)).length;
};

const toggleFilterLabel = (labelId) => {
    const index = activeFilters.value.labels.indexOf(labelId);
    if (index > -1) {
        activeFilters.value.labels.splice(index, 1);
    } else {
        activeFilters.value.labels.push(labelId);
    }
};

const toggleFilterMember = (memberId) => {
    const index = activeFilters.value.members.indexOf(memberId);
    if (index > -1) {
        activeFilters.value.members.splice(index, 1);
    } else {
        activeFilters.value.members.push(memberId);
    }
};

// Adding new items
const isAddingList = ref(false);
const newListTitle = ref('');
const addingFeatureToList = ref(null);
const newFeatureTitle = ref('');
const addingTaskToList = ref(null);
const newTaskTitle = ref('');

// Methods
const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const getMember = (memberId) => {
    return teamMembers.value.find(m => m.id === memberId);
};

const getBoardMembers = () => {
    return activeBoard.value.members.map(id => getMember(id)).filter(Boolean);
};

const formatDueDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const isOverdue = (dueDate) => {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
};

const isDueSoon = (dueDate) => {
    if (!dueDate) return false;
    const due = new Date(dueDate);
    const now = new Date();
    const diff = due - now;
    return diff > 0 && diff < 3 * 24 * 60 * 60 * 1000; // 3 days
};

const getPriorityColor = (priority) => {
    const colors = {
        high: 'error',
        medium: 'warning',
        low: 'success'
    };
    return colors[priority] || 'grey';
};

const getProgressColor = (progress) => {
    if (progress >= 100) return 'success';
    if (progress >= 50) return 'primary';
    if (progress >= 25) return 'warning';
    return 'grey';
};

const getTotalTasks = (feature) => {
    return feature.taskLists.reduce((sum, list) => sum + list.tasks.length, 0);
};

const getCompletedTasks = (feature) => {
    return feature.taskLists.reduce((sum, list) =>
        sum + list.tasks.filter(t => t.completed).length, 0);
};

// Feature Modal
const openFeatureModal = (feature, list) => {
    selectedFeature.value = JSON.parse(JSON.stringify(feature)); // Deep clone
    selectedFeatureList.value = list;
    isFeatureModalOpen.value = true;
};

const closeFeatureModal = () => {
    // Sync changes back to main list before closing
    if (selectedFeature.value && selectedFeatureList.value) {
        syncFeatureToList();
    }
    isFeatureModalOpen.value = false;
    selectedFeature.value = null;
    selectedFeatureList.value = null;
    addingTaskToList.value = null;
    newTaskTitle.value = '';
    isAddingTaskList.value = false;
    newTaskListTitle.value = '';
};

// Add Feature
const startAddingFeature = (listId) => {
    addingFeatureToList.value = listId;
    newFeatureTitle.value = '';
};

const cancelAddingFeature = () => {
    addingFeatureToList.value = null;
    newFeatureTitle.value = '';
};

const addFeature = (listId) => {
    if (!newFeatureTitle.value.trim()) return;

    const list = featureLists.value.find(l => l.id === listId);
    if (list) {
        const newId = Math.max(...featureLists.value.flatMap(l => l.features.map(f => f.id)), 0) + 1;
        list.features.push({
            id: newId,
            title: newFeatureTitle.value.trim(),
            description: '',
            labels: [],
            priority: 'medium',
            assignees: [],
            dueDate: null,
            taskLists: [
                { id: 1, title: 'To Do', tasks: [] },
                { id: 2, title: 'In Progress', tasks: [] },
                { id: 3, title: 'Done', tasks: [] }
            ],
            progress: 0
        });
    }
    cancelAddingFeature();
};

// Add List
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

    const newId = Math.max(...featureLists.value.map(l => l.id)) + 1;
    featureLists.value.push({
        id: newId,
        title: newListTitle.value.trim(),
        features: []
    });
    cancelAddingList();
};

// Add Task (inside feature modal)
const startAddingTask = (taskListId) => {
    addingTaskToList.value = taskListId;
    newTaskTitle.value = '';
};

const cancelAddingTask = () => {
    addingTaskToList.value = null;
    newTaskTitle.value = '';
};

const addTask = (taskListId) => {
    if (!newTaskTitle.value.trim() || !selectedFeature.value) return;

    const taskList = selectedFeature.value.taskLists.find(l => l.id === taskListId);
    if (taskList) {
        const allTasks = selectedFeature.value.taskLists.flatMap(l => l.tasks);
        const newId = Math.max(...allTasks.map(t => t.id), 0) + 1;
        const newTask = {
            id: newId,
            title: newTaskTitle.value.trim(),
            assignee: null,
            status: 'todo',
            completed: false,
            dueDate: null,
            estimatedHours: 0,
            actualHours: 0,
            timeEntries: []
        };
        taskList.tasks.push(newTask);
        updateFeatureProgress();

        // Log activity
        addActivityLog('task_created', 1, newId, newTaskTitle.value.trim(), selectedFeature.value.title);
    }
    cancelAddingTask();
};

// Add Task List (inside feature modal)
const isAddingTaskList = ref(false);
const newTaskListTitle = ref('');

const startAddingTaskList = () => {
    isAddingTaskList.value = true;
    newTaskListTitle.value = '';
};

const cancelAddingTaskList = () => {
    isAddingTaskList.value = false;
    newTaskListTitle.value = '';
};

const addTaskList = () => {
    if (!newTaskListTitle.value.trim() || !selectedFeature.value) return;

    const newId = Math.max(...selectedFeature.value.taskLists.map(l => l.id), 0) + 1;
    selectedFeature.value.taskLists.push({
        id: newId,
        title: newTaskListTitle.value.trim(),
        tasks: []
    });
    syncFeatureToList();
    cancelAddingTaskList();
    showNotification('Task list added');
};

const deleteTaskList = (taskList) => {
    if (!selectedFeature.value) return;
    const index = selectedFeature.value.taskLists.findIndex(l => l.id === taskList.id);
    if (index > -1) {
        selectedFeature.value.taskLists.splice(index, 1);
        syncFeatureToList();
        showNotification('Task list deleted');
    }
};

const toggleTaskComplete = (task) => {
    task.completed = !task.completed;
    updateFeatureProgress();

    // Log activity when task is completed
    if (task.completed && selectedFeature.value) {
        addActivityLog('task_completed', 1, task.id, task.title, selectedFeature.value.title);
    }
};

const updateFeatureProgress = () => {
    if (!selectedFeature.value) return;
    const total = getTotalTasks(selectedFeature.value);
    const completed = getCompletedTasks(selectedFeature.value);
    selectedFeature.value.progress = total > 0 ? Math.round((completed / total) * 100) : 0;

    // Update in main list
    const list = featureLists.value.find(l => l.id === selectedFeatureList.value?.id);
    if (list) {
        const feature = list.features.find(f => f.id === selectedFeature.value.id);
        if (feature) {
            feature.progress = selectedFeature.value.progress;
            feature.taskLists = JSON.parse(JSON.stringify(selectedFeature.value.taskLists));
        }
    }
};

// Starred boards computed
const starredBoards = computed(() => {
    return workspaces.value.flatMap(w => w.boards.filter(b => b.starred));
});

// Filtered features based on search and filters
const filteredFeatureLists = computed(() => {
    let lists = featureLists.value;

    // Apply search query
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        lists = lists.map(list => ({
            ...list,
            features: list.features.filter(f =>
                f.title.toLowerCase().includes(query) ||
                f.description?.toLowerCase().includes(query) ||
                f.labels.some(l => l.name.toLowerCase().includes(query)) ||
                f.taskLists.some(tl => tl.tasks.some(t => t.title.toLowerCase().includes(query)))
            )
        }));
    }

    // Apply filters
    if (hasActiveFilters.value) {
        lists = lists.map(list => ({
            ...list,
            features: list.features.filter(f => {
                // Label filter
                if (activeFilters.value.labels.length > 0) {
                    if (!f.labels.some(l => activeFilters.value.labels.includes(l.id))) {
                        return false;
                    }
                }
                // Member filter
                if (activeFilters.value.members.length > 0) {
                    if (!f.assignees.some(a => activeFilters.value.members.includes(a))) {
                        return false;
                    }
                }
                // Priority filter
                if (activeFilters.value.priority !== null) {
                    if (f.priority !== activeFilters.value.priority) {
                        return false;
                    }
                }
                // Due date filter
                if (activeFilters.value.dueDate !== null) {
                    if (activeFilters.value.dueDate === 'overdue' && !isOverdue(f.dueDate)) {
                        return false;
                    }
                    if (activeFilters.value.dueDate === 'due-soon' && !isDueSoon(f.dueDate)) {
                        return false;
                    }
                    if (activeFilters.value.dueDate === 'no-date' && f.dueDate) {
                        return false;
                    }
                }
                return true;
            })
        }));
    }

    return lists;
});

// ==================== WORKSPACE MANAGEMENT ====================
const openCreateWorkspace = () => {
    editingWorkspace.value = null;
    workspaceForm.value = { name: '' };
    isWorkspaceModalOpen.value = true;
};

const openEditWorkspace = (workspace) => {
    editingWorkspace.value = workspace;
    workspaceForm.value = { name: workspace.name };
    isWorkspaceModalOpen.value = true;
};

const saveWorkspace = () => {
    if (!workspaceForm.value.name.trim()) return;

    if (editingWorkspace.value) {
        // Edit existing
        editingWorkspace.value.name = workspaceForm.value.name.trim();
        showNotification('Workspace updated successfully');
    } else {
        // Create new
        const newId = Math.max(...workspaces.value.map(w => w.id), 0) + 1;
        workspaces.value.push({
            id: newId,
            name: workspaceForm.value.name.trim(),
            boards: []
        });
        showNotification('Workspace created successfully');
    }
    isWorkspaceModalOpen.value = false;
};

const confirmDeleteWorkspace = (workspace) => {
    deleteTarget.value = { type: 'workspace', item: workspace, parent: null };
    isDeleteDialogOpen.value = true;
};

// ==================== BOARD MANAGEMENT ====================
const openCreateBoard = (workspace) => {
    editingBoard.value = null;
    boardForm.value = { name: '', color: '#6366F1', workspaceId: workspace.id };
    isBoardModalOpen.value = true;
};

// Quick create board (from sidebar, user selects workspace)
const showQuickCreateBoard = () => {
    editingBoard.value = null;
    // Default to first workspace if available
    const defaultWorkspaceId = workspaces.value.length > 0 ? workspaces.value[0].id : null;
    boardForm.value = { name: '', color: '#6366F1', workspaceId: defaultWorkspaceId };
    isBoardModalOpen.value = true;
};

const openEditBoard = (board, workspace) => {
    editingBoard.value = board;
    boardForm.value = { name: board.name, color: board.color, workspaceId: workspace.id };
    isBoardModalOpen.value = true;
};

const saveBoard = () => {
    if (!boardForm.value.name.trim()) return;

    if (editingBoard.value) {
        // Edit existing
        editingBoard.value.name = boardForm.value.name.trim();
        editingBoard.value.color = boardForm.value.color;
        showNotification('Board updated successfully');
    } else {
        // Create new
        const workspace = workspaces.value.find(w => w.id === boardForm.value.workspaceId);
        if (workspace) {
            const allBoards = workspaces.value.flatMap(w => w.boards);
            const newId = Math.max(...allBoards.map(b => b.id), 0) + 1;
            workspace.boards.push({
                id: newId,
                name: boardForm.value.name.trim(),
                color: boardForm.value.color,
                starred: false
            });
            showNotification('Board created successfully');
        }
    }
    isBoardModalOpen.value = false;
};

const toggleBoardStar = (board) => {
    board.starred = !board.starred;
    showNotification(board.starred ? 'Board starred' : 'Board unstarred');
};

const selectBoard = (board) => {
    activeBoard.value = {
        ...activeBoard.value,
        id: board.id,
        name: board.name,
        color: board.color
    };
    // In real app, this would load the board's features from API
    showNotification(`Switched to ${board.name}`);
};

const confirmDeleteBoard = (board, workspace) => {
    deleteTarget.value = { type: 'board', item: board, parent: workspace };
    isDeleteDialogOpen.value = true;
};

// ==================== LIST MANAGEMENT ====================
const editingListId = ref(null);
const editingListTitle = ref('');

const startEditingList = (list) => {
    editingListId.value = list.id;
    editingListTitle.value = list.title;
};

const saveListTitle = (list) => {
    if (editingListTitle.value.trim()) {
        list.title = editingListTitle.value.trim();
    }
    editingListId.value = null;
};

const confirmDeleteList = (list) => {
    deleteTarget.value = { type: 'list', item: list, parent: null };
    isDeleteDialogOpen.value = true;
};

// ==================== FEATURE MANAGEMENT ====================
const confirmDeleteFeature = (feature, list) => {
    deleteTarget.value = { type: 'feature', item: feature, parent: list };
    isDeleteDialogOpen.value = true;
};

const duplicateFeature = (feature, list) => {
    const newId = Math.max(...featureLists.value.flatMap(l => l.features.map(f => f.id)), 0) + 1;
    const duplicated = JSON.parse(JSON.stringify(feature));
    duplicated.id = newId;
    duplicated.title = `${feature.title} (Copy)`;
    list.features.push(duplicated);
    showNotification('Feature duplicated');
};

const archiveFeature = (feature, list) => {
    const index = list.features.findIndex(f => f.id === feature.id);
    if (index > -1) {
        list.features.splice(index, 1);
        showNotification('Feature archived');
    }
    closeFeatureModal();
};

// ==================== MEMBER ASSIGNMENT ====================
const openMemberModal = (target, task = null) => {
    memberModalTarget.value = target;
    selectedTaskForMember.value = task;
    isMemberModalOpen.value = true;
};

const toggleMemberAssignment = (memberId) => {
    if (memberModalTarget.value === 'feature' && selectedFeature.value) {
        const index = selectedFeature.value.assignees.indexOf(memberId);
        if (index > -1) {
            selectedFeature.value.assignees.splice(index, 1);
        } else {
            selectedFeature.value.assignees.push(memberId);
        }
        // Sync back to main list
        syncFeatureToList();
    } else if (memberModalTarget.value === 'task' && selectedTaskForMember.value) {
        selectedTaskForMember.value.assignee =
            selectedTaskForMember.value.assignee === memberId ? null : memberId;
        updateFeatureProgress();
    }
};

const syncFeatureToList = () => {
    if (!selectedFeature.value || !selectedFeatureList.value) return;
    const list = featureLists.value.find(l => l.id === selectedFeatureList.value.id);
    if (list) {
        const feature = list.features.find(f => f.id === selectedFeature.value.id);
        if (feature) {
            Object.assign(feature, JSON.parse(JSON.stringify(selectedFeature.value)));
        }
    }
};

// ==================== LABEL MANAGEMENT ====================
const toggleLabel = (label) => {
    if (!selectedFeature.value) return;

    const index = selectedFeature.value.labels.findIndex(l => l.id === label.id);
    if (index > -1) {
        selectedFeature.value.labels.splice(index, 1);
    } else {
        selectedFeature.value.labels.push({ ...label });
    }
    syncFeatureToList();
};

const hasLabel = (labelId) => {
    return selectedFeature.value?.labels.some(l => l.id === labelId) || false;
};

// Create new label
const isCreatingLabel = ref(false);
const newLabelName = ref('');
const newLabelColor = ref('#6366F1');
const labelColors = ['#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F59E0B', '#10B981', '#0EA5E9', '#6B7280'];

const createLabel = () => {
    if (!newLabelName.value.trim()) return;

    const newId = Math.max(...availableLabels.value.map(l => l.id), 0) + 1;
    const newLabel = {
        id: newId,
        name: newLabelName.value.trim(),
        color: newLabelColor.value
    };
    availableLabels.value.push(newLabel);

    // Also add to current feature
    if (selectedFeature.value) {
        selectedFeature.value.labels.push({ ...newLabel });
        syncFeatureToList();
    }

    // Reset form
    isCreatingLabel.value = false;
    newLabelName.value = '';
    newLabelColor.value = '#6366F1';
    showNotification('Label created');
};

// ==================== DUE DATE MANAGEMENT ====================
const openDueDateModal = (target) => {
    dueDateTarget.value = target;
    if (target === 'feature' && selectedFeature.value) {
        tempDueDate.value = selectedFeature.value.dueDate || '';
    }
    isDueDateModalOpen.value = true;
};

const saveDueDate = () => {
    if (dueDateTarget.value === 'feature' && selectedFeature.value) {
        selectedFeature.value.dueDate = tempDueDate.value || null;
        syncFeatureToList();
    }
    isDueDateModalOpen.value = false;
    showNotification('Due date updated');
};

const removeDueDate = () => {
    if (dueDateTarget.value === 'feature' && selectedFeature.value) {
        selectedFeature.value.dueDate = null;
        syncFeatureToList();
    }
    isDueDateModalOpen.value = false;
    showNotification('Due date removed');
};

// ==================== PRIORITY MANAGEMENT ====================
const setFeaturePriority = (priority) => {
    if (!selectedFeature.value) return;
    selectedFeature.value.priority = priority;
    syncFeatureToList();
    showNotification(`Priority set to ${priority}`);
};

// ==================== DELETE CONFIRMATION ====================
const executeDelete = () => {
    const { type, item, parent } = deleteTarget.value;

    switch (type) {
        case 'workspace':
            const wsIndex = workspaces.value.findIndex(w => w.id === item.id);
            if (wsIndex > -1) workspaces.value.splice(wsIndex, 1);
            break;
        case 'board':
            const bIndex = parent.boards.findIndex(b => b.id === item.id);
            if (bIndex > -1) parent.boards.splice(bIndex, 1);
            break;
        case 'list':
            const lIndex = featureLists.value.findIndex(l => l.id === item.id);
            if (lIndex > -1) featureLists.value.splice(lIndex, 1);
            break;
        case 'feature':
            const fIndex = parent.features.findIndex(f => f.id === item.id);
            if (fIndex > -1) parent.features.splice(fIndex, 1);
            closeFeatureModal();
            break;
        case 'task':
            // Find and remove task from its list
            if (selectedFeature.value) {
                for (const taskList of selectedFeature.value.taskLists) {
                    const tIndex = taskList.tasks.findIndex(t => t.id === item.id);
                    if (tIndex > -1) {
                        taskList.tasks.splice(tIndex, 1);
                        updateFeatureProgress();
                        break;
                    }
                }
            }
            break;
    }

    isDeleteDialogOpen.value = false;
    showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} deleted`);
};

// ==================== TASK MANAGEMENT ====================
const confirmDeleteTask = (task) => {
    deleteTarget.value = { type: 'task', item: task, parent: null };
    isDeleteDialogOpen.value = true;
};

// ==================== TIME TRACKING (MANHOURS) ====================
const openTimeTrackingModal = (task) => {
    selectedTaskForTime.value = task;
    timeEntryForm.value = { hours: '', description: '', date: new Date().toISOString().split('T')[0] };
    isTimeTrackingModalOpen.value = true;
};

const logTimeEntry = () => {
    if (!timeEntryForm.value.hours || !selectedTaskForTime.value) return;

    const hours = parseFloat(timeEntryForm.value.hours);
    if (isNaN(hours) || hours <= 0) return;

    // Add time entry to task
    if (!selectedTaskForTime.value.timeEntries) {
        selectedTaskForTime.value.timeEntries = [];
    }

    const newEntryId = Math.max(...selectedTaskForTime.value.timeEntries.map(e => e.id), 0) + 1;
    const newEntry = {
        id: newEntryId,
        userId: 1, // Current user (in real app, get from auth)
        hours: hours,
        date: timeEntryForm.value.date,
        description: timeEntryForm.value.description || 'Time logged'
    };

    selectedTaskForTime.value.timeEntries.push(newEntry);

    // Update actual hours
    if (!selectedTaskForTime.value.actualHours) {
        selectedTaskForTime.value.actualHours = 0;
    }
    selectedTaskForTime.value.actualHours += hours;

    // Add to activity log
    addActivityLog('time_logged', {
        taskId: selectedTaskForTime.value.id,
        taskTitle: selectedTaskForTime.value.title,
        featureTitle: selectedFeature.value?.title || '',
        hours: hours,
        description: newEntry.description
    });

    // Sync changes
    updateFeatureProgress();

    isTimeTrackingModalOpen.value = false;
    showNotification(`${hours} manhour(s) logged successfully`);
};

const updateTaskEstimation = (task, newEstimate) => {
    const oldEstimate = task.estimatedHours || 0;
    task.estimatedHours = parseFloat(newEstimate) || 0;

    // Add to activity log
    addActivityLog('estimation_updated', {
        taskId: task.id,
        taskTitle: task.title,
        featureTitle: selectedFeature.value?.title || '',
        oldEstimate: oldEstimate,
        newEstimate: task.estimatedHours
    });

    updateFeatureProgress();
    showNotification('Estimation updated');
};

const getTaskTimeProgress = (task) => {
    if (!task.estimatedHours || task.estimatedHours === 0) return 0;
    return Math.min(100, Math.round((task.actualHours || 0) / task.estimatedHours * 100));
};

const getTimeProgressColor = (task) => {
    const progress = getTaskTimeProgress(task);
    if (progress >= 100) return 'error';
    if (progress >= 80) return 'warning';
    return 'primary';
};

// Get total estimated and actual hours for a feature
const getFeatureEstimatedHours = (feature) => {
    return feature.taskLists.reduce((sum, list) =>
        sum + list.tasks.reduce((s, t) => s + (t.estimatedHours || 0), 0), 0);
};

const getFeatureActualHours = (feature) => {
    return feature.taskLists.reduce((sum, list) =>
        sum + list.tasks.reduce((s, t) => s + (t.actualHours || 0), 0), 0);
};

// ==================== ACTIVITY TRACKING ====================
const addActivityLog = (type, data) => {
    const newId = Math.max(...activityLog.value.map(a => a.id), 0) + 1;
    activityLog.value.unshift({
        id: newId,
        type: type,
        userId: 1, // Current user (in real app, get from auth)
        timestamp: new Date().toISOString(),
        ...data
    });
};

const getActivityIcon = (type) => {
    const icons = {
        'time_logged': 'mdi-clock-plus-outline',
        'task_completed': 'mdi-checkbox-marked-circle-outline',
        'task_moved': 'mdi-arrow-right-bold',
        'task_assigned': 'mdi-account-plus',
        'task_created': 'mdi-plus-circle-outline',
        'estimation_updated': 'mdi-timer-edit-outline',
        'comment_added': 'mdi-comment-plus-outline'
    };
    return icons[type] || 'mdi-circle-outline';
};

const getActivityColor = (type) => {
    const colors = {
        'time_logged': 'primary',
        'task_completed': 'success',
        'task_moved': 'info',
        'task_assigned': 'warning',
        'task_created': 'secondary',
        'estimation_updated': 'purple',
        'comment_added': 'grey'
    };
    return colors[type] || 'grey';
};

const getActivityDescription = (activity) => {
    const user = getMember(activity.userId);
    const userName = user?.name || 'Unknown';

    switch (activity.type) {
        case 'time_logged':
            return `${userName} logged ${activity.hours}h on "${activity.taskTitle}"`;
        case 'task_completed':
            return `${userName} completed "${activity.taskTitle}"`;
        case 'task_moved':
            return `${userName} moved "${activity.taskTitle}" from ${activity.fromList} to ${activity.toList}`;
        case 'task_assigned':
            const assignee = getMember(activity.assignedTo);
            return `${userName} assigned "${activity.taskTitle}" to ${assignee?.name || 'Unknown'}`;
        case 'task_created':
            return `${userName} created "${activity.taskTitle}"`;
        case 'estimation_updated':
            return `${userName} updated estimation for "${activity.taskTitle}" (${activity.oldEstimate}h → ${activity.newEstimate}h)`;
        default:
            return `${userName} performed an action`;
    }
};

const formatActivityTime = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const filteredActivityLog = computed(() => {
    if (!activityFilterUser.value) return activityLog.value;
    return activityLog.value.filter(a => a.userId === activityFilterUser.value);
});

// Get activities for current user (for employee dashboard)
const getMyActivities = computed(() => {
    return activityLog.value.filter(a => a.userId === 1).slice(0, 10);
});

// Get time logged by each team member
const getTeamTimeStats = computed(() => {
    const stats = {};
    teamMembers.value.forEach(member => {
        stats[member.id] = {
            member: member,
            totalHours: 0,
            tasksCompleted: 0
        };
    });

    activityLog.value.forEach(activity => {
        if (stats[activity.userId]) {
            if (activity.type === 'time_logged') {
                stats[activity.userId].totalHours += activity.hours;
            }
            if (activity.type === 'task_completed') {
                stats[activity.userId].tasksCompleted++;
            }
        }
    });

    return Object.values(stats).sort((a, b) => b.totalHours - a.totalHours);
});
</script>

<template>

    <Head title="Dashboard - ERP Integration Project" />

    <v-app theme="darkTheme">
        <!-- Top Navigation Bar -->
        <v-app-bar color="surface" density="compact" elevation="0" border>
            <template #prepend>
                <v-btn icon variant="text" size="small" @click="toggleSidebar">
                    <v-icon color="grey-lighten-1">mdi-menu</v-icon>
                </v-btn>
            </template>

            <!-- Logo -->
            <v-btn variant="text" class="text-none font-weight-bold mx-2 text-white"
                :class="mobile ? 'text-body-1' : 'text-h6'">
                <v-icon class="mr-1" color="primary">mdi-view-dashboard-outline</v-icon>
                <span v-if="!smAndDown">Taskboard</span>
            </v-btn>

            <v-spacer />

            <!-- Search -->
            <v-text-field v-if="!smAndDown" v-model="searchQuery" density="compact" variant="solo-filled"
                bg-color="surface-variant" placeholder="Search features or tasks..." prepend-inner-icon="mdi-magnify"
                hide-details single-line rounded class="mx-4" style="max-width: 400px;" />
            <v-btn v-else icon variant="text" size="small" class="mr-1" @click="isMobileSearchOpen = true">
                <v-icon color="grey-lighten-1">mdi-magnify</v-icon>
            </v-btn>

            <v-spacer v-if="!smAndDown" />

            <!-- Right actions -->
            <v-btn icon variant="text" size="small" class="mr-1">
                <v-icon color="grey-lighten-1">mdi-bell-outline</v-icon>
            </v-btn>
            <v-btn icon variant="text" size="small" class="mr-1" @click="isActivityLogModalOpen = true">
                <v-icon color="grey-lighten-1">mdi-history</v-icon>
                <v-tooltip activator="parent" location="bottom">Activity Log</v-tooltip>
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
                <v-navigation-drawer v-model="isSidebarOpen" :temporary="mobile" :permanent="!mobile"
                    :rail="!mobile && !isSidebarOpen" color="surface" border width="260">
                    <v-list density="compact" nav class="pa-2">
                        <v-list-item prepend-icon="mdi-view-dashboard-outline" title="Boards" rounded="lg"
                            class="mb-1" />
                        <v-list-item prepend-icon="mdi-file-document-outline" title="Templates" rounded="lg"
                            class="mb-1" />
                        <v-list-item prepend-icon="mdi-home-outline" title="Home" rounded="lg" class="mb-1" />

                        <v-divider class="my-3" />

                        <!-- Starred Boards -->
                        <v-list-subheader class="text-medium-emphasis">Starred</v-list-subheader>
                        <v-list-item v-for="board in starredBoards" :key="'starred-' + board.id" rounded="lg"
                            class="mb-1" @click="selectBoard(board)">
                            <template #prepend>
                                <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
                            </template>
                            <v-list-item-title>{{ board.name }}</v-list-item-title>
                            <template #append>
                                <v-btn icon variant="text" size="x-small" @click.stop="toggleBoardStar(board)">
                                    <v-icon size="14" color="amber">mdi-star</v-icon>
                                </v-btn>
                            </template>
                        </v-list-item>
                        <v-list-item v-if="starredBoards.length === 0" class="text-medium-emphasis text-caption">
                            No starred boards
                        </v-list-item>

                        <v-divider class="my-3" />

                        <!-- Quick Actions -->
                        <v-list-subheader class="text-medium-emphasis">Quick Actions</v-list-subheader>
                        <v-list-item rounded="lg" class="mb-1" prepend-icon="mdi-folder-plus-outline"
                            @click="openCreateWorkspace">
                            <v-list-item-title>Create Workspace</v-list-item-title>
                        </v-list-item>
                        <v-list-item rounded="lg" class="mb-1" prepend-icon="mdi-plus-box-outline"
                            @click="showQuickCreateBoard">
                            <v-list-item-title>Create Board</v-list-item-title>
                        </v-list-item>
                        <v-list-item rounded="lg" class="mb-1" prepend-icon="mdi-history"
                            @click="isActivityLogModalOpen = true">
                            <v-list-item-title>Activity Log</v-list-item-title>
                        </v-list-item>

                        <v-divider class="my-3" />

                        <!-- My Time Stats -->
                        <v-list-subheader class="text-medium-emphasis">My Time (This Week)</v-list-subheader>
                        <v-sheet color="surface-variant" rounded="lg" class="pa-3 mx-2 mb-3">
                            <div class="d-flex justify-space-between mb-2">
                                <span class="text-caption text-medium-emphasis">Hours Logged</span>
                                <span class="text-body-2 font-weight-bold text-primary">
                                    {{getTeamTimeStats.find(s => s.member.id === 1)?.totalHours || 0}}h
                                </span>
                            </div>
                            <div class="d-flex justify-space-between">
                                <span class="text-caption text-medium-emphasis">Tasks Completed</span>
                                <span class="text-body-2 font-weight-bold text-success">
                                    {{getTeamTimeStats.find(s => s.member.id === 1)?.tasksCompleted || 0}}
                                </span>
                            </div>
                        </v-sheet>

                        <v-divider class="my-3" />

                        <!-- Workspaces Header with Add Button -->
                        <div class="d-flex align-center justify-space-between px-2 mb-2">
                            <span class="text-caption text-medium-emphasis font-weight-bold">WORKSPACES</span>
                            <v-tooltip text="Create Workspace" location="top">
                                <template #activator="{ props }">
                                    <v-btn v-bind="props" icon variant="text" size="x-small"
                                        @click="openCreateWorkspace">
                                        <v-icon size="18">mdi-plus</v-icon>
                                    </v-btn>
                                </template>
                            </v-tooltip>
                        </div>

                        <template v-for="workspace in workspaces" :key="workspace.id">
                            <v-list-group>
                                <template #activator="{ props }">
                                    <v-list-item v-bind="props" rounded="lg">
                                        <template #prepend>
                                            <v-avatar size="24" color="surface-variant" rounded="sm">
                                                <span class="text-caption">{{ workspace.name.charAt(0) }}</span>
                                            </v-avatar>
                                        </template>
                                        <v-list-item-title>{{ workspace.name }}</v-list-item-title>
                                        <template #append>
                                            <v-menu>
                                                <template #activator="{ props: menuProps }">
                                                    <v-btn icon variant="text" size="x-small" v-bind="menuProps"
                                                        @click.stop>
                                                        <v-icon size="16">mdi-dots-vertical</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-list density="compact" width="180">
                                                    <v-list-item prepend-icon="mdi-plus" title="Add Board"
                                                        @click="openCreateBoard(workspace)" />
                                                    <v-list-item prepend-icon="mdi-pencil" title="Edit Workspace"
                                                        @click="openEditWorkspace(workspace)" />
                                                    <v-divider class="my-1" />
                                                    <v-list-item prepend-icon="mdi-delete" title="Delete"
                                                        class="text-error" @click="confirmDeleteWorkspace(workspace)" />
                                                </v-list>
                                            </v-menu>
                                        </template>
                                    </v-list-item>
                                </template>

                                <!-- Boards in Workspace -->
                                <v-list-item v-for="board in workspace.boards" :key="board.id" rounded="lg"
                                    :class="{ 'bg-primary-darken-1': activeBoard.id === board.id }"
                                    @click="selectBoard(board)">
                                    <template #prepend>
                                        <div class="board-color-dot mr-3" :style="{ backgroundColor: board.color }" />
                                    </template>
                                    <v-list-item-title class="text-body-2">{{ board.name }}</v-list-item-title>
                                    <template #append>
                                        <v-btn icon variant="text" size="x-small" @click.stop="toggleBoardStar(board)"
                                            class="mr-1">
                                            <v-icon size="14" :color="board.starred ? 'amber' : 'grey'">
                                                {{ board.starred ? 'mdi-star' : 'mdi-star-outline' }}
                                            </v-icon>
                                        </v-btn>
                                        <v-menu>
                                            <template #activator="{ props: menuProps }">
                                                <v-btn icon variant="text" size="x-small" v-bind="menuProps"
                                                    @click.stop>
                                                    <v-icon size="14">mdi-dots-vertical</v-icon>
                                                </v-btn>
                                            </template>
                                            <v-list density="compact" width="160">
                                                <v-list-item prepend-icon="mdi-pencil" title="Edit"
                                                    @click="openEditBoard(board, workspace)" />
                                                <v-list-item prepend-icon="mdi-delete" title="Delete" class="text-error"
                                                    @click="confirmDeleteBoard(board, workspace)" />
                                            </v-list>
                                        </v-menu>
                                    </template>
                                </v-list-item>

                                <!-- Add Board Button -->
                                <v-list-item rounded="lg" class="text-medium-emphasis"
                                    @click="openCreateBoard(workspace)">
                                    <template #prepend>
                                        <v-icon size="18" class="mr-3">mdi-plus</v-icon>
                                    </template>
                                    <v-list-item-title class="text-body-2">Add Board</v-list-item-title>
                                </v-list-item>
                            </v-list-group>
                        </template>
                    </v-list>
                </v-navigation-drawer>

                <!-- Board Area -->
                <div class="flex-grow-1 d-flex flex-column board-area">
                    <!-- Board Header -->
                    <v-sheet color="surface" class="d-flex align-center flex-wrap pa-2 pa-sm-3 board-header">
                        <h1 :class="mobile ? 'text-h6' : 'text-h5'" class="font-weight-bold text-white mr-2 mr-sm-3">
                            {{ activeBoard.name }}
                        </h1>
                        <v-btn icon variant="text" size="small" class="mr-2">
                            <v-icon color="grey-lighten-1">mdi-star-outline</v-icon>
                        </v-btn>

                        <!-- Members -->
                        <div v-if="!smAndDown" class="d-flex align-center">
                            <v-divider vertical class="mx-2 border-opacity-25" style="height: 24px;" />
                            <div class="d-flex align-center mx-2">
                                <v-tooltip v-for="member in getBoardMembers().slice(0, 4)" :key="member.id"
                                    location="bottom">
                                    <template #activator="{ props }">
                                        <v-avatar v-bind="props" :size="mobile ? 28 : 32" :color="member.color"
                                            class="member-avatar cursor-pointer"
                                            @click="isBoardMembersModalOpen = true">
                                            <span class="text-caption font-weight-bold">{{ member.avatar }}</span>
                                        </v-avatar>
                                    </template>
                                    {{ member.name }}
                                </v-tooltip>
                                <v-avatar v-if="getBoardMembers().length > 4" :size="mobile ? 28 : 32"
                                    color="grey-darken-1" class="member-avatar cursor-pointer"
                                    @click="isBoardMembersModalOpen = true">
                                    <span class="text-caption">+{{ getBoardMembers().length - 4 }}</span>
                                </v-avatar>
                                <v-btn icon variant="text" size="small" class="ml-1"
                                    @click="isBoardMembersModalOpen = true">
                                    <v-icon color="grey-lighten-1" size="20">mdi-plus</v-icon>
                                </v-btn>
                            </div>
                        </div>

                        <v-spacer />

                        <!-- Board Actions -->
                        <v-menu v-model="isFilterMenuOpen" :close-on-content-click="false" location="bottom end">
                            <template #activator="{ props }">
                                <v-btn v-if="smAndDown" v-bind="props" icon variant="tonal" size="small" class="mr-1"
                                    :color="hasActiveFilters ? 'primary' : undefined">
                                    <v-badge v-if="hasActiveFilters" dot color="error" offset-x="-3" offset-y="-3">
                                        <v-icon size="18">mdi-filter-variant</v-icon>
                                    </v-badge>
                                    <v-icon v-else size="18">mdi-filter-variant</v-icon>
                                </v-btn>
                            </template>
                            <!-- Filter Menu Content (shared) -->
                            <v-card color="surface" min-width="300">
                                <v-card-title class="d-flex align-center pa-3">
                                    <v-icon start size="20">mdi-filter-variant</v-icon>
                                    Filters
                                    <v-spacer />
                                    <v-btn v-if="hasActiveFilters" variant="text" size="small" color="error"
                                        class="text-none" @click="clearFilters">
                                        Clear all
                                    </v-btn>
                                </v-card-title>
                                <v-divider />
                                <v-card-text class="pa-0">
                                    <!-- Labels Filter -->
                                    <v-list-subheader>Labels</v-list-subheader>
                                    <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                        <v-chip v-for="label in availableLabels" :key="label.id" size="small"
                                            :variant="activeFilters.labels.includes(label.id) ? 'flat' : 'outlined'"
                                            :style="{
                                                backgroundColor: activeFilters.labels.includes(label.id) ? label.color : 'transparent',
                                                borderColor: label.color
                                            }" @click="toggleFilterLabel(label.id)">
                                            {{ label.name }}
                                        </v-chip>
                                    </div>

                                    <!-- Members Filter -->
                                    <v-list-subheader>Members</v-list-subheader>
                                    <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                        <v-chip v-for="member in teamMembers" :key="member.id" size="small"
                                            :variant="activeFilters.members.includes(member.id) ? 'flat' : 'outlined'"
                                            :color="activeFilters.members.includes(member.id) ? member.color : undefined"
                                            @click="toggleFilterMember(member.id)">
                                            <v-avatar start size="18" :color="member.color">
                                                <span style="font-size: 8px;">{{ member.avatar }}</span>
                                            </v-avatar>
                                            {{ member.name.split(' ')[0] }}
                                        </v-chip>
                                    </div>

                                    <!-- Priority Filter -->
                                    <v-list-subheader>Priority</v-list-subheader>
                                    <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                        <v-chip v-for="priority in priorityOptions" :key="priority.value" size="small"
                                            :variant="activeFilters.priority === priority.value ? 'flat' : 'outlined'"
                                            :color="activeFilters.priority === priority.value ? priority.color : undefined"
                                            @click="activeFilters.priority = activeFilters.priority === priority.value ? null : priority.value">
                                            <v-icon start size="14">mdi-flag</v-icon>
                                            {{ priority.title }}
                                        </v-chip>
                                    </div>

                                    <!-- Due Date Filter -->
                                    <v-list-subheader>Due Date</v-list-subheader>
                                    <div class="px-4 pb-3 d-flex flex-wrap ga-1">
                                        <v-chip size="small"
                                            :variant="activeFilters.dueDate === 'overdue' ? 'flat' : 'outlined'"
                                            :color="activeFilters.dueDate === 'overdue' ? 'error' : undefined"
                                            @click="activeFilters.dueDate = activeFilters.dueDate === 'overdue' ? null : 'overdue'">
                                            Overdue
                                        </v-chip>
                                        <v-chip size="small"
                                            :variant="activeFilters.dueDate === 'due-soon' ? 'flat' : 'outlined'"
                                            :color="activeFilters.dueDate === 'due-soon' ? 'warning' : undefined"
                                            @click="activeFilters.dueDate = activeFilters.dueDate === 'due-soon' ? null : 'due-soon'">
                                            Due Soon
                                        </v-chip>
                                        <v-chip size="small"
                                            :variant="activeFilters.dueDate === 'no-date' ? 'flat' : 'outlined'"
                                            @click="activeFilters.dueDate = activeFilters.dueDate === 'no-date' ? null : 'no-date'">
                                            No Date
                                        </v-chip>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-menu>
                        <v-btn v-if="smAndDown" icon variant="tonal" size="small">
                            <v-icon size="18">mdi-dots-horizontal</v-icon>
                        </v-btn>
                        <template v-if="!smAndDown">
                            <v-menu :close-on-content-click="false" location="bottom end">
                                <template #activator="{ props }">
                                    <v-btn v-bind="props" variant="tonal" size="small" class="mr-2 text-none"
                                        :color="hasActiveFilters ? 'primary' : undefined">
                                        <v-icon start size="18">mdi-filter-variant</v-icon>
                                        Filters
                                        <v-badge v-if="hasActiveFilters"
                                            :content="activeFilters.labels.length + activeFilters.members.length + (activeFilters.priority ? 1 : 0) + (activeFilters.dueDate ? 1 : 0)"
                                            color="error" inline class="ml-1" />
                                    </v-btn>
                                </template>
                                <!-- Filter Menu Content -->
                                <v-card color="surface" min-width="320">
                                    <v-card-title class="d-flex align-center pa-3">
                                        <v-icon start size="20">mdi-filter-variant</v-icon>
                                        Filters
                                        <v-spacer />
                                        <v-btn v-if="hasActiveFilters" variant="text" size="small" color="error"
                                            class="text-none" @click="clearFilters">
                                            Clear all
                                        </v-btn>
                                    </v-card-title>
                                    <v-divider />
                                    <v-card-text class="pa-0">
                                        <!-- Labels Filter -->
                                        <v-list-subheader>Labels</v-list-subheader>
                                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                            <v-chip v-for="label in availableLabels" :key="label.id" size="small"
                                                :variant="activeFilters.labels.includes(label.id) ? 'flat' : 'outlined'"
                                                :style="{
                                                    backgroundColor: activeFilters.labels.includes(label.id) ? label.color : 'transparent',
                                                    borderColor: label.color
                                                }" @click="toggleFilterLabel(label.id)">
                                                {{ label.name }}
                                            </v-chip>
                                        </div>

                                        <!-- Members Filter -->
                                        <v-list-subheader>Members</v-list-subheader>
                                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                            <v-chip v-for="member in teamMembers" :key="member.id" size="small"
                                                :variant="activeFilters.members.includes(member.id) ? 'flat' : 'outlined'"
                                                :color="activeFilters.members.includes(member.id) ? member.color : undefined"
                                                @click="toggleFilterMember(member.id)">
                                                <v-avatar start size="18" :color="member.color">
                                                    <span style="font-size: 8px;">{{ member.avatar }}</span>
                                                </v-avatar>
                                                {{ member.name.split(' ')[0] }}
                                            </v-chip>
                                        </div>

                                        <!-- Priority Filter -->
                                        <v-list-subheader>Priority</v-list-subheader>
                                        <div class="px-4 pb-2 d-flex flex-wrap ga-1">
                                            <v-chip v-for="priority in priorityOptions" :key="priority.value"
                                                size="small"
                                                :variant="activeFilters.priority === priority.value ? 'flat' : 'outlined'"
                                                :color="activeFilters.priority === priority.value ? priority.color : undefined"
                                                @click="activeFilters.priority = activeFilters.priority === priority.value ? null : priority.value">
                                                <v-icon start size="14">mdi-flag</v-icon>
                                                {{ priority.title }}
                                            </v-chip>
                                        </div>

                                        <!-- Due Date Filter -->
                                        <v-list-subheader>Due Date</v-list-subheader>
                                        <div class="px-4 pb-3 d-flex flex-wrap ga-1">
                                            <v-chip size="small"
                                                :variant="activeFilters.dueDate === 'overdue' ? 'flat' : 'outlined'"
                                                :color="activeFilters.dueDate === 'overdue' ? 'error' : undefined"
                                                @click="activeFilters.dueDate = activeFilters.dueDate === 'overdue' ? null : 'overdue'">
                                                Overdue
                                            </v-chip>
                                            <v-chip size="small"
                                                :variant="activeFilters.dueDate === 'due-soon' ? 'flat' : 'outlined'"
                                                :color="activeFilters.dueDate === 'due-soon' ? 'warning' : undefined"
                                                @click="activeFilters.dueDate = activeFilters.dueDate === 'due-soon' ? null : 'due-soon'">
                                                Due Soon
                                            </v-chip>
                                            <v-chip size="small"
                                                :variant="activeFilters.dueDate === 'no-date' ? 'flat' : 'outlined'"
                                                @click="activeFilters.dueDate = activeFilters.dueDate === 'no-date' ? null : 'no-date'">
                                                No Date
                                            </v-chip>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-menu>
                            <v-btn variant="tonal" size="small" class="text-none">
                                <v-icon start size="18">mdi-dots-horizontal</v-icon>
                                Menu
                            </v-btn>
                        </template>
                    </v-sheet>

                    <!-- Kanban Board -->
                    <div class="kanban-board pa-3">
                        <div class="d-flex ga-3 lists-container">
                            <!-- Feature Lists -->
                            <draggable v-model="featureLists" group="lists" item-key="id"
                                class="d-flex ga-3 align-start" ghost-class="ghost-list" :animation="150">
                                <template #item="{ element: list }">
                                    <v-sheet color="surface" rounded="xl" class="kanban-list flex-shrink-0">
                                        <!-- List Header -->
                                        <div class="d-flex align-center justify-space-between pa-3 list-header">
                                            <!-- Editable Title -->
                                            <div v-if="editingListId === list.id" class="flex-grow-1 mr-2">
                                                <v-text-field v-model="editingListTitle" variant="outlined"
                                                    density="compact" hide-details autofocus bg-color="surface-variant"
                                                    @blur="saveListTitle(list)" @keydown.enter="saveListTitle(list)"
                                                    @keydown.esc="editingListId = null" />
                                            </div>
                                            <h3 v-else class="text-subtitle-1 font-weight-bold"
                                                @dblclick="startEditingList(list)">
                                                {{ list.title }}
                                                <v-chip size="x-small" class="ml-2" variant="tonal">
                                                    <template v-if="hasActiveFilters || searchQuery.trim()">
                                                        {{ getVisibleFeaturesCount(list) }}/{{ list.features.length }}
                                                    </template>
                                                    <template v-else>
                                                        {{ list.features.length }}
                                                    </template>
                                                </v-chip>
                                            </h3>
                                            <v-menu>
                                                <template #activator="{ props }">
                                                    <v-btn icon variant="text" size="small" v-bind="props">
                                                        <v-icon size="18">mdi-dots-horizontal</v-icon>
                                                    </v-btn>
                                                </template>
                                                <v-list density="compact" width="200">
                                                    <v-list-item title="Add feature" prepend-icon="mdi-plus"
                                                        @click="startAddingFeature(list.id)" />
                                                    <v-list-item title="Rename list" prepend-icon="mdi-pencil"
                                                        @click="startEditingList(list)" />
                                                    <v-divider class="my-1" />
                                                    <v-list-item title="Delete list" prepend-icon="mdi-delete"
                                                        class="text-error" @click="confirmDeleteList(list)" />
                                                </v-list>
                                            </v-menu>
                                        </div>

                                        <!-- Features Container -->
                                        <draggable v-model="list.features" group="features" item-key="id"
                                            class="features-container pa-1" ghost-class="ghost-card" :animation="150">
                                            <template #item="{ element: feature }">
                                                <v-card v-show="featureMatchesFilter(feature)" class="feature-card mb-2"
                                                    rounded="lg" elevation="1" color="surface-variant"
                                                    @click="openFeatureModal(feature, list)">
                                                    <v-card-text class="pa-3">
                                                        <!-- Labels -->
                                                        <div v-if="feature.labels.length"
                                                            class="d-flex flex-wrap gap-1 mb-2">
                                                            <div v-for="label in feature.labels" :key="label.id"
                                                                class="feature-label"
                                                                :style="{ backgroundColor: label.color }" />
                                                        </div>

                                                        <!-- Feature Title -->
                                                        <p class="text-body-2 font-weight-medium mb-2">
                                                            {{ feature.title }}
                                                        </p>

                                                        <!-- Progress Bar -->
                                                        <v-progress-linear v-if="getTotalTasks(feature) > 0"
                                                            :model-value="feature.progress"
                                                            :color="getProgressColor(feature.progress)" height="4"
                                                            rounded class="mb-2" />

                                                        <!-- Feature Metadata -->
                                                        <div class="d-flex align-center flex-wrap gap-2">
                                                            <!-- Priority -->
                                                            <v-icon v-if="feature.priority === 'high'" size="16"
                                                                color="error" title="High Priority">
                                                                mdi-flag
                                                            </v-icon>

                                                            <!-- Due Date -->
                                                            <v-chip v-if="feature.dueDate" size="x-small"
                                                                :color="isOverdue(feature.dueDate) ? 'error' : (isDueSoon(feature.dueDate) ? 'warning' : undefined)"
                                                                :variant="isOverdue(feature.dueDate) || isDueSoon(feature.dueDate) ? 'flat' : 'tonal'">
                                                                <v-icon start size="12">mdi-clock-outline</v-icon>
                                                                {{ formatDueDate(feature.dueDate) }}
                                                            </v-chip>

                                                            <!-- Tasks Count -->
                                                            <div v-if="getTotalTasks(feature) > 0"
                                                                class="d-flex align-center"
                                                                :class="feature.progress === 100 ? 'text-success' : 'text-medium-emphasis'">
                                                                <v-icon size="14"
                                                                    class="mr-1">mdi-checkbox-marked-outline</v-icon>
                                                                <span class="text-caption">
                                                                    {{ getCompletedTasks(feature) }}/{{
                                                                        getTotalTasks(feature) }}
                                                                </span>
                                                            </div>

                                                            <v-spacer />

                                                            <!-- Assignees -->
                                                            <div class="d-flex">
                                                                <v-avatar
                                                                    v-for="assigneeId in feature.assignees.slice(0, 3)"
                                                                    :key="assigneeId" size="24"
                                                                    :color="getMember(assigneeId)?.color"
                                                                    class="feature-assignee">
                                                                    <span class="text-caption" style="font-size: 10px;">
                                                                        {{ getMember(assigneeId)?.avatar }}
                                                                    </span>
                                                                </v-avatar>
                                                            </div>
                                                        </div>
                                                    </v-card-text>
                                                </v-card>
                                            </template>
                                        </draggable>

                                        <!-- Add Feature Form -->
                                        <div v-if="addingFeatureToList === list.id" class="pa-2">
                                            <v-textarea v-model="newFeatureTitle" placeholder="Enter feature name..."
                                                variant="outlined" density="compact" rows="2" hide-details autofocus
                                                bg-color="surface-variant" class="mb-2"
                                                @keydown.enter.prevent="addFeature(list.id)"
                                                @keydown.esc="cancelAddingFeature" />
                                            <div class="d-flex align-center ga-2">
                                                <v-btn color="primary" size="small" class="text-none"
                                                    @click="addFeature(list.id)">
                                                    Add feature
                                                </v-btn>
                                                <v-btn icon variant="text" size="small" @click="cancelAddingFeature">
                                                    <v-icon>mdi-close</v-icon>
                                                </v-btn>
                                            </div>
                                        </div>

                                        <!-- Add Feature Button -->
                                        <v-btn v-if="addingFeatureToList !== list.id" variant="text" block
                                            class="justify-start text-none text-medium-emphasis pa-2"
                                            @click="startAddingFeature(list.id)">
                                            <v-icon size="18" class="mr-1">mdi-plus</v-icon>
                                            Add a feature
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
                                        variant="outlined" density="compact" hide-details autofocus
                                        bg-color="surface-variant" class="mb-2" @keydown.enter.prevent="addList"
                                        @keydown.esc="cancelAddingList" />
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
            </div>
        </v-main>

        <!-- Feature Detail Modal with Nested Kanban -->
        <v-dialog v-model="isFeatureModalOpen" :max-width="smAndDown ? '100%' : 1000" :fullscreen="mobile" scrollable>
            <v-card v-if="selectedFeature" rounded="lg" color="surface">
                <!-- Modal Header -->
                <v-card-title class="d-flex align-start pa-4 pb-2">
                    <v-icon class="mr-3 mt-1 text-medium-emphasis" size="24">mdi-puzzle-outline</v-icon>
                    <div class="flex-grow-1">
                        <v-text-field v-model="selectedFeature.title" variant="plain" density="compact" hide-details
                            class="text-h6 font-weight-bold feature-title-input" />
                        <p class="text-body-2 text-medium-emphasis mt-1">
                            in list <span class="text-decoration-underline">{{ selectedFeatureList?.title }}</span>
                        </p>
                    </div>
                    <v-btn icon variant="text" size="small" @click="closeFeatureModal">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>

                <v-card-text class="pa-4">
                    <v-row>
                        <!-- Main Content - Task Kanban -->
                        <v-col cols="12" :md="mobile ? 12 : 9" :order="mobile ? 2 : 1">
                            <!-- Feature Info -->
                            <div class="d-flex flex-wrap align-center ga-2 mb-4">
                                <!-- Labels -->
                                <v-chip v-for="label in selectedFeature.labels" :key="label.id"
                                    :style="{ backgroundColor: label.color }" size="small" class="text-white" closable
                                    @click:close="toggleLabel(label)">
                                    {{ label.name }}
                                </v-chip>
                                <v-btn v-if="selectedFeature.labels.length === 0" size="small" variant="tonal"
                                    class="text-none" @click="isLabelModalOpen = true">
                                    <v-icon start size="16">mdi-label-outline</v-icon>
                                    Add Label
                                </v-btn>

                                <!-- Priority -->
                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-chip v-bind="props" :color="getPriorityColor(selectedFeature.priority)"
                                            size="small" variant="tonal" class="cursor-pointer">
                                            <v-icon start size="14">mdi-flag</v-icon>
                                            {{ selectedFeature.priority.charAt(0).toUpperCase() +
                                                selectedFeature.priority.slice(1) }} Priority
                                        </v-chip>
                                    </template>
                                    <v-list density="compact" bg-color="surface">
                                        <v-list-item v-for="priority in priorityOptions" :key="priority.value"
                                            :title="priority.title + ' Priority'"
                                            @click="setFeaturePriority(priority.value)">
                                            <template #prepend>
                                                <v-icon :color="priority.color" size="18">mdi-flag</v-icon>
                                            </template>
                                            <template #append>
                                                <v-icon v-if="selectedFeature.priority === priority.value"
                                                    color="primary" size="18">mdi-check</v-icon>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-menu>

                                <!-- Due Date -->
                                <v-chip v-if="selectedFeature.dueDate" size="small"
                                    :color="isOverdue(selectedFeature.dueDate) ? 'error' : (isDueSoon(selectedFeature.dueDate) ? 'warning' : undefined)"
                                    :variant="isOverdue(selectedFeature.dueDate) || isDueSoon(selectedFeature.dueDate) ? 'flat' : 'tonal'"
                                    closable @click="openDueDateModal('feature')"
                                    @click:close.stop="removeDueDate(); dueDateTarget = 'feature'">
                                    <v-icon start size="14">mdi-calendar</v-icon>
                                    {{ formatDueDate(selectedFeature.dueDate) }}
                                </v-chip>
                                <v-btn v-else size="small" variant="tonal" class="text-none"
                                    @click="openDueDateModal('feature')">
                                    <v-icon start size="16">mdi-calendar-plus</v-icon>
                                    Set Due Date
                                </v-btn>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <div class="d-flex align-center mb-2">
                                    <v-icon class="mr-2 text-medium-emphasis" size="20">mdi-text-subject</v-icon>
                                    <h4 class="text-subtitle-2 font-weight-bold">Description</h4>
                                </div>
                                <v-textarea v-model="selectedFeature.description" variant="outlined"
                                    placeholder="Add a description for this feature..." rows="2" hide-details
                                    bg-color="surface-variant" />
                            </div>

                            <!-- Progress -->
                            <div class="mb-4">
                                <div class="d-flex align-center justify-space-between mb-2">
                                    <div class="d-flex align-center">
                                        <v-icon class="mr-2 text-medium-emphasis" size="20">mdi-chart-line</v-icon>
                                        <h4 class="text-subtitle-2 font-weight-bold">Progress</h4>
                                    </div>
                                    <span class="text-body-2 text-medium-emphasis">
                                        {{ getCompletedTasks(selectedFeature) }}/{{ getTotalTasks(selectedFeature) }}
                                        tasks
                                        completed
                                    </span>
                                </div>
                                <v-progress-linear :model-value="selectedFeature.progress"
                                    :color="getProgressColor(selectedFeature.progress)" height="8" rounded />
                            </div>

                            <!-- Time & Estimation (Agile Manhours) -->
                            <div class="mb-4">
                                <div class="d-flex align-center justify-space-between mb-2">
                                    <div class="d-flex align-center">
                                        <v-icon class="mr-2 text-medium-emphasis" size="20">mdi-clock-outline</v-icon>
                                        <h4 class="text-subtitle-2 font-weight-bold">Time Tracking (Manhours)</h4>
                                    </div>
                                    <v-chip size="small"
                                        :color="getFeatureActualHours(selectedFeature) > getFeatureEstimatedHours(selectedFeature) ? 'error' : 'primary'"
                                        variant="tonal">
                                        {{ getFeatureActualHours(selectedFeature) }}h / {{
                                        getFeatureEstimatedHours(selectedFeature) }}h
                                    </v-chip>
                                </div>
                                <v-progress-linear
                                    :model-value="getFeatureEstimatedHours(selectedFeature) > 0 ? (getFeatureActualHours(selectedFeature) / getFeatureEstimatedHours(selectedFeature) * 100) : 0"
                                    :color="getFeatureActualHours(selectedFeature) > getFeatureEstimatedHours(selectedFeature) ? 'error' : 'primary'"
                                    height="8" rounded class="mb-2" />
                                <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                                    <span>Actual: {{ getFeatureActualHours(selectedFeature) }} manhours</span>
                                    <span>Estimated: {{ getFeatureEstimatedHours(selectedFeature) }} manhours</span>
                                </div>
                            </div>

                            <!-- Task Kanban Board -->
                            <div class="mb-4">
                                <div class="d-flex align-center mb-3">
                                    <v-icon class="mr-2 text-medium-emphasis" size="20">mdi-view-column</v-icon>
                                    <h4 class="text-subtitle-2 font-weight-bold">Tasks</h4>
                                </div>

                                <div class="task-kanban">
                                    <div class="d-flex ga-3 task-lists-container">
                                        <draggable v-model="selectedFeature.taskLists" group="task-lists" item-key="id"
                                            class="d-flex ga-3">
                                            <template #item="{ element: taskList }">
                                                <v-sheet color="surface-variant" rounded="lg" class="task-list">
                                                    <!-- Task List Header -->
                                                    <div class="d-flex align-center justify-space-between pa-2">
                                                        <span
                                                            class="text-caption font-weight-bold text-medium-emphasis">
                                                            {{ taskList.title }}
                                                            <v-chip size="x-small" class="ml-1" variant="text">
                                                                {{ taskList.tasks.length }}
                                                            </v-chip>
                                                        </span>
                                                        <v-menu>
                                                            <template #activator="{ props }">
                                                                <v-btn v-bind="props" icon variant="text"
                                                                    size="x-small">
                                                                    <v-icon size="14">mdi-dots-horizontal</v-icon>
                                                                </v-btn>
                                                            </template>
                                                            <v-list density="compact" bg-color="surface">
                                                                <v-list-item prepend-icon="mdi-delete"
                                                                    title="Delete list" class="text-error"
                                                                    :disabled="selectedFeature.taskLists.length <= 1"
                                                                    @click="deleteTaskList(taskList)" />
                                                            </v-list>
                                                        </v-menu>
                                                    </div>

                                                    <!-- Tasks -->
                                                    <draggable v-model="taskList.tasks" group="tasks" item-key="id"
                                                        class="task-cards-container px-2 pb-2" ghost-class="ghost-task"
                                                        @change="updateFeatureProgress">
                                                        <template #item="{ element: task }">
                                                            <v-card class="task-card mb-2" rounded="lg" elevation="0"
                                                                :color="task.completed ? 'success' : 'surface'"
                                                                :variant="task.completed ? 'tonal' : 'flat'">
                                                                <v-card-text class="pa-3">
                                                                    <!-- Task Header Row -->
                                                                    <div class="d-flex align-center mb-1">
                                                                        <v-checkbox :model-value="task.completed"
                                                                            density="comfortable" hide-details
                                                                            class="flex-grow-0 flex-shrink-0"
                                                                            style="margin-right: 12px;"
                                                                            @update:model-value="toggleTaskComplete(task)" />
                                                                        <span class="text-body-2 flex-grow-1"
                                                                            :class="{ 'text-decoration-line-through text-medium-emphasis': task.completed }">
                                                                            {{ task.title }}
                                                                        </span>
                                                                        <v-tooltip v-if="task.assignee" location="top">
                                                                            <template #activator="{ props }">
                                                                                <v-avatar v-bind="props" size="20"
                                                                                    :color="getMember(task.assignee)?.color"
                                                                                    class="cursor-pointer"
                                                                                    @click="openMemberModal('task', task)">
                                                                                    <span style="font-size: 8px;">
                                                                                        {{
                                                                                        getMember(task.assignee)?.avatar
                                                                                        }}
                                                                                    </span>
                                                                                </v-avatar>
                                                                            </template>
                                                                            {{ getMember(task.assignee)?.name }}
                                                                        </v-tooltip>
                                                                        <v-menu>
                                                                            <template #activator="{ props }">
                                                                                <v-btn v-bind="props" icon
                                                                                    variant="text" size="x-small"
                                                                                    class="ml-1">
                                                                                    <v-icon
                                                                                        size="14">mdi-dots-vertical</v-icon>
                                                                                </v-btn>
                                                                            </template>
                                                                            <v-list density="compact"
                                                                                bg-color="surface">
                                                                                <v-list-item
                                                                                    prepend-icon="mdi-clock-plus-outline"
                                                                                    title="Log Time"
                                                                                    @click="openTimeTrackingModal(task)" />
                                                                                <v-list-item
                                                                                    prepend-icon="mdi-account-plus"
                                                                                    title="Assign Member"
                                                                                    @click="openMemberModal('task', task)" />
                                                                                <v-divider class="my-1" />
                                                                                <v-list-item prepend-icon="mdi-delete"
                                                                                    title="Delete Task"
                                                                                    class="text-error"
                                                                                    @click="confirmDeleteTask(task)" />
                                                                            </v-list>
                                                                        </v-menu>
                                                                    </div>

                                                                    <!-- Time Tracking Row -->
                                                                    <div class="d-flex align-center ga-2 mt-2">
                                                                        <!-- Estimation Badge -->
                                                                        <v-tooltip location="top">
                                                                            <template #activator="{ props }">
                                                                                <v-chip v-bind="props" size="x-small"
                                                                                    variant="tonal"
                                                                                    :color="getTimeProgressColor(task)"
                                                                                    class="cursor-pointer"
                                                                                    @click="openTimeTrackingModal(task)">
                                                                                    <v-icon start
                                                                                        size="12">mdi-clock-outline</v-icon>
                                                                                    {{ task.actualHours || 0 }}h / {{
                                                                                    task.estimatedHours || 0 }}h
                                                                                </v-chip>
                                                                            </template>
                                                                            <span>
                                                                                Actual: {{ task.actualHours || 0 }}
                                                                                manhours<br>
                                                                                Estimated: {{ task.estimatedHours || 0
                                                                                }} manhours<br>
                                                                                Click to log time
                                                                            </span>
                                                                        </v-tooltip>

                                                                        <!-- Time Progress Bar -->
                                                                        <v-progress-linear v-if="task.estimatedHours"
                                                                            :model-value="getTaskTimeProgress(task)"
                                                                            :color="getTimeProgressColor(task)"
                                                                            height="4" rounded class="flex-grow-1"
                                                                            style="max-width: 60px;" />

                                                                        <!-- Log Time Button -->
                                                                        <v-btn size="x-small" variant="text"
                                                                            color="primary" class="text-none px-1"
                                                                            @click="openTimeTrackingModal(task)">
                                                                            <v-icon size="12">mdi-plus</v-icon>
                                                                            Log
                                                                        </v-btn>
                                                                    </div>
                                                                </v-card-text>
                                                            </v-card>
                                                        </template>
                                                    </draggable>

                                                    <!-- Add Task -->
                                                    <div v-if="addingTaskToList === taskList.id" class="px-2 pb-2">
                                                        <v-text-field v-model="newTaskTitle" placeholder="Task title..."
                                                            variant="outlined" density="compact" hide-details autofocus
                                                            class="mb-2" @keydown.enter.prevent="addTask(taskList.id)"
                                                            @keydown.esc="cancelAddingTask" />
                                                        <div class="d-flex ga-1">
                                                            <v-btn color="primary" size="x-small" class="text-none"
                                                                @click="addTask(taskList.id)">
                                                                Add
                                                            </v-btn>
                                                            <v-btn icon variant="text" size="x-small"
                                                                @click="cancelAddingTask">
                                                                <v-icon size="16">mdi-close</v-icon>
                                                            </v-btn>
                                                        </div>
                                                    </div>
                                                    <v-btn v-else variant="text" size="x-small" block
                                                        class="text-none text-medium-emphasis mb-2"
                                                        @click="startAddingTask(taskList.id)">
                                                        <v-icon size="14" class="mr-1">mdi-plus</v-icon>
                                                        Add task
                                                    </v-btn>
                                                </v-sheet>
                                            </template>
                                        </draggable>

                                        <!-- Add Task List -->
                                        <v-sheet v-if="isAddingTaskList" color="surface-variant" rounded="lg"
                                            class="task-list pa-2">
                                            <v-text-field v-model="newTaskListTitle" placeholder="List title..."
                                                variant="outlined" density="compact" hide-details autofocus class="mb-2"
                                                @keydown.enter.prevent="addTaskList"
                                                @keydown.esc="cancelAddingTaskList" />
                                            <div class="d-flex ga-1">
                                                <v-btn color="primary" size="x-small" class="text-none"
                                                    @click="addTaskList">
                                                    Add
                                                </v-btn>
                                                <v-btn icon variant="text" size="x-small" @click="cancelAddingTaskList">
                                                    <v-icon size="16">mdi-close</v-icon>
                                                </v-btn>
                                            </div>
                                        </v-sheet>
                                        <v-btn v-else variant="tonal" size="small"
                                            class="text-none task-list align-self-start" style="min-height: 36px;"
                                            @click="startAddingTaskList">
                                            <v-icon size="16" class="mr-1">mdi-plus</v-icon>
                                            Add List
                                        </v-btn>
                                    </div>
                                </div>
                            </div>

                            <!-- Assignees -->
                            <div class="mb-4">
                                <div class="d-flex align-center mb-2">
                                    <v-icon class="mr-2 text-medium-emphasis" size="20">mdi-account-group</v-icon>
                                    <h4 class="text-subtitle-2 font-weight-bold">Assignees</h4>
                                </div>
                                <div class="d-flex flex-wrap ga-2">
                                    <v-chip v-for="assigneeId in selectedFeature.assignees" :key="assigneeId"
                                        :color="getMember(assigneeId)?.color" variant="tonal" size="small" closable
                                        @click:close="toggleMemberAssignment(assigneeId); memberModalTarget = 'feature'">
                                        <v-avatar start size="20" :color="getMember(assigneeId)?.color">
                                            <span style="font-size: 10px;">{{ getMember(assigneeId)?.avatar }}</span>
                                        </v-avatar>
                                        {{ getMember(assigneeId)?.name }}
                                        <span class="text-medium-emphasis ml-1">• {{ getMember(assigneeId)?.role
                                        }}</span>
                                    </v-chip>
                                    <v-btn size="small" variant="tonal" class="text-none"
                                        @click="openMemberModal('feature')">
                                        <v-icon size="16">mdi-plus</v-icon>
                                    </v-btn>
                                </div>
                            </div>
                        </v-col>

                        <!-- Sidebar Actions -->
                        <v-col cols="12" md="3" :order="mobile ? 1 : 2">
                            <div v-if="mobile" class="d-flex flex-wrap ga-2 mb-4">
                                <v-btn variant="tonal" size="small" class="text-none"
                                    @click="openMemberModal('feature')">
                                    <v-icon start size="18">mdi-account-plus</v-icon>
                                    Assign
                                </v-btn>
                                <v-btn variant="tonal" size="small" class="text-none" @click="isLabelModalOpen = true">
                                    <v-icon start size="18">mdi-label</v-icon>
                                    Labels
                                </v-btn>
                                <v-btn variant="tonal" size="small" class="text-none"
                                    @click="openDueDateModal('feature')">
                                    <v-icon start size="18">mdi-calendar</v-icon>
                                    Due Date
                                </v-btn>
                            </div>
                            <template v-else>
                                <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2">Add to feature</h4>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                    @click="openMemberModal('feature')">
                                    <v-icon start size="18">mdi-account-plus</v-icon>
                                    Assign Member
                                </v-btn>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                    @click="isLabelModalOpen = true">
                                    <v-icon start size="18">mdi-label</v-icon>
                                    Labels
                                </v-btn>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                    @click="openDueDateModal('feature')">
                                    <v-icon start size="18">mdi-calendar</v-icon>
                                    Due Date
                                </v-btn>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none">
                                    <v-icon start size="18">mdi-paperclip</v-icon>
                                    Attachments
                                </v-btn>

                                <h4 class="text-caption font-weight-bold text-medium-emphasis mb-2 mt-4">Actions</h4>
                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                            v-bind="props">
                                            <v-icon start size="18">mdi-arrow-right</v-icon>
                                            Move
                                        </v-btn>
                                    </template>
                                    <v-list density="compact" bg-color="surface">
                                        <v-list-subheader>Move to list</v-list-subheader>
                                        <v-list-item v-for="list in featureLists" :key="list.id" :title="list.title"
                                            :disabled="list.id === selectedFeatureList?.id" @click="() => {
                                                if (selectedFeature && selectedFeatureList && list.id !== selectedFeatureList.id) {
                                                    const idx = selectedFeatureList.features.findIndex(f => f.id === selectedFeature.id);
                                                    if (idx > -1) {
                                                        selectedFeatureList.features.splice(idx, 1);
                                                        list.features.push(selectedFeature);
                                                        selectedFeatureList = list;
                                                        showNotification('Feature moved to ' + list.title);
                                                    }
                                                }
                                            }" />
                                    </v-list>
                                </v-menu>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                    @click="duplicateFeature(selectedFeature, selectedFeatureList); closeFeatureModal()">
                                    <v-icon start size="18">mdi-content-copy</v-icon>
                                    Duplicate
                                </v-btn>
                                <v-btn block variant="tonal" size="small" class="mb-2 justify-start text-none"
                                    @click="archiveFeature(selectedFeature, selectedFeatureList)">
                                    <v-icon start size="18">mdi-archive</v-icon>
                                    Archive
                                </v-btn>
                                <v-btn block variant="tonal" size="small" class="justify-start text-none" color="error"
                                    @click="confirmDeleteFeature(selectedFeature, selectedFeatureList)">
                                    <v-icon start size="18">mdi-delete</v-icon>
                                    Delete
                                </v-btn>
                            </template>
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>
        </v-dialog>

        <!-- Workspace Modal -->
        <v-dialog v-model="isWorkspaceModalOpen" max-width="450" persistent>
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-folder-multiple-outline</v-icon>
                    {{ editingWorkspace ? 'Edit Workspace' : 'Create Workspace' }}
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isWorkspaceModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <v-text-field v-model="workspaceForm.name" label="Workspace Name"
                        placeholder="e.g., Development Team" variant="outlined" density="comfortable"
                        bg-color="surface-variant" autofocus @keyup.enter="saveWorkspace" />
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="isWorkspaceModalOpen = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" :disabled="!workspaceForm.name.trim()" @click="saveWorkspace">
                        {{ editingWorkspace ? 'Save Changes' : 'Create Workspace' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Board Modal -->
        <v-dialog v-model="isBoardModalOpen" max-width="450" persistent>
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-view-dashboard-outline</v-icon>
                    {{ editingBoard ? 'Edit Board' : 'Create Board' }}
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isBoardModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <!-- Workspace Selector (only when creating new board) -->
                    <v-select v-if="!editingBoard" v-model="boardForm.workspaceId" :items="workspaces" item-title="name"
                        item-value="id" label="Workspace" variant="outlined" density="comfortable"
                        bg-color="surface-variant" class="mb-4" :rules="[v => !!v || 'Please select a workspace']">
                        <template #prepend-inner>
                            <v-icon size="20">mdi-folder-outline</v-icon>
                        </template>
                    </v-select>

                    <v-text-field v-model="boardForm.name" label="Board Name"
                        placeholder="e.g., ERP Integration Project" variant="outlined" density="comfortable"
                        bg-color="surface-variant" class="mb-4" autofocus />
                    <div class="text-body-2 text-medium-emphasis mb-2">Board Color</div>
                    <div class="d-flex flex-wrap ga-2">
                        <v-btn v-for="color in boardColors" :key="color" icon size="40"
                            :style="{ backgroundColor: color }" :class="{ 'elevation-8': boardForm.color === color }"
                            @click="boardForm.color = color">
                            <v-icon v-if="boardForm.color === color" size="20" color="white">mdi-check</v-icon>
                        </v-btn>
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="isBoardModalOpen = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat"
                        :disabled="!boardForm.name.trim() || (!editingBoard && !boardForm.workspaceId)"
                        @click="saveBoard">
                        {{ editingBoard ? 'Save Changes' : 'Create Board' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Board Members Modal -->
        <v-dialog v-model="isBoardMembersModalOpen" max-width="400">
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-account-multiple-plus</v-icon>
                    Board Members
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isBoardMembersModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-0">
                    <p class="text-caption text-medium-emphasis pa-4 pb-2">
                        Select team members to add to this board
                    </p>
                    <v-list density="comfortable" bg-color="surface">
                        <v-list-item v-for="member in teamMembers" :key="member.id" :title="member.name"
                            :subtitle="member.role" @click="toggleBoardMember(member.id)">
                            <template #prepend>
                                <v-avatar size="36" :color="member.color">
                                    <span class="text-caption font-weight-bold">{{ member.avatar }}</span>
                                </v-avatar>
                            </template>
                            <template #append>
                                <v-checkbox-btn :model-value="activeBoard.members.includes(member.id)" color="primary"
                                    @click.stop="toggleBoardMember(member.id)" />
                            </template>
                        </v-list-item>
                    </v-list>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <span class="text-body-2 text-medium-emphasis">
                        {{ activeBoard.members.length }} members selected
                    </span>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" @click="isBoardMembersModalOpen = false">Done</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Member Selection Modal -->
        <v-dialog v-model="isMemberModalOpen" max-width="400">
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-account-group</v-icon>
                    {{ memberModalTarget === 'feature' ? 'Assign Members' : 'Assign to Task' }}
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isMemberModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-list density="comfortable" bg-color="surface">
                    <v-list-item v-for="member in teamMembers" :key="member.id" :title="member.name"
                        :subtitle="member.role" @click="toggleMemberAssignment(member.id)">
                        <template #prepend>
                            <v-avatar size="36" :color="member.color">
                                <span class="text-caption font-weight-bold">{{ member.avatar }}</span>
                            </v-avatar>
                        </template>
                        <template #append>
                            <v-icon v-if="(memberModalTarget === 'feature' && selectedFeature?.assignees.includes(member.id)) ||
                                (memberModalTarget === 'task' && selectedTaskForMember?.assignee === member.id)"
                                color="primary">
                                mdi-check-circle
                            </v-icon>
                        </template>
                    </v-list-item>
                </v-list>
            </v-card>
        </v-dialog>

        <!-- Label Selection Modal -->
        <v-dialog v-model="isLabelModalOpen" max-width="350">
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-label-multiple</v-icon>
                    Labels
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isLabelModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <v-list density="compact" bg-color="surface">
                        <v-list-item v-for="label in availableLabels" :key="label.id" class="rounded mb-2"
                            :style="{ backgroundColor: label.color + '30' }" @click="toggleLabel(label)">
                            <template #prepend>
                                <div class="rounded mr-3"
                                    :style="{ width: '16px', height: '16px', backgroundColor: label.color }" />
                            </template>
                            <v-list-item-title>{{ label.name }}</v-list-item-title>
                            <template #append>
                                <v-icon v-if="hasLabel(label.id)" color="white" size="18">mdi-check</v-icon>
                            </template>
                        </v-list-item>
                    </v-list>

                    <!-- Create New Label Form -->
                    <v-expand-transition>
                        <div v-if="isCreatingLabel" class="mt-3">
                            <v-divider class="mb-3" />
                            <v-text-field v-model="newLabelName" placeholder="Label name..." variant="outlined"
                                density="compact" hide-details autofocus class="mb-2" @keydown.enter="createLabel" />
                            <p class="text-caption text-medium-emphasis mb-2">Select color</p>
                            <div class="d-flex flex-wrap ga-1 mb-3">
                                <v-btn v-for="color in labelColors" :key="color" icon size="28"
                                    :style="{ backgroundColor: color }" @click="newLabelColor = color">
                                    <v-icon v-if="newLabelColor === color" size="16" color="white">mdi-check</v-icon>
                                </v-btn>
                            </div>
                            <div class="d-flex ga-2">
                                <v-btn color="primary" size="small" class="text-none" :disabled="!newLabelName.trim()"
                                    @click="createLabel">
                                    Create
                                </v-btn>
                                <v-btn variant="text" size="small" class="text-none"
                                    @click="isCreatingLabel = false; newLabelName = ''">
                                    Cancel
                                </v-btn>
                            </div>
                        </div>
                    </v-expand-transition>

                    <v-btn v-if="!isCreatingLabel" variant="tonal" size="small" block class="text-none mt-2"
                        @click="isCreatingLabel = true">
                        <v-icon start size="16">mdi-plus</v-icon>
                        Create new label
                    </v-btn>
                </v-card-text>
            </v-card>
        </v-dialog>

        <!-- Due Date Modal -->
        <v-dialog v-model="isDueDateModalOpen" max-width="350">
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start>mdi-calendar</v-icon>
                    Set Due Date
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isDueDateModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <v-text-field v-model="tempDueDate" type="date" label="Due Date" variant="outlined"
                        density="comfortable" bg-color="surface-variant" />
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-btn variant="text" color="error" @click="removeDueDate">Remove</v-btn>
                    <v-spacer />
                    <v-btn variant="text" @click="isDueDateModalOpen = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveDueDate">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="isDeleteDialogOpen" max-width="400">
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4 text-error">
                    <v-icon start color="error">mdi-alert-circle</v-icon>
                    Confirm Delete
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <p class="text-body-1">
                        Are you sure you want to delete this <strong>{{ deleteTarget.type }}</strong>?
                    </p>
                    <p class="text-body-2 text-medium-emphasis mt-2">
                        <template v-if="deleteTarget.type === 'workspace'">
                            This will also delete all boards within this workspace.
                        </template>
                        <template v-else-if="deleteTarget.type === 'board'">
                            This will remove all lists and features in this board.
                        </template>
                        <template v-else-if="deleteTarget.type === 'list'">
                            This will remove all features in this list.
                        </template>
                        <template v-else-if="deleteTarget.type === 'feature'">
                            This will remove all tasks within this feature.
                        </template>
                        <template v-else>
                            This action cannot be undone.
                        </template>
                    </p>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="isDeleteDialogOpen = false">Cancel</v-btn>
                    <v-btn color="error" variant="flat" @click="executeDelete">Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Mobile Search Dialog -->
        <v-dialog v-model="isMobileSearchOpen" fullscreen transition="dialog-bottom-transition">
            <v-card color="background">
                <v-toolbar color="surface">
                    <v-btn icon @click="isMobileSearchOpen = false">
                        <v-icon>mdi-arrow-left</v-icon>
                    </v-btn>
                    <v-text-field v-model="searchQuery" placeholder="Search features or tasks..." variant="plain"
                        density="compact" hide-details autofocus class="mx-2" />
                    <v-btn icon @click="searchQuery = ''">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-toolbar>
                <v-card-text class="pa-4">
                    <template v-if="searchQuery.trim()">
                        <p class="text-caption text-medium-emphasis mb-2">Search Results</p>
                        <template v-for="list in filteredFeatureLists" :key="list.id">
                            <v-card v-for="feature in list.features" :key="feature.id" class="mb-2" color="surface"
                                @click="openFeatureModal(feature, list); isMobileSearchOpen = false">
                                <v-card-text class="pa-3">
                                    <div class="d-flex align-center">
                                        <v-icon size="20" class="mr-2 text-medium-emphasis">mdi-card-outline</v-icon>
                                        <div>
                                            <p class="text-body-2 font-weight-medium">{{ feature.title }}</p>
                                            <p class="text-caption text-medium-emphasis">in {{ list.title }}</p>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </template>
                        <p v-if="filteredFeatureLists.every(l => l.features.length === 0)"
                            class="text-body-2 text-medium-emphasis text-center py-8">
                            No results found for "{{ searchQuery }}"
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-body-2 text-medium-emphasis text-center py-8">
                            Type to search for features or tasks...
                        </p>
                    </template>
                </v-card-text>
            </v-card>
        </v-dialog>

        <!-- Time Tracking Modal -->
        <v-dialog v-model="isTimeTrackingModalOpen" max-width="450" persistent>
            <v-card v-if="selectedTaskForTime" color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start color="primary">mdi-clock-plus-outline</v-icon>
                    Log Time (Manhours)
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isTimeTrackingModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <!-- Task Info -->
                    <v-sheet color="surface-variant" rounded="lg" class="pa-3 mb-4">
                        <p class="text-caption text-medium-emphasis">Task</p>
                        <p class="text-body-1 font-weight-medium">{{ selectedTaskForTime.title }}</p>
                        <div class="d-flex align-center mt-2 ga-3">
                            <div>
                                <p class="text-caption text-medium-emphasis">Estimated</p>
                                <p class="text-body-2 font-weight-bold">{{ selectedTaskForTime.estimatedHours || 0 }}h
                                </p>
                            </div>
                            <div>
                                <p class="text-caption text-medium-emphasis">Logged</p>
                                <p class="text-body-2 font-weight-bold"
                                    :class="selectedTaskForTime.actualHours > selectedTaskForTime.estimatedHours ? 'text-error' : 'text-primary'">
                                    {{ selectedTaskForTime.actualHours || 0 }}h
                                </p>
                            </div>
                            <div>
                                <p class="text-caption text-medium-emphasis">Remaining</p>
                                <p class="text-body-2 font-weight-bold">
                                    {{ Math.max(0, (selectedTaskForTime.estimatedHours || 0) -
                                        (selectedTaskForTime.actualHours
                                    || 0)) }}h
                                </p>
                            </div>
                        </div>
                    </v-sheet>

                    <!-- Update Estimation -->
                    <v-text-field :model-value="selectedTaskForTime.estimatedHours" label="Estimation (manhours)"
                        type="number" min="0" step="0.5" variant="outlined" density="comfortable"
                        bg-color="surface-variant" class="mb-4" prepend-inner-icon="mdi-timer-outline"
                        @update:model-value="val => selectedTaskForTime.estimatedHours = parseFloat(val) || 0" />

                    <!-- Log New Time -->
                    <p class="text-subtitle-2 font-weight-bold mb-2">Log Time Entry</p>
                    <v-row dense>
                        <v-col cols="6">
                            <v-text-field v-model="timeEntryForm.hours" label="Hours" type="number" min="0.25"
                                step="0.25" variant="outlined" density="comfortable" bg-color="surface-variant"
                                prepend-inner-icon="mdi-clock-outline" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field v-model="timeEntryForm.date" label="Date" type="date" variant="outlined"
                                density="comfortable" bg-color="surface-variant" />
                        </v-col>
                    </v-row>
                    <v-textarea v-model="timeEntryForm.description" label="What did you work on?" variant="outlined"
                        density="comfortable" bg-color="surface-variant" rows="2"
                        placeholder="e.g., Implemented API endpoints, Fixed bugs..." />

                    <!-- Time Entries History -->
                    <div v-if="selectedTaskForTime.timeEntries?.length" class="mt-4">
                        <p class="text-subtitle-2 font-weight-bold mb-2">Time Log History</p>
                        <v-list density="compact" bg-color="transparent" class="pa-0">
                            <v-list-item v-for="entry in selectedTaskForTime.timeEntries.slice().reverse()"
                                :key="entry.id" class="px-0">
                                <template #prepend>
                                    <v-avatar size="28" :color="getMember(entry.userId)?.color">
                                        <span style="font-size: 10px;">{{ getMember(entry.userId)?.avatar }}</span>
                                    </v-avatar>
                                </template>
                                <v-list-item-title class="text-body-2">
                                    {{ entry.description }}
                                </v-list-item-title>
                                <v-list-item-subtitle>
                                    {{ getMember(entry.userId)?.name }} • {{ entry.date }}
                                </v-list-item-subtitle>
                                <template #append>
                                    <v-chip size="small" color="primary" variant="tonal">
                                        {{ entry.hours }}h
                                    </v-chip>
                                </template>
                            </v-list-item>
                        </v-list>
                    </div>
                </v-card-text>
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn variant="text" @click="isTimeTrackingModalOpen = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat"
                        :disabled="!timeEntryForm.hours || parseFloat(timeEntryForm.hours) <= 0" @click="logTimeEntry">
                        <v-icon start>mdi-clock-plus</v-icon>
                        Log Time
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Activity Log Modal -->
        <v-dialog v-model="isActivityLogModalOpen" max-width="600" scrollable>
            <v-card color="surface">
                <v-card-title class="d-flex align-center pa-4">
                    <v-icon start color="primary">mdi-history</v-icon>
                    Activity Log
                    <v-spacer />
                    <v-btn icon variant="text" size="small" @click="isActivityLogModalOpen = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-4">
                    <!-- Filter by User -->
                    <v-select v-model="activityFilterUser"
                        :items="[{ id: null, name: 'All Team Members' }, ...teamMembers]" item-title="name"
                        item-value="id" label="Filter by Team Member" variant="outlined" density="comfortable"
                        bg-color="surface-variant" class="mb-4" clearable />

                    <!-- Activity Timeline -->
                    <v-timeline density="compact" side="end">
                        <v-timeline-item v-for="activity in filteredActivityLog.slice(0, 20)" :key="activity.id"
                            :dot-color="getActivityColor(activity.type)" size="small">
                            <template #icon>
                                <v-icon size="14" color="white">{{ getActivityIcon(activity.type) }}</v-icon>
                            </template>
                            <v-card color="surface-variant" variant="flat" class="mb-2">
                                <v-card-text class="pa-3">
                                    <div class="d-flex align-center mb-1">
                                        <v-avatar size="24" :color="getMember(activity.userId)?.color" class="mr-2">
                                            <span style="font-size: 9px;">{{ getMember(activity.userId)?.avatar
                                                }}</span>
                                        </v-avatar>
                                        <span class="text-body-2 font-weight-medium">{{ getMember(activity.userId)?.name
                                            }}</span>
                                        <v-spacer />
                                        <span class="text-caption text-medium-emphasis">{{
                                            formatActivityTime(activity.timestamp) }}</span>
                                    </div>
                                    <p class="text-body-2 text-medium-emphasis">
                                        {{ getActivityDescription(activity) }}
                                    </p>
                                    <v-chip v-if="activity.hours" size="x-small" color="primary" variant="tonal"
                                        class="mt-1">
                                        <v-icon start size="10">mdi-clock</v-icon>
                                        {{ activity.hours }} manhours
                                    </v-chip>
                                </v-card-text>
                            </v-card>
                        </v-timeline-item>
                    </v-timeline>
                </v-card-text>
            </v-card>
        </v-dialog>

        <!-- Snackbar for notifications -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right">
            {{ snackbar.text }}
            <template #actions>
                <v-btn variant="text" size="small" @click="snackbar.show = false">Close</v-btn>
            </template>
        </v-snackbar>
    </v-app>
</template>

<style scoped>
/* Board Layout */
.board-main {
    overflow: hidden;
}

.board-area {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.board-header {
    flex-shrink: 0;
}

/* Sidebar */
.board-color-dot {
    width: 20px;
    height: 16px;
    border-radius: 4px;
}

:deep(.v-list-group__items .v-list-item) {
    padding-inline-start: 20px !important;
}

/* Member Avatars */
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
    -webkit-overflow-scrolling: touch;
}

.kanban-board::-webkit-scrollbar {
    height: 8px;
}

.kanban-board::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.lists-container {
    height: 100%;
    align-items: flex-start;
    min-width: max-content;
}

/* Feature Lists */
.kanban-list {
    width: 300px;
    min-width: 300px;
    max-height: calc(100vh - 180px);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

@media (max-width: 600px) {
    .kanban-list {
        width: 280px;
        min-width: 280px;
    }
}

.list-header {
    flex-shrink: 0;
}

.features-container {
    flex: 1;
    overflow-y: auto;
    min-height: 20px;
    -webkit-overflow-scrolling: touch;
}

/* Feature Cards */
.feature-card {
    cursor: pointer;
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.feature-card:hover {
    transform: translateY(-2px);
}

@media (hover: none) {
    .feature-card:hover {
        transform: none;
    }
}

.feature-label {
    width: 40px;
    height: 8px;
    border-radius: 4px;
}

.feature-assignee {
    margin-left: -6px;
    border: 2px solid rgb(var(--v-theme-surface-variant));
}

.feature-assignee:first-child {
    margin-left: 0;
}

/* Add List */
.add-list-container {
    min-width: 300px;
    flex-shrink: 0;
    align-self: flex-start;
}

@media (max-width: 600px) {
    .add-list-container {
        min-width: 280px;
    }
}

.add-list-btn {
    min-height: 48px;
}

.add-list-form {
    width: 300px;
}

/* Task Kanban (inside modal) */
.task-kanban {
    overflow-x: auto;
    padding-bottom: 8px;
    -webkit-overflow-scrolling: touch;
}

.task-lists-container {
    min-width: max-content;
}

.task-list {
    width: 200px;
    min-width: 200px;
}

.task-cards-container {
    min-height: 40px;
}

.task-card {
    cursor: grab;
}

.task-card:active {
    cursor: grabbing;
}

/* Feature Title Input */
.feature-title-input :deep(.v-field__input) {
    padding: 0 !important;
    min-height: auto !important;
}

/* Button Styling */
:deep(.v-btn) {
    letter-spacing: normal !important;
}

/* Drag Ghost Styles */
.ghost-list,
.ghost-card,
.ghost-task {
    opacity: 0.5;
    border: 2px dashed rgb(var(--v-theme-primary));
}

.sortable-chosen {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}

/* Touch Improvements */
@media (hover: none) and (pointer: coarse) {

    .feature-card,
    .task-card,
    .v-btn,
    .v-list-item {
        -webkit-tap-highlight-color: transparent;
    }
}
</style>
