<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\DTOs\RegisterUserData;
use App\Domain\Ledger\Actions\CreateDefaultCategoriesAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria a conta e ja deixa o plano de categorias pronto, para o usuario nao
 * cair em um app vazio no primeiro lancamento.
 */
final class RegisterUserAction
{
    public function __construct(
        private readonly CreateDefaultCategoriesAction $createDefaultCategories,
    ) {}

    public function handle(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'timezone' => $data->timezone,
                'currency' => $data->currency,
            ]);

            $this->createDefaultCategories->handle($user);

            return $user;
        });
    }
}
