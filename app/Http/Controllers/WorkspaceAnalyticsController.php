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

        return Inertia::render('Workspaces/Analytics', [
            'workspace' => $workspace,
            'analytics' => $this->analyticsService->getOverview(
                $workspace,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
            ),
            'filters' => $validated,
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
            fputcsv($output, ['space', 'tasks_total', 'tasks_archived']);
            foreach ($rows as $row) {
                fputcsv($output, [$row['space'], $row['tasks_total'], $row['tasks_archived']]);
            }
            fclose($output);
        }, 'workspace-analytics.csv');
    }
}
