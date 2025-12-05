<?php

use Illuminate\Support\Facades\Route;
use KenDeNigerian\PayZephyr\Http\Controllers\WebhookController;

/**
 * Register the main webhook entry point.
 *
 * This route listens for POST requests from payment gateways (identified by the
 * {provider} parameter).
 * Both the URL path and the middleware stack are retrieved
 * from the configuration file to allow for easy customization in the host app.
 */
Route::post(
    config('payments.webhook.path', '/payments/webhook').'/{provider}',
    [WebhookController::class, 'handle']
)->middleware(config('payments.webhook.middleware', ['api']))
    ->name('payments.webhook');
