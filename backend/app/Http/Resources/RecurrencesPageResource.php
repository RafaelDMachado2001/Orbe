<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * As regras fixas do mes. O formato vem inteiro da RecurrencesPageQuery, que
 * ja resolve rotulo de frequencia e estado de lancamento.
 */
class RecurrencesPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{summary: array<string, mixed>, recurrences: list<array<string, mixed>>} $page */
        $page = $this->resource;

        return [
            'summary' => $page['summary'],
            'recurrences' => $page['recurrences'],
        ];
    }
}
