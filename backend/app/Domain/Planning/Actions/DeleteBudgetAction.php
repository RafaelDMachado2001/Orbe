<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Planning\Models\Budget;

/**
 * Um orcamento e so um limite: nada referencia budget_id, entao excluir nunca
 * afeta lancamentos ja feitos — diferente de conta ou cartao, aqui nao ha
 * trava de "nao removivel".
 */
final class DeleteBudgetAction
{
    public function handle(Budget $budget): void
    {
        $budget->delete();
    }
}
