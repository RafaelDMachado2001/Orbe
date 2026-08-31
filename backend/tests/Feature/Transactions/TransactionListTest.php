<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\Actions\TransferBetweenAccountsAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Conta principal',
        'initial_balance' => 1000,
    ]);

    $this->poupanca = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Reserva',
        'initial_balance' => 0,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 10000,
    ]);

    $this->mercado = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Mercado',
        'type' => CategoryType::Despesa,
    ]);

    $this->salario = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Salário',
        'type' => CategoryType::Receita,
    ]);
});

function seedExtrato(object $context): void
{
    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $context->account->id,
        description: 'Salário de agosto',
        amount: 9000.00,
        type: TransactionType::Receita,
        competenceDate: CarbonImmutable::parse('2026-08-05'),
        categoryId: $context->salario->id,
    ));

    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $context->account->id,
        description: 'Feira da semana',
        amount: 320.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-10'),
        categoryId: $context->mercado->id,
    ));

    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $context->account->id,
        description: 'Aluguel previsto',
        amount: 2500.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-25'),
        status: TransactionStatus::Previsto,
    ));

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $context->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
        categoryId: $context->mercado->id,
    ));

    app(TransferBetweenAccountsAction::class)->handle(new TransferData(
        fromAccountId: $context->account->id,
        toAccountId: $context->poupanca->id,
        amount: 500.00,
        date: CarbonImmutable::parse('2026-08-18'),
        description: 'Guardar para a reserva',
    ));
}

it('exige autenticacao', function (): void {
    $this->getJson('/api/v1/transactions')->assertUnauthorized();
});

it('devolve a estrutura de pagina, resumo e metadados', function (): void {
    seedExtrato($this);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'items' => [['id', 'transaction_id', 'origin', 'description', 'amount', 'direction',
                    'type', 'status', 'status_label', 'date', 'category', 'source', 'installment',
                    'is_recurring', 'is_editable']],
                'summary' => ['income', 'expense', 'net', 'entries'],
                'meta' => ['page', 'per_page', 'total', 'last_page', 'has_more'],
            ],
        ]);
});

it('une lancamentos de conta e parcelas de cartao na mesma lista', function (): void {
    seedExtrato($this);

    $items = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->json('data.items');

    // Salario, feira, aluguel previsto, 2 lados da transferencia e 1 parcela.
    expect($items)->toHaveCount(6);

    /** @var array<int, array<string, mixed>> $items */
    $notebook = collect($items)->firstWhere('description', 'Notebook');

    expect($notebook['origin'])->toBe('cartao')
        ->and($notebook['installment'])->toBe('1/3')
        ->and((float) $notebook['amount'])->toBe(1000.0)
        ->and($notebook['source'])->toBe('Nubank Ultravioleta');
});

it('nao lista a compra-mae junto das parcelas', function (): void {
    seedExtrato($this);

    $items = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->json('data.items');

    /** @var array<int, array<string, mixed>> $items */
    expect(collect($items)->where('description', 'Notebook'))->toHaveCount(1);
});

it('soma os totais do periodo inteiro, sem transferencia', function (): void {
    seedExtrato($this);

    $summary = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->json('data.summary');

    // Saidas = feira 320 + aluguel previsto 2.500 + parcela 1.000. A
    // transferencia de 500 move saldo, mas nao e despesa.
    expect((float) $summary['income'])->toBe(9000.0)
        ->and((float) $summary['expense'])->toBe(3820.0)
        ->and((float) $summary['net'])->toBe(5180.0)
        ->and($summary['entries'])->toBe(6);
});

it('ordena do mais recente para o mais antigo', function (): void {
    seedExtrato($this);

    $items = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->json('data.items');

    $dates = array_column($items, 'date');

    expect($dates)->toBe(array_reverse(collect($dates)->sort()->values()->all()));
});

it('filtra por origem, tipo e status', function (): void {
    seedExtrato($this);

    $user = $this->actingAs($this->user, 'sanctum');

    $cartao = $user->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&origins[]=cartao')
        ->json('data.items');

    expect($cartao)->toHaveCount(1)
        ->and($cartao[0]['origin'])->toBe('cartao');

    $receitas = $user->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&types[]=receita')
        ->json('data.items');

    expect($receitas)->toHaveCount(1)
        ->and($receitas[0]['description'])->toBe('Salário de agosto');

    $previstos = $user->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&statuses[]=previsto')
        ->json('data.items');

    expect($previstos)->toHaveCount(1)
        ->and($previstos[0]['description'])->toBe('Aluguel previsto');
});

it('filtra por categoria, conta e cartao', function (): void {
    seedExtrato($this);

    $user = $this->actingAs($this->user, 'sanctum');

    $mercado = $user->getJson("/api/v1/transactions?from=2026-08-01&to=2026-08-31&categories[]={$this->mercado->id}")
        ->json('data.items');

    // A feira e a parcela do notebook compartilham a categoria.
    expect($mercado)->toHaveCount(2);

    $porCartao = $user->getJson("/api/v1/transactions?from=2026-08-01&to=2026-08-31&credit_card_id={$this->card->id}")
        ->json('data.items');

    expect($porCartao)->toHaveCount(1);

    $porConta = $user->getJson("/api/v1/transactions?from=2026-08-01&to=2026-08-31&account_id={$this->poupanca->id}")
        ->json('data.items');

    expect($porConta)->toHaveCount(1)
        ->and($porConta[0]['direction'])->toBe('entrada');
});

it('busca pela descricao sem tratar curinga como operador', function (): void {
    seedExtrato($this);

    app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Desconto de 50% na farmácia',
        amount: 40.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-14'),
    ));

    $user = $this->actingAs($this->user, 'sanctum');

    $encontrados = $user->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&search=feira')
        ->json('data.items');

    expect($encontrados)->toHaveCount(1)
        ->and($encontrados[0]['description'])->toBe('Feira da semana');

    $curinga = $user->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&search=50%25')
        ->json('data.items');

    expect($curinga)->toHaveCount(1)
        ->and($curinga[0]['description'])->toBe('Desconto de 50% na farmácia');
});

it('pagina mantendo os totais do periodo', function (): void {
    seedExtrato($this);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31&per_page=2&page=2');

    expect($response->json('data.items'))->toHaveCount(2)
        ->and($response->json('data.meta.total'))->toBe(6)
        ->and($response->json('data.meta.last_page'))->toBe(3)
        ->and($response->json('data.meta.has_more'))->toBeTrue()
        ->and((float) $response->json('data.summary.income'))->toBe(9000.0);
});

it('respeita o recorte de datas', function (): void {
    seedExtrato($this);

    $items = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-11')
        ->json('data.items');

    expect(array_column($items, 'description'))
        ->toBe(['Feira da semana', 'Salário de agosto']);
});

it('cai no mes corrente quando o periodo nao e informado', function (): void {
    seedExtrato($this);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 6);
});

it('nao vaza lancamento de outro usuario', function (): void {
    seedExtrato($this);

    $outro = User::factory()->create();

    $this->actingAs($outro, 'sanctum')
        ->getJson('/api/v1/transactions?from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.summary.income', 0);
});

it('recusa periodo invertido e filtro fora do dominio', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->getJson('/api/v1/transactions?from=2026-08-31&to=2026-08-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    $user->getJson('/api/v1/transactions?types[]=investimento')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('types.0');
});

it('recusa filtrar por conta de outro usuario', function (): void {
    $alheia = Account::factory()->create([
        'user_id' => User::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/transactions?account_id={$alheia->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account_id');
});
