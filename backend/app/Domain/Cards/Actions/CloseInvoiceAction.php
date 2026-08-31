<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Exceptions\InvoiceOperationException;
use App\Domain\Cards\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Fecha a fatura: o total e congelado e nenhuma compra nova entra nela — as
 * proximas passam a cair na fatura seguinte.
 *
 * Duas portas de entrada de proposito.
 *
 * handle() e idempotente e serve a automacao (CloseDueInvoicesAction), que
 * percorre as faturas e fecha as que ja passaram da data sem se importar com
 * as demais.
 *
 * handleOrFail() serve ao botao da tela, onde silencio seria pior que erro:
 * quem clica precisa saber por que nada aconteceu. Ele aceita fechar antes da
 * data — antecipar o fechamento e justamente o unico fechamento manual que
 * sobrou, ja que as faturas vencidas fecham sozinhas.
 */
final class CloseInvoiceAction
{
    public function __construct(
        private readonly RecalculateInvoiceTotalAction $recalculateTotal,
    ) {}

    public function handle(Invoice $invoice, ?CarbonImmutable $today = null): Invoice
    {
        $today ??= CarbonImmutable::now();

        if ($invoice->status !== InvoiceStatus::Aberta) {
            return $invoice;
        }

        if ($today->startOfDay()->lt($invoice->closing_date)) {
            return $invoice;
        }

        return $this->close($invoice);
    }

    /** Fecha explicando a recusa, para o pedido vindo da interface. */
    public function handleOrFail(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Aberta) {
            throw InvoiceOperationException::alreadyClosed();
        }

        return $this->close($invoice);
    }

    private function close(Invoice $invoice): Invoice
    {
        $this->recalculateTotal->handle($invoice);

        $invoice->forceFill(['status' => InvoiceStatus::Fechada])->save();

        return $invoice;
    }
}
