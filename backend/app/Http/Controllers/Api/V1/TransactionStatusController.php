<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Actions\ChangeTransactionStatusAction;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Queries\TransactionFormQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\ChangeTransactionStatusRequest;
use App\Http\Resources\TransactionFormResource;

/**
 * Confirmar, voltar para previsto ou cancelar sem abrir o formulario — a acao
 * mais repetida da tela merece uma rota propria.
 */
class TransactionStatusController extends Controller
{
    public function __invoke(
        ChangeTransactionStatusRequest $request,
        Transaction $transaction,
        ChangeTransactionStatusAction $action,
        TransactionFormQuery $query,
    ): TransactionFormResource {
        return new TransactionFormResource(
            $query->handle($action->handle($transaction, $request->status())),
        );
    }
}
