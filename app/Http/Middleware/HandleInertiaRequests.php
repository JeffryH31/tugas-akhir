<?php

namespace App\Http\Middleware;

use App\Models\TimeEntry;
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

            if ($activeWorkspaceId) {
                $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId);
            }

            if (!$activeWorkspace && $workspaces->isNotEmpty()) {
                $activeWorkspace = $workspaces->first();
                session(['active_workspace_id' => $activeWorkspace->id]);
            }

            if ($activeWorkspace) {
                $activeWorkspace->load([
                    'spaces' => function ($query) {
                        $query->with([
                            'folders.lists',
                            'listsWithoutFolder'
                        ])->orderBy('position');
                    },
                    'labels' => fn($q) => $q->orderBy('name'),
                ]);
            }
        }

        return [
            ...parent::share($request),
            'activeWorkspace' => $activeWorkspace,
            'workspaces' => $workspaces,
            'runningTimer' => fn () => $request->user() ? TimeEntry::where('user_id', $request->user()->id)
                ->where('is_running', true)
                ->with('subtask.task.taskList.space')
                ->first() : null,
        ];
    }
}
