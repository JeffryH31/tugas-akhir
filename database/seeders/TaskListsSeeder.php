<?php

namespace Database\Seeders;

use App\Models\TaskList;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class TaskListsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sasya = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');
        $budi = $this->demoUser('budi@example.com');
        $rina = $this->demoUser('rina@example.com');

        $lists = [
            ['name' => 'Inventory Module', 'space' => 'Manufacturing', 'folder' => 'ERP System', 'position' => 0, 'created_by' => $budi->id, 'status' => ['Manufacturing', 'In Progress'], 'members' => [[$budi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team'], [$rina, 'development_team']]],
            ['name' => 'Production Tracking', 'space' => 'Manufacturing', 'folder' => 'ERP System', 'position' => 1, 'created_by' => $budi->id, 'status' => ['Manufacturing', 'In Progress'], 'members' => [[$budi, 'project_owner'], [$andi, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'Sensor Dashboard', 'space' => 'Manufacturing', 'folder' => 'IoT & Monitoring', 'position' => 0, 'created_by' => $andi->id, 'status' => ['Manufacturing', 'To Do'], 'members' => [[$andi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'Reporting', 'space' => 'Manufacturing', 'folder' => null, 'position' => 2, 'created_by' => $sasya->id, 'status' => ['Manufacturing', 'Backlog'], 'members' => [[$sasya, 'project_owner'], [$budi, 'development_team'], [$rina, 'development_team']]],
            ['name' => 'Authentication & Access', 'space' => 'B2B', 'folder' => 'Client Portal', 'position' => 0, 'created_by' => $budi->id, 'status' => ['B2B', 'Done'], 'members' => [[$budi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'Order Management', 'space' => 'B2B', 'folder' => 'Client Portal', 'position' => 1, 'created_by' => $budi->id, 'status' => ['B2B', 'In Progress'], 'members' => [[$budi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'API Integrations', 'space' => 'B2B', 'folder' => null, 'position' => 1, 'created_by' => $andi->id, 'status' => ['B2B', 'In Progress'], 'members' => [[$andi, 'project_owner'], [$sasya, 'project_manager']]],
            ['name' => 'Invoice System', 'space' => 'B2B', 'folder' => null, 'position' => 2, 'created_by' => $budi->id, 'status' => ['B2B', 'To Do'], 'members' => [[$budi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'Product Catalog', 'space' => 'B2C', 'folder' => 'E-Commerce', 'position' => 0, 'created_by' => $dian->id, 'status' => ['B2C', 'In Progress'], 'members' => [[$dian, 'project_owner'], [$sasya, 'project_manager'], [$budi, 'development_team']]],
            ['name' => 'Checkout & Payment', 'space' => 'B2C', 'folder' => 'E-Commerce', 'position' => 1, 'created_by' => $budi->id, 'status' => ['B2C', 'In Progress'], 'members' => [[$budi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
            ['name' => 'Android App', 'space' => 'B2C', 'folder' => 'Mobile App', 'position' => 0, 'created_by' => $dian->id, 'status' => ['B2C', 'To Do'], 'members' => [[$dian, 'project_owner'], [$sasya, 'project_manager']]],
            ['name' => 'Customer Support System', 'space' => 'B2C', 'folder' => null, 'position' => 2, 'created_by' => $andi->id, 'status' => ['B2C', 'To Do'], 'members' => [[$andi, 'project_owner'], [$sasya, 'project_manager'], [$dian, 'development_team']]],
        ];

        foreach ($lists as $definition) {
            $list = TaskList::create([
                'name' => $definition['name'],
                'space_id' => $this->demoSpace($definition['space'])->id,
                'folder_id' => $definition['folder'] ? $this->demoFolder($definition['folder'])->id : null,
                'position' => $definition['position'],
                'created_by' => $definition['created_by'],
                'status_id' => $this->demoStatus($definition['status'][0], $definition['status'][1])->id,
            ]);

            foreach ($definition['members'] as [$user, $role]) {
                $list->addMember($user, $role);
            }
        }
    }
}
