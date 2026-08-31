<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\DTOs\RecurrenceData;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Models\Recurrence;

/**
 * Cadastra uma despesa ou receita fixa.
 *
 * Nenhum lancamento nasce aqui. A regra descreve o que se repete; transformar
 * um mes dela em lancamento e um ato separado e explicito
 * (MaterializeRecurrenceAction), coerente com o escopo de controle manual.
 */
final class CreateRecurrenceAction
{
    public function handle(RecurrenceData $data): Recurrence
    {
        return Recurrence::query()->create([
            'user_id' => $this->ownerId($data),
            'category_id' => $data->categoryId,
            'account_id' => $data->accountId,
            'credit_card_id' => $data->creditCardId,
            'description' => $data->description,
            'amount' => $data->amount,
            'type' => $data->type,
            // Regra no cartao e sempre credito; a forma de pagamento informada
            // so faz sentido quando o dinheiro sai da conta.
            'method' => $data->isOnCard() ? PaymentMethod::Credito : $data->method,
            'frequency' => $data->frequency,
            'interval' => $data->interval,
            'day_of_month' => $data->dayOfMonth,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'is_active' => true,
        ]);
    }

    private function ownerId(RecurrenceData $data): int
    {
        return $data->isOnCard()
            ? CreditCard::query()->findOrFail($data->creditCardId)->user_id
            : Account::query()->findOrFail($data->accountId)->user_id;
    }
}
