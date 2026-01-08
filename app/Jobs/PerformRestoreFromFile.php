<?php

namespace App\Jobs;

use App\Models\DatabaseConnection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PerformRestoreFromFile
{
    use Dispatchable;

    protected $databaseConnection;
    protected $filePath;
    protected $restoreId;

    /**
     * Create a new job instance.
     */
    public function __construct(DatabaseConnection $connection, string $filePath)
    {
        $this->databaseConnection = $connection;
        $this->filePath = $filePath;
        $this->restoreId = uniqid('restore_', true);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->writeOutput("Starting restore process...\n");
        $this->writeOutput("Target Database: {$this->databaseConnection->database}\n");
        $this->writeOutput("Driver: {$this->databaseConnection->driver}\n");
        $this->writeOutput("Host: {$this->databaseConnection->host}\n");
        $this->writeOutput("File: {$this->filePath}\n");
        $this->writeOutput(str_repeat('-', 50) . "\n\n");

        try {
            $this->updateStatus('processing', 0, null);

            $this->updateProgress(10, "Preparing restore...");

            if (!file_exists($this->filePath)) {
                throw new \Exception("Backup file not found at: {$this->filePath}");
            }

            $this->updateProgress(20, "Connecting to database...");

            if ($this->databaseConnection->driver === 'pgsql') {
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
        if ($this->databaseConnection->password) {
            $env = ['MYSQL_PWD' => $this->databaseConnection->password];
        }

        $this->writeOutput("Restoring MySQL database...\n");

        $fullCommand = sprintf(
            'mysql -h %s -P %s -u %s %s < %s 2>&1',
            escapeshellarg($this->databaseConnection->host),
            escapeshellarg($this->databaseConnection->port),
            escapeshellarg($this->databaseConnection->username),
            escapeshellarg($this->databaseConnection->database),
            escapeshellarg($this->filePath)
        );

        $this->writeOutput("Executing: $fullCommand\n\n");
        $this->updateProgress(30, "Running MySQL restore...");

        $this->runProcess($fullCommand, $env);
    }

    protected function restorePgsql()
    {
        $env = null;
        if ($this->databaseConnection->password) {
            $env = ['PGPASSWORD' => $this->databaseConnection->password];
        }

        $this->writeOutput("Restoring PostgreSQL database...\n");

        $fullCommand = sprintf(
            'pg_restore -h %s -p %s -U %s -d %s -c --no-owner %s 2>&1',
            escapeshellarg($this->databaseConnection->host),
            escapeshellarg($this->databaseConnection->port),
            escapeshellarg($this->databaseConnection->username),
            escapeshellarg($this->databaseConnection->database),
            escapeshellarg($this->filePath)
        );

        $this->writeOutput("Executing: $fullCommand\n\n");
        $this->updateProgress(30, "Running PostgreSQL restore...");

        $this->runProcess($fullCommand, $env, [0, 1]);
    }

    protected function runProcess(string $command, ?array $env = null, array $allowedExitCodes = [0])
    {
        $process = Process::fromShellCommandline($command, null, $env);
        $process->setTimeout(600); // 10 minutes timeout

        $process->run(function ($type, $buffer) {
            $this->writeOutput($buffer);
            if ($type === Process::ERR) {
                $this->writeOutput("\n");
            }
        });

        if (!in_array($process->getExitCode(), $allowedExitCodes)) {
            throw new \Exception("Restore failed with exit code {$process->getExitCode()}");
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
