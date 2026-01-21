<?php

namespace Database\Seeders;

use App\Models\TaskList;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Seed Project structure demo data
     */
    public function run(): void
    {
        // Get first workspace or create one
        $workspace = Workspace::first();
        $user = User::first();

        if (!$workspace || !$user) {
            $this->command->warn('Please run UserSeeder first to create users and workspaces.');
            return;
        }

        // Create Spaces
        $developmentSpace = Space::create([
            'name' => 'Development',
            'description' => 'Software development projects',
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'color' => '#6366F1',
            'is_private' => false,
            'position' => 0,
            'features' => json_encode([
                'time_tracking' => true,
                'tags' => true,
                'priorities' => true,
                'custom_fields' => true,
                'multiple_assignees' => true,
            ]),
        ]);

        // Attach user to space
        $developmentSpace->members()->attach($user->id, ['role' => 'admin']);

        $marketingSpace = Space::create([
            'name' => 'Marketing',
            'description' => 'Marketing campaigns and content',
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'color' => '#10B981',
            'is_private' => false,
            'position' => 1,
        ]);
        $marketingSpace->members()->attach($user->id, ['role' => 'admin']);

        // Create Folders in Development Space
        $backendFolder = Folder::create([
            'name' => 'Backend',
            'space_id' => $developmentSpace->id,
            'position' => 0,
            'color' => '#EF4444',
        ]);

        $frontendFolder = Folder::create([
            'name' => 'Frontend',
            'space_id' => $developmentSpace->id,
            'position' => 1,
            'color' => '#3B82F6',
        ]);

        // Create Lists in Backend Folder
        $apiList = TaskList::create([
            'name' => 'API Development',
            'description' => 'REST API endpoints',
            'space_id' => $developmentSpace->id,
            'folder_id' => $backendFolder->id,
            'created_by' => $user->id,
            'color' => '#EF4444',
            'position' => 0,
        ]);
        $this->createDefaultStatuses($apiList);

        $databaseList = TaskList::create([
            'name' => 'Database',
            'description' => 'Database migrations and models',
            'space_id' => $developmentSpace->id,
            'folder_id' => $backendFolder->id,
            'created_by' => $user->id,
            'color' => '#F59E0B',
            'position' => 1,
        ]);
        $this->createDefaultStatuses($databaseList);

        // Create Lists in Frontend Folder
        $uiList = TaskList::create([
            'name' => 'UI Components',
            'description' => 'Vue.js components',
            'space_id' => $developmentSpace->id,
            'folder_id' => $frontendFolder->id,
            'created_by' => $user->id,
            'color' => '#3B82F6',
            'position' => 0,
        ]);
        $this->createDefaultStatuses($uiList);

        // Create List without folder (directly in space)
        $bugsList = TaskList::create([
            'name' => 'Bug Reports',
            'description' => 'Bug tracking and fixes',
            'space_id' => $developmentSpace->id,
            'folder_id' => null,
            'created_by' => $user->id,
            'color' => '#DC2626',
            'position' => 0,
        ]);
        $this->createDefaultStatuses($bugsList);

        // Create sample tasks
        $this->createSampleTasks($apiList, $user);
        $this->createSampleTasks($databaseList, $user);
        $this->createSampleTasks($uiList, $user);
        $this->createSampleTasks($bugsList, $user);

        $this->command->info('Project structure seeded successfully!');
        $this->command->info("Created: 2 Spaces, 2 Folders, 4 Lists with statuses and tasks");
    }

    /**
     * Create default statuses for a list
     */
    private function createDefaultStatuses(TaskList $list): void
    {
        $statuses = [
            ['name' => 'To Do', 'color' => '#6B7280', 'type' => 'open', 'position' => 0, 'is_default' => true],
            ['name' => 'In Progress', 'color' => '#3B82F6', 'type' => 'in_progress', 'position' => 1],
            ['name' => 'Review', 'color' => '#F59E0B', 'type' => 'in_progress', 'position' => 2],
            ['name' => 'Done', 'color' => '#10B981', 'type' => 'closed', 'position' => 3],
        ];

        foreach ($statuses as $status) {
            Status::create(array_merge($status, ['list_id' => $list->id]));
        }
    }

    /**
     * Create sample tasks for a list
     */
    private function createSampleTasks(TaskList $list, User $user): void
    {
        $todoStatus = $list->statuses()->where('type', 'open')->first();
        $inProgressStatus = $list->statuses()->where('type', 'in_progress')->first();

        if (!$todoStatus) return;

        // Create 3 sample tasks
        $tasks = [
            [
                'title' => "Setup {$list->name}",
                'description' => "Initial setup for {$list->name}",
                'status_id' => $todoStatus->id,
                'priority' => 'high',
                'position' => 0,
            ],
            [
                'title' => "Implement core features",
                'description' => "Implement core functionality for {$list->name}",
                'status_id' => $inProgressStatus?->id ?? $todoStatus->id,
                'priority' => 'normal',
                'position' => 1,
            ],
            [
                'title' => "Write documentation",
                'description' => "Document all features in {$list->name}",
                'status_id' => $todoStatus->id,
                'priority' => 'low',
                'position' => 2,
            ],
        ];

        foreach ($tasks as $taskData) {
            $task = Task::create([
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'list_id' => $list->id,
                'status_id' => $taskData['status_id'],
                'priority' => $taskData['priority'],
                'position' => $taskData['position'],
                'created_by' => $user->id,
            ]);

            // Assign user to task
            $task->assignees()->attach($user->id);
        }
    }
}
