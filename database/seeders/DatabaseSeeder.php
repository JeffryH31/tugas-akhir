<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Label;
use App\Models\Priority;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create main user
        $user = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create additional team members
        $teamMembers = collect([
            User::factory()->create(['name' => 'John Developer', 'email' => 'john@example.com', 'password' => Hash::make('password')]),
            User::factory()->create(['name' => 'Sarah Designer', 'email' => 'sarah@example.com', 'password' => Hash::make('password')]),
            User::factory()->create(['name' => 'Mike QA', 'email' => 'mike@example.com', 'password' => Hash::make('password')]),
        ]);

        // Create workspace
        $workspace = Workspace::create([
            'name' => 'My Company',
            'slug' => 'my-company',
            'owner_id' => $user->id,
            'color' => '#7B68EE',
        ]);

        // Add all users to workspace
        $workspace->addMember($user, 'owner');
        foreach ($teamMembers as $member) {
            $workspace->addMember($member, 'member');
        }

        // Create priorities
        $priorities = [
            ['name' => 'Urgent', 'level' => 1, 'color' => '#FF6B6B', 'workspace_id' => $workspace->id],
            ['name' => 'High', 'level' => 2, 'color' => '#FFB84D', 'workspace_id' => $workspace->id],
            ['name' => 'Normal', 'level' => 3, 'color' => '#49CCF9', 'workspace_id' => $workspace->id],
            ['name' => 'Low', 'level' => 4, 'color' => '#6B7280', 'workspace_id' => $workspace->id],
        ];

        foreach ($priorities as $priority) {
            Priority::create($priority);
        }

        // Create workspace labels
        $labels = [
            ['name' => 'Bug', 'color' => '#FF6B6B', 'workspace_id' => $workspace->id],
            ['name' => 'Feature', 'color' => '#6BC950', 'workspace_id' => $workspace->id],
            ['name' => 'Enhancement', 'color' => '#49CCF9', 'workspace_id' => $workspace->id],
            ['name' => 'Documentation', 'color' => '#8B5CF6', 'workspace_id' => $workspace->id],
        ];

        foreach ($labels as $label) {
            Label::create($label);
        }

        // Create spaces (these will auto-generate default statuses via Space model boot)
        $developmentSpace = Space::create([
            'name' => 'Development',
            'workspace_id' => $workspace->id,
            'color' => '#6366F1',
            'icon' => 'mdi-code-braces',
            'position' => 0,
        ]);

        $designSpace = Space::create([
            'name' => 'Design',
            'workspace_id' => $workspace->id,
            'color' => '#EC4899',
            'icon' => 'mdi-palette',
            'position' => 1,
        ]);

        // Get the auto-generated statuses and add custom ones
        // First, clear default statuses for development space to create custom workflow
        $developmentSpace->statuses()->delete();

        // Create custom statuses for development space
        $devStatuses = [
            ['name' => 'Backlog', 'type' => 'open', 'color' => '#6B7280', 'space_id' => $developmentSpace->id, 'position' => 0],
            ['name' => 'To Do', 'type' => 'open', 'color' => '#3B82F6', 'space_id' => $developmentSpace->id, 'position' => 1],
            ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $developmentSpace->id, 'position' => 2],
            ['name' => 'Review', 'type' => 'review', 'color' => '#8B5CF6', 'space_id' => $developmentSpace->id, 'position' => 3],
            ['name' => 'Done', 'type' => 'closed', 'color' => '#10B981', 'space_id' => $developmentSpace->id, 'position' => 4, 'is_closed' => true],
        ];

        $statusMap = [];
        foreach ($devStatuses as $status) {
            $statusMap[$status['name']] = Status::create($status);
        }

        // Create folder
        $sprint1Folder = Folder::create([
            'name' => 'Sprint 1',
            'space_id' => $developmentSpace->id,
            'position' => 0,
        ]);

        // Create list in folder
        $backendList = TaskList::create([
            'name' => 'Backend Tasks',
            'space_id' => $developmentSpace->id,
            'folder_id' => $sprint1Folder->id,
            'position' => 0,
        ]);

        // Create standalone list
        $frontendList = TaskList::create([
            'name' => 'Frontend Tasks',
            'space_id' => $developmentSpace->id,
            'position' => 1,
        ]);

        // Get priorities
        $urgentPriority = Priority::where('name', 'Urgent')->first();
        $highPriority = Priority::where('name', 'High')->first();
        $normalPriority = Priority::where('name', 'Normal')->first();

        // Get labels
        $bugLabel = Label::where('name', 'Bug')->first();
        $featureLabel = Label::where('name', 'Feature')->first();

        // Create tasks
        $tasks = [
            [
                'name' => 'Setup database migrations',
                'description' => 'Create all necessary database tables and relationships',
                'task_list_id' => $backendList->id,
                'status_id' => $statusMap['Done']->id,
                'priority_id' => $highPriority->id,
                'created_by' => $user->id,
                'position' => 0,
                'completed_at' => now(),
            ],
            [
                'name' => 'Implement user authentication',
                'description' => 'Setup login, register, and password reset functionality',
                'task_list_id' => $backendList->id,
                'status_id' => $statusMap['In Progress']->id,
                'priority_id' => $urgentPriority->id,
                'created_by' => $user->id,
                'position' => 1,
                'due_date' => now()->addDays(3),
            ],
            [
                'name' => 'Create API endpoints',
                'description' => 'Build RESTful API for all resources',
                'task_list_id' => $backendList->id,
                'status_id' => $statusMap['To Do']->id,
                'priority_id' => $normalPriority->id,
                'created_by' => $user->id,
                'position' => 2,
                'due_date' => now()->addDays(7),
            ],
            [
                'name' => 'Design dashboard layout',
                'description' => 'Create modern, responsive dashboard UI',
                'task_list_id' => $frontendList->id,
                'status_id' => $statusMap['Done']->id,
                'priority_id' => $highPriority->id,
                'created_by' => $teamMembers[1]->id,
                'position' => 0,
                'completed_at' => now()->subDays(2),
            ],
            [
                'name' => 'Implement drag and drop',
                'description' => 'Add drag and drop functionality for kanban board',
                'task_list_id' => $frontendList->id,
                'status_id' => $statusMap['In Progress']->id,
                'priority_id' => $normalPriority->id,
                'created_by' => $teamMembers[0]->id,
                'position' => 1,
            ],
            [
                'name' => 'Fix login page styling',
                'description' => 'Login button not aligned properly on mobile',
                'task_list_id' => $frontendList->id,
                'status_id' => $statusMap['Backlog']->id,
                'priority_id' => null,
                'created_by' => $teamMembers[2]->id,
                'position' => 2,
            ],
        ];

        foreach ($tasks as $taskData) {
            $task = Task::create($taskData);
            
            // Assign random team members
            $assignees = $teamMembers->random(rand(1, 2));
            foreach ($assignees as $assignee) {
                $task->assignees()->attach($assignee->id, [
                    'assigned_at' => now(),
                    'assigned_by' => $user->id,
                ]);
            }
            
            // Add labels to some tasks
            if (rand(0, 1)) {
                $task->labels()->attach(rand(0, 1) ? $bugLabel->id : $featureLabel->id);
            }
        }

        // Clear and create custom design space statuses
        $designSpace->statuses()->delete();
        
        $designStatuses = [
            ['name' => 'Ideas', 'type' => 'open', 'color' => '#6B7280', 'space_id' => $designSpace->id, 'position' => 0],
            ['name' => 'In Design', 'type' => 'in_progress', 'color' => '#EC4899', 'space_id' => $designSpace->id, 'position' => 1],
            ['name' => 'Feedback', 'type' => 'review', 'color' => '#F59E0B', 'space_id' => $designSpace->id, 'position' => 2],
            ['name' => 'Approved', 'type' => 'closed', 'color' => '#10B981', 'space_id' => $designSpace->id, 'position' => 3, 'is_closed' => true],
        ];

        foreach ($designStatuses as $status) {
            Status::create($status);
        }

        // Create design list
        $uiList = TaskList::create([
            'name' => 'UI Components',
            'space_id' => $designSpace->id,
            'position' => 0,
        ]);

        $this->command->info('Database seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('  Email: admin@example.com');
        $this->command->info('  Password: password');
    }
}
