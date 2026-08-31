<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rulePayload(object $context, array $overrides = []): array
{
    return [
        'source_kind' => 'conta',
        'account_id' => $context->account->id,
        'category_id' => $context->moradia->id,
        'description' => 'Aluguel apartamento',
        'amount' => 2900.00,
        'type' => 'despesa',
        'frequency' => 'mensal',
        'interval' => 1,
        'day_of_month' => 10,
        'starts_on' => '2026-01-10',
        'method' => 'boleto',
        ...$overrides,
    ];
}

/** @param array<string, mixed> $overrides */
function ruleFor(object $context, array $overrides = []): Recurrence
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

it('cadastra uma despesa fixa em conta', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/recurrences', rulePayload($this))
        ->assertCreated()
        ->assertJsonPath('data.description', 'Aluguel apartamento')
        ->assertJsonPath('data.source_kind', 'conta')
        ->assertJsonPath('data.is_active', true);

    $rule = Recurrence::query()->ownedBy($this->user->id)->firstOrFail();

    expect($rule->day_of_month)->toBe(10)
        ->and($rule->credit_card_id)->toBeNull()
        // Cadastrar a regra nao lanca nada por conta propria.
        ->and(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('cadastra uma despesa fixa no cartao como credito', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/recurrences', rulePayload($this, [
            'source_kind' => 'cartao',
            'account_id' => null,
            'credit_card_id' => $this->card->id,
            'description' => 'Assinaturas digitais',
            'amount' => 331.40,
            'method' => null,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.source_kind', 'cartao')
        ->assertJsonPath('data.method', 'credito');
});

it('recusa regra sem origem, com as duas, ou fora do dominio', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson('/api/v1/recurrences', rulePayload($this, ['account_id' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account_id');

    $user->postJson('/api/v1/recurrences', rulePayload($this, [
        'credit_card_id' => $this->card->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credit_card_id');

    $user->postJson('/api/v1/recurrences', rulePayload($this, ['type' => 'transferencia']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    $user->postJson('/api/v1/recurrences', rulePayload($this, ['day_of_month' => 31]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('day_of_month');
});

it('recusa receita no cartao e categoria incompativel', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson('/api/v1/recurrences', rulePayload($this, [
        'source_kind' => 'cartao',
        'account_id' => null,
        'credit_card_id' => $this->card->id,
        'type' => 'receita',
        'category_id' => $this->salario->id,
        'method' => null,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    $user->postJson('/api/v1/recurrences', rulePayload($this, ['category_id' => $this->salario->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

it('recusa vigencia invertida e conta de outro usuario', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson('/api/v1/recurrences', rulePayload($this, [
        'starts_on' => '2026-08-10',
        'ends_on' => '2026-07-10',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_on');

    $alheia = Account::factory()->create([
        'user_id' => User::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
    ]);

    $user->postJson('/api/v1/recurrences', rulePayload($this, ['account_id' => $alheia->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account_id');
});

it('edita a regra sem reescrever o que ja foi lancado', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])->assertOk();

    $lancamento = Transaction::query()->ownedBy($this->user->id)->firstOrFail();

    expect((float) $lancamento->amount)->toBe(2900.0);

    $user->putJson("/api/v1/recurrences/{$rule->id}", rulePayload($this, [
        'description' => 'Aluguel reajustado',
        'amount' => 3100.00,
    ]))->assertOk();

    // O lancamento de agosto foi pago com o valor da epoca e nao muda.
    expect((float) $lancamento->fresh()->amount)->toBe(2900.0)
        ->and((float) $rule->fresh()->amount)->toBe(3100.0);
});

it('pausa e retoma uma regra', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs($this->user, 'sanctum');

    $user->patchJson("/api/v1/recurrences/{$rule->id}/status", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    // Pausada nao produz ocorrencia, logo nao ha o que lancar.
    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 0);

    $user->patchJson("/api/v1/recurrences/{$rule->id}/status", ['is_active' => true])->assertOk();

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 1);
});

it('exclui a regra preservando os lancamentos que ela gerou', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])->assertOk();

    $user->deleteJson("/api/v1/recurrences/{$rule->id}")->assertOk();

    $lancamento = Transaction::query()->ownedBy($this->user->id)->firstOrFail();

    expect(Recurrence::query()->ownedBy($this->user->id)->count())->toBe(0)
        // O extrato mantem o historico; so o vinculo com a regra se desfaz.
        ->and($lancamento->recurrence_id)->toBeNull()
        ->and((float) $lancamento->amount)->toBe(2900.0);
});

it('lanca a regra do mes com status conforme a data', function (): void {
    // Regra dia 10: em 15/08 ja passou, entao nasce confirmada.
    $passada = ruleFor($this);
    // Regra dia 25: ainda vai acontecer, entao nasce prevista.
    $futura = ruleFor($this, ['description' => 'Internet', 'amount' => 120.00, 'day_of_month' => 25]);

    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/recurrences/{$passada->id}/launch", ['month' => '2026-08'])->assertOk();
    $user->postJson("/api/v1/recurrences/{$futura->id}/launch", ['month' => '2026-08'])->assertOk();

    $aluguel = Transaction::query()->ownedBy($this->user->id)->where('description', 'Aluguel')->firstOrFail();
    $internet = Transaction::query()->ownedBy($this->user->id)->where('description', 'Internet')->firstOrFail();

    expect($aluguel->status)->toBe(TransactionStatus::Confirmado)
        ->and($aluguel->paid_date?->toDateString())->toBe('2026-08-10')
        ->and($internet->status)->toBe(TransactionStatus::Previsto)
        ->and($internet->paid_date)->toBeNull()
        // Só o confirmado mexe no saldo.
        ->and($this->account->fresh()->currentBalance())->toBe(7100.0);
});

it('nao lanca duas vezes a mesma data', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertJsonPath('data.created', 1);

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('message', 'Nada a lançar: este mês já está em dia.');

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('permite relancar depois que o lancamento gerado e excluido', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])->assertOk();

    $lancamento = Transaction::query()->ownedBy($this->user->id)->firstOrFail();
    $user->deleteJson("/api/v1/transactions/{$lancamento->id}")->assertOk();

    // A verificacao olha os lancamentos existentes, nao um carimbo de data.
    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 1);
});

it('lanca uma linha por ocorrencia numa regra semanal', function (): void {
    $rule = ruleFor($this, [
        'description' => 'Feira',
        'amount' => 100.00,
        'frequency' => RecurrenceFrequency::Semanal,
        'day_of_month' => null,
        'starts_on' => '2026-08-03',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 5);

    $datas = Transaction::query()->ownedBy($this->user->id)
        ->orderBy('competence_date')->pluck('competence_date')
        ->map(fn ($date) => CarbonImmutable::parse((string) $date)->toDateString())->all();

    expect($datas)->toBe(['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31']);
});

it('lanca a regra de cartao como compra de uma parcela na fatura', function (): void {
    $rule = ruleFor($this, [
        'description' => 'Assinaturas digitais',
        'amount' => 331.40,
        'account_id' => null,
        'credit_card_id' => $this->card->id,
        'day_of_month' => 18,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 1);

    $parcela = Installment::query()->ownedBy($this->user->id)->firstOrFail();
    $fatura = Invoice::query()->ownedBy($this->user->id)->firstOrFail();

    expect($parcela->total)->toBe(1)
        ->and((float) $parcela->amount)->toBe(331.40)
        ->and($fatura->reference_month->format('Y-m'))->toBe('2026-08')
        ->and((float) $fatura->total)->toBe(331.40)
        // A compra no cartao nao debita a conta.
        ->and($this->account->fresh()->currentBalance())->toBe(10000.0);
});

it('lanca todas as regras pendentes do mes de uma vez', function (): void {
    ruleFor($this);
    ruleFor($this, ['description' => 'Condomínio', 'amount' => 620.00, 'day_of_month' => 12]);
    ruleFor($this, ['description' => 'Academia', 'amount' => 150.00, 'is_active' => false]);

    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson('/api/v1/recurrences/launch', ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 2);

    // Repetir nao duplica, e a pausada continua de fora.
    $user->postJson('/api/v1/recurrences/launch', ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 0);

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(2);
});

it('registra quando a regra rodou pela ultima vez', function (): void {
    $rule = ruleFor($this);

    expect($rule->last_materialized_on)->toBeNull();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])
        ->assertOk();

    expect($rule->fresh()->last_materialized_on?->toDateString())->toBe('2026-08-10');
});

it('nao alcanca regra de outro usuario', function (): void {
    $rule = ruleFor($this);
    $user = $this->actingAs(User::factory()->create(), 'sanctum');

    $user->getJson("/api/v1/recurrences/{$rule->id}")->assertNotFound();
    $user->deleteJson("/api/v1/recurrences/{$rule->id}")->assertNotFound();
    $user->postJson("/api/v1/recurrences/{$rule->id}/launch", ['month' => '2026-08'])->assertNotFound();
});

it('nao lanca regras de outro usuario no lote', function (): void {
    ruleFor($this);

    $outro = User::factory()->create();

    $this->actingAs($outro, 'sanctum')
        ->postJson('/api/v1/recurrences/launch', ['month' => '2026-08'])
        ->assertOk()
        ->assertJsonPath('data.created', 0);

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa lancar sem informar o mes', function (): void {
    $rule = ruleFor($this);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/recurrences/{$rule->id}/launch", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month');
});
