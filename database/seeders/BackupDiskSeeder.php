<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BackupDisk;

class BackupDiskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default local backup disk if none exists
        if (BackupDisk::count() === 0) {
            BackupDisk::create([
                'name' => 'backups',
                'driver' => 'local',
                'config' => [
                    'root' => storage_path('app/private/backups'),
                ],
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }
}
