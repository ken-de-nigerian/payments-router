<?php
use Illuminate\Support\Facades\Route;
use KenDeNigerian\PayZephyr\Webhook\WebhookController;

Route::post(config('payments.webhook.path').'/{provider}', [WebhookController::class, 'handle']);
