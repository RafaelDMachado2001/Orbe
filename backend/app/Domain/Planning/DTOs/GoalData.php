<?php

declare(strict_types=1);

namespace App\Domain\Planning\DTOs;

/**
 * `initialAmount` vem nulo na edicao: o valor de partida e congelado na
 * criacao, igual ao saldo inicial de uma conta — dali em diante o progresso
 * so anda por aporte, nunca por edicao direta.
 */
final readonly class GoalData
{
    public function __construct(
        public string $name,
        public string $targetAmount,
        public ?string $initialAmount,
        public ?string $deadline,
        public ?int $accountId,
    ) {}
}
