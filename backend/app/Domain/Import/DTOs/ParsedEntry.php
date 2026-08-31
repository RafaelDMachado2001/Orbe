<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Ledger\Enums\MovementDirection;
use Carbon\CarbonImmutable;

/**
 * Uma linha do arquivo, ja normalizada e independente do formato de origem.
 *
 * O valor e sempre positivo: o sinal do extrato virou `direction`, que e como
 * o resto do dominio fala de dinheiro entrando e saindo.
 */
final readonly class ParsedEntry
{
    public function __construct(
        public CarbonImmutable $date,
        public string $description,
        public float $amount,
        public MovementDirection $direction,
        /** Identificador da transacao no banco (FITID do OFX), quando existe. */
        public ?string $externalId = null,
    ) {
        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor lido do arquivo precisa ser positivo.');
        }

        if (trim($this->description) === '') {
            throw new \InvalidArgumentException('A linha precisa de uma descricao.');
        }
    }
}
