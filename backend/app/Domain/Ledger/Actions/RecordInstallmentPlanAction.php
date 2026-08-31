<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\InstallmentPlanData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registra uma despesa em conta dividida em N meses — o parcelamento comum e o
 * emprestimo entram por aqui.
 *
 * Cada parcela e um lancamento por direito, na data do seu mes, e nao uma
 * linha filha de uma despesa-mae. E o que faz a previsibilidade pedida
 * funcionar sem nenhuma query nova: a parcela de novembro ja nasce no extrato
 * de novembro, ja soma no total daquele mes e ja aparece na projecao. Um
 * installment_group_id costura as N linhas para editar e excluir em bloco.
 *
 * Confirmar as 12 parcelas de uma vez tiraria da conta, hoje, dinheiro que so
 * sai ao longo de um ano — entao o status escolhido no formulario vale para as
 * parcelas ja vencidas, e as futuras nascem previstas.
 */
final class RecordInstallmentPlanAction
{
    public function __construct(
        private readonly PlanInstallmentsAction $plan,
    ) {}

    /** @return Transaction a primeira parcela, que representa o plano na tela */
    public function handle(InstallmentPlanData $data): Transaction
    {
        return DB::transaction(function () use ($data): Transaction {
            $account = Account::query()->findOrFail($data->accountId);
            $groupId = $data->isSplit() ? (string) Str::uuid() : null;
            $today = CarbonImmutable::now()->startOfDay();

            $first = null;

            foreach ($this->plan->handle($data) as $installment) {
                $status = $this->statusFor($data, $installment['date'], $today);

                $created = Transaction::query()->create([
                    'user_id' => $account->user_id,
                    'account_id' => $account->id,
                    'category_id' => $data->categoryId,
                    'recurrence_id' => $data->recurrenceId,
                    'installment_group_id' => $groupId,
                    'description' => $data->description,
                    'amount' => $installment['amount'],
                    'type' => TransactionType::Despesa,
                    'direction' => MovementDirection::forType(TransactionType::Despesa),
                    'status' => $status,
                    'method' => $data->method,
                    'competence_date' => $installment['date']->toDateString(),
                    'paid_date' => $status === TransactionStatus::Confirmado
                        ? $installment['date']->toDateString()
                        : null,
                    'notes' => $data->notes,
                    'is_installment_parent' => false,
                    'installment_number' => $groupId === null ? null : $installment['number'],
                    'installment_total' => $groupId === null ? null : $data->installments,
                    'is_loan' => $data->isLoan,
                    'lender' => $data->isLoan ? $data->lender : null,
                ]);

                $first ??= $created;
            }

            if ($first === null) {
                throw new \LogicException('Um parcelamento sempre gera ao menos uma parcela.');
            }

            return $first->refresh();
        });
    }

    private function statusFor(
        InstallmentPlanData $data,
        CarbonImmutable $date,
        CarbonImmutable $today,
    ): TransactionStatus {
        if ($data->status === TransactionStatus::Cancelado) {
            return TransactionStatus::Cancelado;
        }

        return $date->gt($today) ? TransactionStatus::Previsto : $data->status;
    }
}
