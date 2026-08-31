<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Debito = 'debito';
    case Credito = 'credito';
    case Dinheiro = 'dinheiro';
    case Boleto = 'boleto';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Debito => 'Débito',
            self::Credito => 'Crédito',
            self::Dinheiro => 'Dinheiro',
            self::Boleto => 'Boleto',
            self::Transferencia => 'Transferência',
        };
    }
}
