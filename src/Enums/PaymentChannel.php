<?php

declare(strict_types=1);

namespace KenDeNigerian\PayZephyr\Enums;

/**
 * Payment channel enum.
 */
enum PaymentChannel: string
{
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case USSD = 'ussd';
    case MOBILE_MONEY = 'mobile_money';
    case QR_CODE = 'qr_code';

    /**
     * Get channel label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Credit/Debit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::USSD => 'USSD',
            self::MOBILE_MONEY => 'Mobile Money',
            self::QR_CODE => 'QR Code',
        };
    }

    /**
     * Get all channel values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
