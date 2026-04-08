<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Workspace;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    public function __construct(
        protected AccessService $accessService,
    ) {}

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('labels', 'name')->where(fn($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $workspace->labels()->create([
            'name' => trim($validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->back()->with('success', 'Label created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Label $label): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        abort_unless((int) $label->workspace_id === (int) $workspace->id, 404);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('labels', 'name')
                    ->where(fn($query) => $query->where('workspace_id', $workspace->id))
                    ->ignore($label->id),
            ],
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label->update([
            'name' => trim($validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->back()->with('success', 'Label updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, Label $label): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        abort_unless((int) $label->workspace_id === (int) $workspace->id, 404);

        $label->delete();

        return redirect()->back()->with('success', 'Label deleted successfully.');
    }
}
