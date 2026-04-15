<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing 'owner' roles to 'admin' and 'guest' roles to 'member'
        DB::table('workspace_members')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('workspace_members')->where('role', 'guest')->update(['role' => 'member']);

        // Alter the enum to only allow 'admin' and 'member'
        DB::statement("ALTER TABLE workspace_members MODIFY COLUMN role ENUM('admin', 'member') NOT NULL DEFAULT 'member'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE workspace_members MODIFY COLUMN role ENUM('owner', 'admin', 'member', 'guest') NOT NULL DEFAULT 'member'");
    }
};
