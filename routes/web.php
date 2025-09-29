<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

Route::get('/', function () {
    return view('welcome');
});
Route::get('setwebhook', function () {
  $response = Telegram::setWebhook(['url' => 'https://bd1719db8df2.ngrok-free.app/api/telegram/webhooks']);
  return response()->json($response);
});

Route::get('deletewebhook', function () {
  $response = Telegram::deleteWebhook();
  return response()->json($response);
});
