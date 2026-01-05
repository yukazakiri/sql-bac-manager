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
        // Deactivate existing default if any
        DB::table('backup_disks')->where('is_default', true)->update(['is_default' => false]);

        DB::table('backup_disks')->insert([
            'name' => 'Local Public',
            'driver' => 'local',
            'config' => json_encode(['root' => storage_path('app/public')]),
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('backup_disks')->where('name', 'Local Public')->delete();
    }
};
