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
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * Set context.
     *
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception with context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function withContext(string $message, array $context = [], ?Throwable $previous = null): static
    {
        return (new static($message, 0, $previous))->setContext($context);
    }
}
