<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionStatus: string
{
    case Previsto = 'previsto';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Previsto => 'Previsto',
            self::Confirmado => 'Confirmado',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Apenas o que esta confirmado altera o saldo atual da conta. */
    public function affectsBalance(): bool
    {
        return $this === self::Confirmado;
    }
}
