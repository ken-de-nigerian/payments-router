<?php
use Illuminate\Support\Facades\Route;
use Nwaneri\PaymentsRouter\Webhook\WebhookController;

Route::post(config('payments.webhook.path').'/{provider}', [WebhookController::class, 'handle']);
