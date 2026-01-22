<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Workspace;
use App\Services\FolderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function __construct(
        protected FolderService $folderService
    ) {}

    /**
     * Store a newly created folder.
     */
    public function store(StoreFolderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $parent = $request->parent_id ? Folder::find($request->parent_id) : null;

        $this->folderService->create(
            $request->validated(),
            $space,
            $request->user(),
            $parent
        );

        return back()->with('success', 'Folder created successfully.');
    }

    /**
     * Update the specified folder.
     */
    public function update(UpdateFolderRequest $request, Workspace $workspace, Space $space, Folder $folder): RedirectResponse
    {
        $this->folderService->update($folder, $request->validated(), $request->user());

        return back()->with('success', 'Folder updated successfully.');
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, Folder $folder): RedirectResponse
    {
        $this->folderService->delete($folder, $request->user());

        return back()->with('success', 'Folder deleted successfully.');
    }

    /**
     * Move folder to new parent.
     */
    public function move(Request $request, Workspace $workspace, Space $space, Folder $folder): RedirectResponse
    {
        $request->validate([
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $newParent = $request->parent_id ? Folder::find($request->parent_id) : null;

        $this->folderService->move($folder, $newParent, $request->user());

        return back()->with('success', 'Folder moved successfully.');
    }

    /**
     * Reorder folders.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $this->folderService->reorder($request->order);

        return back();
    }
}
