<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthEndpointMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = app('payments.config') ?? config('payments', []);
        $healthConfig = $config['health_check'] ?? [];

        $requiresAuth = $healthConfig['require_auth'] ?? false;
        $allowedIps = $healthConfig['allowed_ips'] ?? [];
        $allowedTokens = $healthConfig['allowed_tokens'] ?? [];

        if (! empty($allowedIps)) {
            $clientIp = $request->ip();
            $allowed = false;

            foreach ($allowedIps as $allowedIp) {
                if ($this->ipMatches($clientIp, $allowedIp)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        if ($requiresAuth || ! empty($allowedTokens)) {
            $token = $request->bearerToken()
                ?? $request->header('X-Health-Token')
                ?? $request->query('token');

            if (empty($token) || ! in_array($token, $allowedTokens, true)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        return $next($request);
    }

    private function ipMatches(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) {
            return true;
        }

        if (str_contains($pattern, '/')) {
            [$subnet, $mask] = explode('/', $pattern, 2);
            $mask = (int) $mask;

            if ($mask >= 0 && $mask <= 32) {
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);

                if ($ipLong !== false && $subnetLong !== false) {
                    $maskLong = -1 << (32 - $mask);

                    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
                }
            }
        }

        return false;
    }
}

