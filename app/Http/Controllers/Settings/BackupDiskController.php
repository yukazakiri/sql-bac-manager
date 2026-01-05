<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BackupDisk;
use App\Services\BackupDiskManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BackupDiskController extends Controller
{
    /**
     * Show the backup disks settings page.
     */
    public function index()
    {
        return Inertia::render('settings/backup-disks', [
            'disks' => BackupDisk::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a new backup disk.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:backup_disks',
            'driver' => 'required|string|in:local,s3,digitalocean,wasabi,backblaze',
            'config' => 'required|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            BackupDisk::where('is_default', true)->update(['is_default' => false]);
        }

        BackupDisk::create($validated);

        (new BackupDiskManager())->loadDisks();

        return redirect()->back();
    }

    /**
     * Update the specified backup disk.
     */
    public function update(Request $request, BackupDisk $backupDisk)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:backup_disks,name,' . $backupDisk->id,
            'driver' => 'required|string|in:local,s3,digitalocean,wasabi,backblaze',
            'config' => 'required|array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (($validated['is_default'] ?? false) && !$backupDisk->is_default) {
            BackupDisk::where('is_default', true)->update(['is_default' => false]);
        }

        $backupDisk->update($validated);

        (new BackupDiskManager())->loadDisks();

        return redirect()->back();
    }

    /**
     * Remove the specified backup disk.
     */
    public function destroy(BackupDisk $backupDisk)
    {
        $backupDisk->delete();
        
        (new BackupDiskManager())->loadDisks();

        return redirect()->back();
    }
}
