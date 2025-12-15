<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Services;

use KenDeNigerian\PayZephyr\Contracts\ChannelMapperInterface;
use KenDeNigerian\PayZephyr\Enums\PaymentChannel;

final class ChannelMapper implements ChannelMapperInterface
{
    public function mapChannels(?array $channels, string $provider): ?array
    {
        if (empty($channels)) {
            return null;
        }

        $method = 'mapTo'.ucfirst($provider);

        if (method_exists($this, $method)) {
            return $this->{$method}($channels);
        }

        return $channels;
    }

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

    protected function mapToPayPal(array $channels): ?array
    {
        return null;
    }

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

    protected function mapToOpay(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'CARD',
            PaymentChannel::BANK_TRANSFER->value => 'BANK_ACCOUNT',
            PaymentChannel::USSD->value => 'OPAY_ACCOUNT',
            PaymentChannel::MOBILE_MONEY->value => 'OPAY_ACCOUNT',
            PaymentChannel::QR_CODE->value => 'OPAY_QRCODE',
        ];

        $validOptions = ['CARD', 'BANK_ACCOUNT', 'OPAY_ACCOUNT', 'OPAY_QRCODE', 'BALANCE', 'OTHERS'];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? (in_array(strtoupper($channel), $validOptions) ? strtoupper($channel) : null),
            $channels
        );

        return array_filter($mapped, fn ($option) => in_array($option, $validOptions));
    }

    protected function mapToMollie(array $channels): array
    {
        $mapping = [
            PaymentChannel::CARD->value => 'creditcard',
            PaymentChannel::BANK_TRANSFER->value => 'banktransfer',
            PaymentChannel::MOBILE_MONEY->value => 'paypal',
        ];

        $mapped = array_map(
            fn ($channel) => $mapping[strtolower($channel)] ?? strtolower($channel),
            $channels
        );

        $validMethods = [
            'creditcard', 'ideal', 'bancontact', 'sofort', 'giropay',
            'eps', 'klarnapaylater', 'klarnasliceit', 'paypal',
            'applepay', 'banktransfer', 'giftcard', 'przelewy24',
            'kbc', 'belfius', 'mybank', 'in3',
        ];

        return array_filter($mapped, fn ($method) => in_array($method, $validMethods));
    }

    public function shouldIncludeChannels(string $provider, ?array $channels): bool
    {
        if (empty($channels)) {
            return false;
        }

        return $this->supportsChannels($provider);
    }

    public static function getUnifiedChannels(): array
    {
        return PaymentChannel::values();
    }

    public function supportsChannels(string $provider): bool
    {
        if ($provider === 'paypal') {
            return false;
        }

        if (method_exists($this, 'mapTo'.ucfirst($provider))) {
            return true;
        }

        return false;
    }

    public function mapFromProvider(string $providerMethod, string $provider): ?string
    {
        if (empty($providerMethod)) {
            return null;
        }

        $method = 'mapFrom'.ucfirst($provider);

        if (method_exists($this, $method)) {
            return $this->{$method}($providerMethod);
        }

        $providerMethodLower = strtolower($providerMethod);
        foreach (PaymentChannel::cases() as $channel) {
            if (strtolower($channel->value) === $providerMethodLower) {
                return $channel->value;
            }
        }

        return null;
    }

    protected function mapFromPaystack(string $providerMethod): ?string
    {
        $mapping = [
            'card' => PaymentChannel::CARD->value,
            'bank' => PaymentChannel::BANK_TRANSFER->value,
            'bank_transfer' => PaymentChannel::BANK_TRANSFER->value,
            'ussd' => PaymentChannel::USSD->value,
            'qr' => PaymentChannel::QR_CODE->value,
            'mobile_money' => PaymentChannel::MOBILE_MONEY->value,
        ];

        return $mapping[strtolower($providerMethod)] ?? null;
    }

    protected function mapFromStripe(string $providerMethod): ?string
    {
        $mapping = [
            'card' => PaymentChannel::CARD->value,
            'us_bank_account' => PaymentChannel::BANK_TRANSFER->value,
        ];

        return $mapping[strtolower($providerMethod)] ?? null;
    }

    protected function mapFromMonnify(string $providerMethod): ?string
    {
        $mapping = [
            'CARD' => PaymentChannel::CARD->value,
            'ACCOUNT_TRANSFER' => PaymentChannel::BANK_TRANSFER->value,
            'USSD' => PaymentChannel::USSD->value,
        ];

        return $mapping[strtoupper($providerMethod)] ?? null;
    }

    protected function mapFromMollie(string $providerMethod): ?string
    {
        $mapping = [
            'creditcard' => PaymentChannel::CARD->value,
            'banktransfer' => PaymentChannel::BANK_TRANSFER->value,
        ];

        return $mapping[strtolower($providerMethod)] ?? null;
    }
}
