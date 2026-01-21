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
     * Creates checklists and checklist_items tables.
     * Checklists are nested todo lists within a task.
     */
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('task_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('checklist_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['checklist_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
    }
};
