<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TaskList;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ListService
 *
 * Handles List-related business logic .
 */
class ListService
{
    /**
     * Create a new list within a space or folder.
     */
    public function create(array $data, User $creator): TaskList
    {
        return DB::transaction(function () use ($data, $creator) {
            $spaceId = $data['space_id'];
            $folderId = $data['folder_id'] ?? null;

            // Determine max position
            $query = TaskList::where('space_id', $spaceId);
            if ($folderId) {
                $query->where('folder_id', $folderId);
            } else {
                $query->whereNull('folder_id');
            }
            $maxPosition = $query->max('position') ?? -1;

            $list = TaskList::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'space_id' => $spaceId,
                'folder_id' => $folderId,
                'created_by' => $creator->id,
                'color' => $data['color'] ?? '#6366F1',
                'position' => $maxPosition + 1,
                'due_date' => $data['due_date'] ?? null,
                'priority' => $data['priority'] ?? null,
            ]);

            // Create default statuses
            $this->createDefaultStatuses($list);

            return $list;
        });
    }

    /**
     * Create default statuses for a list.
     */
    public function createDefaultStatuses(TaskList $list): void
    {
        foreach (Status::DEFAULT_STATUSES as $statusData) {
            $list->statuses()->create($statusData);
        }
    }

    /**
     * Update a list.
     */
    public function update(TaskList $list, array $data): TaskList
    {
        $list->update([
            'name' => $data['name'] ?? $list->name,
            'description' => $data['description'] ?? $list->description,
            'color' => $data['color'] ?? $list->color,
            'due_date' => $data['due_date'] ?? $list->due_date,
            'priority' => $data['priority'] ?? $list->priority,
        ]);

        return $list->fresh();
    }

    /**
     * Delete (soft delete) a list.
     */
    public function delete(TaskList $list): bool
    {
        return $list->delete();
    }

    /**
     * Archive a list.
     */
    public function archive(TaskList $list): TaskList
    {
        $list->update(['is_archived' => true]);
        return $list->fresh();
    }

    /**
     * Unarchive a list.
     */
    public function unarchive(TaskList $list): TaskList
    {
        $list->update(['is_archived' => false]);
        return $list->fresh();
    }

    /**
     * Move a list to a different folder or space.
     */
    public function move(TaskList $list, ?Folder $folder = null, ?Space $space = null): TaskList
    {
        return DB::transaction(function () use ($list, $folder, $space) {
            $updates = [];

            if ($folder) {
                $updates['folder_id'] = $folder->id;
                $updates['space_id'] = $folder->space_id;
            } elseif ($space) {
                $updates['folder_id'] = null;
                $updates['space_id'] = $space->id;
            }

            if (!empty($updates)) {
                // Get new position
                $query = TaskList::where('space_id', $updates['space_id'] ?? $list->space_id);
                if (isset($updates['folder_id'])) {
                    $query->where('folder_id', $updates['folder_id']);
                } else {
                    $query->whereNull('folder_id');
                }
                $updates['position'] = ($query->max('position') ?? -1) + 1;

                $list->update($updates);
            }

            return $list->fresh();
        });
    }

    /**
     * Reorder lists within a folder or space.
     */
    public function reorder(array $listIds): void
    {
        DB::transaction(function () use ($listIds) {
            foreach ($listIds as $position => $listId) {
                TaskList::where('id', $listId)->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a list with all its content.
     */
    public function duplicate(TaskList $list, string $newName = null): TaskList
    {
        return DB::transaction(function () use ($list, $newName) {
            $newList = $list->replicate();
            $newList->name = $newName ?? $list->name . ' (Copy)';
            $newList->position = TaskList::where('space_id', $list->space_id)
                ->where('folder_id', $list->folder_id)
                ->max('position') + 1;
            $newList->save();

            // Copy statuses
            foreach ($list->statuses as $status) {
                $newStatus = $status->replicate();
                $newStatus->list_id = $newList->id;
                $newStatus->save();
            }

            // Tasks copying would go here (simplified)

            return $newList;
        });
    }

    /**
     * Add a custom status to a list.
     */
    public function addStatus(TaskList $list, array $data): Status
    {
        $maxPosition = $list->statuses()->max('position') ?? -1;

        return $list->statuses()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6B7280',
            'type' => $data['type'] ?? 'open',
            'position' => $maxPosition + 1,
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    /**
     * Reorder statuses within a list.
     */
    public function reorderStatuses(TaskList $list, array $statusIds): void
    {
        DB::transaction(function () use ($list, $statusIds) {
            foreach ($statusIds as $position => $statusId) {
                $list->statuses()->where('id', $statusId)->update(['position' => $position]);
            }
        });
    }

    /**
     * Update statuses for a list (create, update, delete).
     */
    public function updateStatuses(TaskList $list, array $statuses): void
    {
        DB::transaction(function () use ($list, $statuses) {
            $existingStatusIds = $list->statuses()->pluck('id')->toArray();
            $newStatusIds = [];

            foreach ($statuses as $statusData) {
                if (!empty($statusData['id'])) {
                    // Update existing status
                    $list->statuses()
                        ->where('id', $statusData['id'])
                        ->update([
                            'name' => $statusData['name'],
                            'color' => $statusData['color'],
                            'type' => $statusData['type'],
                            'position' => $statusData['position'],
                        ]);
                    $newStatusIds[] = $statusData['id'];
                } else {
                    // Create new status
                    $status = $list->statuses()->create([
                        'name' => $statusData['name'],
                        'color' => $statusData['color'],
                        'type' => $statusData['type'],
                        'position' => $statusData['position'],
                    ]);
                    $newStatusIds[] = $status->id;
                }
            }

            // Delete statuses that were not in the update
            $statusesToDelete = array_diff($existingStatusIds, $newStatusIds);
            if (!empty($statusesToDelete)) {
                $list->statuses()->whereIn('id', $statusesToDelete)->delete();
            }
        });
    }
}
