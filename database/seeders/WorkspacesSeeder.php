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
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');
        $budi = $this->demoUser('budi@example.com');
        $rina = $this->demoUser('rina@example.com');

        $workspace = Workspace::create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'owner_id' => $sasya->id,
            'color' => '#6366F1',
        ]);

        $workspace->addMember($andi, 'admin');
        $workspace->addMember($dian, 'member');
        $workspace->addMember($budi, 'member');
        $workspace->addMember($rina, 'member');
    }
}
