<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use Carbon\CarbonImmutable;

/**
 * Uma despesa em conta dividida em N meses — parcelamento comum ou emprestimo.
 *
 * O valor que viaja aqui e sempre o total do plano, ja convertido: quem
 * digitou a prestacao no formulario tem a multiplicacao feita na fronteira,
 * pela AmountMode, para que o dominio conheca um significado so.
 */
final readonly class InstallmentPlanData
{
    public const MAX_INSTALLMENTS = 360;

    public function __construct(
        public int $accountId,
        public string $description,
        public float $totalAmount,
        public int $installments,
        public CarbonImmutable $firstDate,
        public ?int $categoryId = null,
        public TransactionStatus $status = TransactionStatus::Confirmado,
        public ?PaymentMethod $method = null,
        public ?string $notes = null,
        public bool $isLoan = false,
        public ?string $lender = null,
        public ?int $recurrenceId = null,
    ) {
        if ($this->installments < 1 || $this->installments > self::MAX_INSTALLMENTS) {
            throw new \InvalidArgumentException(
                'O parcelamento aceita de 1 a '.self::MAX_INSTALLMENTS.' parcelas.',
            );
        }

        if ($this->totalAmount <= 0.0) {
            throw new \InvalidArgumentException('O valor do parcelamento precisa ser positivo.');
        }

        if ($this->isLoan && ($this->lender === null || trim($this->lender) === '')) {
            throw new \InvalidArgumentException('Um emprestimo precisa do credor.');
        }
    }

    /** Plano de uma parcela so e uma despesa comum: nao precisa de grupo. */
    public function isSplit(): bool
    {
        return $this->installments > 1;
    }
}
