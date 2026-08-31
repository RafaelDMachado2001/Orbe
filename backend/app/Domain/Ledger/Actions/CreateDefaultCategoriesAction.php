<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\SystemCategory;
use App\Domain\Ledger\Models\Category;
use App\Models\User;

/**
 * Categorias que todo usuario recebe ao se cadastrar. Sao marcadas como
 * is_system: podem ser renomeadas e recoloridas, nunca excluidas. As que o
 * proprio sistema precisa localizar depois carregam tambem uma system_key,
 * porque o nome e do usuario e pode mudar.
 *
 * @phpstan-type CategorySeed array{0: string, 1: string, 2: string}
 */
final class CreateDefaultCategoriesAction
{
    /** @var list<array{name: string, color: string, icon: string, system_key?: SystemCategory}> */
    private const EXPENSES = [
        ['name' => 'Moradia', 'color' => '#A07CFF', 'icon' => 'home'],
        ['name' => 'Alimentação', 'color' => '#FF8A3D', 'icon' => 'utensils'],
        ['name' => 'Transporte', 'color' => '#35D68A', 'icon' => 'car'],
        ['name' => 'Saúde', 'color' => '#4FD1C5', 'icon' => 'heart-pulse'],
        ['name' => 'Educação', 'color' => '#7C5CE0', 'icon' => 'graduation-cap'],
        ['name' => 'Assinaturas', 'color' => '#C9B4FF', 'icon' => 'repeat'],
        ['name' => 'Lazer', 'color' => '#C97B2E', 'icon' => 'party-popper'],
        ['name' => 'Compras', 'color' => '#FFA76B', 'icon' => 'shopping-bag'],
        ['name' => 'Equipamento', 'color' => '#8E7BFF', 'icon' => 'laptop'],
        ['name' => 'Impostos e taxas', 'color' => '#6E7681', 'icon' => 'landmark'],
        [
            'name' => 'Empréstimos e financiamentos',
            'color' => '#FF8A3D',
            'icon' => 'landmark',
            'system_key' => SystemCategory::Emprestimos,
        ],
        ['name' => 'Outros', 'color' => '#4A525E', 'icon' => 'circle-dashed'],
    ];

    /** @var list<array{name: string, color: string, icon: string}> */
    private const INCOMES = [
        ['name' => 'Salário', 'color' => '#35D68A', 'icon' => 'wallet'],
        ['name' => 'Serviços PJ', 'color' => '#48E39A', 'icon' => 'briefcase'],
        ['name' => 'Aluguel recebido', 'color' => '#1D9260', 'icon' => 'key'],
        ['name' => 'Rendimentos', 'color' => '#6FE7AC', 'icon' => 'trending-up'],
        ['name' => 'Outras receitas', 'color' => '#4A525E', 'icon' => 'circle-plus'],
    ];

    public function handle(User $user): void
    {
        $rows = [];
        $now = now();

        foreach (self::EXPENSES as $category) {
            $rows[] = [...$category, 'type' => CategoryType::Despesa->value];
        }

        foreach (self::INCOMES as $category) {
            $rows[] = [...$category, 'type' => CategoryType::Receita->value];
        }

        Category::query()->insert(array_map(
            static fn (array $row): array => [
                ...$row,
                'user_id' => $user->id,
                'is_system' => true,
                'system_key' => ($row['system_key'] ?? null)?->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $rows,
        ));
    }
}
