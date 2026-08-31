<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Exceptions\CategoryNotRemovableException;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Exclui uma categoria movendo o que dependia dela.
 *
 * Uma categoria nao e um rotulo solto: apagar "Alimentação" com trezentos
 * lancamentos dentro deixaria trezentas linhas sem classificacao e um mes
 * inteiro de relatorio com um buraco. Por isso a exclusao de uma categoria em
 * uso exige destino, e a mudanca acontece em uma transacao so.
 *
 * As subcategorias nao sao apagadas junto: elas vao para o destino, mantendo
 * os dois niveis da arvore — se o destino ja for uma subcategoria, viram irmas
 * dele em vez de netas de alguem.
 *
 * Orcamentos seguem o cascade do banco e somem com a categoria: um teto de
 * gasto e uma regra da categoria, nao um dado do lancamento, e mover o teto de
 * "Lazer" para "Alimentação" criaria um limite que ninguem definiu.
 */
final class DeleteCategoryAction
{
    public function handle(Category $category, ?Category $destination = null): void
    {
        if ($category->is_system) {
            throw CategoryNotRemovableException::isSystem();
        }

        if ($destination !== null) {
            $this->assertUsable($category, $destination);
        }

        $entries = $this->entriesCount($category);

        if ($entries > 0 && $destination === null) {
            throw CategoryNotRemovableException::needsDestination($entries);
        }

        // Onde as subcategorias vao parar: dentro do destino quando ele e uma
        // categoria principal, ao lado dele quando ele mesmo ja e uma
        // subcategoria — nos dois casos a arvore continua com dois niveis.
        $orphanParentId = $destination === null
            ? null
            : ($destination->parent_id ?? $destination->id);

        DB::transaction(function () use ($category, $destination, $orphanParentId): void {
            if ($destination !== null) {
                Transaction::query()
                    ->withoutUserScope()
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $destination->id]);

                Recurrence::query()
                    ->withoutUserScope()
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $destination->id]);
            }

            Category::query()
                ->withoutUserScope()
                ->where('parent_id', $category->id)
                ->update(['parent_id' => $orphanParentId]);

            $category->delete();
        });
    }

    private function assertUsable(Category $category, Category $destination): void
    {
        if ($destination->id === $category->id) {
            throw CategoryNotRemovableException::destinationIsSelf();
        }

        if ($destination->type !== $category->type) {
            throw CategoryNotRemovableException::destinationTypeMismatch();
        }
    }

    /** Quanto do sistema classifica por esta categoria hoje. */
    private function entriesCount(Category $category): int
    {
        return Transaction::query()
            ->withoutUserScope()
            ->where('category_id', $category->id)
            ->count()
            + Recurrence::query()
                ->withoutUserScope()
                ->where('category_id', $category->id)
                ->count();
    }
}
