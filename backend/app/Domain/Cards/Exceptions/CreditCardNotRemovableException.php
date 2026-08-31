<?php

declare(strict_types=1);

namespace App\Domain\Cards\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * Excluir um cartao levaria em cascata compras, parcelas e faturas — e o
 * extrato perderia meses de historico sem aviso. Cartao com movimento se
 * arquiva; excluir fica so para o que nunca foi usado.
 */
final class CreditCardNotRemovableException extends DomainRuleException
{
    public static function hasMovements(int $purchases): self
    {
        $label = $purchases === 1 ? 'uma compra' : "{$purchases} compras";

        return new self(
            "Este cartão tem {$label} no histórico. Arquive-o em vez de excluir, ".
            'para não apagar os lançamentos já registrados.',
        );
    }
}
