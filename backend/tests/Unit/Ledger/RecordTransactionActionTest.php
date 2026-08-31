<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => Bank::factory()->create(['user_id' => $this->user->id])->id,
        'initial_balance' => 1000,
    ]);
});

it('altera o saldo apenas com lancamentos confirmados', function (): void {
    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Salário',
        amount: 5000.00,
        type: TransactionType::Receita,
        competenceDate: CarbonImmutable::parse('2026-08-05'),
    ));

    expect($this->account->fresh()->currentBalance())->toBe(6000.0);

    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Conta de luz prevista',
        amount: 300.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-20'),
        status: TransactionStatus::Previsto,
    ));

    expect($this->account->fresh()->currentBalance())->toBe(6000.0);
});

it('recusa criar transferencia por este caminho', function (): void {
    new TransactionData(
        accountId: $this->account->id,
        description: 'Transferência',
        amount: 100.00,
        type: TransactionType::Transferencia,
        competenceDate: CarbonImmutable::now(),
    );
})->throws(InvalidArgumentException::class);

it('recusa valor negativo', function (): void {
    new TransactionData(
        accountId: $this->account->id,
        description: 'Erro',
        amount: -10.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::now(),
    );
})->throws(InvalidArgumentException::class);
