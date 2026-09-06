<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\DTOs\BalanceAdjustmentData;
use App\Domain\Banking\Exceptions\BalanceAdjustmentException;
use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\SystemCategory;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;

/**
 * Concilia o saldo da conta com o que o banco mostra.
 *
 * A diferenca vira um lancamento confirmado, nao uma edicao do saldo inicial.
 * Reescrever o saldo inicial acertaria o numero de hoje e, de carona, mudaria
 * o saldo de todos os meses passados — inclusive os que a pessoa ja conferiu —
 * sem deixar nada explicando a mudanca. Como lancamento, a correcao aparece no
 * extrato, na data em que foi feita, e pode ser desfeita como qualquer outra.
 *
 * O alvo e comparado com o saldo *da data do ajuste*: um ajuste datado no mes
 * passado corrige o que estava errado naquele mes.
 */
final class AdjustAccountBalanceAction
{
    public function __construct(
        private readonly RecordTransactionAction $record,
    ) {}

    public function handle(Account $account, BalanceAdjustmentData $data): Transaction
    {
        if (! $account->is_active) {
            throw BalanceAdjustmentException::accountIsArchived();
        }

        $difference = round($data->targetBalance - $account->balanceOn($data->date), 2);

        if (abs($difference) < 0.01) {
            throw BalanceAdjustmentException::noDifference();
        }

        $type = $difference > 0 ? TransactionType::Receita : TransactionType::Despesa;

        return $this->record->handle(new TransactionData(
            accountId: $account->id,
            description: 'Ajuste de saldo',
            amount: abs($difference),
            type: $type,
            competenceDate: $data->date,
            categoryId: $this->adjustmentCategoryId($account->user_id, $type),
            status: TransactionStatus::Confirmado,
            paidDate: $data->date,
            notes: $data->notes,
        ));
    }

    /**
     * A categoria de ajuste e localizada pela system_key, nao pelo nome: a
     * pessoa pode ter renomeado "Ajuste de saldo" para o que quiser.
     */
    private function adjustmentCategoryId(int $userId, TransactionType $type): ?int
    {
        return Category::query()
            ->ownedBy($userId)
            ->where('system_key', SystemCategory::adjustmentFor($type)->value)
            ->value('id');
    }
}
