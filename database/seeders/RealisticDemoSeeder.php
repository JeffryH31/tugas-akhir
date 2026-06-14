<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\Folder;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\View;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic demo seeder — generates fake but realistic data
 * based on PT XYZ bicycle manufacturer context.
 *
 * Period: 27 April – 22 May 2026 (4 weeks / 4 sprints)
 * Working hours: 08:00-17:00 WIB, break 12:00-13:00
 *
 * Usage:
 *   php artisan db:seed --class=RealisticDemoSeeder
 */
class RealisticDemoSeeder extends Seeder
{
    private string $startDate = '2026-04-27';
    private string $endDate = '2026-05-22';
    private array $workingDays = [];
    private array $userMap = [];
    private array $spaceMap = [];
    private array $statusMap = [];
    private array $labelMap = [];
    private array $folderMap = [];
    private array $projectMap = [];
    private array $projectPlan = [];
    private array $sprintMap = [];
    private array $taskMap = [];
    private array $taskMeta = [];
    private ?Workspace $workspace = null;
    private int $folderMaxAt = 0;

    /** Today's reference date for the snapshot — everything is finished before this. */
    private string $today = '2026-06-07';

    /**
     * National holidays inside the testing window that are NOT worked.
     * (Cuti bersama 15 May is intentionally treated as a normal working day.)
     */
    private array $holidays = ['2026-05-01', '2026-05-14'];

    /** Names of the "less diligent" employees who don't fill a full 8h day. */
    private array $lazyUsers = ['Mario', 'Charlie', 'Danny', 'Audi', 'Justin'];

    /** Capacity ledger: remaining work minutes per user per date. */
    private array $capRemaining = [];

    /** Next available start time ('H:i:s') per user per date, to avoid overlaps. */
    private array $dayCursor = [];

    /** Memoised post-lunch resume timestamp (unix) per user per date. */
    private array $lunchResume = [];

    public function run(): void
    {
        $this->workingDays = $this->getWorkingDays($this->startDate, $this->endDate);

        $this->command->info('  Seeding users...');
        $this->seedUsers();

        $this->command->info('  Seeding workspace...');
        $this->seedWorkspace();

        $this->command->info('  Seeding spaces...');
        $this->seedSpaces();

        $this->command->info('  Seeding statuses...');
        $this->seedStatuses();

        $this->command->info('  Seeding labels...');
        $this->seedLabels();

        $this->command->info('  Seeding folders...');
        $this->seedFolders();

        $this->command->info('  Seeding projects...');
        $this->seedProjects();

        $this->command->info('  Seeding sprints...');
        $this->seedSprints();

        $this->command->info('  Seeding tasks...');
        $this->seedTasks();

        $this->command->info('  Seeding subtasks, time entries & checklists...');
        $this->seedSubtasks();

        $this->command->info('  Seeding comments...');
        $this->seedComments();

        $this->command->info('  Seeding activities...');
        $this->seedActivities();

        $this->command->info('  Seeding views...');
        $this->seedViews();

        $this->command->info('  Aligning pivot timestamps...');
        $this->backfillPivotTimestamps();

        $this->command->info('');
        $this->command->info('  ✓ Done. Login: leo@example.com / password');
    }

    private function seedUsers(): void
    {
        $password = Hash::make('password');

        $users = [
            ['name' => 'Leo',       'email' => 'leo@example.com',       'hourly_rate' => 150000],
            ['name' => 'Gilbert',   'email' => 'gilbert@example.com',   'hourly_rate' => 150000],
            ['name' => 'Aji',       'email' => 'aji@example.com',       'hourly_rate' => 75000],
            ['name' => 'Mario',     'email' => 'mario@example.com',     'hourly_rate' => 75000],
            ['name' => 'Grace',     'email' => 'grace@example.com',     'hourly_rate' => 75000],
            ['name' => 'Alief',     'email' => 'alief@example.com',     'hourly_rate' => 75000],
            ['name' => 'Vincent',   'email' => 'vincent@example.com',   'hourly_rate' => 75000],
            ['name' => 'Stanley',   'email' => 'stanley@example.com',   'hourly_rate' => 75000],
            ['name' => 'Moses',     'email' => 'moses@example.com',     'hourly_rate' => 75000],
            ['name' => 'Stefanie',  'email' => 'stefanie@example.com',  'hourly_rate' => 75000],
            ['name' => 'Andry',     'email' => 'andry@example.com',     'hourly_rate' => 75000],
            ['name' => 'Gita',      'email' => 'gita@example.com',      'hourly_rate' => 75000],
            ['name' => 'Justin',    'email' => 'justin@example.com',    'hourly_rate' => 75000],
            ['name' => 'Charlie',   'email' => 'charlie@example.com',   'hourly_rate' => 75000],
            ['name' => 'Frans',     'email' => 'frans@example.com',     'hourly_rate' => 75000],
            ['name' => 'Audi',      'email' => 'audi@example.com',      'hourly_rate' => 75000],
            ['name' => 'Mira',      'email' => 'mira@example.com',      'hourly_rate' => 75000],
            ['name' => 'Clarissa',  'email' => 'clarissa@example.com',  'hourly_rate' => 75000],
            ['name' => 'Danny',     'email' => 'danny@example.com',     'hourly_rate' => 75000],
            ['name' => 'Nicko',     'email' => 'nicko@example.com',     'hourly_rate' => 75000],
            ['name' => 'Amel',      'email' => 'amel@example.com',      'hourly_rate' => 75000],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => $password, 'hourly_rate' => $u['hourly_rate'], 'email_verified_at' => now()]
            );
            // Accounts exist before the workspace is set up.
            $this->stamp($user, $this->workTime('2026-04-21', 8, 16));
            $this->userMap[$u['name']] = $user;
        }
    }

    private function seedWorkspace(): void
    {
        $this->workspace = Workspace::create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'color' => '#6366F1',
        ]);
        $this->stamp($this->workspace, '2026-04-23 08:37:51');

        $wsRoles = [
            'Leo' => 'owner', 'Gilbert' => 'owner',
        ];

        foreach ($this->userMap as $name => $user) {
            $role = $wsRoles[$name] ?? 'member';
            $this->workspace->addMember($user, $role);
        }
    }

    private function seedSpaces(): void
    {
        $spaces = [
            ['name' => 'Manufacture', 'color' => '#F97316', 'members' => ['Leo', 'Gilbert', 'Aji', 'Mario', 'Grace', 'Alief']],
            ['name' => 'B2B',         'color' => '#3B82F6', 'members' => ['Leo', 'Gilbert', 'Vincent', 'Stanley', 'Moses', 'Stefanie']],
            ['name' => 'B2C',         'color' => '#10B981', 'members' => ['Leo', 'Gilbert', 'Andry', 'Gita', 'Justin', 'Charlie', 'Frans', 'Audi']],
            ['name' => 'Data',        'color' => '#8B5CF6', 'members' => ['Leo', 'Gilbert', 'Mira', 'Clarissa', 'Stanley', 'Danny', 'Nicko', 'Amel']],
        ];

        $cursor = $this->cursorOf($this->workspace);

        foreach ($spaces as $pos => $s) {
            $cursor = $this->nextCursor($cursor, 4, 13);
            $space = Space::create([
                'workspace_id' => $this->workspace->id,
                'name' => $s['name'],
                'color' => $s['color'],
                'position' => $pos,
                'created_by' => $this->userMap['Leo']->id,
            ]);
            $this->stamp($space, date('Y-m-d H:i:s', $cursor));
            $this->spaceMap[$s['name']] = $space;

            $memberData = [];
            foreach ($s['members'] as $name) {
                $role = in_array($name, ['Leo', 'Gilbert']) ? 'admin' : 'member';
                $memberData[$this->userMap[$name]->id] = ['role' => $role];
            }
            $space->members()->syncWithoutDetaching($memberData);
        }
    }

    private function seedStatuses(): void
    {
        $defs = [
            ['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'is_default' => false, 'is_closed' => false],
            ['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'is_default' => true,  'is_closed' => false],
            ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'is_default' => false, 'is_closed' => false],
            ['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'is_default' => false, 'is_closed' => false],
            ['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'is_default' => false, 'is_closed' => true],
        ];

        foreach ($this->spaceMap as $spaceName => $space) {
            $space->statuses()->delete();
            $cursor = $this->cursorOf($space);
            foreach ($defs as $pos => $st) {
                $cursor = $this->nextCursor($cursor, 1, 4);
                $status = Status::create([
                    'space_id' => $space->id,
                    'name' => $st['name'],
                    'color' => $st['color'],
                    'type' => $st['type'],
                    'applies_to' => 'both',
                    'position' => $pos,
                    'is_default' => $st['is_default'],
                    'is_closed' => $st['is_closed'],
                ]);
                $this->stamp($status, date('Y-m-d H:i:s', $cursor));
                $this->statusMap[$spaceName . ':' . $st['name']] = $status;
            }
        }
    }

    private function seedLabels(): void
    {
        $labels = [
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

        $cursor = strtotime('2026-04-23 10:' . str_pad((string) rand(2, 24), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT));

        foreach ($labels as $i => $l) {
            $cursor = $this->nextCursor($cursor, 1, 5);
            $label = Label::create(['workspace_id' => $this->workspace->id, 'name' => $l['name'], 'color' => $l['color']]);
            $this->stamp($label, date('Y-m-d H:i:s', $cursor));
            $this->labelMap[$l['name']] = $label;
        }
    }

    private function seedFolders(): void
    {
        $folders = [
            'Manufacture' => [['name' => 'Product Monitoring', 'color' => '#EF4444'], ['name' => 'Planning & Stock', 'color' => '#F97316']],
            'B2B' => [['name' => 'Finance & Invoicing', 'color' => '#3B82F6'], ['name' => 'Operations', 'color' => '#0EA5E9']],
            'B2C' => [['name' => 'E-Commerce', 'color' => '#10B981'], ['name' => 'Bike Services', 'color' => '#14B8A6'], ['name' => 'Customer Engagement', 'color' => '#8B5CF6']],
            'Data' => [['name' => 'AI & ML', 'color' => '#A855F7'], ['name' => 'Catalog Intelligence', 'color' => '#6366F1']],
        ];

        $cursor = strtotime('2026-04-24 08:' . str_pad((string) rand(18, 40), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT));

        $folderIndex = 0;
        foreach ($folders as $spaceName => $list) {
            foreach ($list as $pos => $f) {
                $cursor = $this->nextCursor($cursor, 2, 8);
                $folder = Folder::create([
                    'space_id' => $this->spaceMap[$spaceName]->id,
                    'name' => $f['name'],
                    'color' => $f['color'],
                    'position' => $pos,
                    'created_by' => $this->userMap['Leo']->id,
                ]);
                $this->stamp($folder, date('Y-m-d H:i:s', $cursor));
                $this->folderMap[$f['name']] = $folder;
                $folderIndex++;
            }
        }

        // Remember when folder setup finished so projects start afterwards.
        $this->folderMaxAt = $cursor;
    }

    private function seedProjects(): void
    {
        $projects = $this->getProjectDefinitions();
        $weeks = $this->sprintWeeks();

        // Kickoff plan: which sprint each project starts in, and how many tasks
        // it carries. Totals to 149 tasks (23 late => exactly 15.4%). Projects
        // are staggered: most start at Sprint 1, others are kicked off later in
        // the month (and were created during the preceding sprint).
        $plan = [];
        for ($i = 0; $i < 12; $i++) $plan[] = ['k' => 0, 'count' => 7];
        for ($i = 0; $i < 6; $i++)  $plan[] = ['k' => 1, 'count' => 6];
        for ($i = 0; $i < 4; $i++)  $plan[] = ['k' => 2, 'count' => 6];
        $plan[] = ['k' => 3, 'count' => 5];
        shuffle($plan);

        // Running cursor for the Sprint-1 cohort so they spread across 24 Apr.
        $k0cursor = max($this->folderMaxAt, strtotime('2026-04-24 10:30:00'));

        foreach ($projects as $i => $p) {
            $space = $this->spaceMap[$p['space']];
            $folder = $this->folderMap[$p['folder']] ?? null;
            $statuses = ['In Progress', 'In Progress', 'To Do', 'Review', 'Backlog'];
            $statusName = $statuses[array_rand($statuses)];

            $k = $plan[$i]['k'];
            if ($k === 0) {
                // Kicked off on the planning day, after all folders exist.
                $k0cursor = $this->nextCursor($k0cursor, 8, 28);
                $createdTs = $k0cursor;
            } else {
                // Decided/created during the previous sprint week.
                $prev = $weeks[$k - 1];
                $days = $this->getWorkingDays($prev['start'], $prev['end']);
                $createdTs = strtotime($this->workTime($days[array_rand($days)], 9, 15));
            }

            $project = Project::create([
                'space_id' => $space->id,
                'folder_id' => $folder?->id,
                'name' => $p['name'],
                'status_id' => $this->statusMap[$p['space'] . ':' . $statusName]->id,
                'created_by' => $this->userMap['Leo']->id,
            ]);
            $this->stamp($project, date('Y-m-d H:i:s', $createdTs));
            $this->projectMap[$p['name']] = $project;
            $this->projectPlan[$p['name']] = $plan[$i];

            // Assign members
            $devs = $this->getSpaceDevs($p['space']);
            $project->addMember($this->userMap['Leo'], 'project_owner');
            $pm = $devs[array_rand($devs)];
            $project->addMember($this->userMap[$pm], 'project_manager');
            foreach ($devs as $dev) {
                if ($dev !== $pm) {
                    $project->addMember($this->userMap[$dev], 'development_team');
                }
            }
            if (rand(0, 1)) {
                $project->addMember($this->userMap['Gilbert'], 'project_manager');
            }
        }
    }

    private function seedSprints(): void
    {
        $weeks = [
            ['start' => '2026-04-27', 'end' => '2026-05-03', 'label' => 'Sprint 1'],
            ['start' => '2026-05-04', 'end' => '2026-05-10', 'label' => 'Sprint 2'],
            ['start' => '2026-05-11', 'end' => '2026-05-17', 'label' => 'Sprint 3'],
            ['start' => '2026-05-18', 'end' => '2026-05-22', 'label' => 'Sprint 4'],
        ];
        $goals = [
            'Setup fondasi dan analisis kebutuhan proyek',
            'Implementasi fitur core dan integrasi API',
            'Pengembangan UI/UX dan testing',
            'Finalisasi, review, dan deployment preparation',
        ];

        foreach ($this->projectMap as $projectName => $project) {
            $cursor = $this->cursorOf($project);
            $k = $this->projectPlan[$projectName]['k'] ?? 0;
            foreach ($weeks as $pos => $w) {
                if ($pos < $k) {
                    continue; // project didn't exist yet for earlier sprints
                }
                $cursor = $this->nextCursor($cursor, 1, 5);
                $sprint = Sprint::create([
                    'space_id' => $project->space_id,
                    'project_id' => $project->id,
                    'name' => $w['label'] . ' - ' . substr($projectName, 0, 30),
                    'goal' => $goals[$pos],
                    'start_date' => $w['start'],
                    'end_date' => $w['end'],
                    'is_active' => false,
                    'position' => $pos,
                ]);
                $this->stamp($sprint, date('Y-m-d H:i:s', $cursor));
                $this->sprintMap[$projectName . ':' . $w['label']] = $sprint;
            }
        }
    }

    private function seedTasks(): void
    {
        $templates = $this->getTaskTemplates();
        $supplementary = $this->getSupplementaryTasks();
        $weeks = $this->sprintWeeks();

        // Task counts come from the kickoff plan (sums to 149 => 23 late = 15.4%).
        $projectTasks = [];
        $grandTotal = 0;
        foreach ($templates as $projectName => $curated) {
            $target = $this->projectPlan[$projectName]['count'];
            $tasks = $curated;
            if (count($tasks) < $target) {
                $pool = $supplementary;
                shuffle($pool);
                $tasks = array_merge($tasks, array_slice($pool, 0, $target - count($tasks)));
            }
            $tasks = array_slice($tasks, 0, $target);
            $projectTasks[$projectName] = $tasks;
            $grandTotal += count($tasks);
        }

        // Exactly 15.4% of all tasks finish after their due date.
        $lateQuota = (int) round($grandTotal * 0.154);
        $indices = range(0, $grandTotal - 1);
        shuffle($indices);
        $lateKeys = array_fill_keys(array_slice($indices, 0, $lateQuota), true);

        $globalIdx = 0;
        foreach ($projectTasks as $projectName => $tasks) {
            $project = $this->projectMap[$projectName];
            $count = count($tasks);
            $k = $this->projectPlan[$projectName]['k'];
            $span = 4 - $k; // sprints available from kickoff to the end

            foreach ($tasks as $pos => $tDef) {
                $isLate = isset($lateKeys[$globalIdx]);
                $globalIdx++;

                // Spread tasks across the project's own active sprints (k..3).
                $s0 = min($k + (int) floor($pos / max(1, $count) * $span), 3);
                $s1 = min($s0 + rand(0, 1), 3);

                // Period already passed: every task ended up Done.
                $statusName = 'Done';
                $priority = [1, 2, 2, 3, 3, 3][array_rand([1, 2, 2, 3, 3, 3])];
                $createdBy = $this->userMap[['Leo', 'Gilbert'][rand(0, 1)]];

                $startDate = $weeks[$s0]['start'];
                $dueDate = $weeks[$s1]['end'];

                $task = Task::create([
                    'project_id' => $project->id,
                    'status_id' => $this->statusMap[$project->space->name . ':' . $statusName]->id,
                    'priority_level' => $priority,
                    'name' => $tDef['name'],
                    'description' => $tDef['desc'],
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'time_estimate' => [960, 1200, 1440, 1920, 2400][array_rand([960, 1200, 1440, 1920, 2400])],
                    'position' => $pos,
                    'created_by' => $createdBy->id,
                ]);

                // Tasks are created during sprint planning — the working day
                // before the sprint starts — so they predate all their subtasks
                // while still coming after the project itself.
                $planningDay = $this->previousWorkingDay($startDate);
                $plannedTs = strtotime($this->workTime($planningDay, 13, 16));
                $dayEnd = strtotime($planningDay . ' 16:55:00');
                if ($plannedTs > $dayEnd) {
                    $plannedTs = $dayEnd - rand(0, 3600);
                }
                $minTs = $this->cursorOf($project) + rand(600, 2400);
                if ($plannedTs < $minTs) {
                    $plannedTs = $minTs;
                }
                $createdAt = date('Y-m-d H:i:s', $plannedTs);
                $this->stamp($task, $createdAt);

                $this->taskMap[$projectName . ':' . $pos] = $task;
                $this->taskMeta[$projectName . ':' . $pos] = [
                    'isLate' => $isLate,
                    's0' => $s0,
                    's1' => $s1,
                    'createdAt' => $createdAt,
                    'start' => $startDate,
                    'due' => $dueDate,
                ];

                // NOTE: task-level assignees are intentionally NOT seeded — in this
                // workflow assignees live on subtasks and are derived upward.

                // Labels
                foreach ($tDef['labels'] ?? [] as $lName) {
                    if (isset($this->labelMap[$lName])) {
                        $task->labels()->attach($this->labelMap[$lName]->id);
                    }
                }
            }
        }
    }

    private function seedSubtasks(): void
    {
        $subtaskNames = [
            'Analisis kebutuhan & dokumentasi', 'Desain database schema & ERD', 'Setup project & boilerplate',
            'Implementasi API endpoint', 'Integrasi external service', 'Pengembangan UI komponen',
            'Unit testing & integration test', 'Code review & refactoring', 'Bug fixing & optimization',
            'Deployment & monitoring setup', 'Dokumentasi teknis & user guide', 'Performance testing & tuning',
            'Security audit & hardening', 'UAT preparation & support',
        ];

        // Process tasks in chronological order (by sprint start) so the team's
        // daily capacity fills up naturally from the first sprint onward.
        $taskKeys = array_keys($this->taskMap);
        usort($taskKeys, fn($a, $b) => strcmp($this->taskMeta[$a]['start'], $this->taskMeta[$b]['start']));

        foreach ($taskKeys as $taskKey) {
            $task = $this->taskMap[$taskKey];
            $projectName = explode(':', $taskKey)[0];
            $meta = $this->taskMeta[$taskKey];
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $devs = $this->getSpaceDevs($spaceName);

            $taskDays = $this->getWorkingDays($meta['start'], $meta['due']);
            if (empty($taskDays)) {
                $taskDays = [$meta['start']];
            }
            $L = count($taskDays);

            $numSubtasks = rand(3, 6);
            $names = $subtaskNames;
            shuffle($names);

            $maxCompleted = null;
            $maxUpdated = $meta['createdAt'];
            $lastCompleter = $task->created_by;
            $prevSubtask = null;
            $doneStatusId = $this->statusMap[$spaceName . ':Done']->id;

            for ($i = 0; $i < $numSubtasks; $i++) {
                $assignee = $devs[array_rand($devs)];
                $isTailLate = $meta['isLate'] && $i === $numSubtasks - 1;

                // Working-day segment of the task window for this subtask.
                $segStart = (int) floor($i * $L / $numSubtasks);
                $segEnd = min($L - 1, max($segStart, (int) floor(($i + 1) * $L / $numSubtasks)));
                $workDays = array_slice($taskDays, $segStart, $segEnd - $segStart + 1);
                if (empty($workDays)) {
                    $workDays = [$taskDays[min($segStart, $L - 1)]];
                }
                $dueDate = end($workDays) . ' 17:00:00';
                $startDay = $workDays[0];

                // Effort target (minutes) + PERT estimates.
                $estimate = [150, 210, 270, 330, 420, 480][array_rand([150, 210, 270, 330, 420, 480])];
                $factor = in_array($assignee, $this->lazyUsers, true) ? rand(70, 105) : rand(90, 130);
                $target = (int) round($estimate * $factor / 100);
                if ($isTailLate) {
                    $target = (int) round($target * rand(110, 160) / 100);
                }
                $optimistic = (int) round($estimate * 0.7);
                $pessimistic = (int) round($estimate * 1.5);

                // Schedule work across the allowed days, respecting daily capacity.
                $remaining = $target;
                $logged = 0;
                $firstStart = null;
                $lastEnd = null;
                $sessions = [];
                foreach ($workDays as $day) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $chunk = min($remaining, rand(120, 300));
                    foreach ($this->placeBlock($assignee, $day, $chunk) as $b) {
                        $sessions[] = $b;
                        $firstStart ??= $b[0];
                        $lastEnd = $b[1];
                        $logged += $b[2];
                        $remaining -= $b[2];
                    }
                }

                // Late tasks: their final subtask does a closing session AFTER the
                // deadline, so the parent task's completion slips past its due date.
                if ($isTailLate) {
                    $postDay = $this->addWorkingDays($meta['due'], rand(1, 4));
                    $blocks = [];
                    for ($k = 0; $k < 6 && empty($blocks); $k++) {
                        $blocks = $this->placeBlock($assignee, $postDay, rand(60, 180));
                        if (empty($blocks)) {
                            $postDay = $this->addWorkingDays($postDay, 1);
                        }
                    }
                    foreach ($blocks as $b) {
                        $sessions[] = $b;
                        $firstStart ??= $b[0];
                        $lastEnd = $b[1];
                        $logged += $b[2];
                    }
                }

                // Fallbacks if every candidate day happened to be full.
                $createdAtSub = $firstStart ?? $this->workTime($startDay, 8, 9);
                $completedAt = $lastEnd ?? sprintf('%s %02d:%02d:%02d', end($workDays), rand(15, 16), rand(0, 59), rand(0, 59));

                if ($completedAt > $maxUpdated) {
                    $maxUpdated = $completedAt;
                }
                if ($maxCompleted === null || $completedAt > $maxCompleted) {
                    $maxCompleted = $completedAt;
                    $lastCompleter = $this->userMap[$assignee]->id;
                }

                $subtask = Subtask::create([
                    'task_id' => $task->id,
                    'sprint_id' => $this->sprintForDate($projectName, $startDay)?->id,
                    'status_id' => $doneStatusId,
                    'priority_level' => [1, 2, 2, 3, 3, 4][array_rand([1, 2, 2, 3, 3, 4])],
                    'name' => $names[$i % count($names)],
                    'start_date' => $startDay . ' 08:00:00',
                    'due_date' => $dueDate,
                    'baseline_start_date' => $startDay . ' 08:00:00',
                    'baseline_due_date' => $dueDate,
                    'completed_at' => $completedAt,
                    'time_estimate' => $estimate,
                    'optimistic_estimate' => $optimistic,
                    'most_likely_estimate' => $estimate,
                    'pessimistic_estimate' => $pessimistic,
                    'time_spent' => $logged,
                    'progress' => 100,
                    'position' => $i,
                    'created_by' => $this->userMap[$assignee]->id,
                    'completed_by' => $this->userMap[$assignee]->id,
                ]);
                $this->stamp($subtask, $createdAtSub, $completedAt);

                // Exactly one assignee per subtask (PT XYZ workflow).
                $subtask->assignees()->attach($this->userMap[$assignee]->id, ['assigned_by' => $this->userMap['Leo']->id]);

                // One time entry per work block.
                foreach ($sessions as $b) {
                    $entry = TimeEntry::create([
                        'subtask_id' => $subtask->id,
                        'user_id' => $this->userMap[$assignee]->id,
                        'duration' => $b[2],
                        'started_at' => $b[0],
                        'ended_at' => $b[1],
                        'is_billable' => (bool) rand(0, 1),
                        'is_running' => false,
                    ]);
                    $this->stamp($entry, $b[0]);
                }

                // Selective dependency chain (~45% of consecutive subtasks).
                if ($prevSubtask && rand(1, 100) <= 45) {
                    $subtask->dependencies()->attach($prevSubtask->id, ['dependency_type' => 'blocks']);
                    DB::table('subtask_dependencies')
                        ->where('subtask_id', $subtask->id)
                        ->where('depends_on_subtask_id', $prevSubtask->id)
                        ->update(['created_at' => $createdAtSub, 'updated_at' => $createdAtSub]);
                }
                $prevSubtask = $subtask;

                // Checklist (30% of subtasks) — all checked since the subtask is done.
                if (rand(1, 10) <= 3) {
                    $items = ['Setup environment', 'Write tests', 'Code review', 'Deploy staging', 'QA sign-off'];
                    shuffle($items);
                    foreach (array_slice($items, 0, rand(3, 5)) as $cPos => $itemName) {
                        $checklist = ChecklistItem::create([
                            'subtask_id' => $subtask->id,
                            'name' => $itemName,
                            'is_checked' => true,
                            'position' => $cPos,
                            'created_by' => $this->userMap[$assignee]->id,
                        ]);
                        $this->stamp($checklist, $createdAtSub);
                    }
                }
            }

            // Parent task: completed at the latest subtask completion, by whoever
            // finished last.
            $task->forceFill([
                'created_at' => $meta['createdAt'],
                'updated_at' => $maxUpdated,
                'completed_at' => $maxCompleted,
                'completed_by' => $lastCompleter,
            ])->saveQuietly();
        }
    }

    /** Find the sprint of a project that contains the given date. */
    private function sprintForDate(string $projectName, string $date): ?Sprint
    {
        foreach ($this->sprintWeeks() as $idx => $w) {
            if ($date >= $w['start'] && $date <= $w['end']) {
                return $this->sprintMap[$projectName . ':Sprint ' . ($idx + 1)] ?? null;
            }
        }
        // Dates after the planned window (late slippage) belong to the last sprint.
        return $this->sprintMap[$projectName . ':Sprint 4'] ?? null;
    }

    private function seedComments(): void
    {
        $templates = [
            'Sudah progress %d%%. Estimasi selesai akhir minggu ini.',
            'Butuh klarifikasi dari tim business terkait requirement.',
            'API endpoint sudah ready untuk di-consume frontend.',
            'Unit test coverage sudah di atas 80%%.',
            'Blocker: menunggu akses credentials dari pihak ketiga.',
            'UI sudah sesuai mockup. Tinggal integration testing.',
            'Deploy ke staging berhasil. Silakan di-test.',
            'Performance test done. Response time < 200ms.',
            'Security review passed. No critical findings.',
            'Sprint review kemarin feedback positif dari stakeholder.',
            'Perlu refactor module ini supaya lebih maintainable.',
            'Bug minor ditemukan di edge case. Sedang diperbaiki.',
        ];

        $weeks = $this->sprintWeeks();

        foreach ($this->taskMap as $taskKey => $task) {
            $projectName = explode(':', $taskKey)[0];
            $meta = $this->taskMeta[$taskKey];
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $members = $this->getSpaceMembers($spaceName);

            // Comments only happen on/after the task was created.
            $minDay = $weeks[$meta['s0']]['start'];
            $validDays = array_values(array_filter($this->workingDays, fn($d) => $d >= $minDay));
            if (empty($validDays)) {
                $validDays = [$minDay];
            }

            $numComments = rand(2, 4);
            for ($i = 0; $i < $numComments; $i++) {
                $day = $validDays[array_rand($validDays)];
                $ts = $this->randomWorkTime($day);
                // A comment can never predate the task it belongs to.
                if ($ts <= $task->created_at->format('Y-m-d H:i:s')) {
                    $ts = date('Y-m-d H:i:s', $this->nextCursor($this->cursorOf($task), 30, 180));
                }
                $comment = Comment::create([
                    'task_id' => $task->id,
                    'user_id' => $this->userMap[$members[array_rand($members)]]->id,
                    'content' => sprintf($templates[array_rand($templates)], rand(30, 90)),
                ]);
                $this->stamp($comment, $ts);
            }
        }
    }

    private function seedActivities(): void
    {
        foreach ($this->taskMap as $taskKey => $task) {
            $meta = $this->taskMeta[$taskKey];
            $userId = $task->created_by;

            $createdTs = strtotime($meta['createdAt']);
            $doneTs = strtotime($task->completed_at->format('Y-m-d H:i:s'));

            // "created" — logged at sprint planning.
            $created = Activity::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $userId,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'action' => 'created',
                'properties' => ['name' => $task->name],
            ]);
            $this->stamp($created, $meta['createdAt']);

            // Build the status journey: To Do -> In Progress -> Review -> Done,
            // keeping only the transitions that fit strictly between create & done.
            $points = [];
            $ipTs = strtotime($this->workTime($meta['start'], 9, 11)); // work begins
            if ($ipTs > $createdTs && $ipTs < $doneTs) {
                $points[] = [$ipTs, 'In Progress'];
            }
            $rvTs = $doneTs - rand(2, 5) * 3600; // moved to review a few hours before done
            $lastTs = empty($points) ? $createdTs : end($points)[0];
            if ($rvTs > $lastTs && $rvTs < $doneTs) {
                $points[] = [$rvTs, 'Review'];
            }
            $points[] = [$doneTs, 'Done'];

            $prevStatus = 'To Do';
            foreach ($points as [$ts, $newStatus]) {
                $activity = Activity::create([
                    'workspace_id' => $this->workspace->id,
                    'user_id' => $userId,
                    'subject_type' => Task::class,
                    'subject_id' => $task->id,
                    'action' => 'status_changed',
                    'properties' => ['name' => $task->name],
                    'changes' => ['status' => ['old' => $prevStatus, 'new' => $newStatus]],
                ]);
                $this->stamp($activity, date('Y-m-d H:i:s', $ts));
                $prevStatus = $newStatus;
            }
        }
    }

    private function seedViews(): void
    {
        foreach ($this->spaceMap as $space) {
            $base = $this->cursorOf($space);
            $v1 = View::create(['space_id' => $space->id, 'user_id' => $this->userMap['Leo']->id, 'name' => 'All Tasks', 'type' => 'list', 'is_default' => true, 'is_private' => false, 'position' => 0]);
            $this->stamp($v1, date('Y-m-d H:i:s', $this->nextCursor($base, 1, 6)));
            $v2 = View::create(['space_id' => $space->id, 'user_id' => $this->userMap['Gilbert']->id, 'name' => 'Board View', 'type' => 'board', 'is_default' => false, 'is_private' => false, 'position' => 1]);
            $this->stamp($v2, date('Y-m-d H:i:s', $this->nextCursor($base, 7, 25)));
        }
    }

    // Helpers

    private function getSpaceDevs(string $spaceName): array
    {
        $map = [
            'Manufacture' => ['Aji', 'Mario', 'Grace', 'Alief'],
            'B2B' => ['Vincent', 'Stanley', 'Moses', 'Stefanie'],
            'B2C' => ['Andry', 'Gita', 'Justin', 'Charlie', 'Frans', 'Audi'],
            'Data' => ['Mira', 'Clarissa', 'Danny', 'Nicko', 'Amel'],
        ];
        return $map[$spaceName] ?? [];
    }

    private function getSpaceMembers(string $spaceName): array
    {
        return array_merge(['Leo', 'Gilbert'], $this->getSpaceDevs($spaceName));
    }

    private function getWorkingDays(string $start, string $end): array
    {
        $days = [];
        $current = new \DateTime($start);
        $endDt = new \DateTime($end);
        while ($current <= $endDt) {
            $d = $current->format('Y-m-d');
            if ((int) $current->format('N') <= 5 && !in_array($d, $this->holidays, true)) {
                $days[] = $d;
            }
            $current->modify('+1 day');
        }
        return $days;
    }

    /** Whether a date is an actual working day (weekday and not a holiday). */
    private function isWorkingDay(string $date): bool
    {
        return (int) (new \DateTime($date))->format('N') <= 5
            && !in_array($date, $this->holidays, true);
    }

    private function sprintWeeks(): array
    {
        return [
            ['start' => '2026-04-27', 'end' => '2026-05-03'],
            ['start' => '2026-05-04', 'end' => '2026-05-10'],
            ['start' => '2026-05-11', 'end' => '2026-05-17'],
            ['start' => '2026-05-18', 'end' => '2026-05-22'],
        ];
    }

    /**
     * Add N working days (Mon-Fri, skipping holidays) to a date and return it.
     */
    private function addWorkingDays(string $date, int $days): string
    {
        $current = new \DateTime($date);
        $added = 0;
        while ($added < $days) {
            $current->modify('+1 day');
            if ((int) $current->format('N') <= 5 && !in_array($current->format('Y-m-d'), $this->holidays, true)) {
                $added++;
            }
        }
        return $current->format('Y-m-d');
    }

    /** Step back to the nearest working day strictly before $date. */
    private function previousWorkingDay(string $date): string
    {
        $current = new \DateTime($date);
        do {
            $current->modify('-1 day');
        } while ((int) $current->format('N') > 5 || in_array($current->format('Y-m-d'), $this->holidays, true));
        return $current->format('Y-m-d');
    }

    private function randomWorkTime(string $date): string
    {
        $slots = array_merge(range(8, 11), range(13, 16));
        $hour = $slots[array_rand($slots)];
        return sprintf('%s %02d:%02d:%02d', $date, $hour, rand(0, 59), rand(0, 59));
    }

    /**
     * Pick a realistic time on a given date within working hours, with
     * non-rounded minutes and seconds (so timestamps rarely land on :00).
     * The 12:00 lunch hour is skipped.
     */
    private function workTime(string $date, int $minHour = 8, int $maxHour = 16): string
    {
        $hour = rand($minHour, $maxHour);
        if ($hour === 12) {
            $hour = 13; // skip lunch
        }
        return sprintf('%s %02d:%02d:%02d', $date, $hour, rand(0, 59), rand(0, 59));
    }

    /**
     * Advance a unix-time cursor by an irregular human gap (a random number of
     * minutes within [$minMin, $maxMin] plus random seconds), then keep it
     * inside working hours — rolling over the lunch hour, the end of the day,
     * and weekends as needed. Produces organic, non-uniform spacing.
     */
    private function nextCursor(int $cursor, int $minMin, int $maxMin): int
    {
        $cursor += rand($minMin * 60, $maxMin * 60);

        if ((int) date('H', $cursor) === 12) {
            $cursor += 3600; // skip the lunch hour
        }

        if ((int) date('H', $cursor) >= 17) {
            // Roll over to the next working morning at a random-ish start time.
            $cursor = strtotime(date('Y-m-d', strtotime('+1 day', $cursor)) . ' 08:00:00')
                + rand(0, 55 * 60) + rand(0, 59);
            while ((int) date('N', $cursor) > 5) {
                $cursor = strtotime('+1 day', $cursor);
            }
        }

        return $cursor;
    }

    /** Convert a model's stored created_at into a unix timestamp cursor. */
    private function cursorOf($model): int
    {
        return strtotime($model->created_at->format('Y-m-d H:i:s'));
    }

    /**
     * Lazily initialise (and return) a user's remaining work-minute budget for a
     * given working day. Diligent staff have a full 8h (480m); the "lazy" few
     * have a lighter, variable day (~6-7h).
     */
    private function capacityFor(string $userName, string $date): int
    {
        if (!isset($this->capRemaining[$userName][$date])) {
            if (in_array($userName, $this->lazyUsers, true)) {
                $this->capRemaining[$userName][$date] = rand(360, 430); // ~6-7h
                $this->dayCursor[$userName][$date] = sprintf('08:%02d:%02d', rand(3, 28), rand(0, 59));
            } else {
                $this->capRemaining[$userName][$date] = 480; // full 8h
                $this->dayCursor[$userName][$date] = sprintf('08:00:%02d', rand(0, 45));
            }
        }
        return $this->capRemaining[$userName][$date];
    }

    /**
     * Place one work block of up to $minutes for a user on a date, splitting
     * around the 12:00-13:00 lunch break and never crossing 17:30. Returns a
     * list of [startedAt, endedAt, minutes] entries (1 or 2), or [] if the day
     * is already full. Updates the capacity ledger and day cursor.
     *
     * @return array<int, array{0:string,1:string,2:int}>
     */
    private function placeBlock(string $userName, string $date, int $minutes): array
    {
        $remaining = $this->capacityFor($userName, $date);
        if ($remaining <= 0 || $minutes <= 0) {
            return [];
        }
        $minutes = min($minutes, $remaining);

        $cursor = strtotime($date . ' ' . $this->dayCursor[$userName][$date]);
        $noon = strtotime($date . ' 12:00:00');
        if (!isset($this->lunchResume[$userName][$date])) {
            // People drift back from lunch a few minutes after 13:00.
            $this->lunchResume[$userName][$date] = strtotime($date . ' 13:00:00') + rand(60, 600);
        }
        $afternoon = $this->lunchResume[$userName][$date];
        $hardStop = strtotime($date . ' 17:40:00');

        // If sitting inside the lunch hour, jump to the afternoon.
        if ($cursor >= $noon && $cursor < $afternoon) {
            $cursor = $afternoon;
        }

        $entries = [];
        $secs = $minutes * 60;

        if ($cursor < $noon) {
            $morningAvail = $noon - $cursor;
            if ($secs <= $morningAvail) {
                $end = $cursor + $secs;
                $entries[] = [date('Y-m-d H:i:s', $cursor), date('Y-m-d H:i:s', $end), $minutes];
                $cursor = ($end >= $noon) ? $afternoon : $end;
            } else {
                // Split across lunch: morning part, then continue after 13:00.
                $entries[] = [date('Y-m-d H:i:s', $cursor), date('Y-m-d H:i:s', $noon), (int) ($morningAvail / 60)];
                $rest = $secs - $morningAvail;
                $end = min($afternoon + $rest, $hardStop);
                $entries[] = [date('Y-m-d H:i:s', $afternoon), date('Y-m-d H:i:s', $end), (int) (($end - $afternoon) / 60)];
                $cursor = $end;
            }
        } else {
            $end = min($cursor + $secs, $hardStop);
            $entries[] = [date('Y-m-d H:i:s', $cursor), date('Y-m-d H:i:s', $end), (int) (($end - $cursor) / 60)];
            $cursor = $end;
        }

        $logged = array_sum(array_column($entries, 2));
        $this->capRemaining[$userName][$date] = max(0, $remaining - $logged);
        $this->dayCursor[$userName][$date] = date('H:i:s', $cursor);

        return $entries;
    }

    /**
     * Force-set created_at / updated_at on a model and persist without
     * triggering model events (so timestamps are not overwritten with now()).
     */
    private function stamp($model, string $createdAt, ?string $updatedAt = null): void
    {
        $model->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt ?? $createdAt,
        ])->saveQuietly();
    }

    /**
     * Align pivot table timestamps (Eloquent sets them to now() on attach) to
     * the project timeline, with per-row jitter anchored to the related entity
     * so memberships/labels never predate what they belong to.
     */
    private function backfillPivotTimestamps(): void
    {
        // Workspace & space memberships happen during the setup morning.
        foreach (DB::table('workspace_members')->pluck('id') as $id) {
            $ts = $this->workTime('2026-04-23', 8, 11);
            DB::table('workspace_members')->where('id', $id)->update(['created_at' => $ts, 'updated_at' => $ts]);
        }
        foreach (DB::table('space_members')->pluck('id') as $id) {
            $ts = $this->workTime('2026-04-23', 8, 11);
            DB::table('space_members')->where('id', $id)->update(['created_at' => $ts, 'updated_at' => $ts]);
        }

        // Project members are added shortly after each project is created.
        foreach ($this->projectMap as $project) {
            $base = $this->cursorOf($project);
            foreach (DB::table('project_members')->where('project_id', $project->id)->pluck('id') as $id) {
                $ts = date('Y-m-d H:i:s', $this->nextCursor($base, 1, 40));
                DB::table('project_members')->where('id', $id)->update(['created_at' => $ts, 'updated_at' => $ts]);
            }
        }

        // Task labels are attached when the task itself is created.
        foreach ($this->taskMap as $task) {
            $base = $this->cursorOf($task);
            foreach (DB::table('task_labels')->where('task_id', $task->id)->pluck('id') as $id) {
                $ts = date('Y-m-d H:i:s', $base + rand(20, 600));
                DB::table('task_labels')->where('id', $id)->update(['created_at' => $ts, 'updated_at' => $ts]);
            }
        }
    }

    private function getProjectDefinitions(): array
    {
        return [
            ['name' => 'MRP Forecast Simulation', 'space' => 'Manufacture', 'folder' => 'Planning & Stock'],
            ['name' => 'Product Monitor - Frame Number Management', 'space' => 'Manufacture', 'folder' => 'Product Monitoring'],
            ['name' => 'Product Monitor - Scan Bike Part Serial Number', 'space' => 'Manufacture', 'folder' => 'Product Monitoring'],
            ['name' => 'Promotion Stock Management', 'space' => 'Manufacture', 'folder' => 'Planning & Stock'],
            ['name' => 'Vendor Invoicing and Automatic MIRO', 'space' => 'B2B', 'folder' => 'Finance & Invoicing'],
            ['name' => 'Booking & Bidding Shipment for Vendor Freight Forwarder', 'space' => 'B2B', 'folder' => 'Operations'],
            ['name' => 'Invoice Process Automation', 'space' => 'B2B', 'folder' => 'Finance & Invoicing'],
            ['name' => 'Sales Reps Dashboard', 'space' => 'B2B', 'folder' => 'Operations'],
            ['name' => 'Applicant Recruitment Agentic AI', 'space' => 'B2B', 'folder' => 'Operations'],
            ['name' => 'Bike & PAA Catalog Journey Revamp', 'space' => 'B2C', 'folder' => 'E-Commerce'],
            ['name' => 'AI E-Commerce Product Recommendation', 'space' => 'B2C', 'folder' => 'E-Commerce'],
            ['name' => 'AI Up-selling, Cross-selling, Re-selling', 'space' => 'B2C', 'folder' => 'E-Commerce'],
            ['name' => 'B2C x POS x Marketplace - Realtime Stock Integration', 'space' => 'B2C', 'folder' => 'E-Commerce'],
            ['name' => 'Bike Fitting Integration', 'space' => 'B2C', 'folder' => 'Bike Services'],
            ['name' => 'Bike Service Booking', 'space' => 'B2C', 'folder' => 'Bike Services'],
            ['name' => 'Click & Collect', 'space' => 'B2C', 'folder' => 'Bike Services'],
            ['name' => 'E-commerce Product Description Generation', 'space' => 'B2C', 'folder' => 'E-Commerce'],
            ['name' => 'Membership - Referral Program & Remake', 'space' => 'B2C', 'folder' => 'Customer Engagement'],
            ['name' => 'Net Promoter Score', 'space' => 'B2C', 'folder' => 'Customer Engagement'],
            ['name' => 'Test Ride Booking', 'space' => 'B2C', 'folder' => 'Bike Services'],
            ['name' => 'AI Customer Service', 'space' => 'Data', 'folder' => 'AI & ML'],
            ['name' => 'Catalog Search Correction & Suggestion', 'space' => 'Data', 'folder' => 'Catalog Intelligence'],
            ['name' => 'Catalog Bike Recommendation based on Dealer', 'space' => 'Data', 'folder' => 'Catalog Intelligence'],
        ];
    }

    private function getSupplementaryTasks(): array
    {
        return [
            ['name' => 'Analisis Kebutuhan & Dokumentasi', 'desc' => "Analisis kebutuhan sistem:\n- Wawancara stakeholder\n- Dokumen SRS\n- User story & acceptance criteria", 'labels' => ['Documentation']],
            ['name' => 'Desain Database & Migrasi', 'desc' => "Perancangan basis data:\n- ERD & normalisasi\n- Migration & seeder\n- Indexing", 'labels' => ['Feature']],
            ['name' => 'Pengembangan Backend & API', 'desc' => "Pengembangan backend:\n- Endpoint REST\n- Business logic\n- Validasi & error handling", 'labels' => ['Feature', 'API']],
            ['name' => 'Pengembangan Frontend & UI', 'desc' => "Pengembangan antarmuka:\n- Komponen UI\n- State management\n- Responsiveness", 'labels' => ['Feature', 'UI/UX']],
            ['name' => 'Integration Testing', 'desc' => "Pengujian integrasi:\n- Test antar modul\n- Skenario end-to-end\n- Regression test", 'labels' => ['Feature']],
            ['name' => 'Security Hardening & Audit', 'desc' => "Penguatan keamanan:\n- Audit kerentanan\n- Proteksi OWASP Top 10\n- Penetration test", 'labels' => ['Security']],
            ['name' => 'Optimasi Performa', 'desc' => "Optimasi performa:\n- Query tuning\n- Caching\n- Load testing", 'labels' => ['Performance']],
            ['name' => 'User Acceptance Testing (UAT)', 'desc' => "UAT bersama pengguna:\n- Skenario uji\n- Pengumpulan feedback\n- Sign-off", 'labels' => ['Documentation']],
            ['name' => 'Bug Fixing & Stabilisasi', 'desc' => "Perbaikan bug:\n- Triage issue\n- Fix & verifikasi\n- Stabilisasi rilis", 'labels' => ['Bug']],
            ['name' => 'Deployment & Go-Live', 'desc' => "Deployment produksi:\n- Setup environment\n- Pipeline CI/CD\n- Monitoring pasca rilis", 'labels' => ['Feature']],
            ['name' => 'Dokumentasi Teknis', 'desc' => "Dokumentasi teknis:\n- API docs\n- Panduan deployment\n- Diagram arsitektur", 'labels' => ['Documentation']],
            ['name' => 'Setup Monitoring & Logging', 'desc' => "Monitoring & logging:\n- Centralized logging\n- Alerting\n- Dashboard observability", 'labels' => ['Feature']],
        ];
    }

    private function getTaskTemplates(): array
    {
        return [
            'MRP Forecast Simulation' => [
                ['name' => 'Demand Forecasting Engine', 'desc' => "Engine forecasting demand:\n- Data historis penjualan\n- Machine learning model\n- Visualisasi hasil prediksi", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Production Planning Module', 'desc' => "Modul perencanaan produksi:\n- Kapasitas mesin\n- BOM (Bill of Materials)\n- Scheduling optimasi", 'labels' => ['Feature']],
                ['name' => 'Forecast Accuracy Dashboard', 'desc' => "Dashboard akurasi forecast:\n- MAPE & bias metrics\n- Drill-down per SKU\n- Export laporan", 'labels' => ['Feature', 'UI/UX']],
            ],
            'Product Monitor - Frame Number Management' => [
                ['name' => 'Frame Number Registration System', 'desc' => "Sistem registrasi nomor rangka:\n- Input manual & scan\n- Validasi format\n- Database tracking", 'labels' => ['Feature', 'Security']],
                ['name' => 'Frame Number Validation & Audit', 'desc' => "Validasi & audit nomor rangka:\n- Cek duplikasi\n- Audit trail perubahan\n- Alert anomali", 'labels' => ['Feature']],
                ['name' => 'Frame Tracking Report', 'desc' => "Laporan tracking rangka:\n- Status per unit\n- Filter periode\n- Export PDF/Excel", 'labels' => ['Feature', 'Documentation']],
            ],
            'Product Monitor - Scan Bike Part Serial Number' => [
                ['name' => 'Serial Number Scanner Integration', 'desc' => "Integrasi scanner:\n- Barcode reader API\n- QR code support\n- Real-time validation", 'labels' => ['Feature', 'API']],
                ['name' => 'Batch Scan & Bulk Upload', 'desc' => "Scan massal & bulk upload:\n- Batch processing\n- Error handling\n- Progress indicator", 'labels' => ['Feature', 'Performance']],
                ['name' => 'Part Traceability Dashboard', 'desc' => "Dashboard ketertelusuran part:\n- Lookup per serial\n- History assembly\n- Visualisasi", 'labels' => ['Feature', 'UI/UX']],
            ],
            'Promotion Stock Management' => [
                ['name' => 'Promotion Allocation Engine', 'desc' => "Engine alokasi promosi:\n- Stok khusus promo\n- Auto-reserve\n- Expiry management", 'labels' => ['Feature']],
                ['name' => 'Promo Stock Reservation', 'desc' => "Reservasi stok promo:\n- Hold & release\n- Konflik antar promo\n- Notifikasi habis", 'labels' => ['Feature']],
                ['name' => 'Promotion Monitoring Dashboard', 'desc' => "Dashboard monitoring promo:\n- Sisa stok promo\n- Penyerapan per channel\n- Alert threshold", 'labels' => ['Feature', 'UI/UX']],
            ],
            'Vendor Invoicing and Automatic MIRO' => [
                ['name' => 'Automated MIRO Processing', 'desc' => "Proses MIRO otomatis:\n- Invoice matching\n- 3-way match\n- Auto-posting SAP", 'labels' => ['Feature', 'API']],
                ['name' => 'Vendor Invoice Portal', 'desc' => "Portal vendor:\n- Upload invoice\n- Status tracking\n- Document management", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Invoice Reconciliation & Audit', 'desc' => "Rekonsiliasi & audit invoice:\n- Matching otomatis\n- Exception report\n- Audit log", 'labels' => ['Feature', 'Security']],
            ],
            'Booking & Bidding Shipment for Vendor Freight Forwarder' => [
                ['name' => 'Shipment Bidding Platform', 'desc' => "Platform bidding pengiriman:\n- Vendor registration\n- Bidding flow\n- Auto-award rules", 'labels' => ['Feature']],
                ['name' => 'Vendor Onboarding & Verification', 'desc' => "Onboarding vendor:\n- Registrasi & verifikasi dokumen\n- Approval workflow\n- Rating vendor", 'labels' => ['Feature', 'Security']],
                ['name' => 'Rate Comparison & Award Automation', 'desc' => "Perbandingan tarif & award:\n- Komparasi multi-vendor\n- Aturan pemenang\n- Notifikasi otomatis", 'labels' => ['Feature']],
            ],
            'Invoice Process Automation' => [
                ['name' => 'OCR Invoice Extraction', 'desc' => "Ekstraksi data invoice:\n- OCR processing\n- Template matching\n- Data validation", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Approval Workflow Engine', 'desc' => "Engine approval invoice:\n- Multi-level approval\n- Delegasi & eskalasi\n- SLA tracking", 'labels' => ['Feature']],
                ['name' => 'Accounting System Integration', 'desc' => "Integrasi sistem akuntansi:\n- Sinkronisasi jurnal\n- Mapping COA\n- Error reconciliation", 'labels' => ['Feature', 'API']],
            ],
            'Sales Reps Dashboard' => [
                ['name' => 'Sales Performance Dashboard', 'desc' => "Dashboard performa sales:\n- KPI metrics\n- Territory mapping\n- Pipeline tracking", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Territory & Target Management', 'desc' => "Manajemen teritori & target:\n- Assign teritori\n- Set target bulanan\n- Achievement tracking", 'labels' => ['Feature']],
                ['name' => 'Real-time Sales Reporting', 'desc' => "Laporan sales real-time:\n- Streaming data\n- Drill-down\n- Export terjadwal", 'labels' => ['Feature', 'Performance']],
            ],
            'Applicant Recruitment Agentic AI' => [
                ['name' => 'AI Resume Screening Agent', 'desc' => "Agent AI screening:\n- Resume parsing\n- Skill matching\n- Scoring algorithm", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Candidate Ranking & Shortlist', 'desc' => "Ranking & shortlist kandidat:\n- Ranking otomatis\n- Bias mitigation\n- Rekomendasi", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Interview Scheduling Automation', 'desc' => "Otomasi jadwal interview:\n- Sinkronisasi kalender\n- Reminder\n- Feedback form", 'labels' => ['Feature']],
            ],
            'Bike & PAA Catalog Journey Revamp' => [
                ['name' => 'Catalog UX Redesign', 'desc' => "Redesign katalog:\n- New navigation\n- Product comparison\n- Mobile responsive", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'PAA Integration', 'desc' => "Parts & Accessories:\n- Cross-sell\n- Compatibility checker\n- Bundle pricing", 'labels' => ['Feature', 'API']],
                ['name' => 'Product Comparison Feature', 'desc' => "Fitur perbandingan produk:\n- Side-by-side compare\n- Spec table\n- Share comparison", 'labels' => ['Feature']],
            ],
            'AI E-Commerce Product Recommendation' => [
                ['name' => 'Recommendation Engine', 'desc' => "Engine rekomendasi:\n- Collaborative filtering\n- Content-based filtering\n- Ranking", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Personalization & User Profiling', 'desc' => "Personalisasi & profiling:\n- User behavior tracking\n- Segmentasi\n- Real-time update", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'A/B Testing Framework', 'desc' => "Framework A/B testing:\n- Experiment setup\n- Metric tracking\n- Statistical significance", 'labels' => ['Feature', 'Performance']],
            ],
            'AI Up-selling, Cross-selling, Re-selling' => [
                ['name' => 'Upsell/Cross-sell Engine', 'desc' => "Engine upsell:\n- Rule-based suggestions\n- ML predictions\n- Cart analysis", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Cart Analysis & Trigger', 'desc' => "Analisis keranjang & trigger:\n- Real-time cart scan\n- Trigger penawaran\n- Email follow-up", 'labels' => ['Feature']],
            ],
            'B2C x POS x Marketplace - Realtime Stock Integration' => [
                ['name' => 'Realtime Stock Sync', 'desc' => "Sync stok real-time:\n- Multi-channel inventory\n- Event-driven updates\n- Conflict resolution", 'labels' => ['Feature', 'API', 'Performance']],
                ['name' => 'Multi-channel Inventory Dashboard', 'desc' => "Dashboard inventori multi-channel:\n- Stok per channel\n- Alert mismatch\n- Histori sync", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Conflict Resolution & Reconciliation', 'desc' => "Resolusi konflik & rekonsiliasi:\n- Deteksi konflik\n- Aturan prioritas\n- Manual override", 'labels' => ['Feature']],
            ],
            'Bike Fitting Integration' => [
                ['name' => 'Bike Fitting Calculator', 'desc' => "Kalkulator fitting:\n- Body measurement\n- Size recommendation\n- Frame geometry", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Size Recommendation Engine', 'desc' => "Engine rekomendasi ukuran:\n- Antropometri input\n- Model rekomendasi\n- Confidence score", 'labels' => ['Feature', 'AI/ML']],
            ],
            'Bike Service Booking' => [
                ['name' => 'Service Booking System', 'desc' => "Sistem booking servis:\n- Calendar management\n- Mechanic assignment\n- Reminder notification", 'labels' => ['Feature']],
                ['name' => 'Mechanic & Slot Management', 'desc' => "Manajemen mekanik & slot:\n- Ketersediaan mekanik\n- Kapasitas slot\n- Auto-assign", 'labels' => ['Feature']],
                ['name' => 'Service Reminder Notification', 'desc' => "Notifikasi pengingat servis:\n- Reminder berkala\n- Multi-channel (email/WA)\n- Riwayat servis", 'labels' => ['Feature']],
            ],
            'Click & Collect' => [
                ['name' => 'Click & Collect Flow', 'desc' => "Alur click & collect:\n- Store availability\n- Pickup slot booking\n- QR code generation", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Store Availability & Pickup Slot', 'desc' => "Ketersediaan toko & slot pickup:\n- Cek stok per toko\n- Booking slot\n- Notifikasi siap ambil", 'labels' => ['Feature']],
            ],
            'E-commerce Product Description Generation' => [
                ['name' => 'AI Description Generator', 'desc' => "Generator deskripsi:\n- GPT integration\n- Template management\n- Bulk generate", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Template & Multi-language Support', 'desc' => "Template & multi-bahasa:\n- Manajemen template\n- Terjemahan otomatis\n- Tone konsisten", 'labels' => ['Feature']],
                ['name' => 'SEO Optimization Module', 'desc' => "Modul optimasi SEO:\n- Keyword injection\n- Meta description\n- Readability score", 'labels' => ['Feature', 'Performance']],
            ],
            'Membership - Referral Program & Remake' => [
                ['name' => 'Referral System Revamp', 'desc' => "Revamp referral:\n- Reward tier system\n- Tracking dashboard\n- Link generator", 'labels' => ['Feature', 'Enhancement']],
                ['name' => 'Reward Tier & Payout Automation', 'desc' => "Tier reward & payout:\n- Aturan tier\n- Payout otomatis\n- Histori reward", 'labels' => ['Feature']],
                ['name' => 'Referral Fraud Detection', 'desc' => "Deteksi fraud referral:\n- Pattern detection\n- Self-referral block\n- Alert mencurigakan", 'labels' => ['Feature', 'Security']],
            ],
            'Net Promoter Score' => [
                ['name' => 'NPS Survey System', 'desc' => "Sistem survey NPS:\n- Email/SMS trigger\n- Score calculation\n- Trend analysis", 'labels' => ['Feature', 'UI/UX']],
                ['name' => 'Response Collection & Scoring', 'desc' => "Koleksi respon & scoring:\n- Form responsif\n- Kalkulasi NPS\n- Kategorisasi promoter/detractor", 'labels' => ['Feature']],
            ],
            'Test Ride Booking' => [
                ['name' => 'Test Ride Booking Platform', 'desc' => "Platform test ride:\n- Bike availability\n- Location selection\n- Slot management", 'labels' => ['Feature']],
                ['name' => 'Bike Availability & Location', 'desc' => "Ketersediaan unit & lokasi:\n- Stok unit demo\n- Pemetaan lokasi\n- Filter tipe sepeda", 'labels' => ['Feature']],
                ['name' => 'Post-ride Feedback', 'desc' => "Feedback pasca test ride:\n- Form rating\n- Follow-up sales\n- Analisis minat", 'labels' => ['Feature', 'UI/UX']],
            ],
            'AI Customer Service' => [
                ['name' => 'AI Chatbot Development', 'desc' => "Chatbot AI:\n- NLP training\n- Intent recognition\n- Multi-turn dialog", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Knowledge Base & Intent Training', 'desc' => "Knowledge base & training:\n- Kurasi artikel\n- Labeling intent\n- Continuous learning", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Human Escalation & Handoff', 'desc' => "Eskalasi ke agen manusia:\n- Deteksi kebutuhan handoff\n- Context passing\n- Antrian agen", 'labels' => ['Feature']],
            ],
            'Catalog Search Correction & Suggestion' => [
                ['name' => 'Search Autocorrect & Suggest', 'desc' => "Koreksi pencarian:\n- Typo correction\n- Did-you-mean\n- Autocomplete", 'labels' => ['Feature', 'AI/ML', 'Performance']],
                ['name' => 'Synonym & Popular Search Mapping', 'desc' => "Sinonim & pencarian populer:\n- Mapping sinonim\n- Trending queries\n- Boosting hasil", 'labels' => ['Feature']],
            ],
            'Catalog Bike Recommendation based on Dealer' => [
                ['name' => 'Dealer-Based Recommendation', 'desc' => "Rekomendasi dealer:\n- Dealer profiling\n- Regional preferences\n- Ranking model", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Dealer Profiling & Segmentation', 'desc' => "Profiling & segmentasi dealer:\n- Klasterisasi dealer\n- Pola penjualan\n- Tagging segmen", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Regional Sales Prediction', 'desc' => "Prediksi penjualan regional:\n- Model prediktif\n- Faktor musiman\n- Akurasi per wilayah", 'labels' => ['Feature', 'Performance']],
            ],
        ];
    }
}
