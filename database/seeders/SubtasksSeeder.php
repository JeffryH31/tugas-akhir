<?php

namespace Database\Seeders;

use App\Models\Subtask;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SubtasksSeeder extends Seeder
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
        $iS1 = $this->createSubtask(['name' => 'Desain database inventory', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 1 — ERP Foundation')->id, 'start_date' => now()->subWeeks(4), 'due_date' => now()->subWeeks(4)->addDay(), 'completed_at' => now()->subWeeks(4)->addDay()], [$budi->id], ['Documentation']);
        $iS2 = $this->createSubtask(['name' => 'API master barang (CRUD)', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 1 — ERP Foundation')->id, 'start_date' => now()->subWeeks(4)->addDay(), 'due_date' => now()->subWeeks(4)->addDays(3), 'completed_at' => now()->subWeeks(4)->addDays(3)], [$budi->id]);
        $iS3 = $this->createSubtask(['name' => 'UI halaman master barang', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 2, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('MFG Sprint 1 — ERP Foundation')->id, 'start_date' => now()->subWeeks(3), 'due_date' => now()->subWeeks(3)->addDays(3), 'completed_at' => now()->subWeeks(3)->addDays(3)], [$dian->id], ['UI/UX']);
        $iS4 = $this->createSubtask(['name' => 'API stok masuk & keluar', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 1, 'time_estimate' => 300, 'position' => 3, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 1 — ERP Foundation')->id, 'start_date' => now()->subWeeks(3), 'due_date' => now()->subWeeks(3)->addDays(3), 'completed_at' => now()->subWeeks(3)->addDays(2)], [$budi->id]);
        $iS5 = $this->createSubtask(['name' => 'UI form stok masuk/keluar', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'In Progress')->id, 'priority_level' => 3, 'time_estimate' => 240, 'position' => 4, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'start_date' => now()->subDays(5), 'due_date' => now()->addDay()], [$dian->id], ['UI/UX']);
        $iS6 = $this->createSubtask(['name' => 'Fitur stock opname', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'In Progress')->id, 'priority_level' => 3, 'time_estimate' => 360, 'position' => 5, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(3)], [$budi->id, $andi->id]);
        $iS7 = $this->createSubtask(['name' => 'Barcode/QR code scanner', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 6, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'due_date' => now()->addDays(5)], [$dian->id], ['Feature']);
        $iS8 = $this->createSubtask(['name' => 'Laporan stok (PDF/Excel)', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 240, 'position' => 7, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id, 'due_date' => now()->addWeeks(2)], [$budi->id]);
        $iS9 = $this->createSubtask(['name' => 'Integration testing inventory', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 8, 'created_by' => $rina->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id, 'due_date' => now()->addWeeks(2)->addDays(3)], [$rina->id], ['Security']);
        $iS10 = $this->createSubtask(['name' => 'Deploy modul inventory ke production', 'task_id' => $invTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 1, 'time_estimate' => 60, 'position' => 9, 'created_by' => $sasya->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id, 'due_date' => now()->addWeeks(3)], [$sasya->id, $budi->id]);
        $iS2->dependencies()->attach($iS1->id, ['dependency_type' => 'blocks']);
        $iS3->dependencies()->attach($iS2->id, ['dependency_type' => 'blocks']);
        $iS4->dependencies()->attach($iS1->id, ['dependency_type' => 'blocks']);
        $iS5->dependencies()->attach($iS4->id, ['dependency_type' => 'blocks']);
        $iS5->dependencies()->attach($iS3->id, ['dependency_type' => 'blocks']);
        $iS6->dependencies()->attach($iS4->id, ['dependency_type' => 'blocks']);
        $iS7->dependencies()->attach($iS5->id, ['dependency_type' => 'blocks']);
        $iS8->dependencies()->attach($iS6->id, ['dependency_type' => 'blocks']);
        $iS8->dependencies()->attach($iS7->id, ['dependency_type' => 'blocks']);
        $iS9->dependencies()->attach($iS8->id, ['dependency_type' => 'blocks']);
        $iS10->dependencies()->attach($iS9->id, ['dependency_type' => 'blocks']);

        $prodTask = $this->demoTask('Real-time Production Monitoring');
        $pS1 = $this->createSubtask(['name' => 'Desain schema data produksi', 'task_id' => $prodTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 120, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'completed_at' => now()->subDays(7)], [$andi->id]);
        $pS2 = $this->createSubtask(['name' => 'API data output mesin per shift', 'task_id' => $prodTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'completed_at' => now()->subDays(4)], [$budi->id]);
        $pS3 = $this->createSubtask(['name' => 'Dashboard chart realtime (Vue)', 'task_id' => $prodTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'In Progress')->id, 'priority_level' => 1, 'time_estimate' => 360, 'position' => 2, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(3)], [$dian->id]);
        $pS4 = $this->createSubtask(['name' => 'Notifikasi downtime mesin', 'task_id' => $prodTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 3, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id, 'due_date' => now()->addDays(5)], [$andi->id]);
        $pS2->dependencies()->attach($pS1->id, ['dependency_type' => 'blocks']);
        $pS3->dependencies()->attach($pS2->id, ['dependency_type' => 'blocks']);
        $pS4->dependencies()->attach($pS2->id, ['dependency_type' => 'blocks']);

        $sensorTask = $this->demoTask('IoT Sensor Dashboard');
        $this->createSubtask(['name' => 'API endpoint sensor data (MQTT bridge)', 'task_id' => $sensorTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'To Do')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id]);
        $this->createSubtask(['name' => 'Gauge & line chart komponen', 'task_id' => $sensorTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('MFG Sprint 2 — Production Tracking')->id]);
        $this->createSubtask(['name' => 'Alert threshold config', 'task_id' => $sensorTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 120, 'position' => 2, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id]);

        $mfgRepTask = $this->demoTask('Laporan Produksi Bulanan');
        $this->createSubtask(['name' => 'Template laporan (Blade)', 'task_id' => $mfgRepTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id]);
        $this->createSubtask(['name' => 'Export PDF (DomPDF)', 'task_id' => $mfgRepTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 120, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id]);
        $this->createSubtask(['name' => 'Export Excel (Maatwebsite)', 'task_id' => $mfgRepTask->id, 'status_id' => $this->demoStatus('Manufacturing', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 90, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('MFG Sprint 3 — Reporting & Analytics')->id]);

        $b2bAuthTask = $this->demoTask('Portal Login & Multi-tenant');
        $ba1 = $this->createSubtask(['name' => 'Schema multi-tenant (tenant_id)', 'task_id' => $b2bAuthTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 1, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(3)]);
        $ba2 = $this->createSubtask(['name' => 'Login API + JWT token', 'task_id' => $b2bAuthTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 1, 'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(3)->addDays(2)]);
        $ba3 = $this->createSubtask(['name' => 'Role & permission middleware', 'task_id' => $b2bAuthTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(2)]);
        $ba4 = $this->createSubtask(['name' => 'Login page UI (Vue)', 'task_id' => $b2bAuthTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(2)->addDay()]);
        $ba2->dependencies()->attach($ba1->id, ['dependency_type' => 'blocks']);
        $ba3->dependencies()->attach($ba2->id, ['dependency_type' => 'blocks']);
        $ba4->dependencies()->attach($ba2->id, ['dependency_type' => 'blocks']);

        $orderTask = $this->demoTask('Order Management Portal');
        $oS1 = $this->createSubtask(['name' => 'API order list + filter', 'task_id' => $orderTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(2)]);
        $oS2 = $this->createSubtask(['name' => 'Order detail page', 'task_id' => $orderTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2B Sprint 1 — Client Portal')->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $oS3 = $this->createSubtask(['name' => 'Repeat order feature', 'task_id' => $orderTask->id, 'status_id' => $this->demoStatus('B2B', 'In Progress')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(2)]);
        $oS4 = $this->createSubtask(['name' => 'Order status tracking', 'task_id' => $orderTask->id, 'status_id' => $this->demoStatus('B2B', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 150, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'due_date' => now()->addDays(5)]);
        $oS2->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);
        $oS3->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);
        $oS4->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);

        $apiIntTask = $this->demoTask('REST API untuk Partner');
        $ai1 = $this->createSubtask(['name' => 'API key management', 'task_id' => $apiIntTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 1, 'time_estimate' => 120, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'completed_at' => now()->subDays(5)]);
        $ai2 = $this->createSubtask(['name' => 'Endpoint PO submission', 'task_id' => $apiIntTask->id, 'status_id' => $this->demoStatus('B2B', 'Done')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 1, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'completed_at' => now()->subDays(3)]);
        $ai3 = $this->createSubtask(['name' => 'Endpoint cek stok real-time', 'task_id' => $apiIntTask->id, 'status_id' => $this->demoStatus('B2B', 'In Progress')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 2, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'start_date' => now()->subDays(2), 'due_date' => now()->addDays(3)]);
        $ai4 = $this->createSubtask(['name' => 'Webhook notifikasi status', 'task_id' => $apiIntTask->id, 'status_id' => $this->demoStatus('B2B', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 150, 'position' => 3, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'due_date' => now()->addDays(5)]);
        $ai5 = $this->createSubtask(['name' => 'API documentation (Swagger)', 'task_id' => $apiIntTask->id, 'status_id' => $this->demoStatus('B2B', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 120, 'position' => 4, 'created_by' => $andi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id, 'due_date' => now()->addWeek()]);
        $ai2->dependencies()->attach($ai1->id, ['dependency_type' => 'blocks']);
        $ai3->dependencies()->attach($ai1->id, ['dependency_type' => 'blocks']);
        $ai4->dependencies()->attach($ai2->id, ['dependency_type' => 'blocks']);
        $ai5->dependencies()->attach($ai2->id, ['dependency_type' => 'blocks']);
        $ai5->dependencies()->attach($ai3->id, ['dependency_type' => 'blocks']);

        $invoiceTask = $this->demoTask('Invoice & Billing Otomatis');
        $this->createSubtask(['name' => 'Invoice generator dari PO data', 'task_id' => $invoiceTask->id, 'status_id' => $this->demoStatus('B2B', 'To Do')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id]);
        $this->createSubtask(['name' => 'Email notifikasi dengan PDF', 'task_id' => $invoiceTask->id, 'status_id' => $this->demoStatus('B2B', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 120, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2B Sprint 2 — API Integration')->id]);
        $this->createSubtask(['name' => 'Payment tracking dashboard', 'task_id' => $invoiceTask->id, 'status_id' => $this->demoStatus('B2B', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 2, 'created_by' => $dian->id]);

        $catalogTask = $this->demoTask('Product Catalog Website');
        $cS1 = $this->createSubtask(['name' => 'API product + kategori', 'task_id' => $catalogTask->id, 'status_id' => $this->demoStatus('B2C', 'Live')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 1 — E-Commerce Core')->id, 'completed_at' => now()->subWeeks(2)]);
        $cS2 = $this->createSubtask(['name' => 'Product listing page', 'task_id' => $catalogTask->id, 'status_id' => $this->demoStatus('B2C', 'Live')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2C Sprint 1 — E-Commerce Core')->id, 'completed_at' => now()->subWeeks(2)->addDays(3)]);
        $cS3 = $this->createSubtask(['name' => 'Search & filter komponen', 'task_id' => $catalogTask->id, 'status_id' => $this->demoStatus('B2C', 'In Progress')->id, 'priority_level' => 3, 'time_estimate' => 180, 'position' => 2, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'start_date' => now()->subDays(4), 'due_date' => now()->addDays(2)]);
        $cS4 = $this->createSubtask(['name' => 'Product detail + gallery', 'task_id' => $catalogTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 240, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'due_date' => now()->addDays(5)]);
        $cS5 = $this->createSubtask(['name' => 'SEO meta tags & SSR', 'task_id' => $catalogTask->id, 'status_id' => $this->demoStatus('B2C', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 120, 'position' => 4, 'created_by' => $dian->id]);
        $cS2->dependencies()->attach($cS1->id, ['dependency_type' => 'blocks']);
        $cS3->dependencies()->attach($cS2->id, ['dependency_type' => 'blocks']);
        $cS4->dependencies()->attach($cS1->id, ['dependency_type' => 'blocks']);
        $cS5->dependencies()->attach($cS3->id, ['dependency_type' => 'blocks']);
        $cS5->dependencies()->attach($cS4->id, ['dependency_type' => 'blocks']);

        $checkoutTask = $this->demoTask('Checkout & Payment Gateway');
        $ch1 = $this->createSubtask(['name' => 'Shopping cart (session-based)', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'Live')->id, 'priority_level' => 2, 'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 1 — E-Commerce Core')->id, 'completed_at' => now()->subWeeks(2)]);
        $ch2 = $this->createSubtask(['name' => 'Shipping cost (RajaOngkir API)', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'Live')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 1 — E-Commerce Core')->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $ch3 = $this->createSubtask(['name' => 'Midtrans payment integration', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'In Progress')->id, 'priority_level' => 1, 'time_estimate' => 360, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'start_date' => now()->subDays(5), 'due_date' => now()->addDays(2)]);
        $ch4 = $this->createSubtask(['name' => 'Checkout UI multi-step form', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'In Progress')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(3)]);
        $ch5 = $this->createSubtask(['name' => 'Payment callback handler', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 1, 'time_estimate' => 180, 'position' => 4, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'due_date' => now()->addDays(4)]);
        $ch6 = $this->createSubtask(['name' => 'Email konfirmasi order', 'task_id' => $checkoutTask->id, 'status_id' => $this->demoStatus('B2C', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 90, 'position' => 5, 'created_by' => $budi->id, 'sprint_id' => $this->demoSprint('B2C Sprint 2 — Payment & Shipping')->id, 'due_date' => now()->addWeek()]);
        $ch2->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch3->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch4->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch4->dependencies()->attach($ch2->id, ['dependency_type' => 'blocks']);
        $ch5->dependencies()->attach($ch3->id, ['dependency_type' => 'blocks']);
        $ch6->dependencies()->attach($ch5->id, ['dependency_type' => 'blocks']);

        $androidTask = $this->demoTask('Android App E-Commerce');
        $this->createSubtask(['name' => 'Setup Flutter project + API client', 'task_id' => $androidTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 2, 'time_estimate' => 180, 'position' => 0, 'created_by' => $dian->id]);
        $this->createSubtask(['name' => 'Product list & detail screen', 'task_id' => $androidTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 300, 'position' => 1, 'created_by' => $dian->id]);
        $this->createSubtask(['name' => 'Cart & checkout flow', 'task_id' => $androidTask->id, 'status_id' => $this->demoStatus('B2C', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 360, 'position' => 2, 'created_by' => $dian->id]);
        $this->createSubtask(['name' => 'Push notification (Firebase)', 'task_id' => $androidTask->id, 'status_id' => $this->demoStatus('B2C', 'Backlog')->id, 'priority_level' => 4, 'time_estimate' => 120, 'position' => 3, 'created_by' => $dian->id]);

        $cssTask = $this->demoTask('Live Chat Customer Support');
        $this->createSubtask(['name' => 'WebSocket chat server', 'task_id' => $cssTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 2, 'time_estimate' => 300, 'position' => 0, 'created_by' => $andi->id]);
        $this->createSubtask(['name' => 'Chat UI widget (floating)', 'task_id' => $cssTask->id, 'status_id' => $this->demoStatus('B2C', 'To Do')->id, 'priority_level' => 3, 'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id]);
        $this->createSubtask(['name' => 'CS agent dashboard', 'task_id' => $cssTask->id, 'status_id' => $this->demoStatus('B2C', 'Backlog')->id, 'priority_level' => 3, 'time_estimate' => 240, 'position' => 2, 'created_by' => $andi->id]);
    }

    private function createSubtask(array $attributes, array $assignees = [], array $labels = []): Subtask
    {
        $subtask = Subtask::create($attributes);

        foreach ($assignees as $userId) {
            $subtask->assignees()->attach([$userId => ['assigned_by' => $attributes['created_by']]]);
        }

        if ($labels !== []) {
            $subtask->labels()->attach(
                collect($labels)
                    ->map(fn(string $labelName) => $this->demoLabel($labelName)->id)
                    ->all()
            );
        }

        return $subtask;
    }
}
