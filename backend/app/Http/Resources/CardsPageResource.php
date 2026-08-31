<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Cards\Enums\CardBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardsPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{summary: array<string, mixed>, cards: list<array<string, mixed>>} $page */
        $page = $this->resource;

        return [
            'summary' => $page['summary'],
            'cards' => array_map($this->present(...), $page['cards']),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function present(array $card): array
    {
        return [
            ...$card,
            'brand_label' => CardBrand::from((string) $card['brand'])->label(),
        ];
    }
}
