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

    // Admin login (token olish) - sanctum talab qilmaydi
    Route::post('/api/admin/login', [DashboardController::class, 'adminLogin'])->name('dashboard.api.admin-login');

    // API routes (sanctum bilan himoyalangan)
    Route::prefix('api')->middleware('auth:sanctum')->group(function () {
        Route::get('/stats', [DashboardController::class, 'apiStats'])->name('dashboard.api.stats');
        Route::get('/recent-users', [DashboardController::class, 'recentUsers'])->name('dashboard.api.recent-users');
        Route::get('/user-stats', [DashboardController::class, 'userStatsApi'])->name('dashboard.api.user-stats');
        Route::get('/users', [DashboardController::class, 'usersApi'])->name('dashboard.api.users');
        Route::get('/users/{id}', [DashboardController::class, 'userDetailsApi'])->name('dashboard.api.user.details');
        Route::get('/users/{id}/stats', [DashboardController::class, 'userStats'])->name('dashboard.api.user.stats');
        Route::get('/users/{id}/transactions', [DashboardController::class, 'userTransactions'])->name('dashboard.api.user.transactions');
    });
});

Route::get('setwebhook', function () {
  $response = Telegram::setWebhook(['url' => 'https://7f5a79cf5435.ngrok-free.app/api/telegram/webhooks']);
  return response()->json($response);
});

Route::get('deletewebhook', function () {
  $response = Telegram::deleteWebhook();
  return response()->json($response);
});
