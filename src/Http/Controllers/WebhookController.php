<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KenDeNigerian\PaymentsRouter\PaymentManager;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $manager
    ) {}

    public function handle(Request $request, string $provider)
    {
        try {
            $driver = $this->manager->driver($provider);
            
            if (config('payments.webhook.verify_signature', true)) {
                $isValid = $driver->validateWebhook(
                    $request->headers->all(),
                    $request->getContent()
                );

                if (!$isValid) {
                    logger()->warning("Invalid webhook signature for {$provider}");
                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }

            $payload = $request->all();
            
            event("payments.webhook.{$provider}", [$payload]);
            event('payments.webhook', [$provider, $payload]);

            logger()->info("Webhook processed for {$provider}");

            return response()->json(['status' => 'success'], 200);
        } catch (\Throwable $e) {
            logger()->error("Webhook processing failed", [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
