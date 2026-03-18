<?php

namespace Database\Seeders;

use App\Models\Activity;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class ActivitiesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $workspace = $this->demoWorkspace();
        $sasya = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');
        $budi = $this->demoUser('budi@example.com');

        $activities = [
            [$sasya, $this->demoTask('Sistem Manajemen Inventory'), 'created', ['name' => 'Sistem Manajemen Inventory'], now()->subWeeks(4)],
            [$sasya, $this->demoTask('Sistem Manajemen Inventory'), 'assigned', ['name' => 'Sistem Manajemen Inventory', 'assignee' => 'Budi Backend'], now()->subWeeks(4)],
            [$budi, $this->demoTask('Portal Login & Multi-tenant'), 'created', ['name' => 'Portal Login & Multi-tenant'], now()->subWeeks(3)],
            [$budi, $this->demoTask('Portal Login & Multi-tenant'), 'completed', ['name' => 'Portal Login & Multi-tenant'], now()->subWeeks(2)],
            [$dian, $this->demoTask('Product Catalog Website'), 'created', ['name' => 'Product Catalog Website'], now()->subWeeks(2)->subDay()],
            [$budi, $this->demoTask('Checkout & Payment Gateway'), 'created', ['name' => 'Checkout & Payment Gateway'], now()->subWeeks(2)],
            [$andi, $this->demoTask('Real-time Production Monitoring'), 'created', ['name' => 'Real-time Production Monitoring'], now()->subDays(8)],
            [$andi, $this->demoTask('Real-time Production Monitoring'), 'updated', ['name' => 'Real-time Production Monitoring'], now()->subDays(5), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$andi, $this->demoTask('REST API untuk Partner'), 'created', ['name' => 'REST API untuk Partner'], now()->subDays(7)],
            [$andi, $this->demoTask('REST API untuk Partner'), 'updated', ['name' => 'REST API untuk Partner'], now()->subDays(5), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sasya, $this->demoTask('Order Management Portal'), 'created', ['name' => 'Order Management Portal'], now()->subDays(6)],
            [$dian, $this->demoTask('Product Catalog Website'), 'updated', ['name' => 'Product Catalog Website'], now()->subDays(4), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$budi, $this->demoTask('Checkout & Payment Gateway'), 'updated', ['name' => 'Checkout & Payment Gateway'], now()->subDays(3), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sasya, $this->demoTask('Invoice & Billing Otomatis'), 'created', ['name' => 'Invoice & Billing Otomatis'], now()->subDays(2)],
            [$andi, $this->demoTask('IoT Sensor Dashboard'), 'created', ['name' => 'IoT Sensor Dashboard'], now()->subDays(2)],
            [$dian, $this->demoTask('Android App E-Commerce'), 'created', ['name' => 'Android App E-Commerce'], now()->subDay()],
            [$sasya, $this->demoTask('Laporan Produksi Bulanan'), 'created', ['name' => 'Laporan Produksi Bulanan'], now()->subDay()],
        ];

        foreach ($activities as $activity) {
            Activity::create([
                'workspace_id' => $workspace->id,
                'user_id' => $activity[0]->id,
                'subject_type' => get_class($activity[1]),
                'subject_id' => $activity[1]->id,
                'action' => $activity[2],
                'properties' => $activity[3],
                'changes' => $activity[5] ?? null,
                'created_at' => $activity[4],
                'updated_at' => $activity[4],
            ]);
        }
    }
}
