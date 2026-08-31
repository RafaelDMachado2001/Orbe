<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Planning\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'initial_balance' => 1000,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 10000,
    ]);

    $this->moradia = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Moradia',
        'type' => CategoryType::Despesa,
    ]);

    $this->salario = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Salário',
        'type' => CategoryType::Receita,
    ]);
});

function seedAugust(User $user, Account $account, CreditCard $card, Category $moradia, Category $salario): void
{
    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $account->id,
        description: 'Salário',
        amount: 9000.00,
        type: TransactionType::Receita,
        competenceDate: CarbonImmutable::parse('2026-08-05'),
        categoryId: $salario->id,
    ));

    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $account->id,
        description: 'Aluguel',
        amount: 2500.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-10'),
        categoryId: $moradia->id,
    ));

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
        categoryId: $moradia->id,
    ));
}

it('exige autenticacao', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

it('devolve a estrutura completa da visao geral', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'month',
                'month_label',
                'kpis' => [
                    'consolidated_balance' => ['value', 'delta_percent', 'comparison_label'],
                    'income' => ['value', 'sources_count', 'sources'],
                    'expense' => ['value', 'budget_usage_percent'],
                    'projection' => ['value', 'month', 'confidence'],
                ],
                'chart',
                'accounts',
                'cards',
                'categories',
                'latest_movements',
                'budgets',
                'forecast',
                'alerts',
            ],
        ]);
});

it('calcula receitas, despesas e saldo do mes', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08');

    // Despesa do mes = aluguel 2.500 + a primeira parcela do notebook (1.000).
    $response->assertJsonPath('data.kpis.income.value', 9000)
        ->assertJsonPath('data.kpis.expense.value', 3500)
        ->assertJsonPath('data.kpis.consolidated_balance.value', 7500);
});

it('desenha sete meses de historico mais o mes projetado', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    $chart = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08')
        ->json('data.chart');

    expect($chart)->toHaveCount(8)
        ->and($chart[0]['month'])->toBe('2026-02')
        ->and($chart[6]['month'])->toBe('2026-08')
        ->and($chart[6]['is_forecast'])->toBeFalse()
        ->and($chart[7]['month'])->toBe('2026-09')
        ->and($chart[7]['is_forecast'])->toBeTrue();
});

it('lista a parcela do cartao com o rotulo e o valor da parcela', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    $movements = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08')
        ->json('data.latest_movements');

    /** @var array<int, array<string, mixed>> $movements */
    $notebook = collect($movements)->firstWhere('description', 'Notebook');

    expect($notebook['installment'])->toBe('1/3')
        ->and((float) $notebook['amount'])->toBe(1000.0)
        ->and($notebook['source'])->toBe('Nubank Ultravioleta');
});

it('avisa quando o orcamento da categoria estoura', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $this->moradia->id,
        'reference_month' => '2026-08-01',
        'limit_amount' => 1000.00,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08');

    expect($response->json('data.alerts'))->not->toBeEmpty()
        ->and($response->json('data.alerts.0.title'))->toContain('Moradia')
        ->and($response->json('data.budgets.0.is_exceeded'))->toBeTrue();
});

it('nao vaza dados de outro usuario', function (): void {
    seedAugust($this->user, $this->account, $this->card, $this->moradia, $this->salario);

    $outro = User::factory()->create();

    $response = $this->actingAs($outro, 'sanctum')
        ->getJson('/api/v1/dashboard?month=2026-08');

    $response->assertOk()
        ->assertJsonPath('data.kpis.income.value', 0)
        ->assertJsonPath('data.kpis.consolidated_balance.value', 0)
        ->assertJsonPath('data.accounts', [])
        ->assertJsonPath('data.cards', [])
        ->assertJsonPath('data.latest_movements', []);
});

it('recusa um mes em formato invalido', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard?month=agosto')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month');
});

it('usa o mes corrente quando nenhum e informado', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-08');
});
