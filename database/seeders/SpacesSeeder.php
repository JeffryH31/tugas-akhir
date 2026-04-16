<?php

namespace Database\Seeders;

use App\Models\Space;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SpacesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $workspace = $this->demoWorkspace();
        $sasya = $this->demoUser('admin@example.com');

        foreach (
            [
                ['name' => 'Manufacturing', 'color' => '#F97316', 'position' => 0],
                ['name' => 'B2B', 'color' => '#3B82F6', 'position' => 1],
                ['name' => 'B2C', 'color' => '#10B981', 'position' => 2],
            ] as $space
        ) {
            Space::create([
                'workspace_id' => $workspace->id,
                'name' => $space['name'],
                'color' => $space['color'],
                'position' => $space['position'],
                'created_by' => $sasya->id,
            ]);
        }
    }
}
