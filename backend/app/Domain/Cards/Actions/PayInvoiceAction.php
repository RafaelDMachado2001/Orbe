<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Exceptions\InvoiceOperationException;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pagar a fatura debita a conta e baixa a fatura.
 *
 * O lancamento gerado e do tipo transferencia: o dinheiro sai da conta, mas as
 * compras do cartao ja foram contabilizadas como despesa no mes de competencia
 * de cada parcela. Contabilizar o pagamento como despesa dobraria o gasto.
 */
final class PayInvoiceAction
{
    public function handle(
        Invoice $invoice,
        Account $account,
        ?float $amount = null,
        ?CarbonImmutable $paidAt = null,
    ): Transaction {
        if ($invoice->status === InvoiceStatus::Paga) {
            throw InvoiceOperationException::alreadyPaid();
        }

        $remaining = $invoice->remaining();
        $value = round($amount ?? $remaining, 2);

        if ($value <= 0.0) {
            throw InvoiceOperationException::nonPositiveAmount();
        }

        if ($value > $remaining) {
            throw InvoiceOperationException::exceedsRemaining();
        }

        $paidAt ??= CarbonImmutable::now();

        $invoice->loadMissing('creditCard');

        return DB::transaction(function () use ($invoice, $account, $value, $remaining, $paidAt): Transaction {
            $payment = Transaction::query()->create([
                'user_id' => $invoice->user_id,
                'account_id' => $account->id,
                'category_id' => null,
                'paid_invoice_id' => $invoice->id,
                'description' => "Pagamento fatura {$invoice->creditCard->nickname}",
                'amount' => $value,
                'type' => TransactionType::Transferencia,
                'direction' => MovementDirection::Saida,
                'status' => TransactionStatus::Confirmado,
                'method' => PaymentMethod::Boleto,
                'competence_date' => $paidAt->toDateString(),
                'paid_date' => $paidAt->toDateString(),
                'is_installment_parent' => false,
            ]);

            $isSettled = round($value, 2) >= round($remaining, 2);

            $invoice->forceFill([
                'paid_amount' => round((float) $invoice->paid_amount + $value, 2),
                'status' => $isSettled ? InvoiceStatus::Paga : InvoiceStatus::Fechada,
                'paid_at' => $isSettled ? $paidAt : null,
            ])->save();

            if ($isSettled) {
                $invoice->installments()->withoutUserScope()->update(['is_paid' => true]);
            }

            return $payment;
        });
    }
}
