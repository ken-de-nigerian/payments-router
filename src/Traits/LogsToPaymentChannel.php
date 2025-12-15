<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Traits;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

trait LogsToPaymentChannel
{
    protected function log(string $level, string $message, array $context = []): void
    {
        $config = app('payments.config') ?? config('payments', []);
        $channelName = $config['logging']['channel'] ?? 'payments';

        try {
            Log::channel($channelName)->{$level}($message, $context);
        } catch (InvalidArgumentException) {
            Log::{$level}($message, $context);
        }
    }
}
