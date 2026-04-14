<?php

namespace Database\Seeders;

use App\Models\Task;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class TasksSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $admin = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');
        $budi = $this->demoUser('budi@example.com');

        $this->createTask([
            'name' => 'Sistem Manajemen Inventory',
            'description' => 'Membangun modul inventory lengkap: master barang, stok masuk/keluar, stock opname, dan laporan inventory',
            'list' => 'Inventory Module',
            'status' => ['Manufacturing', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $admin->id,
            'position' => 0,
            'assignees' => [$budi->id, $dian->id],
            'labels' => ['Feature'],
        ]);

        $this->createTask([
            'name' => 'Real-time Production Monitoring',
            'description' => 'Dashboard monitoring produksi real-time dengan data mesin dan output per shift',
            'list' => 'Production Tracking',
            'status' => ['Manufacturing', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $andi->id,
            'position' => 0,
            'assignees' => [$andi->id, $dian->id],
            'labels' => ['Feature', 'Performance'],
        ]);

        $this->createTask([
            'name' => 'IoT Sensor Dashboard',
            'description' => 'Dashboard monitoring sensor suhu, kelembaban, dan getaran mesin pabrik',
            'list' => 'Sensor Dashboard',
            'status' => ['Manufacturing', 'To Do'],
            'priority_level' => 3,
            'created_by' => $andi->id,
            'position' => 0,
            'assignees' => [$andi->id],
            'labels' => ['Feature'],
        ]);

        $this->createTask([
            'name' => 'Laporan Produksi Bulanan',
            'description' => 'Generate laporan produksi bulanan otomatis dengan export PDF dan Excel',
            'list' => 'Reporting',
            'status' => ['Manufacturing', 'Backlog'],
            'priority_level' => 3,
            'created_by' => $admin->id,
            'position' => 0,
            'assignees' => [],
            'labels' => ['Feature', 'Documentation'],
        ]);

        $this->createTask([
            'name' => 'Portal Login & Multi-tenant',
            'description' => 'Sistem login multi-tenant untuk client B2B dengan role-based access control',
            'list' => 'Authentication & Access',
            'status' => ['B2B', 'Done'],
            'priority_level' => 1,
            'created_by' => $budi->id,
            'position' => 0,
            'assignees' => [$budi->id],
            'labels' => ['Security', 'Feature'],
        ]);

        $this->createTask([
            'name' => 'Order Management Portal',
            'description' => 'Client bisa melihat order history, tracking status, dan repeat order',
            'list' => 'Order Management',
            'status' => ['B2B', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $admin->id,
            'position' => 0,
            'assignees' => [$budi->id, $dian->id],
            'labels' => ['Feature'],
        ]);

        $this->createTask([
            'name' => 'REST API untuk Partner',
            'description' => 'Public API agar partner B2B bisa kirim PO, cek stok, dan terima invoice secara otomatis',
            'list' => 'API Integrations',
            'status' => ['B2B', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $andi->id,
            'position' => 0,
            'assignees' => [$andi->id],
            'labels' => ['Feature', 'Security'],
        ]);

        $this->createTask([
            'name' => 'Invoice & Billing Otomatis',
            'description' => 'Generate invoice otomatis dari PO, kirim via email, dan tracking pembayaran',
            'list' => 'Invoice System',
            'status' => ['B2B', 'To Do'],
            'priority_level' => 3,
            'created_by' => $admin->id,
            'position' => 0,
            'assignees' => [$budi->id],
            'labels' => [],
        ]);

        $this->createTask([
            'name' => 'Product Catalog Website',
            'description' => 'Halaman katalog produk dengan search, filter kategori, dan detail produk',
            'list' => 'Product Catalog',
            'status' => ['B2C', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $dian->id,
            'position' => 0,
            'assignees' => [$dian->id, $budi->id],
            'labels' => ['Feature', 'UI/UX'],
        ]);

        $this->createTask([
            'name' => 'Checkout & Payment Gateway',
            'description' => 'Alur checkout: keranjang → shipping → pembayaran Midtrans → konfirmasi',
            'list' => 'Checkout & Payment',
            'status' => ['B2C', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $budi->id,
            'position' => 0,
            'assignees' => [$budi->id, $dian->id],
            'labels' => ['Feature', 'Security'],
        ]);

        $this->createTask([
            'name' => 'Android App E-Commerce',
            'description' => 'Aplikasi Android untuk browse produk, checkout, dan tracking order',
            'list' => 'Android App',
            'status' => ['B2C', 'To Do'],
            'priority_level' => 3,
            'created_by' => $dian->id,
            'position' => 0,
            'assignees' => [$dian->id],
            'labels' => ['Feature', 'UI/UX'],
        ]);

        $this->createTask([
            'name' => 'Live Chat Customer Support',
            'description' => 'Fitur live chat untuk customer dengan auto-assign ke agent CS',
            'list' => 'Customer Support System',
            'status' => ['B2C', 'To Do'],
            'priority_level' => 3,
            'created_by' => $andi->id,
            'position' => 0,
            'assignees' => [$andi->id],
            'labels' => ['Feature'],
        ]);
    }

    private function createTask(array $definition): void
    {
        $task = Task::create([
            'name' => $definition['name'],
            'description' => $definition['description'],
            'task_list_id' => $this->demoTaskList($definition['list'])->id,
            'status_id' => $this->demoStatus($definition['status'][0], $definition['status'][1])->id,
            'priority_level' => $definition['priority_level'],
            'created_by' => $definition['created_by'],
            'position' => $definition['position'],
        ]);

        foreach ($definition['assignees'] as $userId) {
            $task->assignees()->attach([$userId => ['assigned_by' => $definition['created_by']]]);
        }

        if ($definition['labels'] !== []) {
            $task->labels()->attach(
                collect($definition['labels'])
                    ->map(fn(string $labelName) => $this->demoLabel($labelName)->id)
                    ->all()
            );
        }
    }
}
