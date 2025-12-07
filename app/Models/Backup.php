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
        'backup_type',
        'status',
        'progress',
        'path',
        'filename',
        'size',
        'log',
    ];

    const TYPE_FULL = 'full';
    const TYPE_STRUCTURE = 'structure';
    const TYPE_DATA = 'data';
    const TYPE_PUBLIC_SCHEMA = 'public_schema';

    public static function getBackupTypes(): array
    {
        return [
            self::TYPE_FULL => 'Full (Structure + Data)',
            self::TYPE_STRUCTURE => 'Structure Only',
            self::TYPE_DATA => 'Data Only',
            self::TYPE_PUBLIC_SCHEMA => 'Public Schema Only',
        ];
    }

    public function connection()
    {
        return $this->belongsTo(DatabaseConnection::class, 'database_connection_id');
    }

    public function backupDisk()
    {
        return $this->belongsTo(BackupDisk::class);
    }
}
