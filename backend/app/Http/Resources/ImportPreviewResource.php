<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Import\DTOs\ImportPreview;
use App\Domain\Import\DTOs\PreviewRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ImportPreview */
class ImportPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ImportPreview $preview */
        $preview = $this->resource;

        return [
            'format' => $preview->format->value,
            'format_label' => $preview->format->label(),
            'period_start' => $preview->periodStart?->toDateString(),
            'period_end' => $preview->periodEnd?->toDateString(),
            'summary' => $preview->summary(),
            // Mapeamento e cabecalho so existem em CSV; e o que a tela usa
            // para mostrar de qual coluna saiu cada campo e deixar corrigir.
            'mapping' => $preview->mapping?->toArray(),
            'headers' => $preview->headers,
            'column_count' => $preview->columnCount,
            'rows' => array_map($this->presentRow(...), $preview->rows),
        ];
    }

    /** @return array<string, mixed> */
    private function presentRow(PreviewRow $row): array
    {
        return [
            'index' => $row->index,
            'date' => $row->date->toDateString(),
            'description' => $row->description,
            'amount' => round($row->amount, 2),
            'direction' => $row->direction->value,
            'direction_label' => $row->direction->label(),
            'category_id' => $row->categoryId,
            'is_duplicate' => $row->isDuplicate,
            'is_importable' => $row->isImportable,
            'skip_reason' => $row->skipReason,
            // Quem decide o estado inicial da caixa e o backend: a mesma regra
            // que marca a repetida e a que a tela desmarcaria por conta propria.
            'selected' => $row->isSelectedByDefault(),
        ];
    }
}
