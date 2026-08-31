<?php

declare(strict_types=1);

namespace App\Domain\Cards\Enums;

enum InvoiceStatus: string
{
    case Aberta = 'aberta';
    case Fechada = 'fechada';
    case Paga = 'paga';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Fechada => 'Fechada',
            self::Paga => 'Paga',
        };
    }

    /** Faturas que ainda representam divida em aberto. */
    public function isOutstanding(): bool
    {
        return match ($this) {
            self::Aberta, self::Fechada => true,
            self::Paga => false,
        };
    }
}
