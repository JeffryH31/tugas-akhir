<?php

/**
 * generate-demo-sql.php
 *
 * Standalone script to export all demo data from the database to a SQL file.
 * Output: database/demo-data.sql
 *
 * Usage:
 *   php generate-demo-sql.php
 *
 * Requirements:
 *   - Laravel project with .env configured
 *   - Database seeded with demo data
 *
 * Run this script once after seeding to capture a snapshot of demo data.
 */

define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

// ── Configuration ────────────────────────────────────────────────────────────

$outputFile = __DIR__ . '/database/demo-data.sql';

/**
 * Tables in the correct order to respect foreign key constraints.
 * Earlier tables must be populated before tables that reference them.
 */
$tables = [
    'users',
    'workspaces',
    'workspace_members',
    'spaces',
    'space_members',
    'statuses',
    'labels',
    'folders',
    'projects',
    'project_members',
    'sprints',
    'tasks',
    'task_assignees',
    'task_labels',
    'subtasks',
    'subtask_assignees',
    'subtask_labels',
    'subtask_dependencies',
    'checklist_items',
    'time_entries',
    'comments',
    'activities',
    'views',
];

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Escape a scalar value for use in a SQL INSERT statement.
 *
 * @param  mixed  $value
 * @return string  SQL-safe representation
 */
function escapeValue($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    // String: escape backslashes and single quotes
    $escaped = str_replace(
        ['\\',  "'",   "\r\n", "\n",   "\r",   "\x00", "\x1a"],
        ['\\\\', "''", "\\r\\n", "\\n", "\\r", "\\0",  "\\Z"],
        (string) $value
    );

    return "'" . $escaped . "'";
}

/**
 * Build a complete INSERT statement for an array of rows.
 *
 * @param  string   $table
 * @param  array[]  $rows   array of associative arrays
 * @return string
 */
function buildInsert(string $table, array $rows): string
{
    if (empty($rows)) {
        return '';
    }

    $columns = array_keys($rows[0]);
    $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));

    $valueGroups = [];
    foreach ($rows as $row) {
        $values = array_map('escapeValue', array_values($row));
        $valueGroups[] = '(' . implode(', ', $values) . ')';
    }

    return "INSERT INTO `{$table}` ({$colList}) VALUES\n"
        . implode(",\n", $valueGroups)
        . ';';
}

// ── Main ─────────────────────────────────────────────────────────────────────

$now = date('Y-m-d H:i:s');

$lines   = [];
$lines[] = '-- ============================================================';
$lines[] = '--  Demo Data SQL Export';
$lines[] = '--  Generated : ' . $now;
$lines[] = '--  Project   : ' . (config('app.name') ?? 'tugas-akhir');
$lines[] = '--';
$lines[] = '--  Usage:';
$lines[] = '--    mysql -u USER -p DATABASE < database/demo-data.sql';
$lines[] = '--';
$lines[] = '--  WARNING: This file TRUNCATES all listed tables first!';
$lines[] = '--           Make sure you have a backup before running.';
$lines[] = '-- ============================================================';
$lines[] = '';
$lines[] = 'SET NAMES utf8mb4;';
$lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$lines[] = '';

$totalRows = 0;

foreach ($tables as $table) {
    echo "[~] Exporting table: {$table} ... ";

    // Check table exists
    try {
        $rows = DB::table($table)->get()->map(fn($r) => (array) $r)->toArray();
    } catch (\Exception $e) {
        echo "SKIPPED (table not found or error: {$e->getMessage()})\n";
        continue;
    }

    $count    = count($rows);
    $totalRows += $count;

    $lines[] = '-- ------------------------------------------------------------';
    $lines[] = "--  Table: `{$table}`  ({$count} row" . ($count !== 1 ? 's' : '') . ')';
    $lines[] = '-- ------------------------------------------------------------';
    $lines[] = "TRUNCATE TABLE `{$table}`;";

    if ($count > 0) {
        $lines[] = buildInsert($table, $rows);
    }

    $lines[] = '';

    echo "OK ({$count} rows)\n";
}

$lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$lines[] = '';
$lines[] = '-- End of file';

$sql = implode("\n", $lines);

if (file_put_contents($outputFile, $sql) === false) {
    echo "\n[ERROR] Failed to write output file: {$outputFile}\n";
    exit(1);
}

$elapsed = round(microtime(true) - LARAVEL_START, 2);
$size    = round(filesize($outputFile) / 1024, 1);

echo "\n";
echo "╔══════════════════════════════════════════════╗\n";
echo "║          Export Complete!                    ║\n";
echo "╠══════════════════════════════════════════════╣\n";
echo "║  Output : database/demo-data.sql             ║\n";
echo "║  Tables : " . str_pad(count($tables), 5) . "                                  ║\n";
echo "║  Rows   : " . str_pad($totalRows, 5) . "                                  ║\n";
echo "║  Size   : " . str_pad($size . ' KB', 9) . "                              ║\n";
echo "║  Time   : " . str_pad($elapsed . 's', 7) . "                                ║\n";
echo "╚══════════════════════════════════════════════╝\n";
