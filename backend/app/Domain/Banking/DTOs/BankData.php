<?php

declare(strict_types=1);

namespace App\Domain\Banking\DTOs;

use App\Domain\Banking\Enums\BankKind;

final readonly class BankData
{
    public function __construct(
        public string $name,
        public string $color,
        public BankKind $kind,
    ) {}
}
