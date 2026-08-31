<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Recupera (ou cria) a fatura de um cartao para um mes de competencia,
 * ja com as datas de fechamento e vencimento calculadas pela regra do cartao.
 */
final class EnsureInvoiceAction
{
    /**
     * A fatura que deve receber uma compra, pulando as que ja foram fechadas
     * antes da hora.
     *
     * Fechar a fatura de setembro no dia 5, com fechamento previsto para o dia
     * 20, e dizer "para mim setembro acabou": a compra do dia 10 pertence a
     * outubro. Ja uma fatura cuja data de fechamento realmente passou continua
     * aceitando lancamento — e assim que se registra hoje uma compra esquecida
     * de marco, sem que ela pule para a fatura do mes que vem.
     */
    public function handleOpen(
        CreditCard $card,
        CarbonImmutable $referenceMonth,
        ?CarbonImmutable $today = null,
    ): Invoice {
        $today ??= CarbonImmutable::now();
        $month = $referenceMonth->startOfMonth();

        // O teto existe para que uma base inconsistente trave em uma fatura, e
        // nao em um laco infinito.
        for ($skipped = 0; $skipped < 12; $skipped++) {
            $existing = $this->find($card, $month);

            if ($existing === null || $existing->status === InvoiceStatus::Aberta) {
                break;
            }

            if ($existing->closing_date->lte($today->startOfDay())) {
                break;
            }

            $month = $month->addMonthNoOverflow();
        }

        return $this->handle($card, $month);
    }

    public function handle(CreditCard $card, CarbonImmutable $referenceMonth): Invoice
    {
        $month = $referenceMonth->startOfMonth();

        return Invoice::query()
            ->withoutUserScope()
            ->firstOrCreate(
                [
                    'credit_card_id' => $card->id,
                    'reference_month' => $month->toDateString(),
                ],
                [
                    'user_id' => $card->user_id,
                    'closing_date' => $card->closingDateFor($month)->toDateString(),
                    'due_date' => $card->dueDateFor($month)->toDateString(),
                    'total' => 0,
                    'paid_amount' => 0,
                    'status' => InvoiceStatus::Aberta,
                ],
            );
    }

    private function find(CreditCard $card, CarbonImmutable $month): ?Invoice
    {
        return Invoice::query()
            ->withoutUserScope()
            ->where('credit_card_id', $card->id)
            ->where('reference_month', $month->startOfMonth()->toDateString())
            ->first();
    }
}
