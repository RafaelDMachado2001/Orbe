<?php

declare(strict_types=1);

namespace App\Domain\Import\Actions;

use App\Domain\Import\DTOs\ImportPreview;
use App\Domain\Import\DTOs\ImportSource;
use App\Domain\Import\DTOs\ParsedEntry;
use App\Domain\Import\DTOs\PreviewRow;
use App\Domain\Import\Enums\ImportTarget;
use App\Domain\Import\Queries\ExistingEntriesQuery;
use App\Domain\Import\Support\CategoryGuesser;
use App\Domain\Import\Support\DuplicateFinder;
use App\Domain\Import\Support\StatementReader;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Models\Category;

/**
 * Le o arquivo e devolve o que ele contem — sem gravar nada.
 *
 * A prevalidacao acontece aqui e nao no momento de gravar porque o usuario
 * precisa ver o que vai acontecer antes de acontecer: um extrato de meses
 * importado por engano seria desfeito lancamento a lancamento.
 */
final class PreviewStatementImportAction
{
    public function __construct(
        private readonly StatementReader $reader,
        private readonly ExistingEntriesQuery $existingEntries,
    ) {}

    public function handle(ImportSource $source): ImportPreview
    {
        $statement = $this->reader->read($source->contents, $source->format, $source->mapping);

        $start = $statement->periodStart();
        $end = $statement->periodEnd();

        $duplicates = new DuplicateFinder(
            $start === null || $end === null ? [] : $this->existingEntries->handle($source, $start, $end),
        );

        $guesser = new CategoryGuesser(
            Category::query()->ownedBy($source->userId)->get(),
        );

        $rows = [];

        foreach ($statement->entries as $index => $entry) {
            $rows[] = $this->toRow($index, $entry, $source->target(), $duplicates, $guesser);
        }

        return new ImportPreview(
            rows: $rows,
            format: $source->format,
            mapping: $statement->mapping,
            headers: $statement->headers,
            columnCount: $statement->columns,
            periodStart: $start,
            periodEnd: $end,
        );
    }

    private function toRow(
        int $index,
        ParsedEntry $entry,
        ImportTarget $target,
        DuplicateFinder $duplicates,
        CategoryGuesser $guesser,
    ): PreviewRow {
        $blockedReason = $this->blockedReason($entry, $target);

        return new PreviewRow(
            index: $index,
            date: $entry->date,
            description: $entry->description,
            amount: $entry->amount,
            direction: $entry->direction,
            categoryId: $guesser->guess($entry->description, $entry->direction),
            // A checagem consome a correspondencia, entao roda tambem nas
            // linhas bloqueadas — senao uma linha recusada devolveria a vaga
            // para a proxima igual, marcando repetida a que nao e.
            isDuplicate: $duplicates->consume($entry),
            isImportable: $blockedReason === null,
            skipReason: $blockedReason,
        );
    }

    /**
     * Credito na fatura (estorno, pagamento da fatura anterior, cashback) nao
     * e uma compra: gravar isso como compra de valor negativo nao existe no
     * modelo, e como compra positiva inflaria a fatura. Fica de fora com o
     * motivo a vista, apontando a tela que sabe registrar esse dinheiro.
     */
    private function blockedReason(ParsedEntry $entry, ImportTarget $target): ?string
    {
        if ($target === ImportTarget::Cartao && $entry->direction === MovementDirection::Entrada) {
            return 'Crédito na fatura — registre o pagamento pela tela de Cartões.';
        }

        return null;
    }
}
