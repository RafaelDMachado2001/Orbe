<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\CategoryType;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public CategoryType $type,
        public string $color,
        public ?string $icon = null,
        public ?int $parentId = null,
    ) {}
}
