<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restore extends Model
{
    use HasFactory;

    protected $fillable = [
        'database_connection_id',
        'backup_id',
        'status',
        'progress',
        'log',
    ];

    protected $casts = [
        'status' => 'string',
        'progress' => 'integer',
    ];

    public function connection()
    {
        return $this->belongsTo(DatabaseConnection::class, 'database_connection_id');
    }

    public function backup()
    {
        return $this->belongsTo(Backup::class);
    }
}
