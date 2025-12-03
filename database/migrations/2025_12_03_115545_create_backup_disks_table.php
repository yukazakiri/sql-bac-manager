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
        Schema::create('backup_disks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Human-readable name
            $table->string('driver'); // local, s3, digitalocean, etc.
            $table->json('config'); // Driver-specific configuration
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_disks');
    }
};
