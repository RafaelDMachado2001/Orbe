<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * A categoria existe e pertence ao usuario, mas nao pode sumir do jeito que
 * foi pedido. Vira 422 na borda HTTP, como toda DomainRuleException.
 */
final class CategoryNotRemovableException extends DomainRuleException
{
    public static function isSystem(): self
    {
        return new self(
            'Categorias do sistema não podem ser excluídas. Renomeie-a se o nome não serve.',
        );
    }

    public static function needsDestination(int $entries): self
    {
        return new self(
            "Esta categoria tem {$entries} registro(s) classificado(s). Escolha para qual categoria movê-los.",
        );
    }

    public static function destinationIsSelf(): self
    {
        return new self('Escolha uma categoria diferente da que está sendo excluída.');
    }

    public static function destinationTypeMismatch(): self
    {
        return new self('A categoria de destino precisa ser do mesmo tipo.');
    }
}
