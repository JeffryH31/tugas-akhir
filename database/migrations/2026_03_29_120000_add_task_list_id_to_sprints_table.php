<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->foreignId('task_list_id')
                ->nullable()
                ->after('space_id')
                ->constrained('task_lists')
                ->nullOnDelete();
        });

        // Backfill from assigned subtasks first, fallback to first product in the same space.
        $sprints = DB::table('sprints')->select('id', 'space_id')->get();

        foreach ($sprints as $sprint) {
            $listId = DB::table('subtasks')
                ->join('tasks', 'tasks.id', '=', 'subtasks.task_id')
                ->where('subtasks.sprint_id', $sprint->id)
                ->value('tasks.task_list_id');

            if (!$listId) {
                $listId = DB::table('task_lists')
                    ->where('space_id', $sprint->space_id)
                    ->orderBy('position')
                    ->value('id');
            }

            if ($listId) {
                DB::table('sprints')
                    ->where('id', $sprint->id)
                    ->update(['task_list_id' => $listId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_list_id');
        });
    }
};
