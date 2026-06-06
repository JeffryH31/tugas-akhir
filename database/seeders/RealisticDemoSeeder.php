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
    private ?Workspace $workspace = null;

    public function run(): void
    {
        $this->workingDays = $this->getWorkingDays($this->startDate, $this->endDate);

        $this->seedUsers();
        $this->seedWorkspace();
        $this->seedSpaces();
        $this->seedStatuses();
        $this->seedLabels();
        $this->seedFolders();
        $this->seedProjects();
        $this->seedSprints();
        $this->seedTasks();
        $this->seedSubtasks();
        $this->seedComments();
        $this->seedActivities();
        $this->seedViews();
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

        foreach ($labels as $l) {
            $label = Label::create(['workspace_id' => $this->workspace->id, 'name' => $l['name'], 'color' => $l['color']]);
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

        foreach ($folders as $spaceName => $list) {
            foreach ($list as $pos => $f) {
                $folder = Folder::create([
                    'space_id' => $this->spaceMap[$spaceName]->id,
                    'name' => $f['name'],
                    'color' => $f['color'],
                    'position' => $pos,
                    'created_by' => $this->userMap['Leo']->id,
                ]);
                $this->folderMap[$f['name']] = $folder;
            }
        }
    }

    private function seedProjects(): void
    {
        $projects = $this->getProjectDefinitions();

        foreach ($projects as $p) {
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
                $this->sprintMap[$projectName . ':' . $w['label']] = $sprint;
            }
        }
    }

    private function seedTasks(): void
    {
        $templates = $this->getTaskTemplates();

        foreach ($templates as $projectName => $tasks) {
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $devs = $this->getSpaceDevs($spaceName);

            foreach ($tasks as $pos => $tDef) {
                $statuses = ['Done', 'Done', 'In Progress', 'In Progress', 'In Progress', 'Review', 'To Do'];
                $statusName = $statuses[array_rand($statuses)];
                $priority = [1, 2, 2, 3, 3, 3][array_rand([1, 2, 2, 3, 3, 3])];
                $startDate = $this->workingDays[rand(0, min(4, count($this->workingDays) - 1))];
                $dueDate = $this->workingDays[rand(max(5, count($this->workingDays) - 10), count($this->workingDays) - 1)];
                $createdBy = [&$this->userMap['Leo'], &$this->userMap['Gilbert']][array_rand([0, 1])];

                $task = Task::create([
                    'project_id' => $project->id,
                    'status_id' => $this->statusMap[$spaceName . ':' . $statusName]->id,
                    'priority_level' => $priority,
                    'name' => $tDef['name'],
                    'description' => $tDef['desc'],
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'time_estimate' => [960, 1200, 1440, 1920, 2400][array_rand([960, 1200, 1440, 1920, 2400])],
                    'position' => $pos,
                    'created_by' => $this->userMap['Leo']->id,
                ]);
                $this->taskMap[$projectName . ':' . $pos] = $task;

                // Assignees
                $assignees = array_slice($devs, 0, min(rand(1, 3), count($devs)));
                shuffle($assignees);
                foreach ($assignees as $a) {
                    $task->assignees()->attach($this->userMap[$a]->id, ['assigned_by' => $this->userMap['Leo']->id]);
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
        $sprintWeeks = [
            ['start' => '2026-04-27', 'end' => '2026-05-03'],
            ['start' => '2026-05-04', 'end' => '2026-05-10'],
            ['start' => '2026-05-11', 'end' => '2026-05-17'],
            ['start' => '2026-05-18', 'end' => '2026-05-22'],
        ];

        foreach ($this->taskMap as $taskKey => $task) {
            $projectName = explode(':', $taskKey)[0];
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $devs = $this->getSpaceDevs($spaceName);
            $numSubtasks = rand(4, 6);
            $shuffled = $subtaskNames;
            shuffle($shuffled);
            $prevSubtask = null;

            for ($i = 0; $i < $numSubtasks; $i++) {
                $sprintIdx = $i < 2 ? 0 : ($i < 3 ? 1 : ($i < 5 ? 2 : 3));
                $sprintKey = $projectName . ':Sprint ' . ($sprintIdx + 1);
                $sprint = $this->sprintMap[$sprintKey] ?? null;

                $stName = match (true) {
                    $sprintIdx === 0 => 'Done',
                    $sprintIdx === 1 => ['Done', 'Done', 'Review'][array_rand(['Done', 'Done', 'Review'])],
                    $sprintIdx === 2 => ['In Progress', 'In Progress', 'Review', 'To Do'][array_rand(['In Progress', 'In Progress', 'Review', 'To Do'])],
                    default => ['To Do', 'Backlog'][array_rand(['To Do', 'Backlog'])],
                };
                $isCompleted = $stName === 'Done';
                $sprintDays = $this->getWorkingDays($sprintWeeks[$sprintIdx]['start'], $sprintWeeks[$sprintIdx]['end']);
                $startDay = $sprintDays[min($i % count($sprintDays), count($sprintDays) - 1)];
                $endDay = $sprintDays[min(($i % count($sprintDays)) + rand(1, 2), count($sprintDays) - 1)];

                $timeEstimate = [120, 180, 240, 300, 360, 480][array_rand([120, 180, 240, 300, 360, 480])];
                $optimistic = (int)($timeEstimate * 0.7);
                $pessimistic = (int)($timeEstimate * 1.5);
                $timeSpent = $isCompleted ? rand($optimistic, $pessimistic) : ($stName === 'In Progress' ? rand(30, $timeEstimate) : 0);
                $progress = $isCompleted ? 100 : ($stName === 'In Progress' ? rand(20, 70) : ($stName === 'Review' ? rand(80, 95) : 0));
                $assignee = $devs[array_rand($devs)];

                $subtask = Subtask::create([
                    'task_id' => $task->id,
                    'sprint_id' => $sprint?->id,
                    'status_id' => $this->statusMap[$spaceName . ':' . $stName]->id,
                    'priority_level' => [1, 2, 2, 3, 3, 4][array_rand([1, 2, 2, 3, 3, 4])],
                    'name' => $shuffled[$i % count($shuffled)],
                    'start_date' => $startDay . ' 08:00:00',
                    'due_date' => $endDay . ' 17:00:00',
                    'baseline_start_date' => $startDay . ' 08:00:00',
                    'baseline_due_date' => $endDay . ' 17:00:00',
                    'completed_at' => $isCompleted ? $endDay . ' ' . rand(14, 16) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00' : null,
                    'time_estimate' => $timeEstimate,
                    'optimistic_estimate' => $optimistic,
                    'most_likely_estimate' => $timeEstimate,
                    'pessimistic_estimate' => $pessimistic,
                    'time_spent' => $timeSpent,
                    'progress' => $progress,
                    'position' => $i,
                    'created_by' => $this->userMap[$assignee]->id,
                    'completed_by' => $isCompleted ? $this->userMap[$assignee]->id : null,
                ]);

                $subtask->assignees()->attach($this->userMap[$assignee]->id, ['assigned_by' => $this->userMap['Leo']->id]);

                // Dependency chain
                if ($prevSubtask) {
                    $subtask->dependencies()->attach($prevSubtask->id, ['dependency_type' => 'blocks']);
                }
                $prevSubtask = $subtask;

                // Time entry
                if ($timeSpent > 0) {
                    TimeEntry::create([
                        'subtask_id' => $subtask->id,
                        'user_id' => $this->userMap[$assignee]->id,
                        'duration' => $timeSpent,
                        'started_at' => $startDay . ' ' . str_pad(rand(8, 10), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00',
                        'ended_at' => $startDay . ' ' . str_pad(rand(14, 16), 2, '0', STR_PAD_LEFT) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                        'is_billable' => (bool)rand(0, 1),
                        'is_running' => false,
                    ]);
                }

                // Checklist (30% of subtasks)
                if (rand(1, 10) <= 3) {
                    $items = ['Setup environment', 'Write tests', 'Code review', 'Deploy staging', 'QA sign-off'];
                    shuffle($items);
                    foreach (array_slice($items, 0, rand(3, 5)) as $cPos => $itemName) {
                        ChecklistItem::create([
                            'subtask_id' => $subtask->id,
                            'name' => $itemName,
                            'is_checked' => $progress > 50 && rand(0, 2) > 0,
                            'position' => $cPos,
                            'created_by' => $this->userMap[$assignee]->id,
                        ]);
                    }
                }
            }
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

        foreach ($this->taskMap as $taskKey => $task) {
            $projectName = explode(':', $taskKey)[0];
            $project = $this->projectMap[$projectName];
            $spaceName = $project->space->name;
            $members = $this->getSpaceMembers($spaceName);
            $numComments = rand(2, 4);

            for ($i = 0; $i < $numComments; $i++) {
                $day = $this->workingDays[array_rand($this->workingDays)];
                Comment::create([
                    'task_id' => $task->id,
                    'user_id' => $this->userMap[$members[array_rand($members)]]->id,
                    'content' => sprintf($templates[array_rand($templates)], rand(30, 90)),
                    'created_at' => $this->randomWorkTime($day),
                    'updated_at' => $this->randomWorkTime($day),
                ]);
            }
        }
    }

    private function seedActivities(): void
    {
        foreach ($this->taskMap as $task) {
            Activity::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $this->userMap['Leo']->id,
                'subject_type' => Task::class,
                'subject_id' => $task->id,
                'action' => 'created',
                'properties' => ['name' => $task->name],
                'created_at' => $this->startDate . ' 08:' . str_pad(rand(5, 30), 2, '0', STR_PAD_LEFT) . ':00',
            ]);
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

    private function randomWorkTime(string $date): string
    {
        $slots = array_merge(range(8, 11), range(13, 16));
        $hour = $slots[array_rand($slots)];
        return sprintf('%s %02d:%02d:%02d', $date, $hour, rand(0, 59), rand(0, 59));
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

    private function getTaskTemplates(): array
    {
        return [
            'MRP Forecast Simulation' => [
                ['name' => 'Demand Forecasting Engine', 'desc' => "Engine untuk forecasting demand:\n- Data historis penjualan\n- Machine learning model\n- Parameter konfigurasi\n- Visualisasi hasil prediksi", 'labels' => ['Feature', 'AI/ML']],
                ['name' => 'Production Planning Module', 'desc' => "Modul perencanaan produksi:\n- Kapasitas mesin\n- BOM (Bill of Materials)\n- Scheduling optimasi\n- Alert kekurangan material", 'labels' => ['Feature']],
            ],
            'Product Monitor - Frame Number Management' => [['name' => 'Frame Number Registration System', 'desc' => "Sistem registrasi nomor rangka:\n- Input manual & scan\n- Validasi format\n- Database tracking", 'labels' => ['Feature', 'Security']]],
            'Product Monitor - Scan Bike Part Serial Number' => [['name' => 'Serial Number Scanner Integration', 'desc' => "Integrasi scanner:\n- Barcode reader API\n- QR code support\n- Batch scanning", 'labels' => ['Feature', 'API']]],
            'Promotion Stock Management' => [['name' => 'Promotion Allocation Engine', 'desc' => "Engine alokasi promosi:\n- Stok khusus promo\n- Auto-reserve\n- Expiry management", 'labels' => ['Feature']]],
            'Vendor Invoicing and Automatic MIRO' => [
                ['name' => 'Automated MIRO Processing', 'desc' => "Proses MIRO otomatis:\n- Invoice matching\n- 3-way match\n- Auto-posting SAP", 'labels' => ['Feature', 'API']],
                ['name' => 'Vendor Invoice Portal', 'desc' => "Portal vendor:\n- Upload invoice\n- Status tracking\n- Document management", 'labels' => ['Feature', 'UI/UX']],
            ],
            'Booking & Bidding Shipment for Vendor Freight Forwarder' => [['name' => 'Shipment Bidding Platform', 'desc' => "Platform bidding pengiriman:\n- Vendor registration\n- Bidding flow\n- Auto-award rules", 'labels' => ['Feature']]],
            'Invoice Process Automation' => [['name' => 'OCR Invoice Extraction', 'desc' => "Ekstraksi data invoice:\n- OCR processing\n- Template matching\n- Data validation", 'labels' => ['Feature', 'AI/ML']]],
            'Sales Reps Dashboard' => [['name' => 'Sales Performance Dashboard', 'desc' => "Dashboard performa sales:\n- KPI metrics\n- Territory mapping\n- Pipeline tracking", 'labels' => ['Feature', 'UI/UX']]],
            'Applicant Recruitment Agentic AI' => [['name' => 'AI Resume Screening Agent', 'desc' => "Agent AI screening:\n- Resume parsing\n- Skill matching\n- Scoring algorithm", 'labels' => ['Feature', 'AI/ML']]],
            'Bike & PAA Catalog Journey Revamp' => [['name' => 'Catalog UX Redesign', 'desc' => "Redesign katalog:\n- New navigation\n- Product comparison\n- Mobile responsive", 'labels' => ['Feature', 'UI/UX']], ['name' => 'PAA Integration', 'desc' => "Parts & Accessories:\n- Cross-sell\n- Compatibility checker\n- Bundle pricing", 'labels' => ['Feature', 'API']]],
            'AI E-Commerce Product Recommendation' => [['name' => 'Recommendation Engine', 'desc' => "Engine rekomendasi:\n- Collaborative filtering\n- Content-based filtering\n- A/B testing", 'labels' => ['Feature', 'AI/ML']]],
            'AI Up-selling, Cross-selling, Re-selling' => [['name' => 'Upsell/Cross-sell Engine', 'desc' => "Engine upsell:\n- Rule-based suggestions\n- ML predictions\n- Cart analysis", 'labels' => ['Feature', 'AI/ML']]],
            'B2C x POS x Marketplace - Realtime Stock Integration' => [['name' => 'Realtime Stock Sync', 'desc' => "Sync stok real-time:\n- Multi-channel inventory\n- Event-driven updates\n- Conflict resolution", 'labels' => ['Feature', 'API', 'Performance']]],
            'Bike Fitting Integration' => [['name' => 'Bike Fitting Calculator', 'desc' => "Kalkulator fitting:\n- Body measurement\n- Size recommendation\n- Frame geometry", 'labels' => ['Feature', 'UI/UX']]],
            'Bike Service Booking' => [['name' => 'Service Booking System', 'desc' => "Sistem booking servis:\n- Calendar management\n- Mechanic assignment\n- Reminder notification", 'labels' => ['Feature']]],
            'Click & Collect' => [['name' => 'Click & Collect Flow', 'desc' => "Alur click & collect:\n- Store availability\n- Pickup slot booking\n- QR code generation", 'labels' => ['Feature', 'UI/UX']]],
            'E-commerce Product Description Generation' => [['name' => 'AI Description Generator', 'desc' => "Generator deskripsi:\n- GPT integration\n- Template management\n- SEO optimization", 'labels' => ['Feature', 'AI/ML']]],
            'Membership - Referral Program & Remake' => [['name' => 'Referral System Revamp', 'desc' => "Revamp referral:\n- Reward tier system\n- Tracking dashboard\n- Fraud detection", 'labels' => ['Feature', 'Enhancement']]],
            'Net Promoter Score' => [['name' => 'NPS Survey System', 'desc' => "Sistem survey NPS:\n- Email/SMS trigger\n- Score calculation\n- Trend analysis", 'labels' => ['Feature', 'UI/UX']]],
            'Test Ride Booking' => [['name' => 'Test Ride Booking Platform', 'desc' => "Platform test ride:\n- Bike availability\n- Location selection\n- Post-ride feedback", 'labels' => ['Feature']]],
            'AI Customer Service' => [['name' => 'AI Chatbot Development', 'desc' => "Chatbot AI:\n- NLP training\n- Intent recognition\n- Escalation to human", 'labels' => ['Feature', 'AI/ML']]],
            'Catalog Search Correction & Suggestion' => [['name' => 'Search Autocorrect & Suggest', 'desc' => "Koreksi pencarian:\n- Typo correction\n- Did-you-mean\n- Synonym mapping", 'labels' => ['Feature', 'AI/ML', 'Performance']]],
            'Catalog Bike Recommendation based on Dealer' => [['name' => 'Dealer-Based Recommendation', 'desc' => "Rekomendasi dealer:\n- Dealer profiling\n- Regional preferences\n- Sales prediction", 'labels' => ['Feature', 'AI/ML']]],
        ];
    }
}
