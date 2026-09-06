<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\DTOs\BankData;
use App\Domain\Banking\Models\Bank;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Cadastra uma instituicao para o usuario.
 *
 * O banco e so o guarda-chuva: nenhuma conta e criada junto. Quem escolheu um
 * banco do catalogo chega aqui com nome, cor e tipo ja preenchidos pela tela —
 * do ponto de vista do dominio nao ha diferenca entre um banco do catalogo e
 * um digitado a mao.
 */
final class CreateBankAction
{
    public function handle(User $user, BankData $data): Bank
    {
        return Bank::query()->create([
            'user_id' => $user->id,
            'name' => $data->name,
            'slug' => Str::slug($data->name),
            'color' => $data->color,
            'kind' => $data->kind,
        ]);
    }
}
