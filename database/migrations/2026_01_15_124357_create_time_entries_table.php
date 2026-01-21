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
     * Creates the time_entries table for tracking manhours on tasks.
     * Supports both timer-based and manual time logging.
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('list_id')
                ->nullable()
                ->constrained('task_lists')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('hours', 8, 2)->default(0);
            $table->integer('duration_minutes')->default(0)->comment('Duration in minutes');
            $table->date('work_date')->nullable();
            $table->date('logged_date')->nullable()->comment('Date when time was logged');
            $table->text('description')->nullable();
            $table->boolean('is_timer_entry')->default(false)->comment('True if logged via timer');
            $table->timestamp('timer_started_at')->nullable();
            $table->timestamp('timer_stopped_at')->nullable();
            $table->timestamp('started_at')->nullable()->comment('When timer was started');
            $table->boolean('is_running')->default(false)->comment('True if timer is currently running');
            $table->boolean('is_billable')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'user_id']);
            $table->index(['work_date']);
            $table->index(['user_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
