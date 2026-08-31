<?php

declare(strict_types=1);

namespace App\Domain\Import\Actions;

use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Import\DTOs\ImportCommitData;
use App\Domain\Import\DTOs\ImportedRow;
use App\Domain\Import\Enums\ImportTarget;
use App\Domain\Import\Models\ImportBatch;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Grava as linhas confirmadas na tela de conferencia.
 *
 * Nao ha caminho de escrita novo: conta reusa a RecordTransactionAction e
 * cartao reusa a RegisterCardPurchaseAction. Importar precisa produzir
 * exatamente o mesmo registro que digitar na tela produziria — inclusive as
 * parcelas e a fatura do ciclo certo —, senao o extrato passaria a ter dois
 * tipos de lancamento com regras diferentes.
 *
 * Tudo em uma transacao de banco: um arquivo importado pela metade seria pior
 * do que um arquivo nao importado, porque ninguem saberia onde ele parou.
 */
final class CommitStatementImportAction
{
    public function __construct(
        private readonly RecordTransactionAction $recordTransaction,
        private readonly RegisterCardPurchaseAction $registerCardPurchase,
    ) {}

    public function handle(ImportCommitData $data): ImportBatch
    {
        return DB::transaction(function () use ($data): ImportBatch {
            $batch = ImportBatch::query()->create([
                'user_id' => $data->userId,
                'account_id' => $data->accountId,
                'credit_card_id' => $data->creditCardId,
                'filename' => $data->filename,
                'format' => $data->format,
                'imported_count' => count($data->rows),
                'skipped_count' => $data->skippedCount,
                'period_start' => $this->boundary($data->rows, earliest: true)?->toDateString(),
                'period_end' => $this->boundary($data->rows, earliest: false)?->toDateString(),
            ]);

            $ids = [];

            foreach ($data->rows as $row) {
                $ids[] = $this->record($row, $data)->id;
            }

            // Uma atualizacao para o lote todo em vez de um save por linha: o
            // vinculo e a mesma informacao para todas elas.
            Transaction::query()
                ->ownedBy($data->userId)
                ->whereIn('id', $ids)
                ->update(['import_batch_id' => $batch->id]);

            return $batch;
        });
    }

    private function record(ImportedRow $row, ImportCommitData $data): Transaction
    {
        $notes = "Importado de {$data->filename}";

        if ($data->target() === ImportTarget::Cartao) {
            return $this->registerCardPurchase->handle(new CardPurchaseData(
                creditCardId: (int) $data->creditCardId,
                description: $row->description,
                amount: $row->amount,
                purchaseDate: $row->date,
                installments: 1,
                categoryId: $row->categoryId,
                notes: $notes,
            ));
        }

        return $this->recordTransaction->handle(new TransactionData(
            accountId: (int) $data->accountId,
            description: $row->description,
            amount: $row->amount,
            type: $row->direction === MovementDirection::Entrada
                ? TransactionType::Receita
                : TransactionType::Despesa,
            competenceDate: $row->date,
            categoryId: $row->categoryId,
            // O extrato e o registro do que ja aconteceu: nada nele e previsto.
            status: TransactionStatus::Confirmado,
            paidDate: $row->date,
            notes: $notes,
        ));
    }

    /** @param  list<ImportedRow>  $rows */
    private function boundary(array $rows, bool $earliest): ?CarbonImmutable
    {
        $chosen = null;

        foreach ($rows as $row) {
            if ($chosen === null || ($earliest ? $row->date->lessThan($chosen) : $row->date->greaterThan($chosen))) {
                $chosen = $row->date;
            }
        }

        return $chosen;
    }
}
