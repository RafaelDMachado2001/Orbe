<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Import\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ImportBatch */
class ImportBatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ImportBatch $batch */
        $batch = $this->resource;

        $remaining = (int) ($batch->transactions_count ?? 0);
        $locked = (int) ($batch->locked_count ?? 0);

        return [
            'id' => $batch->id,
            'filename' => $batch->filename,
            'format' => $batch->format->value,
            'format_label' => $batch->format->label(),
            'target' => $batch->target()->value,
            'target_label' => $batch->target()->label(),
            'destination' => $batch->destinationLabel(),
            'imported_count' => $batch->imported_count,
            'skipped_count' => $batch->skipped_count,
            // Quantos lancamentos do lote ainda existem: excluir um deles pela
            // tela de Lancamentos nao apaga a importacao, so a esvazia.
            'remaining_count' => $remaining,
            'period_start' => $batch->period_start?->toDateString(),
            'period_end' => $batch->period_end?->toDateString(),
            'created_at' => $batch->created_at->toIso8601String(),
            'can_undo' => $remaining > 0 && $locked === 0,
            'undo_block_reason' => $locked > 0
                ? 'Há compras deste lote já pagas junto com a fatura.'
                : null,
        ];
    }
}
