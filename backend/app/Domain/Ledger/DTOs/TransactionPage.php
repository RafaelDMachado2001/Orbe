<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

/**
 * Uma pagina do extrato somada aos totais do recorte inteiro.
 *
 * Os totais nao sao da pagina: quem olha "entradas do periodo" quer o periodo,
 * nao os 25 primeiros. Por isso eles vem de uma agregacao propria no banco, e
 * a tela nunca soma linha por linha.
 */
final readonly class TransactionPage
{
    /** @param  list<array<string, mixed>>  $rows */
    public function __construct(
        public array $rows,
        public float $income,
        public float $expense,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public static function empty(int $page, int $perPage): self
    {
        return new self(rows: [], income: 0.0, expense: 0.0, total: 0, page: $page, perPage: $perPage);
    }

    /** Resultado do periodo: entradas menos saidas, transferencias de fora. */
    public function net(): float
    {
        return round($this->income - $this->expense, 2);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage();
    }
}
