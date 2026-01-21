<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated Users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // ==========================================
    // Dashboard
    // ==========================================
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/spaces/{space}', [DashboardController::class, 'space'])->name('spaces.show');

    // ==========================================
    // Workspaces
    // ==========================================
    Route::prefix('workspaces')->name('workspaces.')->group(function () {
        Route::post('/', [WorkspaceController::class, 'store'])->name('store');
        Route::put('/{workspace}', [WorkspaceController::class, 'update'])->name('update');
        Route::delete('/{workspace}', [WorkspaceController::class, 'destroy'])->name('destroy');

        // Members
        Route::post('/{workspace}/members', [WorkspaceController::class, 'addMember'])->name('members.add');
        Route::delete('/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('members.remove');
        Route::put('/{workspace}/members/{user}/role', [WorkspaceController::class, 'updateMemberRole'])->name('members.role');
    });

    // ==========================================
    // Spaces (Project)
    // ==========================================
    Route::prefix('spaces')->name('spaces.')->group(function () {
        Route::post('/', [SpaceController::class, 'store'])->name('store');
        Route::put('/{space}', [SpaceController::class, 'update'])->name('update');
        Route::delete('/{space}', [SpaceController::class, 'destroy'])->name('destroy');
        Route::post('/{space}/star', [SpaceController::class, 'toggleStar'])->name('star');

        // Members
        Route::post('/{space}/members', [SpaceController::class, 'addMember'])->name('members.add');
        Route::delete('/{space}/members/{user}', [SpaceController::class, 'removeMember'])->name('members.remove');
    });

    // ==========================================
    // Folders (Project)
    // ==========================================
    Route::prefix('folders')->name('folders.')->group(function () {
        Route::post('/', [FolderController::class, 'store'])->name('store');
        Route::put('/{folder}', [FolderController::class, 'update'])->name('update');
        Route::delete('/{folder}', [FolderController::class, 'destroy'])->name('destroy');
        Route::post('/{folder}/toggle-hidden', [FolderController::class, 'toggleHidden'])->name('toggle-hidden');
        Route::post('/{folder}/move', [FolderController::class, 'move'])->name('move');
    });
    Route::post('/spaces/{space}/folders/reorder', [FolderController::class, 'reorder'])->name('spaces.folders.reorder');

    // ==========================================
    // Lists (Project)
    // ==========================================
    Route::prefix('lists')->name('lists.')->group(function () {
        Route::get('/{list}', [ListController::class, 'show'])->name('show');
        Route::post('/', [ListController::class, 'store'])->name('store');
        Route::put('/{list}', [ListController::class, 'update'])->name('update');
        Route::delete('/{list}', [ListController::class, 'destroy'])->name('destroy');
        Route::post('/{list}/archive', [ListController::class, 'archive'])->name('archive');
        Route::post('/{list}/move', [ListController::class, 'move'])->name('move');
        Route::post('/reorder', [ListController::class, 'reorder'])->name('reorder');
        Route::put('/{list}/statuses', [ListController::class, 'updateStatuses'])->name('statuses');
    });

    // ==========================================
    // Tasks (Project)
    // ==========================================
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::post('/', [TaskController::class, 'store'])->name('store');
        Route::get('/{task}', [TaskController::class, 'show'])->name('show');
        Route::put('/{task}', [TaskController::class, 'update'])->name('update');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
        Route::post('/{task}/status', [TaskController::class, 'updateStatus'])->name('status');
        Route::post('/{task}/move', [TaskController::class, 'move'])->name('move');
        Route::post('/{task}/assignees', [TaskController::class, 'syncAssignees'])->name('assignees');
        Route::post('/{task}/labels', [TaskController::class, 'syncLabels'])->name('labels');
        Route::post('/reorder', [TaskController::class, 'reorder'])->name('reorder');

        // Subtasks
        Route::post('/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('subtasks.store');
    });

    // ==========================================
    // Time Tracking
    // ==========================================
    Route::prefix('time-tracking')->name('time-tracking.')->group(function () {
        // Timer actions
        Route::post('/tasks/{task}/start', [TimeTrackingController::class, 'startTimer'])->name('start');
        Route::post('/entries/{timeEntry}/stop', [TimeTrackingController::class, 'stopTimer'])->name('stop');
        Route::post('/tasks/{task}/pause', [TimeTrackingController::class, 'pauseTimer'])->name('pause');
        Route::post('/tasks/{task}/resume', [TimeTrackingController::class, 'resumeTimer'])->name('resume');
        Route::post('/tasks/{task}/complete', [TimeTrackingController::class, 'completeTask'])->name('complete');
        Route::post('/stop-all', [TimeTrackingController::class, 'stopAllTimers'])->name('stop-all');

        // Manual time logging
        Route::post('/log', [TimeTrackingController::class, 'logManualTime'])->name('log');

        // Time entry management
        Route::put('/entries/{timeEntry}', [TimeTrackingController::class, 'update'])->name('update');
        Route::delete('/entries/{timeEntry}', [TimeTrackingController::class, 'destroy'])->name('destroy');
    });
});
