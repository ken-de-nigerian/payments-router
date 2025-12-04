<?php
namespace KenDeNigerian\PayZephyr\Webhook;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KenDeNigerian\PayZephyr\Manager;

class WebhookController extends Controller
{
    public function handle(Request $request, Manager $manager, $provider)
    {
        $verifier = new SignatureVerifier($manager);
        $body = $request->getContent();
        $headers = $request->headers->all();

        if (! $verifier->verify($provider, $headers, $body)) {
            return response('Invalid signature', 403);
        }

        $payload = $request->all();
        event("payments.$provider.webhook", [$payload]);

        return response('OK', 200);
    }
}
