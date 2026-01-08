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

    public function createBackup(DatabaseConnection $connection, ?BackupDisk $disk = null, string $backupType = 'full')
    {
        // Use default disk if none specified
        if (!$disk) {
            $diskManager = new BackupDiskManager();
            $disk = $diskManager->getDefaultDisk();
        }

        // Throw exception if no disk is available
        if (!$disk) {
            throw new \Exception('No backup disk configured. Please configure a backup disk first.');
        }

        $filename = $this->getBackupFilename($connection, $backupType);
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
                $this->backupPgsql($connection, $tempPath, $backupType);
            } elseif ($connection->driver === 'sqlite') {
                $this->backupSqlite($connection, $tempPath);
            } else {
                $this->backupMysql($connection, $tempPath, $backupType);
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

    protected function backupMysql(DatabaseConnection $connection, string $path, string $backupType = 'full')
    {
        $env = null;
        if ($connection->password) {
            $env = ['MYSQL_PWD' => $connection->password];
        }

        $options = '';
        switch ($backupType) {
            case 'structure':
                $options = '--no-data';
                break;
            case 'data':
                $options = '--no-create-info';
                break;
            case 'public_schema':
                // MySQL doesn't have schemas like PostgreSQL, so this acts like full backup
                $options = '';
                break;
            default: // full
                $options = '';
                break;
        }

        $fullCommand = sprintf(
            'mysqldump -h %s -P %s -u %s %s %s > %s',
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            $options,
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    protected function backupPgsql(DatabaseConnection $connection, string $path, string $backupType = 'full')
    {
        $env = null;
        if ($connection->password) {
            $env = ['PGPASSWORD' => $connection->password];
        }

        $options = '-F c'; // custom format (compressed)
        switch ($backupType) {
            case 'structure':
                $options .= ' --schema-only';
                break;
            case 'data':
                $options .= ' --data-only';
                break;
            case 'public_schema':
                $options .= ' --schema=public';
                break;
            default: // full
                break;
        }

        $fullCommand = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s %s -f %s',
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            $options,
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand, $env);
    }

    protected function backupSqlite(DatabaseConnection $connection, string $path)
    {
        $fullCommand = sprintf(
            'sqlite3 %s .dump > %s',
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand);
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
            } elseif ($connection->driver === 'sqlite') {
                $this->restoreSqlite($connection, $tempPath);
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

    protected function restoreSqlite(DatabaseConnection $connection, string $path)
    {
        $fullCommand = sprintf(
            'sqlite3 %s < %s',
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        $this->runProcess($fullCommand);
    }

    protected function restorePgsql(DatabaseConnection $connection, string $path)
    {
        $env = null;
        if ($connection->password) {
            $env = ['PGPASSWORD' => $connection->password];
        }

        // Try pg_restore first (for custom format dumps)
        $restoreCommand = sprintf(
            'pg_restore -h %s -p %s -U %s -d %s -c --no-owner %s', // -c to clean, --no-owner to skip ownership changes
            escapeshellarg($connection->host),
            escapeshellarg($connection->port),
            escapeshellarg($connection->username),
            escapeshellarg($connection->database),
            escapeshellarg($path)
        );

        try {
            $this->runProcess($restoreCommand, $env, [0, 1]);
        } catch (ProcessFailedException $e) {
            // If pg_restore fails, try psql (for plain SQL dumps)
            $psqlCommand = sprintf(
                'psql -h %s -p %s -U %s -d %s -f %s',
                escapeshellarg($connection->host),
                escapeshellarg($connection->port),
                escapeshellarg($connection->username),
                escapeshellarg($connection->database),
                escapeshellarg($path)
            );

            $this->runProcess($psqlCommand, $env);
        }
    }

    protected function runProcess(string $command, ?array $env = null, array $allowedExitCodes = [0])
    {
        $process = Process::fromShellCommandline($command, null, $env);
        $process->setTimeout(300);
        $process->run();

        if (!in_array($process->getExitCode(), $allowedExitCodes)) {
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

        // Return empty array if no disk is available
        if (!$disk) {
            return [];
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

    protected function getBackupFilename(DatabaseConnection $connection, string $backupType = 'full')
    {
        return sprintf(
            '%d_%s_%s_%s.sql',
            $connection->id,
            $connection->database,
            $backupType,
            date('Y-m-d_H-i-s')
        );
    }
}
