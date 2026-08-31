<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Models\Recurrence;

/**
 * Pausa ou retoma uma regra fixa.
 *
 * Pausada, a regra para de gerar ocorrencias e sai da projecao, mas continua
 * cadastrada com o historico intacto — util para a academia trancada por dois
 * meses, que ninguem quer recadastrar depois.
 */
final class ToggleRecurrenceAction
{
    public function handle(Recurrence $recurrence, bool $isActive): Recurrence
    {
        $recurrence->forceFill(['is_active' => $isActive])->save();

        return $recurrence->refresh();
    }
}
