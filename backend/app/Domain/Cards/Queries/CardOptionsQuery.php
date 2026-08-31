<?php

declare(strict_types=1);

namespace App\Domain\Cards\Queries;

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;

/**
 * O que o formulario de cartao precisa: os bancos e as contas do usuario.
 *
 * Criar banco nao acontece aqui — isso pertence a tela de Bancos e contas.
 * Aqui a lista e de escolha, nao de cadastro.
 *
 * @phpstan-type CardOptions array{
 *     banks: list<array<string, mixed>>,
 *     accounts: list<array<string, mixed>>
 * }
 */
final class CardOptionsQuery
{
    /** @return CardOptions */
    public function handle(int $userId): array
    {
        return [
            'banks' => Bank::query()
                ->ownedBy($userId)
                ->orderBy('name')
                ->get()
                ->map(static fn (Bank $bank): array => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'color' => $bank->color,
                    'kind_label' => $bank->kind->label(),
                ])
                ->all(),

            'accounts' => Account::query()
                ->ownedBy($userId)
                ->with('bank:id,name')
                ->where('is_active', true)
                ->orderBy('nickname')
                ->get()
                ->map(static fn (Account $account): array => [
                    'id' => $account->id,
                    'nickname' => $account->nickname,
                    'bank' => $account->bank->name,
                ])
                ->all(),
        ];
    }
}
