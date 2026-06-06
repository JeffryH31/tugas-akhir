<?php

/**
 * generate-testing-data.php
 *
 * Standalone PHP script — NO database connection needed.
 * Generates a complete, realistic .sql file for testing.
 *
 * Usage:
 *   php generate-testing-data.php
 *
 * Output:
 *   database/testing-data.sql
 *
 * All timestamps follow working hours (08:00-17:00 WIB, break 12:00-13:00)
 * Period: 27 April 2026 – 22 May 2026 (4 weeks / 4 sprints)
 */

// ═══════════════════════════════════════════════════════════════════════════════
// CONFIGURATION — Edit these sections to customize the data
// ═══════════════════════════════════════════════════════════════════════════════

$PASSWORD_HASH = '$2y$12$LQv3c1yqBo9SkvXJgDOR7eGxnLsKp0kbT5mE1V6dOyWX1bLwCE4MG'; // "password"

$CONFIG = [
    'workspace_name' => 'MIS Department',
    'workspace_slug' => 'mis-department',
    'workspace_color' => '#6366F1',

    // Period: 27 April – 22 May 2026
    'start_date' => '2026-04-27',
    'end_date'   => '2026-05-22',

    // Working hours
    'work_start' => 8,  // 08:00
    'work_end'   => 17, // 17:00
    'break_start' => 12,
    'break_end'   => 13,

    // Sprint duration in days (Mon-Fri = 5 work days)
    'sprint_duration_days' => 7, // calendar days (1 week)
];

// ─── Users ───────────────────────────────────────────────────────────────────
// Leo & Gilbert = workspace owners; they are admin in all spaces
$USERS = [
    // Workspace Owners / Admins (present in all spaces)
    ['name' => 'Leo',       'email' => 'leo@example.com',       'hourly_rate' => 150000, 'ws_role' => 'owner'],
    ['name' => 'Gilbert',   'email' => 'gilbert@example.com',   'hourly_rate' => 150000, 'ws_role' => 'owner'],

    // Manufacture team
    ['name' => 'Aji',       'email' => 'aji@example.com',       'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Mario',     'email' => 'mario@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Grace',     'email' => 'grace@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Alief',     'email' => 'alief@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],

    // B2B team
    ['name' => 'Vincent',   'email' => 'vincent@example.com',   'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Stanley',   'email' => 'stanley@example.com',   'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Moses',     'email' => 'moses@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Stefanie',  'email' => 'stefanie@example.com',  'hourly_rate' => 75000, 'ws_role' => 'member'],

    // B2C team
    ['name' => 'Andry',     'email' => 'andry@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Gita',      'email' => 'gita@example.com',      'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Justin',    'email' => 'justin@example.com',    'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Charlie',   'email' => 'charlie@example.com',   'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Frans',     'email' => 'frans@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Audi',      'email' => 'audi@example.com',      'hourly_rate' => 75000, 'ws_role' => 'member'],

    // Data team
    ['name' => 'Mira',      'email' => 'mira@example.com',      'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Clarissa',  'email' => 'clarissa@example.com',  'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Danny',     'email' => 'danny@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Nicko',     'email' => 'nicko@example.com',     'hourly_rate' => 75000, 'ws_role' => 'member'],
    ['name' => 'Amel',      'email' => 'amel@example.com',      'hourly_rate' => 75000, 'ws_role' => 'member'],
];

// ─── Spaces ──────────────────────────────────────────────────────────────────
$SPACES = [
    [
        'name'    => 'Manufacture',
        'color'   => '#F97316',
        'members' => ['Leo', 'Gilbert', 'Aji', 'Mario', 'Grace', 'Alief'],
        'admins'  => ['Leo', 'Gilbert'], // space-level admins
    ],
    [
        'name'    => 'B2B',
        'color'   => '#3B82F6',
        'members' => ['Leo', 'Gilbert', 'Vincent', 'Stanley', 'Moses', 'Stefanie'],
        'admins'  => ['Leo', 'Gilbert'],
    ],
    [
        'name'    => 'B2C',
        'color'   => '#10B981',
        'members' => ['Leo', 'Gilbert', 'Andry', 'Gita', 'Justin', 'Charlie', 'Frans', 'Audi'],
        'admins'  => ['Leo', 'Gilbert'],
    ],
    [
        'name'    => 'Data',
        'color'   => '#8B5CF6',
        'members' => ['Leo', 'Gilbert', 'Mira', 'Clarissa', 'Stanley', 'Danny', 'Nicko', 'Amel'],
        'admins'  => ['Leo', 'Gilbert'],
    ],
];

// ─── Projects (mapped to spaces) ────────────────────────────────────────────
$PROJECTS = [
    // Manufacture
    ['name' => 'MRP Forecast Simulation',                        'space' => 'Manufacture'],
    ['name' => 'Product Monitor - Frame Number Management',      'space' => 'Manufacture'],
    ['name' => 'Product Monitor - Scan Bike Part Serial Number', 'space' => 'Manufacture'],
    ['name' => 'Promotion Stock Management',                     'space' => 'Manufacture'],

    // B2B
    ['name' => 'Vendor Invoicing and Automatic MIRO',                       'space' => 'B2B'],
    ['name' => 'Booking & Bidding Shipment for Vendor Freight Forwarder',   'space' => 'B2B'],
    ['name' => 'Invoice Process Automation',                                'space' => 'B2B'],
    ['name' => 'Sales Reps Dashboard',                                      'space' => 'B2B'],
    ['name' => 'Applicant Recruitment Agentic AI',                          'space' => 'B2B'],

    // B2C
    ['name' => 'Bike & PAA Catalog Journey Revamp',                         'space' => 'B2C'],
    ['name' => 'AI E-Commerce Product Recommendation',                      'space' => 'B2C'],
    ['name' => 'AI Up-selling, Cross-selling, Re-selling',                  'space' => 'B2C'],
    ['name' => 'B2C x POS x Marketplace - Realtime Stock Integration',     'space' => 'B2C'],
    ['name' => 'Bike Fitting Integration',                                  'space' => 'B2C'],
    ['name' => 'Bike Service Booking',                                      'space' => 'B2C'],
    ['name' => 'Click & Collect',                                           'space' => 'B2C'],
    ['name' => 'E-commerce Product Description Generation',                 'space' => 'B2C'],
    ['name' => 'Membership - Referral Program & Remake',                    'space' => 'B2C'],
    ['name' => 'Net Promoter Score',                                        'space' => 'B2C'],
    ['name' => 'Test Ride Booking',                                         'space' => 'B2C'],

    // Data
    ['name' => 'AI Customer Service',                                       'space' => 'Data'],
    ['name' => 'Catalog Search Correction & Suggestion',                    'space' => 'Data'],
    ['name' => 'Catalog Bike Recommendation based on Dealer',               'space' => 'Data'],
];

// ─── Labels ──────────────────────────────────────────────────────────────────
$LABELS = [
    ['name' => 'Bug',           'color' => '#EF4444'],
    ['name' => 'Feature',       'color' => '#3B82F6'],
    ['name' => 'Enhancement',   'color' => '#10B981'],
    ['name' => 'Documentation', 'color' => '#6B7280'],
    ['name' => 'UI/UX',         'color' => '#8B5CF6'],
    ['name' => 'Refactor',      'color' => '#F59E0B'],
    ['name' => 'Security',      'color' => '#DC2626'],
    ['name' => 'Performance',   'color' => '#0EA5E9'],
    ['name' => 'API',           'color' => '#14B8A6'],
    ['name' => 'AI/ML',         'color' => '#A855F7'],
];

// ═══════════════════════════════════════════════════════════════════════════════
// END CONFIGURATION — Below is the generator engine
// ═══════════════════════════════════════════════════════════════════════════════

echo "╔══════════════════════════════════════════════╗\n";
echo "║   Testing Data Generator                    ║\n";
echo "║   Period: {$CONFIG['start_date']} → {$CONFIG['end_date']}   ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// ─── Helper functions ────────────────────────────────────────────────────────

function esc($value): string {
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? '1' : '0';
    if (is_int($value) || is_float($value)) return (string) $value;
    $escaped = str_replace(
        ['\\', "'", "\r\n", "\n", "\r", "\x00", "\x1a"],
        ['\\\\', "''", "\\r\\n", "\\n", "\\r", "\\0", "\\Z"],
        (string) $value
    );
    return "'" . $escaped . "'";
}

function buildInsert(string $table, array $rows): string {
    if (empty($rows)) return '';
    $columns = array_keys($rows[0]);
    $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));
    $valueGroups = [];
    foreach ($rows as $row) {
        $values = array_map('esc', array_values($row));
        $valueGroups[] = '(' . implode(', ', $values) . ')';
    }
    return "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valueGroups) . ";\n";
}

/**
 * Get working days (Mon-Fri) between two dates.
 */
function getWorkingDays(string $start, string $end): array {
    $days = [];
    $current = new DateTime($start);
    $endDt = new DateTime($end);
    while ($current <= $endDt) {
        $dow = (int) $current->format('N'); // 1=Mon, 7=Sun
        if ($dow <= 5) {
            $days[] = $current->format('Y-m-d');
        }
        $current->modify('+1 day');
    }
    return $days;
}

/**
 * Generate a random working timestamp on a given date.
 * Respects 08:00-12:00 and 13:00-17:00 work slots.
 */
function randomWorkTime(string $date, int $minHour = 8, int $maxHour = 17): string {
    // Available slots: 8-12, 13-17 (8 hours of work)
    $slots = [];
    for ($h = 8; $h < 12; $h++) $slots[] = $h;
    for ($h = 13; $h < 17; $h++) $slots[] = $h;

    $filtered = array_filter($slots, fn($h) => $h >= $minHour && $h < $maxHour);
    if (empty($filtered)) $filtered = $slots;

    $hour = $filtered[array_rand($filtered)];
    $minute = rand(0, 59);
    $second = rand(0, 59);
    return sprintf('%s %02d:%02d:%02d', $date, $hour, $minute, $second);
}

/**
 * Generate a working timestamp at a specific hour on a date.
 */
function workTime(string $date, int $hour, int $minute = 0): string {
    return sprintf('%s %02d:%02d:00', $date, $hour, $minute);
}

/**
 * Slug helper.
 */
function toSlug(string $text): string {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    return preg_replace('/-+/', '-', $slug);
}

/**
 * Pick N random items from array.
 */
function pickRandom(array $items, int $count): array {
    if ($count >= count($items)) return $items;
    $keys = array_rand($items, $count);
    if (!is_array($keys)) $keys = [$keys];
    return array_map(fn($k) => $items[$k], $keys);
}

// ─── ID Counters ─────────────────────────────────────────────────────────────
$idCounters = [];
function nextId(string $table): int {
    global $idCounters;
    if (!isset($idCounters[$table])) $idCounters[$table] = 0;
    return ++$idCounters[$table];
}

// ─── Collect all SQL rows ────────────────────────────────────────────────────
$sql_rows = [];
$now = date('Y-m-d H:i:s');

// ═══════════════════════════════════════════════════════════════════════════════
// 1. USERS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[1/14] Generating users...\n";

$userIdMap = []; // name => id
foreach ($USERS as $u) {
    $id = nextId('users');
    $userIdMap[$u['name']] = $id;
    $sql_rows['users'][] = [
        'id' => $id,
        'name' => $u['name'],
        'email' => $u['email'],
        'email_verified_at' => $now,
        'password' => $PASSWORD_HASH,
        'hourly_rate' => $u['hourly_rate'],
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'remember_token' => null,
        'profile_photo_path' => null,
        'last_notifications_read_at' => null,
        'created_at' => '2026-04-25 08:00:00',
        'updated_at' => '2026-04-25 08:00:00',
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. WORKSPACE
// ═══════════════════════════════════════════════════════════════════════════════
echo "[2/14] Generating workspace...\n";

$workspaceId = nextId('workspaces');
$sql_rows['workspaces'][] = [
    'id' => $workspaceId,
    'name' => $CONFIG['workspace_name'],
    'slug' => $CONFIG['workspace_slug'],
    'color' => $CONFIG['workspace_color'],
    'is_personal' => 0,
    'created_at' => '2026-04-25 08:00:00',
    'updated_at' => '2026-04-25 08:00:00',
    'deleted_at' => null,
];

// Workspace members
foreach ($USERS as $u) {
    $sql_rows['workspace_members'][] = [
        'id' => nextId('workspace_members'),
        'workspace_id' => $workspaceId,
        'user_id' => $userIdMap[$u['name']],
        'role' => $u['ws_role'],
        'created_at' => '2026-04-25 08:00:00',
        'updated_at' => '2026-04-25 08:00:00',
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// 3. SPACES & SPACE MEMBERS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[3/14] Generating spaces...\n";

$spaceIdMap = []; // name => id
foreach ($SPACES as $pos => $s) {
    $id = nextId('spaces');
    $spaceIdMap[$s['name']] = $id;
    $sql_rows['spaces'][] = [
        'id' => $id,
        'workspace_id' => $workspaceId,
        'name' => $s['name'],
        'slug' => toSlug($s['name']),
        'color' => $s['color'],
        'position' => $pos,
        'created_by' => $userIdMap['Leo'],
        'created_at' => '2026-04-25 08:00:00',
        'updated_at' => '2026-04-25 08:00:00',
        'deleted_at' => null,
    ];

    foreach ($s['members'] as $memberName) {
        $role = in_array($memberName, $s['admins']) ? 'admin' : 'member';
        $sql_rows['space_members'][] = [
            'id' => nextId('space_members'),
            'space_id' => $id,
            'user_id' => $userIdMap[$memberName],
            'role' => $role,
            'created_at' => '2026-04-25 08:00:00',
            'updated_at' => '2026-04-25 08:00:00',
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 4. STATUSES (per space)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[4/14] Generating statuses...\n";

$statusDefs = [
    ['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'is_default' => 0, 'is_closed' => 0],
    ['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'is_default' => 1, 'is_closed' => 0],
    ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'is_default' => 0, 'is_closed' => 0],
    ['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'is_default' => 0, 'is_closed' => 0],
    ['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'is_default' => 0, 'is_closed' => 1],
];

$statusIdMap = []; // "SpaceName:StatusName" => id
foreach ($SPACES as $space) {
    foreach ($statusDefs as $pos => $st) {
        $id = nextId('statuses');
        $statusIdMap[$space['name'] . ':' . $st['name']] = $id;
        $sql_rows['statuses'][] = [
            'id' => $id,
            'space_id' => $spaceIdMap[$space['name']],
            'name' => $st['name'],
            'slug' => toSlug($st['name']),
            'color' => $st['color'],
            'type' => $st['type'],
            'applies_to' => 'both',
            'position' => $pos,
            'is_default' => $st['is_default'],
            'is_closed' => $st['is_closed'],
            'created_at' => '2026-04-25 08:00:00',
            'updated_at' => '2026-04-25 08:00:00',
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 5. LABELS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[5/14] Generating labels...\n";

$labelIdMap = []; // name => id
foreach ($LABELS as $l) {
    $id = nextId('labels');
    $labelIdMap[$l['name']] = $id;
    $sql_rows['labels'][] = [
        'id' => $id,
        'workspace_id' => $workspaceId,
        'space_id' => null,
        'name' => $l['name'],
        'color' => $l['color'],
        'description' => null,
        'created_at' => '2026-04-25 08:00:00',
        'updated_at' => '2026-04-25 08:00:00',
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// 6. FOLDERS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[6/14] Generating folders...\n";

// Group projects by space to determine folder structure
$spaceFolders = [
    'Manufacture' => [
        ['name' => 'Product Monitoring', 'color' => '#EF4444'],
        ['name' => 'Planning & Stock',   'color' => '#F97316'],
    ],
    'B2B' => [
        ['name' => 'Finance & Invoicing', 'color' => '#3B82F6'],
        ['name' => 'Operations',          'color' => '#0EA5E9'],
    ],
    'B2C' => [
        ['name' => 'E-Commerce',     'color' => '#10B981'],
        ['name' => 'Bike Services',  'color' => '#14B8A6'],
        ['name' => 'Customer Engagement', 'color' => '#8B5CF6'],
    ],
    'Data' => [
        ['name' => 'AI & ML',        'color' => '#A855F7'],
        ['name' => 'Catalog Intelligence', 'color' => '#6366F1'],
    ],
];

$folderIdMap = []; // name => id
foreach ($spaceFolders as $spaceName => $folders) {
    foreach ($folders as $pos => $f) {
        $id = nextId('folders');
        $folderIdMap[$f['name']] = $id;
        $sql_rows['folders'][] = [
            'id' => $id,
            'space_id' => $spaceIdMap[$spaceName],
            'parent_id' => null,
            'name' => $f['name'],
            'slug' => toSlug($f['name']),
            'description' => null,
            'color' => $f['color'],
            'is_hidden' => 0,
            'position' => $pos,
            'created_by' => $userIdMap['Leo'],
            'created_at' => '2026-04-25 08:00:00',
            'updated_at' => '2026-04-25 08:00:00',
            'deleted_at' => null,
        ];
    }
}

// Map each project to a folder
$projectFolderMap = [
    'MRP Forecast Simulation' => 'Planning & Stock',
    'Product Monitor - Frame Number Management' => 'Product Monitoring',
    'Product Monitor - Scan Bike Part Serial Number' => 'Product Monitoring',
    'Promotion Stock Management' => 'Planning & Stock',
    'Vendor Invoicing and Automatic MIRO' => 'Finance & Invoicing',
    'Booking & Bidding Shipment for Vendor Freight Forwarder' => 'Operations',
    'Invoice Process Automation' => 'Finance & Invoicing',
    'Sales Reps Dashboard' => 'Operations',
    'Applicant Recruitment Agentic AI' => 'Operations',
    'Bike & PAA Catalog Journey Revamp' => 'E-Commerce',
    'AI E-Commerce Product Recommendation' => 'E-Commerce',
    'AI Up-selling, Cross-selling, Re-selling' => 'E-Commerce',
    'B2C x POS x Marketplace - Realtime Stock Integration' => 'E-Commerce',
    'Bike Fitting Integration' => 'Bike Services',
    'Bike Service Booking' => 'Bike Services',
    'Click & Collect' => 'Bike Services',
    'E-commerce Product Description Generation' => 'E-Commerce',
    'Membership - Referral Program & Remake' => 'Customer Engagement',
    'Net Promoter Score' => 'Customer Engagement',
    'Test Ride Booking' => 'Bike Services',
    'AI Customer Service' => 'AI & ML',
    'Catalog Search Correction & Suggestion' => 'Catalog Intelligence',
    'Catalog Bike Recommendation based on Dealer' => 'Catalog Intelligence',
];

// ═══════════════════════════════════════════════════════════════════════════════
// 7. PROJECTS & PROJECT MEMBERS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[7/14] Generating projects...\n";

$projectIdMap = []; // name => id
$projectSpaceMap = []; // project name => space name
$positionByFolder = [];

foreach ($PROJECTS as $p) {
    $id = nextId('projects');
    $projectIdMap[$p['name']] = $id;
    $projectSpaceMap[$p['name']] = $p['space'];
    $spaceId = $spaceIdMap[$p['space']];
    $folderName = $projectFolderMap[$p['name']] ?? null;
    $folderId = $folderName ? ($folderIdMap[$folderName] ?? null) : null;

    $posKey = $p['space'] . ':' . ($folderName ?? '_root');
    if (!isset($positionByFolder[$posKey])) $positionByFolder[$posKey] = 0;
    $position = $positionByFolder[$posKey]++;

    // Random status for the project — weighted towards In Progress / To Do
    $projectStatuses = ['In Progress', 'In Progress', 'To Do', 'Review', 'Backlog'];
    $statusName = $projectStatuses[array_rand($projectStatuses)];
    $statusId = $statusIdMap[$p['space'] . ':' . $statusName];

    $sql_rows['projects'][] = [
        'id' => $id,
        'space_id' => $spaceId,
        'folder_id' => $folderId,
        'status_id' => $statusId,
        'name' => $p['name'],
        'slug' => toSlug($p['name']),
        'description' => null,
        'color' => null,
        'icon' => null,
        'is_archived' => 0,
        'position' => $position,
        'settings' => null,
        'created_by' => $userIdMap['Leo'],
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
        'deleted_at' => null,
    ];

    // Assign project members from the space members (excluding global admins for dev role, they get owner)
    $spaceConfig = null;
    foreach ($SPACES as $sc) {
        if ($sc['name'] === $p['space']) { $spaceConfig = $sc; break; }
    }
    $devMembers = array_filter($spaceConfig['members'], fn($n) => !in_array($n, ['Leo', 'Gilbert']));
    $devMembers = array_values($devMembers);

    // Leo = project_owner, pick 1 random dev as project_manager, rest = development_team
    $sql_rows['project_members'][] = [
        'id' => nextId('project_members'),
        'project_id' => $id,
        'user_id' => $userIdMap['Leo'],
        'role' => 'project_owner',
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
    ];

    // Pick a PM randomly
    $pmIndex = array_rand($devMembers);
    $pmName = $devMembers[$pmIndex];
    $sql_rows['project_members'][] = [
        'id' => nextId('project_members'),
        'project_id' => $id,
        'user_id' => $userIdMap[$pmName],
        'role' => 'project_manager',
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
    ];

    // Rest as dev team
    foreach ($devMembers as $idx => $devName) {
        if ($idx === $pmIndex) continue;
        $sql_rows['project_members'][] = [
            'id' => nextId('project_members'),
            'project_id' => $id,
            'user_id' => $userIdMap[$devName],
            'role' => 'development_team',
            'created_at' => '2026-04-25 09:00:00',
            'updated_at' => '2026-04-25 09:00:00',
        ];
    }

    // Gilbert as project_manager on some projects
    if (rand(0, 1) === 1) {
        $sql_rows['project_members'][] = [
            'id' => nextId('project_members'),
            'project_id' => $id,
            'user_id' => $userIdMap['Gilbert'],
            'role' => 'project_manager',
            'created_at' => '2026-04-25 09:00:00',
            'updated_at' => '2026-04-25 09:00:00',
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 8. SPRINTS (4 sprints per project, 1 week each)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[8/14] Generating sprints...\n";

$sprintIdMap = []; // "ProjectName:SprintN" => id
$sprintWeeks = [
    ['start' => '2026-04-27', 'end' => '2026-05-03', 'name' => 'Sprint 1'],
    ['start' => '2026-05-04', 'end' => '2026-05-10', 'name' => 'Sprint 2'],
    ['start' => '2026-05-11', 'end' => '2026-05-17', 'name' => 'Sprint 3'],
    ['start' => '2026-05-18', 'end' => '2026-05-22', 'name' => 'Sprint 4'],
];

foreach ($PROJECTS as $p) {
    $spaceId = $spaceIdMap[$p['space']];
    $projectId = $projectIdMap[$p['name']];

    foreach ($sprintWeeks as $sprintPos => $sw) {
        $id = nextId('sprints');
        $key = $p['name'] . ':Sprint ' . ($sprintPos + 1);
        $sprintIdMap[$key] = $id;

        // Sprint 1 & 2 are completed, Sprint 3 is active (current), Sprint 4 is upcoming
        $isActive = ($sprintPos === 2) ? 1 : 0;

        // Generate a meaningful sprint goal
        $goals = [
            "Setup fondasi dan analisis kebutuhan proyek",
            "Implementasi fitur core dan integrasi API",
            "Pengembangan UI/UX dan testing",
            "Finalisasi, review, dan deployment preparation",
        ];

        $sql_rows['sprints'][] = [
            'id' => $id,
            'space_id' => $spaceId,
            'project_id' => $projectId,
            'name' => $sw['name'] . ' - ' . substr($p['name'], 0, 30),
            'goal' => $goals[$sprintPos],
            'start_date' => $sw['start'],
            'end_date' => $sw['end'],
            'is_active' => $isActive,
            'position' => $sprintPos,
            'created_at' => '2026-04-25 09:00:00',
            'updated_at' => '2026-04-25 09:00:00',
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// 9. TASKS (1-2 tasks per project, each task = one work stream)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[9/14] Generating tasks...\n";

$taskIdMap = []; // "ProjectName:TaskN" => id
$taskCounter = 0;

// Define task templates per project
$taskTemplates = [
    'MRP Forecast Simulation' => [
        ['name' => 'Demand Forecasting Engine', 'desc' => "Engine untuk forecasting demand:\n- Data historis penjualan\n- Machine learning model\n- Parameter konfigurasi\n- Visualisasi hasil prediksi", 'labels' => ['Feature', 'AI/ML']],
        ['name' => 'Production Planning Module', 'desc' => "Modul perencanaan produksi:\n- Kapasitas mesin\n- BOM (Bill of Materials)\n- Scheduling optimasi\n- Alert kekurangan material", 'labels' => ['Feature']],
    ],
    'Product Monitor - Frame Number Management' => [
        ['name' => 'Frame Number Registration System', 'desc' => "Sistem registrasi nomor rangka:\n- Input manual & scan\n- Validasi format\n- Database tracking\n- History log perubahan", 'labels' => ['Feature', 'Security']],
    ],
    'Product Monitor - Scan Bike Part Serial Number' => [
        ['name' => 'Serial Number Scanner Integration', 'desc' => "Integrasi scanner:\n- Barcode reader API\n- QR code support\n- Batch scanning\n- Real-time validation", 'labels' => ['Feature', 'API']],
    ],
    'Promotion Stock Management' => [
        ['name' => 'Promotion Allocation Engine', 'desc' => "Engine alokasi promosi:\n- Stok khusus promo\n- Auto-reserve\n- Expiry management\n- Dashboard monitoring stok promo", 'labels' => ['Feature']],
    ],
    'Vendor Invoicing and Automatic MIRO' => [
        ['name' => 'Automated MIRO Processing', 'desc' => "Proses MIRO otomatis:\n- Invoice matching\n- 3-way match (PO, GR, Invoice)\n- Auto-posting SAP\n- Exception handling", 'labels' => ['Feature', 'API']],
        ['name' => 'Vendor Invoice Portal', 'desc' => "Portal vendor:\n- Upload invoice\n- Status tracking\n- Document management\n- Notifikasi otomatis", 'labels' => ['Feature', 'UI/UX']],
    ],
    'Booking & Bidding Shipment for Vendor Freight Forwarder' => [
        ['name' => 'Shipment Bidding Platform', 'desc' => "Platform bidding pengiriman:\n- Vendor registration\n- Bidding flow\n- Auto-award rules\n- Rate comparison", 'labels' => ['Feature']],
    ],
    'Invoice Process Automation' => [
        ['name' => 'OCR Invoice Extraction', 'desc' => "Ekstraksi data invoice:\n- OCR processing\n- Template matching\n- Data validation\n- Auto-fill form", 'labels' => ['Feature', 'AI/ML']],
    ],
    'Sales Reps Dashboard' => [
        ['name' => 'Sales Performance Dashboard', 'desc' => "Dashboard performa sales:\n- KPI metrics\n- Territory mapping\n- Pipeline tracking\n- Leaderboard", 'labels' => ['Feature', 'UI/UX']],
    ],
    'Applicant Recruitment Agentic AI' => [
        ['name' => 'AI Resume Screening Agent', 'desc' => "Agent AI untuk screening:\n- Resume parsing\n- Skill matching\n- Scoring algorithm\n- Shortlist recommendation", 'labels' => ['Feature', 'AI/ML']],
    ],
    'Bike & PAA Catalog Journey Revamp' => [
        ['name' => 'Catalog UX Redesign', 'desc' => "Redesign pengalaman katalog:\n- New navigation flow\n- Product comparison\n- Filtering enhancement\n- Mobile responsive", 'labels' => ['Feature', 'UI/UX']],
        ['name' => 'PAA Integration', 'desc' => "Parts & Accessories integration:\n- Cross-sell suggestions\n- Compatibility checker\n- Bundle pricing\n- Inventory sync", 'labels' => ['Feature', 'API']],
    ],
    'AI E-Commerce Product Recommendation' => [
        ['name' => 'Recommendation Engine', 'desc' => "Engine rekomendasi produk:\n- Collaborative filtering\n- Content-based filtering\n- Hybrid approach\n- A/B testing framework", 'labels' => ['Feature', 'AI/ML']],
    ],
    'AI Up-selling, Cross-selling, Re-selling' => [
        ['name' => 'Upsell/Cross-sell Engine', 'desc' => "Engine upsell & cross-sell:\n- Rule-based suggestions\n- ML-based predictions\n- Cart analysis\n- Email trigger", 'labels' => ['Feature', 'AI/ML']],
    ],
    'B2C x POS x Marketplace - Realtime Stock Integration' => [
        ['name' => 'Realtime Stock Sync', 'desc' => "Sinkronisasi stok real-time:\n- Multi-channel inventory\n- Event-driven updates\n- Conflict resolution\n- Dashboard monitoring", 'labels' => ['Feature', 'API', 'Performance']],
    ],
    'Bike Fitting Integration' => [
        ['name' => 'Bike Fitting Calculator', 'desc' => "Kalkulator bike fitting:\n- Body measurement input\n- Size recommendation\n- Frame geometry matching\n- Adjustment suggestions", 'labels' => ['Feature', 'UI/UX']],
    ],
    'Bike Service Booking' => [
        ['name' => 'Service Booking System', 'desc' => "Sistem booking servis:\n- Calendar slot management\n- Mechanic assignment\n- Service package selection\n- Reminder notification", 'labels' => ['Feature']],
    ],
    'Click & Collect' => [
        ['name' => 'Click & Collect Flow', 'desc' => "Alur click & collect:\n- Store availability check\n- Pickup slot booking\n- QR code generation\n- Status notification", 'labels' => ['Feature', 'UI/UX']],
    ],
    'E-commerce Product Description Generation' => [
        ['name' => 'AI Description Generator', 'desc' => "Generator deskripsi produk:\n- GPT integration\n- Template management\n- Multi-language\n- SEO optimization", 'labels' => ['Feature', 'AI/ML']],
    ],
    'Membership - Referral Program & Remake' => [
        ['name' => 'Referral System Revamp', 'desc' => "Revamp sistem referral:\n- Reward tier system\n- Tracking dashboard\n- Payout automation\n- Fraud detection", 'labels' => ['Feature', 'Enhancement']],
    ],
    'Net Promoter Score' => [
        ['name' => 'NPS Survey System', 'desc' => "Sistem survey NPS:\n- Email/SMS trigger\n- Response collection\n- Score calculation\n- Trend analysis dashboard", 'labels' => ['Feature', 'UI/UX']],
    ],
    'Test Ride Booking' => [
        ['name' => 'Test Ride Booking Platform', 'desc' => "Platform booking test ride:\n- Bike availability\n- Location selection\n- Slot management\n- Post-ride feedback", 'labels' => ['Feature']],
    ],
    'AI Customer Service' => [
        ['name' => 'AI Chatbot Development', 'desc' => "Pengembangan chatbot AI:\n- NLP training\n- Intent recognition\n- Knowledge base\n- Escalation to human", 'labels' => ['Feature', 'AI/ML']],
    ],
    'Catalog Search Correction & Suggestion' => [
        ['name' => 'Search Autocorrect & Suggest', 'desc' => "Koreksi & saran pencarian:\n- Typo correction\n- Did-you-mean\n- Popular searches\n- Synonym mapping", 'labels' => ['Feature', 'AI/ML', 'Performance']],
    ],
    'Catalog Bike Recommendation based on Dealer' => [
        ['name' => 'Dealer-Based Recommendation', 'desc' => "Rekomendasi berdasarkan dealer:\n- Dealer profiling\n- Regional preferences\n- Stock optimization\n- Sales prediction", 'labels' => ['Feature', 'AI/ML']],
    ],
];

$workingDays = getWorkingDays($CONFIG['start_date'], $CONFIG['end_date']);

foreach ($taskTemplates as $projectName => $tasks) {
    $projectId = $projectIdMap[$projectName];
    $spaceName = $projectSpaceMap[$projectName];

    foreach ($tasks as $taskPos => $tDef) {
        $taskCounter++;
        $id = nextId('tasks');
        $taskKey = $projectName . ':' . $taskPos;
        $taskIdMap[$taskKey] = $id;

        // Determine status based on timeline: Week 1-2 tasks more likely Done, Week 3-4 more likely In Progress/To Do
        $statusWeights = ['Done', 'Done', 'In Progress', 'In Progress', 'In Progress', 'Review', 'To Do'];
        $statusName = $statusWeights[array_rand($statusWeights)];
        $statusId = $statusIdMap[$spaceName . ':' . $statusName];

        $priorityLevel = [1, 2, 2, 3, 3, 3][array_rand([1, 2, 2, 3, 3, 3])];
        $startDate = $workingDays[rand(0, min(4, count($workingDays) - 1))];
        $dueDate = $workingDays[rand(max(5, count($workingDays) - 10), count($workingDays) - 1)];
        $timeEstimate = [960, 1200, 1440, 1920, 2400][array_rand([960, 1200, 1440, 1920, 2400])];

        $prefix = strtoupper(substr($CONFIG['workspace_name'], 0, 3));
        $taskIdStr = $prefix . '-' . $taskCounter;

        // Identify who can be assigned (space members minus Leo/Gilbert who are owners)
        $spaceConfig = null;
        foreach ($SPACES as $sc) { if ($sc['name'] === $spaceName) { $spaceConfig = $sc; break; } }
        $devs = array_filter($spaceConfig['members'], fn($n) => !in_array($n, ['Leo', 'Gilbert']));
        $devs = array_values($devs);
        $assigneeCount = min(rand(1, 3), count($devs));
        $assignees = pickRandom($devs, $assigneeCount);

        $createdBy = $userIdMap[['Leo', 'Gilbert'][array_rand(['Leo', 'Gilbert'])]];

        $sql_rows['tasks'][] = [
            'id' => $id,
            'task_id' => $taskIdStr,
            'project_id' => $projectId,
            'status_id' => $statusId,
            'priority_level' => $priorityLevel,
            'name' => $tDef['name'],
            'description' => $tDef['desc'],
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'time_estimate' => $timeEstimate,
            'position' => $taskPos,
            'is_archived' => 0,
            'created_by' => $createdBy,
            'created_at' => workTime($CONFIG['start_date'], 8, 30),
            'updated_at' => workTime($CONFIG['start_date'], 8, 30),
            'deleted_at' => null,
        ];

        // Task assignees
        foreach ($assignees as $aName) {
            $sql_rows['task_assignees'][] = [
                'id' => nextId('task_assignees'),
                'task_id' => $id,
                'user_id' => $userIdMap[$aName],
                'assigned_by' => $createdBy,
            ];
        }

        // Task labels
        if (!empty($tDef['labels'])) {
            foreach ($tDef['labels'] as $lName) {
                if (isset($labelIdMap[$lName])) {
                    $sql_rows['task_labels'][] = [
                        'id' => nextId('task_labels'),
                        'task_id' => $id,
                        'label_id' => $labelIdMap[$lName],
                        'created_at' => workTime($CONFIG['start_date'], 8, 30),
                        'updated_at' => workTime($CONFIG['start_date'], 8, 30),
                    ];
                }
            }
        }
    }
}

echo "    Generated " . count($sql_rows['tasks']) . " tasks\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 10. SUBTASKS (3-6 per task, with dependencies, PERT estimates, time tracking)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[10/14] Generating subtasks...\n";

$subtaskIdMap = []; // auto-inc id list
$subtaskRows = [];
$subtaskAssigneeRows = [];
$subtaskLabelRows = [];
$subtaskDependencyRows = [];
$timeEntryRows = [];
$subtaskGlobalCounter = 0;

$subtaskNameTemplates = [
    'Analisis kebutuhan & dokumentasi',
    'Desain database schema & ERD',
    'Setup project & boilerplate',
    'Implementasi API endpoint',
    'Integrasi external service',
    'Pengembangan UI komponen',
    'Unit testing & integration test',
    'Code review & refactoring',
    'Bug fixing & optimization',
    'Deployment & monitoring setup',
    'Dokumentasi teknis & user guide',
    'Performance testing & tuning',
    'Security audit & hardening',
    'UAT preparation & support',
];

foreach ($taskTemplates as $projectName => $tasks) {
    $spaceName = $projectSpaceMap[$projectName];
    $spaceConfig = null;
    foreach ($SPACES as $sc) { if ($sc['name'] === $spaceName) { $spaceConfig = $sc; break; } }
    $devs = array_filter($spaceConfig['members'], fn($n) => !in_array($n, ['Leo', 'Gilbert']));
    $devs = array_values($devs);

    foreach ($tasks as $taskPos => $tDef) {
        $taskKey = $projectName . ':' . $taskPos;
        $taskId = $taskIdMap[$taskKey];

        // 4-6 subtasks per task
        $numSubtasks = rand(4, 6);
        $subtaskIdsInTask = [];
        $shuffledTemplates = $subtaskNameTemplates;
        shuffle($shuffledTemplates);

        for ($si = 0; $si < $numSubtasks; $si++) {
            $subtaskGlobalCounter++;
            $subtaskId = nextId('subtasks');
            $subtaskIdsInTask[] = $subtaskId;

            $subtaskIdStr = 'MIS-' . ($taskIdMap[$taskKey] ?? 1) . '-' . ($si + 1);

            // Determine sprint assignment (distribute subtasks across sprints)
            $sprintIndex = min($si, 3); // First subtasks in earlier sprints
            if ($si < 2) $sprintIndex = 0;
            elseif ($si < 3) $sprintIndex = 1;
            elseif ($si < 5) $sprintIndex = 2;
            else $sprintIndex = 3;

            $sprintKey = $projectName . ':Sprint ' . ($sprintIndex + 1);
            $sprintId = $sprintIdMap[$sprintKey] ?? null;

            // Status: Sprint 1 subtasks = Done, Sprint 2 = mostly Done, Sprint 3 = In Progress, Sprint 4 = To Do
            if ($sprintIndex === 0) {
                $stName = 'Done';
            } elseif ($sprintIndex === 1) {
                $stName = ['Done', 'Done', 'Review'][array_rand(['Done', 'Done', 'Review'])];
            } elseif ($sprintIndex === 2) {
                $stName = ['In Progress', 'In Progress', 'Review', 'To Do'][array_rand(['In Progress', 'In Progress', 'Review', 'To Do'])];
            } else {
                $stName = ['To Do', 'Backlog'][array_rand(['To Do', 'Backlog'])];
            }
            $statusId = $statusIdMap[$spaceName . ':' . $stName];
            $isCompleted = ($stName === 'Done');

            // Timing
            $sprintStart = $sprintWeeks[$sprintIndex]['start'];
            $sprintEnd = $sprintWeeks[$sprintIndex]['end'];
            $sprintWorkDays = getWorkingDays($sprintStart, $sprintEnd);

            $startDay = $sprintWorkDays[min($si % count($sprintWorkDays), count($sprintWorkDays) - 1)];
            $endDayIdx = min(($si % count($sprintWorkDays)) + rand(1, 2), count($sprintWorkDays) - 1);
            $endDay = $sprintWorkDays[$endDayIdx];

            $startDatetime = workTime($startDay, 8, 0);
            $dueDatetime = workTime($endDay, 17, 0);
            $completedAt = $isCompleted ? workTime($endDay, rand(14, 16), rand(0, 59)) : null;

            // PERT estimates (in minutes)
            $timeEstimate = [120, 180, 240, 300, 360, 480][array_rand([120, 180, 240, 300, 360, 480])];
            $optimistic = (int)($timeEstimate * 0.7);
            $mostLikely = $timeEstimate;
            $pessimistic = (int)($timeEstimate * 1.5);
            $timeSpent = $isCompleted ? rand($optimistic, $pessimistic) : ($stName === 'In Progress' ? rand(30, $timeEstimate) : 0);
            $progress = $isCompleted ? 100 : ($stName === 'In Progress' ? rand(20, 70) : ($stName === 'Review' ? rand(80, 95) : 0));

            $priorityLevel = [1, 2, 2, 3, 3, 4][array_rand([1, 2, 2, 3, 3, 4])];
            $assignee = $devs[array_rand($devs)];
            $createdBy = $userIdMap[$assignee];

            $subtaskName = $shuffledTemplates[$si % count($shuffledTemplates)];

            $sql_rows['subtasks'][] = [
                'id' => $subtaskId,
                'subtask_id' => $subtaskIdStr,
                'task_id' => $taskId,
                'parent_id' => null,
                'depth' => 0,
                'sprint_id' => $sprintId,
                'status_id' => $statusId,
                'priority_level' => $priorityLevel,
                'name' => $subtaskName,
                'description' => null,
                'start_date' => $startDatetime,
                'due_date' => $dueDatetime,
                'baseline_start_date' => $startDatetime,
                'baseline_due_date' => $dueDatetime,
                'completed_at' => $completedAt,
                'time_estimate' => $timeEstimate,
                'optimistic_estimate' => $optimistic,
                'most_likely_estimate' => $mostLikely,
                'pessimistic_estimate' => $pessimistic,
                'time_spent' => $timeSpent,
                'progress' => $progress,
                'position' => $si,
                'created_by' => $createdBy,
                'completed_by' => $isCompleted ? $createdBy : null,
                'created_at' => $startDatetime,
                'updated_at' => $completedAt ?? $startDatetime,
                'deleted_at' => null,
            ];

            // Subtask assignee
            $sql_rows['subtask_assignees'][] = [
                'id' => nextId('subtask_assignees'),
                'subtask_id' => $subtaskId,
                'user_id' => $userIdMap[$assignee],
                'assigned_by' => $userIdMap['Leo'],
            ];

            // Subtask label (inherit from task or random)
            if (!empty($tDef['labels'])) {
                $lbl = $tDef['labels'][array_rand($tDef['labels'])];
                if (isset($labelIdMap[$lbl])) {
                    $sql_rows['subtask_labels'][] = [
                        'id' => nextId('subtask_labels'),
                        'subtask_id' => $subtaskId,
                        'label_id' => $labelIdMap[$lbl],
                        'created_at' => $startDatetime,
                        'updated_at' => $startDatetime,
                    ];
                }
            }

            // Time entry if time_spent > 0
            if ($timeSpent > 0) {
                $teStarted = workTime($startDay, rand(8, 10), rand(0, 30));
                $sql_rows['time_entries'][] = [
                    'id' => nextId('time_entries'),
                    'subtask_id' => $subtaskId,
                    'user_id' => $userIdMap[$assignee],
                    'duration' => $timeSpent,
                    'started_at' => $teStarted,
                    'ended_at' => workTime($startDay, rand(14, 16), rand(0, 59)),
                    'is_billable' => rand(0, 1),
                    'is_running' => 0,
                    'created_at' => $teStarted,
                    'updated_at' => $teStarted,
                    'deleted_at' => null,
                ];
            }
        }

        // Add dependencies: sequential chain
        for ($di = 1; $di < count($subtaskIdsInTask); $di++) {
            $sql_rows['subtask_dependencies'][] = [
                'id' => nextId('subtask_dependencies'),
                'subtask_id' => $subtaskIdsInTask[$di],
                'depends_on_subtask_id' => $subtaskIdsInTask[$di - 1],
                'dependency_type' => 'blocks',
                'created_at' => '2026-04-27 08:00:00',
                'updated_at' => '2026-04-27 08:00:00',
            ];
        }
    }
}

echo "    Generated " . count($sql_rows['subtasks']) . " subtasks\n";
echo "    Generated " . count($sql_rows['time_entries']) . " time entries\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 11. COMMENTS (2-4 per task)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[11/14] Generating comments...\n";

$commentTemplates = [
    "Sudah progress %d%%. Estimasi selesai hari %s.",
    "Butuh klarifikasi dari tim business terkait requirement.",
    "API endpoint sudah ready untuk di-consume frontend.",
    "Unit test coverage sudah di atas 80%%.",
    "Blocker: menunggu akses credentials dari pihak ketiga.",
    "UI sudah sesuai mockup. Tinggal integration testing.",
    "Deploy ke staging berhasil. Silakan di-test.",
    "Performance test done. Response time < 200ms.",
    "Security review passed. No critical findings.",
    "Documentation sudah di-update di Confluence.",
    "Sprint review kemarin feedback-nya positif dari stakeholder.",
    "Perlu refactor module ini supaya lebih maintainable.",
    "Bug minor ditemukan di edge case. Sedang diperbaiki.",
    "Integrasi dengan SAP sudah berhasil di sandbox.",
    "Meeting sync dengan vendor dijadwalkan besok jam 10.",
];

foreach ($taskTemplates as $projectName => $tasks) {
    $spaceName = $projectSpaceMap[$projectName];
    $spaceConfig = null;
    foreach ($SPACES as $sc) { if ($sc['name'] === $spaceName) { $spaceConfig = $sc; break; } }
    $allMembers = $spaceConfig['members'];

    foreach ($tasks as $taskPos => $tDef) {
        $taskKey = $projectName . ':' . $taskPos;
        $taskId = $taskIdMap[$taskKey];
        $numComments = rand(2, 4);

        $parentCommentId = null;
        for ($ci = 0; $ci < $numComments; $ci++) {
            $commentId = nextId('comments');
            $commenter = $allMembers[array_rand($allMembers)];
            $dayIdx = rand(0, count($workingDays) - 1);
            $commentDate = $workingDays[$dayIdx];
            $commentTime = randomWorkTime($commentDate);

            $content = sprintf($commentTemplates[array_rand($commentTemplates)], rand(30, 90), $workingDays[min($dayIdx + 2, count($workingDays) - 1)]);

            $sql_rows['comments'][] = [
                'id' => $commentId,
                'task_id' => $taskId,
                'subtask_id' => null,
                'user_id' => $userIdMap[$commenter],
                'parent_id' => ($ci > 0 && rand(0, 1)) ? $parentCommentId : null,
                'content' => $content,
                'mentions' => null,
                'attachments' => null,
                'is_resolved' => 0,
                'edited_at' => null,
                'created_at' => $commentTime,
                'updated_at' => $commentTime,
            ];

            if ($ci === 0) $parentCommentId = $commentId;
        }
    }
}

echo "    Generated " . count($sql_rows['comments']) . " comments\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 12. ACTIVITIES
// ═══════════════════════════════════════════════════════════════════════════════
echo "[12/14] Generating activities...\n";

// Generate creation + status change activities for each task
foreach ($taskTemplates as $projectName => $tasks) {
    $spaceName = $projectSpaceMap[$projectName];
    foreach ($tasks as $taskPos => $tDef) {
        $taskKey = $projectName . ':' . $taskPos;
        $taskId = $taskIdMap[$taskKey];

        // Task created
        $sql_rows['activities'][] = [
            'id' => nextId('activities'),
            'workspace_id' => $workspaceId,
            'user_id' => $userIdMap['Leo'],
            'subject_type' => 'App\\Models\\Task',
            'subject_id' => $taskId,
            'action' => 'created',
            'properties' => json_encode(['name' => $tDef['name']]),
            'changes' => null,
            'created_at' => workTime($CONFIG['start_date'], 8, rand(5, 30)),
            'updated_at' => workTime($CONFIG['start_date'], 8, rand(5, 30)),
        ];
    }
}

echo "    Generated " . count($sql_rows['activities']) . " activities\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 13. CHECKLIST ITEMS (3-5 per some subtasks)
// ═══════════════════════════════════════════════════════════════════════════════
echo "[13/14] Generating checklist items...\n";

$checklistTemplates = [
    'Setup environment & config', 'Write unit tests', 'Code review done',
    'Integration testing', 'Update documentation', 'Deploy to staging',
    'QA sign-off', 'Performance benchmark', 'Security checklist',
    'Database migration verified', 'API contract validated', 'UI responsiveness checked',
];

// Add checklists to ~30% of subtasks
$subtaskCount = count($sql_rows['subtasks']);
$checklistSubtasks = pickRandom(range(0, $subtaskCount - 1), (int)($subtaskCount * 0.3));

foreach ($checklistSubtasks as $sIdx) {
    $subtask = $sql_rows['subtasks'][$sIdx];
    $subtaskId = $subtask['id'];
    $numItems = rand(3, 5);
    $checkedCount = 0;

    $shuffledChecklist = $checklistTemplates;
    shuffle($shuffledChecklist);

    for ($cli = 0; $cli < $numItems; $cli++) {
        $isChecked = ($subtask['progress'] > 50 && rand(0, 2) > 0) ? 1 : 0;
        if ($isChecked) $checkedCount++;

        $sql_rows['checklist_items'][] = [
            'id' => nextId('checklist_items'),
            'subtask_id' => $subtaskId,
            'parent_id' => null,
            'name' => $shuffledChecklist[$cli % count($shuffledChecklist)],
            'is_checked' => $isChecked,
            'position' => $cli,
            'depth' => 0,
            'created_by' => $subtask['created_by'],
            'created_at' => $subtask['created_at'],
            'updated_at' => $subtask['updated_at'],
        ];
    }
}

echo "    Generated " . count($sql_rows['checklist_items'] ?? []) . " checklist items\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 14. VIEWS
// ═══════════════════════════════════════════════════════════════════════════════
echo "[14/14] Generating views...\n";

// Create default views per space and some per project
foreach ($SPACES as $space) {
    $spId = $spaceIdMap[$space['name']];
    $sql_rows['views'][] = [
        'id' => nextId('views'),
        'project_id' => null,
        'space_id' => $spId,
        'user_id' => $userIdMap['Leo'],
        'name' => 'All Tasks',
        'type' => 'list',
        'filters' => null,
        'sorts' => null,
        'columns' => null,
        'settings' => null,
        'is_default' => 1,
        'is_private' => 0,
        'position' => 0,
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
    ];
    $sql_rows['views'][] = [
        'id' => nextId('views'),
        'project_id' => null,
        'space_id' => $spId,
        'user_id' => $userIdMap['Gilbert'],
        'name' => 'Board View',
        'type' => 'board',
        'filters' => null,
        'sorts' => null,
        'columns' => null,
        'settings' => null,
        'is_default' => 0,
        'is_private' => 0,
        'position' => 1,
        'created_at' => '2026-04-25 09:00:00',
        'updated_at' => '2026-04-25 09:00:00',
    ];
}

echo "    Generated " . count($sql_rows['views']) . " views\n";

// ═══════════════════════════════════════════════════════════════════════════════
// OUTPUT SQL FILE
// ═══════════════════════════════════════════════════════════════════════════════
echo "\nWriting SQL file...\n";

$outputFile = __DIR__ . '/database/testing-data.sql';

$tableOrder = [
    'users', 'workspaces', 'workspace_members',
    'spaces', 'space_members', 'statuses', 'labels', 'folders',
    'projects', 'project_members', 'sprints',
    'tasks', 'task_assignees', 'task_labels',
    'subtasks', 'subtask_assignees', 'subtask_labels', 'subtask_dependencies',
    'checklist_items', 'time_entries', 'comments', 'activities', 'views',
];

$lines = [];
$lines[] = '-- ============================================================';
$lines[] = '--  TESTING DATA - PT XYZ (Manufacture Sepeda)';
$lines[] = '--  Generated : ' . date('Y-m-d H:i:s');
$lines[] = '--  Period    : ' . $CONFIG['start_date'] . ' to ' . $CONFIG['end_date'];
$lines[] = '--';
$lines[] = '--  Usage:';
$lines[] = '--    mysql -u USER -p DATABASE < database/testing-data.sql';
$lines[] = '--';
$lines[] = '--  Login credentials: any email with password "password"';
$lines[] = '--  Owner accounts: leo@example.com, gilbert@example.com';
$lines[] = '-- ============================================================';
$lines[] = '';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$lines[] = '';

$totalRows = 0;
foreach ($tableOrder as $table) {
    if (empty($sql_rows[$table])) continue;
    $count = count($sql_rows[$table]);
    $totalRows += $count;
    $lines[] = '-- ' . str_repeat('-', 60);
    $lines[] = "--  Table: `{$table}` ({$count} rows)";
    $lines[] = '-- ' . str_repeat('-', 60);
    $lines[] = "TRUNCATE TABLE `{$table}`;";
    $lines[] = buildInsert($table, $sql_rows[$table]);
    $lines[] = '';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$lines[] = '';
$lines[] = '-- End of file';

$sql = implode("\n", $lines);

// Ensure directory exists
$dir = dirname($outputFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (file_put_contents($outputFile, $sql) === false) {
    echo "\n[ERROR] Failed to write: {$outputFile}\n";
    exit(1);
}

$size = round(filesize($outputFile) / 1024, 1);

echo "\n";
echo "╔══════════════════════════════════════════════╗\n";
echo "║          Generation Complete!               ║\n";
echo "╠══════════════════════════════════════════════╣\n";
echo "║  Output : database/testing-data.sql          ║\n";
echo "║  Tables : " . str_pad(count(array_filter($sql_rows, fn($r) => !empty($r))), 5) . "                                 ║\n";
echo "║  Rows   : " . str_pad($totalRows, 6) . "                                ║\n";
echo "║  Size   : " . str_pad($size . ' KB', 9) . "                             ║\n";
echo "╠══════════════════════════════════════════════╣\n";
echo "║  Login  : leo@example.com / password         ║\n";
echo "║           gilbert@example.com / password     ║\n";
echo "╚══════════════════════════════════════════════╝\n";
