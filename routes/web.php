<?php

use App\Http\Controllers\CameraSetupController;
use App\Http\Controllers\PublicCameraController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/setup', [SessionController::class, 'setup'])->name('setup');
    Route::post('/setup', [SessionController::class, 'create'])->middleware('throttle:5,1');
    Route::get('/login', [SessionController::class, 'login'])->name('login');
    Route::post('/login', [SessionController::class, 'authenticate'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/api/storage', [StorageController::class, 'status'])->name('storage.status');
    Route::put('/api/storage', [StorageController::class, 'configure'])->name('storage.configure');
    Route::post('/api/storage/run', [StorageController::class, 'run'])->middleware('throttle:6,1')->name('storage.run');
    Route::post('/api/storage/{id}/restore-test', [StorageController::class, 'restore'])->whereUuid('id')->name('storage.restore');
    Route::get('/archives/{id}/video', [StorageController::class, 'play'])->whereUuid('id')->name('archives.play');
    Route::put('/api/camera-setup', [CameraSetupController::class, 'save'])->name('camera-setup.save');
    Route::get('/', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
    Route::get('/media/{file}', [WorkspaceController::class, 'media'])->name('media');
    Route::get('/api/public-camera/status', [PublicCameraController::class, 'status'])->middleware('throttle:30,1')->name('public-camera.status');
    Route::post('/api/incidents', [WorkspaceController::class, 'storeIncident'])->name('incidents.store');
    Route::patch('/api/incidents/{id}/protect', [WorkspaceController::class, 'protect'])->whereNumber('id')->name('incidents.protect');
    Route::put('/api/policy', [WorkspaceController::class, 'policy'])->name('policy.save');
    Route::put('/api/settings', [WorkspaceController::class, 'settings'])->name('settings.save');
});
