<?php

use Illuminate\Support\Facades\Route;
use KenDeNigerian\PaymentsRouter\Http\Controllers\WebhookController;

Route::post(
    config('payments.webhook.path', '/payments/webhook') . '/{provider}',
    [WebhookController::class, 'handle']
)->middleware(config('payments.webhook.middleware', ['api']))
  ->name('payments.webhook');
