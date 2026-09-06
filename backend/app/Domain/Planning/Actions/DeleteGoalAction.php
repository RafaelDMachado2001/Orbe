<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Planning\Models\Goal;

/**
 * Exclui so a meta e o historico de aportes (cascade em `goal_contributions`).
 * Os lancamentos de transferencia que cada aporte gerou continuam no extrato
 * — o dinheiro realmente se moveu, e desfazer isso e assunto da tela de
 * Lancamentos, nao de apagar a meta que parou de acompanhar.
 */
final class DeleteGoalAction
{
    public function handle(Goal $goal): void
    {
        $goal->delete();
    }
}
