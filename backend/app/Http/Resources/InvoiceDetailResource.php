<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma fatura aberta na tela: cabecalho, o que a compoe e o que ja foi pago.
 *
 * `can_pay`, `can_close` e `can_undo` saem daqui e nao da interface, para que
 * as regras das Actions e o que a tela oferece nunca discordem.
 */
class InvoiceDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{invoice: Invoice, items: list<array<string, mixed>>, payments: list<array<string, mixed>>, today: CarbonImmutable} $data */
        $data = $this->resource;

        $invoice = $data['invoice'];
        $remaining = $invoice->remaining();

        return [
            'id' => $invoice->id,
            'credit_card_id' => $invoice->credit_card_id,
            'card' => $invoice->creditCard->nickname,
            'reference_month' => $invoice->reference_month->format('Y-m'),
            'total' => round((float) $invoice->total, 2),
            'paid_amount' => round((float) $invoice->paid_amount, 2),
            'remaining' => $remaining,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'closing_date' => $invoice->closing_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'paid_at' => $invoice->paid_at?->toDateString(),
            'items' => $data['items'],
            'payments' => $data['payments'],
            'can_pay' => $remaining > 0.0,
            'can_close' => $invoice->status === InvoiceStatus::Aberta,
            // Fechar antes da data e antecipar o corte: existe para quem quer
            // parar de somar compras neste mes, e a tela precisa avisar disso.
            'closes_early' => $invoice->status === InvoiceStatus::Aberta
                && $data['today']->startOfDay()->lt($invoice->closing_date),
            'can_undo' => $data['payments'] !== [],
        ];
    }
}
