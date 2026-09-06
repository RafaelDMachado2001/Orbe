<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Planning\Models\Goal;
use App\Domain\Planning\Models\GoalContribution;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('cadastra uma meta sem conta vinculada', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/goals', [
            'name' => 'Viagem',
            'target_amount' => 5000,
            'initial_amount' => 1000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Viagem')
        ->assertJsonPath('data.target_amount', 5000)
        ->assertJsonPath('data.initial_amount', 1000)
        ->assertJsonPath('data.current_amount', 1000)
        ->assertJsonPath('data.progress', 20)
        ->assertJsonPath('data.account_id', null);
});

it('recusa valor alvo zero ou negativo', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/goals', ['name' => 'Viagem', 'target_amount' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_amount');
});

it('recusa editar o valor inicial depois da criacao', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/goals/{$goal->id}", [
            'name' => $goal->name,
            'target_amount' => $goal->target_amount,
            'initial_amount' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('initial_amount');
});

it('edita nome, alvo, prazo e conta sem mexer no valor inicial', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id, 'initial_amount' => 500]);
    $account = Account::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/goals/{$goal->id}", [
            'name' => 'Nova meta',
            'target_amount' => 8000,
            'deadline' => '2027-01-01',
            'account_id' => $account->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nova meta')
        ->assertJsonPath('data.target_amount', 8000)
        ->assertJsonPath('data.initial_amount', 500)
        ->assertJsonPath('data.account_id', $account->id);
});

it('arquiva e reativa uma meta', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/goals/{$goal->id}/archive", ['is_archived' => true])
        ->assertOk()
        ->assertJsonPath('data.is_archived', true);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/goals/{$goal->id}/archive", ['is_archived' => false])
        ->assertOk()
        ->assertJsonPath('data.is_archived', false);
});

it('exclui uma meta e leva o historico de aportes junto', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);
    GoalContribution::factory()->create(['user_id' => $this->user->id, 'goal_id' => $goal->id]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/goals/{$goal->id}")
        ->assertOk();

    expect(Goal::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and(GoalContribution::query()->ownedBy($this->user->id)->count())->toBe(0);
});

it('nao alcanca meta de outro usuario', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    $other = $this->actingAs(User::factory()->create(), 'sanctum');

    $other->putJson("/api/v1/goals/{$goal->id}", ['name' => 'x', 'target_amount' => 100])
        ->assertNotFound();
    $other->deleteJson("/api/v1/goals/{$goal->id}")->assertNotFound();
});

it('registra um aporte manual em meta sem conta vinculada', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id, 'account_id' => null, 'initial_amount' => 100, 'target_amount' => 1000]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/goals/{$goal->id}/contributions", [
            'amount' => 300,
            'contributed_at' => '2026-09-05',
            // O frontend sempre manda a chave, com null quando o campo fica
            // vazio — não pode virar erro de "precisa ser inteiro".
            'source_account_id' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.current_amount', 400)
        ->assertJsonPath('data.progress', 40);

    $contribution = GoalContribution::query()->ownedBy($this->user->id)->sole();
    expect($contribution->transaction_id)->toBeNull()
        ->and((float) $contribution->amount)->toBe(300.0);
});

it('recusa aporte sem origem quando a meta tem conta vinculada', function (): void {
    $account = Account::factory()->create(['user_id' => $this->user->id]);
    $goal = Goal::factory()->create(['user_id' => $this->user->id, 'account_id' => $account->id]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/goals/{$goal->id}/contributions", ['amount' => 300])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_account_id');
});

it('recusa origem igual a conta da propria meta', function (): void {
    $account = Account::factory()->create(['user_id' => $this->user->id]);
    $goal = Goal::factory()->create(['user_id' => $this->user->id, 'account_id' => $account->id]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/goals/{$goal->id}/contributions", [
            'amount' => 300,
            'source_account_id' => $account->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_account_id');
});

it('aporte com conta vinculada gera uma transferencia de verdade', function (): void {
    $source = Account::factory()->create(['user_id' => $this->user->id, 'initial_balance' => 5000]);
    $goalAccount = Account::factory()->create(['user_id' => $this->user->id, 'initial_balance' => 0]);
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $goalAccount->id,
        'initial_amount' => 0,
        'target_amount' => 1000,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/goals/{$goal->id}/contributions", [
            'amount' => 400,
            'source_account_id' => $source->id,
            'contributed_at' => '2026-09-05',
        ])
        ->assertCreated()
        ->assertJsonPath('data.current_amount', 400);

    expect($source->refresh()->currentBalance())->toBe(4600.0)
        ->and($goalAccount->refresh()->currentBalance())->toBe(400.0)
        ->and(Transaction::query()->ownedBy($this->user->id)->where('type', 'transferencia')->count())->toBe(2);

    $contribution = GoalContribution::query()->ownedBy($this->user->id)->sole();
    expect($contribution->transaction_id)->not->toBeNull();
});

it('desfaz um aporte e reverte a transferencia junto', function (): void {
    $source = Account::factory()->create(['user_id' => $this->user->id, 'initial_balance' => 5000]);
    $goalAccount = Account::factory()->create(['user_id' => $this->user->id, 'initial_balance' => 0]);
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $goalAccount->id,
        'initial_amount' => 0,
        'target_amount' => 1000,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/goals/{$goal->id}/contributions", [
            'amount' => 400,
            'source_account_id' => $source->id,
        ])
        ->assertCreated();

    $contribution = GoalContribution::query()->ownedBy($this->user->id)->sole();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/goals/{$goal->id}/contributions/{$contribution->id}")
        ->assertOk();

    expect(GoalContribution::query()->ownedBy($this->user->id)->count())->toBe(0)
        ->and($source->refresh()->currentBalance())->toBe(5000.0)
        ->and($goalAccount->refresh()->currentBalance())->toBe(0.0)
        ->and(Transaction::query()->ownedBy($this->user->id)->where('type', 'transferencia')->count())->toBe(0);
});

it('lista o historico de aportes do mais recente para o mais antigo', function (): void {
    $goal = Goal::factory()->create(['user_id' => $this->user->id]);

    GoalContribution::factory()->create(['user_id' => $this->user->id, 'goal_id' => $goal->id, 'contributed_at' => '2026-08-01', 'amount' => 100]);
    GoalContribution::factory()->create(['user_id' => $this->user->id, 'goal_id' => $goal->id, 'contributed_at' => '2026-09-01', 'amount' => 200]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/goals/{$goal->id}/contributions")
        ->assertOk()
        ->assertJsonPath('data.0.amount', 200)
        ->assertJsonPath('data.1.amount', 100);
});
