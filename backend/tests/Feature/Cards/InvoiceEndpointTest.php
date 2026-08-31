<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Installment;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));

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
        'payment_account_id' => $this->account->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 10000,
    ]);

    $this->categoria = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Equipamento',
        'type' => CategoryType::Despesa,
    ]);

    // Compra de 12/08: cai na fatura de agosto (fecha 28/08, vence 08/09).
    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
        categoryId: $this->categoria->id,
    ));

    $this->invoice = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-08-01')->firstOrFail();
});

it('abre o detalhe da fatura com o que a compoe', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/invoices/{$this->invoice->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'card', 'reference_month', 'total', 'paid_amount', 'remaining',
                'status', 'status_label', 'closing_date', 'due_date',
                'items' => [['id', 'transaction_id', 'description', 'amount', 'installment', 'category']],
                'payments', 'can_pay', 'can_close', 'can_undo',
            ],
        ]);

    expect($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.items.0.installment'))->toBe('1/3')
        ->and((float) $response->json('data.items.0.amount'))->toBe(1000.0)
        ->and($response->json('data.items.0.category.name'))->toBe('Equipamento')
        ->and((float) $response->json('data.total'))->toBe(1000.0)
        ->and($response->json('data.can_pay'))->toBeTrue()
        ->and($response->json('data.can_undo'))->toBeFalse();
});

it('paga a fatura inteira, debitando a conta e liberando o limite', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'account_id' => $this->account->id,
            'paid_at' => '2026-09-08',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'paga')
        ->assertJsonPath('data.can_pay', false)
        ->assertJsonPath('data.can_undo', true);

    expect((float) $response->json('data.remaining'))->toBe(0.0)
        ->and($this->account->fresh()->currentBalance())->toBe(9000.0)
        // A parcela quitada devolve limite ao cartao.
        ->and(Installment::query()->ownedBy($this->user->id)->where('is_paid', true)->count())->toBe(1);
});

it('aceita pagamento parcial e mantem o saldo devedor', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'account_id' => $this->account->id,
            'amount' => 400,
        ]);

    $response->assertOk()->assertJsonPath('data.status', 'fechada');

    expect((float) $response->json('data.remaining'))->toBe(600.0)
        ->and($response->json('data.can_pay'))->toBeTrue()
        ->and($response->json('data.payments'))->toHaveCount(1);
});

it('desfaz o ultimo pagamento devolvendo saldo e limite', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
        'account_id' => $this->account->id,
        'paid_at' => '2026-09-08',
    ])->assertOk();

    $response = $user->deleteJson("/api/v1/invoices/{$this->invoice->id}/payments");

    $response->assertOk()->assertJsonPath('data.can_undo', false);

    expect((float) $response->json('data.paid_amount'))->toBe(0.0)
        ->and((float) $response->json('data.remaining'))->toBe(1000.0)
        // A fatura ja tinha fechado em 28/08, entao volta para fechada.
        ->and($response->json('data.status'))->toBe('fechada')
        ->and($this->account->fresh()->currentBalance())->toBe(10000.0)
        ->and(Installment::query()->ownedBy($this->user->id)->where('is_paid', true)->count())->toBe(0)
        ->and(Transaction::query()->ownedBy($this->user->id)->whereNotNull('paid_invoice_id')->count())->toBe(0);
});

it('desfaz um pagamento parcial por vez', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    foreach ([300, 200] as $amount) {
        $user->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'account_id' => $this->account->id,
            'amount' => $amount,
        ])->assertOk();
    }

    $response = $user->deleteJson("/api/v1/invoices/{$this->invoice->id}/payments");

    // Some so o pagamento de 200, o ultimo lancado.
    expect((float) $response->json('data.paid_amount'))->toBe(300.0)
        ->and((float) $response->json('data.remaining'))->toBe(700.0)
        ->and($response->json('data.payments'))->toHaveCount(1);
});

it('recusa desfazer quando nao ha pagamento', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$this->invoice->id}/payments")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta fatura não tem pagamento registrado para desfazer.');
});

it('recusa pagar mais que o saldo da fatura', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'account_id' => $this->account->id,
            'amount' => 5000,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O valor do pagamento excede o saldo da fatura.');
});

it('recusa pagar fatura ja quitada', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
        'account_id' => $this->account->id,
    ])->assertOk();

    $user->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
        'account_id' => $this->account->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta fatura ja foi paga.');
});

it('fecha a fatura cujo dia de fechamento ja passou', function (): void {
    $setembro = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-09-01')->firstOrFail();

    expect($setembro->status)->toBe(InvoiceStatus::Aberta);

    // A de agosto ja passou do fechamento (28/08) e hoje e 02/09.
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$this->invoice->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'fechada');
});

it('antecipa o fechamento de uma fatura que ainda nao venceu', function (): void {
    $setembro = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-09-01')->firstOrFail();

    // Hoje e 02/09 e setembro so fecharia em 28/09.
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$setembro->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'fechada');
});

it('manda para outubro a compra feita depois de setembro fechar antes da hora', function (): void {
    $setembro = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-09-01')->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$setembro->id}/close")
        ->assertOk();

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Cadeira',
        amount: 800.00,
        purchaseDate: CarbonImmutable::parse('2026-09-02'),
        categoryId: $this->categoria->id,
    ));

    $outubro = Invoice::query()->ownedBy($this->user->id)
        ->where('reference_month', '2026-10-01')->firstOrFail();

    expect(Installment::query()->ownedBy($this->user->id)
        ->where('invoice_id', $outubro->id)
        ->whereHas('transaction', fn ($query) => $query->where('description', 'Cadeira'))
        ->exists())->toBeTrue();
});

it('recusa fechar uma fatura ja fechada', function (): void {
    $user = $this->actingAs($this->user, 'sanctum');

    $user->postJson("/api/v1/invoices/{$this->invoice->id}/close")->assertOk();

    $user->postJson("/api/v1/invoices/{$this->invoice->id}/close")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta fatura já está fechada.');
});

it('fecha por convencao as faturas vencidas ao abrir a tela de cartoes', function (): void {
    expect($this->invoice->status)->toBe(InvoiceStatus::Aberta);

    // A tela de cartoes nao pede o fechamento: ela so le. A fatura de agosto
    // fechou em 28/08 e nao pode aparecer como aberta em 02/09.
    $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/cards')->assertOk();

    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Fechada)
        // Setembro ainda nao chegou ao fechamento e continua aberta.
        ->and(Invoice::query()->ownedBy($this->user->id)
            ->where('reference_month', '2026-09-01')->firstOrFail()->status)
        ->toBe(InvoiceStatus::Aberta);
});

it('nao alcanca fatura de outro usuario', function (): void {
    $outro = User::factory()->create();
    $user = $this->actingAs($outro, 'sanctum');

    $user->getJson("/api/v1/invoices/{$this->invoice->id}")->assertNotFound();
    $user->postJson("/api/v1/invoices/{$this->invoice->id}/payments", ['account_id' => 1])
        ->assertNotFound();
    $user->deleteJson("/api/v1/invoices/{$this->invoice->id}/payments")->assertNotFound();
});

it('recusa pagar com conta de outro usuario', function (): void {
    $alheia = Account::factory()->create([
        'user_id' => User::factory()->create()->id,
        'bank_id' => Bank::factory()->create()->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", ['account_id' => $alheia->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account_id');
});
