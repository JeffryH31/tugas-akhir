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
            $table->tinyInteger('priority_level')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamp('start_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->integer('time_estimate')->nullable(); // in minutes
            $table->integer('time_spent')->default(0); // in minutes (denormalized for performance)

            $table->integer('position')->default(0);

            $table->boolean('is_archived')->default(false);

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

        Schema::create('subtask_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subtask_id', 'user_id']);
        });

        Schema::create('subtask_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subtask_id', 'label_id']);
        });

        Schema::create('subtask_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_subtask_id')->constrained('subtasks')->cascadeOnDelete();
            $table->enum('dependency_type', ['blocks', 'blocked_by', 'relates_to'])->default('blocks');
            $table->timestamps();

            $table->unique(['subtask_id', 'depends_on_subtask_id']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('subtask_id')->references('id')->on('subtasks')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtask_dependencies');
        Schema::dropIfExists('subtask_labels');
        Schema::dropIfExists('subtask_assignees');
        Schema::dropIfExists('subtasks');
    }
};
