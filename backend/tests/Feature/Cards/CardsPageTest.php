<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 10000,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'payment_account_id' => $this->account->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 10000,
        'is_active' => true,
    ]);
});

it('exige autenticacao', function (): void {
    $this->getJson('/api/v1/cards')->assertUnauthorized();
});

it('devolve o painel e a lista de cartoes', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/cards?month=2026-08')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'limit_total', 'used_total', 'available_total', 'usage_percent',
                    'outstanding_total', 'active_count', 'archived_count',
                ],
                'cards' => [[
                    'id', 'nickname', 'brand', 'brand_label', 'last_four', 'color', 'bank',
                    'limit_amount', 'used_amount', 'available_amount', 'usage_percent',
                    'is_active', 'closing_day', 'due_day', 'payment_account', 'current_invoice',
                ]],
            ],
        ]);
});

it('calcula limite usado a partir das parcelas em aberto', function (): void {
    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/cards?month=2026-08');

    // O limite fica preso pela compra inteira, nao so pela parcela do mes.
    expect((float) $response->json('data.cards.0.used_amount'))->toBe(3000.0)
        ->and((float) $response->json('data.cards.0.available_amount'))->toBe(7000.0)
        ->and((float) $response->json('data.summary.used_total'))->toBe(3000.0);
});

it('aponta a proxima fatura a vencer', function (): void {
    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 1200.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
    ));

    $nextDue = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/cards?month=2026-08')
        ->json('data.summary.next_due');

    expect($nextDue['card'])->toBe('Nubank Ultravioleta')
        ->and((float) $nextDue['remaining'])->toBe(1200.0)
        ->and($nextDue['due_date'])->toBe('2026-09-08');
});

it('esconde cartao arquivado por padrao e o mostra quando pedido', function (): void {
    CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Cartão antigo',
        'is_active' => false,
        'limit_amount' => 4000,
    ]);

    $user = $this->actingAs($this->user, 'sanctum');

    $padrao = $user->getJson('/api/v1/cards?month=2026-08');

    expect($padrao->json('data.cards'))->toHaveCount(1)
        ->and($padrao->json('data.summary.archived_count'))->toBe(0);

    $comArquivados = $user->getJson('/api/v1/cards?month=2026-08&archived=1');

    expect($comArquivados->json('data.cards'))->toHaveCount(2)
        ->and($comArquivados->json('data.summary.archived_count'))->toBe(1)
        // O limite do arquivado nao entra no total disponivel para gastar.
        ->and((float) $comArquivados->json('data.summary.limit_total'))->toBe(10000.0);
});

it('nao vaza cartao de outro usuario', function (): void {
    $outro = User::factory()->create();

    $this->actingAs($outro, 'sanctum')
        ->getJson('/api/v1/cards?month=2026-08')
        ->assertOk()
        ->assertJsonPath('data.cards', [])
        ->assertJsonPath('data.summary.active_count', 0);
});

it('recusa um mes em formato invalido', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/cards?month=agosto')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month');
});

it('lista o historico de faturas do cartao', function (): void {
    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $this->card->id,
        description: 'Notebook',
        amount: 3000.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
        installments: 3,
    ));

    $invoices = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/cards/{$this->card->id}/invoices")
        ->assertOk()
        ->json('data');

    // Tres faturas, da mais recente para a mais antiga.
    expect($invoices)->toHaveCount(3)
        ->and(array_column($invoices, 'reference_month'))->toBe(['2026-10', '2026-09', '2026-08'])
        ->and((float) $invoices[2]['total'])->toBe(1000.0)
        ->and($invoices[2]['items_count'])->toBe(1);
});
