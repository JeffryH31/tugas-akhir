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
     * Creates pivot table for list assignees (default assignees for new tasks).
     */
    public function up(): void
    {
        Schema::create('list_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')
                ->constrained('task_lists')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['list_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_user');
    }
};
