<?php

namespace Database\Seeders;

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
