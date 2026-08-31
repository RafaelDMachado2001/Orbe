<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * Uma regra de repeticao: receita ou despesa fixa.
 *
 * A regra sai de uma conta ou de um cartao, nunca dos dois: o dinheiro tem um
 * caminho so, e uma despesa fixa no credito vira compra na fatura, nao debito
 * em conta.
 */
final readonly class RecurrenceData
{
    public function __construct(
        public string $description,
        public float $amount,
        public TransactionType $type,
        public RecurrenceFrequency $frequency,
        public CarbonImmutable $startsOn,
        public int $interval = 1,
        public ?int $dayOfMonth = null,
        public ?CarbonImmutable $endsOn = null,
        public ?int $categoryId = null,
        public ?int $accountId = null,
        public ?int $creditCardId = null,
        public ?PaymentMethod $method = null,
    ) {
        if ($this->amount <= 0.0) {
            throw new \InvalidArgumentException('O valor da regra precisa ser positivo.');
        }

        if ($this->type === TransactionType::Transferencia) {
            throw new \InvalidArgumentException('Transferencia nao vira despesa fixa.');
        }

        if ($this->interval < 1) {
            throw new \InvalidArgumentException('O intervalo precisa ser de ao menos um.');
        }

        if ($this->accountId === null && $this->creditCardId === null) {
            throw new \InvalidArgumentException('A regra precisa de uma conta ou de um cartao.');
        }

        if ($this->accountId !== null && $this->creditCardId !== null) {
            throw new \InvalidArgumentException('A regra sai da conta ou do cartao, nunca dos dois.');
        }

        if ($this->creditCardId !== null && $this->type === TransactionType::Receita) {
            throw new \InvalidArgumentException('Cartao de credito nao recebe receita.');
        }

        if ($this->endsOn !== null && $this->endsOn->lt($this->startsOn)) {
            throw new \InvalidArgumentException('O fim da vigencia nao pode ser anterior ao inicio.');
        }

        // O dia 29, 30 ou 31 nao existe em todo mes; a regra mensal ficaria
        // pulando meses curtos.
        if ($this->dayOfMonth !== null && ($this->dayOfMonth < 1 || $this->dayOfMonth > 28)) {
            throw new \InvalidArgumentException('O dia do mes precisa ficar entre 1 e 28.');
        }
    }

    public function isOnCard(): bool
    {
        return $this->creditCardId !== null;
    }
}
