<?php
declare(strict_types=1);
namespace App\Domain\Planning\DTOs;
final readonly class BudgetData
{
    public function __construct(public int $categoryId, public string $referenceMonth, public string $limitAmount) {}
}
