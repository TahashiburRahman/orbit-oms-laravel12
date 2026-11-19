<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReleaseController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
Route::get('/dashboard/{team}', [DashboardController::class, 'show'])->name('dashboard.team');
Route::resource('releases', ReleaseController::class)->except('destroy');
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
Route::post('/notifications/{notification}/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
