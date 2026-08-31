<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * Um lancamento existe, pertence ao usuario, mas nao pode ser alterado pela
 * tela de Lancamentos porque outro registro ja depende dele.
 *
 * Vira 422 na borda HTTP, como toda DomainRuleException.
 */
final class TransactionNotEditableException extends DomainRuleException
{
    public static function invoicePayment(): self
    {
        return new self(
            'Este lançamento é o pagamento de uma fatura. Desfaça o pagamento na tela de Cartões.',
        );
    }

    public static function paidInstallment(): self
    {
        return new self(
            'Esta compra já tem parcela em fatura paga. Estorne o pagamento da fatura antes de alterá-la.',
        );
    }

    public static function installmentParent(): self
    {
        return new self(
            'Compras no cartão são editadas por inteiro, nunca parcela a parcela.',
        );
    }
}
