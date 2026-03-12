<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $workspace->labels()->create($validated);

        return redirect()->back()->with('success', 'Label created successfully.');
    }

    public function update(Request $request, Workspace $workspace, Label $label): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $label->update($validated);

        return redirect()->back()->with('success', 'Label updated successfully.');
    }

    public function destroy(Workspace $workspace, Label $label): RedirectResponse
    {
        $label->delete();

        return redirect()->back()->with('success', 'Label deleted successfully.');
    }
}
