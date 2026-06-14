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
    }
}
