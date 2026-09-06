<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->user->id, 'initial_balance' => 1000]);
    $this->category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa, 'name' => 'Mercado']);
});

function income(User $user, Account $account, float $amount, string $date): Transaction
{
    return Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => null,
        'amount' => $amount,
        'type' => TransactionType::Receita,
        'direction' => MovementDirection::Entrada,
        'competence_date' => $date,
        'paid_date' => $date,
    ]);
}

function expense(User $user, Account $account, Category $category, float $amount, string $date): Transaction
{
    return Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => $amount,
        'type' => TransactionType::Despesa,
        'direction' => MovementDirection::Saida,
        'competence_date' => $date,
        'paid_date' => $date,
    ]);
}

it('monta o relatorio anual com totais, saldo e ranking de categorias', function (): void {
    income($this->user, $this->account, 5000, '2026-01-10');
    expense($this->user, $this->account, $this->category, 1200, '2026-01-15');

    income($this->user, $this->account, 5000, '2026-02-10');
    expense($this->user, $this->account, $this->category, 800, '2026-02-15');

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/reports/annual?year=2026')
        ->assertOk()
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.total_income', 10000)
        ->assertJsonPath('data.total_expense', 2000)
        ->assertJsonPath('data.balance', 8000)
        ->assertJsonCount(12, 'data.months');

    expect($response->json('data.months.0.month'))->toBe('2026-01')
        ->and($response->json('data.months.0.income'))->toBe(5000)
        ->and($response->json('data.months.11.month'))->toBe('2026-12')
        ->and($response->json('data.category_ranking.0.name'))->toBe('Mercado')
        ->and($response->json('data.category_ranking.0.total'))->toBe(2000)
        ->and($response->json('data.category_ranking.0.percentage'))->toBe(100);

    // Saldo consolidado no fim de janeiro: 1000 (inicial) + 5000 - 1200 = 4800.
    expect($response->json('data.balance_series.0'))->toBe(4800);
});

it('usa o ano corrente quando nenhum e informado', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/reports/annual')
        ->assertOk()
        ->assertJsonPath('data.year', (int) now()->format('Y'));
});

it('compara com o ano anterior', function (): void {
    income($this->user, $this->account, 4000, '2025-06-10');
    expense($this->user, $this->account, $this->category, 1000, '2025-06-15');

    income($this->user, $this->account, 6000, '2026-06-10');
    expense($this->user, $this->account, $this->category, 1000, '2026-06-15');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/reports/annual?year=2026')
        ->assertOk()
        ->assertJsonPath('data.previous_year_balance', 3000)
        // (5000 - 3000) / 3000 * 100 = 66.7
        ->assertJsonPath('data.year_over_year', 66.7);
});

it('nao vaza relatorio de outro usuario', function (): void {
    $other = User::factory()->create();
    $otherAccount = Account::factory()->create(['user_id' => $other->id]);
    income($other, $otherAccount, 9999, '2026-03-01');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/reports/annual?year=2026')
        ->assertOk()
        ->assertJsonPath('data.total_income', 0);
});

it('exporta o relatorio anual em csv', function (): void {
    income($this->user, $this->account, 5000, '2026-01-10');
    expense($this->user, $this->account, $this->category, 1200, '2026-01-15');

    $response = $this->actingAs($this->user, 'sanctum')
        ->get('/api/v1/reports/annual/export?year=2026&format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('relatorio-anual-2026.csv')
        ->and($response->streamedContent())->toContain('Mercado');
});

it('exporta o relatorio anual em pdf', function (): void {
    income($this->user, $this->account, 5000, '2026-01-10');

    $response = $this->actingAs($this->user, 'sanctum')
        ->get('/api/v1/reports/annual/export?year=2026&format=pdf')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

it('recusa formato de exportacao invalido', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/reports/annual/export?year=2026&format=xml')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});
