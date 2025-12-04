<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Exceptions;

/**
 * Class DriverNotFoundException
 *
 * Thrown when a payment driver cannot be found
 */
class DriverNotFoundException extends PaymentException
{
}

/**
 * Class InvalidConfigurationException
 *
 * Thrown when payment configuration is invalid
 */
class InvalidConfigurationException extends PaymentException
{
}

/**
 * Class VerificationException
 *
 * Thrown when payment verification fails
 */
class VerificationException extends PaymentException
{
}

/**
 * Class WebhookException
 *
 * Thrown when webhook processing fails
 */
class WebhookException extends PaymentException
{
}

/**
 * Class CurrencyException
 *
 * Thrown when currency conversion fails
 */
class CurrencyException extends PaymentException
{
}

/**
 * Class ChargeException
 *
 * Thrown when payment charge fails
 */
class ChargeException extends PaymentException
{
}

/**
 * Class ProviderException
 *
 * Thrown when all payment providers fail
 */
class ProviderException extends PaymentException
{
}
