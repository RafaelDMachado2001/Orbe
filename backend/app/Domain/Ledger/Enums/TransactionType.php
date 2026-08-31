<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionType: string
{
    case Receita = 'receita';
    case Despesa = 'despesa';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Receita => 'Receita',
            self::Despesa => 'Despesa',
            self::Transferencia => 'Transferência',
        };
    }

    /**
     * Transferencias sao espelhadas entre contas e nao entram em relatorios
     * de receita ou despesa.
     */
    public function affectsResult(): bool
    {
        return match ($this) {
            self::Receita, self::Despesa => true,
            self::Transferencia => false,
        };
    }

    /** Multiplicador aplicado ao saldo da conta. */
    public function signal(): int
    {
        return match ($this) {
            self::Receita => 1,
            self::Despesa => -1,
            self::Transferencia => 0,
        };
    }
}
