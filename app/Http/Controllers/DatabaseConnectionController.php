<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|string|max:10',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'database' => 'required|string|max:255',
            'driver' => 'required|string|in:mysql,pgsql',
        ]);

        DatabaseConnection::create($validated);

        return redirect()->route('connections.index')->with('success', 'Connection created successfully.');
    }

    public function show(DatabaseConnection $connection)
    {
        return Inertia::render('Connections/Show', [
            'connection' => $connection,
            'backups' => $this->backupService->listBackups($connection),
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|string|max:10',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'database' => 'required|string|max:255',
            'driver' => 'required|string|in:mysql,pgsql',
        ]);

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
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|string|max:10',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'database' => 'required|string|max:255',
            'driver' => 'required|string|in:mysql,pgsql',
        ]);

        try {
            // Configure a temporary connection
            $config = [
                'driver' => $validated['driver'],
                'host' => $validated['host'],
                'port' => $validated['port'],
                'database' => $validated['database'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ];

            if ($validated['driver'] === 'pgsql') {
                $config['charset'] = 'utf8';
                $config['prefix'] = '';
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
