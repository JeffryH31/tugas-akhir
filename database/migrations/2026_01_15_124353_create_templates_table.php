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
     * Creates the templates table (Project Templates).
     * Templates for tasks, lists, folders, or spaces.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('type', ['task', 'list', 'folder', 'space'])->default('task');
            $table->json('template_data');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
