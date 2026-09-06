<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * Excluir um banco levaria em cascata as contas e os cartoes dele — e, com as
 * contas, todo o extrato. Banco em uso se esvazia primeiro.
 */
final class BankNotRemovableException extends DomainRuleException
{
    public static function inUse(int $accounts, int $cards): self
    {
        $parts = [];

        if ($accounts > 0) {
            $parts[] = $accounts === 1 ? 'uma conta' : "{$accounts} contas";
        }

        if ($cards > 0) {
            $parts[] = $cards === 1 ? 'um cartão' : "{$cards} cartões";
        }

        return new self(sprintf(
            'Este banco ainda tem %s. Exclua ou mova esses registros antes, porque apagar o '.
            'banco levaria o extrato junto.',
            implode(' e ', $parts),
        ));
    }
}
