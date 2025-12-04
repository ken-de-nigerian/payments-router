<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Exceptions;

use Exception;

/**
 * Class PaymentException
 *
 * Base exception for all payment-related errors
 */
class PaymentException extends Exception
{
    protected array $context = [];

    /**
     * Set exception context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get exception context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception with context
     */
    public static function withContext(string $message, array $context = []): static
    {
        return (new static($message))->setContext($context);
    }
}
