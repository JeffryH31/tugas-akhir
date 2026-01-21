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
     * Creates the tasks table (Project Task).
     * Tasks are the main work items. Supports subtasks via parent_id.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->comment('For subtasks - references parent task');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('list_id')
                ->constrained('task_lists')
                ->cascadeOnDelete();
            $table->foreignId('status_id')
                ->nullable()
                ->constrained('statuses')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('position')->default(0);
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->json('custom_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index(['list_id', 'position']);
            $table->index(['parent_id']);
            $table->index(['status_id']);
            $table->index(['priority']);
            $table->index(['due_date']);
            $table->index(['is_completed']);
        });

        // Task Assignees (many-to-many)
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Task Watchers
        Schema::create('task_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Task Labels
        Schema::create('task_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'label_id']);
        });

        // Task Dependencies
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_id')->constrained('tasks')->cascadeOnDelete();
            $table->enum('type', ['waiting_on', 'blocking'])->default('waiting_on');
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_label');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
