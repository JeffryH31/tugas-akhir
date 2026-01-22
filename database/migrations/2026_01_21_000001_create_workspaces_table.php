<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#7C3AED');
            $table->string('icon')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_personal')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'is_personal']);
        });

        // Pivot table for workspace members
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, admin, member, guest
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
