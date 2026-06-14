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
     * Creates the task_lists table (Project List - previously features).
     * Lists are containers for tasks and can be inside a Folder or directly in a Space.
     */
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('space_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('folder_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('color', 7)->default('#6366F1');
            $table->integer('position')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('due_date')->nullable();
            $table->integer('priority')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['space_id', 'folder_id', 'position']);
            $table->index(['is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_lists');
    }
};
