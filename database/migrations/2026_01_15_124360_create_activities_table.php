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
     * Creates the activities table for tracking all user actions.
     * Provides audit trail and activity feed functionality.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 50)->comment('time_logged, task_completed, task_moved, etc.');
            $table->nullableMorphs('subject'); // subject_type, subject_id (polymorphic)
            $table->json('properties')->nullable()->comment('Additional activity data');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
