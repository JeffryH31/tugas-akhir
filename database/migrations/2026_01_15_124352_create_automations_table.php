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
     * Creates the automations table (Project Automations).
     * Automations define triggers and actions for task automation.
     */
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('space_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('folder_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('list_id')
                ->nullable()
                ->constrained('task_lists')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->json('trigger')->comment('Trigger conditions');
            $table->json('actions')->comment('Actions to perform');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['space_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
