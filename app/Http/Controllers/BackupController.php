<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

    public function all()
    {
        $allBackups = \App\Models\Backup::with('connection')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'connection_id' => $backup->connection->id,
                    'connection_name' => $backup->connection->name,
                    'connection_host' => $backup->connection->host,
                    'connection_database' => $backup->connection->database,
                    'filename' => $backup->filename ?? 'Pending...',
                    'size' => $backup->size ? $this->formatBytes($backup->size) : null,
                    'path' => $backup->path,
                    'status' => $backup->status,
                    'progress' => $backup->progress ?? 0,
                    'log' => $backup->log,
                    'created_at' => $backup->created_at->diffForHumans(),
                    'download_url' => $backup->status === 'completed' ? route('backups.download', [$backup->connection, $backup->id]) : null,
                    'delete_url' => route('backups.destroy', [$backup->connection, $backup->id]),
                    'restore_url' => $backup->status === 'completed' ? route('backups.restore', [$backup->connection, $backup->id]) : null,
                ];
            });

        return Inertia::render('Backups/Index', [
            'backups' => $allBackups,
            'connections' => DatabaseConnection::all(),
        ]);
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

    public function restore(Request $request, DatabaseConnection $connection, \App\Models\Backup $backup)
    {
        $request->validate([
            'target_connection_id' => 'required|exists:database_connections,id',
        ]);

        $targetConnection = DatabaseConnection::findOrFail($request->target_connection_id);

        try {
            // Create a restore record
            $restore = $targetConnection->restores()->create([
                'backup_id' => $backup->id,
                'status' => 'pending',
            ]);

            \App\Jobs\PerformRestore::dispatch($restore, $targetConnection, $backup);

            return response()->json(['message' => 'Restore started successfully.', 'restore_id' => $restore->id]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
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

    public function restoreStatus(\App\Models\Restore $restore)
    {
        return response()->json([
            'id' => $restore->id,
            'status' => $restore->status,
            'progress' => $restore->progress,
            'log' => $restore->log,
        ]);
    }
}
