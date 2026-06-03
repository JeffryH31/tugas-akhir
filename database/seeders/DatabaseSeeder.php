<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\View;
use App\Models\Activity;
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
            ProjectsSeeder::class,
            SprintsSeeder::class,
            TasksSeeder::class,
            SubtasksSeeder::class,
            TimeEntriesSeeder::class,
            CommentsSeeder::class,
            ActivitiesSeeder::class,
            ViewsSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('╔══════════════════════════════════════════════════════╗');
        $this->command->info('║        Database Seeded Successfully                  ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  Workspace : MIS Department                         ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  Users:    ' . str_pad(User::count(), 5) . '  (Jeff, Kevin, Christopher, Marvel, Devin) ║');
        $this->command->info('║  Spaces:   3     (Manufacturing, B2B, B2C)          ║');
        $this->command->info('║  Sprints:  ' . str_pad(Sprint::count(), 5) . '                                          ║');
        $this->command->info('║  Folders:  ' . str_pad(Folder::count(), 5) . '                                          ║');
        $this->command->info('║  Projects: ' . str_pad(Project::count(), 5) . '                                          ║');
        $this->command->info('║  Tasks:    ' . str_pad(Task::count(), 5) . '                                          ║');
        $this->command->info('║  Subtasks: ' . str_pad(Subtask::count(), 5) . '                                          ║');
        $this->command->info('║  TimeLog:  ' . str_pad(TimeEntry::count(), 5) . '  entries                               ║');
        $this->command->info('║  Comments: ' . str_pad(Comment::count(), 5) . '                                          ║');
        $this->command->info('║  Activities:' . str_pad(Activity::count(), 4) . '                                          ║');
        $this->command->info('║  Views:    ' . str_pad(View::count(), 5) . '                                          ║');
        $this->command->info('╠══════════════════════════════════════════════════════╣');
        $this->command->info('║  LOGIN (all password: "password")                   ║');
        $this->command->info('║  admin@example.com       → Jeff (Owner)             ║');
        $this->command->info('║  kevin@example.com       → Kevin (Admin)            ║');
        $this->command->info('║  christopher@example.com → Christopher (Member)     ║');
        $this->command->info('║  marvel@example.com      → Marvel (Member)          ║');
        $this->command->info('║  devin@example.com       → Devin (Member)           ║');
        $this->command->info('╚══════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
