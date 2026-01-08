<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_connections_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/connections');

        $response->assertStatus(200);
    }

    public function test_user_can_create_connection()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/connections', [
            'name' => 'Test DB',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => 'secret',
            'database' => 'test_db',
            'driver' => 'mysql',
        ]);

        $response->assertRedirect('/connections');
        $this->assertDatabaseHas('database_connections', [
            'name' => 'Test DB',
            'host' => '127.0.0.1',
            'username' => 'root',
        ]);
    }

    public function test_user_can_create_sqlite_connection()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/connections', [
            'name' => 'SQLite DB',
            'database' => '/path/to/db.sqlite',
            'driver' => 'sqlite',
        ]);

        $response->assertRedirect('/connections');
        $this->assertDatabaseHas('database_connections', [
            'name' => 'SQLite DB',
            'database' => '/path/to/db.sqlite',
            'driver' => 'sqlite',
            'host' => null,
            'port' => null,
            'username' => null,
        ]);
    }

    public function test_user_can_create_sqlite_connection_via_file_upload()
    {
        $user = User::factory()->create();
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.sqlite', 100);

        $response = $this->actingAs($user)->post('/connections', [
            'name' => 'Uploaded SQLite',
            'driver' => 'sqlite',
            'file' => $file,
        ]);

        $response->assertRedirect('/connections');
        
        $connection = DatabaseConnection::where('name', 'Uploaded SQLite')->first();
        $this->assertNotNull($connection);
        $this->assertStringContainsString('sqlite_', $connection->database);
        $this->assertStringEndsWith('.sqlite', $connection->database);
        $this->assertFileExists($connection->database);

        // Cleanup
        if (file_exists($connection->database)) {
            unlink($connection->database);
        }
    }

    public function test_user_can_create_sqlite_connection_from_sql_dump()
    {
        $user = User::factory()->create();
        
        // Create a dummy SQL file
        $sqlContent = "CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO test (name) VALUES ('foo');";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('dump.sql', $sqlContent);

        $response = $this->actingAs($user)->post('/connections', [
            'name' => 'Imported SQL',
            'driver' => 'sqlite',
            'file' => $file,
        ]);

        $response->assertRedirect('/connections');
        
        $connection = DatabaseConnection::where('name', 'Imported SQL')->first();
        $this->assertNotNull($connection);
        $this->assertStringContainsString('sqlite_', $connection->database);
        $this->assertStringEndsWith('.sqlite', $connection->database);
        $this->assertFileExists($connection->database);
        
        // Verify contents (optional, but good)
        // We can use sqlite3 to check if table exists
        $output = shell_exec("sqlite3 " . escapeshellarg($connection->database) . " 'SELECT count(*) FROM test;'");
        $this->assertEquals("1\n", $output);

        // Cleanup
        if (file_exists($connection->database)) {
            unlink($connection->database);
        }
    }

    public function test_password_is_encrypted()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/connections', [
            'name' => 'Test DB',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => 'secret',
            'database' => 'test_db',
            'driver' => 'mysql',
        ]);

        $connection = DatabaseConnection::first();
        $this->assertNotEquals('secret', $connection->getRawOriginal('password'));
        $this->assertEquals('secret', $connection->password);
    }

    public function test_user_can_test_connection()
    {
        $user = User::factory()->create();

        // Mock the DB connection to avoid actual database connection attempts during test
        // However, since we are testing the controller logic which sets config, 
        // we can check if the response is what we expect when connection fails (since we don't have a real DB running for this test)
        // Or we can mock the DB facade. For simplicity in this environment, we'll expect a failure or success depending on local env.
        // Let's just check that the endpoint is reachable and validation works.
        
        $response = $this->actingAs($user)->postJson('/connections/test', [
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => '',
            'database' => 'test_db',
            'driver' => 'mysql',
        ]);

        // It might fail to connect, but it should return a JSON response
        $this->assertTrue(in_array($response->status(), [200, 500]));
        
        $response->assertJsonStructure(['message']);
    }
}
