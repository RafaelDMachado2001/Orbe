<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\PayInvoiceAction;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Queries\MonthlyTotalsQuery;
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
        'payment_account_id' => $this->account->id,
        'closing_day' => 28,
        'due_day' => 8,
    ]);

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Mercado',
        amount: 1200.00,
        purchaseDate: CarbonImmutable::parse('2026-06-10'),
    ));

    $this->invoice = Invoice::query()
        ->withoutUserScope()
        ->where('credit_card_id', $this->card->id)
        ->firstOrFail();
});

it('debita a conta e baixa a fatura', function (): void {
    $payment = app(PayInvoiceAction::class)->handle(
        $this->invoice,
        $this->account,
        paidAt: CarbonImmutable::parse('2026-07-08'),
    );

    $this->invoice->refresh();

    expect($this->invoice->status)->toBe(InvoiceStatus::Paga)
        ->and((float) $this->invoice->paid_amount)->toBe(1200.0)
        ->and($this->invoice->remaining())->toBe(0.0)
        ->and($payment->type)->toBe(TransactionType::Transferencia)
        ->and($this->account->fresh()->currentBalance())->toBe(8800.0);
});

it('libera o limite do cartao ao quitar a fatura', function (): void {
    app(PayInvoiceAction::class)->handle($this->invoice, $this->account);

    $unpaid = $this->invoice->installments()->withoutUserScope()->where('is_paid', false)->count();

    expect($unpaid)->toBe(0);
});

it('nao contabiliza o pagamento da fatura como despesa do mes', function (): void {
    app(PayInvoiceAction::class)->handle(
        $this->invoice,
        $this->account,
        paidAt: CarbonImmutable::parse('2026-07-08'),
    );

    $totals = app(MonthlyTotalsQuery::class)->handle(
        $this->user->id,
        CarbonImmutable::parse('2026-07-01'),
        CarbonImmutable::parse('2026-07-01'),
    );

    // A compra ja foi contada como despesa em junho; julho fica zerado.
    expect($totals['2026-07']->expense)->toBe(0.0);
});

it('aceita pagamento parcial e mantem a fatura em aberto', function (): void {
    app(PayInvoiceAction::class)->handle($this->invoice, $this->account, amount: 500.00);

    $this->invoice->refresh();

    expect($this->invoice->status)->toBe(InvoiceStatus::Fechada)
        ->and($this->invoice->remaining())->toBe(700.0);
});

it('recusa pagar duas vezes a mesma fatura', function (): void {
    app(PayInvoiceAction::class)->handle($this->invoice, $this->account);

    app(PayInvoiceAction::class)->handle($this->invoice->refresh(), $this->account);
})->throws(RuntimeException::class, 'Esta fatura ja foi paga.');

it('recusa pagamento maior que o saldo da fatura', function (): void {
    app(PayInvoiceAction::class)->handle($this->invoice, $this->account, amount: 5000.00);
})->throws(RuntimeException::class);
