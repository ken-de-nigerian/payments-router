<?php

declare(strict_types=1);

use KenDeNigerian\PayZephyr\Payment;

if (! function_exists('payment')) {
    /**
     * Create a new Payment instance
     */
    function payment(): Payment
    {
        return app(Payment::class);
    }
}
