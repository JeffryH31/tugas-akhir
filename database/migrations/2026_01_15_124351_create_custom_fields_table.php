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
     * Creates custom_fields and task_custom_field_values tables.
     * Custom fields allow adding custom data types to tasks.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('space_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('type', [
                'text', 'number', 'dropdown', 'date', 'checkbox',
                'email', 'phone', 'url', 'currency', 'emoji',
                'people', 'files', 'formula', 'relationship'
            ])->default('text');
            $table->json('options')->nullable()->comment('For dropdown type, etc.');
            $table->boolean('is_required')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['space_id', 'position']);
        });

        Schema::create('task_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('custom_field_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'custom_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
