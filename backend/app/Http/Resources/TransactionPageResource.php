<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\DTOs\TransactionPage;
use App\Domain\Ledger\Enums\MovementOrigin;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionPage */
class TransactionPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TransactionPage $page */
        $page = $this->resource;

        return [
            'items' => array_map($this->present(...), $page->rows),
            'summary' => [
                'income' => $page->income,
                'expense' => $page->expense,
                'net' => $page->net(),
                'entries' => $page->total,
            ],
            'meta' => [
                'page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMore(),
            ],
        ];
    }

    /**
     * Rotulos legiveis sao resolvidos aqui, e nao na tela: o enum e a fonte de
     * verdade do texto, entao "Cartão" e "Confirmado" nunca divergem entre
     * frontend e API.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $status = TransactionStatus::from((string) $row['status']);
        $origin = MovementOrigin::from((string) $row['origin']);
        $method = $row['method'] === null ? null : PaymentMethod::tryFrom((string) $row['method']);

        return [
            ...$row,
            'status_label' => $status->label(),
            'origin_label' => $origin->label(),
            'method_label' => $method?->label(),
            // A parcela nasce da compra e nao se altera sozinha; a baixa de
            // fatura pertence a tela de Cartoes. Nos dois casos a linha existe,
            // mas nao oferece editar nem excluir.
            'is_editable' => ! $row['is_invoice_payment'] && ! $row['is_paid'],
        ];
    }
}
