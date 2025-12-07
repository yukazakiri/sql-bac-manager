<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PerformBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $backup;

    /**
     * Create a new job instance.
     */
    public function __construct(Backup $backup)
    {
        $this->backup = $backup;
    }

    /**
     * Execute the job.
     */
    public function handle(BackupService $backupService): void
    {
        $this->backup->update(['status' => 'processing', 'progress' => 0]);

        try {
            // Update progress to 25% when starting
            $this->backup->update(['progress' => 25]);

            $disk = $this->backup->backupDisk;
            $backupType = $this->backup->backup_type ?? 'full';
            $result = $backupService->createBackup($this->backup->connection, $disk, $backupType);

            // Update progress to 75% after backup completes
            $this->backup->update(['progress' => 75]);

            $this->backup->update([
                'status' => 'completed',
                'progress' => 100,
                'path' => $result['path'],
                'filename' => $result['filename'],
                'size' => $result['size'],
                'backup_disk_id' => $disk?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            $this->backup->update([
                'status' => 'failed',
                'progress' => 0,
                'log' => $e->getMessage(),
            ]);
        }
    }
}
