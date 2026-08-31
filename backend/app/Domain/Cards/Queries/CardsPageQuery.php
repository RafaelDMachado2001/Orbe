<?php

declare(strict_types=1);

namespace App\Domain\Cards\Queries;

use Carbon\CarbonImmutable;

/**
 * Os dados da tela de Cartoes: o painel do topo mais a lista de cartoes.
 *
 * A regra de limite usado e de qual fatura mostrar nao se repete aqui — vem da
 * CardsOverviewQuery, a mesma que alimenta a Visao geral. Duas telas mostrando
 * o mesmo numero precisam calcula-lo no mesmo lugar.
 *
 * @phpstan-type CardsPage array{
 *     summary: array<string, mixed>,
 *     cards: list<array<string, mixed>>
 * }
 */
final class CardsPageQuery
{
    public function __construct(
        private readonly CardsOverviewQuery $overview,
    ) {}

    /** @return CardsPage */
    public function handle(int $userId, CarbonImmutable $month, bool $includeArchived = false): array
    {
        $cards = $this->overview->handle($userId, $month, $includeArchived);

        // Cartao arquivado nao entra nos totais de limite: ele nao esta mais
        // disponivel para gastar. A divida que ficou continua no "em aberto".
        $active = array_values(array_filter($cards, static fn (array $card): bool => $card['is_active']));

        $limitTotal = array_sum(array_column($active, 'limit_amount'));
        $usedTotal = array_sum(array_column($active, 'used_amount'));

        return [
            'summary' => [
                'limit_total' => round((float) $limitTotal, 2),
                'used_total' => round((float) $usedTotal, 2),
                'available_total' => round(max((float) $limitTotal - (float) $usedTotal, 0.0), 2),
                'usage_percent' => $limitTotal > 0
                    ? round(min(((float) $usedTotal / (float) $limitTotal) * 100, 100), 1)
                    : 0.0,
                'outstanding_total' => $this->overview->outstandingInvoicesTotal($userId),
                'active_count' => count($active),
                'archived_count' => count($cards) - count($active),
                'next_due' => $this->nextDue($cards),
            ],
            'cards' => $cards,
        ];
    }

    /**
     * A fatura em aberto que vence primeiro, entre todos os cartoes — o que a
     * pessoa precisa pagar a seguir.
     *
     * @param  list<array<string, mixed>>  $cards
     * @return array<string, mixed>|null
     */
    private function nextDue(array $cards): ?array
    {
        $candidates = [];

        foreach ($cards as $card) {
            /** @var array<string, mixed>|null $invoice */
            $invoice = $card['current_invoice'];

            if ($invoice === null || $invoice['id'] === null || $invoice['remaining'] <= 0.0) {
                continue;
            }

            $candidates[] = [
                'card_id' => $card['id'],
                'card' => $card['nickname'],
                'invoice_id' => $invoice['id'],
                'remaining' => $invoice['remaining'],
                'due_date' => $invoice['due_date'],
                'days_to_due' => $invoice['days_to_due'],
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $a['due_date'] <=> $b['due_date']);

        return $candidates[0];
    }
}
