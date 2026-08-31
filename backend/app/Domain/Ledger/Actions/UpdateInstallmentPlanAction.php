<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\InstallmentPlanData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Exceptions\TransactionNotEditableException;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Edita um parcelamento inteiro a partir de qualquer uma das suas parcelas.
 *
 * Mudar valor, data ou numero de parcelas redistribui o plano todo — a mesma
 * regra que ja vale para a compra no cartao. Editar so a parcela clicada
 * deixaria o plano inconsistente com o proprio rotulo: as outras onze
 * continuariam dizendo "de 12" com um total que nao bate mais.
 *
 * As linhas sao atualizadas no lugar, por posicao, em vez de apagadas e
 * recriadas: um plano de 12 que vira 6 perde as seis ultimas e mantem os ids
 * das seis primeiras, para nao quebrar o que ja aponta para elas.
 *
 * Parcela ja vencida acompanha o status escolhido no formulario; parcela
 * futura preserva o status que tinha, porque quem adiantou o pagamento de uma
 * delas nao esperaria ver a confirmacao desfeita ao corrigir uma data.
 */
final class UpdateInstallmentPlanAction
{
    public function __construct(
        private readonly PlanInstallmentsAction $plan,
    ) {}

    /** @return Transaction a parcela equivalente a que foi aberta para edicao */
    public function handle(Transaction $transaction, InstallmentPlanData $data): Transaction
    {
        if ($transaction->isInvoicePayment()) {
            throw TransactionNotEditableException::invoicePayment();
        }

        if ($transaction->isCardPurchase()) {
            throw TransactionNotEditableException::installmentParent();
        }

        return DB::transaction(function () use ($transaction, $data): Transaction {
            $account = Account::query()->findOrFail($data->accountId);
            $existing = $this->currentRows($transaction);
            $schedule = $this->plan->handle($data);
            $today = CarbonImmutable::now()->startOfDay();

            $groupId = $data->isSplit()
                ? ($transaction->installment_group_id ?? (string) Str::uuid())
                : null;

            $rows = [];

            foreach ($schedule as $index => $installment) {
                $row = $existing[$index] ?? new Transaction;
                $status = $this->statusFor($data, $installment['date'], $today, $row->exists ? $row->status : null);

                $row->forceFill([
                    'user_id' => $account->user_id,
                    'account_id' => $account->id,
                    'category_id' => $data->categoryId,
                    'installment_group_id' => $groupId,
                    'description' => $data->description,
                    'amount' => $installment['amount'],
                    'type' => TransactionType::Despesa,
                    'direction' => MovementDirection::forType(TransactionType::Despesa),
                    'status' => $status,
                    'method' => $data->method,
                    'competence_date' => $installment['date']->toDateString(),
                    'paid_date' => $status === TransactionStatus::Confirmado
                        ? ($row->paid_date?->toDateString() ?? $installment['date']->toDateString())
                        : null,
                    'notes' => $data->notes,
                    'is_installment_parent' => false,
                    'installment_number' => $groupId === null ? null : $installment['number'],
                    'installment_total' => $groupId === null ? null : $data->installments,
                    'is_loan' => $data->isLoan,
                    'lender' => $data->isLoan ? $data->lender : null,
                ])->save();

                $rows[] = $row;
            }

            foreach (array_slice($existing, count($schedule)) as $surplus) {
                $surplus->delete();
            }

            return $this->reopened($rows, $transaction);
        });
    }

    /**
     * As parcelas do plano em ordem. Um lancamento avulso e um plano de uma
     * parcela so — assim a mesma Action serve para transformar uma despesa
     * simples em parcelada e o contrario.
     *
     * @return list<Transaction>
     */
    private function currentRows(Transaction $transaction): array
    {
        if (! $transaction->isInstallmentPlan()) {
            return [$transaction];
        }

        return $transaction->installmentPlan()->get()->all();
    }

    /**
     * A parcela que a tela deve reabrir depois de salvar: a mesma posicao que
     * estava aberta, ou a ultima que restou quando o plano encolheu.
     *
     * @param  list<Transaction>  $rows
     */
    private function reopened(array $rows, Transaction $edited): Transaction
    {
        foreach ($rows as $row) {
            if ($row->id === $edited->id) {
                return $row->refresh();
            }
        }

        $last = $rows[count($rows) - 1] ?? null;

        if ($last === null) {
            throw new \LogicException('Um parcelamento sempre mantem ao menos uma parcela.');
        }

        return $last->refresh();
    }

    private function statusFor(
        InstallmentPlanData $data,
        CarbonImmutable $date,
        CarbonImmutable $today,
        ?TransactionStatus $current,
    ): TransactionStatus {
        if ($data->status === TransactionStatus::Cancelado) {
            return TransactionStatus::Cancelado;
        }

        if (! $date->gt($today)) {
            return $data->status;
        }

        return $current ?? TransactionStatus::Previsto;
    }
}
