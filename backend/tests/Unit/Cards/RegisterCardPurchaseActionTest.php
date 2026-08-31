<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => Bank::factory()->create(['user_id' => $this->user->id])->id,
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 12000,
    ]);
    $this->action = app(RegisterCardPurchaseAction::class);
});

it('distribui as parcelas em faturas consecutivas a partir do mes de fechamento', function (): void {
    $purchase = $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook Dell',
        amount: 7499.00,
        purchaseDate: CarbonImmutable::parse('2026-06-24'),
        installments: 10,
    ));

    $installments = $purchase->installments()->withoutUserScope()->orderBy('number')->get();

    expect($installments)->toHaveCount(10)
        ->and($installments->first()->competence_date->format('Y-m'))->toBe('2026-06')
        ->and($installments->last()->competence_date->format('Y-m'))->toBe('2027-03')
        ->and($installments->first()->label())->toBe('1/10');
});

it('empurra a compra feita a partir do fechamento para a fatura seguinte', function (): void {
    $purchase = $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Compra tardia',
        amount: 300.00,
        purchaseDate: CarbonImmutable::parse('2026-06-28'),
    ));

    $first = $purchase->installments()->withoutUserScope()->orderBy('number')->first();

    expect($first->competence_date->format('Y-m'))->toBe('2026-07');
});

it('nao perde centavos ao parcelar um valor indivisivel', function (): void {
    $purchase = $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Curso online',
        amount: 100.00,
        purchaseDate: CarbonImmutable::parse('2026-06-10'),
        installments: 3,
    ));

    $amounts = $purchase->installments()->withoutUserScope()->orderBy('number')->pluck('amount');

    expect($amounts->map(fn ($value): string => (string) $value)->all())
        ->toBe(['33.34', '33.33', '33.33'])
        ->and(round($amounts->sum(), 2))->toBe(100.0);
});

it('soma o total de cada fatura a partir das parcelas alocadas', function (): void {
    $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Geladeira',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-06-10'),
        installments: 3,
    ));

    $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Mercado',
        amount: 450.00,
        purchaseDate: CarbonImmutable::parse('2026-06-11'),
    ));

    $june = Invoice::query()
        ->withoutUserScope()
        ->where('credit_card_id', $this->card->id)
        ->where('reference_month', '2026-06-01')
        ->firstOrFail();

    expect((float) $june->total)->toBe(1450.0)
        ->and($june->closing_date->toDateString())->toBe('2026-06-28')
        ->and($june->due_date->toDateString())->toBe('2026-07-08');
});

it('marca a compra como mae para nao dobrar o valor nos relatorios', function (): void {
    $purchase = $this->action->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'TV',
        amount: 2400.00,
        purchaseDate: CarbonImmutable::parse('2026-06-10'),
        installments: 12,
    ));

    expect($purchase->is_installment_parent)->toBeTrue()
        ->and((float) $purchase->amount)->toBe(2400.0);
});

it('recusa uma compra sem parcelas ou com valor invalido', function (): void {
    expect(fn () => new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Invalida',
        amount: 100.0,
        purchaseDate: CarbonImmutable::now(),
        installments: 0,
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Invalida',
        amount: 0.0,
        purchaseDate: CarbonImmutable::now(),
    ))->toThrow(InvalidArgumentException::class);
});
