<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum BankKind: string
{
    case Digital = 'digital';
    case Tradicional = 'tradicional';
    case Corretora = 'corretora';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Banco digital',
            self::Tradicional => 'Banco tradicional',
            self::Corretora => 'Corretora',
        };
    }
}
