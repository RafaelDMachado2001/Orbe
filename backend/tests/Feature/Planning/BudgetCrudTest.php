<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Planning\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('cadastra um orcamento', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 800,
        ])
        ->assertCreated()
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.reference_month', '2026-09')
        ->assertJsonPath('data.limit_amount', 800);

    expect(Budget::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('recusa categoria de receita', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Receita]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 800,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

it('recusa limite zero ou negativo', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('limit_amount');
});

it('recusa dois orcamentos para a mesma categoria no mesmo mes', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);

    Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'reference_month' => '2026-09-01',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 500,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id')
        ->assertJsonPath('errors.category_id.0', 'Esta categoria já tem um orçamento neste mês.');
});

it('permite a mesma categoria em meses diferentes', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);

    Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'reference_month' => '2026-08-01',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 500,
        ])
        ->assertCreated();
});

it('permite salvar o mesmo orcamento sem trocar a categoria', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);

    $budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'reference_month' => '2026-09-01',
        'limit_amount' => 500,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/budgets/{$budget->id}", [
            'category_id' => $category->id,
            'reference_month' => '2026-09',
            'limit_amount' => 650,
        ])
        ->assertOk()
        ->assertJsonPath('data.limit_amount', 650);
});

it('exclui um orcamento', function (): void {
    $budget = Budget::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/budgets/{$budget->id}")
        ->assertOk();

    expect(Budget::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('nao alcanca orcamento de outro usuario', function (): void {
    $budget = Budget::factory()->create(['user_id' => $this->user->id]);

    $other = $this->actingAs(User::factory()->create(), 'sanctum');

    $other->putJson("/api/v1/budgets/{$budget->id}", [
        'category_id' => $budget->category_id,
        'reference_month' => '2026-09',
        'limit_amount' => 100,
    ])->assertNotFound();

    $other->deleteJson("/api/v1/budgets/{$budget->id}")->assertNotFound();
});

it('lista orcamentos do mes com gasto real, percentual e estouro', function (): void {
    $category = Category::factory()->create(['user_id' => $this->user->id, 'type' => CategoryType::Despesa]);
    $account = Account::factory()->create(['user_id' => $this->user->id]);

    $budget = Budget::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'reference_month' => '2026-09-01',
        'limit_amount' => 200,
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 250,
        'competence_date' => CarbonImmutable::parse('2026-09-10')->toDateString(),
        'paid_date' => CarbonImmutable::parse('2026-09-10')->toDateString(),
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/budgets?month=2026-09')
        ->assertOk()
        ->assertJsonPath('data.0.id', $budget->id)
        ->assertJsonPath('data.0.limit_amount', 200)
        ->assertJsonPath('data.0.spent', 250)
        ->assertJsonPath('data.0.percentage', 125)
        ->assertJsonPath('data.0.is_exceeded', true);
});
