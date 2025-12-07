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

    Route::get('backups', [\App\Http\Controllers\BackupController::class, 'all'])->name('backups.all');
    Route::post('connections/{connection}/backups', [\App\Http\Controllers\BackupController::class, 'store'])->name('backups.store');
    Route::get('connections/{connection}/backups', [\App\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
    Route::get('connections/{connection}/backups/{backup}', [\App\Http\Controllers\BackupController::class, 'download'])->name('backups.download');
    Route::delete('connections/{connection}/backups/{backup}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backups.destroy');
    Route::post('connections/{connection}/backups/{backup}/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->name('backups.restore');
    Route::get('restores/{restore}/status', [\App\Http\Controllers\BackupController::class, 'restoreStatus'])->name('restores.status');

    // Upload Restore Routes
    Route::get('upload-restore', [\App\Http\Controllers\UploadRestoreController::class, 'index'])->name('upload-restore.index');
    Route::post('upload-restore', [\App\Http\Controllers\UploadRestoreController::class, 'uploadAndRestore'])->name('upload-restore.store');
    Route::get('upload-restore/{restoreId}/output', [\App\Http\Controllers\UploadRestoreController::class, 'output'])->name('upload-restore.output-page');
    Route::get('upload-restore/{restoreId}/output-data', [\App\Http\Controllers\UploadRestoreController::class, 'getOutput'])->name('upload-restore.output');
    Route::get('upload-restore/{restoreId}/status', [\App\Http\Controllers\UploadRestoreController::class, 'getStatus'])->name('upload-restore.status');
});

require __DIR__.'/settings.php';
