<?php

declare(strict_types=1);

namespace KenDeNigerian\PaymentsRouter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \KenDeNigerian\PaymentsRouter\Payment amount(float $amount)
 * @method static \KenDeNigerian\PaymentsRouter\Payment currency(string $currency)
 * @method static \KenDeNigerian\PaymentsRouter\Payment email(string $email)
 * @method static \KenDeNigerian\PaymentsRouter\Payment reference(string $reference)
 * @method static \KenDeNigerian\PaymentsRouter\Payment callback(string $url)
 * @method static \KenDeNigerian\PaymentsRouter\Payment metadata(array $metadata)
 * @method static \KenDeNigerian\PaymentsRouter\Payment description(string $description)
 * @method static \KenDeNigerian\PaymentsRouter\Payment customer(array $customer)
 * @method static \KenDeNigerian\PaymentsRouter\Payment with(string|array $providers)
 * @method static \KenDeNigerian\PaymentsRouter\Payment using(string|array $providers)
 * @method static \KenDeNigerian\PaymentsRouter\DataObjects\ChargeResponse charge()
 * @method static mixed redirect()
 * @method static \KenDeNigerian\PaymentsRouter\DataObjects\VerificationResponse verify(string $reference, ?string $provider = null)
 *
 * @see \KenDeNigerian\PaymentsRouter\Payment
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \KenDeNigerian\PaymentsRouter\Payment::class;
    }
}
