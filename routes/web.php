<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DealRecordController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamWorkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\RecordManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:20,1')->name('password.email');
    Route::get('/reset-password', [ForgotPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->middleware('throttle:20,1')->name('password.reset.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/change-password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/work/{team}/create', [TeamWorkController::class, 'create'])->name('work.create');
    Route::post('/work/{team}', [TeamWorkController::class, 'store'])->name('work.store');
    Route::get('/task-records/{task}/edit', [TeamWorkController::class, 'edit'])->name('work.edit');
    Route::put('/task-records/{task}', [TeamWorkController::class, 'update'])->name('work.update');
    foreach (['referrals' => 'referral', 'marketing-updates' => 'marketing_update'] as $path => $kind) {
        Route::get('/'.$path, [DealRecordController::class, 'index'])->defaults('kind', $kind)->name($kind.'.index');
        Route::get('/'.$path.'/create', [DealRecordController::class, 'create'])->defaults('kind', $kind)->name($kind.'.create');
        Route::post('/'.$path, [DealRecordController::class, 'store'])->defaults('kind', $kind)->name($kind.'.store');
    }
    Route::get('/deal-records/{lead}/edit', [DealRecordController::class, 'edit'])->name('deals.edit');
    Route::put('/deal-records/{lead}', [DealRecordController::class, 'update'])->name('deals.update');
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::resource('users', UserController::class);
    Route::delete('/manage/{resource}/{record}', [RecordManagementController::class, 'destroy'])->whereNumber('record')->name('records.destroy');
    Route::get('/client-suggestions', [ClientController::class, 'suggestions'])->middleware('throttle:120,1')->name('clients.suggestions');
    Route::resource('clients', ClientController::class)->except('destroy');
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::get('/development', [TaskController::class, 'development'])->name('development.index');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments');
    Route::put('/tasks/{task}/assignment', [TaskController::class, 'assign'])->name('tasks.assign');
    Route::resource('tasks', TaskController::class)->except('destroy');
    foreach (['marketing', 'payroll', 'experience'] as $module) {
        if (file_exists(__DIR__."/{$module}.php")) {
            require __DIR__."/{$module}.php";
        }
    }
});
