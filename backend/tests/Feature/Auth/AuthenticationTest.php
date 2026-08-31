<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\Category;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('registra um usuario e devolve o token de acesso', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Rafael Machado',
        'email' => 'rafael@exemplo.com',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email', 'initials'], 'token'])
        ->assertJsonPath('user.email', 'rafael@exemplo.com')
        ->assertJsonPath('user.initials', 'RM');

    $this->assertDatabaseHas('users', ['email' => 'rafael@exemplo.com']);
});

it('entrega o plano de categorias padrao ao novo usuario', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Nova Conta',
        'email' => 'nova@exemplo.com',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ])->assertCreated();

    $user = User::query()->where('email', 'nova@exemplo.com')->sole();

    $categories = Category::query()->ownedBy($user->id)->get();

    expect($categories)->not->toBeEmpty()
        ->and($categories->pluck('name'))->toContain('Moradia', 'Salário')
        ->and($categories->every(fn (Category $category): bool => $category->is_system))->toBeTrue();
});

it('recusa registro com dados invalidos', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'A',
        'email' => 'nao-e-email',
        'password' => '123',
        'password_confirmation' => '456',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('recusa registro com e-mail ja cadastrado', function (): void {
    User::factory()->create(['email' => 'repetido@exemplo.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Outro',
        'email' => 'repetido@exemplo.com',
        'password' => 'senha12345',
        'password_confirmation' => 'senha12345',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('autentica com credenciais validas', function (): void {
    User::factory()->create(['email' => 'login@exemplo.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@exemplo.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

it('recusa credenciais invalidas sem revelar qual campo falhou', function (): void {
    User::factory()->create(['email' => 'login@exemplo.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'login@exemplo.com',
        'password' => 'senha-errada',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('devolve o usuario autenticado', function (): void {
    $user = User::factory()->create(['name' => 'Ana Souza']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana Souza')
        ->assertJsonPath('data.initials', 'AS');
});

it('bloqueia rotas protegidas sem token', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Não autenticado.');
});

it('encerra a sessao invalidando o token usado', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect(PersonalAccessToken::query()->count())->toBe(0);

    // Em producao cada requisicao sobe a aplicacao do zero; dentro de um teste
    // o guard do Sanctum sobrevive entre chamadas e devolveria o usuario que
    // ja tinha resolvido. Esquecer os guards reproduz o ciclo real.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('limita tentativas repetidas de login', function (): void {
    User::factory()->create(['email' => 'alvo@exemplo.com']);

    foreach (range(1, 6) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'alvo@exemplo.com',
            'password' => 'senha-errada',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'alvo@exemplo.com',
        'password' => 'senha-errada',
    ])->assertStatus(429);
});
