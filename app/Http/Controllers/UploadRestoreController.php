<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class UploadRestoreController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $connections = DatabaseConnection::all();

        return Inertia::render('UploadRestore/Index', [
            'connections' => $connections,
        ]);
    }

    public function output(string $restoreId)
    {
        return Inertia::render('UploadRestore/Output', [
            'restoreId' => $restoreId,
        ]);
    }

    public function uploadAndRestore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'backup_file' => 'required|file|mimes:sql,dump,bz2,gz',
            'target_connection_id' => 'required|exists:database_connections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('backup_file');
        $targetConnection = DatabaseConnection::findOrFail($request->target_connection_id);

        // Store uploaded file temporarily
        $tempPath = $file->storeAs('temp/restore', $file->getClientOriginalName(), 'local');

        try {
            // Start the restore process
            $restoreJob = new \App\Jobs\PerformRestoreFromFile($targetConnection, storage_path('app/' . $tempPath));
            dispatch($restoreJob);

            return response()->json([
                'success' => true,
                'restore_id' => $restoreJob->getRestoreId(),
            ]);
        } catch (\Exception $e) {
            // Clean up uploaded file if restore failed to start
            Storage::disk('local')->delete($tempPath);
            return response()->json([
                'success' => false,
                'message' => 'Failed to start restore: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getOutput(string $restoreId)
    {
        $output = \Illuminate\Support\Facades\Cache::get("restore_output_{$restoreId}", '');
        return response()->json(['output' => $output]);
    }

    public function getStatus(string $restoreId)
    {
        $status = \Illuminate\Support\Facades\Cache::get("restore_status_{$restoreId}", [
            'status' => 'pending',
            'progress' => 0,
            'log' => null,
            'updated_at' => now()->toISOString(),
        ]);
        return response()->json($status);
    }
}
