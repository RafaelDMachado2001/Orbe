<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $this->bank = Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function accountPayload(object $context, array $overrides = []): array
{
    return [
        'bank_id' => $context->bank->id,
        'nickname' => 'Conta corrente',
        'type' => 'corrente',
        'initial_balance' => 2500.50,
        ...$overrides,
    ];
}

it('cadastra uma conta com o saldo de partida', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounts', accountPayload($this))
        ->assertCreated()
        ->assertJsonPath('data.nickname', 'Conta corrente')
        ->assertJsonPath('data.type_label', 'Conta corrente')
        ->assertJsonPath('data.initial_balance', 2500.5)
        ->assertJsonPath('data.balance', 2500.5)
        ->assertJsonPath('data.is_active', true);

    expect(Account::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('aceita saldo de partida negativo, para conta no vermelho', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounts', accountPayload($this, ['initial_balance' => -320.10]))
        ->assertCreated()
        ->assertJsonPath('data.balance', -320.1);
});

it('recusa dados invalidos da conta', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounts', accountPayload($this, [
            'nickname' => '',
            'type' => 'poupancinha',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nickname', 'type']);
});

it('recusa banco de outro usuario', function (): void {
    $alheio = Bank::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/accounts', accountPayload($this, ['bank_id' => $alheio->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bank_id');
});

it('edita apelido, tipo e banco da conta', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'initial_balance' => 1000,
    ]);

    $outroBanco = Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Itaú',
        'slug' => 'itau',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/accounts/{$account->id}", [
            'bank_id' => $outroBanco->id,
            'nickname' => 'Conta salário',
            'type' => 'poupanca',
        ])
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Conta salário')
        ->assertJsonPath('data.type', 'poupanca')
        ->assertJsonPath('data.bank_id', $outroBanco->id)
        // O saldo de partida continua onde estava.
        ->assertJsonPath('data.initial_balance', 1000);
});

it('recusa reescrever o saldo inicial pela edicao', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'initial_balance' => 1000,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/accounts/{$account->id}", accountPayload($this, [
            'initial_balance' => 90000,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('initial_balance');

    expect((float) $account->refresh()->initial_balance)->toBe(1000.0);
});

it('arquiva e reativa uma conta', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $user = $this->actingAs($this->user, 'sanctum');

    $user->patchJson("/api/v1/accounts/{$account->id}/archive", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($user->getJson('/api/v1/accounts')->json('data.banks.0.accounts'))->toBe([]);

    $user->patchJson("/api/v1/accounts/{$account->id}/archive", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($user->getJson('/api/v1/accounts')->json('data.banks.0.accounts'))->toHaveCount(1);
});

it('exclui conta que nunca recebeu movimento', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/accounts/{$account->id}")
        ->assertOk();

    expect(Account::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa excluir conta com extrato, orientando a arquivar', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/accounts/{$account->id}")
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Esta conta tem um lançamento no extrato. Arquive-a em vez de excluir, para não apagar o histórico já registrado.',
        );

    expect(Account::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('recusa excluir conta que paga a fatura de um cartao', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'payment_account_id' => $account->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/accounts/{$account->id}")
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Um cartão paga a fatura por esta conta. Aponte outra conta de pagamento nele antes de excluí-la.',
        );
});

it('recusa excluir conta usada por despesa fixa', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    Recurrence::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/accounts/{$account->id}")
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Uma despesa fixa lança nesta conta. Mude a conta dessa regra antes de excluí-la, senão ela ficaria sem destino.',
        );
});

it('nao alcanca conta de outro usuario', function (): void {
    $account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
    ]);

    $user = $this->actingAs(User::factory()->create(), 'sanctum');

    $user->getJson("/api/v1/accounts/{$account->id}")->assertNotFound();
    $user->deleteJson("/api/v1/accounts/{$account->id}")->assertNotFound();
    $user->patchJson("/api/v1/accounts/{$account->id}/archive", ['is_active' => false])->assertNotFound();
});
