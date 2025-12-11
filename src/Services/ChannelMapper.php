<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\ChannelMapperInterface;
use KenDeNigerian\PayZephyr\Enums\PaymentChannel;

/**
 * Channel mapper service.
 */
final class ChannelMapper implements ChannelMapperInterface
{
    /** @deprecated Use PaymentChannel enum instead */
    public const CHANNEL_CARD = 'card';

    /** @deprecated Use PaymentChannel enum instead */
    public const CHANNEL_BANK_TRANSFER = 'bank_transfer';

    /** @deprecated Use PaymentChannel enum instead */
    public const CHANNEL_USSD = 'ussd';

    /** @deprecated Use PaymentChannel enum instead */
    public const CHANNEL_MOBILE_MONEY = 'mobile_money';

    /** @deprecated Use PaymentChannel enum instead */
    public const CHANNEL_QR_CODE = 'qr_code';

    /**
     * Map channels to provider format.
     *
     * @param  array<string>|null  $channels
     */
    public function mapChannels(?array $channels, string $provider): ?array
    {
        if (empty($channels)) {
            return null;
        }

        return match ($provider) {
            'paystack' => $this->mapToPaystack($channels),
            'monnify' => $this->mapToMonnify($channels),
            'flutterwave' => $this->mapToFlutterwave($channels),
            'stripe' => $this->mapToStripe($channels),
            'paypal' => $this->mapToPayPal(),
            'square' => $this->mapToSquare($channels),
            default => $channels,
        };
    }

    /**
     * Map channels to Paystack format.
     *
     * Paystack accepts: 'card', 'bank', 'ussd', 'qr', 'mobile_money', 'bank_transfer'
     */
    protected function mapToPaystack(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'card',
            PaymentChannel::BANK_TRANSFER->value => 'bank_transfer',
            PaymentChannel::USSD->value => 'ussd',
            PaymentChannel::MOBILE_MONEY->value => 'mobile_money',
            PaymentChannel::QR_CODE->value => 'qr',
        ];

        return array_filter(
            array_map(fn ($channel) => $mapping[strtolower($channel)] ?? $channel, $channels)
        );
    }

    /**
     * Map channels to Monnify format.
     *
     * Monnify accepts: 'CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'
     */
    protected function mapToMonnify(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'CARD',
            PaymentChannel::BANK_TRANSFER->value => 'ACCOUNT_TRANSFER',
            PaymentChannel::USSD->value => 'USSD',
            PaymentChannel::MOBILE_MONEY->value => 'PHONE_NUMBER',
        ];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? strtoupper($channel),
            $channels
        );

        return array_filter($mapped, fn ($channel) => in_array($channel, ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER']));
    }

    /**
     * Map channels to Flutterwave format.
     *
     * Flutterwave accepts: 'card', 'account', 'banktransfer', 'ussd', 'mpesa',
     * 'mobilemoneyghana', 'mobilemoneyfranco', 'mobilemoneyuganda', 'nqr', etc.
     * Note: Flutterwave uses a comma-separated string, not an array
     */
    protected function mapToFlutterwave(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'card',
            PaymentChannel::BANK_TRANSFER->value => 'banktransfer',
            PaymentChannel::USSD->value => 'ussd',
            PaymentChannel::MOBILE_MONEY->value => 'mobilemoneyghana',
            PaymentChannel::QR_CODE->value => 'nqr',
        ];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? strtolower($channel),
            $channels
        );

        $validOptions = [
            'card', 'account', 'banktransfer', 'ussd', 'mpesa',
            'mobilemoneyghana', 'mobilemoneyfranco', 'mobilemoneyuganda',
            'mobilemoneyrwanda', 'mobilemoneyzambia', 'mobilemoneytanzania',
            'nqr', 'barter', 'credit', 'opay',
        ];

        return array_filter($mapped, fn ($option) => in_array($option, $validOptions));
    }

    /**
     * Map channels to Stripe format.
     *
     * Stripe accepts: 'card', 'us_bank_account', 'link', 'affirm', 'klarna', etc.
     */
    protected function mapToStripe(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'card',
            PaymentChannel::BANK_TRANSFER->value => 'us_bank_account',
        ];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? strtolower($channel),
            $channels
        );

        $validTypes = ['card', 'us_bank_account', 'link', 'affirm', 'klarna', 'cashapp', 'paypal'];

        return array_filter($mapped, fn ($type) => in_array($type, $validTypes));
    }

    /**
     * Map channels to PayPal format.
     *
     * PayPal doesn't use channels in the same way, but we can set payment method preference.
     * Returns null as PayPal handles this differently.
     */
    protected function mapToPayPal(): ?array
    {
        // PayPal doesn't support channel filtering in the same way
        // It uses payment_method_preference in experience_context
        // Return null to use default behavior
        return null;
    }

    /**
     * Map channels to Square format.
     *
     * Square accepts: 'CARD', 'CASH', 'OTHER', 'SQUARE_GIFT_CARD', 'NO_SALE'
     * Square Online Checkout primarily supports card payments.
     */
    protected function mapToSquare(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'CARD',
            PaymentChannel::BANK_TRANSFER->value => 'OTHER',
        ];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? strtoupper($channel),
            $channels
        );

        $validMethods = ['CARD', 'CASH', 'OTHER', 'SQUARE_GIFT_CARD'];

        return array_filter($mapped, fn ($method) => in_array($method, $validMethods));
    }

    /**
     * Get default channels for provider.
     *
     * @return array<string>
     */
    public function getDefaultChannels(string $provider): array
    {
        return match ($provider) {
            'paystack' => ['card', 'bank_transfer'],
            'monnify' => ['CARD', 'ACCOUNT_TRANSFER'],
            'flutterwave' => ['card'],
            'stripe' => ['card'],
            'paypal' => [], // PayPal doesn't use channels
            'square' => ['CARD'],
            default => ['card'],
        };
    }

    /**
     * Check if channels should be included.
     *
     * @param  array<string>|null  $channels
     */
    public function shouldIncludeChannels(string $provider, ?array $channels): bool
    {
        if (empty($channels)) {
            return false;
        }

        return $this->supportsChannels($provider);
    }

    /**
     * Get unified channels.
     *
     * @return array<string>
     */
    public static function getUnifiedChannels(): array
    {
        return PaymentChannel::values();
    }

    /**
     * Check if provider supports channels.
     */
    public function supportsChannels(string $provider): bool
    {
        return match ($provider) {
            'paystack', 'monnify', 'flutterwave', 'stripe', 'square' => true,
            'paypal' => false,
            default => false,
        };
    }
}
