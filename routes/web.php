<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::post('connections/test', [\App\Http\Controllers\DatabaseConnectionController::class, 'test'])->name('connections.test');
    Route::resource('connections', \App\Http\Controllers\DatabaseConnectionController::class);
    
    Route::post('connections/{connection}/backups', [\App\Http\Controllers\BackupController::class, 'store'])->name('backups.store');
    Route::get('connections/{connection}/backups', [\App\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
    Route::get('connections/{connection}/backups/{backup}', [\App\Http\Controllers\BackupController::class, 'download'])->name('backups.download');
    Route::delete('connections/{connection}/backups/{backup}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backups.destroy');
    Route::post('connections/{connection}/backups/{backup}/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->name('backups.restore');
});

require __DIR__.'/settings.php';
