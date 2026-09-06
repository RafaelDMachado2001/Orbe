<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * O ajuste de saldo existe para explicar uma diferenca. Sem diferenca, nao ha
 * nada a lancar — e gravar um lancamento de zero sujaria o extrato com uma
 * linha que nao move nada.
 */
final class BalanceAdjustmentException extends DomainRuleException
{
    public static function noDifference(): self
    {
        return new self('O saldo informado é o que a conta já tem. Nada a ajustar.');
    }

    public static function accountIsArchived(): self
    {
        return new self('Esta conta está arquivada. Reative-a para ajustar o saldo.');
    }
}
