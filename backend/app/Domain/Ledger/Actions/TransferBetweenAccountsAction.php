<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Transferencia entre contas do proprio usuario.
 *
 * Gera um par espelhado (saida na origem, entrada no destino) apontando um
 * para o outro. O tipo transferencia move o saldo das contas mas fica fora dos
 * relatorios de receita e despesa — o dinheiro nao entrou nem saiu do
 * patrimonio, apenas mudou de lugar.
 */
final class TransferBetweenAccountsAction
{
    /** @return array{0: Transaction, 1: Transaction} par [saida, entrada] */
    public function handle(TransferData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $from = Account::query()->findOrFail($data->fromAccountId);
            $to = Account::query()->findOrFail($data->toAccountId);

            $base = [
                'user_id' => $from->user_id,
                'description' => $data->description,
                'amount' => $data->amount,
                'type' => TransactionType::Transferencia,
                'status' => TransactionStatus::Confirmado,
                'method' => PaymentMethod::Transferencia,
                'competence_date' => $data->date->toDateString(),
                'paid_date' => $data->date->toDateString(),
                'notes' => $data->notes,
                'is_installment_parent' => false,
            ];

            $outgoing = Transaction::query()->create([
                ...$base,
                'account_id' => $from->id,
                'direction' => MovementDirection::Saida,
            ]);

            $incoming = Transaction::query()->create([
                ...$base,
                'account_id' => $to->id,
                'direction' => MovementDirection::Entrada,
                'transfer_pair_id' => $outgoing->id,
            ]);

            $outgoing->forceFill(['transfer_pair_id' => $incoming->id])->save();

            return [$outgoing, $incoming];
        });
    }
}
