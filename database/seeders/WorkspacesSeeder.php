<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class WorkspacesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sasya = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('kevin@example.com');
        $dian = $this->demoUser('christopher@example.com');
        $budi = $this->demoUser('marvel@example.com');
        $rina = $this->demoUser('devin@example.com');

        $workspace = Workspace::create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'color' => '#6366F1',
        ]);

        $workspace->addMember($sasya, 'owner');
        $workspace->addMember($andi, 'admin');
        $workspace->addMember($dian, 'member');
        $workspace->addMember($budi, 'member');
        $workspace->addMember($rina, 'member');
    }
}
