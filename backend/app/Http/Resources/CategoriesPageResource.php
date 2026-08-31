<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Enums\CategoryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A arvore de categorias com os totais do mes, mais a lista de tipos.
 *
 * Os tipos viajam junto pelo mesmo motivo das options de Lancamentos: o rotulo
 * "Despesa" sai do enum, nunca de uma string escrita na tela.
 */
class CategoriesPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $page */
        $page = $this->resource;

        return [
            ...$page,
            'types' => array_map(
                static fn (CategoryType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                CategoryType::cases(),
            ),
        ];
    }
}
