<?php

namespace App\Jobs;

use App\Models\DatabaseConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PerformRestoreFromFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $connection;
    protected $filePath;
    protected $restoreId;

    /**
     * Create a new job instance.
     */
    public function __construct(DatabaseConnection $connection, string $filePath)
    {
        $this->connection = $connection;
        $this->filePath = $filePath;
        $this->restoreId = uniqid('restore_', true);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->writeOutput("Starting restore process...\n");
        $this->writeOutput("Target Database: {$this->connection->database}\n");
        $this->writeOutput("Driver: {$this->connection->driver}\n");
        $this->writeOutput("Host: {$this->connection->host}\n");
        $this->writeOutput("File: {$this->filePath}\n");
        $this->writeOutput(str_repeat('-', 50) . "\n\n");

        try {
            $this->updateStatus('processing', 0, null);

            $this->updateProgress(10, "Preparing restore...");

            if (!file_exists($this->filePath)) {
                throw new \Exception("Backup file not found at: {$this->filePath}");
            }

            $this->updateProgress(20, "Connecting to database...");

            if ($this->connection->driver === 'pgsql') {
                $this->restorePgsql();
            } else {
                $this->restoreMysql();
            }

            $this->updateProgress(100, "Restore completed successfully!");
            $this->writeOutput("\n" . str_repeat('=', 50) . "\n");
            $this->writeOutput("Restore completed successfully!\n");
            $this->updateStatus('completed', 100, null);

        } catch (\Throwable $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            $this->writeOutput("\n" . str_repeat('=', 50) . "\n");
            $this->writeOutput("ERROR: " . $e->getMessage() . "\n");
            $this->updateStatus('failed', 0, $e->getMessage());
        }
    }

    protected function restoreMysql()
    {
        $env = null;
        if ($this->connection->password) {
            $env = ['MYSQL_PWD' => $this->connection->password];
        }

        $this->writeOutput("Restoring MySQL database...\n");

        $fullCommand = sprintf(
            'mysql -h %s -P %s -u %s %s < %s 2>&1',
            escapeshellarg($this->connection->host),
            escapeshellarg($this->connection->port),
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->database),
            escapeshellarg($this->filePath)
        );

        $this->writeOutput("Executing: $fullCommand\n\n");
        $this->updateProgress(30, "Running MySQL restore...");

        $this->runProcess($fullCommand, $env);
    }

    protected function restorePgsql()
    {
        $env = null;
        if ($this->connection->password) {
            $env = ['PGPASSWORD' => $this->connection->password];
        }

        $this->writeOutput("Restoring PostgreSQL database...\n");

        $fullCommand = sprintf(
            'pg_restore -h %s -p %s -U %s -d %s -c --no-owner %s 2>&1',
            escapeshellarg($this->connection->host),
            escapeshellarg($this->connection->port),
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->database),
            escapeshellarg($this->filePath)
        );

        $this->writeOutput("Executing: $fullCommand\n\n");
        $this->updateProgress(30, "Running PostgreSQL restore...");

        $this->runProcess($fullCommand, $env);
    }

    protected function runProcess(string $command, ?array $env = null)
    {
        $process = Process::fromShellCommandline($command, null, $env);
        $process->setTimeout(600); // 10 minutes timeout

        $process->run(function ($type, $buffer) {
            $this->writeOutput($buffer);
            if ($type === Process::ERR) {
                $this->writeOutput("\n");
            }
        });

        if (!$process->isSuccessful()) {
            throw new \Exception("Restore failed: " . $process->getErrorOutput());
        }

        $this->updateProgress(80, "Restore command completed");
    }

    protected function writeOutput(string $output)
    {
        $key = "restore_output_{$this->restoreId}";
        $existingOutput = Cache::get($key, '');
        Cache::put($key, $existingOutput . $output, 3600);
    }

    protected function updateStatus(string $status, int $progress, ?string $log)
    {
        $key = "restore_status_{$this->restoreId}";
        $data = [
            'status' => $status,
            'progress' => $progress,
            'log' => $log,
            'updated_at' => now()->toISOString(),
        ];
        Cache::put($key, $data, 3600);
    }

    protected function updateProgress(int $progress, string $message)
    {
        $this->writeOutput("[" . date('H:i:s') . "] $message ($progress%)\n");
        $key = "restore_status_{$this->restoreId}";
        $data = Cache::get($key, []);
        $data['progress'] = $progress;
        $data['updated_at'] = now()->toISOString();
        Cache::put($key, $data, 3600);
    }

    public function getRestoreId(): string
    {
        return $this->restoreId;
    }
}
