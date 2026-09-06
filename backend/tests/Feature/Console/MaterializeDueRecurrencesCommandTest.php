<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

it('materializa recorrencias vencidas de todos os usuarios', function (): void {
    $today = CarbonImmutable::now();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Recurrence::factory()->create([
        'user_id' => $userA->id,
        'account_id' => Account::factory()->create(['user_id' => $userA->id])->id,
        'day_of_month' => 1,
        'starts_on' => $today->subMonths(3)->startOfMonth()->toDateString(),
    ]);

    Recurrence::factory()->create([
        'user_id' => $userB->id,
        'account_id' => Account::factory()->create(['user_id' => $userB->id])->id,
        'day_of_month' => 1,
        'starts_on' => $today->subMonths(3)->startOfMonth()->toDateString(),
    ]);

    $this->artisan('recurrences:materialize-due')->assertSuccessful();

    expect(Transaction::query()->ownedBy($userA->id)->where('type', TransactionType::Despesa->value)->count())->toBe(1)
        ->and(Transaction::query()->ownedBy($userB->id)->where('type', TransactionType::Despesa->value)->count())->toBe(1);
});

it('nao materializa a mesma recorrencia duas vezes no mesmo mes', function (): void {
    $user = User::factory()->create();

    Recurrence::factory()->create([
        'user_id' => $user->id,
        'account_id' => Account::factory()->create(['user_id' => $user->id])->id,
        'day_of_month' => 1,
        'starts_on' => CarbonImmutable::now()->subMonths(3)->startOfMonth()->toDateString(),
    ]);

    $this->artisan('recurrences:materialize-due')->assertSuccessful();
    $this->artisan('recurrences:materialize-due')->assertSuccessful();

    expect(Transaction::query()->ownedBy($user->id)->count())->toBe(1);
});
