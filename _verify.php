<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Task;
use App\Models\Subtask;
use App\Models\Project;

$total = Task::count();
$done = Task::whereHas('status', fn($q) => $q->where('name', 'Done'))->count();
$tasksOverdue = Task::whereHas('subtasks', fn($q) => $q->whereNull('completed_at')->whereNotNull('due_date')->where('due_date', '<', now()))->count();

$out = "Projects: " . Project::count() . "\n";
$out .= "Total tasks: {$total}\n";
$out .= "Done tasks: {$done}\n";
$out .= "Tasks overdue: {$tasksOverdue} (" . round($tasksOverdue / $total * 100) . "%)\n";
$out .= "Total subtasks: " . Subtask::count() . "\n";

$counts = Project::withCount('tasks')->pluck('tasks_count');
$out .= "Tasks per project: min=" . $counts->min() . " max=" . $counts->max() . " avg=" . round($counts->avg(), 1) . "\n";

file_put_contents(__DIR__ . '/_result.txt', $out);
