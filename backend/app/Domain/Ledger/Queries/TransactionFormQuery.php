<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Cards\Models\Installment;
use App\Domain\Ledger\Enums\AmountMode;
use App\Domain\Ledger\Enums\EntryKind;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Monta o registro como o formulario de edicao precisa ve-lo.
 *
 * A linha da lista nao serve: ela mostra uma parcela, enquanto o formulario
 * edita a compra inteira e precisa saber em quantas vezes ela foi feita. Uma
 * transferencia, do mesmo modo, aparece na lista como dois lados e no
 * formulario como origem e destino.
 *
 * Um parcelamento em conta volta pelo total somado das suas parcelas, e nao
 * pelo valor da linha clicada: o residuo de centavos mora na primeira parcela,
 * entao reabrir por ela e multiplicar por N devolveria um total diferente do
 * que foi gravado.
 *
 * @phpstan-type FormRecord array{
 *     id: int,
 *     kind: string,
 *     description: string,
 *     amount: float,
 *     competence_date: string,
 *     status: string,
 *     method: string|null,
 *     notes: string|null,
 *     category_id: int|null,
 *     account_id: int|null,
 *     credit_card_id: int|null,
 *     installments: int|null,
 *     amount_mode: string,
 *     installment_number: int|null,
 *     installment_total: int|null,
 *     lender: string|null,
 *     from_account_id: int|null,
 *     to_account_id: int|null,
 *     is_editable: bool,
 *     lock_reason: string|null
 * }
 */
final class TransactionFormQuery
{
    /** @return FormRecord */
    public function handle(Transaction $transaction): array
    {
        $kind = EntryKind::forTransaction(
            $transaction->type,
            $transaction->isCardPurchase(),
            $transaction->is_loan,
        );

        $lockReason = $this->lockReason($transaction);
        [$fromAccountId, $toAccountId] = $this->transferAccounts($transaction);
        $plan = $this->plan($transaction);

        return [
            'id' => $transaction->id,
            'kind' => $kind->value,
            'description' => $transaction->description,
            'amount' => $plan['amount'] ?? round((float) $transaction->amount, 2),
            'competence_date' => ($plan['first_date'] ?? $transaction->competence_date)->toDateString(),
            'status' => $transaction->status->value,
            'method' => $transaction->method?->value,
            'notes' => $transaction->notes,
            'category_id' => $transaction->category_id,
            'account_id' => $kind === EntryKind::Transferencia ? null : $transaction->account_id,
            'credit_card_id' => $transaction->credit_card_id,
            'installments' => match (true) {
                $kind === EntryKind::Cartao => $this->installmentCount($transaction),
                $plan !== null => $plan['count'],
                $kind->acceptsInstallmentPlan() => 1,
                default => null,
            },
            'amount_mode' => AmountMode::Total->value,
            'installment_number' => $transaction->installment_number,
            'installment_total' => $transaction->installment_total,
            'lender' => $transaction->lender,
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'is_editable' => $lockReason === null,
            'lock_reason' => $lockReason,
        ];
    }

    /**
     * Por que a tela deve abrir o registro somente para leitura. Igual as
     * guardas das Actions — aqui so para que a interface avise antes, em vez
     * de deixar o usuario preencher e tomar um 422.
     */
    private function lockReason(Transaction $transaction): ?string
    {
        if ($transaction->isInvoicePayment()) {
            return 'Pagamento de fatura. Ajuste pela tela de Cartões.';
        }

        if ($transaction->isCardPurchase() && $transaction->hasPaidInstallments()) {
            return 'Compra com parcela em fatura já paga.';
        }

        return null;
    }

    /**
     * O plano de parcelas ao qual este lancamento pertence, somado.
     *
     * @return array{count: int, amount: float, first_date: CarbonImmutable}|null
     */
    private function plan(Transaction $transaction): ?array
    {
        if (! $transaction->isInstallmentPlan()) {
            return null;
        }

        $rows = $transaction->installmentPlan()->get();
        $first = $rows->first();

        if ($first === null) {
            return null;
        }

        return [
            'count' => $rows->count(),
            'amount' => Money::sum($rows->pluck('amount')),
            'first_date' => $first->competence_date,
        ];
    }

    private function installmentCount(Transaction $transaction): int
    {
        return max(1, Installment::query()
            ->withoutUserScope()
            ->where('transaction_id', $transaction->id)
            ->count());
    }

    /** @return array{0: int|null, 1: int|null} */
    private function transferAccounts(Transaction $transaction): array
    {
        if (! $transaction->isTransfer() || $transaction->transfer_pair_id === null) {
            return [null, null];
        }

        $pair = Transaction::query()->find($transaction->transfer_pair_id);

        if ($pair === null) {
            return [null, null];
        }

        return $transaction->direction === MovementDirection::Saida
            ? [$transaction->account_id, $pair->account_id]
            : [$pair->account_id, $transaction->account_id];
    }
}
