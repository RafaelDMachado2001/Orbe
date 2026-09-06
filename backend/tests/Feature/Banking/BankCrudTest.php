<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bankPayload(array $overrides = []): array
{
    return [
        'name' => 'Nubank',
        'color' => '#A05BE0',
        'kind' => 'digital',
        ...$overrides,
    ];
}

it('cadastra um banco', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/banks', bankPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Nubank')
        ->assertJsonPath('data.slug', 'nubank')
        ->assertJsonPath('data.kind_label', 'Banco digital');

    expect(Bank::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('cadastra dinheiro em especie como instituicao', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/banks', bankPayload([
            'name' => 'Dinheiro em espécie',
            'kind' => 'carteira',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.kind_label', 'Dinheiro e carteiras');
});

it('recusa o mesmo banco duas vezes, ignorando caixa e espacos', function (): void {
    Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/banks', bankPayload(['name' => '  NUBANK ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('deixa outro usuario cadastrar um banco de mesmo nome', function (): void {
    Bank::factory()->create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/banks', bankPayload())
        ->assertCreated();
});

it('recusa cor e tipo invalidos', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/banks', bankPayload(['color' => 'roxo', 'kind' => 'cooperativa']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['color', 'kind']);
});

it('renomeia e recolore o banco', function (): void {
    $bank = Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/banks/{$bank->id}", bankPayload([
            'name' => 'Nubank PJ',
            'color' => '#7C3FBF',
            'kind' => 'digital',
        ]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Nubank PJ')
        ->assertJsonPath('data.slug', 'nubank-pj')
        ->assertJsonPath('data.color', '#7C3FBF');
});

it('permite salvar o banco sem trocar o nome', function (): void {
    $bank = Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/banks/{$bank->id}", bankPayload(['color' => '#35D68A']))
        ->assertOk()
        ->assertJsonPath('data.color', '#35D68A');
});

it('exclui banco vazio', function (): void {
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/banks/{$bank->id}")
        ->assertOk();

    expect(Bank::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('recusa excluir banco com conta ou cartao', function (): void {
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    Account::factory()->create(['user_id' => $this->user->id, 'bank_id' => $bank->id]);
    CreditCard::factory()->create(['user_id' => $this->user->id, 'bank_id' => $bank->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/banks/{$bank->id}")
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Este banco ainda tem uma conta e um cartão. Exclua ou mova esses registros antes, porque apagar o banco levaria o extrato junto.',
        );

    expect(Bank::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('conta a conta arquivada como uso do banco', function (): void {
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/banks/{$bank->id}")
        ->assertUnprocessable();
});

it('nao alcanca banco de outro usuario', function (): void {
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $user = $this->actingAs(User::factory()->create(), 'sanctum');

    $user->putJson("/api/v1/banks/{$bank->id}", bankPayload())->assertNotFound();
    $user->deleteJson("/api/v1/banks/{$bank->id}")->assertNotFound();
});

it('entrega catalogo, bancos e enums para o formulario', function (): void {
    Bank::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Nubank',
        'slug' => 'nubank',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/accounts/options')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'banks' => [['id', 'name', 'slug', 'color', 'kind', 'kind_label']],
                'catalog' => [['slug', 'name', 'color', 'kind', 'kind_label', 'is_registered']],
                'account_types' => [['value', 'label']],
                'bank_kinds' => [['value', 'label']],
            ],
        ]);

    /** @var list<array<string, mixed>> $entries */
    $entries = $response->json('data.catalog');
    $catalog = collect($entries);

    // O banco ja cadastrado chega marcado, para a tela nao oferecer de novo.
    expect($catalog->firstWhere('slug', 'nubank')['is_registered'])->toBeTrue()
        ->and($catalog->firstWhere('slug', 'itau')['is_registered'])->toBeFalse()
        ->and($response->json('data.bank_kinds'))->toHaveCount(4);
});
