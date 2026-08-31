<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\RecurrenceData;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Models\Recurrence;

/**
 * Edita uma regra fixa.
 *
 * Alterar a regra nao mexe no que ja foi lancado: os meses passados foram
 * pagos com o valor da epoca, e reescrever o extrato retroativamente faria o
 * saldo divergir do que aconteceu de fato. A mudanca vale dos proximos
 * lancamentos em diante.
 */
final class UpdateRecurrenceAction
{
    public function handle(Recurrence $recurrence, RecurrenceData $data): Recurrence
    {
        $recurrence->forceFill([
            'category_id' => $data->categoryId,
            'account_id' => $data->accountId,
            'credit_card_id' => $data->creditCardId,
            'description' => $data->description,
            'amount' => $data->amount,
            'type' => $data->type,
            'method' => $data->isOnCard() ? PaymentMethod::Credito : $data->method,
            'frequency' => $data->frequency,
            'interval' => $data->interval,
            'day_of_month' => $data->dayOfMonth,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
        ])->save();

        return $recurrence->refresh();
    }
}
