<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TelegramWebhookController;

Route::post('/telegram/webhook/{bot}', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
