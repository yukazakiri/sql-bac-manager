<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'database',
        'driver',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];
    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function restores()
    {
        return $this->hasMany(Restore::class);
    }
}
