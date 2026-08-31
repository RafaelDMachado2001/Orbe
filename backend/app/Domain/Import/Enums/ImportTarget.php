<?php

declare(strict_types=1);

namespace App\Domain\Import\Enums;

/**
 * Onde as linhas do arquivo serao gravadas.
 *
 * Conta e cartao nao sao a mesma escrita: em conta cada linha vira receita ou
 * despesa direta; em cartao cada linha vira uma compra de uma parcela, que a
 * SyncInstallmentsAction encaixa na fatura do ciclo correspondente.
 */
enum ImportTarget: string
{
    case Conta = 'conta';
    case Cartao = 'cartao';

    public function label(): string
    {
        return match ($this) {
            self::Conta => 'Conta bancária',
            self::Cartao => 'Cartão de crédito',
        };
    }
}
