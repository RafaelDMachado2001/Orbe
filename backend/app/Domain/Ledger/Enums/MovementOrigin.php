<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * De onde a linha do extrato vem. Nao e o mesmo que o metodo de pagamento: um
 * lancamento de conta pode ser pix, boleto ou dinheiro, mas continua sendo uma
 * linha de conta. Cartao e sempre uma parcela.
 */
enum MovementOrigin: string
{
    case Conta = 'conta';
    case Cartao = 'cartao';

    public function label(): string
    {
        return match ($this) {
            self::Conta => 'Conta',
            self::Cartao => 'Cartão',
        };
    }
}
