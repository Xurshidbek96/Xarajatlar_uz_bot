<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('telegram/webhooks', [\App\Http\Controllers\v1\TelegramController::class, 'webhook']);
