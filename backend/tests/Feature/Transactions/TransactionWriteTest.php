<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\PayInvoiceAction;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Actions\RecordTransactionAction;
use App\Domain\Ledger\Actions\TransferBetweenAccountsAction;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
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
        'nickname' => 'Conta principal',
        'initial_balance' => 5000,
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
        'payment_account_id' => $this->account->id,
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

it('registra uma despesa em conta', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'despesa',
        'account_id' => $this->account->id,
        'category_id' => $this->mercado->id,
        'description' => 'Feira da semana',
        'amount' => 320.50,
        'competence_date' => '2026-08-10',
        'method' => 'pix',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.kind', 'despesa')
        ->assertJsonPath('data.status', 'confirmado');

    $transaction = Transaction::query()->ownedBy($this->user->id)->firstOrFail();

    expect($transaction->direction->value)->toBe('saida')
        ->and((float) $transaction->amount)->toBe(320.50)
        // Confirmado sem data de pagamento assume a competencia.
        ->and($transaction->paid_date?->toDateString())->toBe('2026-08-10');
});

it('registra uma receita e um lancamento previsto', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'receita',
        'account_id' => $this->account->id,
        'category_id' => $this->salario->id,
        'description' => 'Salário de agosto',
        'amount' => 9000,
        'competence_date' => '2026-08-05',
        'status' => 'previsto',
    ])->assertCreated()->assertJsonPath('data.status', 'previsto');

    $transaction = Transaction::query()->ownedBy($this->user->id)->firstOrFail();

    expect($transaction->direction->value)->toBe('entrada')
        ->and($transaction->type)->toBe(TransactionType::Receita)
        // Previsto nao tem data de pagamento: ainda nao aconteceu.
        ->and($transaction->paid_date)->toBeNull();
});

it('registra uma compra parcelada distribuindo as parcelas nas faturas', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'cartao',
        'credit_card_id' => $this->card->id,
        'category_id' => $this->mercado->id,
        'description' => 'Notebook',
        'amount' => 3000,
        'competence_date' => '2026-08-12',
        'installments' => 3,
    ])->assertCreated()->assertJsonPath('data.installments', 3);

    $parcelas = Installment::query()->ownedBy($this->user->id)->orderBy('number')->get();

    expect($parcelas)->toHaveCount(3)
        ->and($parcelas->sum(fn (Installment $p): float => (float) $p->amount))->toBe(3000.0)
        ->and($parcelas->pluck('competence_date')->map->toDateString()->all())
        ->toBe(['2026-08-12', '2026-09-12', '2026-10-12']);
});

it('registra uma transferencia como par espelhado', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'transferencia',
        'from_account_id' => $this->account->id,
        'to_account_id' => $this->poupanca->id,
        'description' => 'Guardar para a reserva',
        'amount' => 500,
        'competence_date' => '2026-08-18',
    ])->assertCreated()
        ->assertJsonPath('data.kind', 'transferencia')
        ->assertJsonPath('data.from_account_id', $this->account->id)
        ->assertJsonPath('data.to_account_id', $this->poupanca->id);

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(2)
        ->and($this->account->fresh()->currentBalance())->toBe(4500.0)
        ->and($this->poupanca->fresh()->currentBalance())->toBe(500.0);
});

it('edita uma despesa em conta, inclusive trocando de conta', function (): void {
    $transaction = app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Feira',
        amount: 320.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-10'),
        categoryId: $this->mercado->id,
    ));

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/transactions/{$transaction->id}", [
            'account_id' => $this->poupanca->id,
            'category_id' => $this->mercado->id,
            'description' => 'Feira do mês',
            'amount' => 120.00,
            'competence_date' => '2026-08-12',
        ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Feira do mês');

    // Editar nao pode virar um segundo lancamento.
    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(1);

    $atualizado = $transaction->fresh();

    expect($atualizado->account_id)->toBe($this->poupanca->id)
        ->and((float) $atualizado->amount)->toBe(120.00)
        ->and($atualizado->direction->value)->toBe('saida');
});

it('redistribui as parcelas ao editar uma compra no cartao', function (): void {
    $compra = app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
        categoryId: $this->mercado->id,
    ));

    $faturaAgosto = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-08-01')->firstOrFail();

    expect((float) $faturaAgosto->total)->toBe(1000.0);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/transactions/{$compra->id}", [
            'credit_card_id' => $this->card->id,
            'category_id' => $this->mercado->id,
            'description' => 'Notebook e mouse',
            'amount' => 4000.00,
            'competence_date' => '2026-08-12',
            'installments' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.installments', 2);

    $parcelas = Installment::query()->ownedBy($this->user->id)->orderBy('number')->get();

    expect($parcelas)->toHaveCount(2)
        ->and($parcelas->sum(fn (Installment $p): float => (float) $p->amount))->toBe(4000.0)
        ->and((float) $faturaAgosto->fresh()->total)->toBe(2000.0);

    // A fatura de outubro perdeu a terceira parcela e precisa voltar a zero.
    $faturaOutubro = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-10-01')->first();

    expect((float) $faturaOutubro?->total)->toBe(0.0);
});

it('edita os dois lados de uma transferencia', function (): void {
    [$saida] = app(TransferBetweenAccountsAction::class)->handle(new TransferData(
        fromAccountId: $this->account->id,
        toAccountId: $this->poupanca->id,
        amount: 500.00,
        date: CarbonImmutable::parse('2026-08-18'),
    ));

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/transactions/{$saida->id}", [
            'from_account_id' => $this->poupanca->id,
            'to_account_id' => $this->account->id,
            'description' => 'Resgate da reserva',
            'amount' => 200.00,
            'competence_date' => '2026-08-20',
        ])
        ->assertOk();

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(2)
        // A transferencia inverteu: a reserva agora fica negativa em 200.
        ->and($this->poupanca->fresh()->currentBalance())->toBe(-200.0)
        ->and($this->account->fresh()->currentBalance())->toBe(5200.0);
});

it('recusa trocar a natureza do lancamento na edicao', function (): void {
    $transaction = app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Feira',
        amount: 320.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-10'),
    ));

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/transactions/{$transaction->id}", [
            'kind' => 'transferencia',
            'account_id' => $this->account->id,
            'description' => 'Feira',
            'amount' => 320.00,
            'competence_date' => '2026-08-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');
});

it('exclui a compra devolvendo o valor a fatura', function (): void {
    $compra = app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/transactions/{$compra->id}")
        ->assertOk();

    expect(Installment::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and((float) Invoice::query()->ownedBy($this->user->id)
            ->where('reference_month', '2026-08-01')->firstOrFail()->total)->toBe(0.0);
});

it('exclui a transferencia levando o par junto', function (): void {
    [$saida] = app(TransferBetweenAccountsAction::class)->handle(new TransferData(
        fromAccountId: $this->account->id,
        toAccountId: $this->poupanca->id,
        amount: 500.00,
        date: CarbonImmutable::parse('2026-08-18'),
    ));

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/transactions/{$saida->id}")
        ->assertOk();

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and($this->account->fresh()->currentBalance())->toBe(5000.0);
});

it('confirma um lancamento previsto em um clique', function (): void {
    $transaction = app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Aluguel',
        amount: 2500.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-25'),
        status: TransactionStatus::Previsto,
    ));

    // Previsto nao entra no saldo.
    expect($this->account->fresh()->currentBalance())->toBe(5000.0);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/transactions/{$transaction->id}/status", ['status' => 'confirmado'])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmado');

    expect($this->account->fresh()->currentBalance())->toBe(2500.0)
        ->and($transaction->fresh()->paid_date?->toDateString())->toBe('2026-08-25');
});

it('cancela a compra tirando as parcelas do total da fatura', function (): void {
    $compra = app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/transactions/{$compra->id}/status", ['status' => 'cancelado'])
        ->assertOk();

    expect((float) Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-08-01')->firstOrFail()->total)->toBe(0.0)
        // A parcela continua gravada; apenas deixou de contar.
        ->and(Installment::query()->ownedBy($this->user->id)->count())->toBe(3);
});

it('trava a compra com parcela ja paga', function (): void {
    $compra = app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $fatura = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-08-01')->firstOrFail();

    app(PayInvoiceAction::class)->handle($fatura, $this->account, paidAt: CarbonImmutable::parse('2026-09-08'));

    $user = $this->actingAs($this->user, 'sanctum');

    $user->putJson("/api/v1/transactions/{$compra->id}", [
        'credit_card_id' => $this->card->id,
        'description' => 'Notebook',
        'amount' => 4000.00,
        'competence_date' => '2026-08-12',
        'installments' => 3,
    ])->assertUnprocessable();

    $user->deleteJson("/api/v1/transactions/{$compra->id}")->assertUnprocessable();
});

it('nao exclui o pagamento de fatura pela tela de lancamentos', function (): void {
    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $fatura = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-08-01')->firstOrFail();

    app(PayInvoiceAction::class)->handle($fatura, $this->account, paidAt: CarbonImmutable::parse('2026-09-08'));

    $pagamento = Transaction::query()->ownedBy($this->user->id)
        ->whereNotNull('paid_invoice_id')->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/transactions/{$pagamento->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Este lançamento é o pagamento de uma fatura. Desfaça o pagamento na tela de Cartões.');
});

it('recusa categoria incompativel com o tipo do lancamento', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'despesa',
        'account_id' => $this->account->id,
        'category_id' => $this->salario->id,
        'description' => 'Feira',
        'amount' => 100,
        'competence_date' => '2026-08-10',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

it('recusa campos que nao pertencem a aba escolhida', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'despesa',
        'account_id' => $this->account->id,
        'credit_card_id' => $this->card->id,
        'description' => 'Feira',
        'amount' => 100,
        'competence_date' => '2026-08-10',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credit_card_id');

    // Parcelar so faz sentido em despesa e emprestimo: uma receita nao se
    // divide em prestacoes.
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'receita',
        'account_id' => $this->account->id,
        'installments' => 3,
        'description' => 'Salário',
        'amount' => 100,
        'competence_date' => '2026-08-10',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('installments');
});

it('recusa transferencia para a mesma conta', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/transactions', [
        'kind' => 'transferencia',
        'from_account_id' => $this->account->id,
        'to_account_id' => $this->account->id,
        'description' => 'Erro',
        'amount' => 100,
        'competence_date' => '2026-08-10',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_account_id');
});

it('nao alcanca o lancamento de outro usuario', function (): void {
    $transaction = app(RecordTransactionAction::class)->handle(new TransactionData(
        accountId: $this->account->id,
        description: 'Feira',
        amount: 320.00,
        type: TransactionType::Despesa,
        competenceDate: CarbonImmutable::parse('2026-08-10'),
    ));

    $outro = User::factory()->create();
    $user = $this->actingAs($outro, 'sanctum');

    $user->getJson("/api/v1/transactions/{$transaction->id}")->assertNotFound();
    $user->deleteJson("/api/v1/transactions/{$transaction->id}")->assertNotFound();
    $user->patchJson("/api/v1/transactions/{$transaction->id}/status", ['status' => 'cancelado'])
        ->assertNotFound();
});

it('entrega as opcoes de contas, cartoes e categorias do usuario', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/transactions/options')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'accounts' => [['id', 'nickname', 'bank']],
                'cards' => [['id', 'nickname', 'last_four']],
                'categories' => [['id', 'name', 'type']],
                'kinds' => [['value', 'label']],
                'types', 'statuses', 'methods', 'origins',
            ],
        ])
        ->assertJsonCount(2, 'data.accounts')
        ->assertJsonCount(1, 'data.cards');
});
