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
    private array $sprintMap = [];
    private array $taskMap = [];
    private array $taskMeta = [];
    private ?Workspace $workspace = null;

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
        $this->stamp($this->workspace, '2026-04-23 08:30:00');

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

        foreach ($spaces as $pos => $s) {
            $space = Space::create([
                'workspace_id' => $this->workspace->id,
                'name' => $s['name'],
                'color' => $s['color'],
                'position' => $pos,
                'created_by' => $this->userMap['Leo']->id,
            ]);
            $this->stamp($space, $this->offsetTime('2026-04-23 09:00:00', $pos * 20));
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
            foreach ($defs as $pos => $st) {
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
                $this->stamp($status, $space->created_at->copy()->addMinutes($pos + 1)->format('Y-m-d H:i:s'));
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

        foreach ($labels as $i => $l) {
            $label = Label::create(['workspace_id' => $this->workspace->id, 'name' => $l['name'], 'color' => $l['color']]);
            $this->stamp($label, $this->offsetTime('2026-04-23 10:30:00', $i * 2));
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

        $folderIndex = 0;
        foreach ($folders as $spaceName => $list) {
            foreach ($list as $pos => $f) {
                $folder = Folder::create([
                    'space_id' => $this->spaceMap[$spaceName]->id,
                    'name' => $f['name'],
                    'color' => $f['color'],
                    'position' => $pos,
                    'created_by' => $this->userMap['Leo']->id,
                ]);
                $this->stamp($folder, $this->offsetTime('2026-04-24 08:30:00', $folderIndex * 8));
                $this->folderMap[$f['name']] = $folder;
                $folderIndex++;
            }
        }
    }

    private function seedProjects(): void
    {
        $projects = $this->getProjectDefinitions();

        foreach ($projects as $i => $p) {
            $space = $this->spaceMap[$p['space']];
            $folder = $this->folderMap[$p['folder']] ?? null;
            $statuses = ['In Progress', 'In Progress', 'To Do', 'Review', 'Backlog'];
            $statusName = $statuses[array_rand($statuses)];

            $project = Project::create([
                'space_id' => $space->id,
                'folder_id' => $folder?->id,
                'name' => $p['name'],
                'status_id' => $this->statusMap[$p['space'] . ':' . $statusName]->id,
                'created_by' => $this->userMap['Leo']->id,
            ]);
            $this->stamp($project, $this->kickoffTime($i));
            $this->projectMap[$p['name']] = $project;

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
            foreach ($weeks as $pos => $w) {
                $sprint = Sprint::create([
                    'space_id' => $project->space_id,
                    'project_id' => $project->id,
                    'name' => $w['label'] . ' - ' . substr($projectName, 0, 30),
                    'goal' => $goals[$pos],
                    'start_date' => $w['start'],
                    'end_date' => $w['end'],
                    'is_active' => $pos === 2,
                    'position' => $pos,
                ]);
                $this->stamp($sprint, $project->created_at->copy()->addMinutes($pos + 1)->format('Y-m-d H:i:s'));
                $this->sprintMap[$projectName . ':' . $w['label']] = $sprint;
            }
        }
    }

    private function seedTasks(): void
    {
        $templates = $this->getTaskTemplates();
        $supplementary = $this->getSupplementaryTasks();
        $weeks = $this->sprintWeeks();

        // Build the full task list per project: domain-specific tasks topped up
        // with realistic SDLC phase tasks so each project has at least 5 tasks.
        $projectTasks = [];
        $grandTotal = 0;
        foreach ($templates as $projectName => $curated) {
            $target = rand(5, 7);
            $tasks = $curated;
            if (count($tasks) < $target) {
                $pool = $supplementary;
                shuffle($pool);
                $tasks = array_merge($tasks, array_slice($pool, 0, $target - count($tasks)));
            }
            $projectTasks[$projectName] = $tasks;
            $grandTotal += count($tasks);
        }

        // Apply the 16% overdue quota across ALL tasks of ALL projects.
        $lateQuota = (int) floor($grandTotal * 0.16);
        $indices = range(0, $grandTotal - 1);
        shuffle($indices);
        $lateKeys = array_fill_keys(array_slice($indices, 0, $lateQuota), true);

        $globalIdx = 0;
        foreach ($projectTasks as $projectName => $tasks) {
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $devs = $this->getSpaceDevs($spaceName);
            $count = count($tasks);

            foreach ($tasks as $pos => $tDef) {
                $isLate = isset($lateKeys[$globalIdx]);
                $globalIdx++;

                // Spread tasks across the 4 sprints by their order in the project.
                $s0 = min((int) floor($pos / $count * 4), 3);
                $s1 = min($s0 + rand(1, 2) - 1, 3);

                // Period already passed: every task is completed (Done).
                $statusName = 'Done';
                $priority = [1, 2, 2, 3, 3, 3][array_rand([1, 2, 2, 3, 3, 3])];
                $createdBy = $this->userMap[['Leo', 'Gilbert'][rand(0, 1)]];

                $task = Task::create([
                    'project_id' => $project->id,
                    'status_id' => $this->statusMap[$spaceName . ':' . $statusName]->id,
                    'priority_level' => $priority,
                    'name' => $tDef['name'],
                    'description' => $tDef['desc'],
                    'start_date' => $weeks[$s0]['start'],
                    'due_date' => $weeks[$s1]['end'],
                    'time_estimate' => [960, 1200, 1440, 1920, 2400][array_rand([960, 1200, 1440, 1920, 2400])],
                    'position' => $pos,
                    'created_by' => $createdBy->id,
                ]);

                $createdAt = $weeks[$s0]['start'] . ' 08:' . str_pad((string) (($pos * 5) % 50), 2, '0', STR_PAD_LEFT) . ':00';
                $this->stamp($task, $createdAt);

                $this->taskMap[$projectName . ':' . $pos] = $task;
                $this->taskMeta[$projectName . ':' . $pos] = [
                    'isLate' => $isLate,
                    's0' => $s0,
                    's1' => $s1,
                    'createdAt' => $createdAt,
                ];

                // Assignees
                $assignees = $devs;
                shuffle($assignees);
                foreach (array_slice($assignees, 0, min(rand(1, 3), count($devs))) as $a) {
                    $task->assignees()->attach($this->userMap[$a]->id, ['assigned_by' => $createdBy->id]);
                }

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
        $weeks = $this->sprintWeeks();

        foreach ($this->taskMap as $taskKey => $task) {
            $projectName = explode(':', $taskKey)[0];
            $meta = $this->taskMeta[$taskKey];
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $devs = $this->getSpaceDevs($spaceName);

            $s0 = $meta['s0'];
            $s1 = $meta['s1'];
            $spanSprints = range($s0, $s1);
            $isLate = $meta['isLate'];

            $numSubtasks = rand(3, 6);
            $shuffled = $subtaskNames;
            shuffle($shuffled);
            $prevSubtask = null;
            $maxUpdated = $meta['createdAt'];

            // For late tasks, the trailing subtask(s) are completed AFTER their
            // due date (schedule slippage). The rest finish on time.
            $lateFrom = $isLate ? $numSubtasks - rand(1, 2) : $numSubtasks;

            for ($i = 0; $i < $numSubtasks; $i++) {
                $sprintIdx = $spanSprints[(int) floor($i / max(1, $numSubtasks) * count($spanSprints))] ?? $s1;
                $sprintIdx = min($sprintIdx, 3);
                $sprint = $this->sprintMap[$projectName . ':Sprint ' . ($sprintIdx + 1)] ?? null;

                $sprintDays = $this->getWorkingDays($weeks[$sprintIdx]['start'], $weeks[$sprintIdx]['end']);
                $dayCount = count($sprintDays);
                $startIdx = $i % $dayCount;
                $startDay = $sprintDays[$startIdx];
                $endDay = $sprintDays[min($startIdx + rand(1, 2), $dayCount - 1)];

                $isCompletedLate = $i >= $lateFrom;

                // Completion: on-time finishes on/before due date, late finishes
                // 1-6 working days after it.
                if ($isCompletedLate) {
                    $completedDay = $this->addWorkingDays($endDay, rand(1, 6));
                } else {
                    $completedDay = $endDay;
                }
                $completedAt = $completedDay . ' ' . rand(14, 16) . ':' . str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00';

                $timeEstimate = [120, 180, 240, 300, 360, 480][array_rand([120, 180, 240, 300, 360, 480])];
                $optimistic = (int) ($timeEstimate * 0.7);
                $pessimistic = (int) ($timeEstimate * 1.5);
                // Late subtasks tend to consume more time than estimated.
                $timeSpent = $isCompletedLate ? rand($timeEstimate, (int) ($timeEstimate * 1.8)) : rand($optimistic, $pessimistic);

                $assignee = $devs[array_rand($devs)];
                $createdAt = $startDay . ' 09:' . str_pad((string) (($i * 7) % 50), 2, '0', STR_PAD_LEFT) . ':00';
                $updatedAt = $completedAt;
                if ($updatedAt > $maxUpdated) {
                    $maxUpdated = $updatedAt;
                }

                $subtask = Subtask::create([
                    'task_id' => $task->id,
                    'sprint_id' => $sprint?->id,
                    'status_id' => $this->statusMap[$spaceName . ':Done']->id,
                    'priority_level' => [1, 2, 2, 3, 3, 4][array_rand([1, 2, 2, 3, 3, 4])],
                    'name' => $shuffled[$i % count($shuffled)],
                    'start_date' => $startDay . ' 08:00:00',
                    'due_date' => $endDay . ' 17:00:00',
                    'baseline_start_date' => $startDay . ' 08:00:00',
                    'baseline_due_date' => $endDay . ' 17:00:00',
                    'completed_at' => $completedAt,
                    'time_estimate' => $timeEstimate,
                    'optimistic_estimate' => $optimistic,
                    'most_likely_estimate' => $timeEstimate,
                    'pessimistic_estimate' => $pessimistic,
                    'time_spent' => $timeSpent,
                    'progress' => 100,
                    'position' => $i,
                    'created_by' => $this->userMap[$assignee]->id,
                    'completed_by' => $this->userMap[$assignee]->id,
                ]);
                $this->stamp($subtask, $createdAt, $updatedAt);

                $subtask->assignees()->attach($this->userMap[$assignee]->id, ['assigned_by' => $this->userMap['Leo']->id]);

                // Dependency chain
                if ($prevSubtask) {
                    $subtask->dependencies()->attach($prevSubtask->id, ['dependency_type' => 'blocks']);
                }
                $prevSubtask = $subtask;

                // Time entry
                if ($timeSpent > 0) {
                    $startedAt = $startDay . ' ' . str_pad((string) rand(10, 11), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00';
                    $endedAt = $startDay . ' ' . str_pad((string) rand(14, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00';
                    $entry = TimeEntry::create([
                        'subtask_id' => $subtask->id,
                        'user_id' => $this->userMap[$assignee]->id,
                        'duration' => $timeSpent,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'is_billable' => (bool) rand(0, 1),
                        'is_running' => false,
                    ]);
                    $this->stamp($entry, $startedAt);
                }

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
                        $this->stamp($checklist, $createdAt);
                    }
                }
            }

            // Parent task reflects the last activity on its subtasks.
            $this->stamp($task, $meta['createdAt'], $maxUpdated);
        }
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
                $comment = Comment::create([
                    'task_id' => $task->id,
                    'user_id' => $this->userMap[$members[array_rand($members)]]->id,
                    'content' => sprintf($templates[array_rand($templates)], rand(30, 90)),
                ]);
                $this->stamp($comment, $this->randomWorkTime($day));
            }
        }
    }

    private function seedActivities(): void
    {
        foreach ($this->taskMap as $taskKey => $task) {
            $meta = $this->taskMeta[$taskKey];

            // "created" activity at the moment the task was created.
            $created = Activity::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $task->created_by,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'action' => 'created',
                'properties' => ['name' => $task->name],
            ]);
            $this->stamp($created, $meta['createdAt']);

            // Every task is completed — log the status change to Done.
            $completedActivity = Activity::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $task->created_by,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'action' => 'status_changed',
                'properties' => ['name' => $task->name],
                'changes' => ['status' => ['old' => 'In Progress', 'new' => 'Done']],
            ]);
            $this->stamp($completedActivity, $task->updated_at->format('Y-m-d H:i:s'));
        }
    }

    private function seedViews(): void
    {
        foreach ($this->spaceMap as $space) {
            View::create(['space_id' => $space->id, 'user_id' => $this->userMap['Leo']->id, 'name' => 'All Tasks', 'type' => 'list', 'is_default' => true, 'is_private' => false, 'position' => 0]);
            View::create(['space_id' => $space->id, 'user_id' => $this->userMap['Gilbert']->id, 'name' => 'Board View', 'type' => 'board', 'is_default' => false, 'is_private' => false, 'position' => 1]);
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
            if ((int)$current->format('N') <= 5) $days[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
        return $days;
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
     * Add N working days (Mon-Fri) to a date and return the resulting date.
     */
    private function addWorkingDays(string $date, int $days): string
    {
        $current = new \DateTime($date);
        $added = 0;
        while ($added < $days) {
            $current->modify('+1 day');
            if ((int) $current->format('N') <= 5) {
                $added++;
            }
        }
        return $current->format('Y-m-d');
    }

    private function randomWorkTime(string $date): string
    {
        $slots = array_merge(range(8, 11), range(13, 16));
        $hour = $slots[array_rand($slots)];
        return sprintf('%s %02d:%02d:%02d', $date, $hour, rand(0, 59), rand(0, 59));
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
     * Add minutes to a base datetime string (simple, same-day offsets).
     */
    private function offsetTime(string $base, int $minutes): string
    {
        return date('Y-m-d H:i:s', strtotime($base) + $minutes * 60);
    }

    /**
     * Staggered kickoff timestamps on 24 April 2026 (planning day),
     * skipping the 12:00-13:00 lunch break.
     */
    private function kickoffTime(int $index, int $stepMin = 10): string
    {
        $t = strtotime('2026-04-24 09:00:00') + $index * $stepMin * 60;
        if ((int) date('H', $t) === 12) {
            $t += 3600; // skip lunch
        }
        if ((int) date('H', $t) >= 17) {
            $t = strtotime('2026-04-24 16:30:00');
        }
        return date('Y-m-d H:i:s', $t);
    }

    /**
     * Align pivot table timestamps (which Eloquent sets to now() on attach)
     * to the planning window so they stay within the project timeline.
     */
    private function backfillPivotTimestamps(): void
    {
        $setup = '2026-04-23 09:00:00';
        $kickoff = '2026-04-24 09:00:00';
        $sprintStart = '2026-04-27 08:00:00';

        DB::table('workspace_members')->update(['created_at' => $setup, 'updated_at' => $setup]);
        DB::table('space_members')->update(['created_at' => $setup, 'updated_at' => $setup]);
        DB::table('project_members')->update(['created_at' => $kickoff, 'updated_at' => $kickoff]);
        DB::table('task_labels')->update(['created_at' => $kickoff, 'updated_at' => $kickoff]);
        DB::table('subtask_labels')->update(['created_at' => $sprintStart, 'updated_at' => $sprintStart]);
        DB::table('subtask_dependencies')->update(['created_at' => $sprintStart, 'updated_at' => $sprintStart]);
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
