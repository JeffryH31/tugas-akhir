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
     * Creates the comments table (polymorphic).
     * Comments can be attached to tasks or other models.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('parent_id')
                ->nullable();
            $table->json('mentions')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('comments')->nullOnDelete();
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
