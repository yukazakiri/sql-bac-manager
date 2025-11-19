<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index(DatabaseConnection $connection)
    {
        $backups = $connection->backups()->orderBy('created_at', 'desc')->get()->map(function ($backup) use ($connection) {
            return [
                'id' => $backup->id,
                'filename' => $backup->filename ?? 'Pending...',
                'size' => $backup->size ? $this->formatBytes($backup->size) : null,
                'path' => $backup->path,
                'status' => $backup->status,
                'progress' => $backup->progress ?? 0,
                'log' => $backup->log,
                'created_at' => $backup->created_at->diffForHumans(),
                'download_url' => $backup->status === 'completed' ? route('backups.download', [$connection, $backup->id]) : null,
                'delete_url' => route('backups.destroy', [$connection, $backup->id]),
                'restore_url' => $backup->status === 'completed' ? route('backups.restore', [$connection, $backup->id]) : null,
            ];
        });

        return response()->json($backups);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    public function store(Request $request, DatabaseConnection $connection)
    {
        $backup = $connection->backups()->create([
            'status' => 'pending',
        ]);

        \App\Jobs\PerformBackup::dispatch($backup);

        return back()->with('success', 'Backup started successfully.');
    }

    public function restore(DatabaseConnection $connection, \App\Models\Backup $backup)
    {
        try {
            $this->backupService->restore($connection, $backup->filename);
            return back()->with('success', 'Database restored successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function download(DatabaseConnection $connection, \App\Models\Backup $backup)
    {
        if (!file_exists($backup->path)) {
            abort(404);
        }

        return response()->download($backup->path);
    }

    public function destroy(DatabaseConnection $connection, \App\Models\Backup $backup)
    {
        if ($backup->path && file_exists($backup->path)) {
            unlink($backup->path);
        }
        
        $backup->delete();

        return back()->with('success', 'Backup deleted successfully.');
    }
}
