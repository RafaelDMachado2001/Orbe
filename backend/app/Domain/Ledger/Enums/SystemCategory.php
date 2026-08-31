<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * Categorias que o proprio sistema precisa localizar, independentemente do
 * nome que o usuario deu a elas.
 */
enum SystemCategory: string
{
    case Emprestimos = 'emprestimos';

    public function defaultName(): string
    {
        return match ($this) {
            self::Emprestimos => 'Empréstimos e financiamentos',
        };
    }
}
