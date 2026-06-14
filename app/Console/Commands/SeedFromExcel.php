<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\Label;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SeedFromExcel extends Command
{
    protected $signature = 'seed:excel
                            {--path= : Path to Excel file (default: storage/app/seed-data.xlsx)}
                            {--fresh : Truncate all tables before seeding}';

    protected $description = 'Seed the database from an Excel template file (use seed:template to generate the file)';

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Workspace> */
    private array $workspaces = [];

    /** @var array<string, Space> */
    private array $spaces = [];

    /** @var array<string, Status> keyed as "space::name" */
    private array $statuses = [];

    /** @var array<string, Label> */
    private array $labels = [];

    /** @var array<string, Folder> keyed as "space::name" */
    private array $folders = [];

    /** @var array<string, Project> */
    private array $lists = [];

    /** @var array<string, Sprint> */
    private array $sprints = [];

    /** @var array<string, Task> */
    private array $tasks = [];

    /** @var array<string, Subtask> */
    private array $subtasks = [];

    public function handle(): int
    {
        $path = $this->option('path') ?? storage_path('app/seed-data.xlsx');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            $this->line('Run <comment>php artisan seed:template</comment> to generate the template first.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('This will truncate ALL project tables. Continue?', false)) {
                return self::SUCCESS;
            }
            $this->truncateTables();
        }

        $this->info("Reading: {$path}");
        $spreadsheet = IOFactory::load($path);

        DB::transaction(function () use ($spreadsheet) {
            $this->importUsers($spreadsheet);
            $this->importWorkspace($spreadsheet);
            $this->importWorkspaceMembers($spreadsheet);
            $this->importSpaces($spreadsheet);
            $this->importStatuses($spreadsheet);
            $this->importLabels($spreadsheet);
            $this->importFolders($spreadsheet);
            $this->importProjects($spreadsheet);
            $this->importSprints($spreadsheet);
            $this->importTasks($spreadsheet);
            $this->importSubtasks($spreadsheet);
            $this->importTimeEntries($spreadsheet);
        });

        $this->info('Seeding from Excel completed successfully.');

        return self::SUCCESS;
    }

    // ─── Importers ────────────────────────────────────────────────────────────

    private function importUsers(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Users');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Users…');

        foreach ($this->rows($sheet) as $row) {
            [$name, $email, $password, $hourlyRate] = array_pad($row, 4, null);
            if (empty($email)) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => trim($email)],
                [
                    'name' => trim($name),
                    'password' => Hash::make($password ?: 'password'),
                    'hourly_rate' => (int) ($hourlyRate ?? 0),
                ]
            );

            $this->users[$user->email] = $user;
        }

        $this->info('    Users: ' . count($this->users));
    }

    private function importWorkspace(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Workspace');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Workspace…');

        foreach ($this->rows($sheet) as $row) {
            [$name, $slug, $color] = array_pad($row, 3, null);
            if (empty($name)) {
                continue;
            }

            $workspace = Workspace::updateOrCreate(
                ['slug' => trim($slug)],
                ['name' => trim($name), 'color' => $color ?? '#6366F1']
            );

            $this->workspaces[$workspace->name] = $workspace;
        }

        $this->info('    Workspaces: ' . count($this->workspaces));
    }

    private function importWorkspaceMembers(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('WorkspaceMembers');
        if (! $sheet || empty($this->workspaces)) {
            return;
        }

        $this->line('  Importing WorkspaceMembers…');
        $workspace = collect($this->workspaces)->first();

        foreach ($this->rows($sheet) as $row) {
            [$email, $role] = array_pad($row, 2, null);
            $user = $this->resolveUser($email);
            if (! $user) {
                continue;
            }

            if (! $workspace->isMember($user)) {
                $workspace->addMember($user, trim($role ?? 'member'));
            }
        }
    }

    private function importSpaces(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Spaces');
        if (! $sheet || empty($this->workspaces)) {
            return;
        }

        $this->line('  Importing Spaces…');
        $workspace = collect($this->workspaces)->first();
        $adminUser = collect($this->users)->first();

        foreach ($this->rows($sheet) as $row) {
            [$name, $color, $position] = array_pad($row, 3, null);
            if (empty($name)) {
                continue;
            }

            $space = Space::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => trim($name)],
                [
                    'color' => $color ?? '#6366F1',
                    'position' => (int) ($position ?? 0),
                    'created_by' => $adminUser?->id,
                ]
            );

            $this->spaces[$space->name] = $space;
        }

        $this->info('    Spaces: ' . count($this->spaces));
    }

    private function importStatuses(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Statuses');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Statuses…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$spaceName, $name, $type, $color, $position, $appliesTo, $isDefault, $isClosed] = array_pad($row, 8, null);
            $space = $this->resolveSpace($spaceName);
            if (! $space || empty($name)) {
                continue;
            }

            $status = Status::updateOrCreate(
                ['space_id' => $space->id, 'name' => trim($name)],
                [
                    'type' => trim($type ?? 'open'),
                    'color' => $color ?? '#6B7280',
                    'position' => (int) ($position ?? 0),
                    'applies_to' => $appliesTo ?? 'both',
                    'is_default' => $this->bool($isDefault),
                    'is_closed' => $this->bool($isClosed),
                ]
            );

            $this->statuses["{$space->name}::{$status->name}"] = $status;
            $count++;
        }

        $this->info("    Statuses: {$count}");
    }

    private function importLabels(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Labels');
        if (! $sheet || empty($this->workspaces)) {
            return;
        }

        $this->line('  Importing Labels…');
        $workspace = collect($this->workspaces)->first();

        foreach ($this->rows($sheet) as $row) {
            [$name, $color] = array_pad($row, 2, null);
            if (empty($name)) {
                continue;
            }

            $label = Label::updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => trim($name)],
                ['color' => $color ?? '#6B7280']
            );

            $this->labels[$label->name] = $label;
        }

        $this->info('    Labels: ' . count($this->labels));
    }

    private function importFolders(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Folders');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Folders…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$spaceName, $name, $position, $createdBy] = array_pad($row, 4, null);
            $space = $this->resolveSpace($spaceName);
            if (! $space || empty($name)) {
                continue;
            }

            $folder = Folder::updateOrCreate(
                ['space_id' => $space->id, 'name' => trim($name)],
                [
                    'position' => (int) ($position ?? 0),
                    'created_by' => $this->resolveUser($createdBy)?->id ?? collect($this->users)->first()?->id,
                ]
            );

            $this->folders["{$space->name}::{$folder->name}"] = $folder;
            $count++;
        }

        $this->info("    Folders: {$count}");
    }

    private function importProjects(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Projects');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Projects…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$name, $spaceName, $folderName, $position, $createdBy, $statusSpace, $statusName, $membersRaw] = array_pad($row, 8, null);
            $space = $this->resolveSpace($spaceName);
            if (! $space || empty($name)) {
                continue;
            }

            $folder = $folderName ? ($this->folders["{$space->name}::" . trim($folderName)] ?? null) : null;
            $status = $this->resolveStatus($statusSpace, $statusName);
            $creator = $this->resolveUser($createdBy);

            $list = Project::updateOrCreate(
                ['space_id' => $space->id, 'name' => trim($name)],
                [
                    'folder_id' => $folder?->id,
                    'position' => (int) ($position ?? 0),
                    'created_by' => $creator?->id ?? collect($this->users)->first()?->id,
                    'status_id' => $status?->id,
                ]
            );

            // Attach members
            if ($membersRaw) {
                foreach (explode(',', $membersRaw) as $memberDef) {
                    $parts = explode(':', trim($memberDef), 2);
                    if (count($parts) === 2) {
                        $memberUser = $this->resolveUser($parts[0]);
                        if ($memberUser) {
                            $list->addMember($memberUser, trim($parts[1]));
                        }
                    }
                }
            }

            $this->lists[$list->name] = $list;
            $count++;
        }

        $this->info("    Projects: {$count}");
    }

    private function importSprints(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Sprints');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Sprints…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$spaceName, $listName, $name, $goal, $startDate, $endDate, $isActive, $position] = array_pad($row, 8, null);
            $space = $this->resolveSpace($spaceName);
            $list = $this->lists[trim($listName ?? '')] ?? null;
            if (! $space || ! $list || empty($name)) {
                continue;
            }

            $sprint = Sprint::updateOrCreate(
                ['project_id' => $list->id, 'name' => trim($name)],
                [
                    'space_id' => $space->id,
                    'goal' => $goal,
                    'start_date' => $this->date($startDate),
                    'end_date' => $this->date($endDate),
                    'is_active' => $this->bool($isActive),
                    'position' => (int) ($position ?? 0),
                ]
            );

            $this->sprints[$sprint->name] = $sprint;
            $count++;
        }

        $this->info("    Sprints: {$count}");
    }

    private function importTasks(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Tasks');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Tasks…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$name, $description, $listName, $statusSpace, $statusName, $priorityLevel, $createdBy, $position, $assigneesRaw, $labelsRaw] = array_pad($row, 10, null);
            $list = $this->lists[trim($listName ?? '')] ?? null;
            if (! $list || empty($name)) {
                continue;
            }

            $status = $this->resolveStatus($statusSpace, $statusName);
            $creator = $this->resolveUser($createdBy);

            $task = Task::updateOrCreate(
                ['project_id' => $list->id, 'name' => trim($name)],
                [
                    'description' => $description,
                    'status_id' => $status?->id,
                    'priority_level' => (int) ($priorityLevel ?? 3),
                    'created_by' => $creator?->id ?? collect($this->users)->first()?->id,
                    'position' => (int) ($position ?? 0),
                ]
            );

            // Sync assignees
            if ($assigneesRaw) {
                $assigneeIds = collect(explode(',', $assigneesRaw))
                    ->map(fn ($e) => $this->resolveUser(trim($e))?->id)
                    ->filter()
                    ->values()
                    ->all();
                $task->assignees()->sync($assigneeIds);
            }

            // Sync labels
            if ($labelsRaw) {
                $labelIds = collect(explode(',', $labelsRaw))
                    ->map(fn ($l) => $this->labels[trim($l)]?->id ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $task->labels()->sync($labelIds);
            }

            $this->tasks[$task->name] = $task;
            $count++;
        }

        $this->info("    Tasks: {$count}");
    }

    private function importSubtasks(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('Subtasks');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing Subtasks…');
        $count = 0;

        // First pass: create subtasks
        /** @var array<int, array{subtask: Subtask, depends_on: string}> $pendingDependencies */
        $pendingDependencies = [];

        foreach ($this->rows($sheet) as $row) {
            [
                $name, $taskName, $statusSpace, $statusName, $priorityLevel,
                $timeEstimate, $position, $createdBy, $sprintName, $startDate,
                $dueDate, $completedAt, $assigneesRaw, $labelsRaw, $dependsOnRaw,
            ] = array_pad($row, 15, null);

            $task = $this->tasks[trim($taskName ?? '')] ?? null;
            if (! $task || empty($name)) {
                continue;
            }

            $status = $this->resolveStatus($statusSpace, $statusName);
            $sprint = $sprintName ? ($this->sprints[trim($sprintName)] ?? null) : null;
            $creator = $this->resolveUser($createdBy);

            $subtask = Subtask::updateOrCreate(
                ['task_id' => $task->id, 'name' => trim($name)],
                [
                    'status_id' => $status?->id,
                    'priority_level' => (int) ($priorityLevel ?? 3),
                    'time_estimate' => $timeEstimate ? (int) $timeEstimate : null,
                    'position' => (int) ($position ?? 0),
                    'created_by' => $creator?->id ?? collect($this->users)->first()?->id,
                    'sprint_id' => $sprint?->id,
                    'start_date' => $this->date($startDate),
                    'due_date' => $this->date($dueDate),
                    'completed_at' => $this->date($completedAt),
                ]
            );

            // Sync assignees
            if ($assigneesRaw) {
                $assigneeIds = collect(explode(',', $assigneesRaw))
                    ->map(fn ($e) => $this->resolveUser(trim($e))?->id)
                    ->filter()
                    ->values()
                    ->all();
                $subtask->assignees()->sync($assigneeIds);
            }

            // Sync labels
            if ($labelsRaw) {
                $labelIds = collect(explode(',', $labelsRaw))
                    ->map(fn ($l) => $this->labels[trim($l)]?->id ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $subtask->labels()->sync($labelIds);
            }

            $this->subtasks[$subtask->name] = $subtask;

            if (! empty($dependsOnRaw)) {
                $pendingDependencies[] = ['subtask' => $subtask, 'depends_on' => $dependsOnRaw];
            }

            $count++;
        }

        // Second pass: attach dependencies
        foreach ($pendingDependencies as $pending) {
            foreach (explode(',', $pending['depends_on']) as $depName) {
                $dep = $this->subtasks[trim($depName)] ?? null;
                if ($dep) {
                    // dependency_type: the dep "blocks" the subtask
                    $pending['subtask']->dependencies()->syncWithoutDetaching([
                        $dep->id => ['dependency_type' => 'blocks'],
                    ]);
                }
            }
        }

        $this->info("    Subtasks: {$count}");
    }

    private function importTimeEntries(Spreadsheet $s): void
    {
        $sheet = $s->getSheetByName('TimeEntries');
        if (! $sheet) {
            return;
        }

        $this->line('  Importing TimeEntries…');
        $count = 0;

        foreach ($this->rows($sheet) as $row) {
            [$subtaskName, $userEmail, $minutes, $startedAt, $isBillable] = array_pad($row, 5, null);
            $subtask = $this->subtasks[trim($subtaskName ?? '')] ?? null;
            $user = $this->resolveUser($userEmail);
            if (! $subtask || ! $user || empty($minutes)) {
                continue;
            }

            $start = $this->datetime($startedAt) ?? now();
            $mins = (int) $minutes;

            TimeEntry::create([
                'subtask_id' => $subtask->id,
                'user_id' => $user->id,
                'duration' => $mins,
                'started_at' => $start,
                'ended_at' => $start->copy()->addMinutes($mins),
                'is_billable' => $this->bool($isBillable),
                'is_running' => false,
            ]);

            $count++;
        }

        $this->info("    TimeEntries: {$count}");
    }

    // ─── Utility ─────────────────────────────────────────────────────────────

    /**
     * Iterate data rows (skip header row 1, skip blank rows).
     *
     * @return \Generator<array<mixed>>
     */
    private function rows($sheet): \Generator
    {
        $highest = $sheet->getHighestRow();
        for ($row = 2; $row <= $highest; $row++) {
            $data = $sheet->rangeToArray("A{$row}:" . $sheet->getHighestDataColumn() . "{$row}", null, true, true, false)[0];

            // Skip completely blank rows
            if (empty(array_filter($data, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            yield $data;
        }
    }

    private function resolveUser(?string $email): ?User
    {
        if (empty($email)) {
            return null;
        }
        $email = trim($email);

        return $this->users[$email] ?? User::where('email', $email)->first();
    }

    private function resolveSpace(?string $name): ?Space
    {
        if (empty($name)) {
            return null;
        }

        return $this->spaces[trim($name)] ?? null;
    }

    private function resolveStatus(?string $spaceName, ?string $statusName): ?Status
    {
        if (empty($spaceName) || empty($statusName)) {
            return null;
        }
        $key = trim($spaceName) . '::' . trim($statusName);

        return $this->statuses[$key] ?? null;
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower((string) $value);

        return in_array($v, ['true', '1', 'yes', 'ya']);
    }

    private function date(mixed $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function datetime(mixed $value): ?Carbon
    {
        return $this->date($value);
    }

    private function truncateTables(): void
    {
        $this->warn('Truncating tables…');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'time_entries', 'subtask_dependencies', 'subtask_assignees', 'subtask_labels',
            'subtasks', 'task_assignees', 'task_labels', 'task_dependencies', 'tasks',
            'sprints', 'project_members', 'projects',
            'folders', 'labels', 'statuses', 'spaces',
            'workspace_members', 'workspaces', 'users',
        ] as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
