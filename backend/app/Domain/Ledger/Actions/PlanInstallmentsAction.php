<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\InstallmentPlanData;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Calendario e rateio de um parcelamento, sem tocar no banco.
 *
 * Vive separado das Actions de escrita porque registrar e editar precisam da
 * mesma conta: se cada uma calculasse a sua, editar um plano poderia mover uma
 * parcela um dia para o lado sem que ninguem tivesse pedido.
 *
 * O residuo da divisao vai para as primeiras parcelas, entao 3x de R$ 100,00
 * nunca somam R$ 99,99. O dia da primeira parcela e mantido nos meses
 * seguintes, encolhido para o ultimo dia quando o mes e curto — dia 31 vira 28
 * em fevereiro e volta a 31 em marco.
 *
 * @phpstan-type PlannedInstallment array{number: int, amount: float, date: CarbonImmutable}
 */
final class PlanInstallmentsAction
{
    /** @return list<PlannedInstallment> */
    public function handle(InstallmentPlanData $data): array
    {
        $amounts = Money::split(Money::toCents($data->totalAmount), $data->installments);
        $first = $data->firstDate->startOfDay();

        return array_map(
            static fn (int $cents, int $index): array => [
                'number' => $index + 1,
                'amount' => Money::toReais($cents),
                'date' => self::monthlyDate($first, $index),
            ],
            $amounts,
            array_keys($amounts),
        );
    }

    private static function monthlyDate(CarbonImmutable $first, int $offset): CarbonImmutable
    {
        $month = $first->startOfMonth()->addMonthsNoOverflow($offset);

        return $month->setDay(min($first->day, $month->daysInMonth));
    }
}
