<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use App\Models\BackupDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\Process\Process;

class DatabaseConnectionController extends Controller
{
    protected $backupService;

    public function __construct(\App\Services\BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        return Inertia::render('Connections/Index', [
            'connections' => DatabaseConnection::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Connections/Create');
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'driver' => 'required|string|in:mysql,pgsql,sqlite',
            'database' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'file' => 'nullable|file',
        ];

        if ($request->input('driver') !== 'sqlite') {
            $rules['host'] = 'required|string|max:255';
            $rules['port'] = 'required|string|max:10';
            $rules['username'] = 'required|string|max:255';
            $rules['database'] = 'required|string|max:255';
        } else {
            $rules['host'] = 'nullable|string|max:255';
            $rules['port'] = 'nullable|string|max:10';
            $rules['username'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        if ($request->input('driver') === 'sqlite' && $request->hasFile('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();

            if (strtolower($extension) === 'sql') {
                // Handle SQL dump import
                $sqlPath = $file->storeAs('connections', uniqid('import_') . '.sql', 'local');
                $fullSqlPath = Storage::disk('local')->path($sqlPath);

                $dbFilename = uniqid('sqlite_') . '.sqlite';
                $dbPathRelative = 'connections/' . $dbFilename;
                $fullDbPath = Storage::disk('local')->path($dbPathRelative);

                // Ensure directory exists
                Storage::disk('local')->makeDirectory('connections');

                // Run import: sqlite3 db.sqlite < dump.sql
                $command = sprintf('sqlite3 %s < %s', escapeshellarg($fullDbPath), escapeshellarg($fullSqlPath));

                $process = Process::fromShellCommandline($command);
                $process->setTimeout(300);
                $process->run();

                // Cleanup SQL file
                Storage::disk('local')->delete($sqlPath);

                if (!$process->isSuccessful()) {
                    if (file_exists($fullDbPath)) {
                        unlink($fullDbPath);
                    }
                    return back()->withErrors(['file' => 'Failed to import SQL file: ' . $process->getErrorOutput()]);
                }

                $validated['database'] = $fullDbPath;
            } else {
                // Handle binary SQLite file
                $path = $file->storeAs(
                    'connections',
                    uniqid('sqlite_') . '.' . $extension,
                    'local'
                );
                $fullPath = Storage::disk('local')->path($path);
                $validated['database'] = $fullPath;
            }
        }

        if (empty($validated['database']) && $request->input('driver') === 'sqlite') {
             return back()->withErrors(['database' => 'Database path or file upload is required for SQLite.']);
        }

        DatabaseConnection::create($validated);

        return redirect()->route('connections.index')->with('success', 'Connection created successfully.');
    }

    public function show(DatabaseConnection $connection)
    {
        return Inertia::render('Connections/Show', [
            'connection' => $connection,
            'backups' => $this->backupService->listBackups($connection),
            'connections' => DatabaseConnection::all(),
            'backupDisks' => BackupDisk::where('is_active', true)->get(),
        ]);
    }

    public function edit(DatabaseConnection $connection)
    {
        return Inertia::render('Connections/Edit', [
            'connection' => $connection,
        ]);
    }

    public function update(Request $request, DatabaseConnection $connection)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'driver' => 'required|string|in:mysql,pgsql,sqlite',
            'database' => 'required|string|max:255',
            'password' => 'nullable|string',
        ];

        if ($request->input('driver') !== 'sqlite') {
            $rules['host'] = 'required|string|max:255';
            $rules['port'] = 'required|string|max:10';
            $rules['username'] = 'required|string|max:255';
        } else {
            $rules['host'] = 'nullable|string|max:255';
            $rules['port'] = 'nullable|string|max:10';
            $rules['username'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        $connection->update($validated);

        return redirect()->route('connections.index')->with('success', 'Connection updated successfully.');
    }

    public function destroy(DatabaseConnection $connection)
    {
        $connection->delete();

        return redirect()->route('connections.index')->with('success', 'Connection deleted successfully.');
    }

    public function test(Request $request)
    {
        $rules = [
            'driver' => 'required|string|in:mysql,pgsql,sqlite',
            'database' => 'required|string|max:255',
            'password' => 'nullable|string',
        ];

        if ($request->input('driver') !== 'sqlite') {
            $rules['host'] = 'required|string|max:255';
            $rules['port'] = 'required|string|max:10';
            $rules['username'] = 'required|string|max:255';
        } else {
            $rules['host'] = 'nullable|string|max:255';
            $rules['port'] = 'nullable|string|max:10';
            $rules['username'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        try {
            // Configure a temporary connection
            $config = [
                'driver' => $validated['driver'],
                'database' => $validated['database'],
                'password' => $validated['password'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];

            if ($validated['driver'] !== 'sqlite') {
                $config['host'] = $validated['host'];
                $config['port'] = $validated['port'];
                $config['username'] = $validated['username'];
                $config['charset'] = 'utf8mb4';
                $config['collation'] = 'utf8mb4_unicode_ci';
                $config['strict'] = true;
                $config['engine'] = null;
            }

            if ($validated['driver'] === 'pgsql') {
                $config['charset'] = 'utf8';
                $config['schema'] = 'public';
                $config['sslmode'] = 'prefer';
            }

            config(['database.connections.temp_test' => $config]);

            \Illuminate\Support\Facades\DB::purge('temp_test');
            \Illuminate\Support\Facades\DB::connection('temp_test')->getPdo();

            return response()->json(['message' => 'Connection successful!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Connection failed: ' . $e->getMessage()], 500);
        }
    }
}
