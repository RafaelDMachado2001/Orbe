<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Actions\TransferBetweenAccountsAction;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Queries\MonthlyTotalsQuery;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->origin = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'initial_balance' => 5000,
    ]);

    $this->destination = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'initial_balance' => 1000,
    ]);
});

it('gera um par espelhado que move saldo entre as contas', function (): void {
    [$outgoing, $incoming] = app(TransferBetweenAccountsAction::class)->handle(new TransferData(
        fromAccountId: $this->origin->id,
        toAccountId: $this->destination->id,
        amount: 1500.00,
        date: CarbonImmutable::parse('2026-08-10'),
    ));

    expect($outgoing->direction)->toBe(MovementDirection::Saida)
        ->and($incoming->direction)->toBe(MovementDirection::Entrada)
        ->and($outgoing->transfer_pair_id)->toBe($incoming->id)
        ->and($incoming->transfer_pair_id)->toBe($outgoing->id)
        ->and($this->origin->fresh()->currentBalance())->toBe(3500.0)
        ->and($this->destination->fresh()->currentBalance())->toBe(2500.0);
});

it('nao conta a transferencia como receita nem como despesa', function (): void {
    app(TransferBetweenAccountsAction::class)->handle(new TransferData(
        fromAccountId: $this->origin->id,
        toAccountId: $this->destination->id,
        amount: 1500.00,
        date: CarbonImmutable::parse('2026-08-10'),
    ));

    $month = CarbonImmutable::parse('2026-08-01');
    $totals = app(MonthlyTotalsQuery::class)->handle($this->user->id, $month, $month);

    expect($totals['2026-08']->income)->toBe(0.0)
        ->and($totals['2026-08']->expense)->toBe(0.0);
});

it('recusa transferir para a mesma conta', function (): void {
    new TransferData(
        fromAccountId: $this->origin->id,
        toAccountId: $this->origin->id,
        amount: 100.00,
        date: CarbonImmutable::now(),
    );
})->throws(InvalidArgumentException::class);
