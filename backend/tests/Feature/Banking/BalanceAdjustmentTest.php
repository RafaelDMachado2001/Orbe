<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'initial_balance' => 1000,
    ]);
});

it('concilia o saldo da conta pelo endpoint', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", [
            'balance' => 1234.56,
            'notes' => 'Conferido no app do banco',
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 234.56)
        ->assertJsonPath('data.direction', 'entrada')
        ->assertJsonPath('data.date', '2026-08-15')
        ->assertJsonPath('data.balance', 1234.56);

    $adjustment = Transaction::query()->ownedBy($this->user->id)->firstOrFail();

    expect($adjustment->description)->toBe('Ajuste de saldo')
        ->and($adjustment->notes)->toBe('Conferido no app do banco');
});

it('aceita data propria para o ajuste', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", [
            'balance' => 900,
            'date' => '2026-07-31',
        ])
        ->assertCreated()
        ->assertJsonPath('data.direction', 'saida')
        ->assertJsonPath('data.date', '2026-07-31');
});

it('recusa ajuste sem saldo informado', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", ['notes' => 'sem valor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('balance');
});

it('recusa data fora do formato', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", [
            'balance' => 1200,
            'date' => '31/07/2026',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

it('devolve 422 quando nao ha diferenca a ajustar', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", ['balance' => 1000])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O saldo informado é o que a conta já tem. Nada a ajustar.');

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('nao ajusta conta de outro usuario', function (): void {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/v1/accounts/{$this->account->id}/adjustments", ['balance' => 5000])
        ->assertNotFound();
});
