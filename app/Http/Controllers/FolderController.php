<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Space;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FolderController
 *
 * Handles Folder management .
 */
class FolderController extends Controller
{
    private FolderService $folderService;

    public function __construct(FolderService $folderService)
    {
        $this->folderService = $folderService;
    }

    /**
     * Store a newly created folder.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        $space = Space::findOrFail($validated['space_id']);
        $this->authorize('update', $space);

        $this->folderService->create($space, $validated);

        return redirect()->back()
            ->with('success', 'Folder created successfully.');
    }

    /**
     * Update the specified folder.
     */
    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder->space);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:20',
            'hidden' => 'boolean',
        ]);

        $this->folderService->update($folder, $validated);

        return redirect()->back()
            ->with('success', 'Folder updated successfully.');
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder->space);

        $moveLists = $request->boolean('move_lists', true);
        $this->folderService->delete($folder, $moveLists);

        return redirect()->back()
            ->with('success', 'Folder deleted successfully.');
    }

    /**
     * Toggle folder visibility.
     */
    public function toggleHidden(Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder->space);

        $this->folderService->toggleHidden($folder);

        return redirect()->back()
            ->with('success', 'Folder visibility updated.');
    }

    /**
     * Move folder to different space.
     */
    public function move(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder->space);

        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
        ]);

        $newSpace = Space::findOrFail($validated['space_id']);
        $this->authorize('update', $newSpace);

        $this->folderService->move($folder, $newSpace);

        return redirect()->back()
            ->with('success', 'Folder moved successfully.');
    }

    /**
     * Reorder folders within a space.
     */
    public function reorder(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $validated = $request->validate([
            'folder_ids' => 'required|array',
            'folder_ids.*' => 'exists:folders,id',
        ]);

        $this->folderService->reorder($space, $validated['folder_ids']);

        return redirect()->back()
            ->with('success', 'Folder order updated.');
    }
}
