<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the views table (Project Custom Views).
     * Views define how tasks are displayed: List, Board, Calendar, Gantt, etc.
     */
    public function up(): void
    {
        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('space_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('folder_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('list_id')
                ->nullable()
                ->constrained('task_lists')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('type', ['list', 'board', 'calendar', 'gantt', 'timeline', 'table', 'mindmap', 'workload'])->default('list');
            $table->json('filters')->nullable();
            $table->json('sorts')->nullable();
            $table->json('grouping')->nullable();
            $table->json('columns')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['space_id', 'type']);
            $table->index(['list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
