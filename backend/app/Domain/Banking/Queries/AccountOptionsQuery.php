<?php

declare(strict_types=1);

namespace App\Domain\Banking\Queries;

use App\Domain\Banking\Models\Bank;
use App\Domain\Banking\Support\BankCatalog;

/**
 * O que os formularios da tela de Bancos e contas precisam.
 *
 * O catalogo vem junto dos bancos ja cadastrados para a tela poder marcar o que
 * a pessoa ja tem: oferecer "Nubank" a quem cadastrou o Nubank ontem so gera
 * uma tentativa recusada pela validacao.
 *
 * @phpstan-type AccountOptions array{
 *     banks: list<array<string, mixed>>,
 *     catalog: list<array<string, mixed>>
 * }
 */
final class AccountOptionsQuery
{
    /** @return AccountOptions */
    public function handle(int $userId): array
    {
        $banks = Bank::query()
            ->ownedBy($userId)
            ->orderBy('name')
            ->get();

        $slugs = $banks->pluck('slug')->all();

        return [
            'banks' => $banks
                ->map(static fn (Bank $bank): array => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'slug' => $bank->slug,
                    'color' => $bank->color,
                    'kind' => $bank->kind->value,
                    'kind_label' => $bank->kind->label(),
                ])
                ->all(),

            'catalog' => array_map(
                static fn (array $entry): array => [
                    'slug' => $entry['slug'],
                    'name' => $entry['name'],
                    'color' => $entry['color'],
                    'kind' => $entry['kind']->value,
                    'kind_label' => $entry['kind']->label(),
                    'is_registered' => in_array($entry['slug'], $slugs, true),
                ],
                BankCatalog::all(),
            ),
        ];
    }
}
