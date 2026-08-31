<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Ledger\Enums\MovementDirection;
use Carbon\CarbonImmutable;

/**
 * Uma linha do arquivo como a tela de conferencia a mostra: o que foi lido,
 * o palpite de categoria e o motivo de ela vir ou nao marcada.
 */
final readonly class PreviewRow
{
    public function __construct(
        public int $index,
        public CarbonImmutable $date,
        public string $description,
        public float $amount,
        public MovementDirection $direction,
        public ?int $categoryId = null,
        /** Ja existe um lancamento igual: a linha vem desmarcada, mas pode ser importada. */
        public bool $isDuplicate = false,
        /** O destino escolhido nao aceita esta linha: nem marcada ela entra. */
        public bool $isImportable = true,
        public ?string $skipReason = null,
    ) {}

    /** A linha ja nasce marcada quando da para importar e nao parece repetida. */
    public function isSelectedByDefault(): bool
    {
        return $this->isImportable && ! $this->isDuplicate;
    }
}
