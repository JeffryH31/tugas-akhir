<?php

namespace Database\Seeders;

use App\Models\Comment;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class CommentsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sasya = $this->demoUser('admin@example.com');
        $andi = $this->demoUser('andi@example.com');
        $dian = $this->demoUser('dian@example.com');
        $budi = $this->demoUser('budi@example.com');
        $rina = $this->demoUser('rina@example.com');

        $invTask = $this->demoTask('Sistem Manajemen Inventory');
        $prodTask = $this->demoTask('Real-time Production Monitoring');
        $apiIntTask = $this->demoTask('REST API untuk Partner');
        $checkoutTask = $this->demoTask('Checkout & Payment Gateway');
        $orderTask = $this->demoTask('Order Management Portal');
        $catalogTask = $this->demoTask('Product Catalog Website');

        $c1 = Comment::create(['task_id' => $invTask->id, 'user_id' => $sasya->id, 'content' => 'Modul inventory ini prioritas utama ya. Tim gudang sudah nanya kapan bisa pakai sistem baru.', 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $budi->id, 'parent_id' => $c1->id, 'content' => 'Siap, API master barang sudah selesai. Sekarang lagi kerjain stok masuk/keluar.', 'created_at' => now()->subDays(7)->addHours(2), 'updated_at' => now()->subDays(7)->addHours(2)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $dian->id, 'parent_id' => $c1->id, 'content' => 'UI master barang sudah live di staging. Minta tolong review ya Kak Sasya.', 'created_at' => now()->subDays(7)->addHours(4), 'updated_at' => now()->subDays(7)->addHours(4)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $rina->id, 'content' => 'Untuk stock opname, pastikan ada audit trail siapa yang adjust stoknya ya.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);

        $c2 = Comment::create(['task_id' => $prodTask->id, 'user_id' => $andi->id, 'content' => 'Dashboard chart pakai Chart.js atau ApexCharts? Mau yang support real-time update.', 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]);
        Comment::create(['task_id' => $prodTask->id, 'user_id' => $dian->id, 'parent_id' => $c2->id, 'content' => 'Mendingan ApexCharts, lebih enak buat real-time streaming dan confignya lebih simpel.', 'created_at' => now()->subDays(4)->addHours(1), 'updated_at' => now()->subDays(4)->addHours(1)]);

        Comment::create(['task_id' => $apiIntTask->id, 'user_id' => $sasya->id, 'content' => 'API docs harus ready sebelum demo ke client PT Maju Bersama minggu depan.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        Comment::create(['task_id' => $apiIntTask->id, 'user_id' => $andi->id, 'content' => 'Swagger sudah auto-generate dari annotation. Tinggal polish contoh request/response-nya.', 'created_at' => now()->subDays(3)->addHours(2), 'updated_at' => now()->subDays(3)->addHours(2)]);

        $c3 = Comment::create(['task_id' => $checkoutTask->id, 'user_id' => $budi->id, 'content' => 'Midtrans sandbox sudah bisa test. Perlu API key production dari tim finance.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        Comment::create(['task_id' => $checkoutTask->id, 'user_id' => $sasya->id, 'parent_id' => $c3->id, 'content' => 'Sudah minta ke Finance, estimasi dapet key-nya besok. Sementara pakai sandbox dulu.', 'created_at' => now()->subDays(2)->addHours(3), 'updated_at' => now()->subDays(2)->addHours(3)]);

        Comment::create(['task_id' => $orderTask->id, 'user_id' => $dian->id, 'content' => 'Order detail page sudah include timeline status perubahan. Cek di staging ya.', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);
        Comment::create(['task_id' => $catalogTask->id, 'user_id' => $dian->id, 'content' => 'Search component pakai debounce 300ms supaya nggak spam API. Sudah smooth.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
    }
}
