<?php

declare(strict_types=1);

use App\Domain\Banking\Enums\AccountType;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();

    $this->bank = Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
        'color' => '#A05BE0',
    ]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Conta corrente',
        'type' => AccountType::Corrente,
        'initial_balance' => 1000,
    ]);
});

/** @param  array<string, mixed>  $overrides */
function movement(object $context, array $overrides = []): Transaction
{
    return Transaction::factory()->create([
        'user_id' => $context->user->id,
        'account_id' => $context->account->id,
        'credit_card_id' => null,
        'type' => TransactionType::Despesa,
        'direction' => MovementDirection::Saida,
        'status' => TransactionStatus::Confirmado,
        'competence_date' => '2026-08-10',
        'amount' => 100,
        ...$overrides,
    ]);
}

it('soma saldo inicial e lancamentos confirmados no consolidado', function (): void {
    movement($this, ['amount' => 250, 'direction' => MovementDirection::Saida]);
    movement($this, [
        'amount' => 400,
        'type' => TransactionType::Receita,
        'direction' => MovementDirection::Entrada,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    expect((float) $response->json('data.summary.consolidated_balance'))->toBe(1150.0)
        ->and($response->json('data.summary.active_count'))->toBe(1)
        ->and((float) $response->json('data.banks.0.accounts.0.balance'))->toBe(1150.0);
});

it('conta o previsto no movimento do mes, mas nao no saldo', function (): void {
    movement($this, ['amount' => 500, 'status' => TransactionStatus::Previsto]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    expect((float) $response->json('data.summary.consolidated_balance'))->toBe(1000.0)
        ->and((float) $response->json('data.summary.month_out'))->toBe(500.0);
});

it('trata transferencia como movimento da conta, nao como resultado', function (): void {
    $destino = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Reserva',
        'initial_balance' => 0,
    ]);

    movement($this, [
        'amount' => 300,
        'type' => TransactionType::Transferencia,
        'direction' => MovementDirection::Saida,
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $destino->id,
        'credit_card_id' => null,
        'amount' => 300,
        'type' => TransactionType::Transferencia,
        'direction' => MovementDirection::Entrada,
        'status' => TransactionStatus::Confirmado,
        'competence_date' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    // O consolidado nao muda: o dinheiro trocou de conta.
    expect((float) $response->json('data.summary.consolidated_balance'))->toBe(1000.0)
        ->and((float) $response->json('data.summary.month_in'))->toBe(300.0)
        ->and((float) $response->json('data.summary.month_out'))->toBe(300.0);
});

it('esconde conta arquivada por padrao e a devolve sob pedido', function (): void {
    Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Conta antiga',
        'initial_balance' => 5000,
        'is_active' => false,
    ]);

    $user = $this->actingAs($this->user, 'sanctum');

    $visible = $user->getJson('/api/v1/accounts?month=2026-08')->assertOk();

    expect($visible->json('data.banks.0.accounts'))->toHaveCount(1)
        ->and($visible->json('data.summary.archived_count'))->toBe(1)
        // Conta arquivada sai do consolidado.
        ->and((float) $visible->json('data.summary.consolidated_balance'))->toBe(1000.0);

    $all = $user->getJson('/api/v1/accounts?month=2026-08&archived=1')->assertOk();

    expect($all->json('data.banks.0.accounts'))->toHaveCount(2);
});

it('mantem na lista o banco que ainda nao tem conta', function (): void {
    Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Itaú',
        'slug' => 'itau',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    /** @var list<array<string, mixed>> $banks */
    $banks = $response->json('data.banks');

    expect($banks)->toHaveCount(2)
        ->and($response->json('data.summary.banks_count'))->toBe(2);

    $empty = collect($banks)->firstWhere('name', 'Itaú');

    expect($empty['accounts'])->toBe([])
        ->and($empty['accounts_count'])->toBe(0);
});

it('devolve a evolucao do saldo em seis meses', function (): void {
    movement($this, [
        'amount' => 200,
        'type' => TransactionType::Receita,
        'direction' => MovementDirection::Entrada,
        'competence_date' => '2026-06-10',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    $evolution = $response->json('data.evolution');

    expect($evolution)->toHaveCount(6)
        ->and($evolution[0]['month'])->toBe('2026-03')
        ->and($evolution[5]['month'])->toBe('2026-08')
        // O saldo inicial vale desde antes da janela; junho soma os 200.
        ->and((float) $evolution[2]['balance'])->toBe(1000.0)
        ->and((float) $evolution[3]['balance'])->toBe(1200.0)
        ->and((float) $evolution[5]['balance'])->toBe(1200.0);
});

it('conta os lancamentos de cada conta para o dialogo de exclusao', function (): void {
    movement($this, ['competence_date' => '2026-07-05']);
    movement($this, ['competence_date' => '2026-08-12']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    expect($response->json('data.banks.0.accounts.0.movements_count'))->toBe(2)
        ->and($response->json('data.banks.0.accounts.0.last_movement_on'))->toBe('2026-08-12');
});

it('nao mistura contas de usuarios diferentes', function (): void {
    $outro = User::factory()->create();
    $bankAlheio = Bank::factory()->create(['user_id' => $outro->id]);

    Account::factory()->create([
        'user_id' => $outro->id,
        'bank_id' => $bankAlheio->id,
        'initial_balance' => 99999,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts?month=2026-08')
        ->assertOk();

    expect((float) $response->json('data.summary.consolidated_balance'))->toBe(1000.0)
        ->and($response->json('data.banks'))->toHaveCount(1);
});

it('exige autenticacao', function (): void {
    $this->getJson('/api/v1/accounts')->assertUnauthorized();
});
