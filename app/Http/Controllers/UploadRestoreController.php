<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
        \Log::info('Upload request received', [
            'has_file' => $request->hasFile('backup_file'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except('backup_file'),
        ]);

        $validator = Validator::make($request->all(), [
            'backup_file' => 'required|file',
            'target_connection_id' => 'required|exists:database_connections,id',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator->errors());
        }

        $file = $request->file('backup_file');
        
        if (!$file) {
            \Log::error('No file found in request');
            return redirect()->back()->withErrors(['backup_file' => 'No file uploaded']);
        }
        
        \Log::info('File received', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);
        
        $targetConnection = DatabaseConnection::findOrFail($request->target_connection_id);

        try {
            // Store uploaded file temporarily
            $tempPath = $file->storeAs('temp/restore', $file->getClientOriginalName(), 'local');
            $fullPath = Storage::disk('local')->path($tempPath);
            
            \Log::info('File stored', [
                'temp_path' => $tempPath,
                'full_path' => $fullPath,
                'exists' => file_exists($fullPath),
            ]);

            if (!file_exists($fullPath)) {
                throw new \Exception("File was not stored properly at: {$fullPath}");
            }

            // Start the restore process synchronously
            $restoreJob = new \App\Jobs\PerformRestoreFromFile($targetConnection, $fullPath);
            $restoreId = $restoreJob->getRestoreId();
            
            // Dispatch synchronously so the file exists when job runs
            dispatch_sync($restoreJob);
            
            \Log::info('Job dispatched synchronously', ['restore_id' => $restoreId]);

            return redirect()->route('upload-restore.output-page', ['restoreId' => $restoreId]);
        } catch (\Exception $e) {
            \Log::error('Upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Clean up uploaded file if it was stored
            if (isset($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
            
            return redirect()->back()->withErrors(['error' => 'Failed to start restore: ' . $e->getMessage()]);
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
