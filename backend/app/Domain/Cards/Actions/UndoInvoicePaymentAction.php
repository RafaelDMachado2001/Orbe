<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Exceptions\InvoiceOperationException;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz o ultimo pagamento lancado numa fatura.
 *
 * Desfaz um por vez, e nao todos de uma vez: uma fatura pode ter recebido
 * pagamentos parciais em datas diferentes, e apagar os tres porque o terceiro
 * foi um engano tiraria dinheiro que de fato saiu da conta.
 *
 * Some o lancamento que debitou a conta (devolvendo o saldo), o valor volta
 * para o saldo devedor da fatura e as parcelas deixam de contar como pagas —
 * o limite do cartao volta a ficar ocupado.
 */
final class UndoInvoicePaymentAction
{
    public function handle(Invoice $invoice, ?CarbonImmutable $today = null): Invoice
    {
        $today ??= CarbonImmutable::now();

        $payment = Transaction::query()
            ->withoutUserScope()
            ->where('paid_invoice_id', $invoice->id)
            ->orderByDesc('paid_date')
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            throw InvoiceOperationException::nothingToUndo();
        }

        return DB::transaction(function () use ($invoice, $payment, $today): Invoice {
            $restored = round((float) $invoice->paid_amount - (float) $payment->amount, 2);
            $payment->delete();

            $paidAmount = max($restored, 0.0);

            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'status' => $this->statusFor($invoice, $paidAmount, $today),
                'paid_at' => null,
            ])->save();

            // A fatura deixou de estar quitada: o limite volta a ficar ocupado.
            $invoice->installments()->withoutUserScope()->update(['is_paid' => false]);

            return $invoice->refresh();
        });
    }

    /**
     * Sem nenhum pagamento, a fatura volta ao estado que o calendario manda:
     * fechada se o dia de fechamento ja passou, aberta se ainda esta correndo.
     * Com pagamento parcial restante, permanece fechada.
     */
    private function statusFor(Invoice $invoice, float $paidAmount, CarbonImmutable $today): InvoiceStatus
    {
        if ($paidAmount > 0.0) {
            return InvoiceStatus::Fechada;
        }

        return $today->startOfDay()->gte($invoice->closing_date)
            ? InvoiceStatus::Fechada
            : InvoiceStatus::Aberta;
    }
}
