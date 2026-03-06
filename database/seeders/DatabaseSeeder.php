<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Folder;
use App\Models\Label;
use App\Models\Priority;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\View;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ================================================================
        // 1. USERS  — Tim IT In-House
        // ================================================================
        $sasya = User::factory()->create([
            'name' => 'Sasya Rahma',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $andi = User::factory()->create([
            'name' => 'Andi Fullstack',
            'email' => 'andi@example.com',
            'password' => Hash::make('password'),
        ]);

        $dian = User::factory()->create([
            'name' => 'Dian Frontend',
            'email' => 'dian@example.com',
            'password' => Hash::make('password'),
        ]);

        $budi = User::factory()->create([
            'name' => 'Budi Backend',
            'email' => 'budi@example.com',
            'password' => Hash::make('password'),
        ]);

        $rina = User::factory()->create([
            'name' => 'Rina QA',
            'email' => 'rina@example.com',
            'password' => Hash::make('password'),
        ]);

        // ================================================================
        // 2. WORKSPACE  — Divisi IT perusahaan
        // ================================================================
        $workspace = Workspace::create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'description' => 'Divisi IT In-House — Mengelola sistem informasi untuk sektor Manufacturing, B2B, dan B2C',
            'owner_id' => $sasya->id,
            'color' => '#6366F1',
        ]);

        // Boot auto-attaches owner; add remaining members
        $workspace->addMember($andi, 'admin');
        $workspace->addMember($dian, 'member');
        $workspace->addMember($budi, 'member');
        $workspace->addMember($rina, 'member');

        // ================================================================
        // 3. PRIORITIES
        // ================================================================
        $urgent = Priority::create(['name' => 'Urgent', 'level' => 1, 'color' => '#FF6B6B', 'icon' => 'mdi-alert-circle',   'workspace_id' => $workspace->id]);
        $high   = Priority::create(['name' => 'High',   'level' => 2, 'color' => '#FFB84D', 'icon' => 'mdi-arrow-up-bold',  'workspace_id' => $workspace->id]);
        $normal = Priority::create(['name' => 'Normal', 'level' => 3, 'color' => '#49CCF9', 'icon' => 'mdi-minus',          'workspace_id' => $workspace->id, 'is_default' => true]);
        $low    = Priority::create(['name' => 'Low',    'level' => 4, 'color' => '#6B7280', 'icon' => 'mdi-arrow-down-bold', 'workspace_id' => $workspace->id]);

        // ================================================================
        // 4. LABELS
        // ================================================================
        $labelBug         = Label::create(['name' => 'Bug',           'color' => '#FF6B6B', 'workspace_id' => $workspace->id]);
        $labelFeature     = Label::create(['name' => 'Feature',       'color' => '#6BC950', 'workspace_id' => $workspace->id]);
        $labelEnhancement = Label::create(['name' => 'Enhancement',   'color' => '#49CCF9', 'workspace_id' => $workspace->id]);
        $labelDocs        = Label::create(['name' => 'Documentation', 'color' => '#8B5CF6', 'workspace_id' => $workspace->id]);
        $labelUI          = Label::create(['name' => 'UI/UX',         'color' => '#EC4899', 'workspace_id' => $workspace->id]);
        $labelRefactor    = Label::create(['name' => 'Refactor',      'color' => '#F59E0B', 'workspace_id' => $workspace->id]);
        $labelSecurity    = Label::create(['name' => 'Security',      'color' => '#EF4444', 'workspace_id' => $workspace->id]);
        $labelPerformance = Label::create(['name' => 'Performance',   'color' => '#14B8A6', 'workspace_id' => $workspace->id]);

        // ================================================================
        // 5. SPACES  —  Sektor bisnis perusahaan
        //    (boot auto-creates 4 default statuses each)
        // ================================================================

        // ─── Space: Manufacturing ───
        $mfgSpace = Space::create([
            'name' => 'Manufacturing',
            'workspace_id' => $workspace->id,
            'color' => '#F97316',
            'icon' => 'mdi-factory',
            'position' => 0,
            'created_by' => $sasya->id,
        ]);

        $mfgSpace->statuses()->delete();
        $mfgBacklog = Status::create(['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'space_id' => $mfgSpace->id, 'position' => 0, 'applies_to' => 'both']);
        $mfgTodo    = Status::create(['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'space_id' => $mfgSpace->id, 'position' => 1, 'applies_to' => 'both', 'is_default' => true]);
        $mfgDev     = Status::create(['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $mfgSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $mfgReview  = Status::create(['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'space_id' => $mfgSpace->id, 'position' => 3, 'applies_to' => 'both']);
        $mfgDone    = Status::create(['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'space_id' => $mfgSpace->id, 'position' => 4, 'applies_to' => 'both', 'is_closed' => true]);

        // ─── Space: B2B ───
        $b2bSpace = Space::create([
            'name' => 'B2B',
            'workspace_id' => $workspace->id,
            'color' => '#3B82F6',
            'icon' => 'mdi-handshake',
            'position' => 1,
            'created_by' => $sasya->id,
        ]);

        $b2bSpace->statuses()->delete();
        $b2bBacklog = Status::create(['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'space_id' => $b2bSpace->id, 'position' => 0, 'applies_to' => 'both']);
        $b2bTodo    = Status::create(['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'space_id' => $b2bSpace->id, 'position' => 1, 'applies_to' => 'both', 'is_default' => true]);
        $b2bDev     = Status::create(['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $b2bSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $b2bUAT     = Status::create(['name' => 'UAT',         'type' => 'review',      'color' => '#8B5CF6', 'space_id' => $b2bSpace->id, 'position' => 3, 'applies_to' => 'both']);
        $b2bDone    = Status::create(['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'space_id' => $b2bSpace->id, 'position' => 4, 'applies_to' => 'both', 'is_closed' => true]);

        // ─── Space: B2C ───
        $b2cSpace = Space::create([
            'name' => 'B2C',
            'workspace_id' => $workspace->id,
            'color' => '#10B981',
            'icon' => 'mdi-cart',
            'position' => 2,
            'created_by' => $sasya->id,
        ]);

        $b2cSpace->statuses()->delete();
        $b2cBacklog = Status::create(['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'space_id' => $b2cSpace->id, 'position' => 0, 'applies_to' => 'both']);
        $b2cTodo    = Status::create(['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'space_id' => $b2cSpace->id, 'position' => 1, 'applies_to' => 'both', 'is_default' => true]);
        $b2cDev     = Status::create(['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'space_id' => $b2cSpace->id, 'position' => 2, 'applies_to' => 'both']);
        $b2cStaging = Status::create(['name' => 'Staging',     'type' => 'review',      'color' => '#8B5CF6', 'space_id' => $b2cSpace->id, 'position' => 3, 'applies_to' => 'both']);
        $b2cLive    = Status::create(['name' => 'Live',        'type' => 'closed',      'color' => '#10B981', 'space_id' => $b2cSpace->id, 'position' => 4, 'applies_to' => 'both', 'is_closed' => true]);

        // ================================================================
        // 6. SPRINTS  — per space
        // ================================================================

        // Manufacturing sprints
        $mfgSprint1 = Sprint::create(['space_id' => $mfgSpace->id, 'name' => 'MFG Sprint 1 — ERP Foundation', 'goal' => 'Setup modul dasar ERP: master data, inventory core', 'start_date' => now()->subWeeks(4), 'end_date' => now()->subWeeks(2), 'is_active' => false, 'position' => 0]);
        $mfgSprint2 = Sprint::create(['space_id' => $mfgSpace->id, 'name' => 'MFG Sprint 2 — Production Tracking', 'goal' => 'Build real-time production monitoring dan dashboard IoT', 'start_date' => now()->subWeeks(2), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1]);
        $mfgSprint3 = Sprint::create(['space_id' => $mfgSpace->id, 'name' => 'MFG Sprint 3 — Reporting & Analytics', 'goal' => 'Laporan produksi, analisis efisiensi, export PDF/Excel', 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(3), 'is_active' => false, 'position' => 2]);

        // B2B sprints
        $b2bSprint1 = Sprint::create(['space_id' => $b2bSpace->id, 'name' => 'B2B Sprint 1 — Client Portal', 'goal' => 'Portal login, order history, dan invoice management', 'start_date' => now()->subWeeks(3), 'end_date' => now()->subWeek(), 'is_active' => false, 'position' => 0]);
        $b2bSprint2 = Sprint::create(['space_id' => $b2bSpace->id, 'name' => 'B2B Sprint 2 — API Integration', 'goal' => 'REST API untuk partner, webhook notifikasi, API docs', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1]);

        // B2C sprints
        $b2cSprint1 = Sprint::create(['space_id' => $b2cSpace->id, 'name' => 'B2C Sprint 1 — E-Commerce Core', 'goal' => 'Product catalog, keranjang belanja, checkout flow', 'start_date' => now()->subWeeks(3), 'end_date' => now()->subWeek(), 'is_active' => false, 'position' => 0]);
        $b2cSprint2 = Sprint::create(['space_id' => $b2cSpace->id, 'name' => 'B2C Sprint 2 — Payment & Shipping', 'goal' => 'Integrasi Midtrans, ongkir RajaOngkir, notif email', 'start_date' => now()->subWeek(), 'end_date' => now()->addWeek(), 'is_active' => true, 'position' => 1]);

        // ================================================================
        // 7. FOLDERS & LISTS — Manufacturing Space
        // ================================================================

        $erpFolder = Folder::create(['name' => 'ERP System', 'space_id' => $mfgSpace->id, 'position' => 0, 'created_by' => $sasya->id]);

        $inventoryList = TaskList::create(['name' => 'Inventory Module',    'space_id' => $mfgSpace->id, 'folder_id' => $erpFolder->id, 'position' => 0, 'created_by' => $budi->id]);
        $productionList = TaskList::create(['name' => 'Production Tracking', 'space_id' => $mfgSpace->id, 'folder_id' => $erpFolder->id, 'position' => 1, 'created_by' => $budi->id]);

        $iotFolder = Folder::create(['name' => 'IoT & Monitoring', 'space_id' => $mfgSpace->id, 'position' => 1, 'created_by' => $andi->id]);

        $sensorList = TaskList::create(['name' => 'Sensor Dashboard', 'space_id' => $mfgSpace->id, 'folder_id' => $iotFolder->id, 'position' => 0, 'created_by' => $andi->id]);

        $mfgReportList = TaskList::create(['name' => 'Reporting', 'space_id' => $mfgSpace->id, 'position' => 2, 'created_by' => $sasya->id]);

        // ================================================================
        // 8. FOLDERS & LISTS — B2B Space
        // ================================================================

        $portalFolder = Folder::create(['name' => 'Client Portal', 'space_id' => $b2bSpace->id, 'position' => 0, 'created_by' => $sasya->id]);

        $portalAuthList  = TaskList::create(['name' => 'Authentication & Access', 'space_id' => $b2bSpace->id, 'folder_id' => $portalFolder->id, 'position' => 0, 'created_by' => $budi->id]);
        $orderMgmtList   = TaskList::create(['name' => 'Order Management',        'space_id' => $b2bSpace->id, 'folder_id' => $portalFolder->id, 'position' => 1, 'created_by' => $budi->id]);

        $apiIntList   = TaskList::create(['name' => 'API Integrations', 'space_id' => $b2bSpace->id, 'position' => 1, 'created_by' => $andi->id]);
        $invoiceList  = TaskList::create(['name' => 'Invoice System',   'space_id' => $b2bSpace->id, 'position' => 2, 'created_by' => $budi->id]);

        // ================================================================
        // 9. FOLDERS & LISTS — B2C Space
        // ================================================================

        $ecomFolder = Folder::create(['name' => 'E-Commerce', 'space_id' => $b2cSpace->id, 'position' => 0, 'created_by' => $sasya->id]);

        $catalogList  = TaskList::create(['name' => 'Product Catalog',     'space_id' => $b2cSpace->id, 'folder_id' => $ecomFolder->id, 'position' => 0, 'created_by' => $dian->id]);
        $checkoutList = TaskList::create(['name' => 'Checkout & Payment',  'space_id' => $b2cSpace->id, 'folder_id' => $ecomFolder->id, 'position' => 1, 'created_by' => $budi->id]);

        $mobileFolder = Folder::create(['name' => 'Mobile App', 'space_id' => $b2cSpace->id, 'position' => 1, 'created_by' => $dian->id]);

        $androidList = TaskList::create(['name' => 'Android App', 'space_id' => $b2cSpace->id, 'folder_id' => $mobileFolder->id, 'position' => 0, 'created_by' => $dian->id]);

        $cssList = TaskList::create(['name' => 'Customer Support System', 'space_id' => $b2cSpace->id, 'position' => 2, 'created_by' => $andi->id]);

        // ================================================================
        // 10. TASKS — Manufacturing / Inventory Module
        //     (full CPM demo — 10 subtasks with dependencies)
        // ================================================================

        $invTask = Task::create([
            'name'         => 'Sistem Manajemen Inventory',
            'description'  => 'Membangun modul inventory lengkap: master barang, stok masuk/keluar, stock opname, dan laporan inventory',
            'task_list_id' => $inventoryList->id,
            'status_id'    => $mfgDev->id,
            'priority_id'  => $urgent->id,
            'created_by'   => $sasya->id,
            'position'     => 0,
        ]);
        $invTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $invTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $invTask->labels()->attach([$labelFeature->id]);

        // 10 subtasks — CPM dependency chain
        $iS1  = Subtask::create(['name' => 'Desain database inventory',           'task_id' => $invTask->id, 'status_id' => $mfgDone->id,    'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id,  'sprint_id' => $mfgSprint1->id, 'start_date' => now()->subWeeks(4),           'due_date' => now()->subWeeks(4)->addDay(),   'completed_at' => now()->subWeeks(4)->addDay()]);
        $iS2  = Subtask::create(['name' => 'API master barang (CRUD)',            'task_id' => $invTask->id, 'status_id' => $mfgDone->id,    'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id,  'sprint_id' => $mfgSprint1->id, 'start_date' => now()->subWeeks(4)->addDay(), 'due_date' => now()->subWeeks(4)->addDays(3), 'completed_at' => now()->subWeeks(4)->addDays(3)]);
        $iS3  = Subtask::create(['name' => 'UI halaman master barang',            'task_id' => $invTask->id, 'status_id' => $mfgDone->id,    'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 2, 'created_by' => $dian->id,  'sprint_id' => $mfgSprint1->id, 'start_date' => now()->subWeeks(3),           'due_date' => now()->subWeeks(3)->addDays(3), 'completed_at' => now()->subWeeks(3)->addDays(3)]);
        $iS4  = Subtask::create(['name' => 'API stok masuk & keluar',             'task_id' => $invTask->id, 'status_id' => $mfgDone->id,    'priority_id' => $urgent->id, 'time_estimate' => 300, 'position' => 3, 'created_by' => $budi->id,  'sprint_id' => $mfgSprint1->id, 'start_date' => now()->subWeeks(3),           'due_date' => now()->subWeeks(3)->addDays(3), 'completed_at' => now()->subWeeks(3)->addDays(2)]);
        $iS5  = Subtask::create(['name' => 'UI form stok masuk/keluar',           'task_id' => $invTask->id, 'status_id' => $mfgDev->id,     'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 4, 'created_by' => $dian->id,  'sprint_id' => $mfgSprint2->id, 'start_date' => now()->subDays(5),            'due_date' => now()->addDay()]);
        $iS6  = Subtask::create(['name' => 'Fitur stock opname',                  'task_id' => $invTask->id, 'status_id' => $mfgDev->id,     'priority_id' => $normal->id, 'time_estimate' => 360, 'position' => 5, 'created_by' => $budi->id,  'sprint_id' => $mfgSprint2->id, 'start_date' => now()->subDays(3),            'due_date' => now()->addDays(3)]);
        $iS7  = Subtask::create(['name' => 'Barcode/QR code scanner',             'task_id' => $invTask->id, 'status_id' => $mfgTodo->id,    'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 6, 'created_by' => $dian->id,  'sprint_id' => $mfgSprint2->id,                                               'due_date' => now()->addDays(5)]);
        $iS8  = Subtask::create(['name' => 'Laporan stok (PDF/Excel)',            'task_id' => $invTask->id, 'status_id' => $mfgBacklog->id,  'priority_id' => $low->id,    'time_estimate' => 240, 'position' => 7, 'created_by' => $budi->id,  'sprint_id' => $mfgSprint3->id,                                               'due_date' => now()->addWeeks(2)]);
        $iS9  = Subtask::create(['name' => 'Integration testing inventory',       'task_id' => $invTask->id, 'status_id' => $mfgBacklog->id,  'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 8, 'created_by' => $rina->id,  'sprint_id' => $mfgSprint3->id,                                               'due_date' => now()->addWeeks(2)->addDays(3)]);
        $iS10 = Subtask::create(['name' => 'Deploy modul inventory ke production', 'task_id' => $invTask->id, 'status_id' => $mfgBacklog->id,  'priority_id' => $urgent->id, 'time_estimate' => 60,  'position' => 9, 'created_by' => $sasya->id, 'sprint_id' => $mfgSprint3->id,                                               'due_date' => now()->addWeeks(3)]);

        // Assign subtask members
        $iS1->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS2->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS3->assignees()->attach([$dian->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS4->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS5->assignees()->attach([$dian->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS6->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS6->assignees()->attach([$andi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS7->assignees()->attach([$dian->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS8->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS9->assignees()->attach([$rina->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS10->assignees()->attach([$sasya->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $iS10->assignees()->attach([$budi->id  => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);

        // Labels on subtasks
        $iS1->labels()->attach([$labelDocs->id]);
        $iS3->labels()->attach([$labelUI->id]);
        $iS5->labels()->attach([$labelUI->id]);
        $iS7->labels()->attach([$labelFeature->id]);
        $iS9->labels()->attach([$labelSecurity->id]);

        // Dependencies (CPM)
        //   DB Design(1) → API Master(2) → UI Master(3) ──────────────┐
        //                 → API Stok(4) → UI Stok(5) ─── Barcode(7) ──→ Report(8) → Testing(9) → Deploy(10)
        //                               → Stock Opname(6) ────────────┘
        $iS2->dependencies()->attach($iS1->id,   ['dependency_type' => 'blocks']);
        $iS3->dependencies()->attach($iS2->id,   ['dependency_type' => 'blocks']);
        $iS4->dependencies()->attach($iS1->id,   ['dependency_type' => 'blocks']);
        $iS5->dependencies()->attach($iS4->id,   ['dependency_type' => 'blocks']);
        $iS5->dependencies()->attach($iS3->id,   ['dependency_type' => 'blocks']);
        $iS6->dependencies()->attach($iS4->id,   ['dependency_type' => 'blocks']);
        $iS7->dependencies()->attach($iS5->id,   ['dependency_type' => 'blocks']);
        $iS8->dependencies()->attach($iS6->id,   ['dependency_type' => 'blocks']);
        $iS8->dependencies()->attach($iS7->id,   ['dependency_type' => 'blocks']);
        $iS9->dependencies()->attach($iS8->id,   ['dependency_type' => 'blocks']);
        $iS10->dependencies()->attach($iS9->id,  ['dependency_type' => 'blocks']);

        // Time entries
        $this->timeEntry($iS1, $budi, 160, now()->subWeeks(4),               'ER diagram inventory module');
        $this->timeEntry($iS2, $budi, 220, now()->subWeeks(4)->addDay(),     'CRUD endpoint + validation');
        $this->timeEntry($iS3, $dian, 280, now()->subWeeks(3),               'Vue table + form master barang');
        $this->timeEntry($iS4, $budi, 260, now()->subWeeks(3),               'Stok masuk/keluar controller');
        $this->timeEntry($iS4, $budi, 40,  now()->subWeeks(3)->addDays(2),   'Fix edge case stok negatif');
        $this->timeEntry($iS5, $dian, 120, now()->subDays(5),               'Form stok masuk dengan autocomplete');
        $this->timeEntry($iS6, $budi, 90,  now()->subDays(3),               'Stock opname backend logic');

        // ================================================================
        // 11. TASKS — Manufacturing / Production Tracking
        // ================================================================

        $prodTask = Task::create([
            'name'         => 'Real-time Production Monitoring',
            'description'  => 'Dashboard monitoring produksi real-time dengan data mesin dan output per shift',
            'task_list_id' => $productionList->id,
            'status_id'    => $mfgDev->id,
            'priority_id'  => $high->id,
            'created_by'   => $andi->id,
            'position'     => 0,
        ]);
        $prodTask->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $prodTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $prodTask->labels()->attach([$labelFeature->id, $labelPerformance->id]);

        $pS1 = Subtask::create(['name' => 'Desain schema data produksi',       'task_id' => $prodTask->id, 'status_id' => $mfgDone->id, 'priority_id' => $high->id,   'time_estimate' => 120, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $mfgSprint2->id, 'completed_at' => now()->subDays(7)]);
        $pS2 = Subtask::create(['name' => 'API data output mesin per shift',   'task_id' => $prodTask->id, 'status_id' => $mfgDone->id, 'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $mfgSprint2->id, 'completed_at' => now()->subDays(4)]);
        $pS3 = Subtask::create(['name' => 'Dashboard chart realtime (Vue)',    'task_id' => $prodTask->id, 'status_id' => $mfgDev->id,  'priority_id' => $urgent->id, 'time_estimate' => 360, 'position' => 2, 'created_by' => $dian->id, 'sprint_id' => $mfgSprint2->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(3)]);
        $pS4 = Subtask::create(['name' => 'Notifikasi downtime mesin',         'task_id' => $prodTask->id, 'status_id' => $mfgTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 3, 'created_by' => $andi->id, 'sprint_id' => $mfgSprint2->id, 'due_date' => now()->addDays(5)]);

        $pS2->dependencies()->attach($pS1->id, ['dependency_type' => 'blocks']);
        $pS3->dependencies()->attach($pS2->id, ['dependency_type' => 'blocks']);
        $pS4->dependencies()->attach($pS2->id, ['dependency_type' => 'blocks']);

        $pS1->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $pS2->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $pS3->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $pS4->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);

        $this->timeEntry($pS1, $andi, 100, now()->subDays(8), 'Schema mesin + shift + output');
        $this->timeEntry($pS2, $budi, 210, now()->subDays(6), 'Endpoint produksi per shift');
        $this->timeEntry($pS3, $dian, 150, now()->subDays(3), 'Chart.js integration');

        // ================================================================
        // 12. TASKS — Manufacturing / Sensor Dashboard
        // ================================================================

        $sensorTask = Task::create([
            'name'         => 'IoT Sensor Dashboard',
            'description'  => 'Dashboard monitoring sensor suhu, kelembaban, dan getaran mesin pabrik',
            'task_list_id' => $sensorList->id,
            'status_id'    => $mfgTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $andi->id,
            'position'     => 0,
        ]);
        $sensorTask->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $sensorTask->labels()->attach([$labelFeature->id]);

        Subtask::create(['name' => 'API endpoint sensor data (MQTT bridge)',  'task_id' => $sensorTask->id, 'status_id' => $mfgTodo->id, 'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $mfgSprint2->id]);
        Subtask::create(['name' => 'Gauge & line chart komponen',             'task_id' => $sensorTask->id, 'status_id' => $mfgTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $mfgSprint2->id]);
        Subtask::create(['name' => 'Alert threshold config',                  'task_id' => $sensorTask->id, 'status_id' => $mfgBacklog->id, 'priority_id' => $low->id,  'time_estimate' => 120, 'position' => 2, 'created_by' => $andi->id, 'sprint_id' => $mfgSprint3->id]);

        // Manufacturing Reporting
        $mfgRepTask = Task::create([
            'name'         => 'Laporan Produksi Bulanan',
            'description'  => 'Generate laporan produksi bulanan otomatis dengan export PDF dan Excel',
            'task_list_id' => $mfgReportList->id,
            'status_id'    => $mfgBacklog->id,
            'priority_id'  => $normal->id,
            'created_by'   => $sasya->id,
            'position'     => 0,
        ]);
        $mfgRepTask->labels()->attach([$labelFeature->id, $labelDocs->id]);

        Subtask::create(['name' => 'Template laporan (Blade)',       'task_id' => $mfgRepTask->id, 'status_id' => $mfgBacklog->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $mfgSprint3->id]);
        Subtask::create(['name' => 'Export PDF (DomPDF)',            'task_id' => $mfgRepTask->id, 'status_id' => $mfgBacklog->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $mfgSprint3->id]);
        Subtask::create(['name' => 'Export Excel (Maatwebsite)',     'task_id' => $mfgRepTask->id, 'status_id' => $mfgBacklog->id, 'priority_id' => $low->id,    'time_estimate' => 90,  'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $mfgSprint3->id]);

        // ================================================================
        // 13. TASKS — B2B / Authentication & Access
        // ================================================================

        $b2bAuthTask = Task::create([
            'name'         => 'Portal Login & Multi-tenant',
            'description'  => 'Sistem login multi-tenant untuk client B2B dengan role-based access control',
            'task_list_id' => $portalAuthList->id,
            'status_id'    => $b2bDone->id,
            'priority_id'  => $urgent->id,
            'created_by'   => $budi->id,
            'position'     => 0,
        ]);
        $b2bAuthTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $b2bAuthTask->labels()->attach([$labelSecurity->id, $labelFeature->id]);

        $ba1 = Subtask::create(['name' => 'Schema multi-tenant (tenant_id)',    'task_id' => $b2bAuthTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $urgent->id, 'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(3)]);
        $ba2 = Subtask::create(['name' => 'Login API + JWT token',              'task_id' => $b2bAuthTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $urgent->id, 'time_estimate' => 240, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(3)->addDays(2)]);
        $ba3 = Subtask::create(['name' => 'Role & permission middleware',       'task_id' => $b2bAuthTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $ba4 = Subtask::create(['name' => 'Login page UI (Vue)',                'task_id' => $b2bAuthTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(2)->addDay()]);

        $ba2->dependencies()->attach($ba1->id, ['dependency_type' => 'blocks']);
        $ba3->dependencies()->attach($ba2->id, ['dependency_type' => 'blocks']);
        $ba4->dependencies()->attach($ba2->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($ba1, $budi, 160, now()->subWeeks(3),            'Multi-tenant schema + migration');
        $this->timeEntry($ba2, $budi, 220, now()->subWeeks(3)->addDay(),  'JWT auth with Sanctum');
        $this->timeEntry($ba3, $budi, 170, now()->subWeeks(2)->subDay(),  'Spatie permission setup');
        $this->timeEntry($ba4, $dian, 160, now()->subWeeks(2),            'Login page + 2FA flow');

        // ================================================================
        // 14. TASKS — B2B / Order Management
        // ================================================================

        $orderTask = Task::create([
            'name'         => 'Order Management Portal',
            'description'  => 'Client bisa melihat order history, tracking status, dan repeat order',
            'task_list_id' => $orderMgmtList->id,
            'status_id'    => $b2bDev->id,
            'priority_id'  => $high->id,
            'created_by'   => $sasya->id,
            'position'     => 0,
        ]);
        $orderTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $orderTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $orderTask->labels()->attach([$labelFeature->id]);

        $oS1 = Subtask::create(['name' => 'API order list + filter',     'task_id' => $orderTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $oS2 = Subtask::create(['name' => 'Order detail page',           'task_id' => $orderTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $b2bSprint1->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $oS3 = Subtask::create(['name' => 'Repeat order feature',        'task_id' => $orderTask->id, 'status_id' => $b2bDev->id,  'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint2->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(2)]);
        $oS4 = Subtask::create(['name' => 'Order status tracking',       'task_id' => $orderTask->id, 'status_id' => $b2bTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 150, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $b2bSprint2->id, 'due_date' => now()->addDays(5)]);

        $oS2->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);
        $oS3->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);
        $oS4->dependencies()->attach($oS1->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($oS1, $budi, 170, now()->subWeeks(2),            'Laravel query builder + filter');
        $this->timeEntry($oS2, $dian, 220, now()->subWeeks(2)->addDay(),  'Order detail with timeline');
        $this->timeEntry($oS3, $budi, 60,  now()->subDays(2),             'Started repeat order logic');

        // ================================================================
        // 15. TASKS — B2B / API Integrations
        // ================================================================

        $apiIntTask = Task::create([
            'name'         => 'REST API untuk Partner',
            'description'  => 'Public API agar partner B2B bisa kirim PO, cek stok, dan terima invoice secara otomatis',
            'task_list_id' => $apiIntList->id,
            'status_id'    => $b2bDev->id,
            'priority_id'  => $high->id,
            'created_by'   => $andi->id,
            'position'     => 0,
        ]);
        $apiIntTask->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $apiIntTask->labels()->attach([$labelFeature->id, $labelSecurity->id]);

        $ai1 = Subtask::create(['name' => 'API key management',             'task_id' => $apiIntTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $urgent->id, 'time_estimate' => 120, 'position' => 0, 'created_by' => $andi->id, 'sprint_id' => $b2bSprint2->id, 'completed_at' => now()->subDays(5)]);
        $ai2 = Subtask::create(['name' => 'Endpoint PO submission',         'task_id' => $apiIntTask->id, 'status_id' => $b2bDone->id, 'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 1, 'created_by' => $andi->id, 'sprint_id' => $b2bSprint2->id, 'completed_at' => now()->subDays(3)]);
        $ai3 = Subtask::create(['name' => 'Endpoint cek stok real-time',    'task_id' => $apiIntTask->id, 'status_id' => $b2bDev->id,  'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 2, 'created_by' => $andi->id, 'sprint_id' => $b2bSprint2->id, 'start_date' => now()->subDays(2), 'due_date' => now()->addDays(3)]);
        $ai4 = Subtask::create(['name' => 'Webhook notifikasi status',      'task_id' => $apiIntTask->id, 'status_id' => $b2bTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 150, 'position' => 3, 'created_by' => $andi->id, 'sprint_id' => $b2bSprint2->id, 'due_date' => now()->addDays(5)]);
        $ai5 = Subtask::create(['name' => 'API documentation (Swagger)',    'task_id' => $apiIntTask->id, 'status_id' => $b2bBacklog->id, 'priority_id' => $low->id,  'time_estimate' => 120, 'position' => 4, 'created_by' => $andi->id, 'sprint_id' => $b2bSprint2->id, 'due_date' => now()->addWeek()]);

        $ai2->dependencies()->attach($ai1->id, ['dependency_type' => 'blocks']);
        $ai3->dependencies()->attach($ai1->id, ['dependency_type' => 'blocks']);
        $ai4->dependencies()->attach($ai2->id, ['dependency_type' => 'blocks']);
        $ai5->dependencies()->attach($ai2->id, ['dependency_type' => 'blocks']);
        $ai5->dependencies()->attach($ai3->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($ai1, $andi, 100, now()->subDays(6), 'API key hashing + rate limit');
        $this->timeEntry($ai2, $andi, 210, now()->subDays(5), 'PO endpoint + validation');
        $this->timeEntry($ai3, $andi, 80,  now()->subDays(2), 'Stok query optimization');

        // B2B Invoice
        $invoiceTask = Task::create([
            'name'         => 'Invoice & Billing Otomatis',
            'description'  => 'Generate invoice otomatis dari PO, kirim via email, dan tracking pembayaran',
            'task_list_id' => $invoiceList->id,
            'status_id'    => $b2bTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $sasya->id,
            'position'     => 0,
        ]);
        $invoiceTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);

        Subtask::create(['name' => 'Invoice generator dari PO data',    'task_id' => $invoiceTask->id, 'status_id' => $b2bTodo->id, 'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint2->id]);
        Subtask::create(['name' => 'Email notifikasi dengan PDF',       'task_id' => $invoiceTask->id, 'status_id' => $b2bTodo->id, 'priority_id' => $normal->id, 'time_estimate' => 120, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $b2bSprint2->id]);
        Subtask::create(['name' => 'Payment tracking dashboard',        'task_id' => $invoiceTask->id, 'status_id' => $b2bBacklog->id, 'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 2, 'created_by' => $dian->id]);

        // ================================================================
        // 16. TASKS — B2C / Product Catalog
        // ================================================================

        $catalogTask = Task::create([
            'name'         => 'Product Catalog Website',
            'description'  => 'Halaman katalog produk dengan search, filter kategori, dan detail produk',
            'task_list_id' => $catalogList->id,
            'status_id'    => $b2cDev->id,
            'priority_id'  => $high->id,
            'created_by'   => $dian->id,
            'position'     => 0,
        ]);
        $catalogTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $catalogTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $catalogTask->labels()->attach([$labelFeature->id, $labelUI->id]);

        $cS1 = Subtask::create(['name' => 'API product + kategori',       'task_id' => $catalogTask->id, 'status_id' => $b2cLive->id,  'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $cS2 = Subtask::create(['name' => 'Product listing page',         'task_id' => $catalogTask->id, 'status_id' => $b2cLive->id,  'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 1, 'created_by' => $dian->id, 'sprint_id' => $b2cSprint1->id, 'completed_at' => now()->subWeeks(2)->addDays(3)]);
        $cS3 = Subtask::create(['name' => 'Search & filter komponen',     'task_id' => $catalogTask->id, 'status_id' => $b2cDev->id,   'priority_id' => $normal->id, 'time_estimate' => 180, 'position' => 2, 'created_by' => $dian->id, 'sprint_id' => $b2cSprint2->id, 'start_date' => now()->subDays(4), 'due_date' => now()->addDays(2)]);
        $cS4 = Subtask::create(['name' => 'Product detail + gallery',     'task_id' => $catalogTask->id, 'status_id' => $b2cTodo->id,  'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $b2cSprint2->id, 'due_date' => now()->addDays(5)]);
        $cS5 = Subtask::create(['name' => 'SEO meta tags & SSR',          'task_id' => $catalogTask->id, 'status_id' => $b2cBacklog->id, 'priority_id' => $low->id,  'time_estimate' => 120, 'position' => 4, 'created_by' => $dian->id]);

        $cS2->dependencies()->attach($cS1->id, ['dependency_type' => 'blocks']);
        $cS3->dependencies()->attach($cS2->id, ['dependency_type' => 'blocks']);
        $cS4->dependencies()->attach($cS1->id, ['dependency_type' => 'blocks']);
        $cS5->dependencies()->attach($cS3->id, ['dependency_type' => 'blocks']);
        $cS5->dependencies()->attach($cS4->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($cS1, $budi, 220, now()->subWeeks(2),            'Product API + seeder');
        $this->timeEntry($cS2, $dian, 270, now()->subWeeks(2)->addDay(),  'Product grid + responsive');
        $this->timeEntry($cS3, $dian, 100, now()->subDays(3),             'Algolia-style search component');

        // ================================================================
        // 17. TASKS — B2C / Checkout & Payment
        // ================================================================

        $checkoutTask = Task::create([
            'name'         => 'Checkout & Payment Gateway',
            'description'  => 'Alur checkout: keranjang → shipping → pembayaran Midtrans → konfirmasi',
            'task_list_id' => $checkoutList->id,
            'status_id'    => $b2cDev->id,
            'priority_id'  => $urgent->id,
            'created_by'   => $budi->id,
            'position'     => 0,
        ]);
        $checkoutTask->assignees()->attach([$budi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $checkoutTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $checkoutTask->labels()->attach([$labelFeature->id, $labelSecurity->id]);

        $ch1 = Subtask::create(['name' => 'Shopping cart (session-based)',    'task_id' => $checkoutTask->id, 'status_id' => $b2cLive->id,    'priority_id' => $high->id,   'time_estimate' => 240, 'position' => 0, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint1->id, 'completed_at' => now()->subWeeks(2)]);
        $ch2 = Subtask::create(['name' => 'Shipping cost (RajaOngkir API)',  'task_id' => $checkoutTask->id, 'status_id' => $b2cLive->id,    'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 1, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint1->id, 'completed_at' => now()->subWeeks(2)->addDays(2)]);
        $ch3 = Subtask::create(['name' => 'Midtrans payment integration',    'task_id' => $checkoutTask->id, 'status_id' => $b2cDev->id,     'priority_id' => $urgent->id, 'time_estimate' => 360, 'position' => 2, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint2->id, 'start_date' => now()->subDays(5), 'due_date' => now()->addDays(2)]);
        $ch4 = Subtask::create(['name' => 'Checkout UI multi-step form',     'task_id' => $checkoutTask->id, 'status_id' => $b2cDev->id,     'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 3, 'created_by' => $dian->id, 'sprint_id' => $b2cSprint2->id, 'start_date' => now()->subDays(3), 'due_date' => now()->addDays(3)]);
        $ch5 = Subtask::create(['name' => 'Payment callback handler',        'task_id' => $checkoutTask->id, 'status_id' => $b2cTodo->id,    'priority_id' => $urgent->id, 'time_estimate' => 180, 'position' => 4, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint2->id, 'due_date' => now()->addDays(4)]);
        $ch6 = Subtask::create(['name' => 'Email konfirmasi order',          'task_id' => $checkoutTask->id, 'status_id' => $b2cBacklog->id,  'priority_id' => $normal->id, 'time_estimate' => 90,  'position' => 5, 'created_by' => $budi->id, 'sprint_id' => $b2cSprint2->id, 'due_date' => now()->addWeek()]);

        $ch2->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch3->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch4->dependencies()->attach($ch1->id, ['dependency_type' => 'blocks']);
        $ch4->dependencies()->attach($ch2->id, ['dependency_type' => 'blocks']);
        $ch5->dependencies()->attach($ch3->id, ['dependency_type' => 'blocks']);
        $ch6->dependencies()->attach($ch5->id, ['dependency_type' => 'blocks']);

        $this->timeEntry($ch1, $budi, 210, now()->subWeeks(2),            'Cart logic + session store');
        $this->timeEntry($ch2, $budi, 160, now()->subWeeks(2)->addDay(),  'RajaOngkir JNE/JNT/SiCepat');
        $this->timeEntry($ch3, $budi, 180, now()->subDays(4),             'Midtrans Snap integration');
        $this->timeEntry($ch4, $dian, 150, now()->subDays(3),             'Multi-step checkout form');

        // ================================================================
        // 18. TASKS — B2C / Android App
        // ================================================================

        $androidTask = Task::create([
            'name'         => 'Android App E-Commerce',
            'description'  => 'Aplikasi Android untuk browse produk, checkout, dan tracking order',
            'task_list_id' => $androidList->id,
            'status_id'    => $b2cTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $dian->id,
            'position'     => 0,
        ]);
        $androidTask->assignees()->attach([$dian->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $androidTask->labels()->attach([$labelFeature->id, $labelUI->id]);

        Subtask::create(['name' => 'Setup Flutter project + API client',  'task_id' => $androidTask->id, 'status_id' => $b2cTodo->id,     'priority_id' => $high->id,   'time_estimate' => 180, 'position' => 0, 'created_by' => $dian->id]);
        Subtask::create(['name' => 'Product list & detail screen',        'task_id' => $androidTask->id, 'status_id' => $b2cTodo->id,     'priority_id' => $normal->id, 'time_estimate' => 300, 'position' => 1, 'created_by' => $dian->id]);
        Subtask::create(['name' => 'Cart & checkout flow',                'task_id' => $androidTask->id, 'status_id' => $b2cBacklog->id,  'priority_id' => $normal->id, 'time_estimate' => 360, 'position' => 2, 'created_by' => $dian->id]);
        Subtask::create(['name' => 'Push notification (Firebase)',        'task_id' => $androidTask->id, 'status_id' => $b2cBacklog->id,  'priority_id' => $low->id,    'time_estimate' => 120, 'position' => 3, 'created_by' => $dian->id]);

        // ================================================================
        // 19. TASKS — B2C / Customer Support
        // ================================================================

        $cssTask = Task::create([
            'name'         => 'Live Chat Customer Support',
            'description'  => 'Fitur live chat untuk customer dengan auto-assign ke agent CS',
            'task_list_id' => $cssList->id,
            'status_id'    => $b2cTodo->id,
            'priority_id'  => $normal->id,
            'created_by'   => $andi->id,
            'position'     => 0,
        ]);
        $cssTask->assignees()->attach([$andi->id => ['assigned_at' => now(), 'assigned_by' => $sasya->id]]);
        $cssTask->labels()->attach([$labelFeature->id]);

        Subtask::create(['name' => 'WebSocket chat server',       'task_id' => $cssTask->id, 'status_id' => $b2cTodo->id,    'priority_id' => $high->id,   'time_estimate' => 300, 'position' => 0, 'created_by' => $andi->id]);
        Subtask::create(['name' => 'Chat UI widget (floating)',   'task_id' => $cssTask->id, 'status_id' => $b2cTodo->id,    'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 1, 'created_by' => $dian->id]);
        Subtask::create(['name' => 'CS agent dashboard',          'task_id' => $cssTask->id, 'status_id' => $b2cBacklog->id, 'priority_id' => $normal->id, 'time_estimate' => 240, 'position' => 2, 'created_by' => $andi->id]);

        // ================================================================
        // 20. COMMENTS  (with threaded replies)
        // ================================================================

        // Inventory discussion
        $c1 = Comment::create(['task_id' => $invTask->id, 'user_id' => $sasya->id, 'content' => 'Modul inventory ini prioritas utama ya. Tim gudang sudah nanya kapan bisa pakai sistem baru.', 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $budi->id, 'parent_id' => $c1->id, 'content' => 'Siap, API master barang sudah selesai. Sekarang lagi kerjain stok masuk/keluar.', 'created_at' => now()->subDays(7)->addHours(2), 'updated_at' => now()->subDays(7)->addHours(2)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $dian->id, 'parent_id' => $c1->id, 'content' => 'UI master barang sudah live di staging. Minta tolong review ya Kak Sasya.', 'created_at' => now()->subDays(7)->addHours(4), 'updated_at' => now()->subDays(7)->addHours(4)]);
        Comment::create(['task_id' => $invTask->id, 'user_id' => $rina->id, 'content' => 'Untuk stock opname, pastikan ada audit trail siapa yang adjust stoknya ya.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);

        // Production monitoring discussion
        $c2 = Comment::create(['task_id' => $prodTask->id, 'user_id' => $andi->id, 'content' => 'Dashboard chart pakai Chart.js atau ApexCharts? Mau yang support real-time update.', 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]);
        Comment::create(['task_id' => $prodTask->id, 'user_id' => $dian->id, 'parent_id' => $c2->id, 'content' => 'Mendingan ApexCharts, lebih enak buat real-time streaming dan confignya lebih simpel.', 'created_at' => now()->subDays(4)->addHours(1), 'updated_at' => now()->subDays(4)->addHours(1)]);

        // B2B API discussion
        Comment::create(['task_id' => $apiIntTask->id, 'user_id' => $sasya->id, 'content' => 'API docs harus ready sebelum demo ke client PT Maju Bersama minggu depan.', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        Comment::create(['task_id' => $apiIntTask->id, 'user_id' => $andi->id, 'content' => 'Swagger sudah auto-generate dari annotation. Tinggal polish contoh request/response-nya.', 'created_at' => now()->subDays(3)->addHours(2), 'updated_at' => now()->subDays(3)->addHours(2)]);

        // Checkout discussion
        $c3 = Comment::create(['task_id' => $checkoutTask->id, 'user_id' => $budi->id, 'content' => 'Midtrans sandbox sudah bisa test. Perlu API key production dari tim finance.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        Comment::create(['task_id' => $checkoutTask->id, 'user_id' => $sasya->id, 'parent_id' => $c3->id, 'content' => 'Sudah minta ke Finance, estimasi dapet key-nya besok. Sementara pakai sandbox dulu.', 'created_at' => now()->subDays(2)->addHours(3), 'updated_at' => now()->subDays(2)->addHours(3)]);

        // Order management
        Comment::create(['task_id' => $orderTask->id, 'user_id' => $dian->id, 'content' => 'Order detail page sudah include timeline status perubahan. Cek di staging ya.', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);

        // Catalog
        Comment::create(['task_id' => $catalogTask->id, 'user_id' => $dian->id, 'content' => 'Search component pakai debounce 300ms supaya nggak spam API. Sudah smooth.', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);

        // ================================================================
        // 21. ACTIVITIES  (workspace activity log)
        // ================================================================
        $activities = [
            [$sasya, $invTask,       'created',   ['name' => 'Sistem Manajemen Inventory'],                                now()->subWeeks(4)],
            [$sasya, $invTask,       'assigned',  ['name' => 'Sistem Manajemen Inventory', 'assignee' => 'Budi Backend'],   now()->subWeeks(4)],
            [$budi,  $b2bAuthTask,   'created',   ['name' => 'Portal Login & Multi-tenant'],                                now()->subWeeks(3)],
            [$budi,  $b2bAuthTask,   'completed', ['name' => 'Portal Login & Multi-tenant'],                                now()->subWeeks(2)],
            [$dian,  $catalogTask,   'created',   ['name' => 'Product Catalog Website'],                                    now()->subWeeks(2)->subDay()],
            [$budi,  $checkoutTask,  'created',   ['name' => 'Checkout & Payment Gateway'],                                 now()->subWeeks(2)],
            [$andi,  $prodTask,      'created',   ['name' => 'Real-time Production Monitoring'],                             now()->subDays(8)],
            [$andi,  $prodTask,      'updated',   ['name' => 'Real-time Production Monitoring'],                             now()->subDays(5), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$andi,  $apiIntTask,    'created',   ['name' => 'REST API untuk Partner'],                                      now()->subDays(7)],
            [$andi,  $apiIntTask,    'updated',   ['name' => 'REST API untuk Partner'],                                      now()->subDays(5), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sasya, $orderTask,     'created',   ['name' => 'Order Management Portal'],                                     now()->subDays(6)],
            [$dian,  $catalogTask,   'updated',   ['name' => 'Product Catalog Website'],                                     now()->subDays(4), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$budi,  $checkoutTask,  'updated',   ['name' => 'Checkout & Payment Gateway'],                                  now()->subDays(3), ['status' => ['old' => 'To Do', 'new' => 'In Progress']]],
            [$sasya, $invoiceTask,   'created',   ['name' => 'Invoice & Billing Otomatis'],                                  now()->subDays(2)],
            [$andi,  $sensorTask,    'created',   ['name' => 'IoT Sensor Dashboard'],                                        now()->subDays(2)],
            [$dian,  $androidTask,   'created',   ['name' => 'Android App E-Commerce'],                                      now()->subDay()],
            [$sasya, $mfgRepTask,    'created',   ['name' => 'Laporan Produksi Bulanan'],                                    now()->subDay()],
        ];

        foreach ($activities as $act) {
            Activity::create([
                'workspace_id'  => $workspace->id,
                'user_id'       => $act[0]->id,
                'subject_type'  => get_class($act[1]),
                'subject_id'    => $act[1]->id,
                'action'        => $act[2],
                'properties'    => $act[3],
                'changes'       => $act[5] ?? null,
                'created_at'    => $act[4],
                'updated_at'    => $act[4],
            ]);
        }

        // ================================================================
        // 22. VIEWS
        // ================================================================
        View::create(['task_list_id' => $inventoryList->id, 'user_id' => $sasya->id, 'name' => 'Board',     'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['task_list_id' => $inventoryList->id, 'user_id' => $sasya->id, 'name' => 'Gantt',     'type' => 'gantt',                        'position' => 1]);
        View::create(['space_id'     => $mfgSpace->id,      'user_id' => $sasya->id, 'name' => 'All MFG Tasks', 'type' => 'list', 'is_default' => true, 'position' => 0]);
        View::create(['space_id'     => $b2bSpace->id,      'user_id' => $sasya->id, 'name' => 'B2B Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['space_id'     => $b2cSpace->id,      'user_id' => $dian->id,  'name' => 'B2C Board', 'type' => 'board', 'is_default' => true, 'position' => 0]);
        View::create(['task_list_id' => $checkoutList->id,   'user_id' => $budi->id,  'name' => 'Payment Board',  'type' => 'board', 'is_default' => true, 'position' => 0]);

        // ================================================================
        // SUMMARY
        // ================================================================
        $this->command->info('');
        $this->command->info('=== Database Seeded Successfully ===');
        $this->command->info('');
        $this->command->info('Workspace:    MIS Department');
        $this->command->info('Users:        5 (IT Manager + 4 developers)');
        $this->command->info('Spaces:       3 (Manufacturing, B2B, B2C)');
        $this->command->info('Sprints:      ' . Sprint::count());
        $this->command->info('Folders:      ' . Folder::count());
        $this->command->info('Lists:        ' . TaskList::count());
        $this->command->info('Tasks:        ' . Task::count());
        $this->command->info('Subtasks:     ' . Subtask::count());
        $this->command->info('Time Entries: ' . TimeEntry::count());
        $this->command->info('Comments:     ' . Comment::count());
        $this->command->info('Activities:   ' . Activity::count());
        $this->command->info('Views:        ' . View::count());
        $this->command->info('');
        $this->command->info('Login (all password: "password"):');
        $this->command->info('  admin@example.com  — Sasya Rahma (IT Manager / Owner)');
        $this->command->info('  andi@example.com   — Andi Fullstack');
        $this->command->info('  dian@example.com   — Dian Frontend');
        $this->command->info('  budi@example.com   — Budi Backend');
        $this->command->info('  rina@example.com   — Rina QA');
        $this->command->info('');
        $this->command->info('CPM Demo: Open "Sistem Manajemen Inventory" task -> Gantt view');
    }

    /**
     * Create a completed time entry for a subtask.
     */
    private function timeEntry(Subtask $subtask, User $user, int $minutes, $startedAt, ?string $description = null): TimeEntry
    {
        $started = \Carbon\Carbon::parse($startedAt);

        return TimeEntry::create([
            'subtask_id'  => $subtask->id,
            'user_id'     => $user->id,
            'duration'    => $minutes,
            'description' => $description,
            'started_at'  => $started,
            'ended_at'    => $started->copy()->addMinutes($minutes),
            'is_billable' => fake()->boolean(30),
            'is_running'  => false,
        ]);
    }
}
