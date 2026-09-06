<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O resultado de uma conciliacao: o lancamento que registrou a diferenca e o
 * saldo da conta depois dele. A tela precisa dos dois — o valor para explicar o
 * que foi feito, o saldo para conferir que bateu.
 */
class BalanceAdjustmentResource extends JsonResource
{
    public function __construct(
        Transaction $adjustment,
        private readonly Account $account,
    ) {
        parent::__construct($adjustment);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Transaction $adjustment */
        $adjustment = $this->resource;

        return [
            'transaction_id' => $adjustment->id,
            'account_id' => $this->account->id,
            'amount' => round((float) $adjustment->amount, 2),
            'direction' => $adjustment->direction->value,
            'date' => $adjustment->competence_date->toDateString(),
            'balance' => $this->account->currentBalance(),
        ];
    }
}
