<?php

namespace Database\Seeders;

use App\Models\Sprint;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SprintsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        foreach (
            [
                ['space' => 'Manufacturing', 'list' => 'Inventory Module', 'name' => 'MFG Sprint 1 — ERP Foundation', 'goal' => 'Setup modul dasar ERP: master data, inventory core', 'start_date' => now()->subWeeks(4), 'end_date' => now()->subWeeks(2), 'is_active' => false, 'position' => 0],
                ['space' => 'Manufacturing', 'list' => 'Production Tracking', 'name' => 'MFG Sprint 2 — Production Tracking', 'goal' => 'Build real-time production monitoring dan dashboard IoT', 'start_date' => now()->subWeeks(2), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1],
                ['space' => 'Manufacturing', 'list' => 'Reporting', 'name' => 'MFG Sprint 3 — Reporting & Analytics', 'goal' => 'Laporan produksi, analisis efisiensi, export PDF/Excel', 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(3), 'is_active' => false, 'position' => 2],
                ['space' => 'B2B', 'list' => 'Authentication & Access', 'name' => 'B2B Sprint 1 — Client Portal', 'goal' => 'Portal login, order history, dan invoice management', 'start_date' => now()->subWeeks(3), 'end_date' => now()->subWeek(), 'is_active' => false, 'position' => 0],
                ['space' => 'B2B', 'list' => 'API Integrations', 'name' => 'B2B Sprint 2 — API Integration', 'goal' => 'REST API untuk partner, webhook notifikasi, API docs', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1],
                ['space' => 'B2C', 'list' => 'Product Catalog', 'name' => 'B2C Sprint 1 — E-Commerce Core', 'goal' => 'Product catalog, keranjang belanja, checkout flow', 'start_date' => now()->subWeeks(3), 'end_date' => now()->subWeek(), 'is_active' => false, 'position' => 0],
                ['space' => 'B2C', 'list' => 'Checkout & Payment', 'name' => 'B2C Sprint 2 — Payment & Shipping', 'goal' => 'Integrasi Midtrans, ongkir RajaOngkir, notif email', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1],
            ] as $sprint
        ) {
            Sprint::create([
                'space_id' => $this->demoSpace($sprint['space'])->id,
                'task_list_id' => $this->demoTaskList($sprint['list'])->id,
                'name' => $sprint['name'],
                'goal' => $sprint['goal'],
                'start_date' => $sprint['start_date'],
                'end_date' => $sprint['end_date'],
                'is_active' => $sprint['is_active'],
                'position' => $sprint['position'],
            ]);
        }
    }
}
