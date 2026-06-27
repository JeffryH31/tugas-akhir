<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\WorkspaceAnalyticsService;
use Illuminate\Http\Request;
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

        $membersPayload = $this->analyticsService->getMembersPayload($workspace);

        // Non-admin members should not see hourly_rate
        if (! $canManage) {
            $membersPayload['members'] = $membersPayload['members']->map(function ($member) {
                unset($member['hourly_rate']);
                return $member;
            });
        }

        return Inertia::render('Workspaces/Analytics', [
            'workspace' => $workspace,
            'analytics' => $this->analyticsService->getOverview(
                $workspace,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
            ),
            'filters' => $validated,
            'members' => $membersPayload['members'],
            'spaces' => $membersPayload['spaces'],
            'canManage' => $canManage,
        ]);
    }

}
