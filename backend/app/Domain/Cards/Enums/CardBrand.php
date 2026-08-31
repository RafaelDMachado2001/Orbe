<?php

declare(strict_types=1);

namespace App\Domain\Cards\Enums;

enum CardBrand: string
{
    case Visa = 'visa';
    case Mastercard = 'mastercard';
    case Elo = 'elo';
    case Amex = 'amex';
    case Hipercard = 'hipercard';

    public function label(): string
    {
        return match ($this) {
            self::Visa => 'Visa',
            self::Mastercard => 'Mastercard',
            self::Elo => 'Elo',
            self::Amex => 'American Express',
            self::Hipercard => 'Hipercard',
        };
    }
}
