<?php

declare(strict_types=1);

namespace App\Domain\Banking\Enums;

enum AccountType: string
{
    case Corrente = 'corrente';
    case Poupanca = 'poupanca';
    case Investimento = 'investimento';
    case Carteira = 'carteira';

    public function label(): string
    {
        return match ($this) {
            self::Corrente => 'Conta corrente',
            self::Poupanca => 'Poupança',
            self::Investimento => 'Investimento',
            self::Carteira => 'Carteira',
        };
    }
}
