<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 10000,
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

/** @param array<string, mixed> $overrides */
function makeRule(object $context, array $overrides = []): Recurrence
{
    return Recurrence::factory()->create([
        'user_id' => $context->user->id,
        'account_id' => $context->account->id,
        'credit_card_id' => null,
        'category_id' => $context->moradia->id,
        'description' => 'Aluguel',
        'amount' => 2900.00,
        'type' => TransactionType::Despesa,
        'frequency' => RecurrenceFrequency::Mensal,
        'interval' => 1,
        'day_of_month' => 10,
        'starts_on' => '2026-01-10',
        'ends_on' => null,
        'is_active' => true,
        ...$overrides,
    ]);
}

it('exige autenticacao', function (): void {
    $this->getJson('/api/v1/recurrences')->assertUnauthorized();
});

it('devolve o painel e a lista de regras', function (): void {
    makeRule($this);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'expense_total', 'income_total', 'net_total',
                    'active_count', 'paused_count', 'pending_count', 'pending_amount',
                ],
                'recurrences' => [[
                    'id', 'description', 'amount', 'monthly_amount', 'type',
                    'frequency', 'frequency_label', 'schedule_label', 'starts_on',
                    'is_active', 'source', 'source_kind', 'category',
                    'occurrences', 'launched', 'pending', 'next_date',
                ]],
            ],
        ]);
});

it('separa o total fixo de receita e de despesa', function (): void {
    makeRule($this);
    makeRule($this, [
        'description' => 'Salário',
        'amount' => 9000.00,
        'type' => TransactionType::Receita,
        'category_id' => $this->salario->id,
        'day_of_month' => 5,
    ]);

    $summary = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08')
        ->json('data.summary');

    expect((float) $summary['expense_total'])->toBe(2900.0)
        ->and((float) $summary['income_total'])->toBe(9000.0)
        ->and((float) $summary['net_total'])->toBe(6100.0)
        ->and($summary['active_count'])->toBe(2);
});

it('converte frequencias diferentes para um valor mensal comparavel', function (): void {
    // Semanal de 100: cerca de 4,33 ocorrencias por mes.
    makeRule($this, [
        'description' => 'Feira',
        'amount' => 100.00,
        'frequency' => RecurrenceFrequency::Semanal,
        'day_of_month' => null,
        'starts_on' => '2026-08-03',
    ]);

    $row = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08')
        ->json('data.recurrences.0');

    expect((float) $row['amount'])->toBe(100.0)
        ->and((float) $row['monthly_amount'])->toBe(433.33)
        ->and($row['occurrences'])->toBe(5);
});

it('conta o que ainda falta lancar no mes', function (): void {
    $rule = makeRule($this);

    $user = $this->actingAs($this->user, 'sanctum');

    $antes = $user->getJson('/api/v1/recurrences?month=2026-08')->json('data.recurrences.0');

    expect($antes['pending'])->toBe(1)
        ->and($antes['launched'])->toBe(0)
        ->and($antes['next_date'])->toBe('2026-08-10');

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])->assertOk();

    $depois = $user->getJson('/api/v1/recurrences?month=2026-08')->json('data.recurrences.0');

    expect($depois['pending'])->toBe(0)
        ->and($depois['launched'])->toBe(1);
});

it('descreve o agendamento em texto legivel', function (): void {
    makeRule($this, ['day_of_month' => 28]);
    makeRule($this, [
        'description' => 'IPTU',
        'frequency' => RecurrenceFrequency::Anual,
        'day_of_month' => null,
        'starts_on' => '2026-03-14',
    ]);
    makeRule($this, [
        'description' => 'Diarista',
        'frequency' => RecurrenceFrequency::Semanal,
        'interval' => 2,
        'day_of_month' => null,
        'starts_on' => '2026-08-03',
    ]);

    $rows = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08')
        ->json('data.recurrences');

    $labels = array_combine(array_column($rows, 'description'), array_column($rows, 'schedule_label'));

    expect($labels['Aluguel'])->toBe('Todo dia 28')
        ->and($labels['IPTU'])->toBe('Todo ano em março')
        ->and($labels['Diarista'])->toBe('A cada 2 semanas');
});

it('mostra a regra pausada mas a tira dos totais', function (): void {
    makeRule($this);
    makeRule($this, ['description' => 'Academia', 'amount' => 150.00, 'is_active' => false]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08');

    expect($response->json('data.recurrences'))->toHaveCount(2)
        ->and($response->json('data.summary.paused_count'))->toBe(1)
        // A pausada nao soma no total fixo do mes.
        ->and((float) $response->json('data.summary.expense_total'))->toBe(2900.0);

    $semPausadas = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08&paused=0');

    expect($semPausadas->json('data.recurrences'))->toHaveCount(1);
});

it('nao vaza regra de outro usuario', function (): void {
    makeRule($this);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/recurrences?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.recurrences', [])
        ->assertJsonPath('data.summary.active_count', 0);
});

it('entrega contas, cartoes, categorias e frequencias para o formulario', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/recurrences/options')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'accounts' => [['id', 'nickname']],
                'cards' => [['id', 'nickname']],
                'categories' => [['id', 'name', 'type']],
                'frequencies' => [['value', 'label']],
                'methods', 'types',
            ],
        ])
        ->assertJsonCount(4, 'data.frequencies')
        ->assertJsonCount(2, 'data.types');
});
