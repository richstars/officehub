<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('/links', [DashboardController::class, 'storeLink'])->name('links.store');
    Route::put('/links/{link}', [DashboardController::class, 'updateLink'])->name('links.update');
    Route::delete('/links/{link}', [DashboardController::class, 'destroyLink'])->name('links.destroy');
    
    Route::post('/files', [\App\Http\Controllers\FileController::class, 'store'])->name('files.store');
    Route::post('/files/{file}/download', [\App\Http\Controllers\FileController::class, 'download'])->name('files.download');
    Route::delete('/files/{file}', [\App\Http\Controllers\FileController::class, 'destroy'])->name('files.destroy');
    
    Route::post('/announcements', [DashboardController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [DashboardController::class, 'destroyAnnouncement'])->name('announcements.destroy');

    // Contacts
    Route::post('/contacts', [\App\Http\Controllers\ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}', [\App\Http\Controllers\ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [\App\Http\Controllers\ContactController::class, 'destroy'])->name('contacts.destroy');
});

Route::get('/contacts', [\App\Http\Controllers\ContactController::class, 'index'])->name('contacts.index');
Route::get('/files', [\App\Http\Controllers\FileController::class, 'index'])->name('files.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';
