<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\MemberReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMemberReportController extends Controller
{
    public function __construct(
        protected MemberReportService $reportService,
        protected AccessService $accessService,
    ) {}

    public function show(Request $request, Workspace $workspace, User $member): Response
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        abort_unless($workspace->isMember($member), 404);

        $canManage = $this->accessService->canManageWorkspace($request->user(), $workspace);
        $memberWithPivot = $workspace->members()->where('user_id', $member->id)->first();

        $memberData = [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'initials' => $member->initials,
            'avatar_color' => $member->avatar_color,
            'profile_photo_url' => $member->profile_photo_url,
            'role' => $memberWithPivot?->pivot?->role,
        ];

        // Only admin/owner can see hourly_rate
        if ($canManage) {
            $memberData['hourly_rate'] = $member->hourly_rate;
        }

        return Inertia::render('Workspaces/MemberReport', [
            'workspace' => $workspace,
            'member' => $memberData,
            'report' => $this->reportService->getReport($workspace, $member),
            'canManage' => $canManage,
        ]);
    }
}
