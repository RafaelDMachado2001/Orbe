<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Distribui uma compra de cartao nas faturas, do zero.
 *
 * Regra unica de parcelamento do cartao: a compra e sempre representada por N
 * parcelas (N = 1 para uma compra a vista), a primeira cai na primeira fatura
 * aberta a partir do mes devolvido por invoiceMonthFor() e cada seguinte
 * avanca um mes. O residuo da divisao vai
 * para as primeiras parcelas, entao 3x de R$ 100,00 nunca somam R$ 99,99.
 *
 * A action apaga as parcelas atuais antes de recriar, o que a torna igualmente
 * valida para registrar e para editar uma compra. As faturas que perdem
 * parcelas sao recalculadas junto das que recebem — do contrario, editar uma
 * compra para menos parcelas deixaria a fatura antiga inflada.
 */
final class SyncInstallmentsAction
{
    public function __construct(
        private readonly EnsureInvoiceAction $ensureInvoice,
        private readonly RecalculateInvoiceTotalAction $recalculateTotal,
    ) {}

    public function handle(
        Transaction $purchase,
        CreditCard $card,
        float $amount,
        CarbonImmutable $purchaseDate,
        int $installments,
    ): void {
        $touchedIds = $this->currentInvoiceIds($purchase);

        Installment::query()
            ->withoutUserScope()
            ->where('transaction_id', $purchase->id)
            ->delete();

        $amounts = Money::split(Money::toCents($amount), $installments);

        // Onde a primeira parcela cai leva em conta o dia de fechamento do
        // cartao e tambem as faturas fechadas antes da hora; da segunda em
        // diante e sempre o mes seguinte, para nao embaralhar o "3 de 12".
        $firstMonth = $this->ensureInvoice
            ->handleOpen($card, $card->invoiceMonthFor($purchaseDate))
            ->reference_month;

        foreach ($amounts as $index => $cents) {
            $invoice = $this->ensureInvoice->handle($card, $firstMonth->addMonthsNoOverflow($index));
            $touchedIds[] = $invoice->id;

            Installment::query()->create([
                'user_id' => $card->user_id,
                'transaction_id' => $purchase->id,
                'invoice_id' => $invoice->id,
                'number' => $index + 1,
                'total' => $installments,
                'amount' => Money::toReais($cents),
                'competence_date' => $this->competenceDay(
                    $firstMonth->addMonthsNoOverflow($index),
                    $purchaseDate->day,
                )->toDateString(),
                'is_paid' => false,
            ]);
        }

        $this->recalculate($touchedIds);
    }

    /**
     * Recalcula as faturas que hoje contem parcelas desta compra, sem mexer nas
     * parcelas. Usado ao cancelar ou reativar uma compra: as parcelas
     * permanecem, mas deixam de contar no total da fatura.
     */
    public function recalculateFor(Transaction $purchase): void
    {
        $this->recalculate($this->currentInvoiceIds($purchase));
    }

    /**
     * Remove as parcelas da compra e recalcula as faturas que as continham.
     *
     * Chamada antes de excluir a compra: o cascade do banco tambem apagaria as
     * parcelas, mas ai o vinculo com a fatura ja teria se perdido e o total
     * ficaria inflado.
     */
    public function detach(Transaction $purchase): void
    {
        $touchedIds = $this->currentInvoiceIds($purchase);

        Installment::query()
            ->withoutUserScope()
            ->where('transaction_id', $purchase->id)
            ->delete();

        $this->recalculate($touchedIds);
    }

    /** @return list<int> */
    private function currentInvoiceIds(Transaction $purchase): array
    {
        return Installment::query()
            ->withoutUserScope()
            ->where('transaction_id', $purchase->id)
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param  list<int>  $invoiceIds */
    private function recalculate(array $invoiceIds): void
    {
        $unique = array_values(array_unique($invoiceIds));

        if ($unique === []) {
            return;
        }

        Invoice::query()
            ->withoutUserScope()
            ->whereIn('id', $unique)
            ->get()
            ->each(fn (Invoice $invoice) => $this->recalculateTotal->handle($invoice));
    }

    /**
     * A parcela mantem o dia da compra dentro do mes da fatura, ajustado para
     * meses curtos (uma compra dia 31 vira dia 28 em fevereiro).
     */
    private function competenceDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $start = $month->startOfMonth();

        return $start->setDay(min($day, $start->daysInMonth));
    }
}
