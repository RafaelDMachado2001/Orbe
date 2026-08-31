<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum CategoryType: string
{
    case Receita = 'receita';
    case Despesa = 'despesa';

    public function label(): string
    {
        return match ($this) {
            self::Receita => 'Receita',
            self::Despesa => 'Despesa',
        };
    }
}
