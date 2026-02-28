<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Folder;
use App\Models\Label;
use App\Models\Priority;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\View;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. USERS
        // ================================================================
        $admin = User::factory()->create([
            'name' => 'Sasya Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $john = User::factory()->create([
            'name' => 'John Developer',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        $sarah = User::factory()->create([
            'name' => 'Sarah Designer',
            'email' => 'sarah@example.com',
            'password' => Hash::make('password'),
        ]);

        $mike = User::factory()->create([
            'name' => 'Mike QA',
            'email' => 'mike@example.com',
            'password' => Hash::make('password'),
        ]);

        $lisa = User::factory()->create([
            'name' => 'Lisa PM',
            'email' => 'lisa@example.com',
            'password' => Hash::make('password'),
        ]);

        // ================================================================
        // 2. WORKSPACE
        // ================================================================
        $workspace = Workspace::create([
            'name' => 'Startup Rocket',
            'slug' => 'startup-rocket',
            'description' => 'Building an amazing SaaS product for project management',
            'owner_id' => $admin->id,
            'color' => '#7B68EE',
        ]);

        // Boot auto-attaches owner; add remaining members
        $workspace->addMember($john, 'member');
        $workspace->addMember($sarah, 'member');
        $workspace->addMember($mike, 'member');
        $workspace->addMember($lisa, 'admin');

        // ================================================================
        // 3. PRIORITIES
        // ================================================================
        $urgent = Priority::create(['name' => 'Urgent', 'level' => 1, 'color' => '#FF6B6B', 'icon' => 'mdi-alert-circle', 'workspace_id' => $workspace->id]);
        $high   = Priority::create(['name' => 'High',   'level' => 2, 'color' => '#FFB84D', 'icon' => 'mdi-arrow-up-bold', 'workspace_id' => $workspace->id]);
        $normal = Priority::create(['name' => 'Normal', 'level' => 3, 'color' => '#49CCF9', 'icon' => 'mdi-minus',          'workspace_id' => $workspace->id, 'is_default' => true]);
        $low    = Priority::create(['name' => 'Low',    'level' => 4, 'color' => '#6B7280', 'icon' => 'mdi-arrow-down-bold','workspace_id' => $workspace->id]);

        // ================================================================
        // 4. LABELS
        // ================================================================
        $labelBug         = Label::create(['name' => 'Bug',           'color' => '#FF6B6B', 'workspace_id' => $workspace->id]);
        $labelFeature     = Label::create(['name' => 'Feature',       'color' => '#6BC950', 'workspace_id' => $workspace->id]);
        $labelEnhancement = Label::create(['name' => 'Enhancement',   'color' => '#49CCF9', 'workspace_id' => $workspace->id]);
        $labelDocs        = Label::create(['name' => 'Documentation', 'color' => '#8B5CF6', 'workspace_id' => $workspace->id]);
        $labelDesign      = Label::create(['name' => 'Design',        'color' => '#EC4899', 'workspace_id' => $workspace->id]);
        $labelRefactor    = Label::create(['name' => 'Refactor',      'color' => '#F59E0B', 'workspace_id' => $workspace->id]);
        $labelSecurity    = Label::create(['name' => 'Security',      'color' => '#EF4444', 'workspace_id' => $workspace->id]);
        $labelPerformance = Label::create(['name' => 'Performance',   'color' => '#14B8A6', 'workspace_id' => $workspace->id]);

        // ================================================================
        // 5. SPACES  (boot auto-creates 4 default statuses each)
        // ================================================================

        // --- Space: Development ---
        $devSpace = Space::create([
            'name' => 'Development',
            'workspace_id' => $workspace->id,
            'color' => '#6366F1',
            'icon' => 'mdi-code-braces',
            'position' => 0,
            'created_by' => $admin->id,
        ]);

        // Replace default statuses with custom ones
        $devSpace->statuses()->delete();
        $devBacklog    = Status::create(['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'space_id' => $devSpace->id, 'position' => 0, 'applies_to' => 'both']);
        $devTodo       = Status::create(['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'space_id' => $devSpace->id, 'position' => 1, 'applies_to' => 'both', 'is_default' => true]);
        $devInProgress = Status::create(['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $devSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $devReview     = Status::create(['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'space_id' => $devSpace->id, 'position' => 3, 'applies_to' => 'both']);
        $devDone       = Status::create(['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'space_id' => $devSpace->id, 'position' => 4, 'applies_to' => 'both', 'is_closed' => true]);

        // --- Space: Design ---
        $designSpace = Space::create([
            'name' => 'Design',
            'workspace_id' => $workspace->id,
            'color' => '#EC4899',
            'icon' => 'mdi-palette',
            'position' => 1,
            'created_by' => $sarah->id,
        ]);

        $designSpace->statuses()->delete();
        $desIdeas    = Status::create(['name' => 'Ideas',     'type' => 'open',        'color' => '#6B7280', 'space_id' => $designSpace->id, 'position' => 0, 'applies_to' => 'both', 'is_default' => true]);
        $desDesign   = Status::create(['name' => 'In Design', 'type' => 'in_progress', 'color' => '#EC4899', 'space_id' => $designSpace->id, 'position' => 1, 'applies_to' => 'both']);
        $desFeedback = Status::create(['name' => 'Feedback',  'type' => 'review',      'color' => '#F59E0B', 'space_id' => $designSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $desApproved = Status::create(['name' => 'Approved',  'type' => 'closed',      'color' => '#10B981', 'space_id' => $designSpace->id, 'position' => 3, 'applies_to' => 'both', 'is_closed' => true]);

        // --- Space: QA & Testing ---
        $qaSpace = Space::create([
            'name' => 'QA & Testing',
            'workspace_id' => $workspace->id,
            'color' => '#F97316',
            'icon' => 'mdi-bug',
            'position' => 2,
            'created_by' => $mike->id,
        ]);

        $qaSpace->statuses()->delete();
        $qaReported  = Status::create(['name' => 'Reported',  'type' => 'open',        'color' => '#EF4444', 'space_id' => $qaSpace->id, 'position' => 0, 'applies_to' => 'both', 'is_default' => true]);
        $qaTriaging  = Status::create(['name' => 'Triaging',  'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $qaSpace->id, 'position' => 1, 'applies_to' => 'both']);
        $qaFixing    = Status::create(['name' => 'Fixing',    'type' => 'in_progress', 'color' => '#3B82F6', 'space_id' => $qaSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $qaVerifying = Status::create(['name' => 'Verifying', 'type' => 'review',      'color' => '#8B5CF6', 'space_id' => $qaSpace->id, 'position' => 3, 'applies_to' => 'both']);
        $qaClosed    = Status::create(['name' => 'Closed',    'type' => 'closed',      'color' => '#10B981', 'space_id' => $qaSpace->id, 'position' => 4, 'applies_to' => 'both', 'is_closed' => true]);

        // ================================================================
        // 6. SPRINTS
        // ================================================================
        $sprint1 = Sprint::create([
            'space_id'   => $devSpace->id,
            'name'       => 'Sprint 1 — Foundation',
            'goal'       => 'Set up project infrastructure, authentication, and core database schema',
            'start_date' => now()->subWeeks(3),
            'end_date'   => now()->subWeek(),
            'is_active'  => false,
            'position'   => 0,
        ]);

        $sprint2 = Sprint::create([
            'space_id'   => $devSpace->id,
            'name'       => 'Sprint 2 — Core Features',
            'goal'       => 'Build task management, time tracking, and dashboard',
            'start_date' => now()->subWeek(),
            'end_date'   => now()->addWeek(),
            'is_active'  => true,
            'position'   => 1,
        ]);

        $sprint3 = Sprint::create([
            'space_id'   => $devSpace->id,
            'name'       => 'Sprint 3 — Polish & Launch',
            'goal'       => 'Fix bugs, improve performance, prepare for deployment',
            'start_date' => now()->addWeek(),
            'end_date'   => now()->addWeeks(3),
            'is_active'  => false,
            'position'   => 2,
        ]);

        // ================================================================
        // 7. FOLDERS & LISTS — Development Space
        // ================================================================

        // Folder: Backend
        $backendFolder = Folder::create([
            'name'       => 'Backend',
            'space_id'   => $devSpace->id,
            'position'   => 0,
            'created_by' => $admin->id,
        ]);

        $authList = TaskList::create([
            'name'       => 'Authentication',
            'space_id'   => $devSpace->id,
            'folder_id'  => $backendFolder->id,
            'position'   => 0,
            'created_by' => $admin->id,
        ]);

        $apiList = TaskList::create([
            'name'       => 'API Development',
            'space_id'   => $devSpace->id,
            'folder_id'  => $backendFolder->id,
            'position'   => 1,
            'created_by' => $john->id,
        ]);

        // Folder: Frontend
        $frontendFolder = Folder::create([
            'name'       => 'Frontend',
            'space_id'   => $devSpace->id,
            'position'   => 1,
            'created_by' => $sarah->id,
        ]);

        $uiList = TaskList::create([
            'name'       => 'UI Components',
            'space_id'   => $devSpace->id,
            'folder_id'  => $frontendFolder->id,
            'position'   => 0,
            'created_by' => $sarah->id,
        ]);

        $pagesList = TaskList::create([
            'name'       => 'Pages',
            'space_id'   => $devSpace->id,
            'folder_id'  => $frontendFolder->id,
            'position'   => 1,
            'created_by' => $john->id,
        ]);

        // Standalone list (no folder)
        $infraList = TaskList::create([
            'name'       => 'Infrastructure',
            'space_id'   => $devSpace->id,
            'position'   => 2,
            'created_by' => $admin->id,
        ]);

        // ================================================================
        // 8. FOLDERS & LISTS — Design Space
        // ================================================================
        $designUiList = TaskList::create([
            'name'       => 'UI/UX Designs',
            'space_id'   => $designSpace->id,
            'position'   => 0,
            'created_by' => $sarah->id,
        ]);

        $brandList = TaskList::create([
            'name'       => 'Branding',
            'space_id'   => $designSpace->id,
            'position'   => 1,
            'created_by' => $sarah->id,
        ]);

        // ================================================================
        // 9. FOLDERS & LISTS — QA Space
        // ================================================================
        $bugsList = TaskList::create([
            'name'       => 'Bug Reports',
            'space_id'   => $qaSpace->id,
            'position'   => 0,
            'created_by' => $mike->id,
        ]);

        $regressionList = TaskList::create([
            'name'       => 'Regression Tests',
            'space_id'   => $qaSpace->id,
            'position'   => 1,
            'created_by' => $mike->id,
        ]);

        // ================================================================
        // 10. TASKS & SUBTASKS — Authentication List  (full CPM demo)
        // ================================================================

        // -- Task: User Authentication System --
        $authTask = Task::create([
            'name'         => 'User Authentication System',
            'description'  => 'Implement complete authentication flow including login, register, password reset, and 2FA',
            'task_list_id' => $authList->id,
            'status_id'    => $devInProgress->id,
            'priority_id'  => $urgent->id,
            'created_by'   => $admin->id,
            'position'     => 0,
        ]);
        $authTask->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $authTask->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $authTask->labels()->attach([$labelFeature->id, $labelSecurity->id]);

        // Subtasks (10 items — CPM dependency chain)
        $aS1  = Subtask::create(['name' => 'Database schema design',  'task_id' => $authTask->id, 'status_id' => $devDone->id,       'priority_id' => $high->id,   'time_estimate' => 120, 'position' => 0, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'start_date' => now()->subWeeks(3),             'due_date' => now()->subWeeks(3)->addDay(),     'completed_at' => now()->subWeeks(3)->addDay()]);
        $aS2  = Subtask::create(['name' => 'Setup Laravel Fortify',   'task_id' => $authTask->id, 'status_id' => $devDone->id,       'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 1, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'start_date' => now()->subWeeks(3)->addDay(),   'due_date' => now()->subWeeks(3)->addDays(2),   'completed_at' => now()->subWeeks(3)->addDays(2)]);
        $aS3  = Subtask::create(['name' => 'Login API endpoint',      'task_id' => $authTask->id, 'status_id' => $devDone->id,       'priority_id' => $urgent->id, 'time_estimate' => 240, 'position' => 2, 'created_by' => $john->id,  'sprint_id' => $sprint1->id, 'start_date' => now()->subWeeks(2),             'due_date' => now()->subWeeks(2)->addDays(2),   'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $aS4  = Subtask::create(['name' => 'Register API endpoint',   'task_id' => $authTask->id, 'status_id' => $devDone->id,       'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 3, 'created_by' => $john->id,  'sprint_id' => $sprint1->id, 'start_date' => now()->subWeeks(2),             'due_date' => now()->subWeeks(2)->addDays(2),   'completed_at' => now()->subWeeks(2)->addDay()]);
        $aS5  = Subtask::create(['name' => 'Password reset flow',     'task_id' => $authTask->id, 'status_id' => $devInProgress->id, 'priority_id' => $normal->id, 'time_estimate' => 300, 'position' => 4, 'created_by' => $john->id,  'sprint_id' => $sprint2->id, 'start_date' => now()->subDays(3),              'due_date' => now()->addDays(2)]);
        $aS6  = Subtask::create(['name' => 'Email verification',      'task_id' => $authTask->id, 'status_id' => $devTodo->id,       'priority_id' => $low->id,    'time_estimate' => 120, 'position' => 5, 'created_by' => $admin->id, 'sprint_id' => $sprint2->id, 'start_date' => now(),                          'due_date' => now()->addDays(4)]);
        $aS7  = Subtask::create(['name' => 'Login page UI',           'task_id' => $authTask->id, 'status_id' => $devInProgress->id, 'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 6, 'created_by' => $sarah->id, 'sprint_id' => $sprint2->id, 'start_date' => now()->subDays(2),              'due_date' => now()->addDay()]);
        $aS8  = Subtask::create(['name' => 'Register page UI',        'task_id' => $authTask->id, 'status_id' => $devTodo->id,       'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 7, 'created_by' => $sarah->id, 'sprint_id' => $sprint2->id,                                                 'due_date' => now()->addDays(5)]);
        $aS9  = Subtask::create(['name' => 'Integration testing',     'task_id' => $authTask->id, 'status_id' => $devBacklog->id,    'priority_id' => $high->id,   'time_estimate' => 360, 'position' => 8, 'created_by' => $mike->id,  'sprint_id' => $sprint3->id,                                                 'due_date' => now()->addWeeks(2)]);
        $aS10 = Subtask::create(['name' => 'Deploy auth module',      'task_id' => $authTask->id, 'status_id' => $devBacklog->id,    'priority_id' => $urgent->id, 'time_estimate' => 60,  'position' => 9, 'created_by' => $admin->id, 'sprint_id' => $sprint3->id,                                                 'due_date' => now()->addWeeks(2)->addDays(2)]);

        // Assign subtask members
        $aS1->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS2->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS3->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS4->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS5->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS6->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS7->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS8->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS9->assignees()->attach([$mike->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS10->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $aS10->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        // Labels on subtasks
        $aS3->labels()->attach([$labelSecurity->id]);
        $aS5->labels()->attach([$labelSecurity->id]);
        $aS7->labels()->attach([$labelDesign->id]);
        $aS8->labels()->attach([$labelDesign->id]);

        // Dependencies (CPM graph)
        //   Schema(1) → Fortify(2) → Login API(3) → Login UI(7) ──┐
        //                          → Register API(4) → Register UI(8)──→ Integration(9) → Deploy(10)
        //                          → Password Reset(5) ──────────────┘
        //                          → Email Verif.(6) ─────────────────┘
        $aS2->dependencies()->attach($aS1->id,  ['dependency_type' => 'blocks']);
        $aS3->dependencies()->attach($aS2->id,  ['dependency_type' => 'blocks']);
        $aS4->dependencies()->attach($aS2->id,  ['dependency_type' => 'blocks']);
        $aS5->dependencies()->attach($aS2->id,  ['dependency_type' => 'blocks']);
        $aS6->dependencies()->attach($aS2->id,  ['dependency_type' => 'blocks']);
        $aS7->dependencies()->attach($aS3->id,  ['dependency_type' => 'blocks']);
        $aS8->dependencies()->attach($aS4->id,  ['dependency_type' => 'blocks']);
        $aS9->dependencies()->attach($aS7->id,  ['dependency_type' => 'blocks']);
        $aS9->dependencies()->attach($aS8->id,  ['dependency_type' => 'blocks']);
        $aS9->dependencies()->attach($aS5->id,  ['dependency_type' => 'blocks']);
        $aS9->dependencies()->attach($aS6->id,  ['dependency_type' => 'blocks']);
        $aS10->dependencies()->attach($aS9->id, ['dependency_type' => 'blocks']);

        // Time entries for completed/in-progress subtasks
        $this->timeEntry($aS1, $admin, 110, now()->subWeeks(3),               'ER diagram and schema design');
        $this->timeEntry($aS2, $admin, 165, now()->subWeeks(3)->addDay(),     'Fortify installation and config');
        $this->timeEntry($aS3, $john,  200, now()->subWeeks(2),               'Login with validation rules');
        $this->timeEntry($aS3, $john,  45,  now()->subWeeks(2)->addDay(),     'Fixed remember-me edge case');
        $this->timeEntry($aS4, $john,  170, now()->subWeeks(2),               'Register with email verification');
        $this->timeEntry($aS5, $john,  90,  now()->subDays(3),               'Started password reset implementation');
        $this->timeEntry($aS7, $sarah, 120, now()->subDays(2),               'Login page mockup and implementation');

        // -- Task: Database Optimization --
        $dbTask = Task::create([
            'name'         => 'Database Optimization',
            'description'  => 'Optimize queries, add composite indexes, and fix N+1 problems',
            'task_list_id' => $authList->id,
            'status_id'    => $devDone->id,
            'priority_id'  => $high->id,
            'created_by'   => $admin->id,
            'position'     => 1,
        ]);
        $dbTask->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $dbTask->labels()->attach([$labelPerformance->id]);

        $dbS1 = Subtask::create(['name' => 'Analyze slow queries',  'task_id' => $dbTask->id, 'status_id' => $devDone->id, 'priority_id' => $high->id,   'time_estimate' => 120, 'position' => 0, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $dbS2 = Subtask::create(['name' => 'Add composite indexes', 'task_id' => $dbTask->id, 'status_id' => $devDone->id, 'priority_id' => $high->id,   'time_estimate' => 90,  'position' => 1, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)->addDay()]);
        $dbS3 = Subtask::create(['name' => 'Fix N+1 queries',       'task_id' => $dbTask->id, 'status_id' => $devDone->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 2, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);

        $dbS2->dependencies()->attach($dbS1->id, ['dependency_type' => 'blocks']);
        $dbS3->dependencies()->attach($dbS1->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($dbS1, $admin, 100, now()->subWeeks(2)->subDays(2), 'EXPLAIN analysis on 12 queries');
        $this->timeEntry($dbS2, $admin, 80,  now()->subWeeks(2)->subDay(),   'Composite indexes on tasks + subtasks');
        $this->timeEntry($dbS3, $admin, 160, now()->subWeeks(2),             'Converted to eager loading');

        // ================================================================
        // 11. TASKS & SUBTASKS — API Development List
        // ================================================================

        // -- Task: REST API for Tasks --
        $apiTask = Task::create([
            'name'         => 'REST API for Tasks',
            'description'  => 'Build complete CRUD API with filtering, sorting, and pagination',
            'task_list_id' => $apiList->id,
            'status_id'    => $devInProgress->id,
            'priority_id'  => $high->id,
            'created_by'   => $john->id,
            'position'     => 0,
        ]);
        $apiTask->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apiTask->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apiTask->labels()->attach([$labelFeature->id]);

        $apS1 = Subtask::create(['name' => 'Design API schema (OpenAPI)',  'task_id' => $apiTask->id, 'status_id' => $devDone->id,       'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $john->id,  'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $apS2 = Subtask::create(['name' => 'Implement CRUD routes',        'task_id' => $apiTask->id, 'status_id' => $devDone->id,       'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 1, 'created_by' => $john->id,  'sprint_id' => $sprint2->id, 'completed_at' => now()->subDays(3)]);
        $apS3 = Subtask::create(['name' => 'Add auth middleware',           'task_id' => $apiTask->id, 'status_id' => $devInProgress->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 2, 'created_by' => $john->id,  'sprint_id' => $sprint2->id, 'start_date' => now()->subDay(), 'due_date' => now()->addDays(2)]);
        $apS4 = Subtask::create(['name' => 'Write API tests (Pest)',       'task_id' => $apiTask->id, 'status_id' => $devTodo->id,       'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 3, 'created_by' => $mike->id,  'sprint_id' => $sprint2->id, 'due_date' => now()->addDays(5)]);
        $apS5 = Subtask::create(['name' => 'Rate limiting & pagination',   'task_id' => $apiTask->id, 'status_id' => $devBacklog->id,    'priority_id' => $low->id,    'time_estimate' => 90,  'position' => 4, 'created_by' => $john->id,  'sprint_id' => $sprint2->id, 'due_date' => now()->addWeek()]);

        $apS2->dependencies()->attach($apS1->id, ['dependency_type' => 'blocks']);
        $apS3->dependencies()->attach($apS2->id, ['dependency_type' => 'blocks']);
        $apS4->dependencies()->attach($apS3->id, ['dependency_type' => 'blocks']);
        $apS5->dependencies()->attach($apS2->id, ['dependency_type' => 'blocks']);

        $apS1->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apS2->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apS3->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apS4->assignees()->attach([$mike->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $apS5->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        $this->timeEntry($apS1, $john, 150, now()->subWeeks(2),  'OpenAPI spec draft');
        $this->timeEntry($apS2, $john, 280, now()->subDays(5),   'Full CRUD implementation');
        $this->timeEntry($apS3, $john, 40,  now()->subDay(),     'Started middleware setup');

        // -- Task: WebSocket Real-time Updates --
        $wsTask = Task::create([
            'name'         => 'WebSocket Real-time Updates',
            'description'  => 'Add live collaboration via WebSockets for task updates',
            'task_list_id' => $apiList->id,
            'status_id'    => $devTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $john->id,
            'position'     => 1,
        ]);
        $wsTask->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $wsTask->labels()->attach([$labelFeature->id, $labelEnhancement->id]);

        $ws1 = Subtask::create(['name' => 'Setup Laravel Echo & Reverb', 'task_id' => $wsTask->id, 'status_id' => $devTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 0, 'created_by' => $john->id, 'sprint_id' => $sprint3->id]);
        $ws2 = Subtask::create(['name' => 'Broadcast task events',        'task_id' => $wsTask->id, 'status_id' => $devTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 1, 'created_by' => $john->id, 'sprint_id' => $sprint3->id]);
        $ws3 = Subtask::create(['name' => 'Frontend listener hooks',      'task_id' => $wsTask->id, 'status_id' => $devTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 150, 'position' => 2, 'created_by' => $john->id, 'sprint_id' => $sprint3->id]);

        $ws2->dependencies()->attach($ws1->id, ['dependency_type' => 'blocks']);
        $ws3->dependencies()->attach($ws2->id, ['dependency_type' => 'blocks']);

        // ================================================================
        // 12. TASKS & SUBTASKS — UI Components List
        // ================================================================

        // -- Task: Kanban Board Component --
        $kanbanTask = Task::create([
            'name'         => 'Kanban Board Component',
            'description'  => 'Build drag-and-drop kanban board with status columns',
            'task_list_id' => $uiList->id,
            'status_id'    => $devInProgress->id,
            'priority_id'  => $high->id,
            'created_by'   => $sarah->id,
            'position'     => 0,
        ]);
        $kanbanTask->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kanbanTask->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kanbanTask->labels()->attach([$labelFeature->id, $labelDesign->id]);

        $kS1 = Subtask::create(['name' => 'Design board layout',      'task_id' => $kanbanTask->id, 'status_id' => $devDone->id,       'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $sarah->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $kS2 = Subtask::create(['name' => 'Implement drag & drop',    'task_id' => $kanbanTask->id, 'status_id' => $devDone->id,       'priority_id' => $urgent->id, 'time_estimate' => 360, 'position' => 1, 'created_by' => $john->id,  'sprint_id' => $sprint2->id, 'completed_at' => now()->subDays(2)]);
        $kS3 = Subtask::create(['name' => 'Status column actions',    'task_id' => $kanbanTask->id, 'status_id' => $devInProgress->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 2, 'created_by' => $sarah->id, 'sprint_id' => $sprint2->id, 'start_date' => now()->subDay(), 'due_date' => now()->addDays(3)]);
        $kS4 = Subtask::create(['name' => 'Task card component',      'task_id' => $kanbanTask->id, 'status_id' => $devTodo->id,       'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 3, 'created_by' => $sarah->id, 'sprint_id' => $sprint2->id, 'due_date' => now()->addDays(5)]);
        $kS5 = Subtask::create(['name' => 'Mobile responsiveness',    'task_id' => $kanbanTask->id, 'status_id' => $devBacklog->id,    'priority_id' => $low->id,    'time_estimate' => 180, 'position' => 4, 'created_by' => $sarah->id, 'sprint_id' => $sprint3->id]);

        $kS2->dependencies()->attach($kS1->id, ['dependency_type' => 'blocks']);
        $kS3->dependencies()->attach($kS2->id, ['dependency_type' => 'blocks']);
        $kS4->dependencies()->attach($kS2->id, ['dependency_type' => 'blocks']);
        $kS5->dependencies()->attach($kS3->id, ['dependency_type' => 'blocks']);
        $kS5->dependencies()->attach($kS4->id, ['dependency_type' => 'blocks']);

        $kS1->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kS2->assignees()->attach([$john->id  => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kS3->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kS4->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $kS5->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        $this->timeEntry($kS1, $sarah, 160, now()->subWeeks(2),  'Figma mockups for board layout');
        $this->timeEntry($kS2, $john,  300, now()->subDays(5),   'vuedraggable integration');
        $this->timeEntry($kS2, $john,  55,  now()->subDays(3),   'Fixed drop zone boundary bugs');
        $this->timeEntry($kS3, $sarah, 45,  now()->subDay(),     'Started column actions menu');

        // -- Task: Time Tracker Widget --
        $ttTask = Task::create([
            'name'         => 'Time Tracker Widget',
            'description'  => 'Implement time tracking with start/stop timer and manual entry',
            'task_list_id' => $uiList->id,
            'status_id'    => $devReview->id,
            'priority_id'  => $normal->id,
            'created_by'   => $john->id,
            'position'     => 1,
        ]);
        $ttTask->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $ttTask->labels()->attach([$labelFeature->id]);

        $tt1 = Subtask::create(['name' => 'Timer UI component',    'task_id' => $ttTask->id, 'status_id' => $devDone->id,   'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 0, 'created_by' => $john->id, 'sprint_id' => $sprint2->id, 'completed_at' => now()->subDays(2)]);
        $tt2 = Subtask::create(['name' => 'Server-side timer API', 'task_id' => $ttTask->id, 'status_id' => $devDone->id,   'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 1, 'created_by' => $john->id, 'sprint_id' => $sprint2->id, 'completed_at' => now()->subDay()]);
        $tt3 = Subtask::create(['name' => 'Manual time entry form','task_id' => $ttTask->id, 'status_id' => $devReview->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 2, 'created_by' => $john->id, 'sprint_id' => $sprint2->id, 'start_date' => now()->subDay(), 'due_date' => now()]);
        $tt4 = Subtask::create(['name' => 'Time tracking reports', 'task_id' => $ttTask->id, 'status_id' => $devTodo->id,   'priority_id' => $low->id,    'time_estimate' => 300, 'position' => 3, 'created_by' => $lisa->id, 'sprint_id' => $sprint2->id, 'due_date' => now()->addDays(4)]);

        $tt2->dependencies()->attach($tt1->id, ['dependency_type' => 'blocks']);
        $tt3->dependencies()->attach($tt2->id, ['dependency_type' => 'blocks']);
        $tt4->dependencies()->attach($tt2->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($tt1, $john, 170, now()->subDays(4), 'Timer UI with Vuetify chips');
        $this->timeEntry($tt2, $john, 220, now()->subDays(2), 'Start/stop API endpoints');
        $this->timeEntry($tt3, $john, 60,  now()->subDay(),   'Manual entry dialog');

        // ================================================================
        // 13. TASKS — Pages List
        // ================================================================

        // -- Task: Dashboard Page (completed) --
        $dashTask = Task::create([
            'name'         => 'Dashboard Page',
            'description'  => 'Main dashboard with activity feed, assigned tasks, and running timers',
            'task_list_id' => $pagesList->id,
            'status_id'    => $devDone->id,
            'priority_id'  => $high->id,
            'created_by'   => $sarah->id,
            'position'     => 0,
        ]);
        $dashTask->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        $dP1 = Subtask::create(['name' => 'Layout & navigation',  'task_id' => $dashTask->id, 'status_id' => $devDone->id, 'priority_id' => $high->id,   'time_estimate' => 120, 'position' => 0, 'created_by' => $sarah->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $dP2 = Subtask::create(['name' => 'Activity feed widget', 'task_id' => $dashTask->id, 'status_id' => $devDone->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 1, 'created_by' => $sarah->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $dP3 = Subtask::create(['name' => 'My tasks widget',      'task_id' => $dashTask->id, 'status_id' => $devDone->id, 'priority_id' => $normal->id, 'time_estimate' => 150, 'position' => 2, 'created_by' => $sarah->id, 'sprint_id' => $sprint2->id, 'completed_at' => now()->subDays(4)]);

        $this->timeEntry($dP1, $sarah, 110, now()->subWeeks(2),          'Sidebar + header setup');
        $this->timeEntry($dP2, $sarah, 170, now()->subWeeks(2)->addDay(),'Activity feed component');
        $this->timeEntry($dP3, $sarah, 140, now()->subDays(5),           'Task summary cards');

        // -- Task: Settings Page --
        $settingsTask = Task::create([
            'name'         => 'Settings Page',
            'description'  => 'Workspace settings, member management, integrations',
            'task_list_id' => $pagesList->id,
            'status_id'    => $devTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $admin->id,
            'position'     => 1,
        ]);
        $settingsTask->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        Subtask::create(['name' => 'Profile settings UI',   'task_id' => $settingsTask->id, 'status_id' => $devTodo->id,     'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 0, 'created_by' => $john->id, 'sprint_id' => $sprint2->id]);
        Subtask::create(['name' => 'Workspace management',  'task_id' => $settingsTask->id, 'status_id' => $devTodo->id,     'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 1, 'created_by' => $john->id, 'sprint_id' => $sprint2->id]);
        Subtask::create(['name' => 'Member invite & roles', 'task_id' => $settingsTask->id, 'status_id' => $devBacklog->id,  'priority_id' => $low->id,    'time_estimate' => 240, 'position' => 2, 'created_by' => $john->id, 'sprint_id' => $sprint3->id]);

        // ================================================================
        // 14. TASKS — Infrastructure List
        // ================================================================

        // -- Task: Docker Setup (completed) --
        $dockerTask = Task::create([
            'name'         => 'Docker Setup',
            'description'  => 'Docker Compose for local development and production',
            'task_list_id' => $infraList->id,
            'status_id'    => $devDone->id,
            'priority_id'  => $high->id,
            'created_by'   => $admin->id,
            'position'     => 0,
        ]);
        $dockerTask->assignees()->attach([$admin->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $dockerTask->labels()->attach([$labelEnhancement->id]);

        $dk1 = Subtask::create(['name' => 'Dockerfile & docker-compose.yml', 'task_id' => $dockerTask->id, 'status_id' => $devDone->id, 'priority_id' => $high->id,   'time_estimate' => 120, 'position' => 0, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(3)]);
        $dk2 = Subtask::create(['name' => 'CI/CD pipeline (GitHub Actions)', 'task_id' => $dockerTask->id, 'status_id' => $devDone->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 1, 'created_by' => $admin->id, 'sprint_id' => $sprint1->id, 'completed_at' => now()->subWeeks(3)->addDays(2)]);

        $dk2->dependencies()->attach($dk1->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($dk1, $admin, 115, now()->subWeeks(3),          'Docker multi-stage build');
        $this->timeEntry($dk2, $admin, 170, now()->subWeeks(3)->addDay(),'GitHub Actions CI/CD');

        // -- Task: Monitoring & Logging (backlog) --
        $monitorTask = Task::create([
            'name'         => 'Monitoring & Logging',
            'description'  => 'Setup error tracking, log rotation, and health checks',
            'task_list_id' => $infraList->id,
            'status_id'    => $devBacklog->id,
            'priority_id'  => $low->id,
            'created_by'   => $admin->id,
            'position'     => 1,
        ]);
        $monitorTask->labels()->attach([$labelEnhancement->id]);

        Subtask::create(['name' => 'Sentry integration',     'task_id' => $monitorTask->id, 'status_id' => $devBacklog->id, 'priority_id' => $low->id, 'time_estimate' => 90,  'position' => 0, 'created_by' => $admin->id, 'sprint_id' => $sprint3->id]);
        Subtask::create(['name' => 'Log rotation config',    'task_id' => $monitorTask->id, 'status_id' => $devBacklog->id, 'priority_id' => $low->id, 'time_estimate' => 60,  'position' => 1, 'created_by' => $admin->id, 'sprint_id' => $sprint3->id]);
        Subtask::create(['name' => 'Health check endpoint',  'task_id' => $monitorTask->id, 'status_id' => $devBacklog->id, 'priority_id' => $low->id, 'time_estimate' => 45,  'position' => 2, 'created_by' => $admin->id, 'sprint_id' => $sprint3->id]);

        // ================================================================
        // 15. TASKS & SUBTASKS — Design Space
        // ================================================================

        // -- Task: Design System --
        $dsTask = Task::create([
            'name'         => 'Design System',
            'description'  => 'Consistent design system: tokens, components, and patterns',
            'task_list_id' => $designUiList->id,
            'status_id'    => $desDesign->id,
            'priority_id'  => $high->id,
            'created_by'   => $sarah->id,
            'position'     => 0,
        ]);
        $dsTask->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);
        $dsTask->labels()->attach([$labelDesign->id]);

        $ds1 = Subtask::create(['name' => 'Color palette & typography', 'task_id' => $dsTask->id, 'status_id' => $desApproved->id, 'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $sarah->id, 'completed_at' => now()->subWeeks(2)]);
        $ds2 = Subtask::create(['name' => 'Button & input components',  'task_id' => $dsTask->id, 'status_id' => $desDesign->id,   'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 1, 'created_by' => $sarah->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(2)]);
        $ds3 = Subtask::create(['name' => 'Card & layout patterns',     'task_id' => $dsTask->id, 'status_id' => $desIdeas->id,    'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 2, 'created_by' => $sarah->id, 'due_date' => now()->addDays(7)]);
        $ds4 = Subtask::create(['name' => 'Iconography guidelines',     'task_id' => $dsTask->id, 'status_id' => $desIdeas->id,    'priority_id' => $low->id,    'time_estimate' => 120, 'position' => 3, 'created_by' => $sarah->id]);

        $ds2->dependencies()->attach($ds1->id, ['dependency_type' => 'blocks']);
        $ds3->dependencies()->attach($ds1->id, ['dependency_type' => 'blocks']);

        $ds1->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $sarah->id]]);
        $ds2->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $sarah->id]]);

        $this->timeEntry($ds1, $sarah, 170, now()->subWeeks(2), 'Figma style guide v1');
        $this->timeEntry($ds2, $sarah, 90,  now()->subDays(2),  'Button variants');

        // -- Task: Mobile App Mockups --
        $mobileTask = Task::create([
            'name'         => 'Mobile App Mockups',
            'description'  => 'Wireframes and high-fidelity mockups for mobile companion app',
            'task_list_id' => $designUiList->id,
            'status_id'    => $desIdeas->id,
            'priority_id'  => $low->id,
            'created_by'   => $sarah->id,
            'position'     => 1,
        ]);
        $mobileTask->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        Subtask::create(['name' => 'Wireframe sketches', 'task_id' => $mobileTask->id, 'status_id' => $desIdeas->id, 'priority_id' => $low->id, 'time_estimate' => 240, 'position' => 0, 'created_by' => $sarah->id]);
        Subtask::create(['name' => 'Hi-fi mockups',      'task_id' => $mobileTask->id, 'status_id' => $desIdeas->id, 'priority_id' => $low->id, 'time_estimate' => 360, 'position' => 1, 'created_by' => $sarah->id]);

        // -- Task: Logo & Brand Identity --
        $brandTask = Task::create([
            'name'         => 'Logo & Brand Identity',
            'description'  => 'Logo, color scheme, and brand guidelines for Startup Rocket',
            'task_list_id' => $brandList->id,
            'status_id'    => $desApproved->id,
            'priority_id'  => $normal->id,
            'created_by'   => $sarah->id,
            'position'     => 0,
        ]);
        $brandTask->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $admin->id]]);

        $br1 = Subtask::create(['name' => 'Logo concepts (5 directions)',  'task_id' => $brandTask->id, 'status_id' => $desApproved->id, 'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 0, 'created_by' => $sarah->id, 'completed_at' => now()->subWeeks(3)]);
        $br2 = Subtask::create(['name' => 'Brand guidelines document',     'task_id' => $brandTask->id, 'status_id' => $desApproved->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 1, 'created_by' => $sarah->id, 'completed_at' => now()->subWeeks(2)]);

        $br2->dependencies()->attach($br1->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($br1, $sarah, 280, now()->subWeeks(3), 'Explored 5 logo directions');
        $this->timeEntry($br2, $sarah, 160, now()->subWeeks(2), 'Brand book PDF');

        // ================================================================
        // 16. TASKS & SUBTASKS — QA Space
        // ================================================================

        // -- Bug: Login page not responsive --
        $bug1 = Task::create([
            'name'         => 'Login page not responsive on mobile',
            'description'  => 'The login form overflows on screens < 375px. Button misaligned.',
            'task_list_id' => $bugsList->id,
            'status_id'    => $qaFixing->id,
            'priority_id'  => $high->id,
            'created_by'   => $mike->id,
            'position'     => 0,
        ]);
        $bug1->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $bug1->labels()->attach([$labelBug->id, $labelDesign->id]);

        $b1s1 = Subtask::create(['name' => 'Reproduce on multiple devices', 'task_id' => $bug1->id, 'status_id' => $qaClosed->id,   'priority_id' => $high->id,   'time_estimate' => 30,  'position' => 0, 'created_by' => $mike->id,  'completed_at' => now()->subDays(2)]);
        $b1s2 = Subtask::create(['name' => 'Fix CSS breakpoints',           'task_id' => $bug1->id, 'status_id' => $qaFixing->id,   'priority_id' => $high->id,   'time_estimate' => 60,  'position' => 1, 'created_by' => $sarah->id, 'start_date' => now()->subDay(), 'due_date' => now()]);
        $b1s3 = Subtask::create(['name' => 'Verify fix on all breakpoints', 'task_id' => $bug1->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 30,  'position' => 2, 'created_by' => $mike->id,  'due_date' => now()->addDay()]);

        $b1s2->dependencies()->attach($b1s1->id, ['dependency_type' => 'blocks']);
        $b1s3->dependencies()->attach($b1s2->id, ['dependency_type' => 'blocks']);

        $b1s1->assignees()->attach([$mike->id  => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $b1s2->assignees()->attach([$sarah->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $b1s3->assignees()->attach([$mike->id  => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);

        $this->timeEntry($b1s1, $mike,  25, now()->subDays(2), 'Tested on iPhone SE & Galaxy Fold');
        $this->timeEntry($b1s2, $sarah, 30, now()->subDay(),   'Adjusted media queries');

        // -- Bug: Drag-drop loses position --
        $bug2 = Task::create([
            'name'         => 'Task drag-drop loses position after refresh',
            'description'  => 'Dragging a task to a different column resets position on page refresh',
            'task_list_id' => $bugsList->id,
            'status_id'    => $qaTriaging->id,
            'priority_id'  => $urgent->id,
            'created_by'   => $mike->id,
            'position'     => 1,
        ]);
        $bug2->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $bug2->labels()->attach([$labelBug->id]);

        $b2s1 = Subtask::create(['name' => 'Record reproduction steps', 'task_id' => $bug2->id, 'status_id' => $qaClosed->id,   'priority_id' => $urgent->id, 'time_estimate' => 30, 'position' => 0, 'created_by' => $mike->id, 'completed_at' => now()->subDay()]);
        $b2s2 = Subtask::create(['name' => 'Debug position update API', 'task_id' => $bug2->id, 'status_id' => $qaTriaging->id, 'priority_id' => $urgent->id, 'time_estimate' => 90, 'position' => 1, 'created_by' => $john->id, 'start_date' => now(), 'due_date' => now()->addDay()]);
        $b2s3 = Subtask::create(['name' => 'Write regression test',     'task_id' => $bug2->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 60, 'position' => 2, 'created_by' => $mike->id]);

        $b2s2->dependencies()->attach($b2s1->id, ['dependency_type' => 'blocks']);
        $b2s3->dependencies()->attach($b2s2->id, ['dependency_type' => 'blocks']);

        $b2s1->assignees()->attach([$mike->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $b2s2->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);

        $this->timeEntry($b2s1, $mike, 20, now()->subDay(), 'Screen-recorded the bug');

        // -- Bug: Timer negative duration (fixed) --
        $bug3 = Task::create([
            'name'         => 'Timer shows negative duration',
            'description'  => 'Stopping a timer sometimes shows -1m in time spent',
            'task_list_id' => $bugsList->id,
            'status_id'    => $qaClosed->id,
            'priority_id'  => $high->id,
            'created_by'   => $mike->id,
            'position'     => 2,
        ]);
        $bug3->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $bug3->labels()->attach([$labelBug->id]);

        $b3s1 = Subtask::create(['name' => 'Root cause: Carbon diffInMinutes sign', 'task_id' => $bug3->id, 'status_id' => $qaClosed->id, 'priority_id' => $high->id, 'time_estimate' => 30, 'position' => 0, 'created_by' => $john->id, 'completed_at' => now()->subDay()]);
        $b3s2 = Subtask::create(['name' => 'Fix with max(1, abs(diff))',             'task_id' => $bug3->id, 'status_id' => $qaClosed->id, 'priority_id' => $high->id, 'time_estimate' => 15, 'position' => 1, 'created_by' => $john->id, 'completed_at' => now()->subDay()]);

        $b3s2->dependencies()->attach($b3s1->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($b3s1, $john, 25, now()->subDay(), 'Traced to Carbon 3 signed diff');
        $this->timeEntry($b3s2, $john, 10, now()->subDay(), 'One-liner fix');

        // -- Bug: CSRF token expired --
        $bug4 = Task::create([
            'name'         => 'CSRF token expired after long idle',
            'description'  => 'Users get 419 errors when submitting forms after being idle for 30+ minutes',
            'task_list_id' => $bugsList->id,
            'status_id'    => $qaClosed->id,
            'priority_id'  => $normal->id,
            'created_by'   => $mike->id,
            'position'     => 3,
        ]);
        $bug4->assignees()->attach([$john->id => ['assigned_at' => now(), 'assigned_by' => $mike->id]]);
        $bug4->labels()->attach([$labelBug->id, $labelSecurity->id]);

        $b4s1 = Subtask::create(['name' => 'Add CSRF refresh before API calls', 'task_id' => $bug4->id, 'status_id' => $qaClosed->id, 'priority_id' => $normal->id, 'time_estimate' => 60, 'position' => 0, 'created_by' => $john->id, 'completed_at' => now()->subDays(2)]);

        $this->timeEntry($b4s1, $john, 45, now()->subDays(2), 'safeFetch with meta tag refresh');

        // -- Regression Test Suite --
        $regTask = Task::create([
            'name'         => 'Sprint 2 Regression Suite',
            'description'  => 'Run full regression tests before sprint 2 release',
            'task_list_id' => $regressionList->id,
            'status_id'    => $qaReported->id,
            'priority_id'  => $normal->id,
            'created_by'   => $mike->id,
            'position'     => 0,
        ]);
        $regTask->assignees()->attach([$mike->id => ['assigned_at' => now(), 'assigned_by' => $lisa->id]]);

        Subtask::create(['name' => 'Auth flow test',  'task_id' => $regTask->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 60,  'position' => 0, 'created_by' => $mike->id, 'sprint_id' => $sprint2->id]);
        Subtask::create(['name' => 'Task CRUD test',  'task_id' => $regTask->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 90,  'position' => 1, 'created_by' => $mike->id, 'sprint_id' => $sprint2->id]);
        Subtask::create(['name' => 'Timer test',      'task_id' => $regTask->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 45,  'position' => 2, 'created_by' => $mike->id, 'sprint_id' => $sprint2->id]);
        Subtask::create(['name' => 'Drag-drop test',  'task_id' => $regTask->id, 'status_id' => $qaReported->id, 'priority_id' => $normal->id, 'time_estimate' => 60,  'position' => 3, 'created_by' => $mike->id, 'sprint_id' => $sprint2->id]);

        // ================================================================
        // 17. COMMENTS  (with threaded replies)
        // ================================================================

        // Auth task discussion
        $c1 = Comment::create(['task_id' => $authTask->id, 'user_id' => $admin->id, 'content' => 'Let\'s prioritize the login flow first. We need it before the demo next week.', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);
        Comment::create(['task_id' => $authTask->id, 'user_id' => $john->id, 'parent_id' => $c1->id, 'content' => 'Agreed, I\'ll start with the API endpoint today and wire up the Vue form tomorrow.', 'created_at' => now()->subDays(5)->addHours(2), 'updated_at' => now()->subDays(5)->addHours(2)]);
        Comment::create(['task_id' => $authTask->id, 'user_id' => $sarah->id, 'parent_id' => $c1->id, 'content' => 'I\'ll have the login page mockup ready by EOD so you can integrate directly.', 'created_at' => now()->subDays(5)->addHours(4), 'updated_at' => now()->subDays(5)->addHours(4)]);
        Comment::create(['task_id' => $authTask->id, 'user_id' => $mike->id, 'content' => 'Make sure we add rate limiting on the login endpoint to prevent brute force attacks.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        Comment::create(['task_id' => $authTask->id, 'user_id' => $lisa->id, 'content' => 'This is blocking the sprint 2 demo. How\'s the progress?', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        // Kanban board discussion
        $c2 = Comment::create(['task_id' => $kanbanTask->id, 'user_id' => $sarah->id, 'content' => 'The drag-drop is working great! Noticed a small glitch when dropping at the end of a column.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        Comment::create(['task_id' => $kanbanTask->id, 'user_id' => $john->id, 'parent_id' => $c2->id, 'content' => 'Good catch! It\'s a boundary condition in the drop handler. I\'ll fix it.', 'created_at' => now()->subDays(2)->addHour(), 'updated_at' => now()->subDays(2)->addHour()]);

        // Bug discussions
        Comment::create(['task_id' => $bug2->id, 'user_id' => $mike->id, 'content' => 'This is a blocker for the sprint demo. The position update API returns 200 but doesn\'t persist.', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        Comment::create(['task_id' => $bug2->id, 'user_id' => $john->id, 'content' => 'Looking into it now. Might be a transaction isolation issue.', 'created_at' => now()->subDay()->addHours(3), 'updated_at' => now()->subDay()->addHours(3)]);
        Comment::create(['task_id' => $bug2->id, 'user_id' => $lisa->id, 'content' => 'Escalating priority — this is affecting the demo scheduled for Friday.', 'created_at' => now()->subHours(6), 'updated_at' => now()->subHours(6)]);

        // Design discussion
        $c3 = Comment::create(['task_id' => $dsTask->id, 'user_id' => $lisa->id, 'content' => 'The color palette looks great! Can we also add a dark mode variant?', 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]);
        Comment::create(['task_id' => $dsTask->id, 'user_id' => $sarah->id, 'parent_id' => $c3->id, 'content' => 'Already in the system! Here\'s the Figma link: figma.com/xxx. Dark mode tokens included.', 'created_at' => now()->subDays(4)->addHours(2), 'updated_at' => now()->subDays(4)->addHours(2)]);

        // API task comment
        Comment::create(['task_id' => $apiTask->id, 'user_id' => $john->id, 'content' => 'API documentation is live at /api/docs. Let me know if anything is unclear.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);

        // Timer widget comment
        Comment::create(['task_id' => $ttTask->id, 'user_id' => $john->id, 'content' => 'Server-side timer is done! Uses safeFetch with auto CSRF refresh. Ready for review.', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        // ================================================================
        // 18. ACTIVITIES  (recent workspace activity log)
        // ================================================================
        $activities = [
            [$admin, $dockerTask,    'created',   ['name' => 'Docker Setup'],                                             now()->subWeeks(3)],
            [$admin, $dockerTask,    'completed', ['name' => 'Docker Setup'],                                             now()->subWeeks(3)->addDays(2)],
            [$admin, $authTask,      'created',   ['name' => 'User Authentication System'],                               now()->subWeeks(3)],
            [$admin, $authTask,      'assigned',  ['name' => 'User Authentication System', 'assignee' => 'John Developer'], now()->subWeeks(3)],
            [$sarah, $dsTask,        'created',   ['name' => 'Design System'],                                            now()->subWeeks(2)->subDay()],
            [$sarah, $brandTask,     'created',   ['name' => 'Logo & Brand Identity'],                                    now()->subWeeks(3)],
            [$sarah, $brandTask,     'completed', ['name' => 'Logo & Brand Identity'],                                    now()->subWeeks(2)],
            [$john,  $authTask,      'updated',   ['name' => 'User Authentication System'],                               now()->subWeeks(2),  ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sarah, $kanbanTask,    'created',   ['name' => 'Kanban Board Component'],                                   now()->subWeeks(2)],
            [$john,  $apiTask,       'created',   ['name' => 'REST API for Tasks'],                                       now()->subDays(7)],
            [$john,  $apiTask,       'updated',   ['name' => 'REST API for Tasks'],                                       now()->subDays(5),   ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sarah, $dashTask,      'completed', ['name' => 'Dashboard Page'],                                           now()->subDays(4)],
            [$mike,  $bug1,          'created',   ['name' => 'Login page not responsive on mobile'],                      now()->subDays(3)],
            [$mike,  $bug2,          'created',   ['name' => 'Task drag-drop loses position after refresh'],              now()->subDays(2)],
            [$mike,  $bug3,          'created',   ['name' => 'Timer shows negative duration'],                            now()->subDays(2)],
            [$john,  $bug3,          'completed', ['name' => 'Timer shows negative duration'],                            now()->subDay()],
            [$john,  $ttTask,        'updated',   ['name' => 'Time Tracker Widget'],                                      now()->subDay(),     ['status' => ['old' => 'In Progress', 'new' => 'Review']]],
            [$sarah, $dsTask,        'updated',   ['name' => 'Design System'],                                            now()->subDays(2),   ['status' => ['old' => 'Ideas', 'new' => 'In Design']]],
            [$lisa,  $regTask,       'created',   ['name' => 'Sprint 2 Regression Suite'],                                now()->subDay()],
        ];

        foreach ($activities as $act) {
            Activity::create([
                'workspace_id' => $workspace->id,
                'user_id'      => $act[0]->id,
                'subject_type'  => get_class($act[1]),
                'subject_id'    => $act[1]->id,
                'action'        => $act[2],
                'properties'    => $act[3],
                'changes'       => $act[5] ?? null,
                'created_at'    => $act[4],
                'updated_at'    => $act[4],
            ]);
        }

        // ================================================================
        // 19. VIEWS
        // ================================================================
        View::create(['task_list_id' => $authList->id,    'user_id' => $admin->id, 'name' => 'Board',     'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['task_list_id' => $authList->id,    'user_id' => $admin->id, 'name' => 'Gantt',     'type' => 'gantt',                        'position' => 1]);
        View::create(['task_list_id' => $apiList->id,     'user_id' => $john->id,  'name' => 'API Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['space_id'     => $devSpace->id,    'user_id' => $admin->id, 'name' => 'All Dev Tasks', 'type' => 'list', 'is_default' => true, 'position' => 0]);
        View::create(['space_id'     => $designSpace->id, 'user_id' => $sarah->id, 'name' => 'Design Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['space_id'     => $qaSpace->id,     'user_id' => $mike->id,  'name' => 'Bug Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);

        // ================================================================
        // SUMMARY
        // ================================================================
        $this->command->info('');
        $this->command->info('=== Database Seeded Successfully ===');
        $this->command->info('');
        $this->command->info('Users:        5 (admin + 4 team members)');
        $this->command->info('Workspace:    Startup Rocket');
        $this->command->info('Spaces:       3 (Development, Design, QA & Testing)');
        $this->command->info('Sprints:      3 (Foundation, Core Features, Polish & Launch)');
        $this->command->info('Folders:      2 (Backend, Frontend)');
        $this->command->info('Lists:        ' . TaskList::count());
        $this->command->info('Tasks:        ' . Task::count());
        $this->command->info('Subtasks:     ' . Subtask::count());
        $this->command->info('Time Entries: ' . TimeEntry::count());
        $this->command->info('Comments:     ' . Comment::count());
        $this->command->info('Activities:   ' . Activity::count());
        $this->command->info('Views:        ' . View::count());
        $this->command->info('');
        $this->command->info('Login (all password: "password"):');
        $this->command->info('  admin@example.com  (Owner)');
        $this->command->info('  john@example.com   (Developer)');
        $this->command->info('  sarah@example.com  (Designer)');
        $this->command->info('  mike@example.com   (QA)');
        $this->command->info('  lisa@example.com   (PM/Admin)');
        $this->command->info('');
        $this->command->info('CPM Demo: Open "User Authentication System" task -> Gantt view');
    }

    /**
     * Create a completed time entry for a subtask.
     */
    private function timeEntry(Subtask $subtask, User $user, int $minutes, $startedAt, ?string $description = null): TimeEntry
    {
        $started = \Carbon\Carbon::parse($startedAt);

        return TimeEntry::create([
            'subtask_id'  => $subtask->id,
            'user_id'     => $user->id,
            'duration'    => $minutes,
            'description' => $description,
            'started_at'  => $started,
            'ended_at'    => $started->copy()->addMinutes($minutes),
            'is_billable' => fake()->boolean(30),
            'is_running'  => false,
        ]);
    }
}
