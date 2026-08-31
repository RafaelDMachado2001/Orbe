<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Ledger\Enums\MovementDirection;
use Carbon\CarbonImmutable;

/** Uma linha que o usuario confirmou na tela de conferencia. */
final readonly class ImportedRow
{
    public function __construct(
        public CarbonImmutable $date,
        public string $description,
        public float $amount,
        public MovementDirection $direction,
        public ?int $categoryId = null,
    ) {
        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor importado precisa ser positivo.');
        }
    }
}
