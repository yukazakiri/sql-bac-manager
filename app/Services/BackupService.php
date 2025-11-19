<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BackupService
{
    protected $backupDisk = 'local';
    protected $backupPath = 'backups';

    public function createBackup(DatabaseConnection $connection)
    {
        $filename = $this->getBackupFilename($connection);
        $path = Storage::disk($this->backupDisk)->path($this->backupPath . '/' . $filename);
        
        // Ensure directory exists
        if (!Storage::disk($this->backupDisk)->exists($this->backupPath)) {
            Storage::disk($this->backupDisk)->makeDirectory($this->backupPath);
        }

        if ($connection->driver === 'pgsql') {
            $this->backupPgsql($connection, $path);
        } else {
            $this->backupMysql($connection, $path);
        }

        $size = Storage::disk($this->backupDisk)->size($this->backupPath . '/' . $filename);

        return [
            'path' => $path,
            'filename' => $filename,
            'size' => $size,
        ];
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

    public function restore(DatabaseConnection $connection, string $filename)
    {
        $path = Storage::disk($this->backupDisk)->path($this->backupPath . '/' . $filename);

        if (!file_exists($path)) {
            throw new \Exception("Backup file not found.");
        }

        if ($connection->driver === 'pgsql') {
            $this->restorePgsql($connection, $path);
        } else {
            $this->restoreMysql($connection, $path);
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
            'pg_restore -h %s -p %s -U %s -d %s -c %s', // -c to clean (drop) database objects before recreating
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

    public function listBackups(DatabaseConnection $connection)
    {
        // Filter backups for this connection? 
        // Or just list all in the folder?
        // Ideally we prefix filenames with connection ID or name.
        
        $files = Storage::disk($this->backupDisk)->files($this->backupPath);
        $backups = [];
        
        $prefix = $connection->id . '_';

        foreach ($files as $file) {
            $basename = basename($file);
            if (str_starts_with($basename, $prefix)) {
                $backups[] = [
                    'filename' => $basename,
                    'size' => Storage::disk($this->backupDisk)->size($file),
                    'created_at' => Storage::disk($this->backupDisk)->lastModified($file),
                ];
            }
        }
        
        // Sort by created_at desc
        usort($backups, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }
    
    public function deleteBackup(string $filename)
    {
        if (Storage::disk($this->backupDisk)->exists($this->backupPath . '/' . $filename)) {
            Storage::disk($this->backupDisk)->delete($this->backupPath . '/' . $filename);
        }
    }
    
    public function getBackupPath(string $filename)
    {
        return Storage::disk($this->backupDisk)->path($this->backupPath . '/' . $filename);
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
