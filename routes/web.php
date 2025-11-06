<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard routes
Route::prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/users', [DashboardController::class, 'users'])->name('dashboard.users');
    Route::get('/users/{id}', [DashboardController::class, 'userDetails'])->name('dashboard.user.details');
    Route::get('/users/{id}/transactions', [DashboardController::class, 'userTransactions'])->name('dashboard.user.transactions');
    Route::get('/users/{id}/stats', [DashboardController::class, 'userStats'])->name('dashboard.user.stats');
    
    // API routes
    Route::get('/api/stats', [DashboardController::class, 'apiStats'])->name('dashboard.api.stats');
    Route::get('/api/recent-users', [DashboardController::class, 'recentUsers'])->name('dashboard.api.recent-users');
    Route::get('/api/user-stats', [DashboardController::class, 'userStatsApi'])->name('dashboard.api.user-stats');
    Route::get('/api/users', [DashboardController::class, 'usersApi'])->name('dashboard.api.users');
});

Route::get('setwebhook', function () {
  $response = Telegram::setWebhook(['url' => 'https://7f5a79cf5435.ngrok-free.app/api/telegram/webhooks']);
  return response()->json($response);
});

Route::get('deletewebhook', function () {
  $response = Telegram::deleteWebhook();
  return response()->json($response);
});
