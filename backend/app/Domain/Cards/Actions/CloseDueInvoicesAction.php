<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Fecha, por convencao, toda fatura que ja passou da data de fechamento.
 *
 * Uma fatura de tres meses atras aparecer como "aberta" e uma mentira do
 * sistema, nao um estado do mundo: o cartao fechou aquele mes no dia dele,
 * independentemente de alguem ter clicado em algum botao. Entao o fechamento
 * retroativo nao e uma acao do usuario — e a manutencao do que ja aconteceu.
 *
 * Roda por dois caminhos, de proposito. O comando agendado cuida da base
 * inteira uma vez por dia; a varredura preguiçosa nas telas de cartao cuida do
 * usuario que abriu o app em uma maquina onde o agendador nunca subiu. As duas
 * sao idempotentes e a segunda custa uma query indexada que quase sempre nao
 * devolve nada.
 */
final class CloseDueInvoicesAction
{
    public function __construct(
        private readonly CloseInvoiceAction $closeInvoice,
    ) {}

    /**
     * @param  int|null  $userId  null varre a base inteira, para o agendador
     * @return int quantas faturas foram fechadas
     */
    public function handle(?int $userId = null, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now();

        $query = Invoice::query()
            ->withoutUserScope()
            ->where('status', InvoiceStatus::Aberta->value)
            ->where('closing_date', '<=', $today->startOfDay()->toDateString());

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $closed = 0;

        foreach ($query->get() as $invoice) {
            $this->closeInvoice->handle($invoice, $today);
            $closed++;
        }

        return $closed;
    }
}
