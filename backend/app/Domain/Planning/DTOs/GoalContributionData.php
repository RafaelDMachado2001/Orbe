<?php

declare(strict_types=1);

namespace App\Domain\Planning\DTOs;

use Carbon\CarbonImmutable;

final readonly class GoalContributionData
{
    public function __construct(
        public float $amount,
        public CarbonImmutable $contributedAt,
        /** Obrigatorio quando a meta tem conta vinculada: e de onde o dinheiro sai. */
        public ?int $sourceAccountId,
    ) {}
}
