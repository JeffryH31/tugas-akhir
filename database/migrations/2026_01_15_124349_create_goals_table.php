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
     * Creates goals and goal_targets tables (Project Goals/OKRs).
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('status', ['on_track', 'at_risk', 'off_track', 'completed'])->default('on_track');
            $table->integer('progress')->default(0);
            $table->string('color', 7)->default('#6366F1');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
            $table->index(['owner_id']);
        });

        Schema::create('goal_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('goal_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('type', ['number', 'currency', 'boolean', 'task_completion'])->default('number');
            $table->decimal('target_value', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->foreignId('linked_list_id')
                ->nullable()
                ->constrained('task_lists')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['goal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_targets');
        Schema::dropIfExists('goals');
    }
};
