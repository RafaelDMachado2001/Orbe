<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Ledger\Enums\MovementDirection;
use Carbon\CarbonImmutable;

/** O arquivo lido e conferido, pronto para a tela decidir o que gravar. */
final readonly class ImportPreview
{
    /**
     * @param  list<PreviewRow>  $rows
     * @param  list<string>  $headers
     */
    public function __construct(
        public array $rows,
        public ImportFormat $format,
        public ?CsvMapping $mapping = null,
        public array $headers = [],
        public int $columnCount = 0,
        public ?CarbonImmutable $periodStart = null,
        public ?CarbonImmutable $periodEnd = null,
    ) {}

    /**
     * Totais do arquivo inteiro. Somam so o que entraria por padrao — o que ja
     * existe ou o destino recusa nao pode inflar o numero que a tela promete
     * importar.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $income = 0.0;
        $expense = 0.0;
        $selected = 0;
        $duplicates = 0;
        $blocked = 0;

        foreach ($this->rows as $row) {
            if (! $row->isImportable) {
                $blocked++;

                continue;
            }

            if ($row->isDuplicate) {
                $duplicates++;

                continue;
            }

            $selected++;

            if ($row->direction === MovementDirection::Entrada) {
                $income += $row->amount;
            } else {
                $expense += $row->amount;
            }
        }

        return [
            'total' => count($this->rows),
            'selected' => $selected,
            'duplicates' => $duplicates,
            'blocked' => $blocked,
            'income' => round($income, 2),
            'expense' => round($expense, 2),
        ];
    }
}
