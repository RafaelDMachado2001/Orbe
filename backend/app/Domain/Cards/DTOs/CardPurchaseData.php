<?php

declare(strict_types=1);

namespace App\Domain\Cards\DTOs;

use App\Domain\Ledger\Enums\TransactionStatus;
use Carbon\CarbonImmutable;

final readonly class CardPurchaseData
{
    public function __construct(
        public int $creditCardId,
        public string $description,
        public float $amount,
        public CarbonImmutable $purchaseDate,
        public int $installments = 1,
        public ?int $categoryId = null,
        public TransactionStatus $status = TransactionStatus::Confirmado,
        public ?string $notes = null,
        // Preenchido quando a compra nasce de uma despesa fixa no cartao; e o
        // que permite reconhecer depois que aquele mes ja foi lancado.
        public ?int $recurrenceId = null,
    ) {
        if ($this->installments < 1) {
            throw new \InvalidArgumentException('A compra precisa de ao menos uma parcela.');
        }

        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor da compra precisa ser positivo.');
        }
    }
}
