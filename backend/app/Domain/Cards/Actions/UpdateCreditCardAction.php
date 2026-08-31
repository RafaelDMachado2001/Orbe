<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\DTOs\CreditCardData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Edita um cartao.
 *
 * Mudar o dia de fechamento ou de vencimento nao remaneja compras ja feitas:
 * elas cairiam em outra fatura e o historico deixaria de bater com o que o
 * banco cobrou. As datas das faturas ainda em aberto sao recalculadas, porque
 * essas ainda nao foram cobradas; as pagas ficam como estao.
 */
final class UpdateCreditCardAction
{
    public function handle(CreditCard $card, CreditCardData $data): CreditCard
    {
        return DB::transaction(function () use ($card, $data): CreditCard {
            $bank = Bank::query()->findOrFail($data->bankId);
            $cycleChanged = $card->closing_day !== $data->closingDay || $card->due_day !== $data->dueDay;

            $card->forceFill([
                'bank_id' => $bank->id,
                'payment_account_id' => $data->paymentAccountId,
                'nickname' => $data->nickname,
                'brand' => $data->brand,
                'last_four' => $data->lastFour,
                'limit_amount' => $data->limitAmount,
                'closing_day' => $data->closingDay,
                'due_day' => $data->dueDay,
                'color' => $data->color,
            ])->save();

            if ($cycleChanged) {
                $this->refreshOutstandingInvoiceDates($card->refresh());
            }

            return $card->refresh();
        });
    }

    private function refreshOutstandingInvoiceDates(CreditCard $card): void
    {
        Invoice::query()
            ->withoutUserScope()
            ->where('credit_card_id', $card->id)
            ->outstanding()
            ->get()
            ->each(function (Invoice $invoice) use ($card): void {
                $invoice->forceFill([
                    'closing_date' => $card->closingDateFor($invoice->reference_month)->toDateString(),
                    'due_date' => $card->dueDateFor($invoice->reference_month)->toDateString(),
                ])->save();
            });
    }
}
