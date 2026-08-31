<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use Carbon\CarbonImmutable;

final readonly class TransferData
{
    public function __construct(
        public int $fromAccountId,
        public int $toAccountId,
        public float $amount,
        public CarbonImmutable $date,
        public string $description = 'Transferência entre contas',
        public ?string $notes = null,
    ) {
        if ($this->fromAccountId === $this->toAccountId) {
            throw new \InvalidArgumentException('A conta de origem e a de destino precisam ser diferentes.');
        }

        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor da transferencia precisa ser positivo.');
        }
    }
}
