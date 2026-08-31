<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\TransactionStatus;

/**
 * O total da fatura e sempre a soma das parcelas alocadas nela — nunca um
 * acumulador incrementado a cada compra, que sairia de sincronia.
 *
 * Parcelas de uma compra cancelada ficam de fora: cancelar a compra devolve o
 * valor ao limite e tira a linha da fatura.
 */
final class RecalculateInvoiceTotalAction
{
    public function handle(Invoice $invoice): Invoice
    {
        $total = Installment::query()
            ->withoutUserScope()
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->where('installments.invoice_id', $invoice->id)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->sum('installments.amount');

        $invoice->forceFill(['total' => round((float) $total, 2)])->save();

        return $invoice;
    }
}
