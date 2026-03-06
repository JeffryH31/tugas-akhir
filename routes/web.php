<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CpmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\SubtaskController;
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

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/search', [TaskController::class, 'search'])->name('search');

    Route::get('/my-tasks', [TaskController::class, 'myTasks'])->name('my-tasks');

    Route::prefix('time-tracking')->group(function () {
        Route::get('/', [TimeEntryController::class, 'index'])->name('time-tracking.index');
        Route::get('/running', [TimeEntryController::class, 'runningTimer'])->name('time-tracking.running');
    });

    Route::prefix('time-entries')->group(function () {
        Route::patch('/{entry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
        Route::delete('/{entry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');
    });

    Route::prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('workspaces.store');

        Route::prefix('{workspace}')->group(function () {
            Route::get('/', [WorkspaceController::class, 'show'])->name('workspaces.show');
            Route::get('/settings', [WorkspaceController::class, 'settings'])->name('workspaces.settings');
            Route::patch('/', [WorkspaceController::class, 'update'])->name('workspaces.update');
            Route::delete('/', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
            Route::post('/switch', [DashboardController::class, 'switchWorkspace'])->name('workspaces.switch');

            Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

            Route::prefix('members')->group(function () {
                Route::post('/', [WorkspaceController::class, 'addMember'])->name('workspaces.members.add');
                Route::delete('/', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.remove');
                Route::patch('/role', [WorkspaceController::class, 'updateMemberRole'])->name('workspaces.members.role');
            });

            Route::get('/time-report', [TimeEntryController::class, 'workspaceReport'])->name('workspaces.time-report');

            Route::prefix('spaces')->group(function () {
                Route::post('/', [SpaceController::class, 'store'])->name('spaces.store');
                Route::post('/reorder', [SpaceController::class, 'reorder'])->name('spaces.reorder');

                Route::prefix('{space}')->group(function () {
                    Route::get('/', [SpaceController::class, 'show'])->name('spaces.show');
                    Route::patch('/', [SpaceController::class, 'update'])->name('spaces.update');
                    Route::delete('/', [SpaceController::class, 'destroy'])->name('spaces.destroy');
                    Route::post('/star', [SpaceController::class, 'toggleStar'])->name('spaces.star');
                    Route::post('/statuses', [SpaceController::class, 'addStatus'])->name('spaces.statuses.add');
                    Route::patch('/statuses/{status}', [SpaceController::class, 'updateStatus'])->name('spaces.statuses.update');
                    Route::delete('/statuses/{status}', [SpaceController::class, 'deleteStatus'])->name('spaces.statuses.delete');
                    Route::post('/statuses/reorder', [SpaceController::class, 'reorderStatuses'])->name('spaces.statuses.reorder');

                    Route::prefix('sprints')->group(function () {
                        Route::get('/', [SprintController::class, 'index'])->name('sprints.index');
                        Route::post('/', [SprintController::class, 'store'])->name('sprints.store');

                        Route::prefix('{sprint}')->group(function () {
                            Route::get('/', [SprintController::class, 'show'])->name('sprints.show');
                            Route::patch('/', [SprintController::class, 'update'])->name('sprints.update');
                            Route::delete('/', [SprintController::class, 'destroy'])->name('sprints.destroy');
                            Route::post('/start', [SprintController::class, 'start'])->name('sprints.start');
                            Route::post('/complete', [SprintController::class, 'complete'])->name('sprints.complete');
                            Route::post('/tasks', [SprintController::class, 'addTask'])->name('sprints.tasks.add');
                            Route::delete('/tasks', [SprintController::class, 'removeTask'])->name('sprints.tasks.remove');
                        });
                    });

                    Route::prefix('folders')->group(function () {
                        Route::post('/', [FolderController::class, 'store'])->name('folders.store');
                        Route::post('/reorder', [FolderController::class, 'reorder'])->name('folders.reorder');

                        Route::prefix('{folder}')->group(function () {
                            Route::patch('/', [FolderController::class, 'update'])->name('folders.update');
                            Route::delete('/', [FolderController::class, 'destroy'])->name('folders.destroy');
                            Route::post('/move', [FolderController::class, 'move'])->name('folders.move');
                        });
                    });

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

                            Route::prefix('tasks')->group(function () {
                                Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
                                Route::post('/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');

                                Route::prefix('{task}')->group(function () {
                                    Route::get('/', [TaskController::class, 'show'])->name('tasks.show');
                                    Route::patch('/', [TaskController::class, 'update'])->name('tasks.update');
                                    Route::delete('/', [TaskController::class, 'destroy'])->name('tasks.destroy');
                                    Route::patch('/status', [TaskController::class, 'changeStatus'])->name('tasks.change-status');
                                    Route::patch('/priority', [TaskController::class, 'changePriority'])->name('tasks.change-priority');
                                    Route::post('/assign', [TaskController::class, 'assign'])->name('tasks.assign');
                                    Route::delete('/assign', [TaskController::class, 'unassign'])->name('tasks.unassign');
                                    Route::post('/move', [TaskController::class, 'move'])->name('tasks.move');
                                    Route::post('/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');

                                    Route::prefix('labels')->group(function () {
                                        Route::post('/', [TaskController::class, 'addLabel'])->name('tasks.labels.add');
                                        Route::delete('/', [TaskController::class, 'removeLabel'])->name('tasks.labels.remove');
                                    });

                                    Route::prefix('subtasks')->group(function () {
                                        Route::post('/', [SubtaskController::class, 'store'])->name('tasks.subtasks.store');
                                        Route::post('/reorder', [SubtaskController::class, 'reorder'])->name('tasks.subtasks.reorder');

                                        Route::prefix('{subtask}')->group(function () {
                                            Route::patch('/', [SubtaskController::class, 'update'])->name('tasks.subtasks.update');
                                            Route::delete('/', [SubtaskController::class, 'destroy'])->name('tasks.subtasks.destroy');
                                            Route::post('/complete', [SubtaskController::class, 'complete'])->name('tasks.subtasks.complete');
                                            Route::post('/reopen', [SubtaskController::class, 'reopen'])->name('tasks.subtasks.reopen');
                                        });
                                    });

                                    Route::prefix('subtasks/{subtask}/time-entries')->group(function () {
                                        Route::post('/', [TimeEntryController::class, 'store'])->name('tasks.subtasks.time-entries.store');

                                        Route::prefix('{entry}')->group(function () {
                                            Route::delete('/', [TimeEntryController::class, 'destroy'])->name('tasks.subtasks.time-entries.destroy');
                                        });
                                    });

                                    Route::prefix('timer')->group(function () {
                                        Route::post('/start', [TimeEntryController::class, 'startTimer'])->name('tasks.timer.start');
                                        Route::post('/{entry}/stop', [TimeEntryController::class, 'stopTimer'])->name('tasks.timer.stop');
                                    });

                                    Route::prefix('comments')->group(function () {
                                        Route::post('/', [CommentController::class, 'store'])->name('tasks.comments.store');
                                    });

                                    Route::prefix('cpm')->group(function () {
                                        Route::get('/', [CpmController::class, 'analyze'])->name('tasks.cpm.analyze');
                                        Route::get('/gantt', [CpmController::class, 'gantt'])->name('tasks.cpm.gantt');
                                        Route::post('/dependencies', [CpmController::class, 'addDependency'])->name('tasks.cpm.dependencies.add');
                                        Route::delete('/dependencies', [CpmController::class, 'removeDependency'])->name('tasks.cpm.dependencies.remove');
                                    });
                                });
                            });
                        });
                    });
                });
            });
        });
    });

    Route::prefix('comments')->group(function () {
        Route::patch('/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/{comment}/resolve', [CommentController::class, 'resolve'])->name('comments.resolve');
        Route::post('/{comment}/unresolve', [CommentController::class, 'unresolve'])->name('comments.unresolve');
    });
});
