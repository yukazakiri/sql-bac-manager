<?php

namespace App\Providers;

use App\Services\BackupDiskManager;
use Illuminate\Support\ServiceProvider;

class BackupDiskServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            // Load backup disks on application boot
            $diskManager = new BackupDiskManager();
            $diskManager->loadDisks();
        } catch (\Exception $e) {
            // Fail silently if table doesn't exist (e.g. during fresh migration or tests)
        }
    }
}
