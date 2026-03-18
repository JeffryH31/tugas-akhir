<?php

namespace Database\Seeders;

use App\Models\Folder;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class FoldersSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sasya = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');

        foreach ([
            ['name' => 'ERP System', 'space' => 'Manufacturing', 'position' => 0, 'created_by' => $sasya->id],
            ['name' => 'IoT & Monitoring', 'space' => 'Manufacturing', 'position' => 1, 'created_by' => $andi->id],
            ['name' => 'Client Portal', 'space' => 'B2B', 'position' => 0, 'created_by' => $sasya->id],
            ['name' => 'E-Commerce', 'space' => 'B2C', 'position' => 0, 'created_by' => $sasya->id],
            ['name' => 'Mobile App', 'space' => 'B2C', 'position' => 1, 'created_by' => $dian->id],
        ] as $folder) {
            Folder::create([
                'name' => $folder['name'],
                'space_id' => $this->demoSpace($folder['space'])->id,
                'position' => $folder['position'],
                'created_by' => $folder['created_by'],
            ]);
        }
    }
}
