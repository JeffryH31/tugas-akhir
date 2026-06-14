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
     * Creates pivot table for space members.
     */
    public function up(): void
    {
        Schema::create('space_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('role', ['admin', 'member', 'guest'])->default('member');
            $table->timestamps();

            $table->unique(['space_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('space_user');
    }
};
