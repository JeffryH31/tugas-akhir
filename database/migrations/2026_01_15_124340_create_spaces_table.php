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
     * Creates the spaces table (Project Space - previously boards).
     * Spaces are the main organizational units within a workspace.
     */
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('color', 7)->default('#6366F1');
            $table->string('avatar')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->json('features')->nullable()->comment('Enabled features: time_tracking, tags, priorities, custom_fields, multiple_assignees');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
