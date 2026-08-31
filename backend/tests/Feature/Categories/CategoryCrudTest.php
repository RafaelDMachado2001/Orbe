<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-02 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
    ]);

    $this->moradia = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Moradia',
        'type' => CategoryType::Despesa,
    ]);
});

it('lista as categorias em arvore com o total do mes', function (): void {
    $aluguel = Category::factory()->create([
        'user_id' => $this->user->id,
        'parent_id' => $this->moradia->id,
        'name' => 'Aluguel',
        'type' => CategoryType::Despesa,
    ]);

    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $aluguel->id,
        'amount' => 2200,
        'competence_date' => '2026-09-05',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/categories?month=2026-09')
        ->assertOk();

    /** @var list<array<string, mixed>> $categories */
    $categories = $response->json('data.categories');
    $moradia = array_values(array_filter(
        $categories,
        fn (array $node): bool => $node['id'] === $this->moradia->id,
    ))[0];

    expect($moradia['children'])->toHaveCount(1)
        ->and($moradia['children'][0]['name'])->toBe('Aluguel')
        ->and((float) $moradia['children'][0]['month_total'])->toBe(2200.0)
        // A mae nao gastou nada sozinha, mas soma o que a filha gastou.
        ->and((float) $moradia['month_total'])->toBe(0.0)
        ->and((float) $moradia['total_with_children'])->toBe(2200.0);
});

it('cria uma subcategoria', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/categories', [
        'name' => 'Condomínio',
        'type' => 'despesa',
        'color' => '#A07CFF',
        'parent_id' => $this->moradia->id,
    ])->assertCreated()->assertJsonPath('data.parent_id', $this->moradia->id);
});

it('recusa uma subcategoria de tipo diferente da mae', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/categories', [
        'name' => 'Bônus',
        'type' => 'receita',
        'color' => '#35D68A',
        'parent_id' => $this->moradia->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

it('recusa um terceiro nivel na arvore', function (): void {
    $aluguel = Category::factory()->create([
        'user_id' => $this->user->id,
        'parent_id' => $this->moradia->id,
        'name' => 'Aluguel',
    ]);

    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/categories', [
        'name' => 'IPTU',
        'type' => 'despesa',
        'color' => '#A07CFF',
        'parent_id' => $aluguel->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

it('recusa duas categorias com o mesmo nome no mesmo nivel', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/categories', [
        'name' => 'Moradia',
        'type' => 'despesa',
        'color' => '#A07CFF',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('renomeia e recolore uma categoria do sistema, sem mudar o tipo', function (): void {
    $sistema = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Empréstimos e financiamentos',
        'type' => CategoryType::Despesa,
        'is_system' => true,
    ]);

    $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/categories/{$sistema->id}", [
        'name' => 'Financiamentos',
        'type' => 'receita',
        'color' => '#FF8A3D',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Financiamentos')
        ->assertJsonPath('data.type', 'despesa');
});

it('recusa excluir uma categoria do sistema', function (): void {
    $sistema = Category::factory()->create([
        'user_id' => $this->user->id,
        'is_system' => true,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$sistema->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Categorias do sistema não podem ser excluídas. Renomeie-a se o nome não serve.');
});

it('exige destino para excluir categoria em uso', function (): void {
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $this->moradia->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$this->moradia->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta categoria tem 1 registro(s) classificado(s). Escolha para qual categoria movê-los.');
});

it('move lancamentos, despesas fixas e subcategorias ao excluir', function (): void {
    $outros = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Outros',
        'type' => CategoryType::Despesa,
    ]);

    $aluguel = Category::factory()->create([
        'user_id' => $this->user->id,
        'parent_id' => $this->moradia->id,
        'name' => 'Aluguel',
    ]);

    $transaction = Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $this->moradia->id,
    ]);

    $recurrence = Recurrence::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $this->moradia->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$this->moradia->id}", ['reassign_to' => $outros->id])
        ->assertOk();

    expect(Category::query()->find($this->moradia->id))->toBeNull()
        ->and($transaction->refresh()->category_id)->toBe($outros->id)
        ->and($recurrence->refresh()->category_id)->toBe($outros->id)
        // A subcategoria nao e apagada junto: ela passa a pertencer ao destino.
        ->and($aluguel->refresh()->parent_id)->toBe($outros->id);
});

it('recusa mover para categoria de outro tipo', function (): void {
    $receita = Category::factory()->income()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/categories/{$this->moradia->id}", ['reassign_to' => $receita->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A categoria de destino precisa ser do mesmo tipo.');
});

it('nao alcanca categoria de outro usuario', function (): void {
    $alheia = Category::factory()->create(['user_id' => User::factory()->create()->id]);

    $user = $this->actingAs($this->user, 'sanctum');

    $user->getJson("/api/v1/categories/{$alheia->id}")->assertNotFound();
    $user->putJson("/api/v1/categories/{$alheia->id}", [
        'name' => 'Sequestrada',
        'type' => 'despesa',
        'color' => '#A07CFF',
    ])->assertNotFound();
    $user->deleteJson("/api/v1/categories/{$alheia->id}")->assertNotFound();
});
