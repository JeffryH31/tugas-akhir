<?php

namespace Database\Seeders;

use App\Models\TimeEntry;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class TimeEntriesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        foreach ([
            ['subtask' => 'Desain database inventory', 'user' => 'budi@example.com', 'minutes' => 160, 'started_at' => now()->subWeeks(4), 'description' => 'ER diagram inventory module', 'is_billable' => true],
            ['subtask' => 'API master barang (CRUD)', 'user' => 'budi@example.com', 'minutes' => 220, 'started_at' => now()->subWeeks(4)->addDay(), 'description' => 'CRUD endpoint + validation', 'is_billable' => true],
            ['subtask' => 'UI halaman master barang', 'user' => 'dian@example.com', 'minutes' => 280, 'started_at' => now()->subWeeks(3), 'description' => 'Vue table + form master barang', 'is_billable' => false],
            ['subtask' => 'API stok masuk & keluar', 'user' => 'budi@example.com', 'minutes' => 260, 'started_at' => now()->subWeeks(3), 'description' => 'Stok masuk/keluar controller', 'is_billable' => true],
            ['subtask' => 'API stok masuk & keluar', 'user' => 'budi@example.com', 'minutes' => 40, 'started_at' => now()->subWeeks(3)->addDays(2), 'description' => 'Fix edge case stok negatif', 'is_billable' => false],
            ['subtask' => 'UI form stok masuk/keluar', 'user' => 'dian@example.com', 'minutes' => 120, 'started_at' => now()->subDays(5), 'description' => 'Form stok masuk dengan autocomplete', 'is_billable' => false],
            ['subtask' => 'Fitur stock opname', 'user' => 'budi@example.com', 'minutes' => 90, 'started_at' => now()->subDays(3), 'description' => 'Stock opname backend logic', 'is_billable' => true],
            ['subtask' => 'Desain schema data produksi', 'user' => 'andi@example.com', 'minutes' => 100, 'started_at' => now()->subDays(8), 'description' => 'Schema mesin + shift + output', 'is_billable' => true],
            ['subtask' => 'API data output mesin per shift', 'user' => 'budi@example.com', 'minutes' => 210, 'started_at' => now()->subDays(6), 'description' => 'Endpoint produksi per shift', 'is_billable' => true],
            ['subtask' => 'Dashboard chart realtime (Vue)', 'user' => 'dian@example.com', 'minutes' => 150, 'started_at' => now()->subDays(3), 'description' => 'Chart.js integration', 'is_billable' => false],
            ['subtask' => 'Schema multi-tenant (tenant_id)', 'user' => 'budi@example.com', 'minutes' => 160, 'started_at' => now()->subWeeks(3), 'description' => 'Multi-tenant schema + migration', 'is_billable' => true],
            ['subtask' => 'Login API + JWT token', 'user' => 'budi@example.com', 'minutes' => 220, 'started_at' => now()->subWeeks(3)->addDay(), 'description' => 'JWT auth with Sanctum', 'is_billable' => true],
            ['subtask' => 'Role & permission middleware', 'user' => 'budi@example.com', 'minutes' => 170, 'started_at' => now()->subWeeks(2)->subDay(), 'description' => 'Spatie permission setup', 'is_billable' => true],
            ['subtask' => 'Login page UI (Vue)', 'user' => 'dian@example.com', 'minutes' => 160, 'started_at' => now()->subWeeks(2), 'description' => 'Login page + 2FA flow', 'is_billable' => false],
            ['subtask' => 'API order list + filter', 'user' => 'budi@example.com', 'minutes' => 170, 'started_at' => now()->subWeeks(2), 'description' => 'Laravel query builder + filter', 'is_billable' => true],
            ['subtask' => 'Order detail page', 'user' => 'dian@example.com', 'minutes' => 220, 'started_at' => now()->subWeeks(2)->addDay(), 'description' => 'Order detail with timeline', 'is_billable' => false],
            ['subtask' => 'Repeat order feature', 'user' => 'budi@example.com', 'minutes' => 60, 'started_at' => now()->subDays(2), 'description' => 'Started repeat order logic', 'is_billable' => true],
            ['subtask' => 'API key management', 'user' => 'andi@example.com', 'minutes' => 100, 'started_at' => now()->subDays(6), 'description' => 'API key hashing + rate limit', 'is_billable' => true],
            ['subtask' => 'Endpoint PO submission', 'user' => 'andi@example.com', 'minutes' => 210, 'started_at' => now()->subDays(5), 'description' => 'PO endpoint + validation', 'is_billable' => true],
            ['subtask' => 'Endpoint cek stok real-time', 'user' => 'andi@example.com', 'minutes' => 80, 'started_at' => now()->subDays(2), 'description' => 'Stok query optimization', 'is_billable' => true],
            ['subtask' => 'API product + kategori', 'user' => 'budi@example.com', 'minutes' => 220, 'started_at' => now()->subWeeks(2), 'description' => 'Product API + seeder', 'is_billable' => true],
            ['subtask' => 'Product listing page', 'user' => 'dian@example.com', 'minutes' => 270, 'started_at' => now()->subWeeks(2)->addDay(), 'description' => 'Product grid + responsive', 'is_billable' => false],
            ['subtask' => 'Search & filter komponen', 'user' => 'dian@example.com', 'minutes' => 100, 'started_at' => now()->subDays(3), 'description' => 'Algolia-style search component', 'is_billable' => false],
            ['subtask' => 'Shopping cart (session-based)', 'user' => 'budi@example.com', 'minutes' => 210, 'started_at' => now()->subWeeks(2), 'description' => 'Cart logic + session store', 'is_billable' => true],
            ['subtask' => 'Shipping cost (RajaOngkir API)', 'user' => 'budi@example.com', 'minutes' => 160, 'started_at' => now()->subWeeks(2)->addDay(), 'description' => 'RajaOngkir JNE/JNT/SiCepat', 'is_billable' => true],
            ['subtask' => 'Midtrans payment integration', 'user' => 'budi@example.com', 'minutes' => 180, 'started_at' => now()->subDays(4), 'description' => 'Midtrans Snap integration', 'is_billable' => true],
            ['subtask' => 'Checkout UI multi-step form', 'user' => 'dian@example.com', 'minutes' => 150, 'started_at' => now()->subDays(3), 'description' => 'Multi-step checkout form', 'is_billable' => false],
        ] as $entry) {
            $startedAt = $entry['started_at'];

            TimeEntry::create([
                'subtask_id' => $this->demoSubtask($entry['subtask'])->id,
                'user_id' => $this->demoUser($entry['user'])->id,
                'duration' => $entry['minutes'],
                'description' => $entry['description'],
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addMinutes($entry['minutes']),
                'is_billable' => $entry['is_billable'],
                'is_running' => false,
            ]);
        }
    }
}
