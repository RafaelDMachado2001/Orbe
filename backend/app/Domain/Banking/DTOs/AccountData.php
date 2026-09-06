<?php

declare(strict_types=1);

namespace App\Domain\Banking\DTOs;

use App\Domain\Banking\Enums\AccountType;

/**
 * Os campos de uma conta como o formulario os envia.
 *
 * `initialBalance` vem nulo na edicao: o saldo inicial e o ponto de partida do
 * historico e nao se reescreve depois da criacao. Diferenca com o extrato do
 * banco se resolve pelo ajuste de saldo, que deixa um lancamento explicando a
 * correcao.
 */
final readonly class AccountData
{
    public function __construct(
        public int $bankId,
        public string $nickname,
        public AccountType $type,
        public ?float $initialBalance = null,
    ) {}
}
