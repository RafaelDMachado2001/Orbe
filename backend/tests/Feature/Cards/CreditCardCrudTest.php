<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
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
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cardPayload(object $context, array $overrides = []): array
{
    return [
        'bank_id' => $context->bank->id,
        'payment_account_id' => $context->account->id,
        'nickname' => 'Nubank Ultravioleta',
        'brand' => 'mastercard',
        'last_four' => '4417',
        'limit_amount' => 12000,
        'closing_day' => 28,
        'due_day' => 8,
        'color' => '#A07CFF',
        ...$overrides,
    ];
}

it('cadastra um cartao', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/cards', cardPayload($this))
        ->assertCreated()
        ->assertJsonPath('data.nickname', 'Nubank Ultravioleta')
        ->assertJsonPath('data.brand_label', 'Mastercard')
        ->assertJsonPath('data.is_active', true);

    $card = CreditCard::query()->ownedBy($this->user->id)->firstOrFail();

    expect($card->closing_day)->toBe(28)
        ->and($card->payment_account_id)->toBe($this->account->id)
        // Cadastrar cartao nao inventa faturas vazias.
        ->and(Invoice::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa dia de ciclo que nao existe em todo mes', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/cards', cardPayload($this, ['closing_day' => 31]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('closing_day');
});

it('recusa dados invalidos do cartao', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/cards', cardPayload($this, [
            'last_four' => '12',
            'brand' => 'diners',
            'nickname' => '',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['last_four', 'brand', 'nickname']);
});

it('recusa banco de outro usuario', function (): void {
    $alheio = Bank::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/cards', cardPayload($this, ['bank_id' => $alheio->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bank_id');
});

it('edita o cartao e recalcula as datas das faturas em aberto', function (): void {
    $card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'closing_day' => 28,
        'due_day' => 8,
        'limit_amount' => 10000,
    ]);

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $card->id,
        description: 'Notebook',
        amount: 900.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
    ));

    $invoice = Invoice::query()->ownedBy($this->user->id)->firstOrFail();

    expect($invoice->closing_date->toDateString())->toBe('2026-08-28');

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/cards/{$card->id}", cardPayload($this, [
            'nickname' => 'Nubank Roxinho',
            'closing_day' => 10,
            'due_day' => 20,
            'limit_amount' => 15000,
        ]))
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Nubank Roxinho');

    $invoice->refresh();

    expect($invoice->closing_date->toDateString())->toBe('2026-08-10')
        ->and($invoice->due_date->toDateString())->toBe('2026-08-20')
        // A parcela nao muda de fatura: o banco ja cobrou naquele ciclo.
        ->and((float) $invoice->total)->toBe(900.0);
});

it('arquiva e reativa um cartao', function (): void {
    $card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $user = $this->actingAs($this->user, 'sanctum');

    $user->patchJson("/api/v1/cards/{$card->id}/archive", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    // Arquivar preserva o cartao e some da listagem padrao.
    expect($user->getJson('/api/v1/cards')->json('data.cards'))->toHaveCount(0);

    $user->patchJson("/api/v1/cards/{$card->id}/archive", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($user->getJson('/api/v1/cards')->json('data.cards'))->toHaveCount(1);
});

it('exclui cartao que nunca foi usado', function (): void {
    $card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/cards/{$card->id}")
        ->assertOk();

    expect(CreditCard::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa excluir cartao com compra, orientando a arquivar', function (): void {
    $card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    app(RegisterCardPurchaseAction::class)->handle(new CardPurchaseData(
        creditCardId: $card->id,
        description: 'Notebook',
        amount: 900.00,
        purchaseDate: CarbonImmutable::parse('2026-08-12'),
    ));

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/cards/{$card->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Este cartão tem uma compra no histórico. Arquive-o em vez de excluir, para não apagar os lançamentos já registrados.');

    expect(CreditCard::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('nao alcanca cartao de outro usuario', function (): void {
    $card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $outro = User::factory()->create();
    $user = $this->actingAs($outro, 'sanctum');

    $user->getJson("/api/v1/cards/{$card->id}")->assertNotFound();
    $user->deleteJson("/api/v1/cards/{$card->id}")->assertNotFound();
    $user->patchJson("/api/v1/cards/{$card->id}/archive", ['is_active' => false])->assertNotFound();
});

it('entrega bancos, contas e bandeiras para o formulario', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/cards/options')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'banks' => [['id', 'name', 'color', 'kind_label']],
                'accounts' => [['id', 'nickname', 'bank']],
                'brands' => [['value', 'label']],
            ],
        ])
        ->assertJsonCount(1, 'data.banks')
        ->assertJsonCount(1, 'data.accounts');
});
