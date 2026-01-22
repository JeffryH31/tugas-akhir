<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $activeWorkspaceId = session('active_workspace_id');
        $activeWorkspace = null;
        $workspaces = collect([]);

        if ($request->user()) {
            $workspaces = $request->user()
                ->workspaces()
                ->orderBy('created_at', 'desc')
                ->get();

            // Get active workspace
            if ($activeWorkspaceId) {
                $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId);
            }

            // If no active workspace, use the first one
            if (!$activeWorkspace && $workspaces->isNotEmpty()) {
                $activeWorkspace = $workspaces->first();
                session(['active_workspace_id' => $activeWorkspace->id]);
            }

            // Load spaces for active workspace
            if ($activeWorkspace) {
                $activeWorkspace->load([
                    'spaces' => function ($query) {
                        $query->with([
                            'folders.lists',
                            'listsWithoutFolder'
                        ])->orderBy('position');
                    }
                ]);
            }
        }

        return [
            ...parent::share($request),
            'activeWorkspace' => $activeWorkspace,
            'workspaces' => $workspaces,
        ];
    }
}
