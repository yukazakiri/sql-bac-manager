<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BackupDisk extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'config',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get the filesystem disk instance
     */
    public function getDisk()
    {
        return Storage::disk($this->name);
    }
}
