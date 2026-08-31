<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use Carbon\CarbonImmutable;

/**
 * O arquivo inteiro depois de lido: as linhas e o que o cabecalho contava
 * sobre elas.
 */
final readonly class ParsedStatement
{
    /**
     * @param  list<ParsedEntry>  $entries
     * @param  list<string>  $headers  Cabecalho do CSV, para a tela montar o mapeamento.
     */
    public function __construct(
        public array $entries,
        public ?CsvMapping $mapping = null,
        public array $headers = [],
        /** Quantas colunas o arquivo tem, para a tela montar os seletores. */
        public int $columns = 0,
    ) {}

    public function periodStart(): ?CarbonImmutable
    {
        return $this->boundary(fn (CarbonImmutable $a, CarbonImmutable $b): bool => $a->lessThan($b));
    }

    public function periodEnd(): ?CarbonImmutable
    {
        return $this->boundary(fn (CarbonImmutable $a, CarbonImmutable $b): bool => $a->greaterThan($b));
    }

    private function boundary(callable $wins): ?CarbonImmutable
    {
        $chosen = null;

        foreach ($this->entries as $entry) {
            if ($chosen === null || $wins($entry->date, $chosen)) {
                $chosen = $entry->date;
            }
        }

        return $chosen;
    }
}
