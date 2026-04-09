<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Folder;
use App\Models\Sprint;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\View;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            WorkspacesSeeder::class,
            LabelsSeeder::class,
            SpacesSeeder::class,
            StatusesSeeder::class,
            FoldersSeeder::class,
            TaskListsSeeder::class,
            TasksSeeder::class,
            SubtasksSeeder::class,
            TimeEntriesSeeder::class,
            CommentsSeeder::class,
            ActivitiesSeeder::class,
            ViewsSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('=== Database Seeded Successfully ===');
        $this->command->info('');
        $this->command->info('Workspace:    MIS Department');
        $this->command->info('Users:        5 (IT Manager + 4 developers)');
        $this->command->info('Spaces:       3 (Manufacturing, B2B, B2C)');
        $this->command->info('Sprints:      ' . Sprint::count());
        $this->command->info('Folders:      ' . Folder::count());
        $this->command->info('Lists:        ' . TaskList::count());
        $this->command->info('Tasks:        ' . Task::count());
        $this->command->info('Subtasks:     ' . Subtask::count());
        $this->command->info('Time Entries: ' . TimeEntry::count());
        $this->command->info('Comments:     ' . Comment::count());
        $this->command->info('Activities:   ' . Activity::count());
        $this->command->info('Views:        ' . View::count());
        $this->command->info('');
        $this->command->info('Login (all password: "password"):');
        $this->command->info('  admin@example.com  — Sasya Rahma (IT Manager / Owner)');
        $this->command->info('  andi@example.com   — Andi Fullstack');
        $this->command->info('  dian@example.com   — Dian Frontend');
        $this->command->info('  budi@example.com   — Budi Backend');
        $this->command->info('  rina@example.com   — Rina QA');
        $this->command->info('');
        $this->command->info('CPM Demo: Open "Sistem Manajemen Inventory" task -> Gantt view');
    }
}
