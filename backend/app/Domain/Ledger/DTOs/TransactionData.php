<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;

final readonly class TransactionData
{
    public function __construct(
        public int $accountId,
        public string $description,
        public float $amount,
        public TransactionType $type,
        public CarbonImmutable $competenceDate,
        public ?int $categoryId = null,
        public TransactionStatus $status = TransactionStatus::Confirmado,
        public ?PaymentMethod $method = null,
        public ?CarbonImmutable $paidDate = null,
        public ?string $notes = null,
        public ?int $recurrenceId = null,
    ) {
        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor do lancamento precisa ser positivo.');
        }

        if ($this->type === TransactionType::Transferencia) {
            throw new \InvalidArgumentException(
                'Transferencias sao criadas pela TransferBetweenAccountsAction.',
            );
        }
    }
}
