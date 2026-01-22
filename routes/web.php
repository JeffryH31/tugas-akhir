<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Search
    Route::get('/search', [TaskController::class, 'search'])->name('search');

    // My Tasks
    Route::get('/my-tasks', [TaskController::class, 'myTasks'])->name('my-tasks');

    // Time Tracking
    Route::prefix('time-tracking')->group(function () {
        Route::get('/', [TimeEntryController::class, 'index'])->name('time-tracking.index');
        Route::get('/running', [TimeEntryController::class, 'runningTimer'])->name('time-tracking.running');
    });

    // Time Entries
    Route::prefix('time-entries')->group(function () {
        Route::patch('/{entry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
        Route::delete('/{entry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');
    });

    // Workspaces
    Route::prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('workspaces.store');

        Route::prefix('{workspace}')->group(function () {
            Route::get('/', [WorkspaceController::class, 'show'])->name('workspaces.show');
            Route::get('/settings', [WorkspaceController::class, 'settings'])->name('workspaces.settings');
            Route::patch('/', [WorkspaceController::class, 'update'])->name('workspaces.update');
            Route::delete('/', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
            Route::post('/switch', [DashboardController::class, 'switchWorkspace'])->name('workspaces.switch');

            // Workspace Members
            Route::prefix('members')->group(function () {
                Route::post('/', [WorkspaceController::class, 'addMember'])->name('workspaces.members.add');
                Route::delete('/', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.remove');
                Route::patch('/role', [WorkspaceController::class, 'updateMemberRole'])->name('workspaces.members.role');
            });

            // Workspace Time Report
            Route::get('/time-report', [TimeEntryController::class, 'workspaceReport'])->name('workspaces.time-report');

            // Spaces
            Route::prefix('spaces')->group(function () {
                Route::post('/', [SpaceController::class, 'store'])->name('spaces.store');
                Route::post('/reorder', [SpaceController::class, 'reorder'])->name('spaces.reorder');

                Route::prefix('{space}')->group(function () {
                    Route::get('/', [SpaceController::class, 'show'])->name('spaces.show');
                    Route::patch('/', [SpaceController::class, 'update'])->name('spaces.update');
                    Route::delete('/', [SpaceController::class, 'destroy'])->name('spaces.destroy');
                    Route::post('/star', [SpaceController::class, 'toggleStar'])->name('spaces.star');
                    Route::post('/statuses', [SpaceController::class, 'addStatus'])->name('spaces.statuses.add');
                    Route::post('/statuses/reorder', [SpaceController::class, 'reorderStatuses'])->name('spaces.statuses.reorder');

                    // Folders
                    Route::prefix('folders')->group(function () {
                        Route::post('/', [FolderController::class, 'store'])->name('folders.store');
                        Route::post('/reorder', [FolderController::class, 'reorder'])->name('folders.reorder');

                        Route::prefix('{folder}')->group(function () {
                            Route::patch('/', [FolderController::class, 'update'])->name('folders.update');
                            Route::delete('/', [FolderController::class, 'destroy'])->name('folders.destroy');
                            Route::post('/move', [FolderController::class, 'move'])->name('folders.move');
                        });
                    });

                    // Lists
                    Route::prefix('lists')->group(function () {
                        Route::post('/', [TaskListController::class, 'store'])->name('lists.store');
                        Route::post('/reorder', [TaskListController::class, 'reorder'])->name('lists.reorder');

                        Route::prefix('{list}')->group(function () {
                            Route::get('/', [TaskListController::class, 'show'])->name('lists.show');
                            Route::patch('/', [TaskListController::class, 'update'])->name('lists.update');
                            Route::delete('/', [TaskListController::class, 'destroy'])->name('lists.destroy');
                            Route::post('/archive', [TaskListController::class, 'archive'])->name('lists.archive');
                            Route::post('/unarchive', [TaskListController::class, 'unarchive'])->name('lists.unarchive');
                            Route::post('/move-to-folder', [TaskListController::class, 'moveToFolder'])->name('lists.move-to-folder');
                            Route::post('/duplicate', [TaskListController::class, 'duplicate'])->name('lists.duplicate');

                            // Tasks
                            Route::prefix('tasks')->group(function () {
                                Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
                                Route::post('/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');

                                Route::prefix('{task}')->group(function () {
                                    Route::get('/', [TaskController::class, 'show'])->name('tasks.show');
                                    Route::patch('/', [TaskController::class, 'update'])->name('tasks.update');
                                    Route::delete('/', [TaskController::class, 'destroy'])->name('tasks.destroy');
                                    Route::post('/complete', [TaskController::class, 'complete'])->name('tasks.complete');
                                    Route::post('/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
                                    Route::patch('/status', [TaskController::class, 'changeStatus'])->name('tasks.change-status');
                                    Route::patch('/priority', [TaskController::class, 'changePriority'])->name('tasks.change-priority');
                                    Route::post('/assign', [TaskController::class, 'assign'])->name('tasks.assign');
                                    Route::delete('/assign', [TaskController::class, 'unassign'])->name('tasks.unassign');
                                    Route::post('/move', [TaskController::class, 'move'])->name('tasks.move');
                                    Route::post('/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');

                                    // Labels
                                    Route::prefix('labels')->group(function () {
                                        Route::post('/', [TaskController::class, 'addLabel'])->name('tasks.labels.add');
                                        Route::delete('/', [TaskController::class, 'removeLabel'])->name('tasks.labels.remove');
                                    });

                                    // Time Entries
                                    Route::prefix('time-entries')->group(function () {
                                        Route::post('/', [TimeEntryController::class, 'store'])->name('tasks.time-entries.store');
                                    });

                                    // Timer
                                    Route::prefix('timer')->group(function () {
                                        Route::post('/start', [TimeEntryController::class, 'startTimer'])->name('tasks.timer.start');
                                        Route::post('/{entry}/stop', [TimeEntryController::class, 'stopTimer'])->name('tasks.timer.stop');
                                    });

                                    // Comments
                                    Route::prefix('comments')->group(function () {
                                        Route::post('/', [CommentController::class, 'store'])->name('tasks.comments.store');
                                    });
                                });
                            });
                        });
                    });
                });
            });
        });
    });

    // Global Comment Routes
    Route::prefix('comments')->group(function () {
        Route::patch('/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/{comment}/react', [CommentController::class, 'toggleReaction'])->name('comments.react');
        Route::post('/{comment}/resolve', [CommentController::class, 'resolve'])->name('comments.resolve');
        Route::post('/{comment}/unresolve', [CommentController::class, 'unresolve'])->name('comments.unresolve');
    });
});
