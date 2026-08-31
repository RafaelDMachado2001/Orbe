<?php

declare(strict_types=1);

namespace App\Domain\Cards\Queries;

use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;

/**
 * As faturas de um cartao, da mais recente para a mais antiga.
 *
 * A tela usa esta lista como linha do tempo: cada mes vira um item clicavel
 * que abre o detalhe. Sao poucas dezenas de linhas por cartao, entao a lista
 * vem inteira — paginar meses obrigaria a rolar para achar dezembro.
 *
 * @phpstan-type InvoiceRow array{
 *     id: int,
 *     reference_month: string,
 *     total: float,
 *     paid_amount: float,
 *     remaining: float,
 *     status: string,
 *     status_label: string,
 *     closing_date: string,
 *     due_date: string,
 *     items_count: int
 * }
 */
final class InvoiceHistoryQuery
{
    /** @return list<InvoiceRow> */
    public function handle(CreditCard $card): array
    {
        return Invoice::query()
            ->withoutUserScope()
            ->where('credit_card_id', $card->id)
            ->withCount('installments')
            ->orderByDesc('reference_month')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'reference_month' => $invoice->reference_month->format('Y-m'),
                'total' => round((float) $invoice->total, 2),
                'paid_amount' => round((float) $invoice->paid_amount, 2),
                'remaining' => $invoice->remaining(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'closing_date' => $invoice->closing_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'items_count' => (int) $invoice->installments_count,
            ])
            ->all();
    }
}
