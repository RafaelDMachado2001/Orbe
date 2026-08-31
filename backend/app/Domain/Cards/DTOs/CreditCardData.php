<?php

declare(strict_types=1);

namespace App\Domain\Cards\DTOs;

use App\Domain\Cards\Enums\CardBrand;

final readonly class CreditCardData
{
    public function __construct(
        public int $bankId,
        public string $nickname,
        public CardBrand $brand,
        public string $lastFour,
        public float $limitAmount,
        public int $closingDay,
        public int $dueDay,
        public string $color = '#A07CFF',
        public ?int $paymentAccountId = null,
    ) {
        if ($this->limitAmount < 0.0) {
            throw new \InvalidArgumentException('O limite do cartao nao pode ser negativo.');
        }

        // O dia 29, 30 ou 31 nao existe em todo mes; o cartao ajustaria para o
        // ultimo dia e o ciclo ficaria irregular. 28 e o maior dia seguro.
        foreach (['fechamento' => $this->closingDay, 'vencimento' => $this->dueDay] as $label => $day) {
            if ($day < 1 || $day > 28) {
                throw new \InvalidArgumentException("O dia de {$label} precisa ficar entre 1 e 28.");
            }
        }

        if (! preg_match('/^\d{4}$/', $this->lastFour)) {
            throw new \InvalidArgumentException('Os ultimos digitos precisam ser exatamente quatro numeros.');
        }
    }
}
