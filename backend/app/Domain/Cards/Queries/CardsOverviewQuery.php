<?php

declare(strict_types=1);

namespace App\Domain\Cards\Queries;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Panorama dos cartoes: fatura do mes, limite usado e disponivel.
 *
 * O limite usado e a soma das parcelas ainda nao pagas — e o limite volta a
 * ficar livre conforme as faturas sao quitadas.
 *
 * @phpstan-type CardOverview array{
 *     id: int,
 *     nickname: string,
 *     brand: string,
 *     last_four: string,
 *     color: string,
 *     bank: string,
 *     limit_amount: float,
 *     used_amount: float,
 *     available_amount: float,
 *     usage_percent: float,
 *     is_active: bool,
 *     closing_day: int,
 *     due_day: int,
 *     payment_account: array{id: int, nickname: string}|null,
 *     current_invoice: array<string, mixed>|null
 * }
 */
final class CardsOverviewQuery
{
    /**
     * A Visao geral so quer os cartoes em uso; a tela de Cartoes tambem
     * precisa dos arquivados, para poder reativa-los.
     *
     * @return list<CardOverview>
     */
    public function handle(int $userId, CarbonImmutable $month, bool $includeArchived = false): array
    {
        $cards = CreditCard::query()
            ->ownedBy($userId)
            ->with(['bank', 'paymentAccount'])
            ->when(! $includeArchived, fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        if ($cards->isEmpty()) {
            return [];
        }

        $cardIds = $cards->pluck('id')->all();
        $usedByCard = $this->usedLimitByCard($userId, $cardIds);

        // Ancora de "hoje" dentro do mes consultado: no mes corrente e a data
        // real, em meses passados e o ultimo dia daquele mes.
        $anchor = $month->isSameMonth(CarbonImmutable::now())
            ? CarbonImmutable::now()
            : $month->endOfMonth();

        $outstandingByCard = $this->oldestOutstandingInvoiceByCard($userId, $cardIds, $anchor);

        return $cards->map(function (CreditCard $card) use ($usedByCard, $outstandingByCard, $anchor): array {
            $limit = (float) $card->limit_amount;
            $used = round($usedByCard[$card->id] ?? 0.0, 2);
            $invoice = $outstandingByCard[$card->id] ?? null;

            return [
                'id' => $card->id,
                'nickname' => $card->nickname,
                'brand' => $card->brand->value,
                'last_four' => $card->last_four,
                'color' => $card->color,
                'bank' => $card->bank->name,
                'limit_amount' => round($limit, 2),
                'used_amount' => $used,
                'available_amount' => round(max($limit - $used, 0.0), 2),
                'usage_percent' => $limit > 0.0 ? round(min(($used / $limit) * 100, 100), 1) : 0.0,
                'is_active' => $card->is_active,
                'closing_day' => $card->closing_day,
                'due_day' => $card->due_day,
                'payment_account' => $card->paymentAccount === null ? null : [
                    'id' => $card->paymentAccount->id,
                    'nickname' => $card->paymentAccount->nickname,
                ],
                'current_invoice' => $this->presentInvoice($card, $invoice, $anchor),
            ];
        })->all();
    }

    /**
     * Fatura exibida no cartao: a mais antiga ainda em aberto — e ela que o
     * usuario precisa pagar. Quando nao ha nenhuma, mostramos o ciclo que
     * esta correndo agora, ainda sem lancamentos.
     *
     * @return array<string, mixed>
     */
    private function presentInvoice(CreditCard $card, ?Invoice $invoice, CarbonImmutable $anchor): array
    {
        if ($invoice === null) {
            $reference = $card->invoiceMonthFor($anchor);
            $closing = $card->closingDateFor($reference);
            $due = $card->dueDateFor($reference);

            return [
                'id' => null,
                'reference_month' => $reference->format('Y-m'),
                'total' => 0.0,
                'paid_amount' => 0.0,
                'remaining' => 0.0,
                'status' => InvoiceStatus::Aberta->value,
                'status_label' => InvoiceStatus::Aberta->label(),
                'closing_date' => $closing->toDateString(),
                'due_date' => $due->toDateString(),
                'days_to_close' => (int) max($anchor->startOfDay()->diffInDays($closing, false), 0),
                'days_to_due' => (int) max($anchor->startOfDay()->diffInDays($due, false), 0),
            ];
        }

        return [
            'id' => $invoice->id,
            'reference_month' => $invoice->reference_month->format('Y-m'),
            'total' => round((float) $invoice->total, 2),
            'paid_amount' => round((float) $invoice->paid_amount, 2),
            'remaining' => $invoice->remaining(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'closing_date' => $invoice->closing_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'days_to_close' => (int) max($anchor->startOfDay()->diffInDays($invoice->closing_date, false), 0),
            'days_to_due' => (int) max($anchor->startOfDay()->diffInDays($invoice->due_date, false), 0),
        ];
    }

    /** Total das faturas em aberto (aberta ou fechada) de todos os cartoes. */
    public function outstandingInvoicesTotal(int $userId): float
    {
        $total = Invoice::query()
            ->ownedBy($userId)
            ->outstanding()
            ->selectRaw('SUM(total - paid_amount) AS remaining')
            ->value('remaining');

        return round((float) $total, 2);
    }

    /**
     * @param  list<int>  $cardIds
     * @return array<int, float>
     */
    private function usedLimitByCard(int $userId, array $cardIds): array
    {
        return DB::table('installments')
            ->selectRaw(<<<'SQL'
                transactions.credit_card_id       AS credit_card_id,
                       SUM(installments.amount)   AS total
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->where('installments.user_id', $userId)
            ->where('installments.is_paid', false)
            ->where('transactions.status', '<>', 'cancelado')
            ->whereIn('transactions.credit_card_id', $cardIds)
            ->groupBy('transactions.credit_card_id')
            ->pluck('total', 'credit_card_id')
            ->map(static fn (mixed $total): float => (float) $total)
            ->all();
    }

    /**
     * Fatura em aberto mais antiga de cada cartao, ate o ciclo corrente.
     * Uma consulta so para todos os cartoes.
     *
     * @param  list<int>  $cardIds
     * @return array<int, Invoice>
     */
    private function oldestOutstandingInvoiceByCard(int $userId, array $cardIds, CarbonImmutable $anchor): array
    {
        $invoices = Invoice::query()
            ->ownedBy($userId)
            ->outstanding()
            ->whereIn('credit_card_id', $cardIds)
            ->where('reference_month', '<=', $anchor->startOfMonth()->addMonthNoOverflow()->toDateString())
            ->orderBy('reference_month')
            ->get();

        $oldest = [];

        foreach ($invoices as $invoice) {
            $oldest[$invoice->credit_card_id] ??= $invoice;
        }

        return $oldest;
    }
}
