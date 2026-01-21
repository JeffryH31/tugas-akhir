<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Folder;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * FolderService
 *
 * Handles Folder-related business logic .
 */
class FolderService
{
    /**
     * Create a new folder within a space.
     */
    public function create(Space $space, array $data): Folder
    {
        return DB::transaction(function () use ($space, $data) {
            $maxPosition = $space->folders()->max('position') ?? -1;

            return Folder::create([
                'name' => $data['name'],
                'space_id' => $space->id,
                'position' => $maxPosition + 1,
                'color' => $data['color'] ?? null,
                'hidden' => $data['hidden'] ?? false,
            ]);
        });
    }

    /**
     * Update a folder.
     */
    public function update(Folder $folder, array $data): Folder
    {
        $folder->update([
            'name' => $data['name'] ?? $folder->name,
            'color' => $data['color'] ?? $folder->color,
            'hidden' => $data['hidden'] ?? $folder->hidden,
        ]);

        return $folder->fresh();
    }

    /**
     * Delete (soft delete) a folder.
     * Lists inside will be moved to space root.
     */
    public function delete(Folder $folder, bool $moveLists = true): bool
    {
        return DB::transaction(function () use ($folder, $moveLists) {
            if ($moveLists) {
                // Move all lists to space root
                $folder->lists()->update(['folder_id' => null]);
            }

            return $folder->delete();
        });
    }

    /**
     * Toggle folder visibility (hidden).
     */
    public function toggleHidden(Folder $folder): Folder
    {
        $folder->update(['hidden' => !$folder->hidden]);
        return $folder->fresh();
    }

    /**
     * Move a folder to a different space.
     */
    public function move(Folder $folder, Space $newSpace): Folder
    {
        return DB::transaction(function () use ($folder, $newSpace) {
            $maxPosition = $newSpace->folders()->max('position') ?? -1;

            $folder->update([
                'space_id' => $newSpace->id,
                'position' => $maxPosition + 1,
            ]);

            // Update all lists inside the folder
            $folder->lists()->update(['space_id' => $newSpace->id]);

            return $folder->fresh();
        });
    }

    /**
     * Reorder folders within a space.
     */
    public function reorder(Space $space, array $folderIds): void
    {
        DB::transaction(function () use ($space, $folderIds) {
            foreach ($folderIds as $position => $folderId) {
                $space->folders()
                    ->where('id', $folderId)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a folder with all its content.
     */
    public function duplicate(Folder $folder, string $newName = null): Folder
    {
        return DB::transaction(function () use ($folder, $newName) {
            $newFolder = $folder->replicate();
            $newFolder->name = $newName ?? $folder->name . ' (Copy)';
            $newFolder->position = $folder->space->folders()->max('position') + 1;
            $newFolder->save();

            // Copy lists (simplified - would need ListService for full copy)
            foreach ($folder->lists as $list) {
                $newList = $list->replicate();
                $newList->folder_id = $newFolder->id;
                $newList->save();
            }

            return $newFolder;
        });
    }
}
