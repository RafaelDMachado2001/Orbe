<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\Actions\UpdateCardPurchaseAction;
use App\Domain\Ledger\Actions\DeleteTransactionAction;
use App\Domain\Ledger\Actions\RecordInstallmentPlanAction;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\Actions\TransferBetweenAccountsAction;
use App\Domain\Ledger\Actions\UpdateInstallmentPlanAction;
use App\Domain\Ledger\Actions\UpdateTransactionAction;
use App\Domain\Ledger\Actions\UpdateTransferAction;
use App\Domain\Ledger\Enums\EntryKind;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Queries\TransactionFormQuery;
use App\Domain\Ledger\Queries\TransactionListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\TransactionFormResource;
use App\Http\Resources\TransactionPageResource;
use Illuminate\Http\JsonResponse;

/**
 * Um lancamento entra por varios caminhos diferentes (conta, conta parcelada,
 * emprestimo, cartao e os dois lados de uma transferencia) e sai por uma lista
 * so. O controller apenas escolhe a Action da vez — o parcelamento, o par
 * espelhado e o recalculo de fatura vivem no dominio.
 */
class TransactionController extends Controller
{
    public function index(TransactionIndexRequest $request, TransactionListQuery $query): TransactionPageResource
    {
        return new TransactionPageResource(
            $query->handle($request->user()->id, $request->toFilters()),
        );
    }

    public function show(Transaction $transaction, TransactionFormQuery $query): TransactionFormResource
    {
        return new TransactionFormResource($query->handle($transaction));
    }

    public function store(
        StoreTransactionRequest $request,
        RecordTransactionAction $record,
        RecordInstallmentPlanAction $recordPlan,
        RegisterCardPurchaseAction $registerPurchase,
        TransferBetweenAccountsAction $transfer,
        TransactionFormQuery $query,
    ): JsonResponse {
        $created = match ($request->kind()) {
            EntryKind::Receita => $record->handle($request->toTransactionData()),
            EntryKind::Despesa => $request->plannedInstallments() > 1
                ? $recordPlan->handle($request->toInstallmentPlanData())
                : $record->handle($request->toTransactionData()),
            EntryKind::Emprestimo => $recordPlan->handle($request->toInstallmentPlanData()),
            EntryKind::Cartao => $registerPurchase->handle($request->toCardPurchaseData()),
            EntryKind::Transferencia => $transfer->handle($request->toTransferData())[0],
        };

        return (new TransactionFormResource($query->handle($created)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        UpdateTransactionAction $update,
        UpdateInstallmentPlanAction $updatePlan,
        UpdateCardPurchaseAction $updatePurchase,
        UpdateTransferAction $updateTransfer,
        TransactionFormQuery $query,
    ): TransactionFormResource {
        $updated = match ($request->kind()) {
            EntryKind::Receita => $update->handle($transaction, $request->toTransactionData()),
            EntryKind::Despesa => $this->isPlanned($request, $transaction)
                ? $updatePlan->handle($transaction, $request->toInstallmentPlanData())
                : $update->handle($transaction, $request->toTransactionData()),
            EntryKind::Emprestimo => $updatePlan->handle($transaction, $request->toInstallmentPlanData()),
            EntryKind::Cartao => $updatePurchase->handle($transaction, $request->toCardPurchaseData()),
            EntryKind::Transferencia => $updateTransfer->handle($transaction, $request->toTransferData())[0],
        };

        return new TransactionFormResource($query->handle($updated));
    }

    /**
     * Uma despesa passa pela Action de parcelamento quando ja e um plano ou
     * quando esta virando um: e o caminho que sabe criar as parcelas que
     * faltam e apagar as que sobraram. Despesa avulsa que continua avulsa segue
     * pelo caminho simples, sem grupo nem numeracao.
     */
    private function isPlanned(UpdateTransactionRequest $request, Transaction $transaction): bool
    {
        return $request->plannedInstallments() > 1 || $transaction->isInstallmentPlan();
    }

    public function destroy(Transaction $transaction, DeleteTransactionAction $action): JsonResponse
    {
        $action->handle($transaction);

        return response()->json(['message' => 'Lançamento excluído.']);
    }
}
