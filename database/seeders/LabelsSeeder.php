<?php

namespace Database\Seeders;

use App\Models\Label;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class LabelsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $workspace = $this->demoWorkspace();

        foreach ([
            ['name' => 'Bug', 'color' => '#FF6B6B'],
            ['name' => 'Feature', 'color' => '#6BC950'],
            ['name' => 'Enhancement', 'color' => '#49CCF9'],
            ['name' => 'Documentation', 'color' => '#8B5CF6'],
            ['name' => 'UI/UX', 'color' => '#EC4899'],
            ['name' => 'Refactor', 'color' => '#F59E0B'],
            ['name' => 'Security', 'color' => '#EF4444'],
            ['name' => 'Performance', 'color' => '#14B8A6'],
        ] as $label) {
            Label::create([
                'workspace_id' => $workspace->id,
                'name' => $label['name'],
                'color' => $label['color'],
            ]);
        }
    }
}
