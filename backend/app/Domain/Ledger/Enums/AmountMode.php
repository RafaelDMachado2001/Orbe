<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Support\Money;

/**
 * Como ler o valor informado em um lancamento parcelado.
 *
 * Uma compra parcelada e anunciada pelo total ("R$ 3.600 em 12x"), mas um
 * emprestimo e anunciado pela prestacao ("24 parcelas de R$ 480"). Obrigar a
 * pessoa a multiplicar de cabeca antes de digitar era o caminho mais curto
 * para um centavo errado no meio do parcelamento.
 */
enum AmountMode: string
{
    case Total = 'total';
    case Parcela = 'parcela';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Valor total',
            self::Parcela => 'Valor da parcela',
        };
    }

    /** Converte o valor digitado no total do parcelamento. */
    public function totalFor(float $amount, int $installments): float
    {
        return match ($this) {
            self::Total => round($amount, 2),
            self::Parcela => Money::toReais(Money::toCents($amount) * $installments),
        };
    }
}
