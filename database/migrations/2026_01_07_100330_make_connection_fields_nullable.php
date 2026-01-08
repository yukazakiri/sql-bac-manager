<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->string('host')->nullable()->change();
            $table->string('port')->nullable()->change();
            $table->string('username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->string('host')->nullable(false)->change();
            $table->string('port')->nullable(false)->change();
            $table->string('username')->nullable(false)->change();
        });
    }
};
