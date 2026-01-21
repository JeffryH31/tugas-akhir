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
     * Creates the statuses table (Project Custom Statuses - previously task_lists).
     * Each List has its own set of statuses (e.g., To Do, In Progress, Done).
     */
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6B7280');
            $table->foreignId('list_id')
                ->constrained('task_lists')
                ->cascadeOnDelete();
            $table->enum('type', ['open', 'in_progress', 'closed'])->default('open');
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['list_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
