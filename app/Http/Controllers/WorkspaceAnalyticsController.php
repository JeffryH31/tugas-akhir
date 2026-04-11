<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\TimeEntry;
use App\Services\AccessService;
use App\Services\WorkspaceAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceAnalyticsController extends Controller
{
    public function __construct(
        protected WorkspaceAnalyticsService $analyticsService,
        protected AccessService $accessService,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $canManage = $this->accessService->canManageWorkspace($request->user(), $workspace);

        $spaces = $workspace->spaces()->select('id', 'name')->get();

        // Build map: user_id → [space_ids] based on explicit space membership
        $spaceMemberMap = [];
        if ($canManage) {
            $rows = DB::table('space_members')
                ->whereIn('space_id', $spaces->pluck('id'))
                ->select('space_id', 'user_id')
                ->get();

            foreach ($rows as $row) {
                $spaceMemberMap[$row->user_id][] = $row->space_id;
            }
        }

        $members = $canManage
            ? $workspace->members->map(function ($m) use ($spaceMemberMap) {
                $running = TimeEntry::where('user_id', $m->id)
                    ->where('is_running', true)
                    ->with('subtask.task.taskList.space')
                    ->first();

                $runningOn = null;
                if ($running && $running->subtask) {
                    $runningOn = [
                        'subtask' => $running->subtask->name,
                        'task'    => $running->subtask->task?->name,
                        'space'   => $running->subtask->task?->taskList?->space?->name,
                    ];
                }

                return [
                    'id'                => $m->id,
                    'name'              => $m->name,
                    'email'             => $m->email,
                    'initials'          => $m->initials,
                    'avatar_color'      => $m->avatar_color,
                    'profile_photo_url' => $m->profile_photo_url,
                    'hourly_rate'       => $m->hourly_rate,
                    'role'              => $m->pivot?->role,
                    'space_ids'         => $spaceMemberMap[$m->id] ?? [],
                    'running_on'        => $runningOn,
                ];
            })->values()
            : collect();

        return Inertia::render('Workspaces/Analytics', [
            'workspace'  => $workspace,
            'analytics'  => $this->analyticsService->getOverview(
                $workspace,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
            ),
            'filters'    => $validated,
            'members'    => $members,
            'spaces'     => $spaces->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'canManage'  => $canManage,
        ]);
    }

    public function export(Request $request, Workspace $workspace)
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $rows = $this->analyticsService->getCsvRows(
            $workspace,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        );

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['metric', 'value']);
            foreach ($rows as $row) {
                fputcsv($output, [$row['metric'], $row['value']]);
            }
            fclose($output);
        }, 'workspace-analytics.csv');
    }
}
