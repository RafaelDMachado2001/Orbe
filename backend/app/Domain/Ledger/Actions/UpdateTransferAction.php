<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Edita uma transferencia pelos dois lados.
 *
 * Uma transferencia so existe como par espelhado; editar um lado sem o outro
 * criaria dinheiro do nada. Por isso a action recebe qualquer um dos dois
 * registros, resolve o par e grava os dois na mesma transacao de banco.
 */
final class UpdateTransferAction
{
    /** @return array{0: Transaction, 1: Transaction} par [saida, entrada] */
    public function handle(Transaction $leg, TransferData $data): array
    {
        return DB::transaction(function () use ($leg, $data): array {
            $pair = Transaction::query()->findOrFail($leg->transfer_pair_id);

            [$outgoing, $incoming] = $leg->direction === MovementDirection::Saida
                ? [$leg, $pair]
                : [$pair, $leg];

            $from = Account::query()->findOrFail($data->fromAccountId);
            $to = Account::query()->findOrFail($data->toAccountId);

            $shared = [
                'description' => $data->description,
                'amount' => $data->amount,
                'competence_date' => $data->date->toDateString(),
                'paid_date' => $data->date->toDateString(),
                'notes' => $data->notes,
            ];

            $outgoing->forceFill([...$shared, 'account_id' => $from->id])->save();
            $incoming->forceFill([...$shared, 'account_id' => $to->id])->save();

            return [$outgoing->refresh(), $incoming->refresh()];
        });
    }
}
