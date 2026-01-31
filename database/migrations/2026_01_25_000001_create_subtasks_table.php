<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->string('subtask_id')->unique(); // Human-readable ID like "TASK-123-1"
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            
            // Dates - ONLY for subtasks
            $table->timestamp('start_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Time tracking - ONLY for subtasks
            $table->integer('time_estimate')->nullable(); // in minutes
            $table->integer('time_spent')->default(0); // in minutes (denormalized for performance)
            
            // Position for ordering
            $table->integer('position')->default(0);
            
            // Flags
            $table->boolean('is_archived')->default(false);
            
            // Metadata
            $table->json('custom_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'status_id', 'position']);
            $table->index(['sprint_id']);
            $table->index(['due_date', 'completed_at']);
            $table->index(['created_by']);
        });

        // Pivot table for subtask assignees
        Schema::create('subtask_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subtask_id', 'user_id']);
        });

        // Pivot table for subtask labels
        Schema::create('subtask_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subtask_id', 'label_id']);
        });

        // Subtask watchers
        Schema::create('subtask_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subtask_id', 'user_id']);
        });

        // Subtask dependencies
        Schema::create('subtask_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_subtask_id')->constrained('subtasks')->cascadeOnDelete();
            $table->enum('dependency_type', ['blocks', 'blocked_by', 'relates_to'])->default('blocks');
            $table->timestamps();

            $table->unique(['subtask_id', 'depends_on_subtask_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtask_dependencies');
        Schema::dropIfExists('subtask_watchers');
        Schema::dropIfExists('subtask_labels');
        Schema::dropIfExists('subtask_assignees');
        Schema::dropIfExists('subtasks');
    }
};
