<?php

declare(strict_types=1);

namespace App\Domain\Cards\Exceptions;

use App\Support\Exceptions\DomainRuleException;

final class InvoiceOperationException extends DomainRuleException
{
    public static function alreadyPaid(): self
    {
        return new self('Esta fatura ja foi paga.');
    }

    public static function nonPositiveAmount(): self
    {
        return new self('O valor do pagamento precisa ser positivo.');
    }

    public static function exceedsRemaining(): self
    {
        return new self('O valor do pagamento excede o saldo da fatura.');
    }

    public static function nothingToUndo(): self
    {
        return new self('Esta fatura não tem pagamento registrado para desfazer.');
    }

    public static function alreadyClosed(): self
    {
        return new self('Esta fatura já está fechada.');
    }
}
