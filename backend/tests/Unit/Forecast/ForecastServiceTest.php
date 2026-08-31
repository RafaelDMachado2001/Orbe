<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Forecast\Actions\ForecastService;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'initial_balance' => 10000,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'closing_day' => 28,
        'due_day' => 8,
    ]);

    $this->reference = CarbonImmutable::parse('2026-08-01');
});

function recurrence(int $userId, int $accountId, string $description, float $amount, TransactionType $type): Recurrence
{
    return Recurrence::factory()->create([
        'user_id' => $userId,
        'account_id' => $accountId,
        'description' => $description,
        'amount' => $amount,
        'type' => $type,
        'frequency' => RecurrenceFrequency::Mensal,
        'interval' => 1,
        'starts_on' => '2026-01-01',
        'ends_on' => null,
        'is_active' => true,
    ]);
}

it('projeta o horizonte pedido a partir do mes de referencia', function (): void {
    $result = app(ForecastService::class)->handle($this->user->id, horizon: 3, from: $this->reference);

    expect($result->months)->toHaveCount(3)
        ->and($result->months[0]->month)->toBe('2026-09')
        ->and($result->months[2]->month)->toBe('2026-11');
});

it('soma as recorrencias ativas na previsao de cada mes', function (): void {
    recurrence($this->user->id, $this->account->id, 'Salário', 8000.00, TransactionType::Receita);
    recurrence($this->user->id, $this->account->id, 'Aluguel', 2500.00, TransactionType::Despesa);

    $result = app(ForecastService::class)->handle($this->user->id, horizon: 1, from: $this->reference);
    $september = $result->months[0];

    expect($september->predictedIncome)->toBe(8000.0)
        ->and($september->committedAmount)->toBe(2500.0)
        ->and($september->leftover())->toBe(5500.0);
});

it('inclui as parcelas futuras em aberto no valor comprometido', function (): void {
    recurrence($this->user->id, $this->account->id, 'Salário', 8000.00, TransactionType::Receita);

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 6000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-10'),
        installments: 6,
    ));

    $result = app(ForecastService::class)->handle($this->user->id, horizon: 1, from: $this->reference);

    expect($result->months[0]->committedAmount)->toBe(1000.0)
        ->and($result->months[0]->commitmentRate())->toBe(12.5);
});

it('ignora recorrencias pausadas ou ja encerradas', function (): void {
    recurrence($this->user->id, $this->account->id, 'Salário', 8000.00, TransactionType::Receita);

    recurrence($this->user->id, $this->account->id, 'Curso', 500.00, TransactionType::Despesa)
        ->forceFill(['is_active' => false])->save();

    recurrence($this->user->id, $this->account->id, 'Seguro', 300.00, TransactionType::Despesa)
        ->forceFill(['ends_on' => '2026-07-31'])->save();

    $result = app(ForecastService::class)->handle($this->user->id, horizon: 1, from: $this->reference);

    expect($result->months[0]->committedAmount)->toBe(0.0);
});

it('acumula o saldo projetado mes a mes a partir do saldo atual', function (): void {
    recurrence($this->user->id, $this->account->id, 'Salário', 5000.00, TransactionType::Receita);
    recurrence($this->user->id, $this->account->id, 'Aluguel', 3000.00, TransactionType::Despesa);

    $result = app(ForecastService::class)->handle($this->user->id, horizon: 3, from: $this->reference);

    expect($result->currentBalance)->toBe(10000.0)
        ->and($result->months[0]->projectedBalance)->toBe(12000.0)
        ->and($result->months[1]->projectedBalance)->toBe(14000.0)
        ->and($result->months[2]->projectedBalance)->toBe(16000.0);
});

it('reduz a confianca conforme o mes projetado se distancia', function (): void {
    $result = app(ForecastService::class)->handle($this->user->id, horizon: 3, from: $this->reference);

    expect($result->months[0]->confidence)->toBeGreaterThan($result->months[1]->confidence)
        ->and($result->months[1]->confidence)->toBeGreaterThan($result->months[2]->confidence)
        ->and($result->months[2]->confidence)->toBeGreaterThanOrEqual(0);
});

it('limita o horizonte a um intervalo razoavel', function (): void {
    $result = app(ForecastService::class)->handle($this->user->id, horizon: 99, from: $this->reference);

    expect($result->months)->toHaveCount(24);
});
