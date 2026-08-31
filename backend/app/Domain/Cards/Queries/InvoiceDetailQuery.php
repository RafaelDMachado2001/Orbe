<?php

declare(strict_types=1);

namespace App\Domain\Cards\Queries;

use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\TransactionStatus;
use Illuminate\Support\Facades\DB;

/**
 * O que compoe uma fatura: cada parcela alocada nela, com a compra que a
 * originou.
 *
 * O valor da linha e o da parcela, nao o da compra — 3/10 de um notebook de
 * R$ 3.000 aparece como R$ 300. Compra cancelada continua listada, marcada,
 * porque some do total mas nao do historico: sumir sem rastro faria a pessoa
 * procurar um lancamento que ela lembra ter feito.
 *
 * @phpstan-type InvoiceItem array{
 *     id: int,
 *     transaction_id: int,
 *     description: string,
 *     amount: float,
 *     purchase_date: string,
 *     installment: string,
 *     installment_number: int,
 *     installment_total: int,
 *     status: string,
 *     is_cancelled: bool,
 *     is_paid: bool,
 *     category: array{id: int, name: string, color: string}|null
 * }
 */
final class InvoiceDetailQuery
{
    /** @return list<InvoiceItem> */
    public function handle(Invoice $invoice): array
    {
        $rows = DB::table('installments')
            ->selectRaw(<<<'SQL'
                installments.id                    AS id,
                       installments.amount         AS amount,
                       installments.number         AS number,
                       installments.total          AS total,
                       installments.is_paid        AS is_paid,
                       transactions.id             AS transaction_id,
                       transactions.description    AS description,
                       transactions.status         AS status,
                       transactions.competence_date AS purchase_date,
                       categories.id               AS category_id,
                       categories.name             AS category_name,
                       categories.color            AS category_color
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('installments.invoice_id', $invoice->id)
            ->orderBy('transactions.competence_date')
            ->orderBy('installments.id')
            ->get();

        return $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'transaction_id' => (int) $row->transaction_id,
            'description' => (string) $row->description,
            'amount' => round((float) $row->amount, 2),
            'purchase_date' => (string) $row->purchase_date,
            'installment' => "{$row->number}/{$row->total}",
            'installment_number' => (int) $row->number,
            'installment_total' => (int) $row->total,
            'status' => (string) $row->status,
            'is_cancelled' => $row->status === TransactionStatus::Cancelado->value,
            'is_paid' => $this->boolean($row->is_paid),
            'category' => $row->category_id === null ? null : [
                'id' => (int) $row->category_id,
                'name' => (string) $row->category_name,
                'color' => (string) ($row->category_color ?? '#4A525E'),
            ],
        ])->all();
    }

    /** @return list<array<string, mixed>> pagamentos ja lancados nesta fatura */
    public function payments(Invoice $invoice): array
    {
        return DB::table('transactions')
            ->selectRaw(<<<'SQL'
                transactions.id                    AS id,
                       transactions.amount         AS amount,
                       transactions.paid_date      AS paid_date,
                       accounts.nickname           AS account
                SQL)
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.paid_invoice_id', $invoice->id)
            ->orderByDesc('transactions.paid_date')
            ->orderByDesc('transactions.id')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'amount' => round((float) $row->amount, 2),
                'paid_date' => (string) $row->paid_date,
                'account' => (string) ($row->account ?? 'Conta'),
            ])
            ->all();
    }

    /** O driver do Postgres ora devolve bool, ora 't'/'f'. */
    private function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
