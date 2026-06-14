<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateSeedTemplate extends Command
{
    protected $signature = 'seed:template {--path= : Output file path (default: storage/app/seed-data.xlsx)}';

    protected $description = 'Generate Excel template for seeding data (replaces seeder)';

    /** Header background color */
    private const HEADER_BG = 'FF4F46E5';

    /** Header font color */
    private const HEADER_FG = 'FFFFFFFF';

    /** Note cell background */
    private const NOTE_BG = 'FFFFF3CD';

    public function handle(): int
    {
        $path = $this->option('path') ?? storage_path('app/seed-data.xlsx');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->addLegendSheet($spreadsheet);
        $this->addUsersSheet($spreadsheet);
        $this->addWorkspaceSheet($spreadsheet);
        $this->addWorkspaceMembersSheet($spreadsheet);
        $this->addSpacesSheet($spreadsheet);
        $this->addStatusesSheet($spreadsheet);
        $this->addLabelsSheet($spreadsheet);
        $this->addFoldersSheet($spreadsheet);
        $this->addProjectsSheet($spreadsheet);
        $this->addSprintsSheet($spreadsheet);
        $this->addTasksSheet($spreadsheet);
        $this->addSubtasksSheet($spreadsheet);
        $this->addTimeEntriesSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $this->info("Template saved to: {$path}");
        $this->line('Fill in the sheets and run: <comment>php artisan seed:excel</comment>');

        return self::SUCCESS;
    }

    //  Sheet builders 

    private function addLegendSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('README');

        $rows = [
            ['PANDUAN PENGISIAN TEMPLATE SEEDER', ''],
            ['', ''],
            ['Sheet', 'Keterangan'],
            ['Users', 'Daftar akun user (nama, email, password, tarif/jam)'],
            ['Workspace', 'Data workspace utama (1 baris saja)'],
            ['WorkspaceMembers', 'Anggota workspace beserta role mereka'],
            ['Spaces', 'Ruang kerja (Space) di dalam workspace'],
            ['Statuses', 'Status kustom per Space (To Do, In Progress, dll.)'],
            ['Labels', 'Label global untuk workspace'],
            ['Folders', 'Folder di dalam Space'],
            ['Projects', 'Daftar proyek / task list di dalam Space/Folder'],
            ['Sprints', 'Sprint yang terhubung ke Project'],
            ['Tasks', 'Task utama di dalam Project'],
            ['Subtasks', 'Subtask dari Task, berikut assignee & dependensi'],
            ['TimeEntries', 'Catatan waktu kerja per Subtask'],
            ['', ''],
            ['CATATAN PENTING', ''],
            ['• Jangan hapus atau ganti nama sheet.', ''],
            ['• Kolom yang wajib diisi ditandai dengan * pada header.', ''],
            ['• Tanggal diisi dengan format: YYYY-MM-DD (contoh: 2024-01-15)', ''],
            ['• Priority Level: 1=Urgent, 2=High, 3=Normal, 4=Low', ''],
            ['• Type Status: open | in_progress | review | closed', ''],
            ['• applies_to Status: task | subtask | both', ''],
            ['• is_default, is_closed, is_active, is_billable diisi: TRUE atau FALSE', ''],
            ['• Kolom dengan banyak nilai (assignees, labels, depends_on) dipisah koma.', ''],
            ['  Contoh assignees: kevin@example.com,marvel@example.com', ''],
            ['  Contoh labels: Feature,Security', ''],
            ['  Contoh depends_on (subtask): Desain database inventory,API master barang', ''],
            ['• members di Projects diisi format email:role dipisah koma.', ''],
            ['  Contoh: kevin@example.com:project_owner,admin@example.com:project_manager', ''],
        ];

        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$i}", $row[0]);
            $sheet->setCellValue("B{$i}", $row[1]);
        }

        // Title style
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => self::HEADER_BG]],
        ]);

        // Table header style
        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::HEADER_FG]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
        ]);

        // Notes header
        $sheet->getStyle('A17')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::HEADER_BG]],
        ]);

        $sheet->getColumnDimension('A')->setWidth(60);
        $sheet->getColumnDimension('B')->setWidth(50);
    }

    private function addUsersSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Users');

        $headers = ['name *', 'email *', 'password', 'hourly_rate'];
        $examples = [
            ['Jeff', 'admin@example.com', 'password', 100000],
            ['Kevin', 'kevin@example.com', 'password', 44000],
            ['Christopher', 'christopher@example.com', 'password', 50000],
            ['Marvel', 'marvel@example.com', 'password', 50000],
            ['Devin', 'devin@example.com', 'password', 40000],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 25,
            'B' => 35,
            'C' => 20,
            'D' => 15,
        ]);

        $this->addNote($sheet, 'E1', 'password: default "password" jika dikosongkan. hourly_rate: tarif per jam dalam rupiah.');
    }

    private function addWorkspaceSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Workspace');

        $headers = ['name *', 'slug *', 'color'];
        $examples = [
            ['MIS Department', 'mis-department', '#6366F1'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 30,
            'B' => 30,
            'C' => 15,
        ]);

        $this->addNote($sheet, 'D1', 'Hanya 1 baris workspace. slug: huruf kecil, tanda hubung, unik. color: hex code.');
    }

    private function addWorkspaceMembersSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('WorkspaceMembers');

        $headers = ['email *', 'role *'];
        $examples = [
            ['admin@example.com', 'owner'],
            ['kevin@example.com', 'admin'],
            ['christopher@example.com', 'member'],
            ['marvel@example.com', 'member'],
            ['devin@example.com', 'member'],
        ];

        $this->writeSheet($sheet, $headers, $examples, ['A' => 35, 'B' => 15]);

        $this->addNote($sheet, 'C1', 'role: owner | admin | member. Email harus ada di sheet Users.');
    }

    private function addSpacesSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Spaces');

        $headers = ['name *', 'color', 'position'];
        $examples = [
            ['Manufacturing', '#F97316', 0],
            ['B2B', '#3B82F6', 1],
            ['B2C', '#10B981', 2],
        ];

        $this->writeSheet($sheet, $headers, $examples, ['A' => 25, 'B' => 15, 'C' => 12]);

        $this->addNote($sheet, 'D1', 'position: urutan tampil (0, 1, 2, ...). color: hex code.');
    }

    private function addStatusesSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Statuses');

        $headers = ['space *', 'name *', 'type *', 'color', 'position', 'applies_to', 'is_default', 'is_closed'];
        $examples = [
            ['Manufacturing', 'Backlog', 'open', '#6B7280', 0, 'both', 'FALSE', 'FALSE'],
            ['Manufacturing', 'To Do', 'open', '#3B82F6', 1, 'both', 'TRUE', 'FALSE'],
            ['Manufacturing', 'In Progress', 'in_progress', '#F59E0B', 2, 'both', 'FALSE', 'FALSE'],
            ['Manufacturing', 'Review', 'review', '#8B5CF6', 3, 'both', 'FALSE', 'FALSE'],
            ['Manufacturing', 'Done', 'closed', '#10B981', 4, 'both', 'FALSE', 'TRUE'],
            ['B2B', 'Backlog', 'open', '#6B7280', 0, 'both', 'FALSE', 'FALSE'],
            ['B2B', 'To Do', 'open', '#3B82F6', 1, 'both', 'TRUE', 'FALSE'],
            ['B2B', 'In Progress', 'in_progress', '#F59E0B', 2, 'both', 'FALSE', 'FALSE'],
            ['B2B', 'UAT', 'review', '#8B5CF6', 3, 'both', 'FALSE', 'FALSE'],
            ['B2B', 'Done', 'closed', '#10B981', 4, 'both', 'FALSE', 'TRUE'],
            ['B2C', 'Backlog', 'open', '#6B7280', 0, 'both', 'FALSE', 'FALSE'],
            ['B2C', 'To Do', 'open', '#3B82F6', 1, 'both', 'TRUE', 'FALSE'],
            ['B2C', 'In Progress', 'in_progress', '#F59E0B', 2, 'both', 'FALSE', 'FALSE'],
            ['B2C', 'Staging', 'review', '#8B5CF6', 3, 'both', 'FALSE', 'FALSE'],
            ['B2C', 'Live', 'closed', '#10B981', 4, 'both', 'FALSE', 'TRUE'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 20, 'B' => 20, 'C' => 15, 'D' => 12,
            'E' => 10, 'F' => 12, 'G' => 12, 'H' => 12,
        ]);

        $this->addNote($sheet, 'I1', 'type: open | in_progress | review | closed. applies_to: task | subtask | both.');
    }

    private function addLabelsSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Labels');

        $headers = ['name *', 'color'];
        $examples = [
            ['Bug', '#FF6B6B'],
            ['Feature', '#6BC950'],
            ['Enhancement', '#49CCF9'],
            ['Documentation', '#8B5CF6'],
            ['UI/UX', '#EC4899'],
            ['Refactor', '#F59E0B'],
            ['Security', '#EF4444'],
            ['Performance', '#14B8A6'],
        ];

        $this->writeSheet($sheet, $headers, $examples, ['A' => 20, 'B' => 15]);
    }

    private function addFoldersSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Folders');

        $headers = ['space *', 'name *', 'position', 'created_by'];
        $examples = [
            ['Manufacturing', 'ERP System', 0, 'admin@example.com'],
            ['Manufacturing', 'IoT & Monitoring', 1, 'kevin@example.com'],
            ['B2B', 'Client Portal', 0, 'admin@example.com'],
            ['B2C', 'E-Commerce', 0, 'admin@example.com'],
            ['B2C', 'Mobile App', 1, 'christopher@example.com'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 20, 'B' => 25, 'C' => 12, 'D' => 35,
        ]);

        $this->addNote($sheet, 'E1', 'space: nama Space. created_by: email user. Kosongkan created_by untuk menggunakan admin default.');
    }

    private function addProjectsSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Projects');

        $headers = ['name *', 'space *', 'folder', 'position', 'created_by', 'status_space *', 'status_name *', 'members'];
        $examples = [
            ['Inventory Module', 'Manufacturing', 'ERP System', 0, 'marvel@example.com', 'Manufacturing', 'In Progress', 'marvel@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team,devin@example.com:development_team'],
            ['Production Tracking', 'Manufacturing', 'ERP System', 1, 'marvel@example.com', 'Manufacturing', 'In Progress', 'marvel@example.com:project_owner,kevin@example.com:project_manager,christopher@example.com:development_team'],
            ['Sensor Dashboard', 'Manufacturing', 'IoT & Monitoring', 0, 'kevin@example.com', 'Manufacturing', 'To Do', 'kevin@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
            ['Reporting', 'Manufacturing', '', 2, 'admin@example.com', 'Manufacturing', 'Backlog', 'admin@example.com:project_owner,marvel@example.com:development_team,devin@example.com:development_team'],
            ['Authentication & Access', 'B2B', 'Client Portal', 0, 'marvel@example.com', 'B2B', 'Done', 'marvel@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
            ['Order Management', 'B2B', 'Client Portal', 1, 'marvel@example.com', 'B2B', 'In Progress', 'marvel@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
            ['API Integrations', 'B2B', '', 1, 'kevin@example.com', 'B2B', 'In Progress', 'kevin@example.com:project_owner,admin@example.com:project_manager'],
            ['Invoice System', 'B2B', '', 2, 'marvel@example.com', 'B2B', 'To Do', 'marvel@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
            ['Product Catalog', 'B2C', 'E-Commerce', 0, 'christopher@example.com', 'B2C', 'In Progress', 'christopher@example.com:project_owner,admin@example.com:project_manager,marvel@example.com:development_team'],
            ['Checkout & Payment', 'B2C', 'E-Commerce', 1, 'marvel@example.com', 'B2C', 'In Progress', 'marvel@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
            ['Android App', 'B2C', 'Mobile App', 0, 'christopher@example.com', 'B2C', 'To Do', 'christopher@example.com:project_owner,admin@example.com:project_manager'],
            ['Customer Support System', 'B2C', '', 2, 'kevin@example.com', 'B2C', 'To Do', 'kevin@example.com:project_owner,admin@example.com:project_manager,christopher@example.com:development_team'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 30, 'B' => 18, 'C' => 20, 'D' => 10,
            'E' => 30, 'F' => 18, 'G' => 18, 'H' => 90,
        ]);

        $this->addNote($sheet, 'I1', 'folder: nama Folder, kosongkan jika tidak ada. members: format email:role dipisah koma. Role: project_owner|project_manager|development_team|guest');
    }

    private function addSprintsSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Sprints');

        $headers = ['space *', 'list *', 'name *', 'goal', 'start_date', 'end_date', 'is_active', 'position'];
        $today = now();
        $examples = [
            ['Manufacturing', 'Inventory Module', 'MFG Sprint 1 — ERP Foundation', 'Setup modul dasar ERP: master data, inventory core', $today->copy()->subWeeks(4)->format('Y-m-d'), $today->copy()->subWeeks(2)->format('Y-m-d'), 'FALSE', 0],
            ['Manufacturing', 'Production Tracking', 'MFG Sprint 2 — Production Tracking', 'Build real-time production monitoring dan dashboard IoT', $today->copy()->subWeeks(2)->format('Y-m-d'), $today->copy()->addWeek()->format('Y-m-d'), 'TRUE', 1],
            ['Manufacturing', 'Reporting', 'MFG Sprint 3 — Reporting & Analytics', 'Laporan produksi, analisis efisiensi, export PDF/Excel', $today->copy()->addWeek()->format('Y-m-d'), $today->copy()->addWeeks(3)->format('Y-m-d'), 'FALSE', 2],
            ['B2B', 'Authentication & Access', 'B2B Sprint 1 — Client Portal', 'Portal login, order history, dan invoice management', $today->copy()->subWeeks(3)->format('Y-m-d'), $today->copy()->subWeek()->format('Y-m-d'), 'FALSE', 0],
            ['B2B', 'API Integrations', 'B2B Sprint 2 — API Integration', 'REST API untuk partner, webhook notifikasi, API docs', $today->copy()->subWeek()->format('Y-m-d'), $today->copy()->addWeek()->format('Y-m-d'), 'TRUE', 1],
            ['B2C', 'Product Catalog', 'B2C Sprint 1 — E-Commerce Core', 'Product catalog, keranjang belanja, checkout flow', $today->copy()->subWeeks(3)->format('Y-m-d'), $today->copy()->subWeek()->format('Y-m-d'), 'FALSE', 0],
            ['B2C', 'Checkout & Payment', 'B2C Sprint 2 — Payment & Shipping', 'Integrasi Midtrans, ongkir RajaOngkir, notif email', $today->copy()->subWeek()->format('Y-m-d'), $today->copy()->addWeek()->format('Y-m-d'), 'TRUE', 1],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 18, 'B' => 28, 'C' => 40, 'D' => 50,
            'E' => 14, 'F' => 14, 'G' => 12, 'H' => 10,
        ]);

        $this->addNote($sheet, 'I1', 'start_date/end_date: format YYYY-MM-DD. is_active: TRUE atau FALSE (hanya 1 sprint aktif per list).');
    }

    private function addTasksSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Tasks');

        $headers = ['name *', 'description', 'list *', 'status_space *', 'status_name *', 'priority_level', 'created_by', 'position', 'assignees', 'labels'];
        $examples = [
            ['Sistem Manajemen Inventory', 'Membangun modul inventory lengkap: master barang, stok masuk/keluar, stock opname, dan laporan inventory', 'Inventory Module', 'Manufacturing', 'In Progress', 1, 'admin@example.com', 0, 'marvel@example.com,christopher@example.com', 'Feature'],
            ['Real-time Production Monitoring', 'Dashboard monitoring produksi real-time dengan data mesin dan output per shift', 'Production Tracking', 'Manufacturing', 'In Progress', 2, 'kevin@example.com', 0, 'kevin@example.com,christopher@example.com', 'Feature,Performance'],
            ['IoT Sensor Dashboard', 'Dashboard monitoring sensor suhu, kelembaban, dan getaran mesin pabrik', 'Sensor Dashboard', 'Manufacturing', 'To Do', 3, 'kevin@example.com', 0, 'kevin@example.com', 'Feature'],
            ['Laporan Produksi Bulanan', 'Generate laporan produksi bulanan otomatis dengan export PDF dan Excel', 'Reporting', 'Manufacturing', 'Backlog', 3, 'admin@example.com', 0, '', 'Feature,Documentation'],
            ['Portal Login & Multi-tenant', 'Sistem login multi-tenant untuk client B2B dengan role-based access control', 'Authentication & Access', 'B2B', 'Done', 1, 'marvel@example.com', 0, 'marvel@example.com', 'Security,Feature'],
            ['Order Management Portal', 'Client bisa melihat order history, tracking status, dan repeat order', 'Order Management', 'B2B', 'In Progress', 2, 'admin@example.com', 0, 'marvel@example.com,christopher@example.com', 'Feature'],
            ['REST API untuk Partner', 'Public API agar partner B2B bisa kirim PO, cek stok, dan terima invoice secara otomatis', 'API Integrations', 'B2B', 'In Progress', 2, 'kevin@example.com', 0, 'kevin@example.com', 'Feature,Security'],
            ['Invoice & Billing Otomatis', 'Generate invoice otomatis dari PO, kirim via email, dan tracking pembayaran', 'Invoice System', 'B2B', 'To Do', 3, 'admin@example.com', 0, 'marvel@example.com', ''],
            ['Product Catalog Website', 'Halaman katalog produk dengan search, filter kategori, dan detail produk', 'Product Catalog', 'B2C', 'In Progress', 2, 'christopher@example.com', 0, 'christopher@example.com,marvel@example.com', 'Feature,UI/UX'],
            ['Checkout & Payment Gateway', 'Alur checkout: keranjang → shipping → pembayaran Midtrans → konfirmasi', 'Checkout & Payment', 'B2C', 'In Progress', 1, 'marvel@example.com', 0, 'marvel@example.com,christopher@example.com', 'Feature,Security'],
            ['Android App E-Commerce', 'Aplikasi Android untuk browse produk, checkout, dan tracking order', 'Android App', 'B2C', 'To Do', 3, 'christopher@example.com', 0, 'christopher@example.com', 'Feature'],
            ['Customer Support Ticketing', 'Sistem tiket untuk customer service: buka tiket, reply, escalate, resolve', 'Customer Support System', 'B2C', 'To Do', 3, 'kevin@example.com', 0, 'kevin@example.com', 'Feature'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 35, 'B' => 70, 'C' => 28, 'D' => 18,
            'E' => 18, 'F' => 16, 'G' => 30, 'H' => 10,
            'I' => 50, 'J' => 35,
        ]);

        $this->addNote($sheet, 'K1', 'priority_level: 1=Urgent, 2=High, 3=Normal, 4=Low. assignees: email dipisah koma. labels: nama label dipisah koma.');
    }

    private function addSubtasksSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('Subtasks');

        $headers = [
            'name *', 'task *', 'status_space *', 'status_name *', 'priority_level',
            'time_estimate', 'position', 'created_by', 'sprint', 'start_date',
            'due_date', 'completed_at', 'assignees', 'labels', 'depends_on',
        ];

        $today = now();
        $examples = [
            ['Desain database inventory', 'Sistem Manajemen Inventory', 'Manufacturing', 'Done', 2, 180, 0, 'marvel@example.com', 'MFG Sprint 1 — ERP Foundation', $today->copy()->subWeeks(4)->format('Y-m-d'), $today->copy()->subWeeks(4)->addDay()->format('Y-m-d'), $today->copy()->subWeeks(4)->addDay()->format('Y-m-d'), 'marvel@example.com', 'Documentation', ''],
            ['API master barang (CRUD)', 'Sistem Manajemen Inventory', 'Manufacturing', 'Done', 2, 240, 1, 'marvel@example.com', 'MFG Sprint 1 — ERP Foundation', $today->copy()->subWeeks(4)->addDay()->format('Y-m-d'), $today->copy()->subWeeks(4)->addDays(3)->format('Y-m-d'), $today->copy()->subWeeks(4)->addDays(3)->format('Y-m-d'), 'marvel@example.com', '', 'Desain database inventory'],
            ['UI halaman master barang', 'Sistem Manajemen Inventory', 'Manufacturing', 'Done', 2, 300, 2, 'christopher@example.com', 'MFG Sprint 1 — ERP Foundation', $today->copy()->subWeeks(3)->format('Y-m-d'), $today->copy()->subWeeks(3)->addDays(3)->format('Y-m-d'), $today->copy()->subWeeks(3)->addDays(3)->format('Y-m-d'), 'christopher@example.com', 'UI/UX', 'API master barang (CRUD)'],
            ['API stok masuk & keluar', 'Sistem Manajemen Inventory', 'Manufacturing', 'Done', 1, 300, 3, 'marvel@example.com', 'MFG Sprint 1 — ERP Foundation', $today->copy()->subWeeks(3)->format('Y-m-d'), $today->copy()->subWeeks(3)->addDays(3)->format('Y-m-d'), $today->copy()->subWeeks(3)->addDays(2)->format('Y-m-d'), 'marvel@example.com', '', 'Desain database inventory'],
            ['UI form stok masuk/keluar', 'Sistem Manajemen Inventory', 'Manufacturing', 'In Progress', 3, 240, 4, 'christopher@example.com', 'MFG Sprint 2 — Production Tracking', $today->copy()->subDays(5)->format('Y-m-d'), $today->copy()->addDay()->format('Y-m-d'), '', 'christopher@example.com', 'UI/UX', 'API stok masuk & keluar,UI halaman master barang'],
            ['Fitur stock opname', 'Sistem Manajemen Inventory', 'Manufacturing', 'In Progress', 3, 360, 5, 'marvel@example.com', 'MFG Sprint 2 — Production Tracking', $today->copy()->subDays(3)->format('Y-m-d'), $today->copy()->addDays(3)->format('Y-m-d'), '', 'marvel@example.com', '', 'API stok masuk & keluar'],
            ['Barcode/QR code scanner', 'Sistem Manajemen Inventory', 'Manufacturing', 'To Do', 3, 180, 6, 'christopher@example.com', 'MFG Sprint 2 — Production Tracking', '', $today->copy()->addDays(5)->format('Y-m-d'), '', 'christopher@example.com', 'Feature', 'UI form stok masuk/keluar'],
            ['Laporan stok (PDF/Excel)', 'Sistem Manajemen Inventory', 'Manufacturing', 'Backlog', 4, 240, 7, 'marvel@example.com', 'MFG Sprint 3 — Reporting & Analytics', '', $today->copy()->addWeeks(2)->format('Y-m-d'), '', 'marvel@example.com', '', 'Fitur stock opname,Barcode/QR code scanner'],
            ['Integration testing inventory', 'Sistem Manajemen Inventory', 'Manufacturing', 'Backlog', 2, 300, 8, 'devin@example.com', 'MFG Sprint 3 — Reporting & Analytics', '', $today->copy()->addWeeks(2)->addDays(3)->format('Y-m-d'), '', 'devin@example.com', 'Security', 'Laporan stok (PDF/Excel)'],
            ['Deploy modul inventory ke production', 'Sistem Manajemen Inventory', 'Manufacturing', 'Backlog', 1, 60, 9, 'admin@example.com', 'MFG Sprint 3 — Reporting & Analytics', '', $today->copy()->addWeeks(3)->format('Y-m-d'), '', 'admin@example.com', '', 'Integration testing inventory'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 35, 'B' => 35, 'C' => 18, 'D' => 18, 'E' => 16,
            'F' => 15, 'G' => 10, 'H' => 30, 'I' => 40, 'J' => 14,
            'K' => 14, 'L' => 14, 'M' => 40, 'N' => 25, 'O' => 60,
        ]);

        $this->addNote($sheet, 'P1', 'time_estimate: dalam menit. sprint: nama sprint (boleh kosong). completed_at: kosongkan jika belum selesai. depends_on: nama subtask dipisah koma (harus ada di sheet ini).');
    }

    private function addTimeEntriesSheet(Spreadsheet $s): void
    {
        $sheet = $s->createSheet();
        $sheet->setTitle('TimeEntries');

        $headers = ['subtask *', 'user_email *', 'minutes *', 'started_at', 'is_billable'];
        $today = now();
        $examples = [
            ['Desain database inventory', 'marvel@example.com', 160, $today->copy()->subWeeks(4)->format('Y-m-d H:i:s'), 'TRUE'],
            ['API master barang (CRUD)', 'marvel@example.com', 220, $today->copy()->subWeeks(4)->addDay()->format('Y-m-d H:i:s'), 'TRUE'],
            ['UI halaman master barang', 'christopher@example.com', 280, $today->copy()->subWeeks(3)->format('Y-m-d H:i:s'), 'FALSE'],
            ['API stok masuk & keluar', 'marvel@example.com', 260, $today->copy()->subWeeks(3)->format('Y-m-d H:i:s'), 'TRUE'],
            ['API stok masuk & keluar', 'marvel@example.com', 40, $today->copy()->subWeeks(3)->addDays(2)->format('Y-m-d H:i:s'), 'FALSE'],
            ['UI form stok masuk/keluar', 'christopher@example.com', 120, $today->copy()->subDays(5)->format('Y-m-d H:i:s'), 'FALSE'],
            ['Fitur stock opname', 'marvel@example.com', 90, $today->copy()->subDays(3)->format('Y-m-d H:i:s'), 'TRUE'],
        ];

        $this->writeSheet($sheet, $headers, $examples, [
            'A' => 35, 'B' => 30, 'C' => 12,
            'D' => 22, 'F' => 12,
        ]);

        $this->addNote($sheet, 'F1', 'started_at: format YYYY-MM-DD HH:MM:SS. is_billable: TRUE atau FALSE. subtask harus ada di sheet Subtasks.');
    }

    //  Helpers 

    /**
     * Write headers + example rows with styled header row.
     *
     * @param  array<string>       $headers
     * @param  array<array<mixed>> $rows
     * @param  array<string, int>  $colWidths  map of col letter => width
     */
    private function writeSheet($sheet, array $headers, array $rows, array $colWidths = []): void
    {
        // Write header row
        foreach ($headers as $col => $title) {
            $letter = chr(65 + $col);
            $cell = "{$letter}1";
            $sheet->setCellValue($cell, $title);
        }

        // Style header
        $lastCol = chr(64 + count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::HEADER_FG]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']],
            ],
        ]);

        // Freeze header row
        $sheet->freezePane('A2');

        // Write data rows
        foreach ($rows as $rowIdx => $row) {
            $excelRow = $rowIdx + 2;
            foreach ($row as $col => $value) {
                $letter = chr(65 + $col);
                $sheet->setCellValue("{$letter}{$excelRow}", $value);
            }

            // Stripe alternating rows
            if ($rowIdx % 2 === 1) {
                $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
                ]);
            }
        }

        // Apply column widths
        foreach ($colWidths as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        // Auto-filter
        $sheet->setAutoFilter("A1:{$lastCol}1");
    }

    /**
     * Add a yellow note cell to a sheet.
     */
    private function addNote($sheet, string $cell, string $text): void
    {
        $sheet->setCellValue($cell, '📝 ' . $text);
        $sheet->getStyle($cell)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::NOTE_BG]],
            'font' => ['italic' => true, 'size' => 10],
            'alignment' => ['wrapText' => true],
        ]);
    }
}
