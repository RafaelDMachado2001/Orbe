<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum MovementDirection: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
        };
    }

    public function signal(): int
    {
        return match ($this) {
            self::Entrada => 1,
            self::Saida => -1,
        };
    }

    public static function forType(TransactionType $type): self
    {
        return match ($type) {
            TransactionType::Receita => self::Entrada,
            TransactionType::Despesa => self::Saida,
            TransactionType::Transferencia => throw new \LogicException(
                'Transferencia exige direcao explicita: cada lado do par tem a sua.',
            ),
        };
    }
}
