<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 10000,
    ]);

    $this->categoria = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Equipamento',
        'type' => CategoryType::Despesa,
    ]);

    $this->emprestimos = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Empréstimos e financiamentos',
        'type' => CategoryType::Despesa,
        'is_system' => true,
        'system_key' => 'emprestimos',
    ]);

    $this->plan = fn (array $overrides = []): array => [
        'kind' => 'despesa',
        'account_id' => $this->account->id,
        'category_id' => $this->categoria->id,
        'description' => 'Notebook',
        'amount' => 3600,
        'competence_date' => '2026-09-05',
        'installments' => 12,
        'method' => 'boleto',
        ...$overrides,
    ];
});

it('divide a despesa em um lancamento por mes', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)())
        ->assertCreated()
        ->assertJsonPath('data.installments', 12)
        ->assertJsonPath('data.installment_number', 1);

    $rows = Transaction::query()->ownedBy($this->user->id)
        ->orderBy('competence_date')
        ->get();

    expect($rows)->toHaveCount(12)
        ->and($rows->pluck('installment_group_id')->unique())->toHaveCount(1)
        ->and((float) $rows->sum('amount'))->toBe(3600.0)
        ->and($rows->first()->competence_date->toDateString())->toBe('2026-09-05')
        ->and($rows->last()->competence_date->toDateString())->toBe('2027-08-05')
        ->and($rows->last()->installment_number)->toBe(12);
});

it('confirma so a parcela ja vencida e deixa as futuras previstas', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)([
            'competence_date' => '2026-08-05',
            'installments' => 3,
            'amount' => 300,
        ]))
        ->assertCreated();

    $rows = Transaction::query()->ownedBy($this->user->id)->orderBy('competence_date')->get();

    // Hoje e 02/09: a de agosto ja venceu, setembro e outubro nao.
    expect($rows->pluck('status')->all())->toBe([
        TransactionStatus::Confirmado,
        TransactionStatus::Previsto,
        TransactionStatus::Previsto,
    ]);
});

it('aceita o valor digitado como o da parcela', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)([
            'amount' => 480,
            'installments' => 24,
            'amount_mode' => 'parcela',
        ]))
        ->assertCreated();

    $rows = Transaction::query()->ownedBy($this->user->id)->get();

    expect($rows)->toHaveCount(24)
        ->and((float) $rows->sum('amount'))->toBe(11520.0)
        ->and((float) $rows->first()->amount)->toBe(480.0);
});

it('distribui o residuo nas primeiras parcelas', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)([
            'amount' => 100,
            'installments' => 3,
        ]))
        ->assertCreated();

    $amounts = Transaction::query()->ownedBy($this->user->id)
        ->orderBy('installment_number')
        ->pluck('amount')
        ->map(static fn (string $amount): float => (float) $amount);

    expect($amounts->all())->toBe([33.34, 33.33, 33.33])
        ->and($amounts->sum())->toBe(100.0);
});

it('mostra a parcela no extrato com o rotulo do plano', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)(['installments' => 3, 'amount' => 300]))
        ->assertCreated();

    $rows = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-09-01&to=2026-11-30')
        ->assertOk()
        ->json('data.items');

    expect($rows)->toHaveCount(3)
        ->and(array_column($rows, 'installment'))->toBe(['3/3', '2/3', '1/3']);
});

it('redistribui o plano ao editar o numero de parcelas', function (): void {
    $created = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)(['installments' => 12, 'amount' => 1200]))
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/transactions/{$created}", [
            'account_id' => $this->account->id,
            'category_id' => $this->categoria->id,
            'description' => 'Notebook',
            'amount' => 1200,
            'competence_date' => '2026-09-05',
            'installments' => 6,
            'method' => 'boleto',
        ])
        ->assertOk()
        ->assertJsonPath('data.installments', 6);

    $rows = Transaction::query()->ownedBy($this->user->id)->orderBy('installment_number')->get();

    expect($rows)->toHaveCount(6)
        ->and((float) $rows->sum('amount'))->toBe(1200.0)
        ->and((float) $rows->first()->amount)->toBe(200.0);
});

it('exclui o parcelamento inteiro a partir de uma parcela', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)(['installments' => 5, 'amount' => 500]))
        ->assertCreated();

    $terceira = Transaction::query()->ownedBy($this->user->id)
        ->where('installment_number', 3)
        ->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/transactions/{$terceira->id}")
        ->assertOk();

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('registra um emprestimo com credor e categoria propria', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'emprestimo',
        'account_id' => $this->account->id,
        'category_id' => $this->emprestimos->id,
        'description' => 'Empréstimo pessoal',
        'lender' => 'Banco Azul',
        'amount' => 480,
        'amount_mode' => 'parcela',
        'installments' => 24,
        'competence_date' => '2026-10-10',
        'method' => 'debito',
    ])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'emprestimo')
        ->assertJsonPath('data.lender', 'Banco Azul');

    $rows = Transaction::query()->ownedBy($this->user->id)->get();

    expect($rows)->toHaveCount(24)
        ->and($rows->every(fn (Transaction $row): bool => $row->is_loan))->toBeTrue()
        ->and($rows->every(fn (Transaction $row): bool => $row->lender === 'Banco Azul'))->toBeTrue()
        // Todas as 24 estao no futuro: nenhuma nasce confirmada.
        ->and($rows->every(fn (Transaction $row): bool => $row->status === TransactionStatus::Previsto))->toBeTrue();

    // Reabrir o emprestimo devolve o total, nao a prestacao.
    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/transactions/{$response->json('data.id')}")
        ->assertOk()
        ->assertJsonPath('data.amount', 11520)
        ->assertJsonPath('data.installments', 24);
});

it('exige o credor do emprestimo', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'emprestimo',
        'account_id' => $this->account->id,
        'description' => 'Empréstimo pessoal',
        'amount' => 480,
        'installments' => 24,
        'competence_date' => '2026-10-10',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lender');
});

it('recusa credor em lancamento que nao e emprestimo', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/transactions', ($this->plan)(['lender' => 'Banco Azul']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lender');
});
