<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Enums\SystemCategory;
use App\Domain\Ledger\Models\Category;

/**
 * Tudo o que os filtros e o formulario de Lancamentos precisam para montar os
 * seletores: contas, cartoes e categorias do usuario.
 *
 * Vem em uma chamada so porque as tres listas mudam pouco e sempre aparecem
 * juntas — tres requisicoes separadas dariam tres estados de carregamento na
 * mesma tela, sem ganho nenhum.
 *
 * @phpstan-type Options array{
 *     accounts: list<array<string, mixed>>,
 *     cards: list<array<string, mixed>>,
 *     categories: list<array<string, mixed>>,
 *     loan_category_id: int|null
 * }
 */
final class TransactionOptionsQuery
{
    /** @return Options */
    public function handle(int $userId): array
    {
        return [
            'accounts' => $this->accounts($userId),
            'cards' => $this->cards($userId),
            'categories' => $this->categories($userId),
            'loan_category_id' => $this->loanCategoryId($userId),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function accounts(int $userId): array
    {
        return Account::query()
            ->ownedBy($userId)
            ->with('bank:id,name,color')
            ->where('is_active', true)
            ->orderBy('nickname')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'nickname' => $account->nickname,
                'type_label' => $account->type->label(),
                'bank' => $account->bank->name,
                'color' => $account->bank->color,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function cards(int $userId): array
    {
        return CreditCard::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->orderBy('nickname')
            ->get()
            ->map(fn (CreditCard $card): array => [
                'id' => $card->id,
                'nickname' => $card->nickname,
                'brand_label' => $card->brand->label(),
                'last_four' => $card->last_four,
                'color' => $card->color,
                'closing_day' => $card->closing_day,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function categories(int $userId): array
    {
        return Category::query()
            ->ownedBy($userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'color' => $category->color,
                'parent_id' => $category->parent_id,
            ])
            ->all();
    }

    /**
     * A categoria que o formulario de emprestimo marca sozinho. Vem pela
     * system_key, e nao pelo nome: o usuario pode ter renomeado a dela.
     */
    private function loanCategoryId(int $userId): ?int
    {
        return Category::query()
            ->ownedBy($userId)
            ->where('system_key', SystemCategory::Emprestimos->value)
            ->value('id');
    }
}
