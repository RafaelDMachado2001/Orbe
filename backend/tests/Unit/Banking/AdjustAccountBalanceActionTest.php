<?php

declare(strict_types=1);

use App\Domain\Banking\Actions\AdjustAccountBalanceAction;
use App\Domain\Banking\DTOs\BalanceAdjustmentData;
use App\Domain\Banking\Exceptions\BalanceAdjustmentException;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\SystemCategory;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-15 09:00:00'));

    $this->user = User::factory()->create();
    $bank = Bank::factory()->create(['user_id' => $this->user->id]);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 1000,
    ]);

    $this->entrada = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Ajuste de saldo',
        'type' => CategoryType::Receita,
        'is_system' => true,
        'system_key' => SystemCategory::AjusteEntrada,
    ]);

    $this->saida = Category::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Ajuste de saldo',
        'type' => CategoryType::Despesa,
        'is_system' => true,
        'system_key' => SystemCategory::AjusteSaida,
    ]);

    $this->action = app(AdjustAccountBalanceAction::class);
});

function adjust(object $context, float $balance, ?string $date = null): Transaction
{
    return $context->action->handle($context->account, new BalanceAdjustmentData(
        targetBalance: $balance,
        date: CarbonImmutable::parse($date ?? '2026-08-15'),
    ));
}

it('lanca a diferenca como entrada quando o banco mostra mais', function (): void {
    $adjustment = adjust($this, 1250.75);

    expect($adjustment->type)->toBe(TransactionType::Receita)
        ->and($adjustment->direction)->toBe(MovementDirection::Entrada)
        ->and((float) $adjustment->amount)->toBe(250.75)
        ->and($adjustment->status)->toBe(TransactionStatus::Confirmado)
        ->and($adjustment->category_id)->toBe($this->entrada->id)
        ->and($adjustment->description)->toBe('Ajuste de saldo')
        // O saldo passa a ser exatamente o informado.
        ->and($this->account->refresh()->currentBalance())->toBe(1250.75);
});

it('lanca a diferenca como saida quando o banco mostra menos', function (): void {
    $adjustment = adjust($this, 820.00);

    expect($adjustment->type)->toBe(TransactionType::Despesa)
        ->and($adjustment->direction)->toBe(MovementDirection::Saida)
        ->and((float) $adjustment->amount)->toBe(180.0)
        ->and($adjustment->category_id)->toBe($this->saida->id)
        ->and($this->account->refresh()->currentBalance())->toBe(820.0);
});

it('recusa ajuste sem diferenca', function (): void {
    adjust($this, 1000.00);
})->throws(BalanceAdjustmentException::class, 'O saldo informado é o que a conta já tem. Nada a ajustar.');

it('nao dobra a correcao quando o mesmo ajuste e enviado duas vezes', function (): void {
    adjust($this, 1500.00);

    expect($this->account->refresh()->currentBalance())->toBe(1500.0);

    // O alvo ja foi alcancado: o segundo envio nao tem diferenca a lancar.
    expect(fn (): Transaction => adjust($this, 1500.00))
        ->toThrow(BalanceAdjustmentException::class);

    expect(Transaction::query()->ownedBy($this->user->id)->count())->toBe(1);
});

it('recusa ajustar conta arquivada', function (): void {
    $this->account->forceFill(['is_active' => false])->save();

    adjust($this, 2000.00);
})->throws(BalanceAdjustmentException::class, 'Esta conta está arquivada. Reative-a para ajustar o saldo.');

it('compara com o saldo da data do ajuste, nao com o de hoje', function (): void {
    // Uma saida posterior a data do ajuste nao entra na conta da diferenca.
    Transaction::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'amount' => 200,
        'type' => TransactionType::Despesa,
        'direction' => MovementDirection::Saida,
        'status' => TransactionStatus::Confirmado,
        'competence_date' => '2026-08-20',
    ]);

    $adjustment = adjust($this, 1100.00, '2026-08-10');

    expect((float) $adjustment->amount)->toBe(100.0)
        ->and($adjustment->competence_date->toDateString())->toBe('2026-08-10')
        // 1000 de partida, +100 de ajuste, -200 da saida do dia 20.
        ->and($this->account->refresh()->currentBalance())->toBe(900.0);
});

it('ignora lancamento previsto ao calcular a diferenca', function (): void {
    Transaction::factory()->forecasted()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'amount' => 500,
        'competence_date' => '2026-08-05',
    ]);

    $adjustment = adjust($this, 1300.00);

    // O previsto nao move saldo, entao a diferenca e sobre os 1000 iniciais.
    expect((float) $adjustment->amount)->toBe(300.0);
});
