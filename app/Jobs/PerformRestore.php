<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Models\DatabaseConnection;
use App\Models\Restore;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PerformRestore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $restore;
    protected $targetConnection;
    protected $backup;

    /**
     * Create a new job instance.
     */
    public function __construct(Restore $restore, DatabaseConnection $targetConnection, Backup $backup)
    {
        $this->restore = $restore;
        $this->targetConnection = $targetConnection;
        $this->backup = $backup;
    }

    /**
     * Execute the job.
     */
    public function handle(BackupService $backupService): void
    {
        $this->restore->update(['status' => 'processing', 'progress' => 0]);

        try {
            // Update progress to 25% when starting
            $this->restore->update(['progress' => 25]);

            $backupService->restore($this->targetConnection, $this->backup->filename);

            // Update progress to 75% after restore completes
            $this->restore->update(['progress' => 75]);

            $this->restore->update([
                'status' => 'completed',
                'progress' => 100,
            ]);
        } catch (\Throwable $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            $this->restore->update([
                'status' => 'failed',
                'progress' => 0,
                'log' => $e->getMessage(),
            ]);
        }
    }
}
