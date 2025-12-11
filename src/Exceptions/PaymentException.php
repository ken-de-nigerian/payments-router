<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Exceptions;

use Exception;
use Throwable;

/**
 * Base payment exception.
 */
class PaymentException extends Exception
{
    protected array $context = [];

    /**
     * Set context.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception with context.
     */
    public static function withContext(string $message, array $context = [], ?Throwable $previous = null): static
    {
        return (new static($message, 0, $previous))->setContext($context);
    }
}
