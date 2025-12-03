<?php

namespace App\Services;

use App\Models\BackupDisk;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BackupService
{
    protected $backupPath = 'backups';

    public function createBackup(DatabaseConnection $connection, ?BackupDisk $disk = null)
    {
        // Use default disk if none specified
        if (!$disk) {
            $diskManager = new BackupDiskManager();
            $disk = $diskManager->getDefaultDisk();
        }

        $filename = $this->getBackupFilename($connection);
        $diskInstance = $disk->getDisk();

        // Ensure directory exists
        if (!$diskInstance->exists($this->backupPath)) {
            $diskInstance->makeDirectory($this->backupPath);
        }

        // Create temp file for backup
        $tempPath = tempnam(sys_get_temp_dir(), 'backup_');
        $finalPath = $this->backupPath . '/' . $filename;

        try {
            if ($connection->driver === 'pgsql') {
                $this->backupPgsql($connection, $tempPath);
            } else {
                $this->backupMysql($connection, $tempPath);
            }

            // Upload to disk
            $diskInstance->put($finalPath, fopen($tempPath, 'r'));
            $size = $diskInstance->size($finalPath);

            // Get the full path if it's a local disk, otherwise just the storage path
            if ($disk->driver === 'local') {
                $fullPath = $diskInstance->path($finalPath);
            } else {
                $fullPath = $finalPath;
            }

            return [
                'path' => $fullPath,
                'filename' => $filename,
                'size' => $size,
                'disk' => $disk,
            ];
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    protected function backupMysql(DatabaseConnection $connection, string $path)
    {
        $env = null;
        if ($connection->password) {
            $env = ['MYSQL_PWD' => $connection->password];
        }

        $fullCommand = sprintf(
            'mysqldump -h %s -P %s -u %s %s > %s',
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    protected function backupPgsql(DatabaseConnection $connection, string $path)
    {
        $env = null;
        if ($connection->password) {
            $env = ['PGPASSWORD' => $connection->password];
        }

        $fullCommand = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s -F c -f %s', // -F c for custom format (compressed)
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    public function restore(DatabaseConnection $connection, string $filename, BackupDisk $disk)
    {
        $diskInstance = $disk->getDisk();
        $filePath = $this->backupPath . '/' . $filename;

        if (!$diskInstance->exists($filePath)) {
            throw new \Exception("Backup file not found.");
        }

        // Download to temp file for restoration
        $tempPath = tempnam(sys_get_temp_dir(), 'restore_');

        try {
            // Download file from storage
            $tempStream = fopen($tempPath, 'w');
            $diskInstance->readStream($filePath);
            $diskInstance->get($filePath);
            file_put_contents($tempPath, $diskInstance->get($filePath));

            if ($connection->driver === 'pgsql') {
                $this->restorePgsql($connection, $tempPath);
            } else {
                $this->restoreMysql($connection, $tempPath);
            }
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    protected function restoreMysql(DatabaseConnection $connection, string $path)
    {
        $env = null;
        if ($connection->password) {
            $env = ['MYSQL_PWD' => $connection->password];
        }

        $fullCommand = sprintf(
            'mysql -h %s -P %s -u %s %s < %s',
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    protected function restorePgsql(DatabaseConnection $connection, string $path)
    {
        $env = null;
        if ($connection->password) {
            $env = ['PGPASSWORD' => $connection->password];
        }

        // pg_restore is used for custom format dumps
        $fullCommand = sprintf(
            'pg_restore -h %s -p %s -U %s -d %s -c --no-owner %s', // -c to clean, --no-owner to skip ownership changes
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    protected function runProcess(string $command, ?array $env = null)
    {
        $process = Process::fromShellCommandline($command, null, $env);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function listBackups(DatabaseConnection $connection, ?BackupDisk $disk = null)
    {
        // Use default disk if none specified
        if (!$disk) {
            $diskManager = new BackupDiskManager();
            $disk = $diskManager->getDefaultDisk();
        }

        $diskInstance = $disk->getDisk();
        $files = $diskInstance->files($this->backupPath);
        $backups = [];

        $prefix = $connection->id . '_';

        foreach ($files as $file) {
            $basename = basename($file);
            if (str_starts_with($basename, $prefix)) {
                $backups[] = [
                    'filename' => $basename,
                    'size' => $diskInstance->size($file),
                    'created_at' => $diskInstance->lastModified($file),
                ];
            }
        }

        // Sort by created_at desc
        usort($backups, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    public function deleteBackup(string $filename, BackupDisk $disk)
    {
        $diskInstance = $disk->getDisk();
        $filePath = $this->backupPath . '/' . $filename;

        if ($diskInstance->exists($filePath)) {
            $diskInstance->delete($filePath);
        }
    }

    public function getBackupPath(string $filename, BackupDisk $disk)
    {
        $diskInstance = $disk->getDisk();
        return $diskInstance->path($this->backupPath . '/' . $filename);
    }

    protected function getBackupFilename(DatabaseConnection $connection)
    {
        return sprintf(
            '%d_%s_%s.sql',
            $connection->id,
            $connection->database,
            date('Y-m-d_H-i-s')
        );
    }
}
