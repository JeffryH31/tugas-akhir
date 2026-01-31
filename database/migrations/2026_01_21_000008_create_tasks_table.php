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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique(); // Human-readable ID like "PROJ-123"
            $table->foreignId('task_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('name');
            $table->text('description')->nullable();
            
            // Position for ordering
            $table->integer('position')->default(0);
            
            // Flags
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_template')->default(false);
            
            // Metadata
            $table->json('custom_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_list_id', 'status_id', 'position']);
            $table->index(['created_by']);
        });

        // Pivot table for task assignees
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Pivot table for task labels
        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'label_id']);
        });

        // Task watchers
        Schema::create('task_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Task dependencies
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type')->default('blocking'); // blocking, waiting_on
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
