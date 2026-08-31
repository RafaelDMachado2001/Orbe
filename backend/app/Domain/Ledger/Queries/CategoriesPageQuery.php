<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A tela de Categorias: a arvore de dois niveis e quanto cada categoria
 * movimentou no mes.
 *
 * O valor do mes soma as duas fontes que o resto do sistema ja soma junto —
 * lancamentos de conta pela competencia e parcelas de cartao pelo mes em que
 * caem — porque uma categoria que so contasse o que passou na conta diria que
 * "Compras" gastou zero em um mes inteiro de cartao.
 *
 * A categoria mae mostra tambem o total das filhas: e o numero que a pessoa
 * procura quando cria "Casa" com "Aluguel" e "Condominio" dentro.
 *
 * @phpstan-type CategoryNode array{
 *     id: int,
 *     parent_id: int|null,
 *     name: string,
 *     type: string,
 *     type_label: string,
 *     color: string,
 *     icon: string|null,
 *     is_system: bool,
 *     entries_count: int,
 *     month_total: float,
 *     total_with_children: float,
 *     children: list<array<string, mixed>>
 * }
 * @phpstan-type CategoriesPage array{
 *     month: string,
 *     summary: array<string, mixed>,
 *     categories: list<CategoryNode>
 * }
 */
final class CategoriesPageQuery
{
    /** @return CategoriesPage */
    public function handle(int $userId, CarbonImmutable $month): array
    {
        $categories = Category::query()
            ->ownedBy($userId)
            ->orderBy('name')
            ->get();

        $totals = $this->monthTotals($userId, $month);
        $entries = $this->entryCounts($userId);

        /** @var array<int, list<CategoryNode>> $childrenOf */
        $childrenOf = [];
        $roots = [];

        foreach ($categories as $category) {
            $node = $this->node($category, $totals, $entries);

            if ($category->parent_id === null) {
                $roots[$category->id] = $node;

                continue;
            }

            $childrenOf[$category->parent_id][] = $node;
        }

        $tree = [];

        foreach ($roots as $id => $node) {
            $children = $childrenOf[$id] ?? [];

            $node['children'] = $children;
            $node['total_with_children'] = round(
                $node['month_total'] + array_sum(array_column($children, 'month_total')),
                2,
            );

            $tree[] = $node;
        }

        // Uma subcategoria orfa (a mae saiu no meio de uma edicao) ainda
        // precisa aparecer, senao ela some da tela e ninguem consegue
        // conserta-la.
        foreach ($childrenOf as $parentId => $children) {
            if (! isset($roots[$parentId])) {
                array_push($tree, ...$children);
            }
        }

        usort($tree, static fn (array $a, array $b): int => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return [
            'month' => $month->format('Y-m'),
            'summary' => $this->summary($categories->all(), $totals),
            'categories' => $tree,
        ];
    }

    /**
     * @param  array<int, float>  $totals
     * @param  array<int, int>  $entries
     * @return CategoryNode
     */
    private function node(Category $category, array $totals, array $entries): array
    {
        $total = round($totals[$category->id] ?? 0.0, 2);

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'type' => $category->type->value,
            'type_label' => $category->type->label(),
            'color' => $category->color,
            'icon' => $category->icon,
            'is_system' => $category->is_system,
            'entries_count' => $entries[$category->id] ?? 0,
            'month_total' => $total,
            'total_with_children' => $total,
            'children' => [],
        ];
    }

    /**
     * @param  list<Category>  $categories
     * @param  array<int, float>  $totals
     * @return array<string, mixed>
     */
    private function summary(array $categories, array $totals): array
    {
        $byType = static fn (CategoryType $type): int => count(array_filter(
            $categories,
            static fn (Category $category): bool => $category->type === $type,
        ));

        return [
            'total' => count($categories),
            'expense_count' => $byType(CategoryType::Despesa),
            'income_count' => $byType(CategoryType::Receita),
            'in_use_count' => count(array_filter($totals, static fn (float $total): bool => $total > 0.0)),
            'unused_count' => count(array_filter(
                $categories,
                static fn (Category $category): bool => ($totals[$category->id] ?? 0.0) <= 0.0,
            )),
        ];
    }

    /**
     * Quanto cada categoria movimentou no mes, somando conta e cartao.
     *
     * @return array<int, float>
     */
    private function monthTotals(int $userId, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $cancelled = TransactionStatus::Cancelado->value;

        $accounts = DB::table('transactions')
            ->selectRaw(<<<'SQL'
                category_id      AS category_id,
                       SUM(amount) AS total
                SQL)
            ->where('user_id', $userId)
            ->where('status', '<>', $cancelled)
            ->where('is_installment_parent', false)
            ->whereNotNull('category_id')
            ->whereBetween('competence_date', [$start, $end])
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $cards = DB::table('installments')
            ->selectRaw(<<<'SQL'
                transactions.category_id       AS category_id,
                       SUM(installments.amount) AS total
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->where('installments.user_id', $userId)
            ->where('transactions.status', '<>', $cancelled)
            ->whereNotNull('transactions.category_id')
            ->whereBetween('installments.competence_date', [$start, $end])
            ->groupBy('transactions.category_id')
            ->pluck('total', 'category_id');

        $totals = [];

        foreach ([$accounts, $cards] as $source) {
            foreach ($source as $categoryId => $total) {
                $totals[(int) $categoryId] = ($totals[(int) $categoryId] ?? 0.0) + (float) $total;
            }
        }

        return $totals;
    }

    /**
     * Quantos registros apontam para cada categoria, em toda a base — e o
     * numero que a tela mostra antes de perguntar para onde mover.
     *
     * @return array<int, int>
     */
    private function entryCounts(int $userId): array
    {
        $counts = [];

        foreach (['transactions', 'recurrences'] as $table) {
            $rows = DB::table($table)
                ->selectRaw(<<<'SQL'
                    category_id    AS category_id,
                           COUNT(1) AS total
                    SQL)
                ->where('user_id', $userId)
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->pluck('total', 'category_id');

            foreach ($rows as $categoryId => $total) {
                $counts[(int) $categoryId] = ($counts[(int) $categoryId] ?? 0) + (int) $total;
            }
        }

        return $counts;
    }
}
