<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

/**
 * De qual coluna do CSV sai cada campo do lancamento.
 *
 * O arquivo de banco vem em duas formas: uma coluna de valor com sinal
 * (`-284,90`) ou duas colunas separando debito de credito. As duas cabem aqui:
 * sem `inflowColumn`, `amountColumn` e o valor com sinal; com ele,
 * `amountColumn` passa a ser so a saida e `inflowColumn` a entrada.
 */
final readonly class CsvMapping
{
    public function __construct(
        public int $dateColumn,
        public int $descriptionColumn,
        public int $amountColumn,
        public ?int $inflowColumn = null,
        public string $delimiter = ';',
        public bool $hasHeader = true,
    ) {
        foreach ([$this->dateColumn, $this->descriptionColumn, $this->amountColumn] as $column) {
            if ($column < 0) {
                throw new \InvalidArgumentException('Indice de coluna invalido.');
            }
        }

        if ($this->inflowColumn !== null && $this->inflowColumn < 0) {
            throw new \InvalidArgumentException('Indice de coluna invalido.');
        }

        if ($this->delimiter === '') {
            throw new \InvalidArgumentException('O separador do CSV nao pode ser vazio.');
        }
    }

    /** O arquivo separa debito e credito em duas colunas. */
    public function isSplitAmount(): bool
    {
        return $this->inflowColumn !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date_column' => $this->dateColumn,
            'description_column' => $this->descriptionColumn,
            'amount_column' => $this->amountColumn,
            'inflow_column' => $this->inflowColumn,
            'delimiter' => $this->delimiter,
            'has_header' => $this->hasHeader,
        ];
    }
}
