<?php

declare(strict_types=1);

namespace App\Domain\Banking\DTOs;

use Carbon\CarbonImmutable;

/**
 * O que a pessoa informa ao conciliar uma conta: o saldo que o banco mostra.
 *
 * A diferenca nao vem daqui — quem a calcula e a Action, contra o saldo atual
 * da conta no momento do ajuste. Se o valor viesse pronto do formulario, dois
 * ajustes disparados em sequencia dobrariam a correcao.
 */
final readonly class BalanceAdjustmentData
{
    public function __construct(
        public float $targetBalance,
        public CarbonImmutable $date,
        public ?string $notes = null,
    ) {}
}
