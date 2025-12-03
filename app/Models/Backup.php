<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'database_connection_id',
        'backup_disk_id',
        'status',
        'progress',
        'path',
        'filename',
        'size',
        'log',
    ];

    public function connection()
    {
        return $this->belongsTo(DatabaseConnection::class, 'database_connection_id');
    }

    public function backupDisk()
    {
        return $this->belongsTo(BackupDisk::class);
    }
}
